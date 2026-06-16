<?php

namespace App\Services;

use App\Jobs\AgregarFaturamentoJob;
use App\Jobs\CalcularRoyaltiesJob;
use App\Jobs\ProcessarEdiJob;
use App\Jobs\SincronizarEdiDataJob;
use App\Jobs\SincronizarEdiPeriodoJob;
use App\Models\EdiMovimento;
use App\Models\Estabelecimento;
use App\Support\EdiTransacaoCategoria;
use App\Support\PlatformSettings;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EdiProcessadorService
{
    public function buscarEdiPorData(
        CarbonInterface $data,
        string $tipoMovimento = 'transactional',
        ?int $estabelecimentoIdFiltro = null,
    ): bool {
        if (! PlatformSettings::ediConfigurado()) {
            return false;
        }

        try {
            $response = $this->clienteEdi()
                ->get("/movement/v3.00/{$tipoMovimento}/{$data->format('Y-m-d')}", $this->queryPaginacao(1));
        } catch (\Throwable $e) {
            Log::error('EDI PagBank: erro na requisição', [
                'data' => $data->format('Y-m-d'),
                'erro' => $e->getMessage(),
            ]);

            return false;
        }

        if ($response->failed()) {
            Log::warning('EDI PagBank: requisição rejeitada', [
                'data' => $data->format('Y-m-d'),
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);

            return false;
        }

        if (! $this->ediValidado($response)) {
            Log::warning('EDI PagBank: arquivo não validado', [
                'data' => $data->format('Y-m-d'),
                'validado' => $response->header('VALIDADO'),
                'status' => $response->status(),
            ]);

            ProcessarEdiJob::dispatch(
                $data->format('Y-m-d'),
                $tipoMovimento,
                1,
                $estabelecimentoIdFiltro,
            )->delay(now()->addHour());

            return false;
        }

        $registros = $this->extrairRegistros($response->json() ?? []);

        Log::info('EDI PagBank: arquivo validado', [
            'data' => $data->format('Y-m-d'),
            'movimentos_pagina1' => count($registros),
            'estabelecimento_filtro' => $estabelecimentoIdFiltro,
        ]);

        ProcessarEdiJob::dispatch(
            $data->format('Y-m-d'),
            $tipoMovimento,
            1,
            $estabelecimentoIdFiltro,
        );

        return true;
    }

    /**
     * @return array{dias: int, enfileirados: int}
     */
    public function enfileirarSincronizacao(
        ?\Carbon\CarbonInterface $de = null,
        ?\Carbon\CarbonInterface $ate = null,
        ?int $estabelecimentoId = null,
        ?int $limit = null,
    ): array {
        $ate = ($ate ?? now()->subDay())->copy()->startOfDay();
        $de = ($de ?? $ate->copy()->subDays(89))->copy()->startOfDay();

        if ($de->gt($ate)) {
            [$de, $ate] = [$ate, $de];
        }

        if ($limit !== null && $limit > 0) {
            $de = $ate->copy()->subDays($limit - 1)->startOfDay();
        }

        $dias = $de->copy()->diffInDays($ate) + 1;

        SincronizarEdiPeriodoJob::dispatch(
            $de->format('Y-m-d'),
            $ate->format('Y-m-d'),
            $estabelecimentoId,
        );

        return [
            'dias' => $dias,
            'enfileirados' => 1,
        ];
    }

    /**
     * Importa todas as páginas de um dia de forma sequencial (sem enfileirar por página).
     *
     * @return array{validado: bool, importados: int, paginas: int, motivo?: string}
     */
    public function importarDiaCompleto(
        string $data,
        string $tipoMovimento = 'transactional',
        ?int $estabelecimentoIdFiltro = null,
    ): array {
        if (! PlatformSettings::ediConfigurado()) {
            return ['validado' => false, 'importados' => 0, 'paginas' => 0, 'motivo' => 'credenciais'];
        }

        try {
            $response = $this->clienteEdi()
                ->get("/movement/v3.00/{$tipoMovimento}/{$data}", $this->queryPaginacao(1));
        } catch (\Throwable $e) {
            Log::error('EDI PagBank: erro ao importar dia', ['data' => $data, 'erro' => $e->getMessage()]);

            return ['validado' => false, 'importados' => 0, 'paginas' => 0, 'motivo' => $e->getMessage()];
        }

        if ($response->failed()) {
            return [
                'validado' => false,
                'importados' => 0,
                'paginas' => 0,
                'motivo' => 'http_'.$response->status(),
            ];
        }

        if (! $this->ediValidado($response)) {
            return ['validado' => false, 'importados' => 0, 'paginas' => 0, 'motivo' => 'nao_validado'];
        }

        $pagina = 1;
        $total = 0;
        $payload = $response->json() ?? [];

        while (true) {
            if ($pagina > 1) {
                $payload = $this->baixarPagina($data, $tipoMovimento, $pagina);
            }

            $registros = $this->extrairRegistros($payload);
            $total += $this->processarPagina(
                $data,
                $tipoMovimento,
                $pagina,
                $payload,
                $estabelecimentoIdFiltro,
                encadear: false,
            );

            if (! $this->temProximaPagina($payload, $pagina, count($registros))) {
                break;
            }

            $pagina++;
        }

        if ($estabelecimentoIdFiltro === null) {
            CalcularRoyaltiesJob::dispatch($data);
            AgregarFaturamentoJob::dispatch($data);
        }

        Log::info('EDI PagBank: dia importado', [
            'data' => $data,
            'importados' => $total,
            'paginas' => $pagina,
        ]);

        return ['validado' => true, 'importados' => $total, 'paginas' => $pagina];
    }

    public function processarPagina(
        string $data,
        string $tipoMovimento = 'transactional',
        int $pagina = 1,
        ?array $payload = null,
        ?int $estabelecimentoIdFiltro = null,
        bool $encadear = true,
    ): int {
        $payload ??= $this->baixarPagina($data, $tipoMovimento, $pagina);
        $registros = $this->extrairRegistros($payload);
        $estabelecimentosPorToken = $this->mapaEstabelecimentosPorToken();
        $total = $this->gravarRegistros($registros, $estabelecimentosPorToken, $estabelecimentoIdFiltro);

        Log::info('EDI PagBank: página processada', [
            'data' => $data,
            'pagina' => $pagina,
            'registros' => count($registros),
            'importados' => $total,
        ]);

        if ($encadear) {
            if ($this->temProximaPagina($payload, $pagina, count($registros))) {
                ProcessarEdiJob::dispatch($data, $tipoMovimento, $pagina + 1, $estabelecimentoIdFiltro);
            } elseif ($estabelecimentoIdFiltro === null) {
                CalcularRoyaltiesJob::dispatch($data);
            }
        }

        return $total;
    }

    /**
     * @param  array<string, int>  $estabelecimentosPorToken
     */
    private function gravarRegistros(array $registros, array $estabelecimentosPorToken, ?int $estabelecimentoIdFiltro): int
    {
        $lote = [];
        $agora = now();

        foreach ($registros as $registro) {
            $codigo = Arr::get($registro, 'movimento_api_codigo');

            if (! $codigo) {
                continue;
            }

            $mapeado = $this->mapearRegistro($registro, $estabelecimentosPorToken);

            if (! $mapeado) {
                continue;
            }

            if ($estabelecimentoIdFiltro !== null && $mapeado['estabelecimento_id'] !== $estabelecimentoIdFiltro) {
                continue;
            }

            $lote[] = array_merge($mapeado, [
                'movimento_api_codigo' => $codigo,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);
        }

        if ($lote === []) {
            return 0;
        }

        $colunas = array_keys($lote[0]);

        foreach (array_chunk($lote, 250) as $chunk) {
            EdiMovimento::withoutGlobalScopes()->upsert(
                $chunk,
                ['movimento_api_codigo'],
                array_values(array_diff($colunas, ['movimento_api_codigo', 'created_at'])),
            );
        }

        return count($lote);
    }

    /**
     * @return array<string, int>
     */
    private function mapaEstabelecimentosPorToken(): array
    {
        return Cache::remember('edi:estabelecimentos_por_token', 300, function () {
            return Estabelecimento::withoutGlobalScopes()
                ->where('pagbank_edi_ativo', true)
                ->whereNotNull('token_pagseguro')
                ->pluck('id', 'token_pagseguro')
                ->mapWithKeys(fn ($id, $token) => [(string) $token => (int) $id])
                ->all();
        });
    }

    private function baixarPagina(string $data, string $tipoMovimento, int $pagina): array
    {
        $response = $this->clienteEdi()
            ->get("/movement/v3.00/{$tipoMovimento}/{$data}", $this->queryPaginacao($pagina));

        $response->throw();

        return $response->json() ?? [];
    }

    private function clienteEdi(): PendingRequest
    {
        return Http::baseUrl(PlatformSettings::ediUrl())
            ->withBasicAuth(
                (string) PlatformSettings::ediUser(),
                (string) PlatformSettings::ediToken(),
            )
            ->acceptJson()
            ->timeout(60);
    }

    private function ediValidado(Response $response): bool
    {
        $validado = $response->header('VALIDADO') ?? $response->header('validado');

        return strtoupper((string) $validado) === 'TRUE';
    }

    /**
     * @return array<string, int>
     */
    private function queryPaginacao(int $pagina): array
    {
        return [
            'pageNumber' => $pagina,
            'pageSize' => (int) config('pagseguro.pagina_limite', 1000),
        ];
    }

    private function extrairRegistros(array $payload): array
    {
        $registros = Arr::get($payload, 'detalhes')
            ?? Arr::get($payload, 'movimentos')
            ?? Arr::get($payload, 'content')
            ?? Arr::get($payload, 'data')
            ?? (array_is_list($payload) ? $payload : []);

        return is_array($registros) ? $registros : [];
    }

    private function temProximaPagina(array $payload, int $pagina, int $quantidade): bool
    {
        $page = Arr::get($payload, 'pagination') ?? Arr::get($payload, 'page');

        if (is_array($page)) {
            $totalPaginas = (int) ($page['totalPages'] ?? $page['total_pages'] ?? $page['totalPage'] ?? 0);
            $paginaAtual = (int) ($page['number'] ?? $page['pageNumber'] ?? $page['page'] ?? $pagina);

            if ($totalPaginas > 0) {
                return $paginaAtual < $totalPaginas;
            }
        }

        if (Arr::has($payload, 'has_next')) {
            return (bool) Arr::get($payload, 'has_next');
        }

        $totalPaginas = Arr::get($payload, 'total_pages') ?? Arr::get($payload, 'totalPages');

        if ($totalPaginas) {
            return $pagina < (int) $totalPaginas;
        }

        return $quantidade >= (int) config('pagseguro.pagina_limite', 1000);
    }

    /**
     * @param  array<string, int>  $estabelecimentosPorToken
     */
    private function mapearRegistro(array $registro, array $estabelecimentosPorToken): ?array
    {
        $codigoEstabelecimento = (string) Arr::get($registro, 'estabelecimento');

        if (blank($codigoEstabelecimento)) {
            return null;
        }

        $estabelecimentoId = $estabelecimentosPorToken[$codigoEstabelecimento] ?? null;

        if (! $estabelecimentoId) {
            return null;
        }

        return [
            'estabelecimento_id' => $estabelecimentoId,
            'id_cliente' => Arr::get($registro, 'id_cliente'),
            'tipo_registro' => Arr::get($registro, 'tipo_registro'),
            'estabelecimento' => $codigoEstabelecimento,
            'data_inicial_transacao' => Arr::get($registro, 'data_inicial_transacao'),
            'hora_inicial_transacao' => Arr::get($registro, 'hora_inicial_transacao'),
            'data_venda_ajuste' => Arr::get($registro, 'data_venda_ajuste'),
            'hora_venda_ajuste' => Arr::get($registro, 'hora_venda_ajuste'),
            'data_prevista_pagamento' => Arr::get($registro, 'data_prevista_pagamento'),
            'tipo_evento' => Arr::get($registro, 'tipo_evento'),
            'tipo_transacao' => EdiTransacaoCategoria::normalizarParaArmazenamento(
                Arr::get($registro, 'tipo_transacao'),
                Arr::get($registro, 'meio_pagamento'),
                Arr::get($registro, 'arranjo_ur'),
            ),
            'codigo_transacao' => Arr::get($registro, 'codigo_transacao'),
            'codigo_venda' => Arr::get($registro, 'codigo_venda'),
            'valor_total_transacao' => Arr::get($registro, 'valor_total_transacao'),
            'valor_parcela' => Arr::get($registro, 'valor_parcela'),
            'valor_original_transacao' => Arr::get($registro, 'valor_original_transacao'),
            'valor_liquido_transacao' => Arr::get($registro, 'valor_liquido_transacao'),
            'taxa_intermediacao' => Arr::get($registro, 'taxa_intermediacao'),
            'tarifa_intermediacao' => Arr::get($registro, 'tarifa_intermediacao'),
            'pagamento_prazo' => Arr::get($registro, 'pagamento_prazo'),
            'plano' => Arr::get($registro, 'plano'),
            'parcela' => Arr::get($registro, 'parcela'),
            'quantidade_parcela' => Arr::get($registro, 'quantidade_parcela') ?? Arr::get($registro, 'quantidade_parcelas'),
            'status_pagamento' => Arr::get($registro, 'status_pagamento'),
            'meio_pagamento' => Arr::get($registro, 'meio_pagamento'),
            'arranjo_ur' => Arr::get($registro, 'arranjo_ur'),
            'instituicao_financeira' => Arr::get($registro, 'instituicao_financeira'),
            'canal_entrada' => Arr::get($registro, 'canal_entrada'),
            'leitor' => Arr::get($registro, 'leitor'),
            'meio_captura' => Arr::get($registro, 'meio_captura'),
            'num_logico' => Arr::get($registro, 'num_logico'),
            'nsu' => Arr::get($registro, 'nsu'),
            'cartao_bin' => Arr::get($registro, 'cartao_bin'),
            'cartao_holder' => Arr::get($registro, 'cartao_holder'),
            'codigo_autorizacao' => Arr::get($registro, 'codigo_autorizacao'),
            'codigo_cv' => Arr::get($registro, 'codigo_cv'),
            'numero_serie_leitor' => Arr::get($registro, 'numero_serie_leitor'),
            'tx_id' => Arr::get($registro, 'tx_id'),
            'processado' => false,
            'data_importacao' => now(),
        ];
    }
}

<?php

namespace App\Services;

use App\Jobs\ProcessarEdiJob;
use App\Jobs\SincronizarEdiEstabelecimentoJob;
use App\Models\EdiMovimento;
use App\Models\Estabelecimento;
use App\Support\PlatformSettings;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EdiProcessadorService
{
    public function buscarEdiDisponivel(Estabelecimento $estabelecimento, CarbonInterface $data, string $tipoMovimento = 'transactional'): bool
    {
        if (! $estabelecimento->pagbank_edi_ativo || blank($estabelecimento->token_pagseguro)) {
            return false;
        }

        if (! PlatformSettings::ediConfigurado()) {
            return false;
        }

        $response = $this->clienteEdi($estabelecimento)
            ->get("/movement/v3.00/{$tipoMovimento}/{$data->format('Y-m-d')}", $this->queryPaginacao(1));

        if ($response->header('VALIDADO') !== 'TRUE') {
            Log::warning('EDI PagBank: arquivo não validado', [
                'estabelecimento_id' => $estabelecimento->id,
                'data' => $data->format('Y-m-d'),
                'validado' => $response->header('VALIDADO'),
                'status' => $response->status(),
            ]);

            ProcessarEdiJob::dispatch($estabelecimento->id, $data->format('Y-m-d'), $tipoMovimento, 1)->delay(now()->addHour());

            return false;
        }

        $payload = $response->json();
        $registros = Arr::get($payload ?? [], 'movimentos')
            ?? Arr::get($payload ?? [], 'content')
            ?? Arr::get($payload ?? [], 'data')
            ?? [];

        Log::info('EDI PagBank: arquivo validado', [
            'estabelecimento_id' => $estabelecimento->id,
            'data' => $data->format('Y-m-d'),
            'movimentos_pagina1' => is_array($registros) ? count($registros) : 0,
        ]);

        ProcessarEdiJob::dispatch($estabelecimento->id, $data->format('Y-m-d'), $tipoMovimento, 1, $payload);

        return true;
    }

    /**
     * @return array{estabelecimentos: int, dias: int, enfileirados: int}
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

        $query = Estabelecimento::withoutGlobalScopes()
            ->where('ativo', true)
            ->where('pagbank_edi_ativo', true)
            ->whereNotNull('token_pagseguro')
            ->where('token_pagseguro', '!=', '');

        if ($estabelecimentoId) {
            $query->whereKey($estabelecimentoId);
        }

        if ($limit) {
            $query->limit($limit);
        }

        $estabelecimentos = 0;
        $dias = 0;

        $query->orderBy('id')->chunkById(100, function ($chunk) use ($de, $ate, &$estabelecimentos, &$dias) {
            foreach ($chunk as $estabelecimento) {
                $inicio = $de->copy();

                if ($estabelecimento->created_at?->gt($inicio)) {
                    $inicio = $estabelecimento->created_at->copy()->startOfDay();
                }

                if ($inicio->gt($ate)) {
                    continue;
                }

                $diasEstab = $inicio->diffInDays($ate) + 1;
                $dias += $diasEstab;
                $estabelecimentos++;

                SincronizarEdiEstabelecimentoJob::dispatch(
                    $estabelecimento->id,
                    $inicio->format('Y-m-d'),
                    $ate->format('Y-m-d'),
                );
            }
        });

        return [
            'estabelecimentos' => $estabelecimentos,
            'dias' => $dias,
            'enfileirados' => $estabelecimentos,
        ];
    }

    public function processarPagina(int $estabelecimentoId, string $data, string $tipoMovimento, int $pagina = 1, ?array $payload = null): int
    {
        $estabelecimento = Estabelecimento::withoutGlobalScopes()->findOrFail($estabelecimentoId);
        $payload ??= $this->baixarPagina($estabelecimento, $data, $tipoMovimento, $pagina);
        $registros = $this->extrairRegistros($payload);
        $total = 0;

        foreach ($registros as $registro) {
            $codigo = Arr::get($registro, 'movimento_api_codigo');

            if (! $codigo) {
                continue;
            }

            EdiMovimento::withoutGlobalScopes()->updateOrCreate(
                ['movimento_api_codigo' => $codigo],
                $this->mapearRegistro($registro, $estabelecimento)
            );

            $total++;
        }

        if ($this->temProximaPagina($payload, $pagina, count($registros))) {
            ProcessarEdiJob::dispatch($estabelecimentoId, $data, $tipoMovimento, $pagina + 1);
        }

        return $total;
    }

    private function baixarPagina(Estabelecimento $estabelecimento, string $data, string $tipoMovimento, int $pagina): array
    {
        $response = $this->clienteEdi($estabelecimento)
            ->get("/movement/v3.00/{$tipoMovimento}/{$data}", $this->queryPaginacao($pagina));

        $response->throw();

        return $response->json() ?? [];
    }

    private function clienteEdi(Estabelecimento $estabelecimento): PendingRequest
    {
        return Http::baseUrl(PlatformSettings::ediUrl())
            ->withBasicAuth(
                (string) $estabelecimento->token_pagseguro,
                (string) PlatformSettings::ediToken(),
            )
            ->acceptJson()
            ->timeout(60);
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
        return Arr::get($payload, 'movimentos')
            ?? Arr::get($payload, 'content')
            ?? Arr::get($payload, 'data')
            ?? (array_is_list($payload) ? $payload : []);
    }

    private function temProximaPagina(array $payload, int $pagina, int $quantidade): bool
    {
        $page = Arr::get($payload, 'page');

        if (is_array($page)) {
            $totalPaginas = (int) ($page['totalPages'] ?? $page['total_pages'] ?? 0);
            $paginaAtual = (int) ($page['number'] ?? $page['pageNumber'] ?? $pagina);

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

    private function mapearRegistro(array $registro, Estabelecimento $estabelecimento): array
    {
        $codigoEstabelecimento = Arr::get($registro, 'estabelecimento');
        $estabelecimentoVinculado = Estabelecimento::withoutGlobalScopes()
            ->where('token_pagseguro', $codigoEstabelecimento)
            ->first();

        return [
            'estabelecimento_id' => $estabelecimentoVinculado?->id ?? $estabelecimento->id,
            'id_cliente' => Arr::get($registro, 'id_cliente'),
            'tipo_registro' => Arr::get($registro, 'tipo_registro'),
            'estabelecimento' => $codigoEstabelecimento,
            'data_inicial_transacao' => Arr::get($registro, 'data_inicial_transacao'),
            'hora_inicial_transacao' => Arr::get($registro, 'hora_inicial_transacao'),
            'data_venda_ajuste' => Arr::get($registro, 'data_venda_ajuste'),
            'hora_venda_ajuste' => Arr::get($registro, 'hora_venda_ajuste'),
            'data_prevista_pagamento' => Arr::get($registro, 'data_prevista_pagamento'),
            'tipo_evento' => Arr::get($registro, 'tipo_evento'),
            'tipo_transacao' => Arr::get($registro, 'tipo_transacao'),
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
            'quantidade_parcela' => Arr::get($registro, 'quantidade_parcela'),
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

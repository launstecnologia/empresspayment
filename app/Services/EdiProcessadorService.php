<?php

namespace App\Services;

use App\Jobs\ProcessarEdiJob;
use App\Models\EdiMovimento;
use App\Models\Estabelecimento;
use App\Support\PlatformSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class EdiProcessadorService
{
    public function buscarEdiDisponivel(Estabelecimento $estabelecimento, CarbonInterface $data, string $tipoMovimento = 'transactional'): bool
    {
        if (! $estabelecimento->pagbank_edi_ativo || blank($estabelecimento->token_pagseguro)) {
            return false;
        }

        $response = Http::baseUrl((string) config('pagseguro.edi_url'))
            ->withHeaders([
                'USER' => $estabelecimento->token_pagseguro,
                'TOKEN' => (string) config('pagseguro.edi_token'),
            ])
            ->acceptJson()
            ->timeout(60)
            ->get("/movement/v3.00/{$tipoMovimento}/{$data->format('Y-m-d')}", [
                'page' => 1,
                'limit' => config('pagseguro.pagina_limite', 1000),
            ]);

        if ($response->header('VALIDADO') !== 'TRUE') {
            ProcessarEdiJob::dispatch($estabelecimento->id, $data->format('Y-m-d'), $tipoMovimento, 1)->delay(now()->addHour());

            return false;
        }

        ProcessarEdiJob::dispatch($estabelecimento->id, $data->format('Y-m-d'), $tipoMovimento, 1, $response->json());

        return true;
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
        $response = Http::baseUrl(PlatformSettings::ediUrl())
            ->withHeaders([
                'USER'  => $estabelecimento->token_pagseguro,
                'TOKEN' => (string) PlatformSettings::ediToken(),
            ])
            ->acceptJson()
            ->timeout(60)
            ->get("/movement/v3.00/{$tipoMovimento}/{$data}", [
                'page' => $pagina,
                'limit' => config('pagseguro.pagina_limite', 1000),
            ]);

        $response->throw();

        return $response->json() ?? [];
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
        if (Arr::has($payload, 'has_next')) {
            return (bool) Arr::get($payload, 'has_next');
        }

        $totalPaginas = Arr::get($payload, 'total_pages');

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

<?php

namespace App\Jobs;

use App\Models\EdiReprocessamento;
use App\Models\Estabelecimento;
use App\Services\EdiProcessadorService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ReprocessarEdiEstabelecimentosJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 21600;

    public function __construct(public int $reprocessamentoId) {}

    public function handle(EdiProcessadorService $edi): void
    {
        $lote = EdiReprocessamento::query()->find($this->reprocessamentoId);

        if (! $lote) {
            return;
        }

        $de = Carbon::parse($lote->periodo_de)->startOfDay();
        $ate = Carbon::parse($lote->periodo_ate)->startOfDay();
        $entradas = collect($lote->entradas ?? [])
            ->map(fn ($valor) => trim((string) $valor))
            ->filter()
            ->unique()
            ->values();

        $lote->update([
            'status' => 'processando',
            'iniciado_em' => now(),
            'total_itens' => $entradas->count(),
            'total_dias' => $de->diffInDays($ate) + 1,
        ]);

        Cache::forget('edi:estabelecimentos_por_token');

        $resultado = [];
        $totalMovimentos = 0;
        $totalCancelamentos = 0;
        $totalErros = 0;
        $diasImportados = [];

        foreach ($entradas as $entrada) {
            $estabelecimento = $this->resolverEstabelecimento($entrada, (string) $lote->entrada_tipo);

            if (! $estabelecimento) {
                $totalErros++;
                $resultado[] = [
                    'entrada' => $entrada,
                    'status' => 'erro',
                    'mensagem' => 'Estabelecimento não encontrado.',
                ];
                $this->atualizarProgresso($lote, $resultado, $totalMovimentos, $totalCancelamentos, $totalErros);
                continue;
            }

            if (blank($estabelecimento->token_pagseguro) || ! $estabelecimento->pagbank_edi_ativo) {
                $totalErros++;
                $resultado[] = [
                    'entrada' => $entrada,
                    'estabelecimento_id' => $estabelecimento->id,
                    'nome' => $estabelecimento->nome_fantasia ?: $estabelecimento->razao_social ?: $estabelecimento->nome_completo,
                    'status' => 'erro',
                    'mensagem' => 'Estabelecimento sem token_pagseguro ou EDI inativo.',
                ];
                $this->atualizarProgresso($lote, $resultado, $totalMovimentos, $totalCancelamentos, $totalErros);
                continue;
            }

            $movimentos = 0;
            $cancelamentos = 0;
            $diasOk = 0;
            $diasErro = [];

            for ($data = $de->copy(); $data->lte($ate); $data->addDay()) {
                $dia = $data->format('Y-m-d');
                $retorno = $edi->importarDiaCompleto(
                    $dia,
                    'transactional',
                    $estabelecimento->id,
                    atualizarExistentes: false,
                    atualizarCanceladasExistentes: true,
                );

                if ($retorno['validado']) {
                    $diasOk++;
                    $movimentos += (int) $retorno['importados'];
                    $cancelamentos += (int) ($retorno['cancelamentos'] ?? 0);
                    $diasImportados[$dia] = true;
                } else {
                    $diasErro[] = $dia.': '.($retorno['motivo'] ?? 'não validado');
                }
            }

            $totalMovimentos += $movimentos;
            $totalCancelamentos += $cancelamentos;
            $totalErros += count($diasErro);

            $resultado[] = [
                'entrada' => $entrada,
                'estabelecimento_id' => $estabelecimento->id,
                'nome' => $estabelecimento->nome_fantasia ?: $estabelecimento->razao_social ?: $estabelecimento->nome_completo,
                'token_pagseguro' => $estabelecimento->token_pagseguro,
                'status' => $diasErro === [] ? 'concluido' : 'parcial',
                'dias_ok' => $diasOk,
                'movimentos' => $movimentos,
                'cancelamentos' => $cancelamentos,
                'erros' => $diasErro,
            ];

            $this->atualizarProgresso($lote, $resultado, $totalMovimentos, $totalCancelamentos, $totalErros);
        }

        foreach (array_keys($diasImportados) as $dia) {
            CalcularRoyaltiesJob::dispatch($dia);
            AgregarFaturamentoJob::dispatch($dia);
        }

        $lote->update([
            'status' => $totalErros > 0 ? 'erro' : 'concluido',
            'processados' => $entradas->count(),
            'total_movimentos' => $totalMovimentos,
            'total_cancelamentos' => $totalCancelamentos,
            'total_erros' => $totalErros,
            'resultado' => $resultado,
            'erro' => $totalErros > 0 ? 'Concluído com erros em alguns itens/dias.' : null,
            'finalizado_em' => now(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        EdiReprocessamento::query()
            ->whereKey($this->reprocessamentoId)
            ->update([
                'status' => 'erro',
                'erro' => $exception->getMessage(),
                'finalizado_em' => now(),
            ]);

        Log::error('Reprocessamento EDI falhou', [
            'reprocessamento_id' => $this->reprocessamentoId,
            'erro' => $exception->getMessage(),
        ]);
    }

    private function resolverEstabelecimento(string $entrada, string $tipo): ?Estabelecimento
    {
        $query = Estabelecimento::withoutGlobalScopes();

        if ($tipo === 'id') {
            return ctype_digit($entrada) ? $query->find((int) $entrada) : null;
        }

        return $query->where('token_pagseguro', $entrada)->first();
    }

    private function atualizarProgresso(EdiReprocessamento $lote, array $resultado, int $totalMovimentos, int $totalCancelamentos, int $totalErros): void
    {
        $lote->update([
            'processados' => count($resultado),
            'total_movimentos' => $totalMovimentos,
            'total_cancelamentos' => $totalCancelamentos,
            'total_erros' => $totalErros,
            'resultado' => $resultado,
        ]);
    }
}

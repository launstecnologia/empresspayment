<?php

namespace App\Services;

use App\Models\AutomacaoLog;
use App\Support\AutomacaoSchema;
use Illuminate\Database\Eloquent\Collection;

class AutomacaoLogService
{
    public function tabelaDisponivel(): bool
    {
        return AutomacaoSchema::temTabelaLogs();
    }

    /**
     * @return Collection<int, AutomacaoLog>
     */
    public function listarParaEstabelecimento(int $estabelecimentoId, int $limite = 100): Collection
    {
        if (! $this->tabelaDisponivel()) {
            return new Collection;
        }

        return AutomacaoLog::query()
            ->where('estabelecimento_id', $estabelecimentoId)
            ->orderByDesc('id')
            ->limit($limite)
            ->get()
            ->reverse()
            ->values();
    }

    public function registrar(
        int $estabelecimentoId,
        string $mensagem,
        string $nivel = 'info',
        ?string $jobId = null,
        ?string $etapa = null,
        ?array $detalhe = null,
        string $origem = 'laravel',
        ?string $origemRef = null,
    ): ?AutomacaoLog {
        if (! $this->tabelaDisponivel()) {
            return null;
        }

        if ($origemRef !== null) {
            $existente = AutomacaoLog::query()
                ->where('origem', $origem)
                ->where('origem_ref', $origemRef)
                ->first();

            if ($existente) {
                return $existente;
            }
        }

        return AutomacaoLog::create([
            'estabelecimento_id' => $estabelecimentoId,
            'job_id' => $jobId,
            'nivel' => $nivel,
            'etapa' => $etapa,
            'mensagem' => $mensagem,
            'detalhe' => $detalhe,
            'origem' => $origem,
            'origem_ref' => $origemRef,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $logsApi
     */
    public function sincronizarDoJob(int $estabelecimentoId, string $jobId, array $logsApi): void
    {
        if (! $this->tabelaDisponivel()) {
            return;
        }

        foreach ($logsApi as $entrada) {
            if (! is_array($entrada)) {
                continue;
            }

            $id = isset($entrada['id']) ? (string) $entrada['id'] : null;
            $mensagem = trim((string) ($entrada['mensagem'] ?? ''));

            if ($mensagem === '') {
                continue;
            }

            $this->registrar(
                estabelecimentoId: $estabelecimentoId,
                mensagem: $mensagem,
                nivel: (string) ($entrada['nivel'] ?? 'info'),
                jobId: $jobId,
                etapa: filled($entrada['etapa'] ?? null) ? (string) $entrada['etapa'] : null,
                detalhe: is_array($entrada['detalhe'] ?? null) ? $entrada['detalhe'] : null,
                origem: 'python',
                origemRef: $id,
            );
        }
    }

    public function registrarInicio(int $estabelecimentoId, string $tipo, ?string $jobId = null): void
    {
        $this->registrar(
            estabelecimentoId: $estabelecimentoId,
            mensagem: "Automação iniciada: {$tipo}",
            nivel: 'info',
            jobId: $jobId,
            etapa: 'Início',
        );
    }

    public function registrarConclusao(int $estabelecimentoId, string $mensagem, ?string $jobId = null, ?array $detalhe = null): void
    {
        $this->registrar(
            estabelecimentoId: $estabelecimentoId,
            mensagem: $mensagem,
            nivel: 'sucesso',
            jobId: $jobId,
            etapa: 'Concluído',
            detalhe: $detalhe,
        );
    }

    public function registrarErro(int $estabelecimentoId, string $mensagem, ?string $jobId = null, ?string $etapa = null, ?array $detalhe = null): void
    {
        $this->registrar(
            estabelecimentoId: $estabelecimentoId,
            mensagem: $mensagem,
            nivel: 'erro',
            jobId: $jobId,
            etapa: $etapa ?? 'Erro',
            detalhe: $detalhe,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarJson(int $estabelecimentoId, int $limite = 100): array
    {
        return $this->listarParaEstabelecimento($estabelecimentoId, $limite)
            ->map(fn (AutomacaoLog $log) => [
                'id' => $log->id,
                'nivel' => $log->nivel,
                'etapa' => $log->etapa,
                'mensagem' => $log->mensagem,
                'detalhe' => $log->detalhe,
                'job_id' => $log->job_id,
                'origem' => $log->origem,
                'created_at' => $log->created_at?->format('d/m/Y H:i:s'),
            ])
            ->all();
    }
}

<?php

namespace App\Services;

use App\Models\AutomacaoLog;

class AutomacaoLogService
{
    public function registrar(
        int $estabelecimentoId,
        string $mensagem,
        string $nivel = 'info',
        ?string $jobId = null,
        ?string $etapa = null,
        ?array $detalhe = null,
        string $origem = 'laravel',
        ?string $origemRef = null,
    ): AutomacaoLog {
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
}

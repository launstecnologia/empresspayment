<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\AutomacaoPagBankJob;
use App\Jobs\AutomacaoRetentarEmailJob;
use App\Models\Estabelecimento;
use App\Services\AutomacaoPagBankService;
use App\Support\PlatformSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EstabelecimentoAutomacaoController extends Controller
{
    public function iniciar(Request $request, Estabelecimento $estabelecimento)
    {
        abort_unless($request->user()?->tipo === 'admin', 403);

        $statusBloqueante = ['em_andamento'];
        if (in_array($estabelecimento->fv_status, $statusBloqueante, true)) {
            return redirect()
                ->route('estabelecimentos.show', $estabelecimento)
                ->with('aviso', 'A automação já está em andamento. Aguarde a conclusão antes de reexecutar.');
        }

        if (! PlatformSettings::automacaoConfigurado()) {
            return redirect()
                ->route('estabelecimentos.show', $estabelecimento)
                ->withErrors(['automacao' => 'A automação não está configurada. Verifique AUTOMACAO_API_URL e AUTOMACAO_API_KEY.']);
        }

        $senha6 = $this->gerarSenha6();

        $estabelecimento->update(['fv_status' => 'pendente', 'fv_erro' => null]);

        AutomacaoPagBankJob::dispatch($estabelecimento->id, $senha6)
            ->onQueue('automacao');

        return redirect()
            ->route('estabelecimentos.show', $estabelecimento)
            ->with('status', 'Automação enfileirada. Acompanhe o status nesta página.');
    }

    public function retentarEmail(Request $request, Estabelecimento $estabelecimento)
    {
        abort_unless($request->user()?->tipo === 'admin', 403);

        if ($estabelecimento->fv_status !== 'erro_email') {
            return redirect()
                ->route('estabelecimentos.show', $estabelecimento)
                ->with('aviso', 'Esta ação só está disponível quando o status é "Erro no e-mail".');
        }

        if (! PlatformSettings::automacaoConfigurado()) {
            return redirect()
                ->route('estabelecimentos.show', $estabelecimento)
                ->withErrors(['automacao' => 'A automação não está configurada.']);
        }

        try {
            $senha6 = $estabelecimento->fv_senha_6 ?: $this->gerarSenha6();

            $estabelecimento->update([
                'fv_status'  => 'em_andamento',
                'fv_erro'    => null,
                'fv_senha_6' => $senha6,
            ]);

            AutomacaoRetentarEmailJob::dispatch($estabelecimento->id, $senha6)
                ->onQueue('automacao');

            return redirect()
                ->route('estabelecimentos.show', $estabelecimento)
                ->with('status', 'Retentando etapa de e-mail. Acompanhe o status nesta página.');

        } catch (\Throwable $e) {
            return redirect()
                ->route('estabelecimentos.show', $estabelecimento)
                ->withErrors(['automacao' => 'Erro ao retentar e-mail: '.$e->getMessage()]);
        }
    }

    public function status(Estabelecimento $estabelecimento)
    {
        abort_unless(auth()->user()?->tipo === 'admin', 403);

        $estabelecimento->refresh();

        if (! PlatformSettings::automacaoConfigurado()) {
            return response()->json([
                'fv_status'   => $estabelecimento->fv_status,
                'etapa_atual' => null,
                'erro'        => null,
            ]);
        }

        if (blank($estabelecimento->fv_job_id)) {
            return response()->json([
                'fv_status'   => $estabelecimento->fv_status,
                'etapa_atual' => $estabelecimento->fv_status === 'pendente'
                    ? 'Aguardando fila...'
                    : null,
                'erro'        => $estabelecimento->fv_erro,
            ]);
        }

        try {
            $status = app(AutomacaoPagBankService::class)
                ->consultarStatus($estabelecimento->fv_job_id);

            return response()->json([
                'fv_status'   => $estabelecimento->fv_status,
                'status'      => $status['status'] ?? null,
                'etapa_atual' => $status['etapa_atual'] ?? null,
                'erro'        => $status['erro'] ?? $estabelecimento->fv_erro,
                'atualizado_em' => $status['atualizado_em'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Automacao status: falha ao consultar API Python', [
                'estabelecimento_id' => $estabelecimento->id,
                'job_id'             => $estabelecimento->fv_job_id,
                'erro'               => $e->getMessage(),
            ]);

            return response()->json([
                'fv_status'   => $estabelecimento->fv_status,
                'etapa_atual' => 'Consultando status...',
                'erro'        => null,
            ]);
        }
    }

    private function gerarSenha6(): string
    {
        do {
            $senha = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $ehSequencia = preg_match('/^(\d)\1{5}$/', $senha)
                || in_array($senha, ['123456', '654321', '012345', '567890'], true);
        } while ($ehSequencia);

        return $senha;
    }
}

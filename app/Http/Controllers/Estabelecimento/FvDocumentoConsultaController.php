<?php

namespace App\Http\Controllers\Estabelecimento;

use App\Http\Controllers\Controller;
use App\Models\Estabelecimento;
use App\Services\AutomacaoPagBankService;
use App\Support\PlatformSettings;
use App\Support\UsuarioComercial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FvDocumentoConsultaController extends Controller
{
    public function index()
    {
        $this->autorizar();

        return view('fv-documento.index', [
            'automacaoConfigurada' => $this->automacaoPronta(),
        ]);
    }

    public function consultar(Request $request)
    {
        $this->autorizar();

        if (! $this->automacaoPronta()) {
            return response()->json([
                'ok' => false,
                'mensagem' => 'Automação Força de Vendas não configurada. Verifique as credenciais em Configurações.',
            ], 422);
        }

        $dados = $request->validate([
            'documento' => ['required', 'string', 'max:18'],
        ]);

        try {
            $service = app(AutomacaoPagBankService::class);
            $documentoFormatado = $service->formatarDocumentoConsulta($dados['documento']);
            $digits = preg_replace('/\D/', '', $documentoFormatado);

            $local = $this->buscarEstabelecimentoLocal($digits);

            $jobId = $service->iniciarConsultaDocumento($documentoFormatado);

            return response()->json([
                'ok' => true,
                'job_id' => $jobId,
                'documento' => $documentoFormatado,
                'documento_digits' => $digits,
                'local' => $local ? [
                    'id' => $local->id,
                    'nome' => $local->nome_fantasia ?: $local->razao_social ?: $local->nome_completo,
                    'url' => route('estabelecimentos.show', $local),
                ] : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('FvDocumentoConsulta: falha ao iniciar', [
                'documento' => $dados['documento'] ?? null,
                'erro' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'mensagem' => 'Não foi possível iniciar a consulta: '.$e->getMessage(),
            ], 422);
        }
    }

    public function status(string $jobId)
    {
        $this->autorizar();

        if (! PlatformSettings::automacaoConfigurado()) {
            return response()->json(['ok' => false, 'mensagem' => 'Automação não configurada.'], 422);
        }

        try {
            $job = app(AutomacaoPagBankService::class)->consultarStatus($jobId);
            $detalhe = $job['resultado']['detalhe'] ?? null;
            $situacao = is_array($detalhe) ? ($detalhe['situacao'] ?? null) : null;

            return response()->json([
                'ok' => true,
                'status' => $job['status'] ?? null,
                'etapa_atual' => $job['etapa_atual'] ?? null,
                'erro' => $job['erro'] ?? null,
                'resultado' => $detalhe,
                'situacao' => $situacao,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'mensagem' => $e->getMessage(),
            ], 422);
        }
    }

    private function autorizar(): void
    {
        abort_unless(UsuarioComercial::podeCadastrarEstabelecimento(), 403);
    }

    private function automacaoPronta(): bool
    {
        return PlatformSettings::automacaoConfigurado()
            && filled(PlatformSettings::automacaoFvUsuario())
            && filled(PlatformSettings::automacaoFvSenha());
    }

    private function buscarEstabelecimentoLocal(string $digits): ?Estabelecimento
    {
        if (! in_array(strlen($digits), [11, 14], true)) {
            return null;
        }

        $campo = strlen($digits) === 14 ? 'cnpj' : 'cpf';

        return Estabelecimento::query()
            ->whereNotNull($campo)
            ->get()
            ->first(fn (Estabelecimento $estab) => preg_replace('/\D/', '', (string) $estab->{$campo}) === $digits);
    }
}

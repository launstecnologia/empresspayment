<?php

namespace App\Http\Controllers\Estabelecimento;

use App\Http\Controllers\Controller;
use App\Models\EmailCaixaEntrada;
use App\Models\Estabelecimento;
use App\Models\EstabelecimentoEmail;
use App\Services\EmailSmtpService;
use App\Support\EstabelecimentoEmailRedirect;
use Illuminate\Http\Request;

class EstabelecimentoCaixaEmailController extends Controller
{
    public function index(Estabelecimento $estabelecimento, EstabelecimentoEmail $conta)
    {
        $this->garantirConta($estabelecimento, $conta);

        return redirect()->route('estabelecimentos.email.painel', [
            'estabelecimento' => $estabelecimento,
            'conta' => $conta->id,
        ]);
    }

    public function show(Estabelecimento $estabelecimento, EstabelecimentoEmail $conta, EmailCaixaEntrada $mensagem)
    {
        $this->garantirConta($estabelecimento, $conta);
        abort_unless($mensagem->estabelecimento_email_id === $conta->id, 404);

        return EstabelecimentoEmailRedirect::paraLeitor($estabelecimento, $conta, [
            'mensagem' => $mensagem->id,
            'pasta' => $mensagem->pasta === 'INBOX' ? 'INBOX' : request('pasta', 'INBOX'),
        ]);
    }

    public function enviar(Request $request, Estabelecimento $estabelecimento, EstabelecimentoEmail $conta, EmailSmtpService $smtp)
    {
        $this->garantirConta($estabelecimento, $conta);

        $dados = $request->validate([
            'para' => ['required', 'string', 'max:500'],
            'cc' => ['nullable', 'string', 'max:500'],
            'cco' => ['nullable', 'string', 'max:500'],
            'assunto' => ['required', 'string', 'max:500'],
            'corpo' => ['required', 'string'],
            'resposta_ao_id' => ['nullable', 'exists:email_caixa_entrada,id'],
        ]);

        $respostaA = null;
        if (! empty($dados['resposta_ao_id'])) {
            $respostaA = EmailCaixaEntrada::where('estabelecimento_email_id', $conta->id)
                ->findOrFail($dados['resposta_ao_id']);
        }

        $corpoHtml = nl2br(e($dados['corpo']));

        try {
            $smtp->enviar($conta, $dados['para'], $dados['assunto'], $corpoHtml, $dados['cc'] ?? null, $dados['cco'] ?? null, $respostaA);
        } catch (\Throwable $e) {
            return back()->withErrors(['email' => 'Falha ao enviar: '.$e->getMessage()])->withInput();
        }

        return EstabelecimentoEmailRedirect::paraLeitor($estabelecimento, $conta, ['pasta' => 'enviados'])
            ->with('status', 'E-mail enviado com sucesso.');
    }

    private function garantirConta(Estabelecimento $estabelecimento, EstabelecimentoEmail $conta): void
    {
        abort_unless($conta->estabelecimento_id === $estabelecimento->id, 404);
    }
}

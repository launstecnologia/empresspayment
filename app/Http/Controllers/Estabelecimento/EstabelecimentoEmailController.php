<?php

namespace App\Http\Controllers\Estabelecimento;

use App\Http\Controllers\Controller;
use App\Models\Estabelecimento;
use App\Models\EstabelecimentoEmail;
use App\Services\EstabelecimentoEmailService;
use App\Services\ImapService;
use App\Support\EstabelecimentoEmailRedirect;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EstabelecimentoEmailController extends Controller
{
    public function store(Request $request, Estabelecimento $estabelecimento, EstabelecimentoEmailService $emails)
    {
        $dados = $request->validate([
            'prefixo' => ['required', 'string', 'max:50'],
            'senha' => ['nullable', 'string', 'min:8', 'max:100'],
        ]);

        try {
            $conta = $emails->criarManual($estabelecimento, $dados['prefixo'], $dados['senha'] ?? null);
        } catch (\Throwable $e) {
            return back()->withErrors(['email' => $e->getMessage()])->withInput();
        }

        return EstabelecimentoEmailRedirect::paraLeitor($estabelecimento, $conta)
            ->with('status', 'Conta criada. Sincronize o IMAP para buscar mensagens.');
    }

    public function updateSubdominio(Request $request, Estabelecimento $estabelecimento)
    {
        $dados = $request->validate([
            'subdominio' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                Rule::unique('estabelecimentos', 'subdominio')->ignore($estabelecimento->id),
            ],
        ]);

        $estabelecimento->update(['subdominio' => $dados['subdominio']]);

        return EstabelecimentoEmailRedirect::paraLeitor($estabelecimento)
            ->with('status', 'Subdomínio salvo. Agora você pode criar a conta de e-mail.');
    }

    public function provisionar(Estabelecimento $estabelecimento, EstabelecimentoEmailService $emails)
    {
        try {
            $emails->enfileirarProvisionamento($estabelecimento);
        } catch (\Throwable $e) {
            return back()->withErrors(['email' => $e->getMessage()]);
        }

        return EstabelecimentoEmailRedirect::paraLeitor($estabelecimento)
            ->with('status', 'Criação automática enfileirada. Atualize a página em instantes.');
    }

    public function updateSenha(Request $request, Estabelecimento $estabelecimento, EstabelecimentoEmail $conta, EstabelecimentoEmailService $emails)
    {
        $this->garantirConta($estabelecimento, $conta);

        $dados = $request->validate([
            'senha' => ['required', 'string', 'min:8', 'max:100'],
        ]);

        try {
            $emails->alterarSenha($conta, $dados['senha']);
        } catch (\Throwable $e) {
            return back()->withErrors(['email' => $e->getMessage()]);
        }

        return EstabelecimentoEmailRedirect::paraLeitor($estabelecimento, $conta)
            ->with('status', 'Senha do e-mail atualizada.');
    }

    public function redirecionar(Request $request, Estabelecimento $estabelecimento, EstabelecimentoEmail $conta, EstabelecimentoEmailService $emails)
    {
        $this->garantirConta($estabelecimento, $conta);

        $dados = $request->validate([
            'destino' => ['required', 'email', 'max:200'],
        ]);

        try {
            $emails->redirecionar($conta, $dados['destino']);
        } catch (\Throwable $e) {
            return back()->withErrors(['email' => $e->getMessage()]);
        }

        return EstabelecimentoEmailRedirect::paraLeitor($estabelecimento, $conta)
            ->with('status', 'Redirecionamento configurado.');
    }

    public function destroy(Estabelecimento $estabelecimento, EstabelecimentoEmail $conta, EstabelecimentoEmailService $emails)
    {
        $this->garantirConta($estabelecimento, $conta);

        try {
            $emails->excluir($conta);
        } catch (\Throwable $e) {
            return back()->withErrors(['email' => $e->getMessage()]);
        }

        return EstabelecimentoEmailRedirect::paraLeitor($estabelecimento)
            ->with('status', 'Conta de e-mail removida.');
    }

    public function sincronizar(Estabelecimento $estabelecimento, EstabelecimentoEmail $conta, ImapService $imap)
    {
        $this->garantirConta($estabelecimento, $conta);
        $imap->sincronizar($conta);
        $conta->refresh();

        $mensagem = $conta->ultimo_erro_sync
            ? 'Sincronização finalizada com avisos.'
            : 'Sincronização concluída.';

        return EstabelecimentoEmailRedirect::paraLeitor($estabelecimento, $conta)
            ->with('status', $mensagem);
    }

    private function garantirConta(Estabelecimento $estabelecimento, EstabelecimentoEmail $conta): void
    {
        abort_unless($conta->estabelecimento_id === $estabelecimento->id, 404);
    }
}

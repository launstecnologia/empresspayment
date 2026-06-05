<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Estabelecimento;
use App\Services\LogService;
use App\Services\PagBankCadastroDispatcher;
use App\Services\PagBankFeesService;
use App\Support\PagBankEstabelecimentoStatus;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EstabelecimentoPagBankController extends Controller
{
    public function reenviarCadastro(
        Request $request,
        Estabelecimento $estabelecimento,
        PagBankCadastroDispatcher $dispatcher,
    ) {
        $this->authorizeAdmin($request);

        if ($estabelecimento->pagbank_account_id) {
            return redirect()
                ->route('estabelecimentos.show', $estabelecimento)
                ->with('status', 'Este estabelecimento já possui conta PagBank.');
        }

        $estabelecimento->load('kycAnalise');

        if (! PagBankEstabelecimentoStatus::podeEnfileirarCadastro($estabelecimento)) {
            return redirect()
                ->route('estabelecimentos.show', $estabelecimento)
                ->with('status', 'KYC precisa estar aprovado para enviar cadastro ao PagBank.');
        }

        $dispatcher->enfileirar($estabelecimento, 0);

        return redirect()
            ->route('estabelecimentos.show', $estabelecimento)
            ->with('status', 'Cadastro PagBank enfileirado. Acompanhe o status nesta página.');
    }

    public function reaplicarFees(Request $request, Estabelecimento $estabelecimento, PagBankFeesService $fees)
    {
        $this->authorizeAdmin($request);

        if (! $estabelecimento->pagbank_account_id) {
            return redirect()
                ->route('estabelecimentos.show', $estabelecimento)
                ->with('status', 'Estabelecimento sem conta PagBank. Faça o cadastro primeiro.');
        }

        if (! $estabelecimento->plano_id) {
            return redirect()
                ->route('estabelecimentos.show', $estabelecimento)
                ->with('status', 'Estabelecimento sem plano vinculado.');
        }

        $sucesso = $fees->aplicar($estabelecimento);

        return redirect()
            ->route('estabelecimentos.show', $estabelecimento)
            ->with('status', $sucesso
                ? 'Tabela de taxas reaplicada com sucesso no PagBank.'
                : 'Erro ao aplicar taxas no PagBank. Verifique os logs.');
    }

    public function atualizarEdi(Request $request, Estabelecimento $estabelecimento, LogService $log)
    {
        $this->authorizeAdmin($request);

        $dados = $request->validate([
            'token_pagseguro' => ['nullable', 'string', 'max:255'],
            'pagbank_edi_ativo' => ['required', 'boolean'],
        ]);

        $token = filled($dados['token_pagseguro'] ?? null)
            ? trim($dados['token_pagseguro'])
            : $estabelecimento->token_pagseguro;

        $ativarEdi = (bool) $dados['pagbank_edi_ativo'];

        if ($ativarEdi && blank($token)) {
            throw ValidationException::withMessages([
                'token_pagseguro' => 'Informe o código USER do EDI retornado pelo PagBank antes de ativar.',
            ]);
        }

        $anterior = [
            'token_pagseguro' => $estabelecimento->token_pagseguro,
            'pagbank_edi_ativo' => $estabelecimento->pagbank_edi_ativo,
        ];

        $update = ['pagbank_edi_ativo' => $ativarEdi];

        if (filled($dados['token_pagseguro'] ?? null)) {
            $update['token_pagseguro'] = trim($dados['token_pagseguro']);
        }

        $estabelecimento->update($update);

        $log->registrar(
            'Estabelecimento',
            $estabelecimento->id,
            'pagbank_edi_atualizado',
            $ativarEdi ? 'EDI PagBank ativado' : 'EDI PagBank desativado',
            $anterior,
            [
                'token_pagseguro' => $estabelecimento->token_pagseguro,
                'pagbank_edi_ativo' => $estabelecimento->pagbank_edi_ativo,
            ],
        );

        $mensagem = $ativarEdi
            ? 'EDI ativado. O job diário passará a buscar transações deste estabelecimento.'
            : 'EDI desativado para este estabelecimento.';

        return redirect()
            ->route('estabelecimentos.show', $estabelecimento)
            ->with('status', $mensagem);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user() && $request->user()->tipo === 'admin', 403);
    }
}

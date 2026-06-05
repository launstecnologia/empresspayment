<?php

namespace App\Http\Controllers\Estabelecimento;

use App\Http\Controllers\Controller;
use App\Models\EmailCaixaEntrada;
use App\Models\Estabelecimento;
use App\Services\EmailDemoLayoutService;
use App\Services\ImapService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class EstabelecimentoEmailPainelController extends Controller
{
    public function index(Request $request, Estabelecimento $estabelecimento, ImapService $imap, EmailDemoLayoutService $demo)
    {
        $estabelecimento->load('emails');

        $modoDemo = false;
        $contaDemo = null;

        if ($demo->ativo()) {
            $demoResultado = $demo->garantir($estabelecimento);
            $modoDemo = $demoResultado['modo_demo'];
            $contaDemo = $demoResultado['conta'];
            $estabelecimento->load('emails');
        }

        $pastaAtiva = (string) $request->query('pasta', 'INBOX');
        $compondo = $request->boolean('compor');
        $contaAtiva = null;
        $mensagens = new LengthAwarePaginator([], 0, 25);
        $enviados = new LengthAwarePaginator([], 0, 25);
        $mensagemAtiva = null;
        $contagemPastas = [
            'inbox_nao_lidos' => 0,
            'favoritos' => 0,
        ];

        if ($modoDemo && $contaDemo) {
            $contaAtiva = $contaDemo;
        } elseif ($request->filled('conta')) {
            $contaAtiva = $estabelecimento->emails->firstWhere('id', (int) $request->query('conta'));
        }
        $contaAtiva ??= $estabelecimento->emails->first();

        if ($contaAtiva) {
            $base = $contaAtiva->mensagens();
            $contagemPastas = [
                'inbox_nao_lidos' => (clone $base)->where('pasta', 'INBOX')->where('deletado', false)->where('spam', false)->where('lido', false)->count(),
                'favoritos' => (clone $base)->where('favorito', true)->where('deletado', false)->count(),
            ];

            if ($pastaAtiva === 'enviados') {
                $enviados = $contaAtiva->enviados()->latest()->paginate(25)->withQueryString();
            } else {
                $consulta = match ($pastaAtiva) {
                    'favoritos' => (clone $base)->where('favorito', true)->where('deletado', false),
                    'spam' => (clone $base)->where('spam', true)->where('deletado', false),
                    'lixeira' => (clone $base)->where('deletado', true),
                    default => (clone $base)->where('pasta', 'INBOX')->where('deletado', false)->where('spam', false),
                };

                $mensagens = $consulta
                    ->orderByDesc('data_email')
                    ->orderByDesc('id')
                    ->paginate(25)
                    ->withQueryString();
            }

            if ($request->filled('mensagem')) {
                $mensagemAtiva = EmailCaixaEntrada::query()
                    ->where('estabelecimento_email_id', $contaAtiva->id)
                    ->find($request->integer('mensagem'));

                if ($mensagemAtiva && ! $mensagemAtiva->lido) {
                    $mensagemAtiva->update(['lido' => true]);
                }
            }
        }

        $nome = $estabelecimento->nome_fantasia
            ?: $estabelecimento->razao_social
            ?: $estabelecimento->nome_completo
            ?: 'Estabelecimento';

        return view('estabelecimento.email.painel', [
            'estabelecimento' => $estabelecimento,
            'nomeEstabelecimento' => $nome,
            'contaAtiva' => $contaAtiva,
            'mensagens' => $mensagens,
            'enviados' => $enviados,
            'mensagemAtiva' => $mensagemAtiva,
            'pastaAtiva' => $pastaAtiva,
            'compondo' => $compondo,
            'contagemPastas' => $contagemPastas,
            'imapDisponivel' => $imap->disponivel(),
            'modoDemo' => $modoDemo,
        ]);
    }
}

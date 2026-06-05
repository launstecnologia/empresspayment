<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Services\NotificacaoEmailService;
use App\Support\EmailTemplateCatalogo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmailTemplateController extends Controller
{
    public function index()
    {
        abort_unless(request()->user()?->tipo === 'admin', 403);

        $templates = EmailTemplate::query()
            ->orderBy('categoria')
            ->orderBy('nome')
            ->get()
            ->groupBy('categoria');

        return view('admin.email-templates.index', [
            'templates' => $templates,
            'categorias' => EmailTemplateCatalogo::categorias(),
        ]);
    }

    public function edit(EmailTemplate $emailTemplate)
    {
        abort_unless(request()->user()?->tipo === 'admin', 403);

        return view('admin.email-templates.edit', [
            'template' => $emailTemplate,
            'categorias' => EmailTemplateCatalogo::categorias(),
        ]);
    }

    public function update(Request $request, EmailTemplate $emailTemplate)
    {
        abort_unless($request->user()?->tipo === 'admin', 403);

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'categoria' => ['required', 'string', Rule::in(array_keys(EmailTemplateCatalogo::categorias()))],
            'assunto' => ['required', 'string', 'max:200'],
            'corpo' => ['required', 'string', 'max:10000'],
            'botao_texto' => ['nullable', 'string', 'max:80'],
            'placeholders_ajuda' => ['nullable', 'string', 'max:500'],
            'ativo' => ['boolean'],
        ]);

        $dados['ativo'] = $request->boolean('ativo');

        $emailTemplate->update($dados);

        return redirect()
            ->route('admin.email-templates.index')
            ->with('status', 'Template de e-mail atualizado.');
    }

    public function teste(Request $request, EmailTemplate $emailTemplate, NotificacaoEmailService $notificacao)
    {
        abort_unless($request->user()?->tipo === 'admin', 403);

        $admin = $request->user();

        $notificacao->enviar($emailTemplate->slug, $admin->email, [
            'nome' => $admin->nomeExibicao(),
            'estabelecimento' => 'Estabelecimento Exemplo',
            'documento' => '00.000.000/0001-00',
            'motivo' => 'Exemplo de motivo para preview.',
            'numero' => 'CHM-2026-000001',
            'titulo' => 'Chamado de teste',
            'categoria' => 'Técnico',
            'prioridade' => 'Média',
            'status' => 'Aberto',
            'mensagem' => 'Esta é uma mensagem de exemplo do template.',
            'account_id' => 'ACCO_EXEMPLO',
            'expira' => '60 minutos',
            'link' => config('app.url'),
        ], config('app.url'));

        return redirect()
            ->route('admin.email-templates.edit', $emailTemplate)
            ->with('status', "E-mail de teste enviado para {$admin->email}.");
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chamado;
use App\Services\ChamadoNotificacaoService;
use App\Services\ChamadoService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChamadoController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->tipo === 'admin', 403);

        $chamados = Chamado::with(['master', 'marketplace', 'revenda', 'mensagens'])
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->when($request->prioridade, fn ($query, $prioridade) => $query->where('prioridade', $prioridade))
            ->when($request->categoria, fn ($query, $categoria) => $query->where('categoria', $categoria))
            ->when($request->nivel, fn ($query, $nivel) => $query->where('aberto_por_nivel', $nivel))
            ->when($request->busca, function ($query, $busca) {
                $query->where(function ($query) use ($busca) {
                    $query->where('numero', 'like', "%{$busca}%")
                        ->orWhere('titulo', 'like', "%{$busca}%");
                });
            })
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        $contadores = Chamado::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('admin.chamados.index', compact('chamados', 'contadores'));
    }

    public function show(string $numero)
    {
        abort_unless(request()->user()?->tipo === 'admin', 403);

        $chamado = Chamado::with(['mensagens.anexos', 'historicos', 'master', 'marketplace', 'revenda'])
            ->where('numero', $numero)
            ->firstOrFail();

        $chamado->update(['visualizado_admin' => true]);

        return view('admin.chamados.show', compact('chamado'));
    }

    public function responder(string $numero, Request $request, ChamadoService $service)
    {
        abort_unless($request->user()?->tipo === 'admin', 403);

        $chamado = Chamado::where('numero', $numero)->firstOrFail();

        $anexos = collect($request->file('anexos', []))
            ->filter(fn ($arquivo) => $arquivo instanceof \Illuminate\Http\UploadedFile && $arquivo->isValid())
            ->values()
            ->all();

        $dados = $request->validate([
            'mensagem' => ['required', 'string', 'min:3'],
            'interno' => ['nullable', 'boolean'],
        ], [
            'mensagem.required' => 'Digite uma mensagem antes de enviar.',
            'mensagem.min' => 'A mensagem deve ter pelo menos 3 caracteres.',
        ]);

        $interno = $request->boolean('interno');

        $service->responder($chamado, $request->user(), trim($dados['mensagem']), $anexos, $interno);

        if (! $interno) {
            try {
                app(ChamadoNotificacaoService::class)->respostaAdmin($chamado, trim($dados['mensagem']));
            } catch (\Throwable) {
                // Resposta já foi salva; falha de e-mail não deve impedir o atendimento.
            }
        }

        return redirect()->route('admin.chamados.show', $chamado->numero)->with('status', 'Resposta registrada.');
    }

    public function alterarStatus(string $numero, Request $request, ChamadoService $service)
    {
        abort_unless($request->user()?->tipo === 'admin', 403);

        $dados = $request->validate([
            'status' => ['required', Rule::in(ChamadoService::STATUS)],
        ]);

        $chamado = Chamado::where('numero', $numero)->firstOrFail();
        $service->alterarStatus($chamado, $request->user(), $dados['status']);

        app(ChamadoNotificacaoService::class)->statusAlterado($chamado->fresh());

        return redirect()->route('admin.chamados.show', $chamado->numero)->with('status', 'Status atualizado.');
    }

    public function alterarPrioridade(string $numero, Request $request)
    {
        abort_unless($request->user()?->tipo === 'admin', 403);

        $dados = $request->validate([
            'prioridade' => ['required', Rule::in(ChamadoService::PRIORIDADES)],
        ]);

        $chamado = Chamado::where('numero', $numero)->firstOrFail();
        $anterior = $chamado->prioridade;
        $chamado->update(['prioridade' => $dados['prioridade']]);
        $chamado->historicos()->create([
            'autor_id' => $request->user()->id,
            'autor_nome' => $request->user()->nomeExibicao(),
            'acao' => 'prioridade_alterada',
            'valor_anterior' => $anterior,
            'valor_novo' => $dados['prioridade'],
        ]);

        return redirect()->route('admin.chamados.show', $chamado->numero)->with('status', 'Prioridade atualizada.');
    }
}

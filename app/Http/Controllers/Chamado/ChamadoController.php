<?php

namespace App\Http\Controllers\Chamado;

use App\Http\Controllers\Controller;
use App\Models\Chamado;
use App\Models\ChamadoAnexo;
use App\Models\Usuario;
use App\Services\ChamadoNotificacaoService;
use App\Services\ChamadoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ChamadoController extends Controller
{
    public function index(Request $request, ChamadoService $service)
    {
        $usuario = $request->user();

        if ($usuario instanceof Usuario && $usuario->tipo === 'admin') {
            return redirect()->route('admin.chamados.index');
        }

        $chamados = $service->queryVisiveisPara($usuario)
            ->with('mensagens')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('categoria'), fn ($query) => $query->where('categoria', $request->string('categoria')))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('chamados.index', compact('chamados'));
    }

    public function create()
    {
        abort_if(request()->user() instanceof Usuario && request()->user()->tipo === 'admin', 404);

        return view('chamados.form');
    }

    public function store(Request $request, ChamadoService $service)
    {
        abort_if($request->user() instanceof Usuario && $request->user()->tipo === 'admin', 404);

        $dados = $request->validate([
            'titulo' => ['required', 'string', 'min:10', 'max:200'],
            'categoria' => ['required', Rule::in(ChamadoService::CATEGORIAS)],
            'prioridade' => ['required', Rule::in(ChamadoService::PRIORIDADES)],
            'mensagem' => ['required', 'string', 'min:20'],
            'anexos' => ['nullable', 'array', 'max:5'],
            'anexos.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt,zip'],
        ]);

        $chamado = $service->abrir($request->user(), $dados, $request->file('anexos', []));

        app(ChamadoNotificacaoService::class)->chamadoAberto($chamado, $dados['mensagem']);

        return redirect()->route('chamados.show', $chamado->numero)->with('status', 'Chamado aberto com sucesso.');
    }

    public function show(string $numero, Request $request, ChamadoService $service)
    {
        $chamado = Chamado::with(['mensagens.anexos', 'historicos'])
            ->where('numero', $numero)
            ->firstOrFail();

        abort_unless($service->podeAcessar($chamado, $request->user()), 403);

        return view('chamados.show', compact('chamado'));
    }

    public function responder(string $numero, Request $request, ChamadoService $service)
    {
        $chamado = Chamado::where('numero', $numero)->firstOrFail();

        abort_unless($service->podeAcessar($chamado, $request->user()), 403);
        abort_if($chamado->status === 'fechado', 422, 'Chamado fechado não aceita respostas.');

        $anexos = collect($request->file('anexos', []))
            ->filter(fn ($arquivo) => $arquivo instanceof \Illuminate\Http\UploadedFile && $arquivo->isValid())
            ->values()
            ->all();

        $dados = $request->validate([
            'mensagem' => ['required', 'string', 'min:3'],
        ], [
            'mensagem.required' => 'Digite uma mensagem antes de enviar.',
            'mensagem.min' => 'A mensagem deve ter pelo menos 3 caracteres.',
        ]);

        $service->responder($chamado, $request->user(), trim($dados['mensagem']), $anexos);

        app(ChamadoNotificacaoService::class)->respostaCliente($chamado, $dados['mensagem']);

        return redirect()->route('chamados.show', $chamado->numero)->with('status', 'Mensagem enviada.');
    }

    public function reabrir(string $numero, Request $request, ChamadoService $service)
    {
        $chamado = Chamado::where('numero', $numero)->firstOrFail();

        abort_unless($service->podeAcessar($chamado, $request->user()), 403);
        abort_unless($chamado->status === 'resolvido' && $chamado->updated_at->gte(now()->subDays(7)), 422);

        $service->alterarStatus($chamado, $request->user(), 'aberto');

        app(ChamadoNotificacaoService::class)->statusAlterado($chamado->fresh());

        return redirect()->route('chamados.show', $chamado->numero)->with('status', 'Chamado reaberto.');
    }

    public function avaliar(string $numero, Request $request, ChamadoService $service)
    {
        $chamado = Chamado::where('numero', $numero)->firstOrFail();

        abort_unless($service->podeAcessar($chamado, $request->user()), 403);
        abort_unless($chamado->status === 'fechado', 422);

        $dados = $request->validate([
            'avaliacao' => ['required', 'integer', 'min:1', 'max:5'],
            'avaliacao_comentario' => ['nullable', 'string', 'max:1000'],
        ]);

        $chamado->update($dados);

        return redirect()->route('chamados.show', $chamado->numero)->with('status', 'Avaliação registrada.');
    }

    public function download(ChamadoAnexo $anexo, Request $request, ChamadoService $service)
    {
        abort_unless($service->podeAcessar($anexo->chamado, $request->user()), 403);
        abort_unless(Storage::exists($anexo->caminho), 404);

        return Storage::download($anexo->caminho, $anexo->nome_original);
    }
}

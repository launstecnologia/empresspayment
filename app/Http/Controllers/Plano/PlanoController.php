<?php

namespace App\Http\Controllers\Plano;

use App\Http\Controllers\Controller;
use App\Models\Plano;
use App\Services\PlanoTaxaGradeService;
use App\Support\UsuarioComercial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PlanoController extends Controller
{
    public function index()
    {
        if (in_array(UsuarioComercial::tipo(), ['marketplace', 'revenda'], true)) {
            return redirect()->route('comissoes.meu-plano');
        }

        return view('plano.index', [
            'planos' => Plano::query()->where('ativo', true)->with('taxas')->orderBy('nome')->paginate(20),
            'podeGerirPlanos' => UsuarioComercial::ehAdmin(),
        ]);
    }

    public function create()
    {
        $this->autorizarGestao();

        return view('plano.form', ['plano' => new Plano]);
    }

    public function store(Request $request)
    {
        $this->autorizarGestao();
        Plano::create($request->validate([
            'nome'       => ['required', 'string', 'max:100'],
            'codigo_fv'  => ['nullable', 'string', 'max:100'],
            'descricao'  => ['nullable', 'string'],
            'ativo'      => ['boolean'],
        ]));

        return redirect()->route('planos.index')->with('status', 'Plano criado.');
    }

    public function edit(Plano $plano)
    {
        $this->autorizarGestao();
        abort_unless($plano->ativo, 404);

        return view('plano.form', compact('plano'));
    }

    public function update(Request $request, Plano $plano)
    {
        $this->autorizarGestao();
        $plano->update($request->validate([
            'nome'       => ['required', 'string', 'max:100'],
            'codigo_fv'  => ['nullable', 'string', 'max:100'],
            'descricao'  => ['nullable', 'string'],
            'ativo'      => ['boolean'],
        ]));

        return redirect()->route('planos.index')->with('status', 'Plano atualizado.');
    }

    public function show(Plano $plano, PlanoTaxaGradeService $gradeService)
    {
        if (in_array(UsuarioComercial::tipo(), ['marketplace', 'revenda'], true)) {
            return redirect()->route('comissoes.meu-plano', ['plano' => $plano->id]);
        }

        abort_unless($plano->ativo, 404);
        return view('plano.show', [
            'plano' => $plano->load('taxas.royalties'),
            'grade' => $gradeService->dadosGrade($plano),
            'debitoGrupos' => PlanoTaxaGradeService::DEBITO_GRUPOS,
            'creditoGrupos' => PlanoTaxaGradeService::CREDITO_GRUPOS,
            'podeGerirPlanos' => UsuarioComercial::ehAdmin(),
        ]);
    }

    public function salvarGrade(Request $request, Plano $plano, PlanoTaxaGradeService $gradeService)
    {
        $this->autorizarGestao();
        $dados = $request->validate([
            'grade' => ['array'],
            'grade.*' => ['array'],
        ]);

        $gradeService->salvar($plano, $dados['grade'] ?? []);

        return redirect()->route('planos.show', $plano)->with('status', 'Grade de taxas salva.');
    }

    public function inativar(Request $request, Plano $plano)
    {
        $this->autorizarGestao();

        $dados = $request->validate([
            'senha_admin' => ['required', 'string'],
            'confirmacao' => ['accepted'],
        ], [
            'senha_admin.required' => 'Informe sua senha de administrador.',
            'confirmacao.accepted' => 'Confirme que deseja ocultar este plano.',
        ]);

        if (! Hash::check($dados['senha_admin'], $request->user()->password)) {
            return redirect()
                ->route('planos.index')
                ->withErrors(['senha_admin' => 'Senha de administrador incorreta.'])
                ->with('abrir_modal_inativar_plano', $plano->id);
        }

        $plano->update(['ativo' => false]);

        return redirect()->route('planos.index')->with('status', 'Plano ocultado da listagem.');
    }

    private function autorizarGestao(): void
    {
        abort_unless(UsuarioComercial::ehAdmin(), 403);
    }
}

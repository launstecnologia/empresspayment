<?php

namespace App\Http\Controllers\Plano;

use App\Http\Controllers\Controller;
use App\Models\Plano;
use App\Services\PlanoTaxaGradeService;
use Illuminate\Http\Request;

class PlanoController extends Controller
{
    public function index()
    {
        return view('plano.index', ['planos' => Plano::with('taxas')->paginate(20)]);
    }

    public function create()
    {
        return view('plano.form', ['plano' => new Plano]);
    }

    public function store(Request $request)
    {
        Plano::create($request->validate([
            'nome' => ['required', 'string', 'max:100'],
            'descricao' => ['nullable', 'string'],
            'ativo' => ['boolean'],
        ]));

        return redirect()->route('planos.index')->with('status', 'Plano criado.');
    }

    public function edit(Plano $plano)
    {
        return view('plano.form', compact('plano'));
    }

    public function update(Request $request, Plano $plano)
    {
        $plano->update($request->validate([
            'nome' => ['required', 'string', 'max:100'],
            'descricao' => ['nullable', 'string'],
            'ativo' => ['boolean'],
        ]));

        return redirect()->route('planos.index')->with('status', 'Plano atualizado.');
    }

    public function show(Plano $plano, PlanoTaxaGradeService $gradeService)
    {
        return view('plano.show', [
            'plano' => $plano->load('taxas.royalties'),
            'grade' => $gradeService->dadosGrade($plano),
            'debitoGrupos' => PlanoTaxaGradeService::DEBITO_GRUPOS,
            'creditoGrupos' => PlanoTaxaGradeService::CREDITO_GRUPOS,
        ]);
    }

    public function salvarGrade(Request $request, Plano $plano, PlanoTaxaGradeService $gradeService)
    {
        $dados = $request->validate([
            'grade' => ['array'],
            'grade.*' => ['array'],
        ]);

        $gradeService->salvar($plano, $dados['grade'] ?? []);

        return redirect()->route('planos.show', $plano)->with('status', 'Grade de taxas salva.');
    }

    public function destroy(Plano $plano)
    {
        $plano->delete();

        return redirect()->route('planos.index')->with('status', 'Plano removido.');
    }
}

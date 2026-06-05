<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Segmento;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SegmentoController extends Controller
{
    public function index()
    {
        return view('admin.segmentos.index', [
            'segmentos' => Segmento::latest()->paginate(20),
        ]);
    }

    public function create()
    {
        return view('admin.segmentos.form', [
            'segmento' => new Segmento,
        ]);
    }

    public function store(Request $request)
    {
        Segmento::create($this->validar($request));

        return redirect()->route('segmentos.index')->with('status', 'Segmento cadastrado.');
    }

    public function edit(Segmento $segmento)
    {
        return view('admin.segmentos.form', compact('segmento'));
    }

    public function update(Request $request, Segmento $segmento)
    {
        $segmento->update($this->validar($request, $segmento));

        return redirect()->route('segmentos.index')->with('status', 'Segmento atualizado.');
    }

    public function destroy(Segmento $segmento)
    {
        $segmento->delete();

        return redirect()->route('segmentos.index')->with('status', 'Segmento removido.');
    }

    private function validar(Request $request, ?Segmento $segmento = null): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:200', Rule::unique('segmentos', 'nome')->ignore($segmento)],
            'descricao' => ['nullable', 'string'],
            'ativo' => ['nullable', 'boolean'],
        ]);
    }
}

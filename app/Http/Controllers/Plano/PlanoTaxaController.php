<?php

namespace App\Http\Controllers\Plano;

use App\Http\Controllers\Controller;
use App\Models\Plano;
use App\Models\PlanoTaxa;
use App\Support\UsuarioComercial;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanoTaxaController extends Controller
{
    public function create(Plano $plano)
    {
        $this->autorizarGestao();
        return view('plano.taxas.form', [
            'plano' => $plano,
            'taxa' => new PlanoTaxa,
        ]);
    }

    public function store(Request $request, Plano $plano)
    {
        $this->autorizarGestao();

        $plano->taxas()->create($this->validar($request));

        return redirect()->route('planos.show', $plano)->with('status', 'Taxa criada.');
    }

    public function edit(Plano $plano, PlanoTaxa $taxa)
    {
        $this->autorizarGestao();
        $this->garantirTaxaDoPlano($plano, $taxa);

        return view('plano.taxas.form', compact('plano', 'taxa'));
    }

    public function update(Request $request, Plano $plano, PlanoTaxa $taxa)
    {
        $this->autorizarGestao();
        $this->garantirTaxaDoPlano($plano, $taxa);
        $taxa->update($this->validar($request));

        return redirect()->route('planos.show', $plano)->with('status', 'Taxa atualizada.');
    }

    public function destroy(Plano $plano, PlanoTaxa $taxa)
    {
        $this->autorizarGestao();
        $this->garantirTaxaDoPlano($plano, $taxa);
        $taxa->delete();

        return redirect()->route('planos.show', $plano)->with('status', 'Taxa removida.');
    }

    private function validar(Request $request): array
    {
        $dados = $request->validate([
            'instituicao' => ['required', 'string', 'max:50'],
            'tipo_transacao' => ['required', Rule::in(['debito', 'credito', 'pix', 'voucher'])],
            'parcelas' => ['required', 'integer', 'min:1', 'max:24'],
            'taxa_percentual' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $dados['instituicao'] = mb_strtoupper($dados['instituicao']);

        return $dados;
    }

    private function garantirTaxaDoPlano(Plano $plano, PlanoTaxa $taxa): void
    {
        abort_unless($taxa->plano_id === $plano->id, 404);
    }

    private function autorizarGestao(): void
    {
        abort_unless(UsuarioComercial::ehAdmin(), 403);
    }
}

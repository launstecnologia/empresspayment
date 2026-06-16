<?php

namespace App\Http\Controllers\Royalty;

use App\Http\Controllers\Controller;
use App\Models\PlanoTaxa;
use App\Models\PlanoTaxaRoyalty;
use App\Models\Usuario;
use App\Services\RoyaltyCalculadorService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ComissaoConfiguracaoController extends Controller
{
    public function index()
    {
        return view('comissao.configuracoes.index', [
            'configuracoes' => PlanoTaxaRoyalty::with(['taxa.plano', 'usuario'])->latest()->paginate(30),
        ]);
    }

    public function create()
    {
        return view('comissao.configuracoes.form', [
            'configuracao' => new PlanoTaxaRoyalty,
            'taxas' => PlanoTaxa::with('plano')->orderBy('instituicao')->get(),
            'usuarios' => $this->usuariosConfiguraveis(),
        ]);
    }

    public function store(Request $request, RoyaltyCalculadorService $calculador)
    {
        $dados = $this->validar($request);
        $this->validarLimite($dados, $calculador);

        PlanoTaxaRoyalty::create($dados);

        return redirect()->route('comissoes.configuracoes.index')->with('status', 'Configuração de comissão criada.');
    }

    public function edit(PlanoTaxaRoyalty $configuracao)
    {
        return view('comissao.configuracoes.form', [
            'configuracao' => $configuracao,
            'taxas' => PlanoTaxa::with('plano')->orderBy('instituicao')->get(),
            'usuarios' => $this->usuariosConfiguraveis(),
        ]);
    }

    public function update(Request $request, PlanoTaxaRoyalty $configuracao, RoyaltyCalculadorService $calculador)
    {
        $dados = $this->validar($request, $configuracao);
        $this->validarLimite($dados, $calculador);
        $configuracao->update($dados);

        return redirect()->route('comissoes.configuracoes.index')->with('status', 'Configuração de comissão atualizada.');
    }

    public function destroy(PlanoTaxaRoyalty $configuracao)
    {
        $configuracao->delete();

        return redirect()->route('comissoes.configuracoes.index')->with('status', 'Configuração de comissão removida.');
    }

    private function validar(Request $request, ?PlanoTaxaRoyalty $configuracao = null): array
    {
        $dados = $request->validate([
            'plano_taxa_id' => [
                'required',
                'exists:plano_taxas,id',
                Rule::unique('plano_taxa_royalties', 'plano_taxa_id')
                    ->where(fn ($query) => $query->where('usuario_id', $request->integer('usuario_id')))
                    ->ignore($configuracao?->id),
            ],
            'usuario_id' => ['required', 'exists:usuarios,id'],
            'percentual' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $usuario = Usuario::findOrFail($dados['usuario_id']);
        abort_if($usuario->tipo === 'revenda', 422, 'Revenda não repassa comissão para níveis abaixo.');

        $dados['nivel'] = $usuario->tipo;

        return $dados;
    }

    private function validarLimite(array $dados, RoyaltyCalculadorService $calculador): void
    {
        $taxa = PlanoTaxa::findOrFail($dados['plano_taxa_id']);
        $usuario = Usuario::findOrFail($dados['usuario_id']);
        $recebe = $calculador->percentualRecebidoUsuario($taxa, $usuario);

        $calculador->validarRepasse((float) $dados['percentual'], $recebe);
    }

    private function usuariosConfiguraveis()
    {
        return Usuario::with('hierarquia.pai.usuario')
            ->whereIn('tipo', ['admin', 'master', 'marketplace'])
            ->where('ativo', true)
            ->orderBy('tipo')
            ->orderBy('nome_fantasia')
            ->get();
    }
}

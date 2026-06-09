<?php

namespace App\Http\Controllers\Royalty;

use App\Http\Controllers\Controller;
use App\Services\MarketplacePlanoService;
use App\Services\PlanoTaxaGradeService;
use App\Services\RoyaltyCalculadorService;
use App\Support\UsuarioComercial;
use Illuminate\Http\Request;

class ComissaoMeuPlanoController extends Controller
{
    public function index(
        Request $request,
        MarketplacePlanoService $marketplacePlano,
        PlanoTaxaGradeService $gradeService,
        RoyaltyCalculadorService $calculador,
    ) {
        $usuario = UsuarioComercial::principal();
        abort_unless(in_array($usuario?->tipo, ['marketplace', 'revenda'], true), 403);

        $planos = $marketplacePlano->planosDisponiveis($usuario);
        $plano = null;
        $grade = null;

        if ($planos->isNotEmpty()) {
            $planoId = $request->integer('plano');
            $plano = $planos->firstWhere('id', $planoId) ?? $planos->first();

            abort_unless($marketplacePlano->planoPermitido($plano->id, $usuario), 403);

            $grade = $gradeService->dadosGradeComissaoUsuario($plano, $usuario, $calculador);
        }

        return view('comissao.meu-plano', [
            'planos' => $planos,
            'plano' => $plano,
            'grade' => $grade,
            'debitoGrupos' => PlanoTaxaGradeService::DEBITO_GRUPOS,
            'creditoGrupos' => PlanoTaxaGradeService::CREDITO_GRUPOS,
        ]);
    }
}

<?php

namespace Tests\Unit;

use App\Models\EstabelecimentoRoyalty;
use App\Models\Hierarquia;
use App\Models\Usuario;
use App\Services\RoyaltyCalculadorService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class RoyaltyCalculadorServiceTest extends TestCase
{
    private RoyaltyCalculadorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new RoyaltyCalculadorService;
    }

    public function test_calcula_retencao_do_pai_sobre_comissao_bruta(): void
    {
        $marketplace = new Usuario(['tipo' => 'marketplace']);
        $marketplace->id = 10;
        $revenda = new Usuario(['tipo' => 'revenda', 'percentual_retencao_pai' => 20]);
        $revenda->id = 20;

        $noMarketplace = new Hierarquia(['usuario_id' => 10]);
        $noMarketplace->setRelation('usuario', $marketplace);

        $noRevenda = new Hierarquia(['usuario_id' => 20, 'pai_id' => 1]);
        $noRevenda->setRelation('pai', $noMarketplace);
        $revenda->setRelation('hierarquia', $noRevenda);

        $resultado = $this->service->calcularRetencaoPai($revenda, 100.0);

        $this->assertSame(10, $resultado['pai']?->id);
        $this->assertSame(20.0, $resultado['valor']);
    }

    public function test_distribui_comissao_liquida_para_revenda_e_retencao_para_marketplace(): void
    {
        $marketplace = new Usuario(['tipo' => 'marketplace']);
        $marketplace->id = 10;
        $revenda = new Usuario(['tipo' => 'revenda', 'percentual_retencao_pai' => 20]);
        $revenda->id = 20;

        $noMarketplace = new Hierarquia(['usuario_id' => 10]);
        $noMarketplace->setRelation('usuario', $marketplace);

        $noRevenda = new Hierarquia(['usuario_id' => 20]);
        $noRevenda->setRelation('pai', $noMarketplace);
        $revenda->setRelation('hierarquia', $noRevenda);

        $royalties = collect([
            new EstabelecimentoRoyalty([
                'usuario_id' => 20,
                'nivel' => 'revenda',
                'percentual_royalty' => 1.0,
                'ordem' => 3,
            ]),
        ]);

        $lancamentos = $this->service->distribuirComissoes(
            10_000,
            $royalties,
            collect([20 => $revenda, 10 => $marketplace])
        );

        $this->assertSame(80.0, $lancamentos[20]['valor']);
        $this->assertSame(20.0, $lancamentos[10]['valor']);
    }

    public function test_admin_retem_sobre_comissao_do_marketplace(): void
    {
        $admin = new Usuario(['tipo' => 'admin']);
        $admin->id = 1;
        $marketplace = new Usuario(['tipo' => 'marketplace', 'percentual_retencao_pai' => 20]);
        $marketplace->id = 10;

        $noAdmin = new Hierarquia(['usuario_id' => 1]);
        $noAdmin->setRelation('usuario', $admin);

        $noMarketplace = new Hierarquia(['usuario_id' => 10]);
        $noMarketplace->setRelation('pai', $noAdmin);
        $marketplace->setRelation('hierarquia', $noMarketplace);

        $royalties = collect([
            new EstabelecimentoRoyalty([
                'usuario_id' => 10,
                'nivel' => 'marketplace',
                'percentual_royalty' => 0.5,
                'ordem' => 2,
            ]),
        ]);

        $lancamentos = $this->service->distribuirComissoes(
            100_000,
            $royalties,
            collect([10 => $marketplace, 1 => $admin])
        );

        $this->assertSame(400.0, $lancamentos[10]['valor']);
        $this->assertSame(100.0, $lancamentos[1]['valor']);
    }
}

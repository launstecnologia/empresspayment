<?php

namespace Tests\Unit;

use App\Models\Estabelecimento;
use App\Models\PagbankLog;
use App\Services\PagBankTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PagBankTokenServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_renova_token_com_sucesso(): void
    {
        config([
            'pagbank.token' => 'token-parceiro',
            'pagbank.api_url' => 'https://sandbox.api.pagseguro.com',
        ]);

        Http::fake([
            'sandbox.api.pagseguro.com/oauth2/token' => Http::response([
                'access_token' => 'novo-access',
                'refresh_token' => 'novo-refresh',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
        ]);

        $estab = Estabelecimento::withoutGlobalScopes()->create([
            'pessoa_tipo' => 'fisica',
            'status' => 'em_cadastro',
            'pagbank_account_id' => 'ACCO_TEST',
            'pagbank_refresh_token' => 'refresh-antigo',
        ]);

        (new PagBankTokenService)->renovar($estab);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/oauth2/token')
                && $request['grant_type'] === 'refresh_token';
        });

        $estab->refresh();

        $this->assertSame('novo-access', $estab->pagbank_access_token);
        $this->assertSame('novo-refresh', $estab->pagbank_refresh_token);
        $this->assertNotNull($estab->pagbank_token_expira);

        $this->assertDatabaseHas('pagbank_logs', [
            'estabelecimento_id' => $estab->id,
            'tipo' => 'renovar_token',
            'sucesso' => 1,
        ]);
    }

    public function test_renova_token_lanca_excecao_quando_api_falha(): void
    {
        config([
            'pagbank.token' => 'token-parceiro',
            'pagbank.api_url' => 'https://sandbox.api.pagseguro.com',
        ]);

        Http::fake([
            'sandbox.api.pagseguro.com/oauth2/token' => Http::response(['error' => 'invalid_grant'], 401),
        ]);

        $estab = Estabelecimento::withoutGlobalScopes()->create([
            'pessoa_tipo' => 'fisica',
            'status' => 'em_cadastro',
            'pagbank_account_id' => 'ACCO_TEST',
            'pagbank_refresh_token' => 'refresh-invalido',
        ]);

        $this->expectException(\RuntimeException::class);

        (new PagBankTokenService)->renovar($estab);

        $this->assertSame(1, PagbankLog::where('estabelecimento_id', $estab->id)->where('sucesso', false)->count());
    }
}

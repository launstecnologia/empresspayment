<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class DirectAdminService
{
    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('directadmin.url'))
            ->asForm()
            ->withBasicAuth((string) config('directadmin.usuario'), (string) config('directadmin.senha'))
            ->timeout(20);
    }

    public function criarSubdominio(string $subdominio): bool
    {
        $response = $this->client()->post('/CMD_API_SUBDOMAINS', [
            'action' => 'create',
            'domain' => config('directadmin.dominio'),
            'subdomain' => $subdominio,
        ]);

        return $response->successful();
    }

    public function criarEmail(string $subdominio, string $prefixo, string $senha, int $cota = 250): bool
    {
        $response = $this->client()->post('/CMD_API_POP', [
            'action' => 'create',
            'domain' => $this->dominioCompleto($subdominio),
            'user' => $prefixo,
            'passwd' => $senha,
            'passwd2' => $senha,
            'quota' => $cota,
        ]);

        return $response->successful();
    }

    public function alterarSenhaEmail(string $subdominio, string $prefixo, string $novaSenha): bool
    {
        $response = $this->client()->post('/CMD_API_POP', [
            'action' => 'modify',
            'domain' => $this->dominioCompleto($subdominio),
            'user' => $prefixo,
            'passwd' => $novaSenha,
            'passwd2' => $novaSenha,
        ]);

        return $response->successful();
    }

    public function redirecionarEmail(string $subdominio, string $prefixo, string $destino): bool
    {
        $response = $this->client()->post('/CMD_API_EMAIL_FORWARDERS', [
            'action' => 'create',
            'domain' => $this->dominioCompleto($subdominio),
            'user' => $prefixo,
            'email' => $destino,
        ]);

        return $response->successful();
    }

    public function excluirEmail(string $subdominio, string $prefixo): bool
    {
        $response = $this->client()->post('/CMD_API_POP', [
            'action' => 'delete',
            'domain' => $this->dominioCompleto($subdominio),
            'select0' => $prefixo,
        ]);

        return $response->successful();
    }

    private function dominioCompleto(string $subdominio): string
    {
        return $subdominio.'.'.config('directadmin.dominio');
    }
}

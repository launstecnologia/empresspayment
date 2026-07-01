<?php

namespace App\Console\Commands;

use App\Services\TenantSslProvisionerService;
use Illuminate\Console\Command;

class TenantSslFinalizeCommand extends Command
{
    protected $signature = 'tenant:ssl-finalize {domain : Domínio personalizado (ex: julio.com.br)}';

    protected $description = 'Gera config Nginx HTTPS após o Certbot emitir o certificado';

    public function handle(TenantSslProvisionerService $service): int
    {
        $domain = strtolower(trim($this->argument('domain')));

        try {
            $service->finalizarAposCertbot($domain);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Config HTTPS gerada para {$domain}.");

        return self::SUCCESS;
    }
}

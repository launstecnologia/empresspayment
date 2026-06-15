<?php

namespace App\Console\Commands;

use App\Models\PlatformSetting;
use App\Support\PlatformSettings;
use Illuminate\Console\Command;

class PlatformEdiTokenCommand extends Command
{
    protected $signature = 'platform:edi-token
                            {token : Token EDI do parceiro PagBank}
                            {--sandbox : Grava no token sandbox}
                            {--producao : Grava no token produção (padrão)}';

    protected $description = 'Define token EDI PagBank nas configurações da plataforma (via CLI)';

    public function handle(): int
    {
        $producao = ! $this->option('sandbox') || $this->option('producao');
        $coluna = $producao ? 'pagbank_edi_token_producao' : 'pagbank_edi_token_sandbox';
        $ambiente = $producao ? 'producao' : 'sandbox';

        $config = PlatformSetting::query()->firstOrCreate([], [
            'app_name' => config('app.name', 'Express Payments'),
            'meta_robots' => 'noindex, nofollow',
            'theme_color' => '#2563eb',
        ]);

        $config->update([
            $coluna => trim($this->argument('token')),
            'pagbank_ambiente' => $ambiente,
        ]);

        PlatformSettings::forget();

        $this->info("Token EDI {$ambiente} salvo com sucesso.");
        $this->line('Ambiente PagBank ativo: '.PlatformSettings::pagbankAmbienteRotulo());
        $this->line('EDI configurado: '.(PlatformSettings::ediConfigurado() ? 'sim' : 'não'));

        return self::SUCCESS;
    }
}

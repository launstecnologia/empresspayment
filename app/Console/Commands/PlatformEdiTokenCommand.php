<?php

namespace App\Console\Commands;

use App\Models\PlatformSetting;
use App\Support\PlatformSettings;
use Illuminate\Console\Command;

class PlatformEdiTokenCommand extends Command
{
    protected $signature = 'platform:edi-token
                            {token : Token EDI do parceiro PagBank}
                            {--user= : USER EDI do parceiro (modelo 1xN)}
                            {--sandbox : Grava no token sandbox}
                            {--producao : Grava no token produção (padrão)}';

    protected $description = 'Define credenciais EDI PagBank nas configurações da plataforma (via CLI)';

    public function handle(): int
    {
        $producao = ! $this->option('sandbox') || $this->option('producao');
        $colunaToken = $producao ? 'pagbank_edi_token_producao' : 'pagbank_edi_token_sandbox';
        $colunaUser = $producao ? 'pagbank_edi_user_producao' : 'pagbank_edi_user_sandbox';
        $ambiente = $producao ? 'producao' : 'sandbox';

        $config = PlatformSetting::query()->firstOrCreate([], [
            'app_name' => config('app.name', 'Express Payments'),
            'meta_robots' => 'noindex, nofollow',
            'theme_color' => '#2563eb',
        ]);

        $update = [
            $colunaToken => trim($this->argument('token')),
            'pagbank_ambiente' => $ambiente,
        ];

        if ($this->option('user')) {
            $update[$colunaUser] = trim((string) $this->option('user'));
        }

        $config->update($update);

        PlatformSettings::forget();

        $this->info("Credenciais EDI {$ambiente} salvas com sucesso.");
        $this->line('Ambiente PagBank ativo: '.PlatformSettings::pagbankAmbienteRotulo());
        $this->line('USER EDI: '.(PlatformSettings::ediUser() ?: '(não configurado)'));
        $this->line('EDI configurado: '.(PlatformSettings::ediConfigurado() ? 'sim' : 'não'));

        return self::SUCCESS;
    }
}

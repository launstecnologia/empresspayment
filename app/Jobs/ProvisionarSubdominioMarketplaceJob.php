<?php

namespace App\Jobs;

use App\Models\MarketplaceBranding;
use App\Services\DirectAdminService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProvisionarSubdominioMarketplaceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $brandingId) {}

    public function handle(DirectAdminService $directAdmin): void
    {
        $branding = MarketplaceBranding::query()->find($this->brandingId);

        if (! $branding || $branding->subdominio_provisionado) {
            return;
        }

        if ($directAdmin->criarSubdominio($branding->slug)) {
            $branding->update(['subdominio_provisionado' => true]);
        }
    }
}

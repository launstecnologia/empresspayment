<?php

namespace App\Jobs;

use App\Services\NotificacaoEmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnviarNotificacaoEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @param  array<string, mixed>  $vars
     */
    public function __construct(
        public string $slug,
        public string $destinatario,
        public array $vars = [],
        public ?string $link = null,
    ) {}

    public function handle(NotificacaoEmailService $service): void
    {
        $service->enviar($this->slug, $this->destinatario, $this->vars, $this->link);
    }
}

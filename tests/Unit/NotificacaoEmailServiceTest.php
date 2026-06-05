<?php

namespace Tests\Unit;

use App\Models\EmailTemplate;
use App\Services\NotificacaoEmailService;
use App\Support\PlatformMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificacaoEmailServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_substituicao_de_placeholders_no_assunto(): void
    {
        EmailTemplate::create([
            'slug' => 'teste.unitario',
            'nome' => 'Teste',
            'categoria' => 'sistema',
            'assunto' => 'Olá {nome} — {app_name}',
            'corpo' => 'Corpo para {nome}',
            'ativo' => true,
        ]);

        $texto = PlatformMail::substituirPlaceholders('Olá {nome} — {app_name}', [
            'nome' => 'Maria',
            'app_name' => 'Express',
        ]);

        $this->assertSame('Olá Maria — Express', $texto);
    }

    public function test_template_inativo_retorna_falso(): void
    {
        EmailTemplate::create([
            'slug' => 'teste.inativo',
            'nome' => 'Inativo',
            'categoria' => 'sistema',
            'assunto' => 'Teste',
            'corpo' => 'Corpo',
            'ativo' => false,
        ]);

        $service = new NotificacaoEmailService;
        $resultado = $service->enviar('teste.inativo', 'teste@example.com');

        $this->assertFalse($resultado);
    }
}

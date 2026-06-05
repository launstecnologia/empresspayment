<?php

namespace Database\Seeders;

use App\Models\Hierarquia;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Usuario::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'tipo' => 'admin',
                'pessoa_tipo' => 'juridica',
                'razao_social' => 'Administrador Plataforma',
                'nome_fantasia' => 'Admin',
                'password' => 'password',
                'ativo' => true,
            ]
        );

        Hierarquia::firstOrCreate(
            ['usuario_id' => $admin->id],
            ['nivel' => 'admin']
        );

        $this->call(EmailTemplateSeeder::class);
    }
}

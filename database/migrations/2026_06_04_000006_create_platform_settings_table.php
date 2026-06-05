<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('app_name', 120)->default('Express Payments');
            $table->string('meta_description', 500)->nullable();
            $table->string('meta_keywords', 500)->nullable();
            $table->string('meta_robots', 80)->default('noindex, nofollow');
            $table->string('theme_color', 7)->default('#2563eb');
            $table->string('logo_path')->nullable();
            $table->string('logo_white_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('razao_social', 200)->nullable();
            $table->string('nome_fantasia', 200)->nullable();
            $table->string('cnpj', 18)->nullable();
            $table->string('inscricao_estadual', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('celular', 20)->nullable();
            $table->string('site_url', 255)->nullable();
            $table->string('cep', 10)->nullable();
            $table->string('endereco', 200)->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento', 100)->nullable();
            $table->string('bairro', 100)->nullable();
            $table->string('cidade', 100)->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('responsavel_nome', 200)->nullable();
            $table->string('responsavel_cpf', 14)->nullable();
            $table->text('observacoes_relatorio')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};

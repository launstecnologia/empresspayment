<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_brandings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_id')->unique()->constrained('usuarios')->cascadeOnDelete();
            $table->string('slug', 63)->unique();
            $table->string('app_name', 120)->nullable();
            $table->string('primary_color', 7)->default('#2563eb');
            $table->string('logo_path')->nullable();
            $table->string('logo_white_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('custom_domain', 255)->nullable()->unique();
            $table->timestamp('custom_domain_verified_at')->nullable();
            $table->boolean('whitelabel_ativo')->default(true);
            $table->boolean('subdominio_provisionado')->default(false);
            $table->timestamps();

            $table->index('custom_domain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_brandings');
    }
};

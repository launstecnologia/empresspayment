<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_plano', function (Blueprint $table) {
            $table->foreignId('marketplace_id')->constrained('usuarios')->cascadeOnDelete();
            $table->foreignId('plano_id')->constrained('planos')->cascadeOnDelete();
            $table->primary(['marketplace_id', 'plano_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_plano');
    }
};

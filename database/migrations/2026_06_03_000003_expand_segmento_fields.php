<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('segmento', 200)->nullable()->change();
        });

        Schema::table('estabelecimentos', function (Blueprint $table) {
            $table->string('segmento', 200)->nullable()->change();
        });

        Schema::table('segmentos', function (Blueprint $table) {
            $table->string('nome', 200)->change();
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('segmento', 100)->nullable()->change();
        });

        Schema::table('estabelecimentos', function (Blueprint $table) {
            $table->string('segmento', 100)->nullable()->change();
        });

        Schema::table('segmentos', function (Blueprint $table) {
            $table->string('nome', 100)->change();
        });
    }
};

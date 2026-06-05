<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edi_movimentos', function (Blueprint $table) {
            $table->string('arranjo_ur', 50)->nullable()->after('meio_pagamento')->index();
        });
    }

    public function down(): void
    {
        Schema::table('edi_movimentos', function (Blueprint $table) {
            $table->dropColumn('arranjo_ur');
        });
    }
};

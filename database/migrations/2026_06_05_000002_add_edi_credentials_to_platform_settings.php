<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->string('pagbank_edi_token_sandbox', 500)->nullable()->after('pagbank_ambiente');
            $table->string('pagbank_edi_token_producao', 500)->nullable()->after('pagbank_edi_token_sandbox');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn([
                'pagbank_edi_token_sandbox',
                'pagbank_edi_token_producao',
            ]);
        });
    }
};

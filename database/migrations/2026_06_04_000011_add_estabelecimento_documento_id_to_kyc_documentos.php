<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kyc_documentos', function (Blueprint $table) {
            $table->foreignId('estabelecimento_documento_id')
                ->nullable()
                ->after('estabelecimento_id')
                ->constrained('estabelecimento_documentos')
                ->nullOnDelete();

            $table->unique('estabelecimento_documento_id');
        });
    }

    public function down(): void
    {
        Schema::table('kyc_documentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('estabelecimento_documento_id');
        });
    }
};

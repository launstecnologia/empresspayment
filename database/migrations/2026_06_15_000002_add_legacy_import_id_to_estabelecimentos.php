<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estabelecimentos', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_import_id')->nullable()->after('ativo');

            $table->unique('legacy_import_id');
        });
    }

    public function down(): void
    {
        Schema::table('estabelecimentos', function (Blueprint $table) {
            $table->dropUnique(['legacy_import_id']);
            $table->dropColumn('legacy_import_id');
        });
    }
};

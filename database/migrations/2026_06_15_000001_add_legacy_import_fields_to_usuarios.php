<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('legacy_pagbank_id', 50)->nullable()->after('ativo');
            $table->unsignedBigInteger('legacy_import_id')->nullable()->after('legacy_pagbank_id');

            $table->unique('legacy_pagbank_id');
            $table->unique('legacy_import_id');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropUnique(['legacy_pagbank_id']);
            $table->dropUnique(['legacy_import_id']);
            $table->dropColumn(['legacy_pagbank_id', 'legacy_import_id']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('email');
        });

        Schema::table('sub_usuarios', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('sub_usuarios', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
    }
};

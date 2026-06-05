<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->string('pagbank_client_id', 255)->nullable()->after('pagbank_token');
            $table->text('pagbank_client_secret')->nullable()->after('pagbank_client_id');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn(['pagbank_client_id', 'pagbank_client_secret']);
        });
    }
};

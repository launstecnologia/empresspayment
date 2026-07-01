<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_brandings', function (Blueprint $table) {
            $table->timestamp('ssl_provisioned_at')->nullable()->after('custom_domain_verified_at');
            $table->text('ssl_last_error')->nullable()->after('ssl_provisioned_at');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_brandings', function (Blueprint $table) {
            $table->dropColumn(['ssl_provisioned_at', 'ssl_last_error']);
        });
    }
};

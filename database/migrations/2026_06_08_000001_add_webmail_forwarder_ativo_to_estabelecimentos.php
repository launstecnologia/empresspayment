<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estabelecimentos', function (Blueprint $table) {
            $table->boolean('webmail_forwarder_ativo')->default(false)->after('webmail_senha');
        });

        // Marca como ativo os que já têm e-mail cadastrado (forwarder criado antes desta migration)
        DB::table('estabelecimentos')
            ->whereNotNull('webmail_email')
            ->update(['webmail_forwarder_ativo' => true]);
    }

    public function down(): void
    {
        Schema::table('estabelecimentos', function (Blueprint $table) {
            $table->dropColumn('webmail_forwarder_ativo');
        });
    }
};

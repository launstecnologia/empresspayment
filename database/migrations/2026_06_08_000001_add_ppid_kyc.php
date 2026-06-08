<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ppid_uso_mensal', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('ano');
            $table->unsignedTinyInteger('mes');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('limite')->default(490);
            $table->timestamps();

            $table->unique(['ano', 'mes']);
        });

        Schema::table('platform_settings', function (Blueprint $table) {
            $table->string('ppid_api_url', 255)->nullable()->after('brasilapi_url');
            $table->string('ppid_email', 150)->nullable()->after('ppid_api_url');
            $table->text('ppid_senha')->nullable()->after('ppid_email');
            $table->unsignedInteger('ppid_limite_mensal')->default(490)->after('ppid_senha');
        });

        Schema::table('kyc_documentos', function (Blueprint $table) {
            $table->string('ppid_consulta_id', 64)->nullable()->after('openai_analisado_em');
        });
    }

    public function down(): void
    {
        Schema::table('kyc_documentos', function (Blueprint $table) {
            $table->dropColumn('ppid_consulta_id');
        });

        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn(['ppid_api_url', 'ppid_email', 'ppid_senha', 'ppid_limite_mensal']);
        });

        Schema::dropIfExists('ppid_uso_mensal');
    }
};

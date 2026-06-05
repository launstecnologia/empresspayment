<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->string('mail_mailer', 20)->default('log')->after('observacoes_relatorio');
            $table->string('mail_host', 255)->nullable()->after('mail_mailer');
            $table->unsignedSmallInteger('mail_port')->nullable()->after('mail_host');
            $table->string('mail_encryption', 10)->nullable()->after('mail_port');
            $table->string('mail_username', 255)->nullable()->after('mail_encryption');
            $table->text('mail_password')->nullable()->after('mail_username');
            $table->string('mail_from_address', 150)->nullable()->after('mail_password');
            $table->string('mail_from_name', 120)->nullable()->after('mail_from_address');
            $table->boolean('mail_reset_ativo')->default(true)->after('mail_from_name');
            $table->unsignedSmallInteger('mail_reset_expira_minutos')->default(60)->after('mail_reset_ativo');
            $table->string('mail_reset_assunto', 200)->nullable()->after('mail_reset_expira_minutos');
            $table->text('mail_reset_corpo')->nullable()->after('mail_reset_assunto');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn([
                'mail_mailer',
                'mail_host',
                'mail_port',
                'mail_encryption',
                'mail_username',
                'mail_password',
                'mail_from_address',
                'mail_from_name',
                'mail_reset_ativo',
                'mail_reset_expira_minutos',
                'mail_reset_assunto',
                'mail_reset_corpo',
            ]);
        });
    }
};

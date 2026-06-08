<?php

use App\Http\Controllers\Auth\TrocaSenhaObrigatoriaController;
use App\Http\Controllers\Admin\ChamadoController as AdminChamadoController;
use App\Http\Controllers\Admin\AdminKycController;
use App\Http\Controllers\Admin\ConfiguracaoPlataformaController;
use App\Http\Controllers\Admin\EstabelecimentoAutomacaoController;
use App\Http\Controllers\Admin\EstabelecimentoPagBankController;
use App\Http\Controllers\Admin\EmailTemplateController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\MarketplaceBrandingAdminController;
use App\Http\Controllers\Marketplace\MarketplaceBrandingController;
use App\Http\Controllers\Admin\SegmentoController;
use App\Http\Controllers\Admin\SubUsuarioController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Chamado\ChamadoController;
use App\Http\Controllers\Estabelecimento\EstabelecimentoCaixaEmailController;
use App\Http\Controllers\Estabelecimento\EstabelecimentoController;
use App\Http\Controllers\Estabelecimento\EstabelecimentoWebmailController;
use App\Http\Controllers\Kyc\KycController;
use App\Http\Controllers\Estabelecimento\EstabelecimentoDocumentoController;
use App\Http\Controllers\Estabelecimento\EstabelecimentoEmailController;
use App\Http\Controllers\Estabelecimento\EstabelecimentoEmailPainelController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\Plano\PlanoController;
use App\Http\Controllers\Plano\PlanoTaxaController;
use App\Http\Controllers\Publico\EnvioDocumentoController;
use App\Http\Controllers\Relatorio\RelatorioController;
use App\Http\Controllers\Royalty\ComissaoConfiguracaoController;
use App\Http\Controllers\Royalty\RoyaltyController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::get('/envio-documento/{token}', [EnvioDocumentoController::class, 'create'])->name('documentos.public.create');
Route::post('/envio-documento/{token}', [EnvioDocumentoController::class, 'store'])->name('documentos.public.store');

Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

Route::get('/password/forgot', [PasswordResetController::class, 'create'])->name('password.request');
Route::post('/password/forgot', [PasswordResetController::class, 'store'])->name('password.email');
Route::get('/password/reset/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
Route::post('/password/reset', [PasswordResetController::class, 'update'])->name('password.update');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/senha/criar', [TrocaSenhaObrigatoriaController::class, 'create'])->name('senha.trocar');
    Route::post('/senha/criar', [TrocaSenhaObrigatoriaController::class, 'store'])->name('senha.trocar.salvar');
});

Route::middleware(['auth', 'trocar.senha', 'tenant.access'])->group(function () {
    Route::get('/perfil', [PerfilController::class, 'edit'])->name('perfil.edit');
    Route::put('/perfil', [PerfilController::class, 'update'])->name('perfil.update');
    Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
    Route::prefix('admin/chamados')->name('admin.chamados.')->group(function () {
        Route::get('/', [AdminChamadoController::class, 'index'])->name('index');
        Route::get('/{numero}', [AdminChamadoController::class, 'show'])->name('show');
        Route::post('/{numero}/responder', [AdminChamadoController::class, 'responder'])->name('responder');
        Route::post('/{numero}/status', [AdminChamadoController::class, 'alterarStatus'])->name('status');
        Route::post('/{numero}/prioridade', [AdminChamadoController::class, 'alterarPrioridade'])->name('prioridade');
    });
    Route::prefix('chamados')->name('chamados.')->group(function () {
        Route::get('/', [ChamadoController::class, 'index'])->name('index');
        Route::get('/criar', [ChamadoController::class, 'create'])->name('create');
        Route::post('/', [ChamadoController::class, 'store'])->name('store');
        Route::get('/anexos/{anexo}', [ChamadoController::class, 'download'])->name('anexos.download');
        Route::get('/{numero}', [ChamadoController::class, 'show'])->name('show');
        Route::post('/{numero}/mensagem', [ChamadoController::class, 'responder'])->name('responder');
        Route::post('/{numero}/reabrir', [ChamadoController::class, 'reabrir'])->name('reabrir');
        Route::post('/{numero}/avaliar', [ChamadoController::class, 'avaliar'])->name('avaliar');
    });
    Route::get('usuarios/{usuario}/subusuarios/create', [SubUsuarioController::class, 'create'])->name('usuarios.subusuarios.create');
    Route::post('usuarios/{usuario}/subusuarios', [SubUsuarioController::class, 'store'])->name('usuarios.subusuarios.store');
    Route::get('usuarios/{usuario}/subusuarios/{subUsuario}/senha', [SubUsuarioController::class, 'editPassword'])->name('usuarios.subusuarios.password.edit');
    Route::put('usuarios/{usuario}/subusuarios/{subUsuario}/senha', [SubUsuarioController::class, 'updatePassword'])->name('usuarios.subusuarios.password.update');
    Route::post('usuarios/{usuario}/subusuarios/{subUsuario}/resetar-senha', [SubUsuarioController::class, 'resetarSenha'])->name('usuarios.subusuarios.resetar-senha');
    Route::post('usuarios/{usuario}/resetar-senha', [UsuarioController::class, 'resetarSenha'])->name('usuarios.resetar-senha');
    Route::resource('usuarios', UsuarioController::class)->except(['destroy']);
    Route::resource('segmentos', SegmentoController::class)->except(['show'])->middleware('nivel:admin');
    Route::patch('estabelecimentos/{estabelecimento}/status', [EstabelecimentoController::class, 'updateStatus'])->name('estabelecimentos.status.update');
    Route::post('estabelecimentos/{estabelecimento}/documentos', [EstabelecimentoDocumentoController::class, 'store'])->name('estabelecimentos.documentos.store');
    Route::get('documentos/{documento}/download', [EstabelecimentoDocumentoController::class, 'download'])->name('documentos.download');
    Route::delete('estabelecimentos/{estabelecimento}/documentos/{documento}', [EstabelecimentoDocumentoController::class, 'destroy'])->name('estabelecimentos.documentos.destroy');
    Route::prefix('estabelecimentos/{estabelecimento}')->name('estabelecimentos.')->group(function () {
        Route::get('email', [EstabelecimentoEmailPainelController::class, 'index'])->name('email.painel');
        Route::patch('subdominio', [EstabelecimentoEmailController::class, 'updateSubdominio'])->name('subdominio.update');
        Route::post('emails', [EstabelecimentoEmailController::class, 'store'])->name('emails.store');
        Route::post('emails/provisionar', [EstabelecimentoEmailController::class, 'provisionar'])->name('emails.provisionar');
        Route::patch('emails/{conta}/senha', [EstabelecimentoEmailController::class, 'updateSenha'])->name('emails.senha');
        Route::patch('emails/{conta}/redirecionar', [EstabelecimentoEmailController::class, 'redirecionar'])->name('emails.redirecionar');
        Route::delete('emails/{conta}', [EstabelecimentoEmailController::class, 'destroy'])->name('emails.destroy');
        Route::post('emails/{conta}/sincronizar', [EstabelecimentoEmailController::class, 'sincronizar'])->name('emails.sincronizar');
        Route::get('emails/{conta}/caixa', [EstabelecimentoCaixaEmailController::class, 'index'])->name('emails.caixa');
        Route::get('emails/{conta}/caixa/{mensagem}', [EstabelecimentoCaixaEmailController::class, 'show'])->name('emails.caixa.show');
        Route::post('emails/{conta}/caixa/enviar', [EstabelecimentoCaixaEmailController::class, 'enviar'])->name('emails.caixa.enviar');
        Route::post('webmail/criar', [EstabelecimentoWebmailController::class, 'criar'])->name('webmail.criar');
        Route::post('webmail/sso', [EstabelecimentoWebmailController::class, 'sso'])->name('webmail.sso');
        Route::patch('webmail/senha', [EstabelecimentoWebmailController::class, 'trocarSenha'])->name('webmail.senha');
    });
    Route::get('estabelecimentos/{estabelecimento}/kyc', [KycController::class, 'show'])->name('estabelecimentos.kyc.show');
    Route::post('estabelecimentos/{estabelecimento}/kyc/documento', [KycController::class, 'enviarDocumento'])->name('estabelecimentos.kyc.documento');
    Route::delete('kyc/documentos/{documento}', [KycController::class, 'removerDocumento'])->name('kyc.documentos.destroy');
    Route::resource('estabelecimentos', EstabelecimentoController::class);
    Route::resource('planos', PlanoController::class);
    Route::prefix('planos/{plano}')->name('planos.')->group(function () {
        Route::post('grade-taxas', [PlanoController::class, 'salvarGrade'])->name('grade-taxas.salvar');
        Route::get('taxas/create', [PlanoTaxaController::class, 'create'])->name('taxas.create');
        Route::post('taxas', [PlanoTaxaController::class, 'store'])->name('taxas.store');
        Route::get('taxas/{taxa}/edit', [PlanoTaxaController::class, 'edit'])->name('taxas.edit');
        Route::put('taxas/{taxa}', [PlanoTaxaController::class, 'update'])->name('taxas.update');
        Route::delete('taxas/{taxa}', [PlanoTaxaController::class, 'destroy'])->name('taxas.destroy');
    });
    Route::get('/comissoes', [RoyaltyController::class, 'index'])->name('comissoes.index');
    Route::redirect('/royalties', '/comissoes');
    Route::resource('comissoes/configuracoes', ComissaoConfiguracaoController::class)
        ->parameters(['configuracoes' => 'configuracao'])
        ->names('comissoes.configuracoes');
    Route::get('/relatorios/faturamento', [RelatorioController::class, 'faturamento'])->name('relatorios.faturamento');
    Route::get('/relatorios/faturamento/{linha}/detalhe', [RelatorioController::class, 'faturamentoDetalhe'])->name('relatorios.faturamento.detalhe');

    Route::middleware('nivel:admin')->group(function () {
        Route::get('/admin/configuracoes', [ConfiguracaoPlataformaController::class, 'edit'])->name('admin.configuracoes.edit');
        Route::put('/admin/configuracoes', [ConfiguracaoPlataformaController::class, 'update'])->name('admin.configuracoes.update');
        Route::post('/admin/configuracoes/email/testar', [ConfiguracaoPlataformaController::class, 'testarEmail'])->name('admin.configuracoes.email.testar');
        Route::post('/admin/configuracoes/pagbank/buscar-credenciais', [ConfiguracaoPlataformaController::class, 'buscarCredenciaisPagBank'])->name('admin.configuracoes.pagbank.buscar-credenciais');
        Route::prefix('admin/kyc')->name('admin.kyc.')->group(function () {
            Route::get('/', [AdminKycController::class, 'index'])->name('index');
            Route::get('/documentos/{documento}/arquivo', [AdminKycController::class, 'arquivo'])->name('documentos.arquivo');
            Route::post('/documentos/{documento}/override', [AdminKycController::class, 'override'])->name('documentos.override');
            Route::post('/documentos/{documento}/reanalise', [AdminKycController::class, 'solicitarReanalise'])->name('documentos.reanalise');
            Route::post('/{kyc}/processar-pendentes', [AdminKycController::class, 'processarPendentes'])->name('processar-pendentes');
            Route::get('/{kyc}', [AdminKycController::class, 'show'])->name('show');
            Route::post('/{kyc}/aprovar', [AdminKycController::class, 'aprovar'])->name('aprovar');
            Route::post('/{kyc}/reprovar', [AdminKycController::class, 'reprovar'])->name('reprovar');
        });
        Route::patch('estabelecimentos/{estabelecimento}/pagbank/edi', [EstabelecimentoPagBankController::class, 'atualizarEdi'])
            ->name('admin.estabelecimentos.pagbank.edi');
        Route::post('estabelecimentos/{estabelecimento}/automacao/iniciar', [EstabelecimentoAutomacaoController::class, 'iniciar'])
            ->name('admin.estabelecimentos.automacao.iniciar');
        Route::post('estabelecimentos/{estabelecimento}/automacao/retentar-email', [EstabelecimentoAutomacaoController::class, 'retentarEmail'])
            ->name('admin.estabelecimentos.automacao.retentar-email');
        Route::get('admin/email-templates', [EmailTemplateController::class, 'index'])->name('admin.email-templates.index');
        Route::get('admin/email-templates/{emailTemplate}/edit', [EmailTemplateController::class, 'edit'])->name('admin.email-templates.edit');
        Route::put('admin/email-templates/{emailTemplate}', [EmailTemplateController::class, 'update'])->name('admin.email-templates.update');
        Route::post('admin/email-templates/{emailTemplate}/teste', [EmailTemplateController::class, 'teste'])->name('admin.email-templates.teste');
    });

    Route::middleware('nivel:admin')->group(function () {
        Route::get('/marketplace/whitelabel', [MarketplaceBrandingController::class, 'edit'])->name('marketplace.branding.edit');
        Route::put('/marketplace/whitelabel', [MarketplaceBrandingController::class, 'update'])->name('marketplace.branding.update');
        Route::post('/marketplace/whitelabel/verificar-dominio', [MarketplaceBrandingController::class, 'verificarDominio'])->name('marketplace.branding.verificar-dominio');
    });

    Route::put('/usuarios/{usuario}/whitelabel', [MarketplaceBrandingAdminController::class, 'update'])->name('usuarios.whitelabel.update');
});

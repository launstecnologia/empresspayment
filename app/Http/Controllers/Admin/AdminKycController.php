<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KycAnalise;
use App\Models\KycDocumento;
use App\Services\KycDocumentoSyncService;
use App\Services\KycFinalizacaoService;
use App\Services\KycHistoricoService;
use App\Support\KycDocumentosObrigatorios;
use App\Scopes\ExcluirInativoSistemaScope;
use App\Scopes\HierarquiaScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminKycController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $query = KycAnalise::query()
            ->with(['estabelecimentoCompleto.marketplace'])
            ->latest('updated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('busca')) {
            $termo = '%'.$request->string('busca')->trim().'%';
            $query->whereHas('estabelecimentoCompleto', function (Builder $q) use ($termo) {
                $q->where('nome_fantasia', 'like', $termo)
                    ->orWhere('razao_social', 'like', $termo)
                    ->orWhere('nome_completo', 'like', $termo)
                    ->orWhere('cnpj', 'like', $termo)
                    ->orWhere('cpf', 'like', $termo);
            });
        }

        $contagens = KycAnalise::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.kyc.index', [
            'kycs' => $query->paginate(20)->withQueryString(),
            'filtros' => $request->only(['status', 'busca']),
            'contagens' => $contagens,
        ]);
    }

    public function show(Request $request, KycAnalise $kyc)
    {
        $this->authorizeAdmin($request);

        $kyc->load([
            'estabelecimentoCompleto.marketplace',
            'estabelecimentoCompleto.pagbankLogs' => fn ($q) => $q->latest()->limit(3),
            'documentos',
            'historico',
            'admin',
        ]);

        return view('admin.kyc.show', [
            'kyc' => $kyc,
            'estabelecimento' => $kyc->estabelecimentoCompleto,
        ]);
    }

    public function aprovar(Request $request, KycAnalise $kyc, KycFinalizacaoService $finalizacao)
    {
        $this->authorizeAdmin($request);

        $dados = $request->validate([
            'motivo' => ['nullable', 'string', 'max:2000'],
        ]);

        $finalizacao->aprovar($kyc, (int) $request->user()->id, $dados['motivo'] ?? null);

        return redirect()
            ->route('admin.kyc.show', $kyc)
            ->with('status', 'KYC aprovado. Cadastro PagBank enfileirado.');
    }

    public function reprovar(Request $request, KycAnalise $kyc, KycFinalizacaoService $finalizacao)
    {
        $this->authorizeAdmin($request);

        $dados = $request->validate([
            'motivo' => ['required', 'string', 'max:2000'],
        ]);

        $finalizacao->reprovar($kyc, (int) $request->user()->id, $dados['motivo']);

        return redirect()
            ->route('admin.kyc.show', $kyc)
            ->with('status', 'KYC reprovado.');
    }

    public function override(Request $request, KycDocumento $documento, KycFinalizacaoService $finalizacao, KycHistoricoService $historico)
    {
        $this->authorizeAdmin($request);

        $dados = $request->validate([
            'decisao' => ['required', 'in:aprovado,reprovado'],
            'motivo' => ['nullable', 'string', 'max:2000'],
        ]);

        $documento->update([
            'admin_override' => $dados['decisao'],
            'admin_override_motivo' => $dados['motivo'] ?? null,
        ]);

        $historico->registrar(
            $documento->kycAnalise,
            'admin_override_documento',
            "Documento {$documento->tipo}: {$dados['decisao']}",
            ['documento_id' => $documento->id],
            $request->user(),
        );

        $finalizacao->verificar($documento->kyc_analise_id);

        return redirect()
            ->route('admin.kyc.show', $documento->kyc_analise_id)
            ->with('status', 'Decisão manual registrada no documento.');
    }

    public function processarPendentes(Request $request, KycAnalise $kyc, KycDocumentoSyncService $kycSync)
    {
        $this->authorizeAdmin($request);

        $kyc->load('estabelecimento');
        $disparados = $kycSync->processarAnalisesPendentes($kyc->estabelecimento);

        return redirect()
            ->route('admin.kyc.show', $kyc)
            ->with('status', $disparados > 0
                ? "{$disparados} análise(s) enfileirada(s). Aguarde alguns segundos e atualize a página."
                : 'Nenhum documento pendente de análise automática.');
    }

    public function solicitarReanalise(Request $request, KycDocumento $documento, KycHistoricoService $historico)
    {
        $this->authorizeAdmin($request);

        $documento->update([
            'openai_status' => 'pendente',
            'openai_motivo_reprovacao' => null,
            'openai_dados_extraidos' => null,
            'openai_analisado_em' => null,
            'ppid_consulta_id' => null,
            'cruzamento_status' => 'nao_verificado',
            'cruzamento_divergencias' => null,
            'admin_override' => null,
            'admin_override_motivo' => null,
        ]);

        app(KycDocumentoSyncService::class)->dispararAnalise($documento->fresh());

        $historico->registrar($documento->kycAnalise, 'reanalise_solicitada', "Reanálise do documento {$documento->tipo}", null, $request->user());

        return redirect()
            ->route('admin.kyc.show', $documento->kyc_analise_id)
            ->with('status', 'Documento reenviado para análise.');
    }

    public function arquivo(Request $request, KycDocumento $documento)
    {
        $this->authorizeAdmin($request);

        $disk = $documento->usaDiscoPublico() ? 'public' : 'local';

        abort_unless(Storage::disk($disk)->exists($documento->caminho), 404);

        return Storage::disk($disk)->response(
            $documento->caminho,
            $documento->nome_original,
            ['Content-Type' => $documento->mime_type]
        );
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user() && $request->user()->tipo === 'admin', 403);
    }
}

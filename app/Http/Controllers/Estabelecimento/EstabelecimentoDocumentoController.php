<?php

namespace App\Http\Controllers\Estabelecimento;

use App\Http\Controllers\Controller;
use App\Models\Estabelecimento;
use App\Models\EstabelecimentoDocumento;
use App\Services\KycDocumentoSyncService;
use App\Services\LogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EstabelecimentoDocumentoController extends Controller
{
    public function store(Request $request, Estabelecimento $estabelecimento, LogService $log, KycDocumentoSyncService $kycSync)
    {
        $dados = $this->validar($request);
        $arquivo = $request->file('documento');
        $path = $arquivo->store("estabelecimentos/{$estabelecimento->id}/documentos", 'public');

        $documento = $estabelecimento->documentos()->create([
            'tipo_documento' => $dados['tipo_documento'],
            'arquivo_path' => $path,
            'arquivo_nome' => $arquivo->getClientOriginalName(),
        ]);

        $log->registrar('Estabelecimento', $estabelecimento->id, 'documento_inserido', 'Documento cadastrado com sucesso', null, [
            'documento_id' => $documento->id,
            'tipo_documento' => $documento->tipo_documento,
        ]);

        $documento->setRelation('estabelecimento', $estabelecimento);
        $kycSync->sincronizar($documento, $request->user());

        $mensagem = \App\Support\KycTipoDocumentoMapper::tipoKyc($documento->tipo_documento)
            ? 'Documento adicionado. Análise KYC iniciada automaticamente.'
            : 'Documento adicionado.';

        return redirect()
            ->route('estabelecimentos.show', $estabelecimento)
            ->with('status', $mensagem)
            ->withFragment('documentos');
    }

    public function download(EstabelecimentoDocumento $documento)
    {
        abort_unless(Storage::disk('public')->exists($documento->arquivo_path), 404);

        return Storage::disk('public')->download($documento->arquivo_path, $documento->arquivo_nome);
    }

    public function destroy(Estabelecimento $estabelecimento, EstabelecimentoDocumento $documento, LogService $log, KycDocumentoSyncService $kycSync)
    {
        abort_unless((int) $documento->estabelecimento_id === (int) $estabelecimento->id, 404);

        $kycSync->removerPorEstabelecimentoDocumento($documento);

        Storage::disk('public')->delete($documento->arquivo_path);
        $documento->delete();

        $log->registrar('Estabelecimento', $estabelecimento->id, 'documento_removido', 'Documento removido com sucesso');

        return redirect()
            ->route('estabelecimentos.show', $estabelecimento)
            ->with('status', 'Documento removido.')
            ->withFragment('documentos');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'tipo_documento' => ['required', 'string', 'max:100'],
            'documento' => ['required', 'file', 'max:25600', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx'],
        ]);
    }
}

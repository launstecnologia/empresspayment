<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Estabelecimento;
use App\Services\KycDocumentoSyncService;
use App\Services\LogService;
use Illuminate\Http\Request;

class EnvioDocumentoController extends Controller
{
    public function create(string $token)
    {
        $estabelecimento = $this->estabelecimento($token);

        return view('publico.envio-documento', compact('estabelecimento'));
    }

    public function store(Request $request, string $token, LogService $log, KycDocumentoSyncService $kycSync)
    {
        $estabelecimento = $this->estabelecimento($token);

        $dados = $request->validate([
            'tipo_documento' => ['required', 'string', 'max:100'],
            'documento' => ['required', 'file', 'max:25600', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx'],
        ]);

        $arquivo = $request->file('documento');
        $path = $arquivo->store("estabelecimentos/{$estabelecimento->id}/documentos", 'public');

        $documento = $estabelecimento->documentos()->create([
            'tipo_documento' => $dados['tipo_documento'],
            'arquivo_path' => $path,
            'arquivo_nome' => $arquivo->getClientOriginalName(),
        ]);

        $log->registrar('Estabelecimento', $estabelecimento->id, 'documento_inserido', 'Documento enviado pelo link público', null, [
            'documento_id' => $documento->id,
            'tipo_documento' => $documento->tipo_documento,
        ]);

        $documento->setRelation('estabelecimento', $estabelecimento);
        $kycSync->sincronizar($documento);

        return redirect()->route('documentos.public.create', $token)->with('status', 'Documento enviado com sucesso.');
    }

    private function estabelecimento(string $token): Estabelecimento
    {
        return Estabelecimento::withoutGlobalScopes()
            ->where('documento_token_publico', $token)
            ->firstOrFail();
    }
}

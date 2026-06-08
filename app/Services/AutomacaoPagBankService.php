<?php

namespace App\Services;

use App\Models\Estabelecimento;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AutomacaoPagBankService
{
    private string $apiUrl;
    private string $apiKey;

    public function __construct()
    {
        $url = PlatformSettings::automacaoApiUrl();
        $key = PlatformSettings::automacaoApiKey();

        if (! filled($url) || ! filled($key)) {
            throw new RuntimeException(
                'Automação PagBank não configurada. '
                . 'Defina AUTOMACAO_API_URL e AUTOMACAO_API_KEY no .env'
            );
        }

        $this->apiUrl = $url;
        $this->apiKey = $key;
    }

    // ----------------------------------------------------------------
    // Verificação de saúde
    // ----------------------------------------------------------------
    public function healthOk(): bool
    {
        try {
            $resp = Http::timeout(5)
                ->get("{$this->apiUrl}/health");

            return $resp->ok() && ($resp->json('ok') === true);
        } catch (\Throwable) {
            return false;
        }
    }

    // ----------------------------------------------------------------
    // Inicia o job de automação
    // Retorna o job_id ou lança exceção
    // ----------------------------------------------------------------
    public function iniciarCadastro(Estabelecimento $estab, string $senha6): string
    {
        $payload = $this->montarPayload($estab, $senha6);

        $response = Http::timeout(15)
            ->withHeaders(['X-Api-Key' => $this->apiKey])
            ->post("{$this->apiUrl}/cadastrar", $payload);

        if ($response->status() === 409) {
            // Já existe job em andamento — retorna o job_id existente
            $jobId = $response->json('job_id');
            Log::info('AutomacaoPagBank: job já em andamento', [
                'estabelecimento_id' => $estab->id,
                'job_id' => $jobId,
            ]);

            return $jobId;
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                'Falha ao iniciar automação: '.$response->status().' — '.$response->body()
            );
        }

        return $response->json('job_id');
    }

    // ----------------------------------------------------------------
    // Retenta apenas a etapa de e-mail
    // ----------------------------------------------------------------
    public function retentarEmail(Estabelecimento $estab, string $senha6): string
    {
        $payload = [
            'estabelecimento_id'  => $estab->id,
            'webmail_url'         => PlatformSettings::automacaoWebmailUrl() ?? '',
            'webmail_usuario'     => $estab->webmail_email ?? '',
            'webmail_senha'       => $estab->webmail_senha ?? '',
            'senha_6'             => $senha6,
            'headless'            => config('automacao.headless', true),
            'aguardar_email_seg'  => config('automacao.aguardar_email_seg', 90),
        ];

        $response = Http::timeout(15)
            ->withHeaders(['X-Api-Key' => $this->apiKey])
            ->post("{$this->apiUrl}/retentar-email", $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Falha ao retentar e-mail: '.$response->status().' — '.$response->body()
            );
        }

        return $response->json('job_id');
    }

    // ----------------------------------------------------------------
    // Consulta status do job
    // ----------------------------------------------------------------
    public function consultarStatus(string $jobId): array
    {
        $response = Http::timeout(10)
            ->withHeaders(['X-Api-Key' => $this->apiKey])
            ->get("{$this->apiUrl}/status/{$jobId}");

        if (! $response->successful()) {
            throw new RuntimeException(
                "Falha ao consultar job {$jobId}: ".$response->status().' — '.$response->body()
            );
        }

        return $response->json();
    }

    // ----------------------------------------------------------------
    // Monta o payload com os dados do estabelecimento
    // ----------------------------------------------------------------
    private function montarPayload(Estabelecimento $estab, string $senha6): array
    {
        $cpfCnpj = $estab->pessoa_tipo === 'juridica'
            ? $this->formatarCnpj($estab->cnpj)
            : $this->formatarCpf($estab->cpf);

        $dados = [
            'cpf_cnpj'        => $cpfCnpj,
            'email'           => $estab->email,
            'email_confirmar' => $estab->email,
            'celular'         => preg_replace('/\D/', '', $estab->celular ?? ''),
            'telefone'        => preg_replace('/\D/', '', $estab->telefone ?? ''),
            'url_site'        => '',
            'faturamento'     => $this->mapearFaturamento($estab),
            'cep'             => preg_replace('/\D/', '', $estab->cep ?? ''),
            'endereco'        => $estab->endereco ?? '',
            'bairro'          => $estab->bairro ?? '',
            'numero'          => $estab->numero ?? 'S/N',
            'complemento'     => $estab->complemento ?? '',
            'estado'          => $estab->uf ?? '',
            'segmento'        => $this->mapearSegmento($estab),
            'tipo_link'       => 'Link Mobile',
            'promocao'        => $estab->plano?->codigo_fv ?? '',
        ];

        if ($estab->pessoa_tipo === 'juridica') {
            $dados = array_merge($dados, [
                'razao_social'  => $estab->razao_social ?? '',
                'nome_fantasia' => $estab->nome_fantasia ?? $estab->razao_social ?? '',
                'cpf_socio'     => $this->formatarCpf($estab->rep_cpf ?? $estab->cpf ?? ''),
                'nascimento'    => $estab->rep_data_nascimento
                    ? $estab->rep_data_nascimento->format('d/m/Y')
                    : ($estab->data_nascimento ? $estab->data_nascimento->format('d/m/Y') : ''),
                'nome_socio'    => $estab->rep_nome ?? $estab->nome_completo ?? '',
            ]);
        }

        return [
            'estabelecimento_id'  => $estab->id,
            'dados'               => $dados,
            'fv_usuario'          => PlatformSettings::automacaoFvUsuario() ?? '',
            'fv_senha'            => PlatformSettings::automacaoFvSenha() ?? '',
            'webmail_url'         => PlatformSettings::automacaoWebmailUrl() ?? '',
            // Email e senha do webmail vêm do cadastro do estabelecimento na plataforma
            'webmail_usuario'     => $estab->webmail_email ?? '',
            'webmail_senha'       => $estab->webmail_senha ?? '',
            'senha_6'             => $senha6,
            'headless'            => config('automacao.headless', true),
            'aguardar_email_seg'  => config('automacao.aguardar_email_seg', 90),
        ];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------
    private function formatarCpf(?string $cpf): string
    {
        $n = preg_replace('/\D/', '', $cpf ?? '');
        if (strlen($n) === 11) {
            return substr($n, 0, 3).'.'.substr($n, 3, 3).'.'.substr($n, 6, 3).'-'.substr($n, 9);
        }

        return $cpf ?? '';
    }

    private function formatarCnpj(?string $cnpj): string
    {
        $n = preg_replace('/\D/', '', $cnpj ?? '');
        if (strlen($n) === 14) {
            return substr($n, 0, 2).'.'.substr($n, 2, 3).'.'.substr($n, 5, 3)
                .'/'.substr($n, 8, 4).'-'.substr($n, 12);
        }

        return $cnpj ?? '';
    }

    private function mapearFaturamento(Estabelecimento $estab): string
    {
        // Usa o faturamento cadastrado no estabelecimento, com fallback padrão
        return $estab->faturamento_mensal ?: 'De R$ 1 mil até R$ 5 mil';
    }

    private function mapearSegmento(Estabelecimento $estab): string
    {
        // Os segmentos agora são cadastrados com os nomes exatos do portal PagBank FV
        // Retorna direto o campo segmento, com fallback para "Outras atividades empresariais"
        return filled($estab->segmento) ? $estab->segmento : 'Outras atividades empresariais';
    }
}

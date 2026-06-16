<?php

namespace App\Console\Commands;

use App\Models\Estabelecimento;
use App\Services\EdiProcessadorService;
use App\Support\PlatformSettings;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class EdiTestarCommand extends Command
{
    protected $signature = 'edi:testar
                            {estabelecimento? : ID do estabelecimento (filtro na importação)}
                            {--data= : Data Y-m-d (padrão: ontem)}
                            {--user= : USER do Basic Auth (padrão: config plataforma)}
                            {--token= : TOKEN EDI (padrão: config da plataforma)}
                            {--importar : Grava movimentos se VALIDADO=TRUE}';

    protected $description = 'Testa conexão EDI PagBank e mostra diagnóstico';

    public function handle(EdiProcessadorService $service): int
    {
        $estabelecimento = null;

        if ($this->argument('estabelecimento')) {
            $estabelecimento = Estabelecimento::withoutGlobalScopes()->find($this->argument('estabelecimento'));

            if (! $estabelecimento) {
                $this->error('Estabelecimento não encontrado.');

                return self::FAILURE;
            }
        }

        $data = $this->option('data')
            ? Carbon::parse($this->option('data'))->format('Y-m-d')
            : now()->subDay()->format('Y-m-d');

        $ediUser = filled($this->option('user'))
            ? trim((string) $this->option('user'))
            : (string) (PlatformSettings::ediUser() ?? '');

        $ediToken = filled($this->option('token'))
            ? trim((string) $this->option('token'))
            : (string) (PlatformSettings::ediToken() ?? '');

        if ($estabelecimento) {
            $this->info("Estabelecimento #{$estabelecimento->id} — {$estabelecimento->nome_fantasia}");
            $this->line("token_pagseguro (vínculo): {$estabelecimento->token_pagseguro}");
        } else {
            $this->info('Teste EDI — credenciais da plataforma (modelo 1xN)');
        }

        $this->line("USER na requisição: {$ediUser}");
        $this->line('Ambiente PagBank: '.PlatformSettings::pagbankAmbienteRotulo());
        $this->line('EDI URL: '.PlatformSettings::ediUrl());
        $this->line('TOKEN EDI: '.(filled($ediToken) ? 'informado' : 'NÃO configurado'));
        $this->line('Autenticação: Basic Auth base64(USER parceiro:TOKEN)');
        $this->newLine();

        if (blank($ediUser)) {
            $this->error('USER EDI não configurado (Admin → PagBank, platform:edi-token --user= ou --user=).');

            return self::FAILURE;
        }

        if (blank($ediToken)) {
            $this->error('Token EDI não configurado (Admin → PagBank, platform:edi-token ou --token=).');

            return self::FAILURE;
        }

        $url = PlatformSettings::ediUrl()."/movement/v3.00/transactional/{$data}";

        $this->line("Consultando: {$url}");

        try {
            $response = Http::baseUrl(PlatformSettings::ediUrl())
                ->withBasicAuth($ediUser, $ediToken)
                ->acceptJson()
                ->timeout(60)
                ->get("/movement/v3.00/transactional/{$data}", [
                    'pageNumber' => 1,
                    'pageSize' => 10,
                ]);
        } catch (\Throwable $e) {
            $this->error('Erro HTTP: '.$e->getMessage());

            return self::FAILURE;
        }

        $validado = $response->header('VALIDADO') ?? $response->header('validado');
        $validadoOk = strtoupper((string) $validado) === 'TRUE';
        $payload = $response->json() ?? [];
        $movimentos = $payload['detalhes'] ?? $payload['movimentos'] ?? $payload['content'] ?? $payload['data'] ?? [];
        $pagination = $payload['pagination'] ?? null;

        if (is_array($movimentos) && ! array_is_list($movimentos)) {
            $movimentos = [];
        }

        $this->table(
            ['Campo', 'Valor'],
            [
                ['HTTP status', (string) $response->status()],
                ['Header VALIDADO', $validado ?: '(vazio)'],
                ['Validado (interpretado)', $validadoOk ? 'sim' : 'não'],
                ['Movimentos na página', (string) count($movimentos)],
                ['Total (pagination)', is_array($pagination) ? (string) ($pagination['totalElements'] ?? '—') : '—'],
                ['Páginas', is_array($pagination) ? (string) ($pagination['totalPages'] ?? '—') : '—'],
            ],
        );

        if ($response->failed()) {
            $this->error('Resposta de erro:');
            $this->line(substr($response->body(), 0, 800));

            return self::FAILURE;
        }

        if (! $validadoOk) {
            $this->warn('Arquivo ainda não validado pelo PagBank.');

            return self::FAILURE;
        }

        if (count($movimentos) === 0) {
            $this->warn("VALIDADO mas sem movimentos em {$data} (sem vendas nesse dia).");

            return self::SUCCESS;
        }

        $codigosEstabelecimento = collect($movimentos)
            ->pluck('estabelecimento')
            ->filter()
            ->unique()
            ->values();

        if ($codigosEstabelecimento->isNotEmpty()) {
            $this->line('IDs PagBank nos movimentos: '.$codigosEstabelecimento->implode(', '));
        }

        $this->info('Conexão OK — amostra do primeiro movimento:');
        $this->line(json_encode($movimentos[0] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if ($this->option('importar')) {
            $total = $service->processarPagina(
                $data,
                'transactional',
                1,
                $payload,
                $estabelecimento?->id,
            );
            $this->info("Importados/atualizados: {$total} movimento(s).");
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Services;

use App\Models\Estabelecimento;
use App\Models\Plano;
use App\Models\Usuario;
use App\Support\DocumentoBrasil;
use App\Support\EstabelecimentoSchema;
use App\Support\LegacyImportConcerns;
use App\Support\SimpleXlsxReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LegacyEstImportService
{
    use LegacyImportConcerns;

    /** @var array<string, Usuario> */
    private array $cacheMarketplacePorNome = [];

    /** @var array<string, Usuario> */
    private array $cacheRevendaPorNome = [];

    /** @var array<string, Plano> */
    private array $cachePlanoPorChave = [];

    public function __construct(
        private readonly RoyaltyCalculadorService $royaltyCalculador,
    ) {}

    /**
     * @return array{criados: int, ignorados: int, erros: int, linhas: list<array<string, mixed>>}
     */
    public function importar(
        string $path,
        bool $dryRun = false,
        bool $skipTest = true,
        ?int $limit = null,
        int $offset = 0,
    ): array {
        $rows = SimpleXlsxReader::rowsAssociativos($path);
        $estRows = array_values(array_filter(
            $rows,
            fn (array $row) => ! in_array(strtoupper(trim((string) ($row['tag'] ?? ''))), ['MKT', 'REP'], true),
        ));

        if ($offset > 0) {
            $estRows = array_slice($estRows, $offset);
        }

        if ($limit !== null) {
            $estRows = array_slice($estRows, 0, max(0, $limit));
        }

        $resultado = [
            'criados' => 0,
            'ignorados' => 0,
            'erros' => 0,
            'linhas' => [],
        ];

        $this->precarregarMarketplaces();
        $this->precarregarRevendas();
        $this->precarregarPlanos();

        foreach ($estRows as $row) {
            $linha = $this->processarLinha($row, $dryRun, $skipTest);
            $resultado['linhas'][] = $linha;

            match ($linha['status']) {
                'criado' => $resultado['criados']++,
                'ignorado' => $resultado['ignorados']++,
                default => $resultado['erros']++,
            };
        }

        return $resultado;
    }

    private function precarregarMarketplaces(): void
    {
        Usuario::query()
            ->where('tipo', 'marketplace')
            ->get(['id', 'nome_fantasia', 'razao_social', 'nome_completo'])
            ->each(function (Usuario $usuario) {
                foreach ($this->chavesNomeUsuario($usuario) as $chave) {
                    $this->cacheMarketplacePorNome[$chave] = $usuario;
                }
            });
    }

    private function precarregarRevendas(): void
    {
        Usuario::query()
            ->where('tipo', 'revenda')
            ->with('hierarquia.pai.usuario')
            ->get(['id', 'nome_fantasia', 'razao_social', 'nome_completo'])
            ->each(function (Usuario $usuario) {
                foreach ($this->chavesNomeUsuario($usuario) as $chave) {
                    $this->cacheRevendaPorNome[$chave] = $usuario;
                }
            });
    }

    private function precarregarPlanos(): void
    {
        Plano::query()
            ->get(['id', 'nome', 'codigo_fv'])
            ->each(function (Plano $plano) {
                foreach ($this->chavesNomeTexto($plano->nome) as $chave) {
                    $this->cachePlanoPorChave[$chave] = $plano;
                }

                if (filled($plano->codigo_fv)) {
                    foreach ($this->chavesNomeTexto($plano->codigo_fv) as $chave) {
                        $this->cachePlanoPorChave[$chave] = $plano;
                    }
                }
            });
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function processarLinha(array $row, bool $dryRun, bool $skipTest): array
    {
        $fantasia = trim((string) ($row['client_statement'] ?? $row['nome_listagem'] ?? ''));
        $token = trim((string) ($row['token'] ?? $row['id_estabelecimento'] ?? ''));
        $legacyId = $this->normalizarInteiro($row['id'] ?? null);
        $email = strtolower(trim((string) ($row['client_email'] ?? '')));
        $nomeMarketplace = trim((string) ($row['marketplace'] ?? $row['mkt'] ?? ''));
        $nomeRepresentante = trim((string) ($row['representante'] ?? ''));

        if ($skipTest && $this->ehRegistroTeste($fantasia, $email)) {
            return $this->resultadoLinha($fantasia, $token, $nomeMarketplace, 'ignorado', 'Registro de teste ignorado.');
        }

        if ($token === '') {
            return $this->resultadoLinha($fantasia, $token, $nomeMarketplace, 'erro', 'Token PagBank ausente.');
        }

        $revenda = $nomeRepresentante !== '' ? $this->resolverRevenda($nomeRepresentante) : null;
        $avisos = [];

        if ($nomeRepresentante !== '' && ! $revenda) {
            $avisos[] = "Revenda não encontrada: {$nomeRepresentante}";
        }

        $marketplace = $nomeMarketplace !== ''
            ? $this->resolverMarketplace($nomeMarketplace)
            : ($revenda ? $this->marketplaceDoUsuario($revenda) : null);

        if ($nomeMarketplace !== '' && ! $marketplace) {
            $avisos[] = "Marketplace não encontrado: {$nomeMarketplace}";
        }

        if ($revenda) {
            $marketplaceRevenda = $this->marketplaceDoUsuario($revenda);

            if ($marketplace && $marketplaceRevenda && $marketplaceRevenda->id !== $marketplace->id) {
                $avisos[] = "Revenda {$nomeRepresentante} ignorada — não pertence ao marketplace informado.";
                $revenda = null;
            } elseif (! $marketplace && $marketplaceRevenda) {
                $marketplace = $marketplaceRevenda;
            }
        }

        $existente = $this->buscarDuplicata($row, $token, $legacyId);

        if ($existente) {
            $motivo = match (true) {
                (string) $existente->token_pagseguro === $token => 'token_pagseguro',
                filled($legacyId) && (int) $existente->legacy_import_id === $legacyId => 'legacy_import_id',
                default => 'documento/email',
            };

            return $this->resultadoLinha(
                $fantasia,
                $token,
                $nomeMarketplace,
                'ignorado',
                "Já existe estabelecimento #{$existente->id} ({$existente->nome_fantasia}) — match: {$motivo}",
                $existente->id,
                $marketplace?->id,
                $revenda?->id,
            );
        }

        $plano = $this->resolverPlano(trim((string) ($row['plan_code'] ?? $row['plano_listagem'] ?? '')));
        $dados = $this->mapearEstabelecimento($row, $marketplace, $revenda, $plano, $token, $legacyId);

        if (! $plano) {
            $avisos[] = 'Plano não mapeado — importado sem plano_id.';
        }

        $textoAvisos = $avisos !== [] ? ' '.implode(' ', $avisos) : '';

        if ($dryRun) {
            $vinculos = trim(collect([
                $marketplace ? "MKT #{$marketplace->id}" : null,
                $revenda ? "REP #{$revenda->id}" : null,
            ])->filter()->join(' / '));

            return $this->resultadoLinha(
                $fantasia,
                $token,
                $nomeMarketplace,
                'criado',
                trim('Seria criado'.($vinculos !== '' ? " vinculado a {$vinculos}" : ' sem vínculo MKT/REP.').'.'.$textoAvisos),
                null,
                $marketplace?->id,
                $revenda?->id,
            );
        }

        try {
            $estabelecimento = DB::transaction(function () use ($dados, $plano, $row) {
                $estabelecimento = $this->salvarComDataCadastro(
                    Estabelecimento::withoutGlobalScopes()->make($dados),
                    $row,
                );

                if ($plano) {
                    $estabelecimento->load('plano.taxas.royalties');
                    $this->royaltyCalculador->fixarCadeia($estabelecimento);
                }

                return $estabelecimento;
            });

            return $this->resultadoLinha(
                $fantasia,
                $token,
                $nomeMarketplace,
                'criado',
                trim("Estabelecimento #{$estabelecimento->id} criado.{$textoAvisos}"),
                $estabelecimento->id,
                $marketplace?->id,
                $revenda?->id,
            );
        } catch (\Throwable $e) {
            return $this->resultadoLinha($fantasia, $token, $nomeMarketplace, 'erro', $e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function buscarDuplicata(array $row, string $token, ?int $legacyId): ?Estabelecimento
    {
        $query = Estabelecimento::withoutGlobalScopes();

        $porToken = (clone $query)->where('token_pagseguro', $token)->first();
        if ($porToken) {
            return $porToken;
        }

        if ($legacyId) {
            $porLegacy = (clone $query)->where('legacy_import_id', $legacyId)->first();
            if ($porLegacy) {
                return $porLegacy;
            }
        }

        $email = strtolower(trim((string) ($row['client_email'] ?? '')));
        if ($email !== '') {
            $porEmail = (clone $query)->whereRaw('LOWER(email) = ?', [$email])->first();
            if ($porEmail) {
                return $porEmail;
            }
        }

        $documento = DocumentoBrasil::apenasDigitos((string) ($row['client_document'] ?? ''));

        if (strlen($documento) === 14) {
            $cnpjFmt = DocumentoBrasil::formatarCnpj($documento);
            $porDoc = (clone $query)->where(function ($q) use ($documento, $cnpjFmt) {
                $q->whereRaw("REPLACE(REPLACE(REPLACE(cnpj, '.', ''), '/', ''), '-', '') = ?", [$documento])
                    ->orWhere('cnpj', $cnpjFmt);
            })->first();

            if ($porDoc) {
                return $porDoc;
            }
        }

        if (strlen($documento) === 11) {
            $cpfFmt = DocumentoBrasil::formatarCpf($documento);
            $porDoc = (clone $query)->where(function ($q) use ($documento, $cpfFmt) {
                $q->whereRaw("REPLACE(REPLACE(cpf, '.', ''), '-', '') = ?", [$documento])
                    ->orWhere('cpf', $cpfFmt);
            })->first();

            if ($porDoc) {
                return $porDoc;
            }
        }

        return null;
    }

    private function resolverMarketplace(string $nome): ?Usuario
    {
        foreach ($this->chavesNomeTexto($nome) as $chave) {
            if (isset($this->cacheMarketplacePorNome[$chave])) {
                return $this->cacheMarketplacePorNome[$chave];
            }
        }

        return null;
    }

    private function resolverRevenda(string $nome): ?Usuario
    {
        foreach ($this->chavesNomeTexto($nome) as $chave) {
            if (isset($this->cacheRevendaPorNome[$chave])) {
                return $this->cacheRevendaPorNome[$chave];
            }
        }

        return null;
    }

    private function resolverPlano(string $planCode): ?Plano
    {
        $planCode = trim($planCode);
        if ($planCode === '') {
            return null;
        }

        foreach ($this->chavesNomeTexto($planCode) as $chave) {
            if (isset($this->cachePlanoPorChave[$chave])) {
                return $this->cachePlanoPorChave[$chave];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapearEstabelecimento(
        array $row,
        ?Usuario $marketplace,
        ?Usuario $revenda,
        ?Plano $plano,
        string $token,
        ?int $legacyId,
    ): array {
        $tipoPessoa = strtoupper(trim((string) ($row['tipo_pessoa'] ?? 'PJ')));
        $pessoaTipo = $tipoPessoa === 'PF' ? 'fisica' : 'juridica';
        $documento = DocumentoBrasil::apenasDigitos((string) ($row['client_document'] ?? ''));

        $fantasia = trim((string) ($row['client_statement'] ?? ''));
        $razao = trim((string) ($row['client_name'] ?? $fantasia));

        $cadastradoPor = $revenda ?? $marketplace;

        $dados = [
            'pessoa_tipo' => $pessoaTipo,
            'nome_fantasia' => $fantasia ?: $razao,
            'email' => strtolower(trim((string) ($row['client_email'] ?? ''))) ?: null,
            'segmento' => filled($row['mcc_id'] ?? null) ? trim((string) $row['mcc_id']) : null,
            'cep' => $this->formatarCep((string) ($row['client_address_cep'] ?? '')),
            'endereco' => filled($row['client_address'] ?? null) ? trim((string) $row['client_address']) : null,
            'numero' => $this->normalizarNumero($row['client_address_number'] ?? null),
            'complemento' => filled($row['client_address_comp'] ?? null) ? trim((string) $row['client_address_comp']) : null,
            'bairro' => filled($row['client_address_neighborhood'] ?? null) ? trim((string) $row['client_address_neighborhood']) : null,
            'cidade' => filled($row['client_address_city'] ?? null) ? trim((string) $row['client_address_city']) : null,
            'uf' => filled($row['client_address_state'] ?? null) ? strtoupper(substr(trim((string) $row['client_address_state']), 0, 2)) : null,
            'celular' => $this->formatarTelefone((string) ($row['client_phone'] ?? '')),
            'token_pagseguro' => $token,
            'pagbank_edi_ativo' => true,
            'plano_id' => $plano?->id,
            'master_id' => null,
            'marketplace_id' => $marketplace?->id,
            'revenda_id' => $revenda?->id,
            'cadastrado_por_id' => $cadastradoPor?->id,
            'cadastrado_por_nivel' => $cadastradoPor?->tipo,
            'status' => EstabelecimentoSchema::statusParaBanco('aprovado'),
            'ativo' => true,
            'legacy_import_id' => $legacyId,
            'documento_token_publico' => (string) Str::uuid(),
            'anotacoes' => $this->montarAnotacoes($row),
        ];

        if ($pessoaTipo === 'juridica') {
            $dados['razao_social'] = $razao;
            $dados['cnpj'] = strlen($documento) === 14 ? DocumentoBrasil::formatarCnpj($documento) : null;
            $dados['rep_nome'] = filled($row['client_responsavel_name'] ?? null) ? trim((string) $row['client_responsavel_name']) : null;
            $dados['rep_cpf'] = filled($row['client_responsavel_document'] ?? null)
                ? DocumentoBrasil::formatarCpf((string) $row['client_responsavel_document'])
                : null;
            $dados['data_abertura'] = $this->parseData((string) ($row['client_date_open'] ?? ''));
            $dados['rep_data_nascimento'] = $this->parseData((string) ($row['client_responsavel_birthay'] ?? ''));
        } else {
            $dados['nome_completo'] = $razao;
            $dados['cpf'] = strlen($documento) === 11 ? DocumentoBrasil::formatarCpf($documento) : null;
            $dados['data_nascimento'] = $this->parseData((string) ($row['client_responsavel_birthay'] ?? ''));
        }

        return $dados;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function montarAnotacoes(array $row): ?string
    {
        $partes = array_filter([
            filled($row['client_anotacoes'] ?? null) ? trim((string) $row['client_anotacoes']) : null,
            filled($row['client_observacoes'] ?? null) ? trim((string) $row['client_observacoes']) : null,
        ]);

        return $partes === [] ? null : implode("\n\n", $partes);
    }

    private function resultadoLinha(
        string $fantasia,
        string $token,
        string $marketplace,
        string $status,
        string $mensagem,
        ?int $estabelecimentoId = null,
        ?int $marketplaceId = null,
        ?int $revendaId = null,
    ): array {
        return [
            'fantasia' => $fantasia,
            'token' => $token,
            'marketplace' => $marketplace,
            'marketplace_id' => $marketplaceId,
            'revenda_id' => $revendaId,
            'status' => $status,
            'mensagem' => $mensagem,
            'estabelecimento_id' => $estabelecimentoId,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\Usuario;
use App\Support\LegacyImportConcerns;
use App\Support\SimpleXlsxReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LegacyRepImportService
{
    use LegacyImportConcerns;

    /** @var array<string, Usuario> */
    private array $cacheMarketplacePorNome = [];

    /** @var array<string, Usuario> */
    private array $cacheRevendaPorNome = [];

    public function __construct(
        private readonly HierarquiaService $hierarquiaService,
    ) {}

    /**
     * @return array{criados: int, ignorados: int, erros: int, linhas: list<array<string, mixed>>}
     */
    public function importar(string $path, bool $dryRun = false, bool $skipTest = true): array
    {
        $rows = SimpleXlsxReader::rowsAssociativos($path);
        $repRows = array_values(array_filter(
            $rows,
            fn (array $row) => strtoupper(trim((string) ($row['tag'] ?? ''))) === 'REP',
        ));

        $resultado = [
            'criados' => 0,
            'ignorados' => 0,
            'erros' => 0,
            'linhas' => [],
        ];

        $this->precarregarMarketplaces();
        $this->precarregarRevendas();

        foreach ($repRows as $row) {
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
            ->with('hierarquia')
            ->get(['id', 'nome_fantasia', 'razao_social', 'nome_completo', 'email', 'cnpj', 'cpf', 'legacy_pagbank_id'])
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
            ->get(['id', 'nome_fantasia', 'razao_social', 'nome_completo', 'email', 'cnpj', 'cpf', 'legacy_pagbank_id'])
            ->each(function (Usuario $usuario) {
                foreach ($this->chavesNomeUsuario($usuario) as $chave) {
                    $this->cacheRevendaPorNome[$chave] = $usuario;
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

        if ($skipTest && $this->ehRegistroTeste($fantasia, $email)) {
            return $this->resultadoLinha($fantasia, $token, $nomeMarketplace, 'ignorado', 'Registro de teste ignorado.');
        }

        if ($nomeMarketplace === '' || strcasecmp($nomeMarketplace, 'Representante:') === 0) {
            return $this->resultadoLinha($fantasia, $token, $nomeMarketplace, 'erro', 'Marketplace não informado na coluna marketplace.');
        }

        $marketplace = $this->resolverMarketplace($nomeMarketplace);

        if (! $marketplace) {
            return $this->resultadoLinha(
                $fantasia,
                $token,
                $nomeMarketplace,
                'erro',
                "Marketplace não encontrado: {$nomeMarketplace}",
            );
        }

        if ($email === '') {
            return $this->resultadoLinha($fantasia, $token, $nomeMarketplace, 'erro', 'E-mail ausente.');
        }

        if ($this->emailEmUso($email)) {
            return $this->resultadoLinha($fantasia, $token, $nomeMarketplace, 'ignorado', "E-mail já cadastrado: {$email}");
        }

        $existente = $this->buscarDuplicataPorTipo(
            $row,
            'revenda',
            $token,
            $legacyId,
            $fantasia,
            $this->cacheRevendaPorNome,
        );

        if ($existente) {
            $motivo = match (true) {
                filled($token) && (string) $existente->legacy_pagbank_id === $token => 'legacy_pagbank_id',
                filled($legacyId) && (int) $existente->legacy_import_id === $legacyId => 'legacy_import_id',
                default => 'documento/nome',
            };

            return $this->resultadoLinha(
                $fantasia,
                $token,
                $nomeMarketplace,
                'ignorado',
                "Já existe revenda #{$existente->id} ({$existente->nome_fantasia}) — match: {$motivo}",
                $existente->id,
                $marketplace->id,
            );
        }

        if (! $marketplace->hierarquia) {
            return $this->resultadoLinha(
                $fantasia,
                $token,
                $nomeMarketplace,
                'erro',
                "Marketplace #{$marketplace->id} sem registro de hierarquia.",
            );
        }

        $dados = $this->mapearRevenda($row);

        if ($dryRun) {
            return $this->resultadoLinha(
                $fantasia,
                $token,
                $nomeMarketplace,
                'criado',
                "Seria criado vinculado ao marketplace #{$marketplace->id} (dry-run).",
                null,
                $marketplace->id,
            );
        }

        try {
            $usuario = DB::transaction(function () use ($dados, $marketplace, $row) {
                $usuario = $this->salvarComDataCadastro(Usuario::make($dados), $row);
                $hierarquia = $this->hierarquiaService->criarNo($usuario, $marketplace);
                $this->atualizarDataCadastro($hierarquia, $row);

                return $usuario;
            });

            foreach ($this->chavesNomeUsuario($usuario) as $chave) {
                $this->cacheRevendaPorNome[$chave] = $usuario;
            }

            return $this->resultadoLinha(
                $fantasia,
                $token,
                $nomeMarketplace,
                'criado',
                "Revenda #{$usuario->id} criada vinculada ao marketplace #{$marketplace->id}.",
                $usuario->id,
                $marketplace->id,
            );
        } catch (\Throwable $e) {
            return $this->resultadoLinha($fantasia, $token, $nomeMarketplace, 'erro', $e->getMessage());
        }
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

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapearRevenda(array $row): array
    {
        $dados = $this->mapearDadosComuns($row, 'revenda');
        $dados['password'] = Hash::make('123456');

        return $dados;
    }

    private function resultadoLinha(
        string $fantasia,
        string $token,
        string $marketplace,
        string $status,
        string $mensagem,
        ?int $usuarioId = null,
        ?int $marketplaceId = null,
    ): array {
        return [
            'fantasia' => $fantasia,
            'token' => $token,
            'marketplace' => $marketplace,
            'marketplace_id' => $marketplaceId,
            'status' => $status,
            'mensagem' => $mensagem,
            'usuario_id' => $usuarioId,
        ];
    }
}

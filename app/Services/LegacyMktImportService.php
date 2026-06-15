<?php

namespace App\Services;

use App\Models\Hierarquia;
use App\Models\Usuario;
use App\Support\LegacyImportConcerns;
use App\Support\SimpleXlsxReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LegacyMktImportService
{
    use LegacyImportConcerns;

    /** @var array<string, Usuario> */
    private array $cachePorNome = [];

    /**
     * @return array{criados: int, ignorados: int, erros: int, linhas: list<array<string, mixed>>}
     */
    public function importar(string $path, bool $dryRun = false, bool $skipTest = true): array
    {
        $rows = SimpleXlsxReader::rowsAssociativos($path);
        $mktRows = array_values(array_filter(
            $rows,
            fn (array $row) => strtoupper(trim((string) ($row['tag'] ?? ''))) === 'MKT',
        ));

        $resultado = [
            'criados' => 0,
            'ignorados' => 0,
            'erros' => 0,
            'linhas' => [],
        ];

        $this->precarregarMarketplaces();

        foreach ($mktRows as $row) {
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
            ->get(['id', 'nome_fantasia', 'razao_social', 'nome_completo', 'email', 'cnpj', 'cpf', 'legacy_pagbank_id'])
            ->each(function (Usuario $usuario) {
                foreach ($this->chavesNomeUsuario($usuario) as $chave) {
                    $this->cachePorNome[$chave] = $usuario;
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

        if ($skipTest && $this->ehRegistroTeste($fantasia, $email)) {
            return $this->resultadoLinha($fantasia, $token, 'ignorado', 'Registro de teste ignorado.');
        }

        if ($email === '') {
            return $this->resultadoLinha($fantasia, $token, 'erro', 'E-mail ausente.');
        }

        if ($this->emailEmUso($email)) {
            return $this->resultadoLinha($fantasia, $token, 'ignorado', "E-mail já cadastrado: {$email}");
        }

        $existente = $this->buscarDuplicataPorTipo(
            $row,
            'marketplace',
            $token,
            $legacyId,
            $fantasia,
            $this->cachePorNome,
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
                'ignorado',
                "Já existe marketplace #{$existente->id} ({$existente->nome_fantasia}) — match: {$motivo}",
                $existente->id,
            );
        }

        $dados = $this->mapearMarketplace($row);

        if ($dryRun) {
            return $this->resultadoLinha($fantasia, $token, 'criado', 'Seria criado (dry-run).');
        }

        try {
            $usuario = DB::transaction(function () use ($dados, $row) {
                $usuario = $this->salvarComDataCadastro(Usuario::make($dados), $row);

                $this->salvarComDataCadastro(Hierarquia::make([
                    'usuario_id' => $usuario->id,
                    'pai_id' => null,
                    'nivel' => 'marketplace',
                ]), $row);

                return $usuario;
            });

            foreach ($this->chavesNomeUsuario($usuario) as $chave) {
                $this->cachePorNome[$chave] = $usuario;
            }

            return $this->resultadoLinha(
                $fantasia,
                $token,
                'criado',
                "Marketplace #{$usuario->id} criado.",
                $usuario->id,
            );
        } catch (\Throwable $e) {
            return $this->resultadoLinha($fantasia, $token, 'erro', $e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapearMarketplace(array $row): array
    {
        $dados = $this->mapearDadosComuns($row, 'marketplace');
        $dados['password'] = Hash::make('123456');

        return $dados;
    }

    private function resultadoLinha(
        string $fantasia,
        string $token,
        string $status,
        string $mensagem,
        ?int $usuarioId = null,
    ): array {
        return [
            'fantasia' => $fantasia,
            'token' => $token,
            'status' => $status,
            'mensagem' => $mensagem,
            'usuario_id' => $usuarioId,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\Estabelecimento;
use App\Models\Usuario;
use App\Support\LegacyImportConcerns;
use App\Support\SimpleXlsxReader;
use Carbon\Carbon;

class LegacyImportBackfillDatesService
{
    use LegacyImportConcerns;

    /**
     * @return array{atualizados: int, ignorados: int, nao_encontrados: int, sem_data: int}
     */
    public function corrigir(string $path, string $tipo = 'all', bool $dryRun = false): array
    {
        $resultado = [
            'atualizados' => 0,
            'ignorados' => 0,
            'nao_encontrados' => 0,
            'sem_data' => 0,
        ];

        $rows = SimpleXlsxReader::rowsAssociativos($path);

        foreach ($rows as $row) {
            $tag = strtoupper(trim((string) ($row['tag'] ?? '')));

            if ($tag === 'MKT') {
                if ($tipo === 'all' || $tipo === 'mkt') {
                    $this->corrigirUsuario($row, 'marketplace', $resultado, $dryRun);
                }

                continue;
            }

            if ($tag === 'REP') {
                if ($tipo === 'all' || $tipo === 'rep') {
                    $this->corrigirUsuario($row, 'revenda', $resultado, $dryRun);
                }

                continue;
            }

            if ($tipo === 'all' || $tipo === 'est') {
                $this->corrigirEstabelecimento($row, $resultado, $dryRun);
            }
        }

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{atualizados: int, ignorados: int, nao_encontrados: int, sem_data: int}  $resultado
     */
    private function corrigirEstabelecimento(array $row, array &$resultado, bool $dryRun): void
    {
        if (! $this->parseDataCadastro($row['data_cadastro'] ?? null)) {
            $resultado['sem_data']++;

            return;
        }

        $estabelecimento = $this->buscarEstabelecimento($row);

        if (! $estabelecimento) {
            $resultado['nao_encontrados']++;

            return;
        }

        if ($this->mesmaData($estabelecimento->created_at, $row)) {
            $resultado['ignorados']++;

            return;
        }

        if ($dryRun || $this->atualizarDataCadastro($estabelecimento, $row)) {
            $resultado['atualizados']++;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{atualizados: int, ignorados: int, nao_encontrados: int, sem_data: int}  $resultado
     */
    private function corrigirUsuario(array $row, string $tipoUsuario, array &$resultado, bool $dryRun): void
    {
        if (! $this->parseDataCadastro($row['data_cadastro'] ?? null)) {
            $resultado['sem_data']++;

            return;
        }

        $usuario = $this->buscarUsuario($row, $tipoUsuario);

        if (! $usuario) {
            $resultado['nao_encontrados']++;

            return;
        }

        if ($this->mesmaData($usuario->created_at, $row)) {
            $resultado['ignorados']++;

            return;
        }

        if ($dryRun || $this->atualizarDataCadastro($usuario, $row)) {
            $resultado['atualizados']++;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function buscarEstabelecimento(array $row): ?Estabelecimento
    {
        $token = trim((string) ($row['token'] ?? $row['id_estabelecimento'] ?? ''));
        $legacyId = $this->normalizarInteiro($row['id'] ?? null);
        $query = Estabelecimento::withoutGlobalScopes();

        if ($token !== '') {
            $porToken = (clone $query)->where('token_pagseguro', $token)->first();
            if ($porToken) {
                return $porToken;
            }
        }

        if ($legacyId) {
            return (clone $query)->where('legacy_import_id', $legacyId)->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function buscarUsuario(array $row, string $tipo): ?Usuario
    {
        $token = trim((string) ($row['token'] ?? $row['id_estabelecimento'] ?? ''));
        $legacyId = $this->normalizarInteiro($row['id'] ?? null);
        $query = Usuario::query()->where('tipo', $tipo);

        if ($token !== '') {
            $porToken = (clone $query)->where('legacy_pagbank_id', $token)->first();
            if ($porToken) {
                return $porToken;
            }
        }

        if ($legacyId) {
            return (clone $query)->where('legacy_import_id', $legacyId)->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function mesmaData(mixed $createdAt, array $row): bool
    {
        $cadastro = $this->parseDataCadastro($row['data_cadastro'] ?? null);

        if (! $cadastro || ! $createdAt) {
            return false;
        }

        return $cadastro->toDateString() === Carbon::parse($createdAt)->toDateString();
    }
}

<?php

namespace App\Support;

use App\Models\SubUsuario;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait LegacyImportConcerns
{
    /**
     * @return list<string>
     */
    protected function chavesNomeUsuario(Usuario $usuario): array
    {
        return array_merge(
            $this->chavesNomeTexto($usuario->nome_fantasia ?? ''),
            $this->chavesNomeTexto($usuario->razao_social ?? ''),
            $this->chavesNomeTexto($usuario->nome_completo ?? ''),
        );
    }

    /**
     * @return list<string>
     */
    protected function chavesNomeTexto(?string $nome): array
    {
        $nome = trim((string) $nome);
        if ($nome === '') {
            return [];
        }

        $ascii = Str::ascii($nome);

        return array_values(array_unique(array_filter([
            mb_strtoupper($nome),
            mb_strtoupper($ascii),
        ])));
    }

    protected function emailEmUso(string $email): bool
    {
        return Usuario::query()->whereRaw('LOWER(email) = ?', [$email])->exists()
            || SubUsuario::query()->whereRaw('LOWER(email) = ?', [$email])->exists();
    }

    protected function ehRegistroTeste(string $fantasia, string $email): bool
    {
        return str_contains($email, 'teste')
            || strcasecmp($fantasia, 'SUA MARCA') === 0
            || strcasecmp($fantasia, 'CADASTRO TESTES') === 0;
    }

    protected function formatarCep(string $cep): ?string
    {
        $digits = DocumentoBrasil::apenasDigitos($cep);

        if (strlen($digits) !== 8) {
            return $cep !== '' ? $cep : null;
        }

        return substr($digits, 0, 5).'-'.substr($digits, 5);
    }

    protected function formatarTelefone(string $telefone): ?string
    {
        $digits = DocumentoBrasil::apenasDigitos($telefone);

        if (strlen($digits) === 11) {
            return DocumentoBrasil::formatarCelular($digits);
        }

        if (strlen($digits) === 10) {
            return '('.substr($digits, 0, 2).') '.substr($digits, 2, 4).'-'.substr($digits, 6);
        }

        return $telefone !== '' ? $telefone : null;
    }

    protected function normalizarNumero(mixed $numero): ?string
    {
        if ($numero === null || $numero === '') {
            return null;
        }

        return trim((string) $numero);
    }

    protected function normalizarInteiro(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (int) preg_replace('/\D/', '', (string) $valor);
    }

    protected function parseData(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        try {
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $valor, $m)) {
                return Carbon::createFromFormat('d/m/Y', $valor)->format('Y-m-d');
            }

            return Carbon::parse($valor)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function parseDataCadastro(mixed $valor): ?Carbon
    {
        $data = $this->parseData(trim((string) $valor));

        return $data ? Carbon::parse($data)->startOfDay() : null;
    }

    protected function aplicarDataCadastro(Model $model, array $row): void
    {
        $cadastro = $this->parseDataCadastro($row['data_cadastro'] ?? null);

        if (! $cadastro) {
            return;
        }

        $model->created_at = $cadastro;
        $model->updated_at = $cadastro;
    }

    protected function salvarComDataCadastro(Model $model, array $row): Model
    {
        $cadastro = $this->parseDataCadastro($row['data_cadastro'] ?? null);

        if ($cadastro) {
            $model->timestamps = false;
            $model->created_at = $cadastro;
            $model->updated_at = $cadastro;
        }

        $model->save();

        if ($cadastro && $model->exists) {
            $model->newQueryWithoutScopes()
                ->whereKey($model->getKey())
                ->update([
                    'created_at' => $cadastro,
                    'updated_at' => $cadastro,
                ]);
        }

        return $model;
    }

    protected function atualizarDataCadastro(Model $model, array $row): bool
    {
        $cadastro = $this->parseDataCadastro($row['data_cadastro'] ?? null);

        if (! $cadastro || ! $model->exists) {
            return false;
        }

        return (bool) $model->newQueryWithoutScopes()
            ->whereKey($model->getKey())
            ->update([
                'created_at' => $cadastro,
                'updated_at' => $cadastro,
            ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function mapearDadosComuns(array $row, string $tipo): array
    {
        $tipoPessoa = strtoupper(trim((string) ($row['tipo_pessoa'] ?? 'PJ')));
        $pessoaTipo = $tipoPessoa === 'PF' ? 'fisica' : 'juridica';
        $documento = DocumentoBrasil::apenasDigitos((string) ($row['client_document'] ?? ''));

        $fantasia = trim((string) ($row['client_statement'] ?? ''));
        $razao = trim((string) ($row['client_name'] ?? $fantasia));

        $dados = [
            'tipo' => $tipo,
            'pessoa_tipo' => $pessoaTipo,
            'nome_fantasia' => $fantasia ?: $razao,
            'email' => strtolower(trim((string) $row['client_email'])),
            'must_change_password' => true,
            'ativo' => true,
            'segmento' => filled($row['mcc_id'] ?? null) ? trim((string) $row['mcc_id']) : null,
            'cep' => $this->formatarCep((string) ($row['client_address_cep'] ?? '')),
            'endereco' => filled($row['client_address'] ?? null) ? trim((string) $row['client_address']) : null,
            'numero' => $this->normalizarNumero($row['client_address_number'] ?? null),
            'complemento' => filled($row['client_address_comp'] ?? null) ? trim((string) $row['client_address_comp']) : null,
            'bairro' => filled($row['client_address_neighborhood'] ?? null) ? trim((string) $row['client_address_neighborhood']) : null,
            'cidade' => filled($row['client_address_city'] ?? null) ? trim((string) $row['client_address_city']) : null,
            'uf' => filled($row['client_address_state'] ?? null) ? strtoupper(substr(trim((string) $row['client_address_state']), 0, 2)) : null,
            'celular' => $this->formatarTelefone((string) ($row['client_phone'] ?? '')),
            'legacy_pagbank_id' => trim((string) ($row['token'] ?? $row['id_estabelecimento'] ?? '')) ?: null,
            'legacy_import_id' => $this->normalizarInteiro($row['id'] ?? null),
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

    protected function buscarDuplicataPorTipo(
        array $row,
        string $tipo,
        string $token,
        ?int $legacyId,
        string $fantasia,
        array $cachePorNome,
    ): ?Usuario {
        if ($token !== '') {
            $porToken = Usuario::query()
                ->where('tipo', $tipo)
                ->where('legacy_pagbank_id', $token)
                ->first();

            if ($porToken) {
                return $porToken;
            }
        }

        if ($legacyId) {
            $porLegacy = Usuario::query()
                ->where('tipo', $tipo)
                ->where('legacy_import_id', $legacyId)
                ->first();

            if ($porLegacy) {
                return $porLegacy;
            }
        }

        $documento = DocumentoBrasil::apenasDigitos((string) ($row['client_document'] ?? ''));

        if (strlen($documento) === 14) {
            $cnpjFmt = DocumentoBrasil::formatarCnpj($documento);
            $porDoc = Usuario::query()
                ->where('tipo', $tipo)
                ->where(function ($q) use ($documento, $cnpjFmt) {
                    $q->whereRaw("REPLACE(REPLACE(REPLACE(cnpj, '.', ''), '/', ''), '-', '') = ?", [$documento])
                        ->orWhere('cnpj', $cnpjFmt);
                })
                ->first();

            if ($porDoc) {
                return $porDoc;
            }
        }

        if (strlen($documento) === 11) {
            $cpfFmt = DocumentoBrasil::formatarCpf($documento);
            $porDoc = Usuario::query()
                ->where('tipo', $tipo)
                ->where(function ($q) use ($documento, $cpfFmt) {
                    $q->whereRaw("REPLACE(REPLACE(cpf, '.', ''), '-', '') = ?", [$documento])
                        ->orWhere('cpf', $cpfFmt);
                })
                ->first();

            if ($porDoc) {
                return $porDoc;
            }
        }

        foreach ($this->chavesNomeTexto($fantasia) as $chave) {
            if (isset($cachePorNome[$chave])) {
                return $cachePorNome[$chave];
            }
        }

        return null;
    }

    protected function marketplaceDoUsuario(Usuario $usuario): ?Usuario
    {
        if ($usuario->tipo === 'marketplace') {
            return $usuario;
        }

        $usuario->loadMissing('hierarquia.pai.usuario');
        $pai = $usuario->hierarquia?->pai?->usuario;

        while ($pai) {
            if ($pai->tipo === 'marketplace') {
                return $pai;
            }

            $pai->loadMissing('hierarquia.pai.usuario');
            $pai = $pai->hierarquia?->pai?->usuario;
        }

        return null;
    }
}

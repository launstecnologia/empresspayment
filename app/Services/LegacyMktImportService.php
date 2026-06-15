<?php

namespace App\Services;

use App\Models\Hierarquia;
use App\Models\SubUsuario;
use App\Models\Usuario;
use App\Support\DocumentoBrasil;
use App\Support\SimpleXlsxReader;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LegacyMktImportService
{
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
            ->get(['id', 'nome_fantasia', 'razao_social', 'email', 'cnpj', 'cpf', 'legacy_pagbank_id'])
            ->each(function (Usuario $usuario) {
                foreach ($this->chavesNome($usuario) as $chave) {
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

        if ($skipTest && ($this->ehRegistroTeste($fantasia, $email))) {
            return $this->resultadoLinha($fantasia, $token, 'ignorado', 'Registro de teste ignorado.');
        }

        if ($email === '') {
            return $this->resultadoLinha($fantasia, $token, 'erro', 'E-mail ausente.');
        }

        if ($this->emailEmUso($email)) {
            return $this->resultadoLinha($fantasia, $token, 'ignorado', "E-mail já cadastrado: {$email}");
        }

        $existente = $this->buscarDuplicata($row, $token, $legacyId, $fantasia);

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

        $dados = $this->mapearUsuario($row);

        if ($dryRun) {
            return $this->resultadoLinha($fantasia, $token, 'criado', 'Seria criado (dry-run).');
        }

        try {
            $usuario = DB::transaction(function () use ($dados) {
                $usuario = Usuario::create($dados);

                Hierarquia::create([
                    'usuario_id' => $usuario->id,
                    'pai_id' => null,
                    'nivel' => 'marketplace',
                ]);

                return $usuario;
            });

            foreach ($this->chavesNome($usuario) as $chave) {
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
     */
    private function buscarDuplicata(array $row, string $token, ?int $legacyId, string $fantasia): ?Usuario
    {
        if ($token !== '') {
            $porToken = Usuario::query()
                ->where('tipo', 'marketplace')
                ->where('legacy_pagbank_id', $token)
                ->first();

            if ($porToken) {
                return $porToken;
            }
        }

        if ($legacyId) {
            $porLegacy = Usuario::query()
                ->where('tipo', 'marketplace')
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
                ->where('tipo', 'marketplace')
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
                ->where('tipo', 'marketplace')
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
            if (isset($this->cachePorNome[$chave])) {
                return $this->cachePorNome[$chave];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapearUsuario(array $row): array
    {
        $tipoPessoa = strtoupper(trim((string) ($row['tipo_pessoa'] ?? 'PJ')));
        $pessoaTipo = $tipoPessoa === 'PF' ? 'fisica' : 'juridica';
        $documento = DocumentoBrasil::apenasDigitos((string) ($row['client_document'] ?? ''));

        $fantasia = trim((string) ($row['client_statement'] ?? ''));
        $razao = trim((string) ($row['client_name'] ?? $fantasia));

        $dados = [
            'tipo' => 'marketplace',
            'pessoa_tipo' => $pessoaTipo,
            'nome_fantasia' => $fantasia ?: $razao,
            'email' => strtolower(trim((string) $row['client_email'])),
            'password' => Hash::make('123456'),
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

    private function emailEmUso(string $email): bool
    {
        return Usuario::query()->whereRaw('LOWER(email) = ?', [$email])->exists()
            || SubUsuario::query()->whereRaw('LOWER(email) = ?', [$email])->exists();
    }

    private function ehRegistroTeste(string $fantasia, string $email): bool
    {
        return str_contains($email, 'teste')
            || strcasecmp($fantasia, 'SUA MARCA') === 0;
    }

    /**
     * @return list<string>
     */
    private function chavesNome(Usuario $usuario): array
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
    private function chavesNomeTexto(?string $nome): array
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

    private function formatarCep(string $cep): ?string
    {
        $digits = DocumentoBrasil::apenasDigitos($cep);

        if (strlen($digits) !== 8) {
            return $cep !== '' ? $cep : null;
        }

        return substr($digits, 0, 5).'-'.substr($digits, 5);
    }

    private function formatarTelefone(string $telefone): ?string
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

    private function normalizarNumero(mixed $numero): ?string
    {
        if ($numero === null || $numero === '') {
            return null;
        }

        return trim((string) $numero);
    }

    private function normalizarInteiro(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (int) preg_replace('/\D/', '', (string) $valor);
    }

    private function parseData(string $valor): ?string
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

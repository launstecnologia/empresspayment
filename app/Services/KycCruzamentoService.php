<?php

namespace App\Services;

use App\Models\KycDocumento;
use App\Support\KycDivergenciaHelper;

class KycCruzamentoService
{
    public function cruzar(KycDocumento $documento): void
    {
        $estab = $documento->estabelecimento;
        $dados = $documento->openai_dados_extraidos ?? [];
        $divergencias = [];

        switch ($documento->tipo) {
            case 'rg_frente':
            case 'cnh_frente':
                if (isset($dados['cpf'])) {
                    $cpfDoc = preg_replace('/\D/', '', (string) $dados['cpf']);
                    $cpfCadastro = preg_replace('/\D/', '', (string) ($estab->cpf ?? $estab->rep_cpf ?? ''));
                    if ($cpfCadastro && $cpfDoc !== $cpfCadastro) {
                        $divergencias['cpf'] = [
                            'documento' => $dados['cpf'],
                            'cadastro' => $estab->cpf ?? $estab->rep_cpf,
                        ];
                    }
                }

                if (isset($dados['nome'])) {
                    $nomeDoc = strtolower(trim((string) $dados['nome']));
                    $nomeCadastro = strtolower(trim((string) ($estab->nome_completo ?? $estab->rep_nome ?? '')));
                    similar_text($nomeDoc, $nomeCadastro, $similaridade);
                    if ($nomeCadastro && $similaridade < 80) {
                        $divergencias['nome'] = [
                            'documento' => $dados['nome'],
                            'cadastro' => $estab->nome_completo ?? $estab->rep_nome,
                            'similaridade' => round($similaridade, 1),
                        ];
                    }
                }

                if (isset($dados['data_nascimento'])) {
                    $nascCadastro = $estab->data_nascimento ?? $estab->rep_data_nascimento;
                    if ($nascCadastro && $dados['data_nascimento'] !== $nascCadastro->format('Y-m-d')) {
                        $divergencias['data_nascimento'] = [
                            'documento' => $dados['data_nascimento'],
                            'cadastro' => $nascCadastro->format('Y-m-d'),
                        ];
                    }
                }

                if (! empty($dados['data_expiracao'])) {
                    $exp = strtotime((string) $dados['data_expiracao']);
                    if ($exp && $exp < time()) {
                        $divergencias['documento_expirado'] = ['expiracao' => $dados['data_expiracao']];
                    }
                }
                break;

            case 'comprovante_endereco':
                if (isset($dados['dentro_prazo']) && ! $dados['dentro_prazo']) {
                    $divergencias['comprovante_vencido'] = [
                        'data_documento' => $dados['data_documento'] ?? null,
                        'limite' => '90 dias',
                    ];
                }

                if (isset($dados['uf'])) {
                    $ufDoc = strtoupper(trim((string) $dados['uf']));
                    $ufCad = strtoupper(trim((string) ($estab->uf ?? '')));
                    if ($ufCad && $ufDoc !== $ufCad) {
                        $divergencias['uf'] = ['documento' => $ufDoc, 'cadastro' => $ufCad];
                    }
                }

                if (isset($dados['cidade'])) {
                    $cidadeDoc = mb_strtolower(trim((string) $dados['cidade']), 'UTF-8');
                    $cidadeCad = mb_strtolower(trim((string) ($estab->cidade ?? '')), 'UTF-8');
                    if ($cidadeCad !== '') {
                        similar_text($cidadeDoc, $cidadeCad, $similaridade);
                        if ($similaridade < 75) {
                            $divergencias['cidade'] = [
                                'documento' => $dados['cidade'],
                                'cadastro' => $estab->cidade,
                                'similaridade' => round($similaridade, 1),
                            ];
                        }
                    }
                }
                break;

            case 'cartao_cnpj':
            case 'contrato_social':
                if (isset($dados['cnpj'])) {
                    $cnpjDoc = preg_replace('/\D/', '', (string) $dados['cnpj']);
                    $cnpjCadastro = preg_replace('/\D/', '', (string) ($estab->cnpj ?? ''));
                    if ($cnpjCadastro && $cnpjDoc !== $cnpjCadastro) {
                        $divergencias['cnpj'] = [
                            'documento' => $dados['cnpj'],
                            'cadastro' => $estab->cnpj,
                        ];
                    }
                }

                if (isset($dados['razao_social'])) {
                    $nomeDoc = strtolower(trim((string) $dados['razao_social']));
                    $nomeCad = strtolower(trim((string) ($estab->razao_social ?? '')));
                    similar_text($nomeDoc, $nomeCad, $similaridade);
                    if ($nomeCad && $similaridade < 75) {
                        $divergencias['razao_social'] = [
                            'documento' => $dados['razao_social'],
                            'cadastro' => $estab->razao_social,
                            'similaridade' => round($similaridade, 1),
                        ];
                    }
                }
                break;
        }

        $status = empty($divergencias) ? 'ok' : 'divergencia';
        $updates = [
            'cruzamento_status' => $status,
            'cruzamento_divergencias' => $divergencias ?: null,
        ];

        if (! empty($divergencias) && $documento->openai_status === 'aprovado') {
            $updates['openai_status'] = 'revisao_manual';
            $updates['openai_motivo_reprovacao'] = KycDivergenciaHelper::mensagemReenvio($divergencias);
        }

        $documento->update($updates);
    }
}

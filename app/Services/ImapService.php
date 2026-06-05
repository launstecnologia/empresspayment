<?php

namespace App\Services;

use App\Models\EmailCaixaEntrada;
use App\Models\EstabelecimentoEmail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImapService
{
    public function disponivel(): bool
    {
        return function_exists('imap_open');
    }

    public function sincronizar(EstabelecimentoEmail $conta, int $limite = 50): void
    {
        if (! $this->disponivel()) {
            $conta->update([
                'ultimo_erro_sync' => 'Extensão PHP IMAP não instalada no servidor.',
            ]);

            return;
        }

        if (blank($conta->senha())) {
            $conta->update(['ultimo_erro_sync' => 'Senha da conta não configurada.']);

            return;
        }

        $mailbox = $this->caixaEntrada($conta);
        $conexao = @imap_open($mailbox, $conta->email_completo, $conta->senha(), 0, 1);

        if ($conexao === false) {
            $erro = imap_last_error() ?: 'Falha ao conectar no IMAP.';
            $conta->update(['ultimo_erro_sync' => $erro]);

            return;
        }

        try {
            $uids = imap_search($conexao, 'ALL') ?: [];
            rsort($uids);
            $uids = array_slice($uids, 0, $limite);

            foreach ($uids as $uid) {
                $this->importarMensagem($conexao, $conta, (int) $uid);
            }

            $conta->update([
                'ultimo_sync' => now(),
                'ultimo_erro_sync' => null,
            ]);
        } finally {
            imap_close($conexao);
        }
    }

    private function importarMensagem($conexao, EstabelecimentoEmail $conta, int $uid): void
    {
        $overview = imap_fetch_overview($conexao, (string) $uid, FT_UID);
        $item = $overview[0] ?? null;

        if (! $item) {
            return;
        }

        $estrutura = imap_fetchstructure($conexao, $uid, FT_UID);
        [$texto, $html] = $this->extrairCorpo($conexao, $uid, $estrutura);
        $de = $this->parseEndereco($item->from ?? '');
        $data = isset($item->date) ? Carbon::parse($item->date) : null;

        EmailCaixaEntrada::updateOrCreate(
            [
                'estabelecimento_email_id' => $conta->id,
                'uid' => (string) $uid,
                'pasta' => 'INBOX',
            ],
            [
                'message_id' => $item->message_id ?? null,
                'de_nome' => $de['nome'],
                'de_email' => $de['email'],
                'para' => $item->to ?? null,
                'cc' => $item->cc ?? null,
                'assunto' => isset($item->subject) ? $this->decodificar($item->subject) : null,
                'corpo_texto' => $texto,
                'corpo_html' => $html,
                'tem_anexo' => $this->temAnexo($estrutura),
                'tamanho_bytes' => (int) ($item->size ?? 0),
                'lido' => ! empty($item->seen),
                'data_email' => $data,
            ],
        );
    }

    private function extrairCorpo($conexao, int $uid, $estrutura): array
    {
        $texto = null;
        $html = null;

        if (! $estrutura) {
            $raw = imap_body($conexao, $uid, FT_UID);

            return [$raw, null];
        }

        if ($estrutura->type === TYPETEXT) {
            $corpo = imap_fetchbody($conexao, $uid, '1', FT_UID);
            $corpo = $this->decodificarParte($corpo, $estrutura->encoding);

            if ($estrutura->subtype === 'HTML') {
                $html = $corpo;
            } else {
                $texto = $corpo;
            }

            return [$texto, $html];
        }

        if (! empty($estrutura->parts)) {
            foreach ($estrutura->parts as $indice => $parte) {
                $secao = (string) ($indice + 1);
                $corpo = imap_fetchbody($conexao, $uid, $secao, FT_UID);
                $corpo = $this->decodificarParte($corpo, $parte->encoding ?? 0);

                if (($parte->subtype ?? '') === 'HTML' && $html === null) {
                    $html = $corpo;
                } elseif (($parte->subtype ?? '') === 'PLAIN' && $texto === null) {
                    $texto = $corpo;
                }
            }
        }

        return [$texto, $html];
    }

    private function decodificarParte(?string $corpo, int $encoding): ?string
    {
        if ($corpo === null) {
            return null;
        }

        return match ($encoding) {
            ENCBASE64 => base64_decode($corpo) ?: $corpo,
            ENCQUOTEDPRINTABLE => quoted_printable_decode($corpo),
            default => $corpo,
        };
    }

    private function temAnexo($estrutura): bool
    {
        if (! $estrutura) {
            return false;
        }

        if (! empty($estrutura->parts)) {
            foreach ($estrutura->parts as $parte) {
                if (($parte->ifdisposition ?? false) && strtoupper((string) ($parte->disposition ?? '')) === 'ATTACHMENT') {
                    return true;
                }
            }
        }

        return false;
    }

    private function parseEndereco(string $from): array
    {
        if (preg_match('/^(.*)<([^>]+)>$/', trim($from), $m)) {
            return ['nome' => trim($m[1], ' "'), 'email' => trim($m[2])];
        }

        return ['nome' => null, 'email' => trim($from)];
    }

    private function decodificar(string $valor): string
    {
        $decodificado = imap_utf8($valor);

        return $decodificado !== false ? $decodificado : $valor;
    }

    private function caixaEntrada(EstabelecimentoEmail $conta): string
    {
        $host = $conta->imap_host ?: config('directadmin.imap_host') ?: 'mail.'.config('directadmin.dominio');
        $porta = $conta->imap_porta ?: 993;
        $flag = $conta->imap_ssl ? '/imap/ssl' : '/imap/tls';

        return '{'.$host.':'.$porta.$flag.'}INBOX';
    }

    public function salvarAnexo(string $conteudo, string $nome): string
    {
        $arquivo = 'email-anexos/'.uniqid('', true).'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $nome);
        Storage::disk('local')->put($arquivo, $conteudo);

        return $arquivo;
    }
}

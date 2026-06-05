<?php

namespace App\Services;

use App\Models\EmailCaixaEntrada;
use App\Models\EmailEnviado;
use App\Models\EstabelecimentoEmail;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

class EmailSmtpService
{
    public function enviar(
        EstabelecimentoEmail $conta,
        string $para,
        string $assunto,
        string $corpoHtml,
        ?string $cc = null,
        ?string $cco = null,
        ?EmailCaixaEntrada $respostaA = null,
    ): EmailEnviado {
        $registro = EmailEnviado::create([
            'estabelecimento_email_id' => $conta->id,
            'para' => $para,
            'cc' => $cc,
            'cco' => $cco,
            'assunto' => $assunto,
            'corpo_html' => $corpoHtml,
            'resposta_ao_id' => $respostaA?->id,
            'status' => 'enviado',
        ]);

        try {
            $mailer = new Mailer(Transport::fromDsn($this->dsn($conta)));

            $mensagem = (new Email)
                ->from(new Address($conta->email_completo, $conta->estabelecimento?->nome_fantasia ?: 'Estabelecimento'))
                ->to(...$this->enderecos($para))
                ->subject($assunto)
                ->html($corpoHtml);

            if ($cc) {
                $mensagem->cc(...$this->enderecos($cc));
            }

            if ($cco) {
                $mensagem->bcc(...$this->enderecos($cco));
            }

            if ($respostaA?->message_id) {
                $mensagem->getHeaders()->addTextHeader('In-Reply-To', $respostaA->message_id);
            }

            $mailer->send($mensagem);

            if ($respostaA) {
                $respostaA->update(['respondido' => true]);
            }
        } catch (Throwable $e) {
            $registro->update([
                'status' => 'falha',
                'erro' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $registro;
    }

    private function dsn(EstabelecimentoEmail $conta): string
    {
        $host = $conta->smtp_host ?: config('directadmin.smtp_host') ?: 'mail.'.config('directadmin.dominio');
        $porta = $conta->smtp_porta ?: 587;
        $usuario = urlencode($conta->email_completo);
        $senha = urlencode((string) $conta->senha());
        $esquema = $conta->smtp_ssl && $porta === 465 ? 'smtps' : 'smtp';
        $params = $conta->smtp_ssl ? '?encryption=tls' : '';

        return "{$esquema}://{$usuario}:{$senha}@{$host}:{$porta}{$params}";
    }

    /** @return Address[] */
    private function enderecos(string $lista): array
    {
        return collect(preg_split('/[;,]+/', $lista) ?: [])
            ->map(fn ($e) => trim((string) $e))
            ->filter()
            ->map(fn ($e) => new Address($e))
            ->values()
            ->all();
    }
}

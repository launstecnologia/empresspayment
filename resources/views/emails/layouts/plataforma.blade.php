<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $appName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f3f4f6;padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(15,23,42,0.08);">
                <tr>
                    <td style="background:linear-gradient(135deg,{{ $themeColor }} 0%,#1e40af 100%);padding:28px 32px;text-align:center;">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $appName }}" style="max-height:56px;max-width:220px;height:auto;display:inline-block;">
                        @else
                            <span style="color:#ffffff;font-size:22px;font-weight:700;letter-spacing:-0.02em;">{{ $appName }}</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
                        @foreach (preg_split("/\r\n|\n|\r/", trim($corpoTexto)) as $linha)
                            @if (trim($linha) !== '')
                                <p style="margin:0 0 14px;font-size:15px;line-height:1.65;color:#374151;">{{ $linha }}</p>
                            @endif
                        @endforeach

                        @if ($botaoTexto && $botaoUrl)
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:28px 0 8px;">
                                <tr>
                                    <td style="border-radius:8px;background:{{ $themeColor }};">
                                        <a href="{{ $botaoUrl }}" target="_blank" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px;">
                                            {{ $botaoTexto }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:16px 0 0;font-size:12px;line-height:1.5;color:#9ca3af;word-break:break-all;">
                                Se o botão não funcionar, copie e cole este link no navegador:<br>
                                <a href="{{ $botaoUrl }}" style="color:{{ $themeColor }};">{{ $botaoUrl }}</a>
                            </p>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 32px;background:#f9fafb;border-top:1px solid #e5e7eb;text-align:center;">
                        <p style="margin:0;font-size:12px;color:#9ca3af;line-height:1.5;">
                            {{ $appName }} · {{ date('Y') }}<br>
                            Este é um e-mail automático. Não responda diretamente.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

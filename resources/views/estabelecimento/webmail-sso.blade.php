<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abrindo Webmail…</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            padding: 2rem 2.5rem;
            box-shadow: 0 4px 24px rgba(0,0,0,.08);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #e2e8f0;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin .7s linear infinite;
            margin: 0 auto 1rem;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        p { color: #64748b; font-size: .9rem; margin: 0 0 .5rem; }
        strong { color: #1e293b; }
        .manual { margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e2e8f0; display: none; }
        .manual p { font-size: .85rem; margin-bottom: .75rem; }
        .cred { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: .5rem .75rem; font-family: monospace; font-size: .85rem; color: #334155; margin-bottom: .5rem; text-align: left; word-break: break-all; }
        .btn { display: inline-block; background: #3b82f6; color: #fff; border: none; border-radius: 8px; padding: .6rem 1.25rem; font-size: .85rem; font-weight: 600; cursor: pointer; text-decoration: none; margin-top: .5rem; }
    </style>
</head>
<body>
    <div class="card">
        <div class="spinner" id="spinner"></div>
        <p>Entrando em <strong>{{ $email }}</strong>…</p>

        {{-- Fallback manual caso o auto-login falhe --}}
        <div class="manual" id="manual">
            <p>O login automático não foi possível. Use as credenciais abaixo para entrar manualmente:</p>
            <div class="cred">{{ $email }}</div>
            <div class="cred" id="senhaBox" title="Clique para revelar" style="cursor:pointer;filter:blur(4px)" onclick="this.style.filter='none'">{{ $senha }}</div>
            <a class="btn" href="{{ $webmailUrl }}/" target="_blank">Abrir Webmail</a>
        </div>
    </div>

    <form id="roundcube-login" method="POST" action="{{ $webmailUrl }}/" style="display:none">
        <input type="hidden" name="_task"     value="login">
        <input type="hidden" name="_action"   value="login">
        <input type="hidden" name="_timezone" value="America/Sao_Paulo">
        <input type="hidden" name="_url"      value="">
        <input type="hidden" name="_user"     value="{{ $email }}">
        <input type="hidden" name="_pass"     value="{{ $senha }}">
        @if ($rcToken)
        <input type="hidden" name="_token"    value="{{ $rcToken }}">
        @endif
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            @if ($rcToken)
                // Tem token CSRF — submete o formulário
                document.getElementById('roundcube-login').submit();
            @else
                // Sem token — mostra opção manual após 1s
                setTimeout(function () {
                    document.getElementById('spinner').style.display = 'none';
                    document.getElementById('manual').style.display = 'block';
                }, 1000);
            @endif
        });
    </script>
</body>
</html>

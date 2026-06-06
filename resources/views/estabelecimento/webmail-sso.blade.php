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
            max-width: 360px;
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
        p { color: #64748b; font-size: .9rem; margin: 0; }
        strong { color: #1e293b; }
    </style>
</head>
<body>
    <div class="card">
        <div class="spinner"></div>
        <p>Entrando em <strong>{{ $email }}</strong>…</p>
    </div>

    <form id="roundcube-login" method="POST" action="{{ $webmailUrl }}/" style="display:none">
        <input type="hidden" name="_task"     value="login">
        <input type="hidden" name="_action"   value="login">
        <input type="hidden" name="_timezone" value="America/Sao_Paulo">
        <input type="hidden" name="_url"      value="">
        <input type="hidden" name="_user"     value="{{ $email }}">
        <input type="hidden" name="_pass"     value="{{ $senha }}">
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('roundcube-login').submit();
        });
    </script>
</body>
</html>

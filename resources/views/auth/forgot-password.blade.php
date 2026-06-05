<!doctype html>
<html lang="pt-BR">
<head>
    @include('partials.head-meta', ['pageTitle' => 'Esqueci minha senha'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    @php $loginPrimary = $primaryColor ?? '#2563eb'; @endphp
    <script>tailwind.config={theme:{extend:{colors:{express:{600:'{{ $loginPrimary }}',700:'{{ $loginPrimary }}'}}}}};</script>
</head>
<body class="flex min-h-screen items-center justify-center bg-gray-50 px-6 py-12">
    <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
        <a href="{{ $tenantSlug ? route('login', [$tenantParam => $tenantSlug]) : route('login') }}" class="text-sm text-gray-500 hover:text-express-600">
            <i class="fa-solid fa-arrow-left mr-1"></i> Voltar ao login
        </a>
        <h1 class="mt-4 text-2xl font-bold text-gray-900">Esqueci minha senha</h1>
        <p class="mt-2 text-sm text-gray-500">Informe seu e-mail cadastrado. Enviaremos um link para criar uma nova senha.</p>

        @if (session('status'))
            <div class="mt-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif

        @if (session('reset_link_dev'))
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">Ambiente local — link de redefinição</p>
                <p class="mt-1 text-amber-800">O e-mail foi gravado no log. Use o link abaixo para testar:</p>
                <a href="{{ session('reset_link_dev') }}" class="mt-2 block break-all font-medium text-blue-600 underline">{{ session('reset_link_dev') }}</a>
            </div>
        @endif
        @if ($errors->any())
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <form method="POST" action="{{ $tenantSlug ? route('password.email', [$tenantParam => $tenantSlug]) : route('password.email') }}" class="mt-8 space-y-5">
            @csrf
            @if ($tenantSlug)
                <input type="hidden" name="{{ $tenantParam }}" value="{{ $tenantSlug }}">
            @endif
            <label class="block space-y-1">
                <span class="text-sm font-medium text-gray-700">E-mail</span>
                <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" class="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm focus:border-express-600 focus:ring-2 focus:ring-express-600/20">
            </label>
            <button type="submit" class="w-full rounded-xl bg-express-600 py-3 text-sm font-semibold text-white hover:bg-express-700">
                Enviar link de redefinição
            </button>
        </form>
    </div>
</body>
</html>

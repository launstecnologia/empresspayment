<!doctype html>
<html lang="pt-BR">
<head>
    @include('partials.head-meta', ['pageTitle' => 'Nova senha'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    @php $loginPrimary = $primaryColor ?? '#2563eb'; @endphp
    <script>tailwind.config={theme:{extend:{colors:{express:{600:'{{ $loginPrimary }}',700:'{{ $loginPrimary }}'}}}}};</script>
</head>
<body class="flex min-h-screen items-center justify-center bg-gray-50 px-6 py-12">
    <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
        <h1 class="text-2xl font-bold text-gray-900">Definir nova senha</h1>
        <p class="mt-2 text-sm text-gray-500">Crie uma senha com no mínimo 8 caracteres.</p>

        @if ($errors->any())
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            @if ($tenantSlug)
                <input type="hidden" name="{{ $tenantParam }}" value="{{ $tenantSlug }}">
            @endif
            <label class="block space-y-1">
                <span class="text-sm font-medium text-gray-700">E-mail</span>
                <input type="email" name="email" value="{{ old('email', $email) }}" required readonly class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
            </label>
            <label class="block space-y-1">
                <span class="text-sm font-medium text-gray-700">Nova senha</span>
                <input type="password" name="password" required autocomplete="new-password" class="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm focus:border-express-600 focus:ring-2 focus:ring-express-600/20">
            </label>
            <label class="block space-y-1">
                <span class="text-sm font-medium text-gray-700">Confirmar senha</span>
                <input type="password" name="password_confirmation" required autocomplete="new-password" class="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm focus:border-express-600 focus:ring-2 focus:ring-express-600/20">
            </label>
            <button type="submit" class="w-full rounded-xl bg-express-600 py-3 text-sm font-semibold text-white hover:bg-express-700">
                Salvar nova senha
            </button>
        </form>
    </div>
</body>
</html>

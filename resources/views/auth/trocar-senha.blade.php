<!doctype html>
<html lang="pt-BR">
<head>
    @include('partials.head-meta', ['pageTitle' => 'Criar nova senha'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center px-4">

<div class="w-full max-w-md">

    <div class="mb-8 text-center">
        <div class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-blue-100 mb-4">
            <i class="fa-solid fa-lock text-blue-600 text-xl"></i>
        </div>
        <h1 class="text-2xl font-bold text-gray-900">Crie sua senha</h1>
        <p class="mt-2 text-sm text-gray-500">
            Este é seu primeiro acesso. Defina uma senha pessoal para continuar.
        </p>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">

        @if ($errors->any())
            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('senha.trocar.salvar') }}" class="space-y-5">
            @csrf

            <div class="space-y-1.5">
                <label for="password" class="block text-sm font-semibold text-gray-700">Nova senha</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autofocus
                    minlength="8"
                    placeholder="Mínimo 8 caracteres"
                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
            </div>

            <div class="space-y-1.5">
                <label for="password_confirmation" class="block text-sm font-semibold text-gray-700">Confirmar senha</label>
                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    required
                    minlength="8"
                    placeholder="Repita a nova senha"
                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
            </div>

            <button
                type="submit"
                class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            >
                Salvar e entrar
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
            @csrf
            <button type="submit" class="text-xs text-gray-400 hover:text-gray-600">
                Sair e entrar com outra conta
            </button>
        </form>

    </div>

</div>

</body>
</html>

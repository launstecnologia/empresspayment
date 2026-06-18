<!doctype html>
<html lang="pt-BR">
<head>
    @include('partials.head-meta', [
        'pageTitle' => 'Entrar',
        'description' => 'Acesse a plataforma Express Payments para gerenciar estabelecimentos, faturamento, comissões e operações.',
    ])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    @php
        $loginPrimary = $primaryColor ?? '#2563eb';
    @endphp
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        express: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '{{ $loginPrimary }}',
                            700: '{{ $loginPrimary }}',
                            800: '#1e40af',
                        },
                    },
                },
            },
        };
    </script>
    <style>
        :root { --login-primary: {{ $loginPrimary }}; }
        .login-input {
            padding-top: 1.35rem;
            padding-bottom: 0.55rem;
        }
        .login-input:focus ~ .login-label,
        .login-input:not(:placeholder-shown) ~ .login-label {
            top: 0.45rem;
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--login-primary);
        }
        .login-panel-gradient {
            background: linear-gradient(to bottom right, var(--login-primary), color-mix(in srgb, var(--login-primary) 85%, #000), color-mix(in srgb, var(--login-primary) 70%, #000));
        }
    </style>
</head>
<body class="min-h-screen bg-white antialiased">
    <div class="flex min-h-screen flex-col lg:flex-row">
        {{-- Painel esquerdo: ilustração e marca --}}
        <div class="login-panel-gradient relative flex min-h-[280px] flex-1 flex-col items-center justify-between overflow-hidden px-8 py-10 text-white lg:min-h-screen lg:max-w-[50%] lg:px-12 lg:py-14">
            <div class="absolute -left-16 -top-16 h-56 w-56 rounded-full bg-white/10"></div>
            <div class="absolute -bottom-20 -right-10 h-72 w-72 rounded-full bg-white/5"></div>
            <div class="absolute right-12 top-24 h-3 w-3 rounded-full bg-amber-400/80"></div>
            <div class="absolute left-20 top-32 h-2 w-2 rounded-full bg-emerald-400/80"></div>
            <div class="absolute bottom-40 left-10 h-2 w-2 rounded-full bg-rose-400/80"></div>

            <div class="relative z-10 flex w-full max-w-xl flex-col items-center pt-2 text-center">
                <img
                    src="{{ $logoWhiteUrl ?? $logoUrl }}"
                    alt="{{ $appName }}"
                    class="mx-auto h-20 w-full max-w-sm object-contain object-center brightness-0 invert sm:h-24 lg:h-32 lg:max-w-md"
                    onerror="this.style.display='none'; this.nextElementSibling.style.display='block'"
                >
                <p class="hidden text-2xl font-bold tracking-tight" style="display:none">Express Payments</p>
            </div>

            <div class="relative z-10 my-6 flex w-full max-w-lg flex-1 items-center justify-center">
                <svg class="w-full max-w-md drop-shadow-lg" viewBox="0 0 480 360" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    {{-- Painel de faturamento --}}
                    <rect x="130" y="48" width="220" height="264" rx="20" fill="white" fill-opacity="0.96"/>
                    <rect x="154" y="72" width="72" height="10" rx="5" fill="#dbeafe"/>
                    <rect x="154" y="92" width="120" height="8" rx="4" fill="#eff6ff"/>
                    <text x="154" y="128" fill="#1e40af" font-size="13" font-weight="700" font-family="system-ui, sans-serif">Faturamento</text>
                    <text x="154" y="152" fill="#64748b" font-size="11" font-family="system-ui, sans-serif">EDI · Últimos 30 dias</text>
                    {{-- Barras do gráfico --}}
                    <rect x="154" y="228" width="28" height="56" rx="6" fill="#fcd34d"/>
                    <rect x="192" y="200" width="28" height="84" rx="6" fill="#34d399"/>
                    <rect x="230" y="168" width="28" height="116" rx="6" fill="#60a5fa"/>
                    <rect x="268" y="148" width="28" height="136" rx="6" fill="#2563eb"/>
                    <path d="M168 196 L212 172 L256 188 L296 158" stroke="#93c5fd" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="168" cy="196" r="4" fill="#2563eb"/>
                    <circle cx="212" cy="172" r="4" fill="#2563eb"/>
                    <circle cx="256" cy="188" r="4" fill="#2563eb"/>
                    <circle cx="296" cy="158" r="4" fill="#2563eb"/>
                    {{-- Nota fiscal / fatura --}}
                    <rect x="56" y="108" width="88" height="112" rx="12" fill="#fef3c7" stroke="#fbbf24" stroke-width="2"/>
                    <rect x="68" y="124" width="48" height="6" rx="3" fill="#fbbf24"/>
                    <rect x="68" y="138" width="64" height="4" rx="2" fill="#fde68a"/>
                    <rect x="68" y="148" width="56" height="4" rx="2" fill="#fde68a"/>
                    <rect x="68" y="158" width="60" height="4" rx="2" fill="#fde68a"/>
                    <text x="68" y="198" fill="#b45309" font-size="14" font-weight="700" font-family="system-ui, sans-serif">R$</text>
                    {{-- Total em destaque --}}
                    <rect x="300" y="88" width="96" height="52" rx="12" fill="#2563eb"/>
                    <text x="314" y="112" fill="#bfdbfe" font-size="9" font-weight="600" font-family="system-ui, sans-serif">TOTAL</text>
                    <text x="314" y="132" fill="white" font-size="15" font-weight="700" font-family="system-ui, sans-serif">2.000,40</text>
                    {{-- Ícone relatório --}}
                    <rect x="72" y="248" width="64" height="48" rx="10" fill="white" fill-opacity="0.2" stroke="white" stroke-opacity="0.35"/>
                    <path d="M88 278 L100 262 L112 272 L124 256" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <rect x="330" y="248" width="72" height="48" rx="10" fill="#1e3a8a" fill-opacity="0.45"/>
                    <circle cx="366" cy="268" r="14" fill="#fcd34d"/>
                    <text x="360" y="273" fill="#1e3a8a" font-size="14" font-weight="800" font-family="system-ui, sans-serif">%</text>
                    {{-- Linhas EDI --}}
                    <rect x="154" y="168" width="172" height="28" rx="8" fill="#eff6ff"/>
                    <rect x="166" y="178" width="48" height="6" rx="3" fill="#93c5fd"/>
                    <rect x="222" y="178" width="36" height="6" rx="3" fill="#60a5fa"/>
                    <rect x="266" y="178" width="48" height="6" rx="3" fill="#2563eb"/>
                </svg>
            </div>

            <div class="relative z-10 w-full max-w-lg text-center lg:text-left">
                <p class="text-lg font-semibold leading-snug text-white/95 lg:text-xl">
                    Gestão de faturamento inteligente e em tempo real.
                </p>
                <p class="mt-2 text-sm text-blue-100/90">
                    Acompanhe transações EDI, comissões e relatórios financeiros em um só painel.
                </p>
            </div>
        </div>

        {{-- Painel direito: formulário --}}
        <div class="flex flex-1 items-center justify-center px-6 py-10 lg:max-w-[50%] lg:px-16 lg:py-12">
            <div class="w-full max-w-md">
                <div class="mb-8 lg:hidden">
                    <img src="{{ $logoUrl }}" alt="{{ $appName }}" class="mx-auto h-16 w-auto max-w-xs object-contain sm:h-20">
                </div>

                <h1 class="text-center text-2xl font-bold text-gray-900 lg:text-left lg:text-3xl">Faça seu login</h1>
                <p class="mt-2 text-center text-sm text-gray-500 lg:text-left">
                    Acesse sua conta na plataforma {{ $appName }}.
                </p>

                @php
                    use App\Support\TenantBranding;

                    $tenantParam = $tenantParam ?? config('tenant.local_query', 'tenant');
                    $tenantSlug = $tenantSlug ?? TenantBranding::porSlugAtivo(old($tenantParam, request()->query($tenantParam)))?->slug;
                @endphp

                @if (session('status'))
                    <div class="mt-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                @if ($sessaoAtiva ?? false)
                    <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        <p class="font-semibold">Você já está logado</p>
                        <p class="mt-1">Conta atual: <strong>{{ $usuarioLogado->email ?? $usuarioLogado->nome ?? 'usuário' }}</strong>. Saia para entrar com outro e-mail (ex.: usuário operacional do marketplace).</p>
                        <form method="POST" action="{{ route('logout') }}" class="mt-3">
                            @csrf
                            @if ($tenantSlug)
                                <input type="hidden" name="{{ $tenantParam }}" value="{{ $tenantSlug }}">
                            @endif
                            <button type="submit" class="rounded-lg bg-amber-800 px-4 py-2 text-xs font-semibold text-white hover:bg-amber-900">
                                Sair e trocar de conta
                            </button>
                        </form>
                    </div>
                @endif

                <form method="POST" action="{{ $tenantSlug ? route('login.store', [$tenantParam => $tenantSlug]) : route('login.store') }}" class="mt-8 space-y-5">
                    @csrf
                    @if ($tenantSlug)
                        <input type="hidden" name="{{ $tenantParam }}" value="{{ $tenantSlug }}">
                    @endif

                    <div class="relative">
                        <span class="login-field-icon pointer-events-none absolute left-4 top-4 z-10 flex h-5 w-5 items-center justify-center text-gray-400">
                            <i class="fa-regular fa-envelope text-[15px] leading-none"></i>
                        </span>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            placeholder=" "
                            required
                            autocomplete="email"
                            class="login-input peer w-full rounded-xl border border-gray-300 bg-white pl-11 pr-4 text-sm text-gray-800 outline-none transition focus:border-express-600 focus:ring-2 focus:ring-express-600/20"
                        >
                        <label for="email" class="login-label pointer-events-none absolute left-11 top-4 text-sm text-gray-500 transition-all duration-200">
                            E-mail
                        </label>
                    </div>

                    <div class="relative">
                        <span class="login-field-icon pointer-events-none absolute left-4 top-4 z-10 flex h-5 w-5 items-center justify-center text-gray-400">
                            <i class="fa-solid fa-lock text-[15px] leading-none"></i>
                        </span>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            placeholder=" "
                            required
                            autocomplete="current-password"
                            class="login-input peer w-full rounded-xl border border-gray-300 bg-white pl-11 pr-11 text-sm text-gray-800 outline-none transition focus:border-express-600 focus:ring-2 focus:ring-express-600/20"
                        >
                        <label for="password" class="login-label pointer-events-none absolute left-11 top-4 text-sm text-gray-500 transition-all duration-200">
                            Senha
                        </label>
                        <button
                            type="button"
                            id="toggle-password"
                            class="absolute right-3 top-1/2 z-10 -translate-y-1/2 text-gray-400 transition hover:text-express-600"
                            aria-label="Mostrar senha"
                        >
                            <i class="fa-regular fa-eye" id="toggle-password-icon"></i>
                        </button>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                        <label class="flex cursor-pointer items-center gap-2 text-gray-600">
                            <input name="remember" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-express-600 focus:ring-express-600">
                            Lembrar de mim
                        </label>
                        <a href="{{ $tenantSlug ? route('password.request', [$tenantParam => $tenantSlug]) : route('password.request') }}" class="font-medium text-express-600 transition hover:text-express-700 hover:underline">
                            Esqueci minha senha
                        </a>
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-xl bg-express-600 py-3 text-sm font-semibold text-white shadow-md shadow-express-600/25 transition hover:bg-express-700 focus:outline-none focus:ring-2 focus:ring-express-600 focus:ring-offset-2"
                    >
                        Entrar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('toggle-password')?.addEventListener('click', () => {
            const input = document.getElementById('password');
            const icon = document.getElementById('toggle-password-icon');
            if (!input || !icon) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            icon.classList.toggle('fa-eye', !show);
            icon.classList.toggle('fa-eye-slash', show);
        });
    </script>
</body>
</html>

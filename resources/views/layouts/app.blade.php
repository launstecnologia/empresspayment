<!doctype html>
<html lang="pt-BR" id="html-root">
<head>
    @include('partials.head-meta')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script>
        (function () {
            const key = 'express-theme';
            const saved = localStorage.getItem(key);
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (saved === 'dark' || (!saved && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script>
        tailwind.config = { darkMode: 'class' };
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }

        :root {
            color-scheme: light;
            --color-primary: {{ $primaryColor ?? '#2563eb' }};
            --page-bg: #f9fafb;
            --surface-bg: #ffffff;
            --surface-muted: #f3f4f6;
            --border-color: #e5e7eb;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
        }

        html.dark {
            color-scheme: dark;
            --page-bg: #030712;
            --surface-bg: #111827;
            --surface-muted: #1f2937;
            --border-color: #374151;
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
        }

        html,
        body {
            height: 100%;
            overflow: hidden;
        }

        body {
            background-color: var(--page-bg) !important;
            color: var(--text-primary) !important;
        }

        html.dark .bg-white {
            background-color: var(--surface-bg) !important;
        }

        html.dark .bg-gray-50.dark\:bg-gray-950,
        html.dark main.bg-gray-50 {
            background-color: var(--page-bg) !important;
        }

        html.dark .dashboard-apuracao {
            background: var(--surface-bg) !important;
            background-image: none !important;
            border-color: var(--border-color) !important;
        }

        html.dark input:not([type="checkbox"]):not([type="radio"]),
        html.dark select,
        html.dark textarea {
            background-color: #0f172a !important;
            border-color: var(--border-color) !important;
            color: var(--text-primary) !important;
        }

        html.dark .hover\:bg-gray-50:hover,
        html.dark .hover\:bg-gray-100:hover {
            background-color: #374151 !important;
        }

        select {
            min-height: 42px;
            height: 42px;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            padding-right: 2rem !important;
            line-height: 42px;
            -webkit-appearance: none;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M5 7.5L10 12.5L15 7.5' stroke='%2364758b' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right .75rem center;
            background-size: 14px 14px;
        }

        select[multiple],
        select[size] {
            height: auto;
            min-height: 42px;
            padding-top: .5rem !important;
            padding-bottom: .5rem !important;
            line-height: 1.25rem;
            background-image: none;
        }

        .modal-overlay {
            display: none !important;
        }

        .modal-overlay.is-open {
            display: flex !important;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased dark:bg-gray-950 dark:text-gray-100">
    @php
        use App\Support\UsuarioComercial;

        $user = auth()->user();
        $principal = UsuarioComercial::principal();
        $userTipo = $principal?->tipo ?? $user?->tipo ?? 'admin';
        $ehAdmin = $userTipo === 'admin';
        $ehMarketplace = $userTipo === 'marketplace';
        $ehMaster = $userTipo === 'master';
        $userName = $user?->nomeExibicao() ?? $user?->nome ?? 'Administrador';
        $userRole = strtoupper($userTipo);
        $navClass = fn (string $pattern) => request()->routeIs($pattern)
            ? 'flex items-center gap-3 px-4 py-2.5 text-sm font-semibold text-blue-600 bg-blue-50 rounded-lg mx-2 dark:bg-blue-950/50 dark:text-blue-400'
            : 'flex items-center gap-3 px-4 py-2.5 text-sm text-gray-600 rounded-lg mx-2 hover:bg-gray-100 hover:text-gray-900 transition-colors dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100';
        $usuarioRouteTipo = request()->route('usuario')?->tipo;
        $usuarioMenuTipo = request('tipo') ?: $usuarioRouteTipo;
        $usuarioBaseActive = request()->routeIs('usuarios.*') && ! in_array($usuarioMenuTipo, ['master', 'marketplace', 'revenda'], true);
    @endphp

    <div class="flex h-screen overflow-hidden bg-gray-50 dark:bg-gray-950">
        <aside class="hidden w-56 flex-shrink-0 flex-col overflow-y-auto border-r border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 lg:flex">
            <div class="px-4 pb-5 pt-4">
                <a href="{{ route('dashboard') }}" class="block">
                    <img src="{{ $logoUrl }}" alt="{{ $appName }}" class="h-16 w-auto object-contain">
                </a>
            </div>

            <nav class="pb-4">
                <p class="mb-1 mt-6 px-4 text-xs font-semibold uppercase tracking-widest text-gray-400">Geral</p>
                <a href="{{ route('dashboard') }}" class="{{ $navClass('dashboard') }}">
                    <i class="fa-solid fa-gauge-high w-5 text-center text-[15px]"></i>
                    <span>Dashboard</span>
                </a>
                <a href="{{ route('relatorios.faturamento') }}" class="{{ $navClass('relatorios.*') }}">
                    <i class="fa-solid fa-chart-line w-5 text-center text-[15px]"></i>
                    <span>Faturamento</span>
                </a>
                <a href="{{ route('comissoes.index') }}" class="{{ $navClass('comissoes.index') }}">
                    <i class="fa-solid fa-hand-holding-dollar w-5 text-center text-[15px]"></i>
                    <span>Comissão</span>
                </a>
                <a href="{{ route('comissoes.configuracoes.index') }}" class="{{ $navClass('comissoes.configuracoes.*') }}">
                    <i class="fa-solid fa-sliders w-5 text-center text-[15px]"></i>
                    <span>Config. Comissão</span>
                </a>
                <a href="{{ route($ehAdmin ? 'admin.chamados.index' : 'chamados.index') }}" class="{{ request()->routeIs('admin.chamados.*') || request()->routeIs('chamados.*') ? 'flex items-center gap-3 px-4 py-2.5 text-sm font-semibold text-blue-600 bg-blue-50 rounded-lg mx-2 dark:bg-blue-950/50 dark:text-blue-400' : 'flex items-center gap-3 px-4 py-2.5 text-sm text-gray-600 rounded-lg mx-2 hover:bg-gray-100 hover:text-gray-900 transition-colors dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100' }}">
                    <i class="fa-solid fa-ticket w-5 text-center text-[15px]"></i>
                    <span>Chamados</span>
                    @if (($chamadosAbertos ?? 0) > 0)
                        <span class="ml-auto rounded-full bg-red-500 px-1.5 py-0.5 text-xs font-bold text-white">{{ $chamadosAbertos > 99 ? '99+' : $chamadosAbertos }}</span>
                    @endif
                </a>

                <p class="mb-1 mt-6 px-4 text-xs font-semibold uppercase tracking-widest text-gray-400">Operações</p>
                <a href="{{ route('estabelecimentos.index') }}" class="{{ $navClass('estabelecimentos.*') }}">
                    <i class="fa-solid fa-store w-5 text-center text-[15px]"></i>
                    <span>Estabelecimentos</span>
                </a>
                <a href="{{ route('planos.index') }}" class="{{ $navClass('planos.*') }}">
                    <i class="fa-solid fa-credit-card w-5 text-center text-[15px]"></i>
                    <span>Planos e Taxas</span>
                </a>

                <p class="mb-1 mt-6 px-4 text-xs font-semibold uppercase tracking-widest text-gray-400">Administração</p>
                @if ($ehMarketplace && $principal)
                    @php
                        $perfilMarketplaceAtivo = request()->routeIs('usuarios.show') && (int) request()->route('usuario')?->id === (int) $principal->id;
                    @endphp
                    <a href="{{ route('usuarios.show', $principal) }}" class="{{ $perfilMarketplaceAtivo ? 'flex items-center gap-3 px-4 py-2.5 text-sm font-semibold text-blue-600 bg-blue-50 rounded-lg mx-2 dark:bg-blue-950/50 dark:text-blue-400' : 'flex items-center gap-3 px-4 py-2.5 text-sm text-gray-600 rounded-lg mx-2 hover:bg-gray-100 hover:text-gray-900 transition-colors dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100' }}">
                        <i class="fa-solid fa-users w-5 text-center text-[15px]"></i>
                        <span>Usuários operacionais</span>
                    </a>
                    <a href="{{ route('usuarios.index', ['tipo' => 'revenda']) }}" class="{{ $usuarioMenuTipo === 'revenda' ? 'flex items-center gap-3 px-4 py-2.5 text-sm font-semibold text-blue-600 bg-blue-50 rounded-lg mx-2 dark:bg-blue-950/50 dark:text-blue-400' : 'flex items-center gap-3 px-4 py-2.5 text-sm text-gray-600 rounded-lg mx-2 hover:bg-gray-100 hover:text-gray-900 transition-colors dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100' }}">
                        <i class="fa-solid fa-handshake w-5 text-center text-[15px]"></i>
                        <span>Revendas</span>
                    </a>
                @else
                    @if ($ehAdmin)
                        <a href="{{ route('usuarios.index') }}" class="{{ $usuarioBaseActive ? 'flex items-center gap-3 px-4 py-2.5 text-sm font-semibold text-blue-600 bg-blue-50 rounded-lg mx-2 dark:bg-blue-950/50 dark:text-blue-400' : 'flex items-center gap-3 px-4 py-2.5 text-sm text-gray-600 rounded-lg mx-2 hover:bg-gray-100 hover:text-gray-900 transition-colors dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100' }}">
                            <i class="fa-solid fa-users w-5 text-center text-[15px]"></i>
                            <span>Usuários</span>
                        </a>
                        <a href="{{ route('usuarios.index', ['tipo' => 'master']) }}" class="{{ $usuarioMenuTipo === 'master' ? 'flex items-center gap-3 px-4 py-2.5 text-sm font-semibold text-blue-600 bg-blue-50 rounded-lg mx-2 dark:bg-blue-950/50 dark:text-blue-400' : 'flex items-center gap-3 px-4 py-2.5 text-sm text-gray-600 rounded-lg mx-2 hover:bg-gray-100 hover:text-gray-900 transition-colors dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100' }}">
                            <i class="fa-solid fa-crown w-5 text-center text-[15px]"></i>
                            <span>Masters</span>
                        </a>
                        <a href="{{ route('usuarios.index', ['tipo' => 'marketplace']) }}" class="{{ $usuarioMenuTipo === 'marketplace' ? 'flex items-center gap-3 px-4 py-2.5 text-sm font-semibold text-blue-600 bg-blue-50 rounded-lg mx-2 dark:bg-blue-950/50 dark:text-blue-400' : 'flex items-center gap-3 px-4 py-2.5 text-sm text-gray-600 rounded-lg mx-2 hover:bg-gray-100 hover:text-gray-900 transition-colors dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100' }}">
                            <i class="fa-solid fa-network-wired w-5 text-center text-[15px]"></i>
                            <span>Marketplaces</span>
                        </a>
                    @endif
                    @if ($ehAdmin || $ehMaster)
                        @unless ($ehAdmin)
                            <a href="{{ route('usuarios.index') }}" class="{{ $usuarioBaseActive ? 'flex items-center gap-3 px-4 py-2.5 text-sm font-semibold text-blue-600 bg-blue-50 rounded-lg mx-2 dark:bg-blue-950/50 dark:text-blue-400' : 'flex items-center gap-3 px-4 py-2.5 text-sm text-gray-600 rounded-lg mx-2 hover:bg-gray-100 hover:text-gray-900 transition-colors dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100' }}">
                                <i class="fa-solid fa-users w-5 text-center text-[15px]"></i>
                                <span>Usuários</span>
                            </a>
                        @endunless
                        @if ($ehMaster)
                            <a href="{{ route('usuarios.index', ['tipo' => 'marketplace']) }}" class="{{ $usuarioMenuTipo === 'marketplace' ? 'flex items-center gap-3 px-4 py-2.5 text-sm font-semibold text-blue-600 bg-blue-50 rounded-lg mx-2 dark:bg-blue-950/50 dark:text-blue-400' : 'flex items-center gap-3 px-4 py-2.5 text-sm text-gray-600 rounded-lg mx-2 hover:bg-gray-100 hover:text-gray-900 transition-colors dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100' }}">
                                <i class="fa-solid fa-network-wired w-5 text-center text-[15px]"></i>
                                <span>Marketplaces</span>
                            </a>
                        @endif
                        <a href="{{ route('usuarios.index', ['tipo' => 'revenda']) }}" class="{{ $usuarioMenuTipo === 'revenda' ? 'flex items-center gap-3 px-4 py-2.5 text-sm font-semibold text-blue-600 bg-blue-50 rounded-lg mx-2 dark:bg-blue-950/50 dark:text-blue-400' : 'flex items-center gap-3 px-4 py-2.5 text-sm text-gray-600 rounded-lg mx-2 hover:bg-gray-100 hover:text-gray-900 transition-colors dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100' }}">
                            <i class="fa-solid fa-handshake w-5 text-center text-[15px]"></i>
                            <span>Revendas</span>
                        </a>
                    @endif
                    @if ($ehAdmin)
                        <a href="{{ route('segmentos.index') }}" class="{{ $navClass('segmentos.*') }}">
                            <i class="fa-solid fa-tags w-5 text-center text-[15px]"></i>
                            <span>Segmentos</span>
                        </a>
                        <a href="{{ route('admin.kyc.index') }}" class="{{ $navClass('admin.kyc.*') }}">
                            <i class="fa-solid fa-shield-halved w-5 text-center text-[15px]"></i>
                            <span>KYC</span>
                        </a>
                        <a href="{{ route('admin.email-templates.index') }}" class="{{ $navClass('admin.email-templates.*') }}">
                            <i class="fa-solid fa-envelope-open-text w-5 text-center text-[15px]"></i>
                            <span>Templates E-mail</span>
                        </a>
                        <a href="{{ route('admin.configuracoes.edit') }}" class="{{ $navClass('admin.configuracoes.*') }}">
                            <i class="fa-solid fa-gear w-5 text-center text-[15px]"></i>
                            <span>Configurações</span>
                        </a>
                    @endif
                @endif
            </nav>

            <div class="mt-auto flex items-center gap-3 border-t border-gray-200 px-4 py-3 dark:border-gray-800">
                <a href="{{ route('perfil.edit') }}" class="shrink-0">
                    @if ($avatarUrl ?? null)
                        <img src="{{ $avatarUrl }}" alt="" class="h-8 w-8 rounded-full border border-gray-200 object-cover dark:border-gray-700">
                    @else
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">
                            {{ $userIniciais ?? mb_substr($userName, 0, 1) }}
                        </div>
                    @endif
                </a>
                <div class="min-w-0">
                    <a href="{{ route('perfil.edit') }}" class="truncate text-sm font-semibold text-gray-800 hover:text-blue-600 dark:text-gray-100 dark:hover:text-blue-400">{{ $userName }}</a>
                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ $userRole }}</p>
                </div>
                @auth
                    <form method="POST" action="{{ route('logout') }}" class="ml-auto">
                        @csrf
                        <button class="text-gray-400 transition-colors hover:text-gray-600" title="Sair"><i class="fa-solid fa-right-from-bracket"></i></button>
                    </form>
                @endauth
            </div>
        </aside>

        <div class="flex min-h-0 min-w-0 flex-1 flex-col">
            <header class="flex h-16 flex-shrink-0 items-center justify-between border-b border-gray-200 bg-white px-6 dark:border-gray-800 dark:bg-gray-900">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">@yield('title', 'Dashboard')</h1>
                </div>
                <div class="flex items-center gap-2 sm:gap-3" x-data="{ notifOpen: false }">
                    @if ($ehAdmin)
                        <a
                            href="{{ route('admin.configuracoes.edit') }}"
                            class="flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 text-gray-600 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                            title="Configurações da plataforma"
                        >
                            <i class="fa-solid fa-gear"></i>
                        </a>
                    @endif
                    <button
                        type="button"
                        id="theme-toggle"
                        class="flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 text-gray-600 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        title="Alternar tema claro/escuro"
                        aria-label="Alternar tema"
                    >
                        <i class="fa-solid fa-moon pointer-events-none hidden dark:inline"></i>
                        <i class="fa-solid fa-sun pointer-events-none dark:hidden"></i>
                    </button>

                    <div class="relative">
                        <button
                            type="button"
                            @click="notifOpen = !notifOpen"
                            class="relative flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 text-gray-600 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                            title="Notificações"
                        >
                            <i class="fa-solid fa-bell"></i>
                            @if (($notificacoesTotal ?? 0) > 0)
                                <span class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                                    {{ ($notificacoesTotal ?? 0) > 9 ? '9+' : $notificacoesTotal }}
                                </span>
                            @endif
                        </button>
                        <div
                            x-show="notifOpen"
                            x-cloak
                            @click.outside="notifOpen = false"
                            class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800"
                        >
                            <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Notificações</p>
                            </div>
                            <div class="max-h-72 overflow-y-auto">
                                @forelse ($notificacoes ?? [] as $notificacao)
                                    <a
                                        href="{{ $notificacao['url'] }}"
                                        class="flex gap-3 border-b border-gray-50 px-4 py-3 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50"
                                        @click="notifOpen = false"
                                    >
                                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-400">
                                            <i class="fa-solid {{ $notificacao['icone'] }} text-sm"></i>
                                        </span>
                                        <span>
                                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $notificacao['titulo'] }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $notificacao['mensagem'] }}</p>
                                        </span>
                                    </a>
                                @empty
                                    <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Nenhuma notificação no momento.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <a
                        href="{{ route('perfil.edit') }}"
                        class="flex items-center gap-2 rounded-lg border border-gray-200 py-1.5 pl-1.5 pr-3 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                        title="Meu perfil"
                    >
                        @if ($avatarUrl ?? null)
                            <img src="{{ $avatarUrl }}" alt="" class="h-8 w-8 rounded-full object-cover">
                        @else
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">
                                {{ $userIniciais ?? '?' }}
                            </div>
                        @endif
                        <span class="hidden max-w-[120px] truncate text-sm font-semibold text-gray-700 dark:text-gray-200 sm:inline">{{ $userName }}</span>
                    </a>
                </div>
            </header>

            <main class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden bg-gray-50 p-6 dark:bg-gray-950">
                @if (session('status'))
                    <div class="mb-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700 shadow-sm dark:border-green-900 dark:bg-green-950 dark:text-green-300">{{ session('status') }}</div>
                @endif

                {{ $slot ?? '' }}
                @yield('content')
            </main>
        </div>
    </div>

    @stack('modals')

    <script>
        (function () {
            const btn = document.getElementById('theme-toggle');
            if (!btn) return;
            btn.addEventListener('click', () => {
                const isDark = document.documentElement.classList.toggle('dark');
                localStorage.setItem('express-theme', isDark ? 'dark' : 'light');
            });
        })();

        document.addEventListener('DOMContentLoaded', () => {
            const onlyDigits = (value) => (value || '').replace(/\D/g, '');

            const masks = {
                cpf(value) {
                    const digits = onlyDigits(value).slice(0, 11);
                    return digits
                        .replace(/(\d{3})(\d)/, '$1.$2')
                        .replace(/(\d{3})(\d)/, '$1.$2')
                        .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                },
                cnpj(value) {
                    const digits = onlyDigits(value).slice(0, 14);
                    return digits
                        .replace(/^(\d{2})(\d)/, '$1.$2')
                        .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
                        .replace(/\.(\d{3})(\d)/, '.$1/$2')
                        .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
                },
                cep(value) {
                    const digits = onlyDigits(value).slice(0, 8);
                    return digits.replace(/(\d{5})(\d{1,3})$/, '$1-$2');
                },
                phone(value) {
                    const digits = onlyDigits(value).slice(0, 11);

                    if (digits.length <= 10) {
                        return digits
                            .replace(/^(\d{2})(\d)/, '($1) $2')
                            .replace(/(\d{4})(\d{1,4})$/, '$1-$2');
                    }

                    return digits
                        .replace(/^(\d{2})(\d)/, '($1) $2')
                        .replace(/(\d{5})(\d{1,4})$/, '$1-$2');
                },
                uf(value) {
                    return (value || '').replace(/[^a-zA-Z]/g, '').slice(0, 2).toUpperCase();
                },
            };

            const maskByName = (name) => {
                if (['cnpj'].includes(name)) return masks.cnpj;
                if (['cpf', 'rep_cpf'].includes(name)) return masks.cpf;
                if (['cep'].includes(name)) return masks.cep;
                if (['telefone', 'celular'].includes(name)) return masks.phone;
                if (['uf'].includes(name)) return masks.uf;

                return null;
            };

            const applyMask = (input) => {
                const name = input.name || input.dataset.autofill || '';
                const mask = maskByName(name);

                if (!mask) {
                    return;
                }

                input.value = mask(input.value);
                input.addEventListener('input', () => {
                    input.value = mask(input.value);
                });
                input.addEventListener('blur', () => {
                    input.value = mask(input.value);
                });
            };

            document.querySelectorAll('input[name], input[data-autofill]').forEach(applyMask);
        });
    </script>
    @yield('scripts')
</body>
</html>

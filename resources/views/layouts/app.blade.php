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
        $ehRevenda = $userTipo === 'revenda';
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

    <div
        class="flex h-screen overflow-hidden bg-gray-50 dark:bg-gray-950"
        x-data="{ sidebarOpen: false }"
        @keydown.escape.window="sidebarOpen = false"
    >
        {{-- Overlay mobile --}}
        <div
            x-show="sidebarOpen"
            x-cloak
            x-transition:enter="transition-opacity ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-40 bg-black/50 lg:hidden"
            @click="sidebarOpen = false"
            aria-hidden="true"
        ></div>

        {{-- Menu lateral mobile --}}
        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-[min(100vw-3rem,18rem)] flex-col border-r border-gray-200 bg-white shadow-xl transition-transform duration-300 ease-in-out dark:border-gray-800 dark:bg-gray-900 lg:hidden"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            aria-label="Menu principal"
        >
            @include('partials.app-sidebar', ['mobileMenu' => true])
        </aside>

        {{-- Sidebar desktop --}}
        <aside class="hidden w-56 flex-shrink-0 flex-col overflow-y-auto border-r border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 lg:flex">
            @include('partials.app-sidebar')
        </aside>

        <div class="flex min-h-0 min-w-0 flex-1 flex-col">
            <header class="flex h-14 flex-shrink-0 items-center justify-between gap-3 border-b border-gray-200 bg-white px-4 dark:border-gray-800 dark:bg-gray-900 sm:h-16 sm:px-6">
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <button
                        type="button"
                        @click="sidebarOpen = true"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 text-gray-600 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800 lg:hidden"
                        aria-label="Abrir menu"
                    >
                        <i class="fa-solid fa-bars text-lg"></i>
                    </button>
                    <h1 class="truncate text-lg font-bold text-gray-800 dark:text-gray-100 sm:text-xl lg:text-2xl">@yield('title', 'Dashboard')</h1>
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

            <main class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden bg-gray-50 p-4 dark:bg-gray-950 sm:p-6">
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
                documento(value) {
                    const digits = onlyDigits(value).slice(0, 14);
                    return digits.length <= 11 ? masks.cpf(digits) : masks.cnpj(digits);
                },
                cep(value) {
                    const digits = onlyDigits(value).slice(0, 8);
                    return digits.replace(/(\d{5})(\d{1,3})$/, '$1-$2');
                },
                celular(value) {
                    const digits = onlyDigits(value).slice(0, 11);

                    return digits
                        .replace(/^(\d{2})(\d)/, '($1) $2')
                        .replace(/(\d{5})(\d{1,4})$/, '$1-$2');
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
                if (['documento'].includes(name)) return masks.documento;
                if (['cep'].includes(name)) return masks.cep;
                if (['telefone'].includes(name)) return masks.phone;
                if (['celular'].includes(name)) return masks.celular;
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

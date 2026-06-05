@extends('layouts.app')

@section('title', 'Templates de e-mail')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Templates de notificação</h2>
            <p class="text-sm text-gray-500">E-mails automáticos do sistema — KYC, chamados, cadastros e mais.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif

    @forelse ($templates as $categoria => $itens)
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-100 px-5 py-3 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                    {{ $categorias[$categoria] ?? ucfirst($categoria) }}
                </h3>
            </div>
            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($itens as $template)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                        <div>
                            <p class="font-medium text-gray-800 dark:text-gray-100">{{ $template->nome }}</p>
                            <p class="text-xs text-gray-400">{{ $template->slug }} · {{ $template->assunto }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($template->ativo)
                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700">Ativo</span>
                            @else
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500">Inativo</span>
                            @endif
                            <a href="{{ route('admin.email-templates.edit', $template) }}" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200">
                                Editar
                            </a>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <p class="text-sm text-gray-500">Nenhum template encontrado. Execute o seeder <code>EmailTemplateSeeder</code>.</p>
    @endforelse
</div>
@endsection

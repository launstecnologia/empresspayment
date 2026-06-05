<!doctype html>
<html lang="pt-BR">
<head>
    @include('partials.head-meta', [
        'pageTitle' => 'Envio de Documentos',
        'description' => 'Envie os documentos solicitados pelo estabelecimento na plataforma Express Payments.',
        'robots' => 'noindex, nofollow',
    ])
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 px-4 py-10 text-slate-800">
    <main class="mx-auto max-w-3xl overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-6 py-5">
            <h1 class="text-xl font-bold">Envio de Documentos</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $estabelecimento->nome_fantasia ?: $estabelecimento->razao_social ?: $estabelecimento->nome_completo }}</p>
        </div>

        <div class="p-6">
            @if (session('status'))
                <div class="mb-5 rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="mb-5 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <p class="font-semibold">Revise o envio.</p>
                    <ul class="mt-2 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('documentos.public.store', $estabelecimento->documento_token_publico) }}" enctype="multipart/form-data" class="space-y-5">
                @csrf
                <label class="block space-y-1">
                    <span class="text-sm font-bold">Tipo de Documento</span>
                    <select name="tipo_documento" class="h-11 w-full rounded border border-slate-300 bg-white px-3 text-sm">
                        <option value="">Selecione</option>
                        <option>RG/CNH - Frente</option>
                        <option>RG/CNH - Verso</option>
                        <option>Selfie Segurando Documento</option>
                        <option>Comprovante da Atividade</option>
                        <option>Comprovante de Endereço</option>
                        <option>Contrato Social (Se for MEI - CCMEI)</option>
                    </select>
                </label>

                <label data-dropzone class="flex min-h-44 cursor-pointer flex-col items-center justify-center rounded border-2 border-dashed border-blue-200 bg-blue-50/60 px-4 py-8 text-center transition-colors hover:border-blue-400 hover:bg-blue-50">
                    <span class="text-lg font-bold text-slate-800">Arraste o documento aqui</span>
                    <span data-file-name class="mt-1 text-sm text-slate-500">ou clique para escolher um arquivo</span>
                    <span class="mt-1 text-xs text-slate-400">PDF, imagem ou Word até 25MB.</span>
                    <input data-file-input type="file" name="documento" class="sr-only" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
                </label>

                <div class="flex justify-end">
                    <button class="rounded bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700">Enviar Documento</button>
                </div>
            </form>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const dropzone = document.querySelector('[data-dropzone]');
            const fileInput = document.querySelector('[data-file-input]');
            const fileName = document.querySelector('[data-file-name]');

            ['dragenter', 'dragover'].forEach((eventName) => {
                dropzone.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    dropzone.classList.add('border-blue-500', 'bg-blue-100');
                });
            });

            ['dragleave', 'drop'].forEach((eventName) => {
                dropzone.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    dropzone.classList.remove('border-blue-500', 'bg-blue-100');
                });
            });

            dropzone.addEventListener('drop', (event) => {
                if (event.dataTransfer.files.length) {
                    fileInput.files = event.dataTransfer.files;
                    fileName.textContent = event.dataTransfer.files[0].name;
                }
            });

            fileInput.addEventListener('change', () => {
                fileName.textContent = fileInput.files[0]?.name || 'ou clique para escolher um arquivo';
            });
        });
    </script>
</body>
</html>

<?php

namespace App\Http\Controllers\Estabelecimento;

use App\Http\Controllers\Controller;
use App\Models\Estabelecimento;
use App\Models\Log;
use App\Models\Plano;
use App\Models\Segmento;
use App\Models\Usuario;
use App\Support\UsuarioComercial;
use App\Services\EmailPlataformaService;
use App\Services\HierarquiaService;
use App\Services\LogService;
use App\Services\NotificacaoEmailService;
use App\Services\RoyaltyCalculadorService;
use App\Support\NotificacaoVars;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EstabelecimentoController extends Controller
{
    public function index(Request $request)
    {
        $query = Estabelecimento::query()->with('marketplace')->latest();

        $this->aplicarFiltrosIndex($query, $request);

        $filtros = $request->only([
            'master_id',
            'marketplace_id',
            'revenda_id',
            'status',
            'risco',
            'plano_id',
            'segmento',
            'pessoa_tipo',
            'ativo',
            'data_inicio',
            'data_fim',
        ]);

        return view('estabelecimento.index', [
            'estabelecimentos' => $query->paginate(20)->withQueryString(),
            'filtros' => $filtros,
            'masters' => $this->usuariosPorTipo('master'),
            'marketplaces' => $this->usuariosPorTipo('marketplace'),
            'revendas' => $this->usuariosPorTipo('revenda'),
            'planos' => Plano::where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
            'segmentos' => Segmento::where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function create()
    {
        $this->autorizarMutacaoEstabelecimento();

        return view('estabelecimento.form', [
            'estabelecimento' => new Estabelecimento,
            'planos' => Plano::where('ativo', true)->orderBy('nome')->get(),
            'segmentos' => Segmento::where('ativo', true)->orderBy('nome')->get(),
            ...$this->opcoesFormulario(),
        ]);
    }

    public function store(Request $request, RoyaltyCalculadorService $royalties, HierarquiaService $hierarquia, EmailPlataformaService $emailPlataforma)
    {
        $this->autorizarMutacaoEstabelecimento();

        $dados = $this->validar($request);
        $usuario = $request->user();
        $dados = array_merge(
            $dados,
            $hierarquia->cadeiaParaEstabelecimento($usuario),
            $this->hierarquiaSelecionada($dados),
            $this->ipCadastro($request),
        );

        $estabelecimento = Estabelecimento::create($dados);
        $royalties->fixarCadeia($estabelecimento->load('plano.taxas.royalties'));

        if (filled($estabelecimento->email)) {
            app(NotificacaoEmailService::class)->enfileirar(
                'estabelecimento.cadastro',
                $estabelecimento->email,
                NotificacaoVars::estabelecimento($estabelecimento),
                route('estabelecimentos.show', $estabelecimento)
            );
        }

        $avisoEmail = null;

        if (config('directadmin.criar_email_ao_habilitar') && filled($estabelecimento->email)) {
            try {
                $usernameOcupado = $emailPlataforma->provisionarAutomatico($estabelecimento);
                if ($usernameOcupado !== null) {
                    $avisoEmail = "O username \"{$usernameOcupado}\" já existe no servidor de e-mail. Acesse o cadastro para definir manualmente.";
                }
            } catch (\Throwable) {
                $avisoEmail = 'Não foi possível criar o e-mail da plataforma automaticamente. Acesse o cadastro para tentar novamente.';
            }
        }

        $redirect = redirect()->route('estabelecimentos.show', $estabelecimento)->with('status', 'Estabelecimento cadastrado.');

        if ($avisoEmail) {
            $redirect = $redirect->with('aviso', $avisoEmail);
        }

        return $redirect;
    }

    public function edit(Estabelecimento $estabelecimento)
    {
        $this->autorizarMutacaoEstabelecimento();

        return view('estabelecimento.form', [
            'estabelecimento' => $estabelecimento,
            'planos' => Plano::where('ativo', true)->orderBy('nome')->get(),
            'segmentos' => Segmento::where('ativo', true)->orderBy('nome')->get(),
            ...$this->opcoesFormulario(),
        ]);
    }

    public function update(Request $request, Estabelecimento $estabelecimento)
    {
        $this->autorizarMutacaoEstabelecimento();

        $estabelecimento->update(array_merge(
            $this->validar($request),
            $this->ipCadastro($request, $estabelecimento),
        ));

        return redirect()->route('estabelecimentos.index')->with('status', 'Estabelecimento atualizado.');
    }

    public function show(Estabelecimento $estabelecimento)
    {
        if (blank($estabelecimento->documento_token_publico)) {
            $estabelecimento->forceFill(['documento_token_publico' => (string) Str::uuid()])->save();
        }

        $estabelecimento->load(['master', 'marketplace', 'revenda', 'plano', 'documentos', 'emails', 'kycAnalise']);

        $logs = Log::where('entidade', 'Estabelecimento')
            ->where('entidade_id', $estabelecimento->id)
            ->latest()
            ->take(10)
            ->get();

        return view('estabelecimento.show', compact('estabelecimento', 'logs'));
    }

    public function updateStatus(Request $request, Estabelecimento $estabelecimento, LogService $log)
    {
        $dados = $request->validate([
            'status' => ['required', 'in:habilitado,desabilitado,em_analise,pendente,qualidade,em_cadastro'],
            'observacao' => ['nullable', 'string', 'max:1000'],
        ]);

        $anterior = $estabelecimento->status;

        $estabelecimento->update([
            'status' => $dados['status'],
            'anotacoes_interno' => trim(($estabelecimento->anotacoes_interno ? $estabelecimento->anotacoes_interno.PHP_EOL.PHP_EOL : '').($dados['observacao'] ?? '')),
        ]);

        $log->registrar(
            'Estabelecimento',
            $estabelecimento->id,
            'update_status',
            'Status alterado com sucesso',
            ['status' => $anterior],
            ['status' => $dados['status'], 'observacao' => $dados['observacao'] ?? null],
        );

        return redirect()->route('estabelecimentos.show', $estabelecimento)->with('status', 'Status alterado com sucesso.');
    }

    public function destroy(Estabelecimento $estabelecimento)
    {
        $this->autorizarMutacaoEstabelecimento();

        $estabelecimento->delete();

        return redirect()->route('estabelecimentos.index')->with('status', 'Estabelecimento removido.');
    }

    private function autorizarMutacaoEstabelecimento(): void
    {
        abort_unless(UsuarioComercial::podeCadastrarEstabelecimento(), 403, 'Seu perfil não pode cadastrar ou alterar estabelecimentos.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'pessoa_tipo' => ['required', 'in:juridica,fisica'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'cpf' => ['nullable', 'string', 'max:14'],
            'razao_social' => ['nullable', 'string', 'max:200'],
            'inscricao_estadual' => ['nullable', 'string', 'max:30'],
            'data_abertura' => ['nullable', 'date'],
            'nome_completo' => ['nullable', 'string', 'max:200'],
            'data_nascimento' => ['nullable', 'date'],
            'nome_fantasia' => ['nullable', 'string', 'max:200'],
            'segmento' => ['nullable', 'string', 'max:200'],
            'rep_nome' => ['nullable', 'string', 'max:200'],
            'rep_cpf' => ['nullable', 'string', 'max:14'],
            'rep_data_nascimento' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:150'],
            'telefone' => ['nullable', 'string', 'max:15'],
            'celular' => ['nullable', 'string', 'max:15'],
            'cep' => ['nullable', 'string', 'max:9'],
            'endereco' => ['nullable', 'string', 'max:200'],
            'numero' => ['nullable', 'string', 'max:10'],
            'complemento' => ['nullable', 'string', 'max:100'],
            'bairro' => ['nullable', 'string', 'max:100'],
            'cidade' => ['nullable', 'string', 'max:100'],
            'uf' => ['nullable', 'string', 'size:2'],
            'token_pagseguro' => ['nullable', 'string', 'max:255'],
            'plano_id' => ['nullable', 'exists:planos,id'],
            'master_id' => ['nullable', Rule::exists('usuarios', 'id')->where('tipo', 'master')],
            'marketplace_id' => ['nullable', Rule::exists('usuarios', 'id')->where('tipo', 'marketplace')],
            'revenda_id' => ['nullable', Rule::exists('usuarios', 'id')->where('tipo', 'revenda')],
            'subdominio' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('estabelecimentos', 'subdominio')->ignore($request->route('estabelecimento')),
            ],
            'status' => ['nullable', 'in:habilitado,desabilitado,em_analise,pendente,qualidade,em_cadastro'],
            'risco' => ['nullable', 'in:confiavel,atencao,bloqueado'],
            'anotacoes' => ['nullable', 'string'],
            'anotacoes_interno' => ['nullable', 'string'],
            'ativo' => ['nullable', 'boolean'],
        ]);
    }

    private function ipCadastro(Request $request, ?Estabelecimento $estabelecimento = null): array
    {
        if ($estabelecimento?->ip_cadastro) {
            return [];
        }

        $ip = $request->ip();

        return $ip ? ['ip_cadastro' => $ip] : [];
    }

    private function opcoesFormulario(): array
    {
        return [
            'gestores' => Usuario::where('tipo', 'master')->where('ativo', true)->orderBy('nome_fantasia')->orderBy('razao_social')->orderBy('nome_completo')->get(),
            'representantes' => Usuario::where('tipo', 'marketplace')->where('ativo', true)->orderBy('nome_fantasia')->orderBy('razao_social')->orderBy('nome_completo')->get(),
            'revendas' => Usuario::where('tipo', 'revenda')->where('ativo', true)->orderBy('nome_fantasia')->orderBy('razao_social')->orderBy('nome_completo')->get(),
        ];
    }

    private function hierarquiaSelecionada(array $dados): array
    {
        return array_filter([
            'master_id' => $dados['master_id'] ?? null,
            'marketplace_id' => $dados['marketplace_id'] ?? null,
            'revenda_id' => $dados['revenda_id'] ?? null,
        ]);
    }

    private function aplicarFiltrosIndex(Builder $query, Request $request): void
    {
        if ($request->filled('master_id')) {
            $query->where('master_id', $request->integer('master_id'));
        }

        if ($request->filled('marketplace_id')) {
            $query->where('marketplace_id', $request->integer('marketplace_id'));
        }

        if ($request->filled('revenda_id')) {
            $query->where('revenda_id', $request->integer('revenda_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('risco')) {
            $query->where('risco', $request->string('risco'));
        }

        if ($request->filled('plano_id')) {
            $query->where('plano_id', $request->integer('plano_id'));
        }

        if ($request->filled('segmento')) {
            $query->where('segmento', $request->string('segmento'));
        }

        if ($request->filled('pessoa_tipo')) {
            $query->where('pessoa_tipo', $request->string('pessoa_tipo'));
        }

        if ($request->has('ativo') && $request->input('ativo') !== '') {
            $query->where('ativo', $request->boolean('ativo'));
        }

        if ($request->filled('data_inicio')) {
            $query->whereDate('created_at', '>=', $request->date('data_inicio'));
        }

        if ($request->filled('data_fim')) {
            $query->whereDate('created_at', '<=', $request->date('data_fim'));
        }
    }

    private function usuariosPorTipo(string $tipo)
    {
        return Usuario::query()
            ->where('tipo', $tipo)
            ->where('ativo', true)
            ->orderByRaw('COALESCE(nome_fantasia, razao_social, nome_completo, email)')
            ->get()
            ->map(fn (Usuario $usuario) => [
                'id' => $usuario->id,
                'nome' => $usuario->nomeExibicao(),
            ]);
    }
}

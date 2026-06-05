<?php

namespace App\Services;

use App\Models\Chamado;
use App\Models\ChamadoAnexo;
use App\Models\ChamadoMensagem;
use App\Models\SubUsuario;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class ChamadoService
{
    public const CATEGORIAS = ['financeiro', 'tecnico', 'comercial', 'cadastro', 'integracao', 'outro'];

    public const PRIORIDADES = ['baixa', 'media', 'alta', 'urgente'];

    public const STATUS = ['aberto', 'em_atendimento', 'aguardando_cliente', 'resolvido', 'fechado'];

    public function gerarNumero(): string
    {
        $ano = now()->format('Y');
        $ultimoId = Chamado::whereYear('created_at', $ano)->max('id') ?? 0;

        return 'CHM-'.$ano.'-'.str_pad($ultimoId + 1, 6, '0', STR_PAD_LEFT);
    }

    public function abrir(Usuario|SubUsuario $autor, array $dados, array $anexos = []): Chamado
    {
        $dono = $autor instanceof SubUsuario ? $autor->dono : $autor;
        $cadeia = $this->cadeiaComercial($dono);

        $chamado = Chamado::create([
            'aberto_por_id' => $autor->id,
            'aberto_por_tipo' => $autor instanceof SubUsuario ? 'sub_usuario' : 'usuario',
            'aberto_por_nivel' => $dono->tipo,
            'master_id' => $cadeia['master_id'],
            'marketplace_id' => $cadeia['marketplace_id'],
            'revenda_id' => $cadeia['revenda_id'],
            'titulo' => $dados['titulo'],
            'categoria' => $dados['categoria'],
            'prioridade' => $dados['prioridade'],
            'status' => 'aberto',
            'numero' => $this->gerarNumero(),
        ]);

        $mensagem = $this->criarMensagem($chamado, $autor, $dados['mensagem'], false);
        $this->salvarAnexos($anexos, $mensagem, $chamado);
        $this->historico($chamado, $autor, 'chamado_aberto', null, 'aberto');

        return $chamado;
    }

    public function responder(Chamado $chamado, Usuario|SubUsuario $autor, string $mensagem, array $anexos = [], bool $interno = false): ChamadoMensagem
    {
        $registro = $this->criarMensagem($chamado, $autor, $mensagem, $interno);
        $this->salvarAnexos($anexos, $registro, $chamado);

        if (! $interno) {
            $novoStatus = $this->autorEhAdmin($autor) ? 'aguardando_cliente' : 'em_atendimento';
            $this->alterarStatus($chamado, $autor, $novoStatus);
        }

        return $registro;
    }

    public function alterarStatus(Chamado $chamado, Usuario|SubUsuario $autor, string $status): void
    {
        $anterior = $chamado->status;

        $chamado->forceFill([
            'status' => $status,
            'fechado_em' => in_array($status, ['resolvido', 'fechado'], true) ? now() : null,
        ])->save();

        if ($anterior !== $status) {
            $this->historico($chamado, $autor, 'status_alterado', $anterior, $status);
        }
    }

    public function salvarAnexos(array $arquivos, ChamadoMensagem $mensagem, Chamado $chamado): void
    {
        $arquivos = array_filter($arquivos);

        if (count($arquivos) > 5) {
            throw new RuntimeException('Cada mensagem aceita no máximo 5 anexos.');
        }

        $total = 0;

        foreach ($arquivos as $arquivo) {
            if (! $arquivo instanceof UploadedFile) {
                continue;
            }

            $total += $arquivo->getSize();
        }

        if ($total > 30 * 1024 * 1024) {
            throw new RuntimeException('O tamanho total dos anexos não pode ultrapassar 30MB.');
        }

        foreach ($arquivos as $arquivo) {
            if (! $arquivo instanceof UploadedFile) {
                continue;
            }

            $extensao = strtolower($arquivo->getClientOriginalExtension());

            if (! in_array($extensao, ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'], true)) {
                throw new RuntimeException("Tipo de arquivo não permitido: {$extensao}");
            }

            if ($arquivo->getSize() > 10 * 1024 * 1024) {
                throw new RuntimeException('Cada arquivo pode ter no máximo 10MB.');
            }

            $nomeArquivo = uniqid('chamado_', true).'.'.$extensao;
            $caminho = $arquivo->storeAs("chamados/{$chamado->id}", $nomeArquivo);

            ChamadoAnexo::create([
                'mensagem_id' => $mensagem->id,
                'chamado_id' => $chamado->id,
                'nome_original' => $arquivo->getClientOriginalName(),
                'nome_arquivo' => $nomeArquivo,
                'caminho' => $caminho,
                'mime_type' => $arquivo->getMimeType() ?: 'application/octet-stream',
                'tamanho_bytes' => $arquivo->getSize(),
                'extensao' => $extensao,
            ]);
        }
    }

    public function podeAcessar(Chamado $chamado, Usuario|SubUsuario $usuario): bool
    {
        if ($this->autorEhAdmin($usuario)) {
            return true;
        }

        if ($usuario instanceof SubUsuario) {
            return $chamado->aberto_por_tipo === 'sub_usuario' && (int) $chamado->aberto_por_id === (int) $usuario->id;
        }

        return $chamado->aberto_por_tipo === 'usuario' && (int) $chamado->aberto_por_id === (int) $usuario->id;
    }

    private function criarMensagem(Chamado $chamado, Usuario|SubUsuario $autor, string $mensagem, bool $interno): ChamadoMensagem
    {
        return $chamado->mensagens()->create([
            'autor_id' => $autor->id,
            'autor_tipo' => $this->autorEhAdmin($autor) ? 'admin' : ($autor instanceof SubUsuario ? 'sub_usuario' : 'usuario'),
            'autor_nome' => $autor instanceof SubUsuario ? $autor->nome : $autor->nomeExibicao(),
            'mensagem' => $mensagem,
            'interno' => $interno,
        ]);
    }

    private function historico(Chamado $chamado, Usuario|SubUsuario $autor, string $acao, ?string $anterior, ?string $novo): void
    {
        $chamado->historicos()->create([
            'autor_id' => $autor->id,
            'autor_nome' => $autor instanceof SubUsuario ? $autor->nome : $autor->nomeExibicao(),
            'acao' => $acao,
            'valor_anterior' => $anterior,
            'valor_novo' => $novo,
        ]);
    }

    private function cadeiaComercial(Usuario $usuario): array
    {
        $ids = [
            'master_id' => null,
            'marketplace_id' => null,
            'revenda_id' => null,
        ];

        $no = $usuario->hierarquia;

        while ($no) {
            if ($no->usuario?->tipo === 'master') {
                $ids['master_id'] = $no->usuario->id;
            }

            if ($no->usuario?->tipo === 'marketplace') {
                $ids['marketplace_id'] = $no->usuario->id;
            }

            if ($no->usuario?->tipo === 'revenda') {
                $ids['revenda_id'] = $no->usuario->id;
            }

            $no = $no->pai;
        }

        return $ids;
    }

    private function autorEhAdmin(Usuario|SubUsuario $autor): bool
    {
        return $autor instanceof Usuario && $autor->tipo === 'admin';
    }
}

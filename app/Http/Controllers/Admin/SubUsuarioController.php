<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PerfilPermissao;
use App\Models\SubUsuario;
use App\Models\Usuario;
use App\Support\UsuarioComercial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SubUsuarioController extends Controller
{
    public function create(Usuario $usuario)
    {
        abort_if($usuario->tipo === 'admin', 404);
        abort_unless(UsuarioComercial::podeGerenciar($usuario), 403);

        return view('admin.subusuarios.form', [
            'dono' => $usuario,
            'subUsuario' => new SubUsuario,
            'perfis' => PerfilPermissao::where('dono_id', $usuario->id)->where('ativo', true)->orderBy('nome')->get(),
        ]);
    }

    public function store(Request $request, Usuario $usuario)
    {
        abort_if($usuario->tipo === 'admin', 404);
        abort_unless(UsuarioComercial::podeGerenciar($usuario), 403);

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:150', Rule::unique('sub_usuarios', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'perfil_id' => ['nullable', Rule::exists('perfis_permissao', 'id')->where('dono_id', $usuario->id)],
            'ativo' => ['boolean'],
        ]);

        $dados['dono_id'] = $usuario->id;
        $dados['dono_tipo'] = $usuario->tipo;

        SubUsuario::create($dados);

        return redirect()->route('usuarios.show', $usuario)->with('status', 'Usuário operacional cadastrado.');
    }

    public function editPassword(Usuario $usuario, SubUsuario $subUsuario)
    {
        abort_unless(UsuarioComercial::podeGerenciar($usuario), 403);
        $this->validarDono($usuario, $subUsuario);

        return view('admin.subusuarios.password', compact('usuario', 'subUsuario'));
    }

    public function updatePassword(Request $request, Usuario $usuario, SubUsuario $subUsuario)
    {
        abort_unless(UsuarioComercial::podeGerenciar($usuario), 403);
        $this->validarDono($usuario, $subUsuario);

        $dados = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $subUsuario->update(['password' => $dados['password']]);

        return redirect()->route('usuarios.show', $usuario)->with('status', 'Senha do usuário operacional atualizada.');
    }

    public function resetarSenha(Usuario $usuario, SubUsuario $subUsuario)
    {
        abort_unless(UsuarioComercial::podeGerenciar($usuario), 403);
        $this->validarDono($usuario, $subUsuario);

        $subUsuario->update([
            'password' => '123456',
            'must_change_password' => true,
        ]);

        return redirect()->route('usuarios.show', $usuario)
            ->with('status', 'Senha resetada para 123456. O usuário deverá criar uma nova senha no próximo acesso.');
    }

    public function destroy(Request $request, Usuario $usuario, SubUsuario $subUsuario)
    {
        abort_unless(UsuarioComercial::podeGerenciar($usuario), 403);
        $this->validarDono($usuario, $subUsuario);

        $dados = $request->validate([
            'senha_admin' => ['required', 'string'],
            'confirmacao' => ['accepted'],
        ], [
            'senha_admin.required' => 'Informe sua senha de administrador.',
            'confirmacao.accepted' => 'Confirme que deseja excluir este usuário.',
        ]);

        if (! Hash::check($dados['senha_admin'], $request->user()->password)) {
            return redirect()
                ->route('usuarios.show', $usuario)
                ->withErrors(['senha_admin' => 'Senha de administrador incorreta.'])
                ->with('abrir_modal_excluir_subusuario', $subUsuario->id);
        }

        $nome = $subUsuario->nome;
        $subUsuario->delete();

        return redirect()
            ->route('usuarios.show', $usuario)
            ->with('status', "Usuário operacional {$nome} excluído.");
    }

    private function validarDono(Usuario $usuario, SubUsuario $subUsuario): void
    {
        abort_unless((int) $subUsuario->dono_id === (int) $usuario->id, 404);
    }
}

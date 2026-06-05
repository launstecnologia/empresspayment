<?php

namespace App\Http\Controllers;

use App\Models\SubUsuario;
use App\Models\Usuario;
use App\Support\AvatarUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PerfilController extends Controller
{
    public function edit(Request $request)
    {
        return view('perfil.edit', [
            'perfil' => $request->user(),
        ]);
    }

    public function update(Request $request)
    {
        $usuario = $request->user();

        if ($usuario instanceof SubUsuario) {
            return $this->atualizarSubUsuario($request, $usuario);
        }

        return $this->atualizarUsuario($request, $usuario);
    }

    private function atualizarUsuario(Request $request, Usuario $usuario)
    {
        $dados = $request->validate([
            'nome_fantasia' => ['nullable', 'string', 'max:200'],
            'nome_completo' => ['nullable', 'string', 'max:200'],
            'telefone' => ['nullable', 'string', 'max:15'],
            'celular' => ['nullable', 'string', 'max:15'],
            'email' => ['required', 'email', 'max:150', Rule::unique('usuarios', 'email')->ignore($usuario->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'remover_avatar' => ['boolean'],
        ]);

        if ($request->boolean('remover_avatar') && $usuario->avatar_path) {
            Storage::disk('public')->delete($usuario->avatar_path);
            $dados['avatar_path'] = null;
        }

        if ($request->hasFile('avatar')) {
            $dados['avatar_path'] = AvatarUsuario::salvar($usuario, $request->file('avatar'));
        }

        if (blank($dados['password'] ?? null)) {
            unset($dados['password']);
        }

        unset($dados['avatar'], $dados['remover_avatar'], $dados['password_confirmation']);

        $usuario->update($dados);

        return redirect()->route('perfil.edit')->with('status', 'Perfil atualizado com sucesso.');
    }

    private function atualizarSubUsuario(Request $request, SubUsuario $usuario)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:150', Rule::unique('sub_usuarios', 'email')->ignore($usuario->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'remover_avatar' => ['boolean'],
        ]);

        if ($request->boolean('remover_avatar') && $usuario->avatar_path) {
            Storage::disk('public')->delete($usuario->avatar_path);
            $dados['avatar_path'] = null;
        }

        if ($request->hasFile('avatar')) {
            $dados['avatar_path'] = AvatarUsuario::salvar($usuario, $request->file('avatar'));
        }

        if (blank($dados['password'] ?? null)) {
            unset($dados['password']);
        }

        unset($dados['avatar'], $dados['remover_avatar'], $dados['password_confirmation']);

        $usuario->update($dados);

        return redirect()->route('perfil.edit')->with('status', 'Perfil atualizado com sucesso.');
    }
}

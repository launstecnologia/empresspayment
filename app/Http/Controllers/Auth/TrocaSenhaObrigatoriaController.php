<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TrocaSenhaObrigatoriaController extends Controller
{
    public function create()
    {
        return view('auth.trocar-senha');
    }

    public function store(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.required' => 'Informe a nova senha.',
            'password.min' => 'A senha deve ter pelo menos 8 caracteres.',
            'password.confirmed' => 'As senhas não conferem.',
        ]);

        $user = auth()->user();
        $user->update([
            'password' => $request->password,
            'must_change_password' => false,
        ]);

        return redirect()->route('dashboard')->with('status', 'Senha alterada com sucesso!');
    }
}

<?php

namespace App\Support;

use App\Models\Estabelecimento;
use App\Models\EstabelecimentoEmail;
use Illuminate\Http\RedirectResponse;

class EstabelecimentoEmailRedirect
{
    public static function paraLeitor(Estabelecimento $estabelecimento, ?EstabelecimentoEmail $conta = null, array $query = []): RedirectResponse
    {
        if ($conta) {
            $query['conta'] = $conta->id;
        }

        $url = route('estabelecimentos.email.painel', $estabelecimento);

        if ($query !== []) {
            $url .= '?'.http_build_query(array_filter($query));
        }

        return redirect()->to($url);
    }
}

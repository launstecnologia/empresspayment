<x-mail::message>
@foreach (explode("\n", $corpoTexto) as $linha)
{{ $linha }}

@endforeach

<x-mail::button :url="$link">
Redefinir senha
</x-mail::button>

<x-mail::subcopy>
Se o botão não funcionar, copie e cole este link no navegador: [{{ $link }}]({{ $link }})
</x-mail::subcopy>
</x-mail::message>

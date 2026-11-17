<?php

declare(strict_types=1);

namespace App\Http\Erros;

final class NaoEncontrado extends ErroHttp
{
    public function __construct(string $mensagem = 'Recurso não encontrado.')
    {
        parent::__construct(404, $mensagem);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Erros;

final class NaoAutenticado extends ErroHttp
{
    public function __construct(string $mensagem = 'Autenticação necessária.')
    {
        parent::__construct(401, $mensagem);
    }
}

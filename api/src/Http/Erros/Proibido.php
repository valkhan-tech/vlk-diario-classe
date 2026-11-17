<?php

declare(strict_types=1);

namespace App\Http\Erros;

final class Proibido extends ErroHttp
{
    public function __construct(string $mensagem = 'Acesso negado.')
    {
        parent::__construct(403, $mensagem);
    }
}

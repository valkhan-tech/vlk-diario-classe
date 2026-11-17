<?php

declare(strict_types=1);

namespace App\Http\Erros;

final class Conflito extends ErroHttp
{
    public function __construct(string $mensagem = 'Conflito com o estado atual do recurso.')
    {
        parent::__construct(409, $mensagem);
    }
}

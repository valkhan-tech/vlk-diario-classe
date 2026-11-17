<?php

declare(strict_types=1);

namespace App\Http\Erros;

final class LimiteExcedido extends ErroHttp
{
    public function __construct(string $mensagem = 'Muitas tentativas. Tente novamente mais tarde.')
    {
        parent::__construct(429, $mensagem);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Erros;

final class ErroValidacao extends ErroHttp
{
    /** @param array<string,string> $erros campo => mensagem */
    public function __construct(public readonly array $erros, string $mensagem = 'Dados inválidos.')
    {
        parent::__construct(422, $mensagem, ['erros' => $erros]);
    }
}

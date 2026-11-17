<?php

declare(strict_types=1);

namespace App\Http\Erros;

/** Erro de negócio/HTTP convertido em application/problem+json. */
class ErroHttp extends \RuntimeException
{
    /** @param array<string,mixed> $extras */
    public function __construct(
        public readonly int $status,
        string $mensagem,
        public readonly array $extras = [],
    ) {
        parent::__construct($mensagem, $status);
    }
}

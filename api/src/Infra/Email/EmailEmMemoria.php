<?php

declare(strict_types=1);

namespace App\Infra\Email;

/** Enviador para testes: guarda as mensagens em memória. */
final class EmailEmMemoria implements EmailEnviador
{
    /** @var list<array{para:string,assunto:string,corpo:string}> */
    public array $enviados = [];

    public function enviar(string $para, string $assunto, string $corpo): void
    {
        $this->enviados[] = ['para' => $para, 'assunto' => $assunto, 'corpo' => $corpo];
    }
}

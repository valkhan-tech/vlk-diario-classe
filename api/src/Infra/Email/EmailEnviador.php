<?php

declare(strict_types=1);

namespace App\Infra\Email;

interface EmailEnviador
{
    public function enviar(string $para, string $assunto, string $corpo): void;
}

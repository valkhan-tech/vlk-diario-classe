<?php

declare(strict_types=1);

namespace App\Dominio\Acesso;

/** Regra de senha: mínimo de 8 caracteres, com letra e número. */
final class SenhaPolitica
{
    public const MENSAGEM = 'A senha deve ter pelo menos 8 caracteres, com letras e números.';

    public static function valida(string $senha): bool
    {
        return mb_strlen($senha) >= 8
            && preg_match('/\p{L}/u', $senha) === 1
            && preg_match('/\d/', $senha) === 1;
    }
}

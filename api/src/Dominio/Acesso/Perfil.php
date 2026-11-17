<?php

declare(strict_types=1);

namespace App\Dominio\Acesso;

enum Perfil: string
{
    case Admin = 'admin';
    case Professora = 'professora';
    case Responsavel = 'responsavel';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(static fn (self $p): string => $p->value, self::cases());
    }
}

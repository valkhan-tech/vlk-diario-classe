<?php

declare(strict_types=1);

namespace App\Dominio\Progresso;

enum StatusLicao: string
{
    case Pendente = 'pendente';
    case Aprovado = 'aprovado';
    case Reprovado = 'reprovado';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}

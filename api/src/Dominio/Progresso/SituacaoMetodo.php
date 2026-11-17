<?php

declare(strict_types=1);

namespace App\Dominio\Progresso;

enum SituacaoMetodo: string
{
    case Iniciado = 'iniciado';
    case Pausado = 'pausado';
    case Concluido = 'concluido';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}

<?php

declare(strict_types=1);

namespace App\Dominio\Alunos;

enum SituacaoAluno: string
{
    case Ativo = 'ativo';
    case Pausado = 'pausado';
    case Encerrado = 'encerrado';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}

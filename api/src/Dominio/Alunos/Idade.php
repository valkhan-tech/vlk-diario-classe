<?php

declare(strict_types=1);

namespace App\Dominio\Alunos;

use DateTimeImmutable;

/** Valores derivados de datas do aluno. */
final class Idade
{
    public static function anos(DateTimeImmutable $nascimento, DateTimeImmutable $hoje): int
    {
        return $nascimento->diff($hoje)->invert === 1 ? 0 : $nascimento->diff($hoje)->y;
    }

    /** Faixas da planilha original: Musicalização (0-7), Infantil (8-12), Adolescente (13-17), Adulto (18+). */
    public static function faixaEtaria(DateTimeImmutable $nascimento, DateTimeImmutable $hoje): string
    {
        $anos = self::anos($nascimento, $hoje);
        return match (true) {
            $anos <= 7 => 'Musicalização (0-7)',
            $anos <= 12 => 'Infantil (8-12)',
            $anos <= 17 => 'Adolescente (13-17)',
            default => 'Adulto (18+)',
        };
    }

    /** Meses completos desde o início (0 se ainda não começou). */
    public static function mesesAtivos(DateTimeImmutable $inicio, DateTimeImmutable $hoje): int
    {
        $d = $inicio->diff($hoje);
        return $d->invert === 1 ? 0 : $d->y * 12 + $d->m;
    }
}

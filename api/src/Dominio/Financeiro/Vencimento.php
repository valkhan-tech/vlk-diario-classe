<?php

declare(strict_types=1);

namespace App\Dominio\Financeiro;

use DateTimeImmutable;

final class Vencimento
{
    /** Dia fixo do aluno no mês; dias 29–31 em mês curto vencem no último dia do mês. */
    public static function para(int $ano, int $mes, int $dia): DateTimeImmutable
    {
        $primeiro = new DateTimeImmutable(sprintf('%04d-%02d-01', $ano, $mes));
        $ultimoDia = (int) $primeiro->format('t');
        return $primeiro->setDate($ano, $mes, min(max($dia, 1), $ultimoDia));
    }
}

<?php

declare(strict_types=1);

namespace App\Dominio\Aulas;

final class Frequencia
{
    /**
     * Percentual de presença: presentes ÷ (presentes + faltas do aluno), de 0 a 100, com uma casa decimal.
     * Faltas da professora e feriados/eventos não contam. Sem base de cálculo → null.
     *
     * @param list<string|Presenca> $presencas
     */
    public static function percentual(array $presencas): ?float
    {
        $presentes = 0;
        $base = 0;
        foreach ($presencas as $p) {
            $p = $p instanceof Presenca ? $p : Presenca::from($p);
            if (!$p->contaNaFrequencia()) {
                continue;
            }
            $base++;
            if ($p === Presenca::Presente) {
                $presentes++;
            }
        }
        return $base === 0 ? null : round($presentes / $base * 100, 1);
    }
}

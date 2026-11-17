<?php

declare(strict_types=1);

namespace App\Dominio\Progresso;

/**
 * Média de cada método = (lições − não realizadas) ÷ lições = aprovadas ÷ total.
 * Progresso geral = média ponderada simples das médias dos métodos associados (peso = nº de lições),
 * o que equivale a Σ aprovadas ÷ Σ lições. Método Pausado e método sem lições ficam de fora;
 * método Concluído vale 100%.
 */
final class ProgressoGeral
{
    /** Média do método em % (0–100, uma casa decimal); null se não há lições. */
    public static function mediaDoMetodo(int $totalLicoes, int $aprovadas, string $situacao): ?float
    {
        if ($totalLicoes <= 0) {
            return null;
        }
        $efetivas = $situacao === SituacaoMetodo::Concluido->value ? $totalLicoes : min($aprovadas, $totalLicoes);
        return round($efetivas / $totalLicoes * 100, 1);
    }

    /**
     * @param list<array{totalLicoes:int,aprovadas:int,situacao:string}> $metodos
     */
    public static function calcular(array $metodos): ?float
    {
        $soma = 0;
        $total = 0;
        foreach ($metodos as $m) {
            if ($m['situacao'] === SituacaoMetodo::Pausado->value || $m['totalLicoes'] <= 0) {
                continue;
            }
            $total += $m['totalLicoes'];
            $soma += $m['situacao'] === SituacaoMetodo::Concluido->value ? $m['totalLicoes'] : min($m['aprovadas'], $m['totalLicoes']);
        }
        return $total === 0 ? null : round($soma / $total * 100, 1);
    }
}

<?php

declare(strict_types=1);

namespace App\Dominio\Financeiro;

/** Conversões entre decimal do banco/JSON e centavos inteiros (o domínio só calcula em centavos). */
final class Dinheiro
{
    public static function centavos(string|int|float $valor): int
    {
        return (int) round(((float) $valor) * 100);
    }

    /** Formato do DECIMAL(12,2): "150.00". */
    public static function decimal(int $centavos): string
    {
        return number_format($centavos / 100, 2, '.', '');
    }

    public static function numero(int $centavos): float
    {
        return $centavos / 100;
    }
}

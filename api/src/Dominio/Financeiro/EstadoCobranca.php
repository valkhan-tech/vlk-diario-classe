<?php

declare(strict_types=1);

namespace App\Dominio\Financeiro;

use DateTimeImmutable;

/**
 * Estado derivado de uma cobrança. Atrasado = não quitada na data de vencimento
 * (vale também para pagamento parcial vencido).
 */
final class EstadoCobranca
{
    public const CANCELADA = 'cancelada';
    public const PAGO = 'pago';
    public const ATRASADO = 'atrasado';
    public const PARCIAL = 'parcial';
    public const PENDENTE = 'pendente';

    /** @return list<string> */
    public static function todos(): array
    {
        return [self::PENDENTE, self::PARCIAL, self::ATRASADO, self::PAGO, self::CANCELADA];
    }

    public static function calcular(
        string $situacao,
        int $valorCent,
        int $descontoCent,
        int $pagoCent,
        DateTimeImmutable $vencimento,
        DateTimeImmutable $hoje,
    ): string {
        if ($situacao === 'cancelada') {
            return self::CANCELADA;
        }
        if ($pagoCent >= $valorCent - $descontoCent) {
            return self::PAGO;
        }
        if ($vencimento->setTime(0, 0) < $hoje->setTime(0, 0)) {
            return self::ATRASADO;
        }
        return $pagoCent > 0 ? self::PARCIAL : self::PENDENTE;
    }
}

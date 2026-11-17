<?php

declare(strict_types=1);

namespace App\Infra\Auditoria;

/** Prepara o registro para o log: mascara segredos e remove colunas derivadas. */
final class Mascara
{
    public const SEGREDOS = ['senha_hash', 'token_hash'];
    public const DERIVADAS = ['sys_ativo'];

    /**
     * @param array<string,mixed> $registro
     * @return array<string,mixed>
     */
    public static function aplicar(array $registro): array
    {
        foreach (self::DERIVADAS as $coluna) {
            unset($registro[$coluna]);
        }
        foreach (self::SEGREDOS as $coluna) {
            if (array_key_exists($coluna, $registro)) {
                $registro[$coluna] = '***';
            }
        }
        return $registro;
    }
}

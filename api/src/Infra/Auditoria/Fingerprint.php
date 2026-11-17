<?php

declare(strict_types=1);

namespace App\Infra\Auditoria;

/**
 * Fingerprint de um registro: SHA-256 do JSON canônico
 * (chaves ordenadas recursivamente; escalares normalizados para string; null preservado).
 */
final class Fingerprint
{
    /** @param array<string,mixed> $registro */
    public static function canonico(array $registro): string
    {
        return json_encode(
            self::normalizar($registro),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    /** @param array<string,mixed> $registro */
    public static function calcular(array $registro): string
    {
        return hash('sha256', self::canonico($registro));
    }

    private static function normalizar(mixed $valor): mixed
    {
        if (is_array($valor)) {
            ksort($valor);
            return array_map(self::normalizar(...), $valor);
        }
        if ($valor === null) {
            return null;
        }
        if (is_bool($valor)) {
            return $valor ? '1' : '0';
        }
        return (string) $valor;
    }
}

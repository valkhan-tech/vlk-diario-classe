<?php

declare(strict_types=1);

namespace App\Infra;

/**
 * Leitura de configuração a partir de variáveis de ambiente (.env já carregado).
 * Permite sobrescritas explícitas (usadas nos testes).
 */
final class Config
{
    /** @param array<string,string> $sobrescritas */
    public function __construct(private readonly array $sobrescritas = [])
    {
    }

    public function get(string $chave, ?string $padrao = null): ?string
    {
        if (array_key_exists($chave, $this->sobrescritas)) {
            return $this->sobrescritas[$chave];
        }
        $valor = $_ENV[$chave] ?? $_SERVER[$chave] ?? getenv($chave);
        if ($valor === false || $valor === null || $valor === '') {
            return $padrao;
        }
        return (string) $valor;
    }

    public function obrigatorio(string $chave): string
    {
        $valor = $this->get($chave);
        if ($valor === null) {
            throw new \RuntimeException("Variável de ambiente obrigatória ausente: {$chave}");
        }
        return $valor;
    }

    public function int(string $chave, int $padrao): int
    {
        $valor = $this->get($chave);
        return $valor === null ? $padrao : (int) $valor;
    }

    public function ehProducao(): bool
    {
        return $this->get('APP_ENV', 'production') === 'production';
    }

    public function raizApi(): string
    {
        return dirname(__DIR__, 2);
    }
}

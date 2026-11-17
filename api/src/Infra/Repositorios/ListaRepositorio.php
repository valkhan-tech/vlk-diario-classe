<?php

declare(strict_types=1);

namespace App\Infra\Repositorios;

use App\Infra\Banco\RepositorioBase;

final class ListaRepositorio extends RepositorioBase
{
    public const TIPOS = ['instrumento', 'tipo_aula', 'forma_pagamento'];

    public function tabela(): string
    {
        return 'listas';
    }

    public function colunas(): array
    {
        return ['tipo', 'valor', 'ordem', 'ativo'];
    }

    /** @return list<array<string,mixed>> */
    public function porTipo(string $tipo, bool $apenasAtivos = false): array
    {
        $sql = 'SELECT * FROM listas WHERE tipo = ? AND sys_del IS NULL' . ($apenasAtivos ? ' AND ativo = 1' : '') . ' ORDER BY ordem, valor';
        return $this->db->consultar($sql, [$tipo]);
    }

    /** @return array<string,mixed>|null */
    public function buscarDoTipo(int $id, string $tipo): ?array
    {
        return $this->db->um('SELECT * FROM listas WHERE id = ? AND tipo = ? AND sys_del IS NULL', [$id, $tipo]);
    }

    public function existeValor(string $tipo, string $valor, ?int $exceto = null): bool
    {
        return (int) $this->db->escalar(
            'SELECT COUNT(*) FROM listas WHERE tipo = ? AND valor = ? AND sys_del IS NULL AND id <> ?',
            [$tipo, $valor, $exceto ?? 0]
        ) > 0;
    }
}

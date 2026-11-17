<?php

declare(strict_types=1);

namespace App\Infra\Repositorios;

use App\Infra\Banco\RepositorioBase;

final class LicaoRepositorio extends RepositorioBase
{
    public function tabela(): string
    {
        return 'licoes';
    }

    public function colunas(): array
    {
        return ['metodo_id', 'ordem', 'titulo', 'observacoes'];
    }

    /** @return list<array<string,mixed>> */
    public function doMetodo(int $metodoId): array
    {
        return $this->db->consultar(
            'SELECT * FROM licoes WHERE metodo_id = ? AND sys_del IS NULL ORDER BY ordem',
            [$metodoId]
        );
    }

    public function proximaOrdem(int $metodoId): int
    {
        return (int) $this->db->escalar('SELECT COALESCE(MAX(ordem), 0) + 1 FROM licoes WHERE metodo_id = ? AND sys_del IS NULL', [$metodoId]);
    }

    public function totalAvaliacoes(int $licaoId): int
    {
        return (int) $this->db->escalar('SELECT COUNT(*) FROM avaliacoes_licao WHERE licao_id = ? AND sys_del IS NULL', [$licaoId]);
    }

    /** @return list<array{metodo_id:int|string,total:int|string}> */
    public function totaisPorMetodo(): array
    {
        return $this->db->consultar('SELECT metodo_id, COUNT(*) AS total FROM licoes WHERE sys_del IS NULL GROUP BY metodo_id');
    }
}

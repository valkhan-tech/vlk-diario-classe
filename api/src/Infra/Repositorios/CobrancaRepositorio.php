<?php

declare(strict_types=1);

namespace App\Infra\Repositorios;

use App\Infra\Banco\RepositorioBase;

final class CobrancaRepositorio extends RepositorioBase
{
    private const SELECT = 'SELECT c.*, a.nome AS aluno, COALESCE(SUM(p.valor), 0) AS pago
                              FROM cobrancas c
                              JOIN alunos a ON a.id = c.aluno_id
                              LEFT JOIN pagamentos p ON p.cobranca_id = c.id AND p.sys_del IS NULL';

    public function tabela(): string
    {
        return 'cobrancas';
    }

    public function colunas(): array
    {
        return ['aluno_id', 'competencia', 'valor', 'desconto', 'vencimento', 'situacao', 'observacoes'];
    }

    /**
     * @param array{competencia?:?string,aluno_id?:?int,de?:?string,ate?:?string} $filtros competencia = AAAA-MM-01
     * @return list<array<string,mixed>> com 'pago' somado
     */
    public function listar(array $filtros = []): array
    {
        $onde = ['c.sys_del IS NULL'];
        $params = [];
        if (!empty($filtros['competencia'])) {
            $onde[] = 'c.competencia = ?';
            $params[] = $filtros['competencia'];
        }
        if (!empty($filtros['aluno_id'])) {
            $onde[] = 'c.aluno_id = ?';
            $params[] = (int) $filtros['aluno_id'];
        }
        if (!empty($filtros['de'])) {
            $onde[] = 'c.competencia >= ?';
            $params[] = $filtros['de'];
        }
        if (!empty($filtros['ate'])) {
            $onde[] = 'c.competencia <= ?';
            $params[] = $filtros['ate'];
        }
        return $this->db->consultar(
            self::SELECT . ' WHERE ' . implode(' AND ', $onde) . ' GROUP BY c.id ORDER BY c.competencia DESC, c.vencimento, a.nome',
            $params
        );
    }

    /** @return array<string,mixed>|null */
    public function buscarComPago(int $id): ?array
    {
        return $this->db->um(self::SELECT . ' WHERE c.id = ? AND c.sys_del IS NULL GROUP BY c.id', [$id]);
    }

    public function existe(int $alunoId, string $competencia): bool
    {
        return (int) $this->db->escalar(
            'SELECT COUNT(*) FROM cobrancas WHERE aluno_id = ? AND competencia = ? AND sys_del IS NULL',
            [$alunoId, $competencia]
        ) > 0;
    }
}

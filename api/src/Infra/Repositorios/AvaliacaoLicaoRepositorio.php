<?php

declare(strict_types=1);

namespace App\Infra\Repositorios;

use App\Infra\Banco\RepositorioBase;

/** Avaliações de lição: registro só de acréscimo (correção = exclusão lógica + novo registro). */
final class AvaliacaoLicaoRepositorio extends RepositorioBase
{
    public function tabela(): string
    {
        return 'avaliacoes_licao';
    }

    public function colunas(): array
    {
        return ['aluno_id', 'licao_id', 'status', 'data_tentativa', 'aula_id', 'observacoes'];
    }

    /**
     * Todas as avaliações vigentes (de um aluno, ou de todos), em ordem cronológica por lição.
     *
     * @return list<array<string,mixed>> aluno_id, licao_id, metodo_id, status, data_tentativa, id
     */
    public function vigentes(?int $alunoId = null): array
    {
        $sql = 'SELECT e.id, e.aluno_id, e.licao_id, l.metodo_id, e.status, e.data_tentativa
                  FROM avaliacoes_licao e
                  JOIN licoes l ON l.id = e.licao_id AND l.sys_del IS NULL
                 WHERE e.sys_del IS NULL';
        $params = [];
        if ($alunoId !== null) {
            $sql .= ' AND e.aluno_id = ?';
            $params[] = $alunoId;
        }
        return $this->db->consultar($sql . ' ORDER BY e.aluno_id, e.licao_id, e.data_tentativa, e.id', $params);
    }

    /** @return list<array<string,mixed>> tentativas de uma lição, em ordem cronológica */
    public function daLicao(int $alunoId, int $licaoId): array
    {
        return $this->db->consultar(
            'SELECT * FROM avaliacoes_licao WHERE aluno_id = ? AND licao_id = ? AND sys_del IS NULL ORDER BY data_tentativa, id',
            [$alunoId, $licaoId]
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Infra\Repositorios;

use App\Infra\Banco\RepositorioBase;

final class AulaRepositorio extends RepositorioBase
{
    private const SELECT = 'SELECT au.*, a.nome AS aluno, t.valor AS tipo_aula
                              FROM aulas au
                              JOIN alunos a ON a.id = au.aluno_id
                              LEFT JOIN listas t ON t.id = au.tipo_aula_id';

    public function tabela(): string
    {
        return 'aulas';
    }

    public function colunas(): array
    {
        return ['aluno_id', 'data', 'presenca', 'motivo', 'tipo_aula_id', 'metodo_id', 'conteudo', 'hino', 'licao_casa', 'video_url', 'observacoes'];
    }

    /**
     * @param array{aluno_id?:?int,de?:?string,ate?:?string,limite?:?int} $filtros
     * @return list<array<string,mixed>>
     */
    public function listar(array $filtros = []): array
    {
        $onde = ['au.sys_del IS NULL'];
        $params = [];
        if (!empty($filtros['aluno_id'])) {
            $onde[] = 'au.aluno_id = ?';
            $params[] = (int) $filtros['aluno_id'];
        }
        if (!empty($filtros['de'])) {
            $onde[] = 'au.data >= ?';
            $params[] = $filtros['de'];
        }
        if (!empty($filtros['ate'])) {
            $onde[] = 'au.data <= ?';
            $params[] = $filtros['ate'];
        }
        $limite = max(1, min(500, (int) ($filtros['limite'] ?? 200)));
        return $this->db->consultar(
            self::SELECT . ' WHERE ' . implode(' AND ', $onde) . ' ORDER BY au.data DESC, au.id DESC LIMIT ' . $limite,
            $params
        );
    }

    /** @return array<string,mixed>|null */
    public function buscarDetalhada(int $id): ?array
    {
        return $this->db->um(self::SELECT . ' WHERE au.id = ? AND au.sys_del IS NULL', [$id]);
    }

    /** @return array<string,mixed>|null última aula do aluno antes da data (para mostrar a lição de casa) */
    public function anteriorDoAluno(int $alunoId, string $antesDe): ?array
    {
        return $this->db->um(
            self::SELECT . ' WHERE au.aluno_id = ? AND au.data < ? AND au.sys_del IS NULL ORDER BY au.data DESC, au.id DESC LIMIT 1',
            [$alunoId, $antesDe]
        );
    }

    /** @return list<string> presenças do aluno no período (para frequência) */
    public function presencasDoAluno(int $alunoId, ?string $de = null, ?string $ate = null): array
    {
        $sql = 'SELECT presenca FROM aulas WHERE aluno_id = ? AND sys_del IS NULL';
        $params = [$alunoId];
        if ($de !== null) {
            $sql .= ' AND data >= ?';
            $params[] = $de;
        }
        if ($ate !== null) {
            $sql .= ' AND data <= ?';
            $params[] = $ate;
        }
        return array_column($this->db->consultar($sql, $params), 'presenca');
    }

    /** @return list<array<string,mixed>> */
    public function doPeriodo(string $de, string $ate): array
    {
        return $this->db->consultar(
            self::SELECT . ' WHERE au.data BETWEEN ? AND ? AND au.sys_del IS NULL ORDER BY au.data, a.nome',
            [$de, $ate]
        );
    }

    /** @return list<array<string,mixed>> */
    public function doDia(string $data): array
    {
        return $this->doPeriodo($data, $data);
    }
}

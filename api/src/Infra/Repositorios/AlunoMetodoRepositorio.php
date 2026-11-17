<?php

declare(strict_types=1);

namespace App\Infra\Repositorios;

use App\Infra\Banco\RepositorioBase;

final class AlunoMetodoRepositorio extends RepositorioBase
{
    private const SELECT = 'SELECT am.*, m.nome AS metodo, m.tipo, m.nivel,
                                   (SELECT COUNT(*) FROM licoes l WHERE l.metodo_id = m.id AND l.sys_del IS NULL) AS total_licoes
                              FROM aluno_metodo am
                              JOIN metodos m ON m.id = am.metodo_id AND m.sys_del IS NULL';

    public function tabela(): string
    {
        return 'aluno_metodo';
    }

    public function colunas(): array
    {
        return ['aluno_id', 'metodo_id', 'data_inicio', 'situacao', 'data_situacao', 'observacoes'];
    }

    /** @return list<array<string,mixed>> */
    public function doAluno(int $alunoId): array
    {
        return $this->db->consultar(self::SELECT . ' WHERE am.aluno_id = ? AND am.sys_del IS NULL ORDER BY m.tipo, m.nome', [$alunoId]);
    }

    /** @return list<array<string,mixed>> */
    public function todos(): array
    {
        return $this->db->consultar(self::SELECT . ' WHERE am.sys_del IS NULL');
    }

    /** @return array<string,mixed>|null */
    public function buscarVinculo(int $alunoId, int $metodoId): ?array
    {
        return $this->db->um(self::SELECT . ' WHERE am.aluno_id = ? AND am.metodo_id = ? AND am.sys_del IS NULL', [$alunoId, $metodoId]);
    }
}

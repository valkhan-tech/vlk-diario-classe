<?php

declare(strict_types=1);

namespace App\Infra\Repositorios;

use App\Infra\Banco\RepositorioBase;

/** Progresso informado pela professora: sobrescrita de estágio, aptidão (checkbox) e técnica. */
final class ProgressoAlunoRepositorio extends RepositorioBase
{
    public function tabela(): string
    {
        return 'progresso_aluno';
    }

    public function colunas(): array
    {
        return ['aluno_id', 'estagio_manual', 'apto_jovens', 'apto_adulto', 'apto_oficializacao', 'tecnica_m1', 'tecnica_m2', 'tecnica_ped'];
    }

    /** @return array<string,mixed>|null */
    public function doAluno(int $alunoId): ?array
    {
        return $this->db->um('SELECT * FROM progresso_aluno WHERE aluno_id = ? AND sys_del IS NULL', [$alunoId]);
    }

    /** @return list<array<string,mixed>> */
    public function todos(): array
    {
        return $this->db->consultar('SELECT * FROM progresso_aluno WHERE sys_del IS NULL');
    }
}

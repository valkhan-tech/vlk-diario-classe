<?php

declare(strict_types=1);

namespace App\Infra\Repositorios;

use App\Infra\Banco\RepositorioBase;

final class AlunoRepositorio extends RepositorioBase
{
    public function tabela(): string
    {
        return 'alunos';
    }

    public function colunas(): array
    {
        return [
            'nome', 'data_nascimento', 'instrumento_id', 'contato', 'responsavel_nome', 'dia_semana', 'hora_aula',
            'valor_mensalidade', 'dia_vencimento', 'data_inicio', 'situacao', 'observacoes',
        ];
    }

    /**
     * @param array{q?:?string,instrumento_id?:?int,situacao?:?string,incluir_encerrados?:bool} $filtros
     * @return list<array<string,mixed>>
     */
    public function listar(array $filtros = []): array
    {
        $onde = ['a.sys_del IS NULL'];
        $params = [];
        if (!empty($filtros['q'])) {
            $onde[] = 'a.nome LIKE ?';
            $params[] = '%' . $filtros['q'] . '%';
        }
        if (!empty($filtros['instrumento_id'])) {
            $onde[] = 'a.instrumento_id = ?';
            $params[] = (int) $filtros['instrumento_id'];
        }
        if (!empty($filtros['situacao'])) {
            $onde[] = 'a.situacao = ?';
            $params[] = $filtros['situacao'];
        } elseif (empty($filtros['incluir_encerrados'])) {
            $onde[] = "a.situacao <> 'encerrado'";
        }
        return $this->db->consultar(
            'SELECT a.*, l.valor AS instrumento FROM alunos a
               LEFT JOIN listas l ON l.id = a.instrumento_id
              WHERE ' . implode(' AND ', $onde) . ' ORDER BY a.nome',
            $params
        );
    }

    /** @return array<string,mixed>|null */
    public function buscarComInstrumento(int $id): ?array
    {
        return $this->db->um(
            'SELECT a.*, l.valor AS instrumento FROM alunos a
               LEFT JOIN listas l ON l.id = a.instrumento_id
              WHERE a.id = ? AND a.sys_del IS NULL',
            [$id]
        );
    }

    /** @return list<array<string,mixed>> alunos ativos (para geração de cobranças e agenda) */
    public function ativos(): array
    {
        return $this->db->consultar("SELECT * FROM alunos WHERE situacao = 'ativo' AND sys_del IS NULL ORDER BY nome");
    }
}

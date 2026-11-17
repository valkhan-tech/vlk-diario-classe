<?php

declare(strict_types=1);

namespace App\Infra\Repositorios;

use App\Infra\Banco\RepositorioBase;

final class MetodoRepositorio extends RepositorioBase
{
    private const SELECT = 'SELECT m.*, i.valor AS instrumento,
                                   (SELECT COUNT(*) FROM licoes l WHERE l.metodo_id = m.id AND l.sys_del IS NULL) AS total_licoes
                              FROM metodos m
                              LEFT JOIN listas i ON i.id = m.instrumento_id';

    public function tabela(): string
    {
        return 'metodos';
    }

    public function colunas(): array
    {
        return ['nome', 'tipo', 'nivel', 'instrumento_id', 'ativo', 'observacoes'];
    }

    /** @return list<array<string,mixed>> */
    public function listar(bool $apenasAtivos = false, ?string $tipo = null): array
    {
        $onde = ['m.sys_del IS NULL'];
        $params = [];
        if ($apenasAtivos) {
            $onde[] = 'm.ativo = 1';
        }
        if ($tipo !== null) {
            $onde[] = 'm.tipo = ?';
            $params[] = $tipo;
        }
        return $this->db->consultar(self::SELECT . ' WHERE ' . implode(' AND ', $onde) . ' ORDER BY m.tipo, m.nome', $params);
    }

    /** @return array<string,mixed>|null */
    public function buscarDetalhado(int $id): ?array
    {
        return $this->db->um(self::SELECT . ' WHERE m.id = ? AND m.sys_del IS NULL', [$id]);
    }

    public function existeNome(string $nome, ?int $exceto = null): bool
    {
        return (int) $this->db->escalar(
            'SELECT COUNT(*) FROM metodos WHERE nome = ? AND sys_del IS NULL AND id <> ?',
            [$nome, $exceto ?? 0]
        ) > 0;
    }

    public function totalAlunos(int $metodoId): int
    {
        return (int) $this->db->escalar('SELECT COUNT(*) FROM aluno_metodo WHERE metodo_id = ? AND sys_del IS NULL', [$metodoId]);
    }
}

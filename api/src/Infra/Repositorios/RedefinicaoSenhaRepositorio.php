<?php

declare(strict_types=1);

namespace App\Infra\Repositorios;

use App\Infra\Banco\Conexao;

/** Tabela técnica de tokens de recuperação de senha (guarda só o hash). */
final class RedefinicaoSenhaRepositorio
{
    public function __construct(private readonly Conexao $db)
    {
    }

    public function criar(int $usuarioId, string $tokenHash, int $validadeMinutos): void
    {
        $this->db->executar(
            'INSERT INTO redefinicoes_senha (usuario_id, token_hash, expira_em)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))',
            [$usuarioId, $tokenHash, $validadeMinutos]
        );
    }

    /** @return array<string,mixed>|null */
    public function buscarValida(string $tokenHash): ?array
    {
        return $this->db->um(
            'SELECT * FROM redefinicoes_senha WHERE token_hash = ? AND usada_em IS NULL AND expira_em > NOW()',
            [$tokenHash]
        );
    }

    public function marcarUsada(int $id): void
    {
        $this->db->executar('UPDATE redefinicoes_senha SET usada_em = NOW() WHERE id = ?', [$id]);
    }

    public function limparVencidas(): int
    {
        return $this->db->executar('DELETE FROM redefinicoes_senha WHERE expira_em <= NOW() OR usada_em IS NOT NULL');
    }
}

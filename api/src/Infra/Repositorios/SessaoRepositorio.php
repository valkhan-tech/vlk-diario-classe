<?php

declare(strict_types=1);

namespace App\Infra\Repositorios;

use App\Infra\Banco\Conexao;

/** Tabela técnica: sem sys_log, exclusão física das expiradas. */
final class SessaoRepositorio
{
    public function __construct(private readonly Conexao $db)
    {
    }

    public function criar(int $usuarioId, string $tokenHash, int $ttlMinutos, ?string $ip, ?string $ua): void
    {
        $this->db->executar(
            'INSERT INTO sessoes (usuario_id, token_hash, expira_em, ip, user_agent)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?, ?)',
            [$usuarioId, $tokenHash, $ttlMinutos, $ip, $ua !== null ? mb_substr($ua, 0, 255) : null]
        );
    }

    /** @return array<string,mixed>|null usuário dono da sessão válida */
    public function usuarioDaSessao(string $tokenHash): ?array
    {
        return $this->db->um(
            'SELECT u.* FROM sessoes s
               JOIN usuarios u ON u.id = s.usuario_id AND u.sys_del IS NULL AND u.ativo = 1
              WHERE s.token_hash = ? AND s.expira_em > NOW() AND s.sys_del IS NULL',
            [$tokenHash]
        );
    }

    public function renovar(string $tokenHash, int $ttlMinutos): void
    {
        $this->db->executar(
            'UPDATE sessoes SET expira_em = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE token_hash = ?',
            [$ttlMinutos, $tokenHash]
        );
    }

    public function excluirPorHash(string $tokenHash): void
    {
        $this->db->executar('DELETE FROM sessoes WHERE token_hash = ?', [$tokenHash]);
    }

    public function excluirDoUsuario(int $usuarioId): void
    {
        $this->db->executar('DELETE FROM sessoes WHERE usuario_id = ?', [$usuarioId]);
    }

    public function limparExpiradas(): int
    {
        return $this->db->executar('DELETE FROM sessoes WHERE expira_em <= NOW()');
    }
}

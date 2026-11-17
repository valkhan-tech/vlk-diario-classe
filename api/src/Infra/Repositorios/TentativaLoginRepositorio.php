<?php

declare(strict_types=1);

namespace App\Infra\Repositorios;

use App\Infra\Banco\Conexao;

/** Tabela técnica de tentativas de login (limite de força bruta). */
final class TentativaLoginRepositorio
{
    public function __construct(private readonly Conexao $db)
    {
    }

    public function registrar(string $email, string $ip, bool $sucesso): void
    {
        $this->db->executar(
            'INSERT INTO tentativas_login (email, ip, sucesso) VALUES (?, ?, ?)',
            [$email, $ip, $sucesso ? 1 : 0]
        );
    }

    /** Falhas por e-mail OU por ip na janela, desde o último sucesso daquele e-mail. */
    public function contarFalhas(string $email, string $ip, int $janelaMinutos): int
    {
        return (int) $this->db->escalar(
            'SELECT COUNT(*) FROM tentativas_login
              WHERE sucesso = 0 AND sys_cri > DATE_SUB(NOW(), INTERVAL ? MINUTE)
                AND (email = ? OR ip = ?)',
            [$janelaMinutos, $email, $ip]
        );
    }

    public function limparAntigas(int $dias): int
    {
        return $this->db->executar('DELETE FROM tentativas_login WHERE sys_cri < DATE_SUB(NOW(), INTERVAL ? DAY)', [$dias]);
    }
}

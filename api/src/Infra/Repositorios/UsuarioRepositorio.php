<?php

declare(strict_types=1);

namespace App\Infra\Repositorios;

use App\Infra\Banco\RepositorioBase;

final class UsuarioRepositorio extends RepositorioBase
{
    public function tabela(): string
    {
        return 'usuarios';
    }

    public function colunas(): array
    {
        return ['nome', 'email', 'senha_hash', 'perfil', 'ativo', 'troca_senha_obrigatoria', 'ultimo_login_em'];
    }

    /** @return array<string,mixed>|null */
    public function buscarPorEmail(string $email): ?array
    {
        return $this->db->um('SELECT * FROM usuarios WHERE email = ? AND sys_del IS NULL', [$email]);
    }

    /** @return list<array<string,mixed>> */
    public function listar(): array
    {
        return $this->db->consultar('SELECT * FROM usuarios WHERE sys_del IS NULL ORDER BY nome');
    }

    public function contarAdminsAtivos(?int $exceto = null): int
    {
        return (int) $this->db->escalar(
            "SELECT COUNT(*) FROM usuarios WHERE perfil = 'admin' AND ativo = 1 AND sys_del IS NULL AND id <> ?",
            [$exceto ?? 0]
        );
    }

    /** @return array<string,mixed> */
    public static function publico(array $usuario): array
    {
        unset($usuario['senha_hash'], $usuario['sys_ativo']);
        $usuario['id'] = (int) $usuario['id'];
        $usuario['ativo'] = (bool) $usuario['ativo'];
        $usuario['troca_senha_obrigatoria'] = (bool) $usuario['troca_senha_obrigatoria'];
        return $usuario;
    }
}

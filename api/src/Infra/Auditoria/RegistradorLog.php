<?php

declare(strict_types=1);

namespace App\Infra\Auditoria;

use App\Infra\Banco\Conexao;

/** Grava uma linha em sys_log (deve ser chamado dentro da transação da operação). */
class RegistradorLog
{
    public function __construct(
        private readonly Conexao $db,
        private readonly ContextoOperacao $contexto,
    ) {
    }

    /** @param array<string,mixed> $registro registro completo relido após a ação */
    public function registrar(string $operacao, string $tabela, int $id, array $registro): void
    {
        $mascarado = Mascara::aplicar($registro);
        $json = Fingerprint::canonico($mascarado);
        $this->db->executar(
            'INSERT INTO sys_log (usuario_id, usuario_perfil, operacao, tabela, registro_id, origem, controller,
                                  registro_json, fingerprint, ip, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $this->contexto->usuarioId,
                $this->contexto->perfil,
                $operacao,
                $tabela,
                $id,
                mb_substr($this->contexto->origem, 0, 255),
                $this->contexto->controller,
                $json,
                hash('sha256', $json),
                $this->contexto->ip,
                $this->contexto->userAgent !== null ? mb_substr($this->contexto->userAgent, 0, 255) : null,
            ]
        );
    }
}

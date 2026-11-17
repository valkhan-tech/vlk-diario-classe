<?php

declare(strict_types=1);

namespace App\Infra\Auditoria;

/** Contexto da operação corrente (quem, de onde); compartilhado durante a requisição ou o comando. */
final class ContextoOperacao
{
    public ?int $usuarioId = null;
    public ?string $perfil = null;
    public string $origem = 'sistema';
    public ?string $controller = null;
    public ?string $ip = null;
    public ?string $userAgent = null;

    public function definirSistema(string $origem): void
    {
        $this->usuarioId = null;
        $this->perfil = null;
        $this->origem = $origem;
        $this->controller = $origem;
        $this->ip = null;
        $this->userAgent = null;
    }
}

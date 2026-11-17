<?php

declare(strict_types=1);

namespace App\Http;

use App\Infra\Config;

/** Monta o cabeçalho Set-Cookie da sessão (HttpOnly, SameSite=Lax, Secure fora do ambiente local). */
final class CookieSessao
{
    public function __construct(private readonly Config $config)
    {
    }

    public function nome(): string
    {
        return $this->config->get('SESSION_COOKIE', 'ddc_sessao') ?? 'ddc_sessao';
    }

    public function criar(string $token, int $ttlMinutos): string
    {
        return $this->montar($token, $ttlMinutos * 60);
    }

    public function expirar(): string
    {
        return $this->montar('', 0);
    }

    private function montar(string $valor, int $maxAge): string
    {
        $seguro = str_starts_with($this->config->get('APP_URL', '') ?? '', 'https://');
        return sprintf(
            '%s=%s; Path=/; Max-Age=%d; HttpOnly; SameSite=Lax%s',
            $this->nome(),
            $valor,
            $maxAge,
            $seguro ? '; Secure' : ''
        );
    }
}

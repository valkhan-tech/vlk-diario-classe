<?php

declare(strict_types=1);

namespace App\Aplicacao\Acesso;

use App\Http\Erros\LimiteExcedido;
use App\Http\Erros\NaoAutenticado;
use App\Infra\Config;
use App\Infra\Repositorios\SessaoRepositorio;
use App\Infra\Repositorios\TentativaLoginRepositorio;
use App\Infra\Repositorios\UsuarioRepositorio;

final class AutenticacaoServico
{
    public const MAX_FALHAS = 5;
    public const JANELA_MINUTOS = 15;

    public function __construct(
        private readonly UsuarioRepositorio $usuarios,
        private readonly SessaoRepositorio $sessoes,
        private readonly TentativaLoginRepositorio $tentativas,
        private readonly Config $config,
    ) {
    }

    public function ttlMinutos(): int
    {
        return $this->config->int('SESSION_TTL_MINUTES', 720);
    }

    /** @return array{token:string,usuario:array<string,mixed>} */
    public function entrar(string $email, string $senha, ?string $ip, ?string $userAgent): array
    {
        $email = mb_strtolower(trim($email));
        $ip ??= '0.0.0.0';

        if ($this->tentativas->contarFalhas($email, $ip, self::JANELA_MINUTOS) >= self::MAX_FALHAS) {
            throw new LimiteExcedido();
        }

        $usuario = $this->usuarios->buscarPorEmail($email);
        // verifica sempre contra um hash, para não revelar por tempo se o e-mail existe
        $hash = $usuario['senha_hash'] ?? '$2y$12$usesomesillystringforsaleuLYVUi5VVhWqAdlEGLmYXkKt1iSyxWi';
        $senhaOk = password_verify($senha, $hash);

        if ($usuario === null || !$senhaOk || (int) $usuario['ativo'] !== 1) {
            $this->tentativas->registrar($email, $ip, false);
            throw new NaoAutenticado('E-mail ou senha inválidos.');
        }

        $this->tentativas->registrar($email, $ip, true);
        $token = bin2hex(random_bytes(32));
        $this->sessoes->criar((int) $usuario['id'], hash('sha256', $token), $this->ttlMinutos(), $ip, $userAgent);

        $dados = ['ultimo_login_em' => date('Y-m-d H:i:s')];
        if (password_needs_rehash($usuario['senha_hash'], PASSWORD_DEFAULT)) {
            $dados['senha_hash'] = password_hash($senha, PASSWORD_DEFAULT);
        }
        $usuario = $this->usuarios->atualizar((int) $usuario['id'], $dados);

        return ['token' => $token, 'usuario' => UsuarioRepositorio::publico($usuario)];
    }

    public function sair(string $token): void
    {
        $this->sessoes->excluirPorHash(hash('sha256', $token));
    }

    /** @return array<string,mixed>|null */
    public function usuarioDoToken(string $token): ?array
    {
        $hash = hash('sha256', $token);
        $usuario = $this->sessoes->usuarioDaSessao($hash);
        if ($usuario === null) {
            return null;
        }
        $this->sessoes->renovar($hash, $this->ttlMinutos());
        return $usuario;
    }
}

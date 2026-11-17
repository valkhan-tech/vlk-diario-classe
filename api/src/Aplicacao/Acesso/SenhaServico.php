<?php

declare(strict_types=1);

namespace App\Aplicacao\Acesso;

use App\Dominio\Acesso\SenhaPolitica;
use App\Http\Erros\ErroValidacao;
use App\Infra\Config;
use App\Infra\Email\EmailEnviador;
use App\Infra\Repositorios\RedefinicaoSenhaRepositorio;
use App\Infra\Repositorios\SessaoRepositorio;
use App\Infra\Repositorios\UsuarioRepositorio;

final class SenhaServico
{
    public const VALIDADE_MINUTOS = 60;

    public function __construct(
        private readonly UsuarioRepositorio $usuarios,
        private readonly RedefinicaoSenhaRepositorio $redefinicoes,
        private readonly SessaoRepositorio $sessoes,
        private readonly EmailEnviador $email,
        private readonly Config $config,
    ) {
    }

    /** Sempre "silencioso": não revela se o e-mail existe. */
    public function solicitarRecuperacao(string $email): void
    {
        $usuario = $this->usuarios->buscarPorEmail(mb_strtolower(trim($email)));
        if ($usuario === null || (int) $usuario['ativo'] !== 1) {
            return;
        }
        $token = bin2hex(random_bytes(32));
        $this->redefinicoes->criar((int) $usuario['id'], hash('sha256', $token), self::VALIDADE_MINUTOS);

        $url = rtrim($this->config->get('APP_URL', '') ?? '', '/') . '/redefinir-senha?token=' . $token;
        $this->email->enviar(
            (string) $usuario['email'],
            'Recuperação de senha — Diário de Classe',
            "Olá, {$usuario['nome']}.\n\nPara definir uma nova senha, acesse (válido por " . self::VALIDADE_MINUTOS
            . " minutos):\n{$url}\n\nSe não foi você, ignore esta mensagem."
        );
    }

    public function redefinir(string $token, string $novaSenha): void
    {
        $this->exigirSenhaValida($novaSenha, 'senha');
        $registro = $this->redefinicoes->buscarValida(hash('sha256', $token));
        if ($registro === null) {
            throw new ErroValidacao(['token' => 'Link inválido ou expirado.']);
        }
        $usuarioId = (int) $registro['usuario_id'];
        $this->usuarios->atualizar($usuarioId, [
            'senha_hash' => password_hash($novaSenha, PASSWORD_DEFAULT),
            'troca_senha_obrigatoria' => 0,
        ]);
        $this->redefinicoes->marcarUsada((int) $registro['id']);
        $this->sessoes->excluirDoUsuario($usuarioId);
    }

    public function trocar(int $usuarioId, string $atual, string $nova): void
    {
        $this->exigirSenhaValida($nova, 'nova');
        $usuario = $this->usuarios->obter($usuarioId);
        if (!password_verify($atual, (string) $usuario['senha_hash'])) {
            throw new ErroValidacao(['atual' => 'Senha atual incorreta.']);
        }
        $this->usuarios->atualizar($usuarioId, [
            'senha_hash' => password_hash($nova, PASSWORD_DEFAULT),
            'troca_senha_obrigatoria' => 0,
        ]);
    }

    private function exigirSenhaValida(string $senha, string $campo): void
    {
        if (!SenhaPolitica::valida($senha)) {
            throw new ErroValidacao([$campo => SenhaPolitica::MENSAGEM]);
        }
    }
}

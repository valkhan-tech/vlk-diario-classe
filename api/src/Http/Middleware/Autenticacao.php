<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Aplicacao\Acesso\AutenticacaoServico;
use App\Http\Erros\ErroHttp;
use App\Http\Erros\NaoAutenticado;
use App\Infra\Auditoria\ContextoOperacao;
use App\Infra\Config;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Exige sessão válida; coloca o usuário no atributo "usuario" e no contexto de auditoria. */
final class Autenticacao implements MiddlewareInterface
{
    /** Rotas liberadas mesmo com troca de senha obrigatória. */
    private const LIBERADAS_COM_TROCA = ['/api/v1/eu', '/api/v1/sessao', '/api/v1/senha/trocar'];

    public function __construct(
        private readonly AutenticacaoServico $autenticacao,
        private readonly ContextoOperacao $contexto,
        private readonly Config $config,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $request->getCookieParams()[$this->config->get('SESSION_COOKIE', 'ddc_sessao')] ?? null;
        if (!is_string($token) || $token === '') {
            throw new NaoAutenticado();
        }
        $usuario = $this->autenticacao->usuarioDoToken($token);
        if ($usuario === null) {
            throw new NaoAutenticado('Sessão inválida ou expirada.');
        }

        $this->contexto->usuarioId = (int) $usuario['id'];
        $this->contexto->perfil = (string) $usuario['perfil'];

        if ((int) $usuario['troca_senha_obrigatoria'] === 1
            && !in_array($request->getUri()->getPath(), self::LIBERADAS_COM_TROCA, true)) {
            throw new ErroHttp(403, 'É necessário trocar a senha antes de continuar.', ['codigo' => 'troca_senha_obrigatoria']);
        }

        return $handler->handle($request->withAttribute('usuario', $usuario)->withAttribute('token', $token));
    }
}

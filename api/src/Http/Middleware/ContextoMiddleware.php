<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Infra\Auditoria\ContextoOperacao;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Routing\RouteContext;

/** Preenche o contexto da operação (origem, controller, ip, user agent) para o sys_log. */
final class ContextoMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly ContextoOperacao $contexto)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $servidor = $request->getServerParams();
        $this->contexto->origem = $request->getMethod() . ' ' . $request->getUri()->getPath();
        $this->contexto->ip = isset($servidor['REMOTE_ADDR']) ? (string) $servidor['REMOTE_ADDR'] : null;
        $this->contexto->userAgent = $request->getHeaderLine('User-Agent') ?: null;

        $rota = RouteContext::fromRequest($request)->getRoute();
        $chamavel = $rota?->getCallable();
        $this->contexto->controller = is_string($chamavel) ? $chamavel : null;

        return $handler->handle($request);
    }
}

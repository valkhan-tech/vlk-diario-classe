<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Erros\Proibido;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Exige o cabeçalho X-Requested-With nas requisições que gravam (defesa extra além do SameSite). */
final class CsrfMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)
            && $request->getHeaderLine('X-Requested-With') !== 'XMLHttpRequest') {
            throw new Proibido('Cabeçalho de proteção CSRF ausente.');
        }
        return $handler->handle($request);
    }
}

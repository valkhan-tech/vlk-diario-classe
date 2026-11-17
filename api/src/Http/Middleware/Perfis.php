<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Erros\NaoAutenticado;
use App\Http\Erros\Proibido;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Restringe a rota a determinados perfis (deve vir depois de Autenticacao). */
final class Perfis implements MiddlewareInterface
{
    /** @param list<string> $permitidos */
    public function __construct(private readonly array $permitidos)
    {
    }

    public static function admin(): self
    {
        return new self(['admin']);
    }

    /** Operação do dia a dia: administrador e professora. */
    public static function operacao(): self
    {
        return new self(['admin', 'professora']);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $usuario = $request->getAttribute('usuario');
        if (!is_array($usuario)) {
            throw new NaoAutenticado();
        }
        if (!in_array($usuario['perfil'], $this->permitidos, true)) {
            throw new Proibido();
        }
        return $handler->handle($request);
    }
}

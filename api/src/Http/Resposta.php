<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ResponseInterface;

final class Resposta
{
    public static function json(ResponseInterface $resposta, mixed $dados, int $status = 200): ResponseInterface
    {
        $resposta->getBody()->write(
            json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)
        );
        return $resposta->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public static function vazia(ResponseInterface $resposta, int $status = 204): ResponseInterface
    {
        return $resposta->withStatus($status);
    }

    /** @param array<string,mixed> $extras */
    public static function problema(ResponseInterface $resposta, int $status, string $titulo, array $extras = []): ResponseInterface
    {
        $corpo = ['type' => 'about:blank', 'title' => $titulo, 'status' => $status] + $extras;
        $resposta->getBody()->write(json_encode($corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return $resposta->withStatus($status)->withHeader('Content-Type', 'application/problem+json; charset=utf-8');
    }
}

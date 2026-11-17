<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Erros\ErroHttp;
use App\Infra\Config;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;
use Slim\Psr7\Factory\ResponseFactory;
use Throwable;

/** Converte exceções em application/problem+json. */
final class TratadorErros
{
    public function __construct(private readonly LoggerInterface $logger, private readonly Config $config)
    {
    }

    public function __invoke(ServerRequestInterface $request, Throwable $e, bool $exibirDetalhes, bool $logar, bool $logarDetalhes): ResponseInterface
    {
        $resposta = (new ResponseFactory())->createResponse();

        if ($e instanceof ErroHttp) {
            return Resposta::problema($resposta, $e->status, $e->getMessage(), $e->extras);
        }
        if ($e instanceof HttpException) {
            $titulo = match ($e->getCode()) {
                404 => 'Recurso não encontrado.',
                405 => 'Método não permitido.',
                default => $e->getMessage(),
            };
            return Resposta::problema($resposta, $e->getCode(), $titulo);
        }

        $this->logger->error($e->getMessage(), ['excecao' => $e]);
        $extras = $this->config->ehProducao() ? [] : ['detail' => $e->getMessage(), 'trace' => explode("\n", $e->getTraceAsString())];
        return Resposta::problema($resposta, 500, 'Erro interno do servidor.', $extras);
    }
}

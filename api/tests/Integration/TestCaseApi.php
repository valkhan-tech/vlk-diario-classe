<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Kernel;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

/** Testes de ponta a ponta no nível HTTP (sem servidor): monta o Slim e chama handle(). */
abstract class TestCaseApi extends TestCaseBanco
{
    protected App $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = Kernel::app($this->sobrescritas());
        // Kernel::app cria um container novo; usamos o dele daqui para frente para compartilhar o contexto.
        $this->container = $this->app->getContainer();
        $this->db = $this->container->get(\App\Infra\Banco\Conexao::class);
    }

    /**
     * @param array<string,mixed>|null $json
     * @param array<string,string> $cookies
     * @param array<string,string> $cabecalhos
     */
    protected function requisicao(
        string $metodo,
        string $uri,
        ?array $json = null,
        array $cookies = [],
        array $cabecalhos = [],
        bool $csrf = true,
    ): ResponseInterface {
        $req = (new ServerRequestFactory())->createServerRequest($metodo, 'http://localhost' . $uri, ['REMOTE_ADDR' => '127.0.0.1']);
        if ($csrf && !in_array($metodo, ['GET', 'HEAD', 'OPTIONS'], true)) {
            $req = $req->withHeader('X-Requested-With', 'XMLHttpRequest');
        }
        foreach ($cabecalhos as $nome => $valor) {
            $req = $req->withHeader($nome, $valor);
        }
        if ($cookies !== []) {
            $req = $req->withCookieParams($cookies);
        }
        if ($json !== null) {
            $req = $req->withHeader('Content-Type', 'application/json')
                ->withBody((new StreamFactory())->createStream(json_encode($json, JSON_THROW_ON_ERROR)));
        }
        return $this->app->handle($req);
    }

    /** @return array<string,mixed> */
    protected function corpo(ResponseInterface $resposta): array
    {
        $resposta->getBody()->rewind();
        $texto = (string) $resposta->getBody();
        return $texto === '' ? [] : (json_decode($texto, true, 512, JSON_THROW_ON_ERROR) ?? []);
    }
}

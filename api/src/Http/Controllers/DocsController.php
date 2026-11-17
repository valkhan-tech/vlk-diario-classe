<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Erros\NaoEncontrado;
use App\Infra\Config;
use App\Infra\Docs\OpenApiGerador;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Documentação navegável (Scalar, auto-hospedado). Somente administrador. */
final class DocsController
{
    public function __construct(private readonly OpenApiGerador $gerador, private readonly Config $config)
    {
    }

    public function pagina(Request $request, Response $response): Response
    {
        $html = <<<'HTML'
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>API — Diário de Classe</title>
</head>
<body>
  <script id="api-reference" data-url="/api/openapi.json"
          data-configuration='{"theme":"default","layout":"modern","hideDownloadButton":false,"defaultOpenAllTags":false}'></script>
  <script src="/api/docs/scalar.js"></script>
</body>
</html>
HTML;
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');
    }

    public function script(Request $request, Response $response): Response
    {
        $arquivo = $this->config->raizApi() . '/resources/docs/scalar.js';
        if (!is_file($arquivo)) {
            throw new NaoEncontrado('Interface de documentação ausente.');
        }
        $response->getBody()->write((string) file_get_contents($arquivo));
        return $response->withHeader('Content-Type', 'application/javascript; charset=utf-8')
            ->withHeader('Cache-Control', 'private, max-age=3600');
    }

    public function openapi(Request $request, Response $response): Response
    {
        $response->getBody()->write($this->gerador->obter());
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');
    }
}

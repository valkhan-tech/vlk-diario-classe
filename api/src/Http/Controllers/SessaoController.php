<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Aplicacao\Acesso\AutenticacaoServico;
use App\Http\CookieSessao;
use App\Http\Resposta;
use App\Http\Validador;
use App\Infra\Repositorios\UsuarioRepositorio;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

final class SessaoController
{
    public function __construct(
        private readonly AutenticacaoServico $autenticacao,
        private readonly CookieSessao $cookie,
    ) {
    }

    #[OA\Post(
        path: '/api/v1/sessao',
        summary: 'Entrar (login)',
        description: 'Cria a sessão e devolve o cookie HttpOnly. Após 5 falhas em 15 minutos, bloqueia temporariamente (429).',
        tags: ['Acesso'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email', 'senha'],
            properties: [new OA\Property(property: 'email', type: 'string'), new OA\Property(property: 'senha', type: 'string')]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Usuário autenticado'),
            new OA\Response(response: 401, description: 'Credenciais inválidas'),
            new OA\Response(response: 429, description: 'Muitas tentativas'),
        ]
    )]
    public function entrar(Request $request, Response $response): Response
    {
        $dados = Validador::validar(Validador::corpo($request->getParsedBody()), [
            'email' => [v::stringType()->notEmpty(), 'Informe o e-mail.'],
            'senha' => [v::stringType()->notEmpty(), 'Informe a senha.'],
        ]);
        $servidor = $request->getServerParams();
        $resultado = $this->autenticacao->entrar(
            (string) $dados['email'],
            (string) $request->getParsedBody()['senha'],
            isset($servidor['REMOTE_ADDR']) ? (string) $servidor['REMOTE_ADDR'] : null,
            $request->getHeaderLine('User-Agent') ?: null
        );
        $response = Resposta::json($response, ['usuario' => $resultado['usuario']]);
        return $response->withAddedHeader('Set-Cookie', $this->cookie->criar($resultado['token'], $this->autenticacao->ttlMinutos()));
    }

    #[OA\Delete(
        path: '/api/v1/sessao',
        summary: 'Sair (logout)',
        tags: ['Acesso'],
        responses: [new OA\Response(response: 204, description: 'Sessão encerrada')]
    )]
    public function sair(Request $request, Response $response): Response
    {
        $this->autenticacao->sair((string) $request->getAttribute('token'));
        return Resposta::vazia($response)->withAddedHeader('Set-Cookie', $this->cookie->expirar());
    }

    #[OA\Get(
        path: '/api/v1/eu',
        summary: 'Usuário da sessão atual',
        tags: ['Acesso'],
        responses: [new OA\Response(response: 200, description: 'Usuário'), new OA\Response(response: 401, description: 'Sem sessão')]
    )]
    public function eu(Request $request, Response $response): Response
    {
        /** @var array<string,mixed> $usuario */
        $usuario = $request->getAttribute('usuario');
        return Resposta::json($response, ['usuario' => UsuarioRepositorio::publico($usuario)]);
    }
}

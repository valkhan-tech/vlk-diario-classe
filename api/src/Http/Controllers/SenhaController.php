<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Aplicacao\Acesso\SenhaServico;
use App\Http\Resposta;
use App\Http\Validador;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

final class SenhaController
{
    public function __construct(private readonly SenhaServico $senhas)
    {
    }

    #[OA\Post(
        path: '/api/v1/senha/recuperar',
        summary: 'Solicitar recuperação de senha por e-mail',
        description: 'Responde sempre 204, exista ou não o e-mail (não revela cadastro).',
        tags: ['Acesso'],
        responses: [new OA\Response(response: 204, description: 'Solicitação registrada')]
    )]
    public function recuperar(Request $request, Response $response): Response
    {
        $dados = Validador::validar(Validador::corpo($request->getParsedBody()), [
            'email' => [v::stringType()->notEmpty(), 'Informe o e-mail.'],
        ]);
        $this->senhas->solicitarRecuperacao((string) $dados['email']);
        return Resposta::vazia($response);
    }

    #[OA\Post(
        path: '/api/v1/senha/redefinir',
        summary: 'Definir nova senha com o token recebido por e-mail',
        tags: ['Acesso'],
        responses: [new OA\Response(response: 204, description: 'Senha redefinida'), new OA\Response(response: 422, description: 'Token inválido ou senha fraca')]
    )]
    public function redefinir(Request $request, Response $response): Response
    {
        $corpo = Validador::corpo($request->getParsedBody());
        Validador::validar($corpo, [
            'token' => [v::stringType()->notEmpty(), 'Informe o token.'],
            'senha' => [v::stringType()->notEmpty(), 'Informe a nova senha.'],
        ]);
        $this->senhas->redefinir((string) $corpo['token'], (string) $corpo['senha']);
        return Resposta::vazia($response);
    }

    #[OA\Post(
        path: '/api/v1/senha/trocar',
        summary: 'Trocar a própria senha (exige a senha atual)',
        tags: ['Acesso'],
        responses: [new OA\Response(response: 204, description: 'Senha trocada'), new OA\Response(response: 422, description: 'Senha atual incorreta ou nova senha fraca')]
    )]
    public function trocar(Request $request, Response $response): Response
    {
        $corpo = Validador::corpo($request->getParsedBody());
        Validador::validar($corpo, [
            'atual' => [v::stringType()->notEmpty(), 'Informe a senha atual.'],
            'nova' => [v::stringType()->notEmpty(), 'Informe a nova senha.'],
        ]);
        /** @var array<string,mixed> $usuario */
        $usuario = $request->getAttribute('usuario');
        $this->senhas->trocar((int) $usuario['id'], (string) $corpo['atual'], (string) $corpo['nova']);
        return Resposta::vazia($response);
    }
}

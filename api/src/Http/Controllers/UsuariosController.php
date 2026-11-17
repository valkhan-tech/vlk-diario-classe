<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Aplicacao\Acesso\UsuarioServico;
use App\Dominio\Acesso\Perfil;
use App\Http\Resposta;
use App\Http\Validador;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

final class UsuariosController
{
    public function __construct(private readonly UsuarioServico $usuarios)
    {
    }

    #[OA\Get(path: '/api/v1/usuarios', summary: 'Listar usuários (administrador)', tags: ['Usuários'],
        responses: [new OA\Response(response: 200, description: 'Lista')])]
    public function listar(Request $request, Response $response): Response
    {
        return Resposta::json($response, ['dados' => $this->usuarios->listar()]);
    }

    #[OA\Get(path: '/api/v1/usuarios/{id}', summary: 'Obter usuário (administrador)', tags: ['Usuários'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Usuário'), new OA\Response(response: 404, description: 'Não encontrado')])]
    public function obter(Request $request, Response $response, array $args): Response
    {
        return Resposta::json($response, ['dados' => $this->usuarios->obter((int) $args['id'])]);
    }

    #[OA\Post(path: '/api/v1/usuarios', summary: 'Criar usuário com senha definida pelo administrador', tags: ['Usuários'],
        description: 'O usuário será obrigado a trocar a senha no primeiro acesso.',
        responses: [new OA\Response(response: 201, description: 'Criado'), new OA\Response(response: 409, description: 'E-mail já cadastrado'), new OA\Response(response: 422, description: 'Dados inválidos')])]
    public function criar(Request $request, Response $response): Response
    {
        $dados = Validador::validar(Validador::corpo($request->getParsedBody()), $this->regras());
        $dados['senha'] = (string) (Validador::corpo($request->getParsedBody())['senha'] ?? '');
        return Resposta::json($response, ['dados' => $this->usuarios->criar($dados)], 201);
    }

    #[OA\Put(path: '/api/v1/usuarios/{id}', summary: 'Atualizar nome, e-mail, perfil e situação', tags: ['Usuários'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Atualizado'), new OA\Response(response: 409, description: 'Conflito')])]
    public function atualizar(Request $request, Response $response, array $args): Response
    {
        $regras = $this->regras();
        unset($regras['senha']);
        $regras['ativo'] = [v::boolType(), 'Informe se o usuário está ativo.'];
        $dados = Validador::validar(Validador::corpo($request->getParsedBody()), $regras, true);
        /** @var array<string,mixed> $logado */
        $logado = $request->getAttribute('usuario');
        return Resposta::json($response, ['dados' => $this->usuarios->atualizar((int) $args['id'], $dados, (int) $logado['id'])]);
    }

    #[OA\Post(path: '/api/v1/usuarios/{id}/senha', summary: 'Definir a senha de um usuário (administrador)', tags: ['Usuários'],
        description: 'Encerra as sessões do usuário e exige troca de senha no próximo acesso.',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: 'Senha definida'), new OA\Response(response: 422, description: 'Senha fraca')])]
    public function definirSenha(Request $request, Response $response, array $args): Response
    {
        $corpo = Validador::corpo($request->getParsedBody());
        Validador::validar($corpo, ['senha' => [v::stringType()->notEmpty(), 'Informe a senha.']]);
        $this->usuarios->definirSenha((int) $args['id'], (string) $corpo['senha']);
        return Resposta::vazia($response);
    }

    #[OA\Delete(path: '/api/v1/usuarios/{id}', summary: 'Excluir usuário (exclusão lógica)', tags: ['Usuários'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: 'Excluído'), new OA\Response(response: 409, description: 'Último administrador')])]
    public function excluir(Request $request, Response $response, array $args): Response
    {
        /** @var array<string,mixed> $logado */
        $logado = $request->getAttribute('usuario');
        $this->usuarios->excluir((int) $args['id'], (int) $logado['id']);
        return Resposta::vazia($response);
    }

    /** @return array<string,array{0:\Respect\Validation\Validatable,1:string}> */
    private function regras(): array
    {
        return [
            'nome' => [v::stringType()->length(1, 150), 'Informe o nome (até 150 caracteres).'],
            'email' => [v::stringType()->email()->length(1, 190), 'Informe um e-mail válido.'],
            'perfil' => [v::in(Perfil::valores(), true), 'Perfil inválido.'],
            'senha' => [v::stringType()->notEmpty(), 'Informe a senha.'],
        ];
    }
}

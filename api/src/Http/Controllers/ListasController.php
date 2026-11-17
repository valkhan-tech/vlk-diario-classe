<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Aplicacao\Cadastros\ListaServico;
use App\Http\Resposta;
use App\Http\Validador;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

final class ListasController
{
    private const PARAM_TIPO = ['instrumento', 'tipo_aula', 'forma_pagamento'];

    public function __construct(private readonly ListaServico $listas)
    {
    }

    #[OA\Get(path: '/api/v1/listas/{tipo}', summary: 'Listar itens de uma lista', tags: ['Listas'],
        parameters: [
            new OA\Parameter(name: 'tipo', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: self::PARAM_TIPO)),
            new OA\Parameter(name: 'ativos', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [new OA\Response(response: 200, description: 'Itens da lista')])]
    public function listar(Request $request, Response $response, array $args): Response
    {
        $ativos = ($request->getQueryParams()['ativos'] ?? '') === '1';
        return Resposta::json($response, ['dados' => $this->listas->listar((string) $args['tipo'], $ativos)]);
    }

    #[OA\Post(path: '/api/v1/listas/{tipo}', summary: 'Criar item de lista', tags: ['Listas'],
        parameters: [new OA\Parameter(name: 'tipo', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: self::PARAM_TIPO))],
        responses: [new OA\Response(response: 201, description: 'Criado'), new OA\Response(response: 422, description: 'Dados inválidos')])]
    public function criar(Request $request, Response $response, array $args): Response
    {
        $dados = Validador::validar(Validador::corpo($request->getParsedBody()), $this->regras());
        return Resposta::json($response, ['dados' => $this->listas->criar((string) $args['tipo'], $dados)], 201);
    }

    #[OA\Put(path: '/api/v1/listas/{tipo}/{id}', summary: 'Atualizar item de lista', tags: ['Listas'],
        parameters: [
            new OA\Parameter(name: 'tipo', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: self::PARAM_TIPO)),
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Atualizado')])]
    public function atualizar(Request $request, Response $response, array $args): Response
    {
        $dados = Validador::validar(Validador::corpo($request->getParsedBody()), $this->regras(), true);
        return Resposta::json($response, ['dados' => $this->listas->atualizar((string) $args['tipo'], (int) $args['id'], $dados)]);
    }

    #[OA\Delete(path: '/api/v1/listas/{tipo}/{id}', summary: 'Excluir item de lista (bloqueado se estiver em uso)', tags: ['Listas'],
        parameters: [
            new OA\Parameter(name: 'tipo', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: self::PARAM_TIPO)),
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 204, description: 'Excluído'), new OA\Response(response: 409, description: 'Em uso')])]
    public function excluir(Request $request, Response $response, array $args): Response
    {
        $this->listas->excluir((string) $args['tipo'], (int) $args['id']);
        return Resposta::vazia($response);
    }

    /** @return array<string,array{0:\Respect\Validation\Validatable,1:string}> */
    private function regras(): array
    {
        return [
            'valor' => [v::stringType()->length(1, 100), 'Informe o nome (até 100 caracteres).'],
            'ordem' => [v::optional(v::intVal()), 'A ordem deve ser um número inteiro.'],
            'ativo' => [v::optional(v::boolType()), 'Informe se o item está ativo.'],
        ];
    }
}

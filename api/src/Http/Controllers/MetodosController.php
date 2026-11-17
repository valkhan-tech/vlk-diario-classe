<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Aplicacao\Progresso\MetodoServico;
use App\Dominio\Progresso\Nivel;
use App\Http\Erros\ErroValidacao;
use App\Http\Resposta;
use App\Http\Validador;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

final class MetodosController
{
    public function __construct(private readonly MetodoServico $metodos)
    {
    }

    #[OA\Get(path: '/api/v1/metodos', summary: 'Listar métodos e hinários', tags: ['Métodos e lições'],
        parameters: [
            new OA\Parameter(name: 'ativos', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'tipo', in: 'query', schema: new OA\Schema(type: 'string', enum: ['metodo', 'hinario'])),
        ],
        responses: [new OA\Response(response: 200, description: 'Métodos com total de lições')])]
    public function listar(Request $request, Response $response): Response
    {
        $p = $request->getQueryParams();
        $tipo = in_array($p['tipo'] ?? null, ['metodo', 'hinario'], true) ? (string) $p['tipo'] : null;
        return Resposta::json($response, ['dados' => $this->metodos->listar(($p['ativos'] ?? '') === '1', $tipo)]);
    }

    #[OA\Get(path: '/api/v1/metodos/{id}', summary: 'Obter método', tags: ['Métodos e lições'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Método'), new OA\Response(response: 404, description: 'Não encontrado')])]
    public function obter(Request $request, Response $response, array $args): Response
    {
        return Resposta::json($response, ['dados' => $this->metodos->obter((int) $args['id'])]);
    }

    #[OA\Post(path: '/api/v1/metodos', summary: 'Cadastrar método ou hinário', tags: ['Métodos e lições'],
        description: 'O nível (iniciante, básico, intermediário, avançado) dá o nome do estágio calculado dos alunos.',
        responses: [new OA\Response(response: 201, description: 'Criado'), new OA\Response(response: 422, description: 'Dados inválidos')])]
    public function criar(Request $request, Response $response): Response
    {
        $dados = Validador::validar(Validador::corpo($request->getParsedBody()), $this->regras());
        return Resposta::json($response, ['dados' => $this->metodos->criar($dados)], 201);
    }

    #[OA\Put(path: '/api/v1/metodos/{id}', summary: 'Atualizar método', tags: ['Métodos e lições'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Atualizado')])]
    public function atualizar(Request $request, Response $response, array $args): Response
    {
        $dados = Validador::validar(Validador::corpo($request->getParsedBody()), $this->regras(), true);
        return Resposta::json($response, ['dados' => $this->metodos->atualizar((int) $args['id'], $dados)]);
    }

    #[OA\Delete(path: '/api/v1/metodos/{id}', summary: 'Excluir método (bloqueado se houver alunos associados)', tags: ['Métodos e lições'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: 'Excluído'), new OA\Response(response: 409, description: 'Em uso')])]
    public function excluir(Request $request, Response $response, array $args): Response
    {
        $this->metodos->excluir((int) $args['id']);
        return Resposta::vazia($response);
    }

    #[OA\Get(path: '/api/v1/metodos/{id}/licoes', summary: 'Listar lições do método, em ordem', tags: ['Métodos e lições'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Lições')])]
    public function licoes(Request $request, Response $response, array $args): Response
    {
        return Resposta::json($response, ['dados' => $this->metodos->licoes((int) $args['id'])]);
    }

    #[OA\Post(path: '/api/v1/metodos/{id}/licoes', summary: 'Cadastrar uma lição', tags: ['Métodos e lições'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 201, description: 'Criada')])]
    public function criarLicao(Request $request, Response $response, array $args): Response
    {
        $dados = Validador::validar(Validador::corpo($request->getParsedBody()), [
            'titulo' => [v::stringType()->length(1, 255), 'Informe o título (até 255 caracteres).'],
            'ordem' => [v::optional(v::intVal()->positive()), 'Posição inválida.'],
            'observacoes' => [v::optional(v::stringType()), 'Observações inválidas.'],
        ]);
        return Resposta::json($response, ['dados' => $this->metodos->criarLicao((int) $args['id'], $dados)], 201);
    }

    #[OA\Post(path: '/api/v1/metodos/{id}/licoes/lote', summary: 'Cadastrar lições em lote (colar uma lista, uma por linha)', tags: ['Métodos e lições'],
        description: 'Cada linha não vazia vira uma lição, em sequência, depois das existentes. Tudo ou nada.',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 201, description: 'Quantidade criada')])]
    public function lote(Request $request, Response $response, array $args): Response
    {
        $corpo = Validador::corpo($request->getParsedBody());
        Validador::validar($corpo, ['texto' => [v::stringType()->notEmpty(), 'Cole a lista de lições.']]);
        return Resposta::json($response, ['dados' => $this->metodos->colarLista((int) $args['id'], (string) $corpo['texto'])], 201);
    }

    #[OA\Put(path: '/api/v1/metodos/{id}/licoes/ordem', summary: 'Reordenar as lições (lista completa de ids na nova ordem)', tags: ['Métodos e lições'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Lições reordenadas')])]
    public function reordenar(Request $request, Response $response, array $args): Response
    {
        $ids = Validador::corpo($request->getParsedBody())['ids'] ?? null;
        if (!is_array($ids) || array_filter($ids, static fn (mixed $i): bool => !is_int($i)) !== []) {
            throw new ErroValidacao(['ids' => 'Informe a lista de ids das lições.']);
        }
        return Resposta::json($response, ['dados' => $this->metodos->reordenar((int) $args['id'], array_values($ids))]);
    }

    #[OA\Put(path: '/api/v1/licoes/{id}', summary: 'Atualizar título/observações da lição', tags: ['Métodos e lições'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Atualizada')])]
    public function atualizarLicao(Request $request, Response $response, array $args): Response
    {
        $dados = Validador::validar(Validador::corpo($request->getParsedBody()), [
            'titulo' => [v::stringType()->length(1, 255), 'Informe o título (até 255 caracteres).'],
            'observacoes' => [v::optional(v::stringType()), 'Observações inválidas.'],
        ], true);
        return Resposta::json($response, ['dados' => $this->metodos->atualizarLicao((int) $args['id'], $dados)]);
    }

    #[OA\Delete(path: '/api/v1/licoes/{id}', summary: 'Excluir lição (bloqueado se houver avaliações)', tags: ['Métodos e lições'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: 'Excluída'), new OA\Response(response: 409, description: 'Possui avaliações')])]
    public function excluirLicao(Request $request, Response $response, array $args): Response
    {
        $this->metodos->excluirLicao((int) $args['id']);
        return Resposta::vazia($response);
    }

    /** @return array<string,array{0:\Respect\Validation\Validatable,1:string}> */
    private function regras(): array
    {
        return [
            'nome' => [v::stringType()->length(1, 150), 'Informe o nome (até 150 caracteres).'],
            'tipo' => [v::optional(v::in(['metodo', 'hinario'], true)), 'Tipo inválido.'],
            'nivel' => [v::optional(v::in(Nivel::valores(), true)), 'Nível inválido.'],
            'instrumento_id' => [v::optional(v::intVal()), 'Instrumento inválido.'],
            'ativo' => [v::optional(v::boolType()), 'Informe se o método está ativo.'],
            'observacoes' => [v::optional(v::stringType()), 'Observações inválidas.'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Aplicacao\Cadastros\AlunoServico;
use App\Aplicacao\Progresso\ProgressoServico;
use App\Dominio\Alunos\SituacaoAluno;
use App\Http\Resposta;
use App\Http\Validador;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

final class AlunosController
{
    public function __construct(private readonly AlunoServico $alunos, private readonly ProgressoServico $progresso)
    {
    }

    #[OA\Get(path: '/api/v1/alunos', summary: 'Listar alunos', tags: ['Alunos'],
        description: 'Encerrados ficam ocultos por padrão (use incluir_encerrados=1 ou situacao=encerrado).',
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', description: 'Busca por nome', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'instrumento_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'situacao', in: 'query', schema: new OA\Schema(type: 'string', enum: ['ativo', 'pausado', 'encerrado'])),
            new OA\Parameter(name: 'incluir_encerrados', in: 'query', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [new OA\Response(response: 200, description: 'Lista de alunos')])]
    public function listar(Request $request, Response $response): Response
    {
        $p = $request->getQueryParams();
        $filtros = [
            'q' => isset($p['q']) ? trim((string) $p['q']) : null,
            'instrumento_id' => isset($p['instrumento_id']) ? (int) $p['instrumento_id'] : null,
            'situacao' => in_array($p['situacao'] ?? null, SituacaoAluno::valores(), true) ? $p['situacao'] : null,
            'incluir_encerrados' => ($p['incluir_encerrados'] ?? '') === '1',
        ];
        $lista = $this->alunos->listar($filtros);
        $resumos = $this->progresso->resumoDosAlunos();
        foreach ($lista as &$a) {
            $r = $resumos[$a['id']] ?? null;
            $a['estagio'] = $r['estagio'] ?? ['valor' => null, 'origem' => null, 'rotulo' => null];
            $a['progresso_geral'] = $r['progresso_geral'] ?? null;
        }
        unset($a);
        return Resposta::json($response, ['dados' => $lista]);
    }

    #[OA\Get(path: '/api/v1/alunos/{id}', summary: 'Obter aluno', tags: ['Alunos'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Aluno'), new OA\Response(response: 404, description: 'Não encontrado')])]
    public function obter(Request $request, Response $response, array $args): Response
    {
        return Resposta::json($response, ['dados' => $this->alunos->obter((int) $args['id'])]);
    }

    #[OA\Post(path: '/api/v1/alunos', summary: 'Cadastrar aluno', tags: ['Alunos'],
        responses: [new OA\Response(response: 201, description: 'Criado'), new OA\Response(response: 422, description: 'Dados inválidos')])]
    public function criar(Request $request, Response $response): Response
    {
        $dados = Validador::validar(Validador::corpo($request->getParsedBody()), $this->regras());
        return Resposta::json($response, ['dados' => $this->alunos->criar($dados)], 201);
    }

    #[OA\Put(path: '/api/v1/alunos/{id}', summary: 'Atualizar aluno (inclui mudar a situação)', tags: ['Alunos'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Atualizado')])]
    public function atualizar(Request $request, Response $response, array $args): Response
    {
        $dados = Validador::validar(Validador::corpo($request->getParsedBody()), $this->regras(true), true);
        return Resposta::json($response, ['dados' => $this->alunos->atualizar((int) $args['id'], $dados)]);
    }

    #[OA\Delete(path: '/api/v1/alunos/{id}', summary: 'Excluir aluno (exclusão lógica)', tags: ['Alunos'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: 'Excluído')])]
    public function excluir(Request $request, Response $response, array $args): Response
    {
        $this->alunos->excluir((int) $args['id']);
        return Resposta::vazia($response);
    }

    /** @return array<string,array{0:\Respect\Validation\Validatable,1:string}> */
    private function regras(bool $comSituacao = false): array
    {
        $regras = [
            'nome' => [v::stringType()->length(1, 150), 'Informe o nome (até 150 caracteres).'],
            'data_nascimento' => [v::optional(v::date('Y-m-d')), 'Data de nascimento inválida.'],
            'instrumento_id' => [v::optional(v::intVal()), 'Instrumento inválido.'],
            'contato' => [v::optional(v::stringType()->length(0, 150)), 'Contato muito longo.'],
            'responsavel_nome' => [v::optional(v::stringType()->length(0, 150)), 'Nome do responsável muito longo.'],
            'dia_semana' => [v::optional(v::intVal()->between(0, 6)), 'Dia da semana inválido (0 a 6).'],
            'hora_aula' => [v::optional(v::regex('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/')), 'Hora inválida (HH:MM).'],
            'valor_mensalidade' => [v::numericVal()->min(0), 'Informe a mensalidade (0 ou mais).'],
            'dia_vencimento' => [v::intVal()->between(1, 31), 'O dia de vencimento deve ser de 1 a 31.'],
            'data_inicio' => [v::optional(v::date('Y-m-d')), 'Data de início inválida.'],
            'observacoes' => [v::optional(v::stringType()), 'Observações inválidas.'],
        ];
        if ($comSituacao) {
            $regras['situacao'] = [v::in(SituacaoAluno::valores(), true), 'Situação inválida.'];
        }
        return $regras;
    }
}

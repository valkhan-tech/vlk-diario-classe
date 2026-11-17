<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Aplicacao\Aulas\AgendaServico;
use App\Aplicacao\Aulas\AulaServico;
use App\Dominio\Aulas\Presenca;
use App\Http\Erros\ErroValidacao;
use App\Http\Resposta;
use App\Http\Validador;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

final class AulasController
{
    public function __construct(private readonly AulaServico $aulas, private readonly AgendaServico $agenda)
    {
    }

    #[OA\Get(path: '/api/v1/aulas', summary: 'Listar aulas', tags: ['Aulas'],
        parameters: [
            new OA\Parameter(name: 'aluno_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'de', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'ate', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [new OA\Response(response: 200, description: 'Aulas (mais recentes primeiro)')])]
    public function listar(Request $request, Response $response): Response
    {
        $p = $request->getQueryParams();
        return Resposta::json($response, ['dados' => $this->aulas->listar([
            'aluno_id' => isset($p['aluno_id']) ? (int) $p['aluno_id'] : null,
            'de' => $this->data($p['de'] ?? null),
            'ate' => $this->data($p['ate'] ?? null),
            'limite' => isset($p['limite']) ? (int) $p['limite'] : null,
        ])]);
    }

    #[OA\Get(path: '/api/v1/aulas/{id}', summary: 'Obter aula', tags: ['Aulas'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Aula'), new OA\Response(response: 404, description: 'Não encontrada')])]
    public function obter(Request $request, Response $response, array $args): Response
    {
        return Resposta::json($response, ['dados' => $this->aulas->obter((int) $args['id'])]);
    }

    #[OA\Post(path: '/api/v1/aulas', summary: 'Lançar aula', tags: ['Aulas'],
        description: 'Presença não gera progresso; a avaliação de lições é feita à parte (entrega de progresso).',
        responses: [new OA\Response(response: 201, description: 'Criada'), new OA\Response(response: 422, description: 'Dados inválidos')])]
    public function criar(Request $request, Response $response): Response
    {
        $dados = Validador::validar(Validador::corpo($request->getParsedBody()), $this->regras());
        return Resposta::json($response, ['dados' => $this->aulas->criar($dados)], 201);
    }

    #[OA\Put(path: '/api/v1/aulas/{id}', summary: 'Corrigir aula lançada', tags: ['Aulas'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Atualizada')])]
    public function atualizar(Request $request, Response $response, array $args): Response
    {
        $dados = Validador::validar(Validador::corpo($request->getParsedBody()), $this->regras(), true);
        return Resposta::json($response, ['dados' => $this->aulas->atualizar((int) $args['id'], $dados)]);
    }

    #[OA\Delete(path: '/api/v1/aulas/{id}', summary: 'Excluir aula (exclusão lógica)', tags: ['Aulas'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: 'Excluída')])]
    public function excluir(Request $request, Response $response, array $args): Response
    {
        $this->aulas->excluir((int) $args['id']);
        return Resposta::vazia($response);
    }

    #[OA\Get(path: '/api/v1/alunos/{id}/aulas', summary: 'Histórico de aulas do aluno e frequência', tags: ['Aulas'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Aulas e resumo de frequência')])]
    public function doAluno(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        return Resposta::json($response, [
            'dados' => $this->aulas->listar(['aluno_id' => $id]),
            'resumo' => $this->aulas->resumoDoAluno($id),
        ]);
    }

    #[OA\Get(path: '/api/v1/alunos/{id}/aulas/anterior', summary: 'Aula anterior à data (mostra a lição de casa)', tags: ['Aulas'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'antes', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [new OA\Response(response: 200, description: 'Aula anterior ou null')])]
    public function anterior(Request $request, Response $response, array $args): Response
    {
        $antes = $this->data($request->getQueryParams()['antes'] ?? null);
        if ($antes === null) {
            throw new ErroValidacao(['antes' => 'Informe a data (AAAA-MM-DD).']);
        }
        return Resposta::json($response, ['dados' => $this->aulas->anterior((int) $args['id'], $antes)]);
    }

    #[OA\Get(path: '/api/v1/calendario', summary: 'Calendário mensal de aulas', tags: ['Aulas'],
        parameters: [new OA\Parameter(name: 'mes', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: '2026-11'))],
        responses: [new OA\Response(response: 200, description: 'Aulas por dia e resumo do mês')])]
    public function calendario(Request $request, Response $response): Response
    {
        $mes = (string) ($request->getQueryParams()['mes'] ?? '');
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes) !== 1) {
            throw new ErroValidacao(['mes' => 'Informe o mês no formato AAAA-MM.']);
        }
        return Resposta::json($response, ['dados' => $this->agenda->calendario($mes)]);
    }

    #[OA\Get(path: '/api/v1/agenda/hoje', summary: 'Aulas de hoje pela agenda semanal dos alunos ativos', tags: ['Aulas'],
        parameters: [new OA\Parameter(name: 'data', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date'))],
        responses: [new OA\Response(response: 200, description: 'Agenda do dia')])]
    public function agendaHoje(Request $request, Response $response): Response
    {
        return Resposta::json($response, ['dados' => $this->agenda->doDia($this->data($request->getQueryParams()['data'] ?? null))]);
    }

    private function data(mixed $valor): ?string
    {
        return is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1 ? $valor : null;
    }

    /** @return array<string,array{0:\Respect\Validation\Validatable,1:string}> */
    private function regras(): array
    {
        return [
            'aluno_id' => [v::intVal()->positive(), 'Informe o aluno.'],
            'data' => [v::date('Y-m-d'), 'Informe a data da aula.'],
            'presenca' => [v::in(Presenca::valores(), true), 'Presença inválida.'],
            'motivo' => [v::optional(v::stringType()->length(0, 255)), 'Motivo muito longo.'],
            'tipo_aula_id' => [v::optional(v::intVal()), 'Tipo de aula inválido.'],
            'metodo_id' => [v::optional(v::intVal()), 'Método inválido.'],
            'conteudo' => [v::optional(v::stringType()), 'Conteúdo inválido.'],
            'hino' => [v::optional(v::stringType()->length(0, 150)), 'Hino muito longo.'],
            'licao_casa' => [v::optional(v::stringType()), 'Lição de casa inválida.'],
            'video_url' => [v::optional(v::stringType()->length(0, 255)), 'Link muito longo.'],
            'observacoes' => [v::optional(v::stringType()), 'Observações inválidas.'],
        ];
    }
}

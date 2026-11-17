<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Aplicacao\Progresso\ProgressoServico;
use App\Dominio\Progresso\Estagio;
use App\Dominio\Progresso\SituacaoMetodo;
use App\Dominio\Progresso\StatusLicao;
use App\Http\Erros\ErroValidacao;
use App\Http\Resposta;
use App\Http\Validador;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

final class ProgressoController
{
    private const TECNICA = ['nao_iniciado', 'em_andamento', 'concluido'];

    public function __construct(private readonly ProgressoServico $progresso)
    {
    }

    #[OA\Get(path: '/api/v1/alunos/{id}/ficha-progresso', summary: 'Ficha de progresso do aluno', tags: ['Progresso'],
        description: 'Estágio (calculado × manual × efetivo), progresso geral %, aptidão, técnica, métodos associados (com lição atual) e frequência.',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Ficha de progresso')])]
    public function ficha(Request $request, Response $response, array $args): Response
    {
        return Resposta::json($response, ['dados' => $this->progresso->ficha((int) $args['id'])]);
    }

    #[OA\Get(path: '/api/v1/alunos/{id}/metodos/{metodoId}/licoes', summary: 'Lições do método com o status do aluno', tags: ['Progresso'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'metodoId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Lições com status, data do status, tentativas e tempo na lição')])]
    public function licoes(Request $request, Response $response, array $args): Response
    {
        return Resposta::json($response, ['dados' => $this->progresso->licoesDoMetodo((int) $args['id'], (int) $args['metodoId'])]);
    }

    #[OA\Post(path: '/api/v1/alunos/{id}/metodos', summary: 'Associar método ao aluno (situação Iniciado)', tags: ['Progresso'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 201, description: 'Ficha atualizada'), new OA\Response(response: 409, description: 'Já associado')])]
    public function associar(Request $request, Response $response, array $args): Response
    {
        $dados = Validador::validar(Validador::corpo($request->getParsedBody()), [
            'metodo_id' => [v::intVal()->positive(), 'Escolha o método.'],
            'data_inicio' => [v::optional(v::date('Y-m-d')), 'Data inválida.'],
        ]);
        return Resposta::json($response, ['dados' => $this->progresso->associar((int) $args['id'], (int) $dados['metodo_id'], $dados['data_inicio'] ?? null)], 201);
    }

    #[OA\Put(path: '/api/v1/alunos/{id}/metodos/{metodoId}', summary: 'Mudar a situação do método (iniciado, pausado, concluído)', tags: ['Progresso'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'metodoId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Ficha atualizada')])]
    public function situacao(Request $request, Response $response, array $args): Response
    {
        $dados = Validador::validar(Validador::corpo($request->getParsedBody()), [
            'situacao' => [v::in(SituacaoMetodo::valores(), true), 'Situação inválida.'],
            'data_situacao' => [v::optional(v::date('Y-m-d')), 'Data inválida.'],
        ]);
        return Resposta::json($response, ['dados' => $this->progresso->alterarSituacao((int) $args['id'], (int) $args['metodoId'], (string) $dados['situacao'], $dados['data_situacao'] ?? null)]);
    }

    #[OA\Delete(path: '/api/v1/alunos/{id}/metodos/{metodoId}', summary: 'Remover a associação do método (exclusão lógica; avaliações são mantidas)', tags: ['Progresso'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'metodoId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Ficha atualizada')])]
    public function desassociar(Request $request, Response $response, array $args): Response
    {
        return Resposta::json($response, ['dados' => $this->progresso->desassociar((int) $args['id'], (int) $args['metodoId'])]);
    }

    #[OA\Post(path: '/api/v1/alunos/{id}/avaliacoes', summary: 'Avaliar lições (Pendente / Aprovado / Reprovado) com a data da tentativa', tags: ['Progresso'],
        description: 'Cada avaliação é um novo registro (histórico só de acréscimo). A sequência é orientação: lição fora de ordem é aceita e sinalizada em fora_de_ordem. Método ainda não associado é associado automaticamente.',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 201, description: 'Resultado por lição'), new OA\Response(response: 422, description: 'Dados inválidos')])]
    public function avaliar(Request $request, Response $response, array $args): Response
    {
        $corpo = Validador::corpo($request->getParsedBody());
        $lista = $corpo['avaliacoes'] ?? null;
        if (!is_array($lista) || $lista === []) {
            throw new ErroValidacao(['avaliacoes' => 'Informe ao menos uma lição.']);
        }
        $itens = [];
        foreach ($lista as $i => $item) {
            $itens[] = Validador::validar(is_array($item) ? $item : [], [
                'licao_id' => [v::intVal()->positive(), "Lição inválida (item {$i})."],
                'status' => [v::in(StatusLicao::valores(), true), "Status inválido (item {$i})."],
                'data_tentativa' => [v::optional(v::date('Y-m-d')), "Data inválida (item {$i})."],
                'observacoes' => [v::optional(v::stringType()), "Observações inválidas (item {$i})."],
            ]);
        }
        $cabecalho = Validador::validar($corpo, [
            'data' => [v::optional(v::date('Y-m-d')), 'Data inválida.'],
            'aula_id' => [v::optional(v::intVal()), 'Aula inválida.'],
        ]);
        return Resposta::json($response, ['dados' => $this->progresso->avaliar(
            (int) $args['id'], $itens, $cabecalho['data'] ?? null, isset($cabecalho['aula_id']) ? (int) $cabecalho['aula_id'] : null
        )], 201);
    }

    #[OA\Get(path: '/api/v1/alunos/{id}/licoes/{licaoId}/avaliacoes', summary: 'Histórico de tentativas de uma lição', tags: ['Progresso'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'licaoId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Tentativas em ordem cronológica')])]
    public function historico(Request $request, Response $response, array $args): Response
    {
        return Resposta::json($response, ['dados' => $this->progresso->historicoDaLicao((int) $args['id'], (int) $args['licaoId'])]);
    }

    #[OA\Delete(path: '/api/v1/avaliacoes/{id}', summary: 'Excluir avaliação lançada por engano (exclusão lógica, fica no log)', tags: ['Progresso'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: 'Excluída')])]
    public function excluirAvaliacao(Request $request, Response $response, array $args): Response
    {
        $this->progresso->excluirAvaliacao((int) $args['id']);
        return Resposta::vazia($response);
    }

    #[OA\Put(path: '/api/v1/alunos/{id}/progresso', summary: 'Definir estágio manual, aptidão para culto (checkbox) e técnica', tags: ['Progresso'],
        description: 'estagio_manual = null volta a usar o estágio calculado. Aptidão é decisão da professora, sem regra rígida.',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Ficha atualizada')])]
    public function atualizar(Request $request, Response $response, array $args): Response
    {
        $dados = Validador::validar(Validador::corpo($request->getParsedBody()), [
            'estagio_manual' => [v::optional(v::in(Estagio::valores(), true)), 'Estágio inválido.'],
            'apto_jovens' => [v::boolType(), 'Valor inválido.'],
            'apto_adulto' => [v::boolType(), 'Valor inválido.'],
            'apto_oficializacao' => [v::boolType(), 'Valor inválido.'],
            'tecnica_m1' => [v::in(self::TECNICA, true), 'Situação da técnica inválida.'],
            'tecnica_m2' => [v::in(self::TECNICA, true), 'Situação da técnica inválida.'],
            'tecnica_ped' => [v::in(self::TECNICA, true), 'Situação da técnica inválida.'],
        ], true);
        if (array_key_exists('estagio_manual', $dados) && $dados['estagio_manual'] === '') {
            $dados['estagio_manual'] = null;
        }
        return Resposta::json($response, ['dados' => $this->progresso->atualizarProgresso((int) $args['id'], $dados)]);
    }
}

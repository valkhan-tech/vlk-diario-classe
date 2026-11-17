<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Aplicacao\Financeiro\CobrancaServico;
use App\Aplicacao\Financeiro\GeracaoCobrancas;
use App\Aplicacao\Financeiro\PagamentoServico;
use App\Dominio\Financeiro\EstadoCobranca;
use App\Http\Erros\ErroValidacao;
use App\Http\Resposta;
use App\Http\Validador;
use DateTimeImmutable;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

final class CobrancasController
{
    public function __construct(
        private readonly CobrancaServico $cobrancas,
        private readonly PagamentoServico $pagamentos,
        private readonly GeracaoCobrancas $geracao,
    ) {
    }

    #[OA\Get(path: '/api/v1/cobrancas', summary: 'Listar cobranças com estado derivado e resumo', tags: ['Financeiro'],
        description: 'Estado: pendente, parcial, atrasado (não quitada após o vencimento), pago ou cancelada.',
        parameters: [
            new OA\Parameter(name: 'competencia', in: 'query', description: 'AAAA-MM', schema: new OA\Schema(type: 'string', example: '2026-11')),
            new OA\Parameter(name: 'aluno_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'estado', in: 'query', schema: new OA\Schema(type: 'string', enum: ['pendente', 'parcial', 'atrasado', 'pago', 'cancelada'])),
        ],
        responses: [new OA\Response(response: 200, description: 'Cobranças e resumo')])]
    public function listar(Request $request, Response $response): Response
    {
        $p = $request->getQueryParams();
        $competencia = isset($p['competencia']) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $p['competencia']) === 1 ? (string) $p['competencia'] : null;
        $estado = in_array($p['estado'] ?? null, EstadoCobranca::todos(), true) ? (string) $p['estado'] : null;
        $r = $this->cobrancas->listar([
            'competencia' => $competencia,
            'aluno_id' => isset($p['aluno_id']) ? (int) $p['aluno_id'] : null,
            'estado' => $estado,
        ]);
        return Resposta::json($response, $r);
    }

    #[OA\Get(path: '/api/v1/cobrancas/{id}', summary: 'Obter cobrança com pagamentos', tags: ['Financeiro'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Cobrança'), new OA\Response(response: 404, description: 'Não encontrada')])]
    public function obter(Request $request, Response $response, array $args): Response
    {
        return Resposta::json($response, ['dados' => $this->cobrancas->obter((int) $args['id'])]);
    }

    #[OA\Put(path: '/api/v1/cobrancas/{id}', summary: 'Ajustar valor, desconto, vencimento, observações ou cancelar', tags: ['Financeiro'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Atualizada')])]
    public function atualizar(Request $request, Response $response, array $args): Response
    {
        $dados = Validador::validar(Validador::corpo($request->getParsedBody()), [
            'valor' => [v::numericVal()->min(0), 'Valor inválido.'],
            'desconto' => [v::numericVal()->min(0), 'Desconto inválido.'],
            'vencimento' => [v::date('Y-m-d'), 'Vencimento inválido.'],
            'situacao' => [v::in(['aberta', 'cancelada'], true), 'Situação inválida.'],
            'observacoes' => [v::optional(v::stringType()), 'Observações inválidas.'],
        ], true);
        return Resposta::json($response, ['dados' => $this->cobrancas->atualizar((int) $args['id'], $dados)]);
    }

    #[OA\Post(path: '/api/v1/cobrancas/gerar', summary: 'Gerar as cobranças de uma competência (idempotente)', tags: ['Financeiro'],
        description: 'Só alunos ativos, sem proporcional. Rodar de novo não duplica. Também executado diariamente pelo cron.',
        responses: [new OA\Response(response: 200, description: 'Quantidade criada e já existente')])]
    public function gerar(Request $request, Response $response): Response
    {
        $corpo = Validador::corpo($request->getParsedBody());
        $competencia = (string) ($corpo['competencia'] ?? '');
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $competencia) !== 1) {
            throw new ErroValidacao(['competencia' => 'Informe a competência no formato AAAA-MM.']);
        }
        return Resposta::json($response, ['dados' => $this->geracao->gerar(new DateTimeImmutable($competencia . '-01'))]);
    }

    #[OA\Post(path: '/api/v1/cobrancas/{id}/pagamentos', summary: 'Registrar pagamento (baixa total ou parcial)', tags: ['Financeiro'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 201, description: 'Cobrança atualizada'), new OA\Response(response: 422, description: 'Valor acima do saldo')])]
    public function pagar(Request $request, Response $response, array $args): Response
    {
        $dados = Validador::validar(Validador::corpo($request->getParsedBody()), [
            'valor' => [v::numericVal()->positive(), 'Informe um valor maior que zero.'],
            'data_pagamento' => [v::date('Y-m-d'), 'Informe a data do pagamento.'],
            'forma_pagamento_id' => [v::optional(v::intVal()), 'Forma de pagamento inválida.'],
            'observacoes' => [v::optional(v::stringType()), 'Observações inválidas.'],
        ]);
        return Resposta::json($response, ['dados' => $this->pagamentos->registrar((int) $args['id'], $dados)], 201);
    }

    #[OA\Delete(path: '/api/v1/pagamentos/{id}', summary: 'Estornar pagamento (exclusão lógica)', tags: ['Financeiro'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Cobrança atualizada')])]
    public function estornar(Request $request, Response $response, array $args): Response
    {
        return Resposta::json($response, ['dados' => $this->pagamentos->estornar((int) $args['id'])]);
    }

    #[OA\Get(path: '/api/v1/alunos/{id}/ficha-financeira', summary: 'Ficha financeira do aluno', tags: ['Financeiro'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Cobranças, pagamentos e totais')])]
    public function fichaFinanceira(Request $request, Response $response, array $args): Response
    {
        return Resposta::json($response, ['dados' => $this->cobrancas->fichaFinanceira((int) $args['id'])]);
    }
}

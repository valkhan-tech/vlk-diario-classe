<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Aplicacao\Financeiro\DashboardServico;
use App\Aplicacao\Financeiro\ExportacaoServico;
use App\Http\Erros\NaoEncontrado;
use App\Http\Resposta;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class DashboardController
{
    public function __construct(private readonly DashboardServico $dashboard, private readonly ExportacaoServico $exportacao)
    {
    }

    #[OA\Get(path: '/api/v1/dashboards/financeiro', summary: 'Dashboard financeiro do período', tags: ['Financeiro'],
        description: 'Previsto × recebido por competência, inadimplência, formas de pagamento, envelhecimento do atraso e ranking. Período máximo de 24 meses.',
        parameters: [
            new OA\Parameter(name: 'de', in: 'query', description: 'AAAA-MM (padrão: 5 meses atrás)', schema: new OA\Schema(type: 'string', example: '2026-06')),
            new OA\Parameter(name: 'ate', in: 'query', description: 'AAAA-MM (padrão: mês atual)', schema: new OA\Schema(type: 'string', example: '2026-11')),
        ],
        responses: [new OA\Response(response: 200, description: 'Indicadores agregados'), new OA\Response(response: 422, description: 'Período inválido')])]
    public function financeiro(Request $request, Response $response): Response
    {
        $p = $request->getQueryParams();
        $de = (string) ($p['de'] ?? date('Y-m', strtotime('-5 months')));
        $ate = (string) ($p['ate'] ?? date('Y-m'));
        return Resposta::json($response, ['dados' => $this->dashboard->resumo($de, $ate)]);
    }

    #[OA\Get(path: '/api/v1/exportacoes/{tipo}.xlsx', summary: 'Exportar planilha Excel financeira', tags: ['Financeiro'],
        description: 'Planilhas formatadas, com fórmulas de total e gráficos. Tipos: cobrancas, resumo-mensal, inadimplencia, recebimentos-forma, ficha-financeira (requer aluno_id).',
        parameters: [
            new OA\Parameter(name: 'tipo', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['cobrancas', 'resumo-mensal', 'inadimplencia', 'recebimentos-forma', 'ficha-financeira'])),
            new OA\Parameter(name: 'de', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'ate', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'aluno_id', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Arquivo .xlsx')])]
    public function exportar(Request $request, Response $response, array $args): Response
    {
        $tipo = (string) $args['tipo'];
        if (!in_array($tipo, ExportacaoServico::TIPOS, true)) {
            throw new NaoEncontrado('Tipo de exportação desconhecido.');
        }
        $p = $request->getQueryParams();
        [$nome, $conteudo] = $this->exportacao->gerar($tipo, [
            'de' => isset($p['de']) ? (string) $p['de'] : null,
            'ate' => isset($p['ate']) ? (string) $p['ate'] : null,
            'aluno_id' => isset($p['aluno_id']) ? (int) $p['aluno_id'] : null,
        ]);
        $response->getBody()->write($conteudo);
        return $response
            ->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $nome . '"')
            ->withHeader('Cache-Control', 'no-store');
    }
}

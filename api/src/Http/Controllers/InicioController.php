<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Aplicacao\Auditoria\AuditoriaServico;
use App\Aplicacao\Inicio\InicioServico;
use App\Http\Resposta;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class InicioController
{
    public function __construct(private readonly InicioServico $inicio, private readonly AuditoriaServico $auditoria)
    {
    }

    #[OA\Get(path: '/api/v1/inicio', summary: 'Painel inicial (KPIs, agenda do dia, estágios)', tags: ['Início'],
        responses: [new OA\Response(response: 200, description: 'Indicadores')])]
    public function painel(Request $request, Response $response): Response
    {
        return Resposta::json($response, ['dados' => $this->inicio->painel()]);
    }

    #[OA\Get(path: '/api/v1/auditoria', summary: 'Consultar o log de auditoria (administrador)', tags: ['Auditoria'],
        description: 'Filtros por tabela, registro, usuário, operação e período. Pagina de 50 em 50.',
        parameters: [
            new OA\Parameter(name: 'tabela', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'registro_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'usuario_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'operacao', in: 'query', schema: new OA\Schema(type: 'string', enum: ['INSERT', 'UPDATE', 'DELETE'])),
            new OA\Parameter(name: 'de', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'ate', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'pagina', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Entradas do log')])]
    public function listarAuditoria(Request $request, Response $response): Response
    {
        $p = $request->getQueryParams();
        $data = static fn (mixed $v): ?string => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1 ? $v : null;
        return Resposta::json($response, $this->auditoria->listar([
            'tabela' => isset($p['tabela']) && preg_match('/^[a-z_]{1,64}$/', (string) $p['tabela']) === 1 ? (string) $p['tabela'] : null,
            'registro_id' => isset($p['registro_id']) ? (int) $p['registro_id'] : null,
            'usuario_id' => isset($p['usuario_id']) ? (int) $p['usuario_id'] : null,
            'operacao' => isset($p['operacao']) ? (string) $p['operacao'] : null,
            'de' => $data($p['de'] ?? null),
            'ate' => $data($p['ate'] ?? null),
            'pagina' => isset($p['pagina']) ? (int) $p['pagina'] : null,
        ]));
    }

    #[OA\Get(path: '/api/v1/auditoria/{id}', summary: 'Entrada do log com o JSON do registro (administrador)', tags: ['Auditoria'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Entrada'), new OA\Response(response: 404, description: 'Não encontrada')])]
    public function obterAuditoria(Request $request, Response $response, array $args): Response
    {
        return Resposta::json($response, ['dados' => $this->auditoria->obter((int) $args['id'])]);
    }

    #[OA\Get(path: '/api/v1/auditoria/{id}/verificar', summary: 'Verificar o fingerprint da entrada (administrador)', tags: ['Auditoria'],
        description: 'Recalcula o SHA-256 do JSON gravado e, se for a última entrada do registro, compara com a linha atual da tabela (acusa alteração fora do sistema).',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Resultado da verificação')])]
    public function verificarAuditoria(Request $request, Response $response, array $args): Response
    {
        return Resposta::json($response, ['dados' => $this->auditoria->verificar((int) $args['id'])]);
    }
}

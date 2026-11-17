<?php

declare(strict_types=1);

use App\Http\Controllers\AlunosController;
use App\Http\Controllers\AulasController;
use App\Http\Controllers\CobrancasController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\MetodosController;
use App\Http\Controllers\ProgressoController;
use App\Http\Controllers\ListasController;
use App\Http\Controllers\SenhaController;
use App\Http\Controllers\SessaoController;
use App\Http\Controllers\UsuariosController;
use App\Http\Middleware\Autenticacao;
use App\Http\Middleware\Perfis;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return static function (App $app): void {
    $app->group('/v1', function (RouteCollectorProxy $v1): void {
        // Públicas
        $v1->get('/health', HealthController::class . ':verificar');
        $v1->post('/sessao', SessaoController::class . ':entrar');
        $v1->post('/senha/recuperar', SenhaController::class . ':recuperar');
        $v1->post('/senha/redefinir', SenhaController::class . ':redefinir');

        // Autenticadas
        $v1->group('', function (RouteCollectorProxy $auth): void {
            $auth->delete('/sessao', SessaoController::class . ':sair');
            $auth->get('/eu', SessaoController::class . ':eu');
            $auth->post('/senha/trocar', SenhaController::class . ':trocar');

            // Somente administrador
            $auth->group('/usuarios', function (RouteCollectorProxy $g): void {
                $g->get('', UsuariosController::class . ':listar');
                $g->post('', UsuariosController::class . ':criar');
                $g->get('/{id:[0-9]+}', UsuariosController::class . ':obter');
                $g->put('/{id:[0-9]+}', UsuariosController::class . ':atualizar');
                $g->post('/{id:[0-9]+}/senha', UsuariosController::class . ':definirSenha');
                $g->delete('/{id:[0-9]+}', UsuariosController::class . ':excluir');
            })->add(Perfis::admin());

            // Auditoria (somente administrador)
            $auth->group('/auditoria', function (RouteCollectorProxy $g): void {
                $g->get('', InicioController::class . ':listarAuditoria');
                $g->get('/{id:[0-9]+}', InicioController::class . ':obterAuditoria');
                $g->get('/{id:[0-9]+}/verificar', InicioController::class . ':verificarAuditoria');
            })->add(Perfis::admin());

            // Operação do dia a dia: administrador e professora
            $auth->group('', function (RouteCollectorProxy $op): void {
                $op->get('/listas/{tipo}', ListasController::class . ':listar');
                $op->post('/listas/{tipo}', ListasController::class . ':criar');
                $op->put('/listas/{tipo}/{id:[0-9]+}', ListasController::class . ':atualizar');
                $op->delete('/listas/{tipo}/{id:[0-9]+}', ListasController::class . ':excluir');

                $op->get('/alunos', AlunosController::class . ':listar');
                $op->post('/alunos', AlunosController::class . ':criar');
                $op->get('/alunos/{id:[0-9]+}', AlunosController::class . ':obter');
                $op->put('/alunos/{id:[0-9]+}', AlunosController::class . ':atualizar');
                $op->delete('/alunos/{id:[0-9]+}', AlunosController::class . ':excluir');

                $op->get('/aulas', AulasController::class . ':listar');
                $op->post('/aulas', AulasController::class . ':criar');
                $op->get('/aulas/{id:[0-9]+}', AulasController::class . ':obter');
                $op->put('/aulas/{id:[0-9]+}', AulasController::class . ':atualizar');
                $op->delete('/aulas/{id:[0-9]+}', AulasController::class . ':excluir');
                $op->get('/alunos/{id:[0-9]+}/aulas', AulasController::class . ':doAluno');
                $op->get('/alunos/{id:[0-9]+}/aulas/anterior', AulasController::class . ':anterior');
                $op->get('/calendario', AulasController::class . ':calendario');
                $op->get('/agenda/hoje', AulasController::class . ':agendaHoje');

                $op->get('/cobrancas', CobrancasController::class . ':listar');
                $op->post('/cobrancas/gerar', CobrancasController::class . ':gerar');
                $op->get('/cobrancas/{id:[0-9]+}', CobrancasController::class . ':obter');
                $op->put('/cobrancas/{id:[0-9]+}', CobrancasController::class . ':atualizar');
                $op->post('/cobrancas/{id:[0-9]+}/pagamentos', CobrancasController::class . ':pagar');
                $op->delete('/pagamentos/{id:[0-9]+}', CobrancasController::class . ':estornar');
                $op->get('/alunos/{id:[0-9]+}/ficha-financeira', CobrancasController::class . ':fichaFinanceira');

                $op->get('/dashboards/financeiro', DashboardController::class . ':financeiro');
                $op->get('/exportacoes/{tipo}.xlsx', DashboardController::class . ':exportar');

                $op->get('/metodos', MetodosController::class . ':listar');
                $op->post('/metodos', MetodosController::class . ':criar');
                $op->get('/metodos/{id:[0-9]+}', MetodosController::class . ':obter');
                $op->put('/metodos/{id:[0-9]+}', MetodosController::class . ':atualizar');
                $op->delete('/metodos/{id:[0-9]+}', MetodosController::class . ':excluir');
                $op->get('/metodos/{id:[0-9]+}/licoes', MetodosController::class . ':licoes');
                $op->post('/metodos/{id:[0-9]+}/licoes', MetodosController::class . ':criarLicao');
                $op->post('/metodos/{id:[0-9]+}/licoes/lote', MetodosController::class . ':lote');
                $op->put('/metodos/{id:[0-9]+}/licoes/ordem', MetodosController::class . ':reordenar');
                $op->put('/licoes/{id:[0-9]+}', MetodosController::class . ':atualizarLicao');
                $op->delete('/licoes/{id:[0-9]+}', MetodosController::class . ':excluirLicao');

                $op->get('/alunos/{id:[0-9]+}/ficha-progresso', ProgressoController::class . ':ficha');
                $op->get('/alunos/{id:[0-9]+}/metodos/{metodoId:[0-9]+}/licoes', ProgressoController::class . ':licoes');
                $op->post('/alunos/{id:[0-9]+}/metodos', ProgressoController::class . ':associar');
                $op->put('/alunos/{id:[0-9]+}/metodos/{metodoId:[0-9]+}', ProgressoController::class . ':situacao');
                $op->delete('/alunos/{id:[0-9]+}/metodos/{metodoId:[0-9]+}', ProgressoController::class . ':desassociar');
                $op->post('/alunos/{id:[0-9]+}/avaliacoes', ProgressoController::class . ':avaliar');
                $op->get('/alunos/{id:[0-9]+}/licoes/{licaoId:[0-9]+}/avaliacoes', ProgressoController::class . ':historico');
                $op->delete('/avaliacoes/{id:[0-9]+}', ProgressoController::class . ':excluirAvaliacao');
                $op->put('/alunos/{id:[0-9]+}/progresso', ProgressoController::class . ':atualizar');
                $op->get('/inicio', InicioController::class . ':painel');
                // {{OPERACAO}}
            })->add(Perfis::operacao());
        })->add(Autenticacao::class);
    });

    // Documentação da API (somente administrador)
    $app->group('', function (RouteCollectorProxy $docs): void {
        $docs->get('/docs', DocsController::class . ':pagina');
        $docs->get('/docs/scalar.js', DocsController::class . ':script');
        $docs->get('/openapi.json', DocsController::class . ':openapi');
    })->add(Perfis::admin())->add(Autenticacao::class);
};

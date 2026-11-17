<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
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
        })->add(Autenticacao::class);
    });
};

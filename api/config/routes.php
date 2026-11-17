<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return static function (App $app): void {
    $app->group('/v1', function (RouteCollectorProxy $v1): void {
        $v1->get('/health', HealthController::class . ':verificar');
    });
};

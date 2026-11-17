<?php

declare(strict_types=1);

namespace App;

use App\Http\Middleware\ContextoMiddleware;
use App\Http\Middleware\CsrfMiddleware;
use App\Http\TratadorErros;
use App\Infra\Config;
use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Factory\AppFactory;

/** Monta container e aplicação Slim (usado pelo bootstrap e pelos testes). */
final class Kernel
{
    public static function raiz(): string
    {
        return dirname(__DIR__);
    }

    public static function carregarEnv(): void
    {
        Dotenv::createImmutable(self::raiz())->safeLoad();
    }

    /** @param array<string,string> $sobrescritas */
    public static function container(array $sobrescritas = []): ContainerInterface
    {
        $config = new Config($sobrescritas);
        $definicoes = (require self::raiz() . '/config/container.php')($config);
        return (new ContainerBuilder())->addDefinitions($definicoes)->build();
    }

    /** @param array<string,string> $sobrescritas */
    public static function app(array $sobrescritas = []): App
    {
        $container = self::container($sobrescritas);
        $config = $container->get(Config::class);

        AppFactory::setContainer($container);
        $app = AppFactory::create();
        $app->setBasePath('/api');

        $app->add(ContextoMiddleware::class);
        $app->add(CsrfMiddleware::class);
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();

        $erros = $app->addErrorMiddleware(!$config->ehProducao(), true, true, $container->get(LoggerInterface::class));
        $erros->setDefaultErrorHandler($container->get(TratadorErros::class));

        (require self::raiz() . '/config/routes.php')($app);

        return $app;
    }

    public static function executar(): void
    {
        self::carregarEnv();
        $config = new Config();
        date_default_timezone_set($config->get('APP_TIMEZONE', 'America/Sao_Paulo') ?? 'America/Sao_Paulo');
        self::app()->run();
    }
}

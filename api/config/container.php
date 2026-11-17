<?php

declare(strict_types=1);

use App\Infra\Banco\Conexao;
use App\Infra\Config;
use App\Infra\Email\EmailEmMemoria;
use App\Infra\Email\EmailEnviador;
use App\Infra\Email\PhpMailerEnviador;
use App\Infra\Relogio;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

use function DI\factory;

/**
 * Definições do container (PHP-DI). O que não está aqui é resolvido por autowiring.
 *
 * @return array<string,mixed>
 */
return static function (Config $config): array {
    return [
        Config::class => $config,

        Conexao::class => static function () use ($config): Conexao {
            return new Conexao([
                'host' => $config->get('DB_HOST', 'localhost'),
                'porta' => $config->int('DB_PORT', 3306),
                'nome' => $config->obrigatorio('DB_NAME'),
                'usuario' => $config->get('DB_USER', '') ?? '',
                'senha' => $config->get('DB_PASS', '') ?? '',
            ]);
        },

        EmailEnviador::class => static fn (): EmailEnviador => $config->get('APP_ENV') === 'testing'
            ? new EmailEmMemoria()
            : new PhpMailerEnviador($config),

        Relogio::class => static fn (): Relogio => new Relogio($config->get('APP_TIMEZONE', 'America/Sao_Paulo') ?? 'America/Sao_Paulo'),

        LoggerInterface::class => static function () use ($config): LoggerInterface {
            $logger = new Logger('ddc');
            $destino = $config->get('LOG_FILE', $config->raizApi() . '/storage/logs/app.log');
            $logger->pushHandler(new StreamHandler($destino ?? 'php://stderr'));
            return $logger;
        },
    ];
};

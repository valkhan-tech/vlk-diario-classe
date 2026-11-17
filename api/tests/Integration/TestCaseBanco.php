<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infra\Banco\Conexao;
use App\Infra\Banco\Migrador;
use App\Infra\Config;
use App\Kernel;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Base dos testes de integração: usa SOMENTE o banco DB_NAME_TEST (deve terminar em _test),
 * recria o esquema uma vez por execução e limpa os dados antes de cada teste.
 */
abstract class TestCaseBanco extends TestCase
{
    private static bool $migrado = false;
    protected ContainerInterface $container;
    protected Conexao $db;

    public static function nomeBancoTeste(): string
    {
        $nome = (new Config())->obrigatorio('DB_NAME_TEST');
        if (!str_ends_with($nome, '_test')) {
            throw new \RuntimeException("Recusado: o banco de teste deve terminar em _test ({$nome}).");
        }
        return $nome;
    }

    /** @return array<string,string> */
    protected function sobrescritas(): array
    {
        return ['DB_NAME' => self::nomeBancoTeste(), 'APP_ENV' => 'testing', 'LOG_FILE' => 'php://memory', 'RELOGIO_FIXO' => '2026-11-17 09:00:00'];
    }

    public static function setUpBeforeClass(): void
    {
        if (self::$migrado) {
            return;
        }
        $nome = self::nomeBancoTeste();
        $config = new Config(['DB_NAME' => $nome]);
        $db = new Conexao([
            'host' => $config->get('DB_HOST', 'localhost'),
            'porta' => $config->int('DB_PORT', 3306),
            'nome' => $nome,
            'usuario' => $config->get('DB_USER', '') ?? '',
            'senha' => $config->get('DB_PASS', '') ?? '',
        ]);
        $migrador = new Migrador($db, Kernel::raiz() . '/migrations');
        $migrador->apagarTudo();
        $migrador->aplicar();
        self::$migrado = true;
    }

    protected function setUp(): void
    {
        $this->container = Kernel::container($this->sobrescritas());
        $this->db = $this->container->get(Conexao::class);
        $pdo = $this->db->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->db->consultar('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE()') as $linha) {
            if ($linha['t'] !== 'sys_migracao') {
                $pdo->exec('TRUNCATE TABLE `' . $linha['t'] . '`');
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}

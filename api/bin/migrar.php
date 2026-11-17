<?php

declare(strict_types=1);

// Aplica as migrações pendentes (DESENVOLVIMENTO/TESTES).
// Uso: php api/bin/migrar.php [--teste] [--zerar]
//   --teste  usa o banco DB_NAME_TEST
//   --zerar  apaga todas as tabelas antes (recusado em produção)

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Infra\Banco\Conexao;
use App\Infra\Banco\Migrador;
use App\Infra\Config;
use App\Kernel;

Kernel::carregarEnv();
$config = new Config();
$opcoes = array_slice($argv, 1);
$teste = in_array('--teste', $opcoes, true);
$zerar = in_array('--zerar', $opcoes, true);

if ($zerar && $config->ehProducao()) {
    fwrite(STDERR, "Recusado: --zerar não é permitido em produção.\n");
    exit(1);
}

$nome = $teste ? $config->obrigatorio('DB_NAME_TEST') : $config->obrigatorio('DB_NAME');
if ($teste && !str_ends_with($nome, '_test')) {
    fwrite(STDERR, "Recusado: o banco de teste deve terminar em _test ({$nome}).\n");
    exit(1);
}

$db = new Conexao([
    'host' => $config->get('DB_HOST', 'localhost'),
    'porta' => $config->int('DB_PORT', 3306),
    'nome' => $nome,
    'usuario' => $config->get('DB_USER', '') ?? '',
    'senha' => $config->get('DB_PASS', '') ?? '',
]);
$migrador = new Migrador($db, dirname(__DIR__) . '/migrations');

if ($zerar) {
    $migrador->apagarTudo();
    echo "Banco {$nome} zerado.\n";
}

$feitas = $migrador->aplicar();
if ($feitas === []) {
    echo "Nenhuma migração pendente em {$nome}.\n";
    exit(0);
}
foreach ($feitas as $arquivo) {
    echo "Aplicada: {$arquivo}\n";
}

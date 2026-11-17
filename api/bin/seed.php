<?php

declare(strict_types=1);

// Seeder abrangente para DESENVOLVIMENTO/TESTES (recusado em produção).
// Uso: php api/bin/seed.php [--limpar]
//   --limpar  apaga todos os dados (exceto sys_migracao) antes de semear

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Infra\Config;
use App\Infra\Seed\Semeador;
use App\Kernel;

Kernel::carregarEnv();
$config = new Config();
if ($config->ehProducao()) {
    fwrite(STDERR, "Recusado: o seeder não roda com APP_ENV=production.\n");
    exit(1);
}
date_default_timezone_set($config->get('APP_TIMEZONE', 'America/Sao_Paulo') ?? 'America/Sao_Paulo');

try {
    $contagens = Kernel::container()->get(Semeador::class)->executar(in_array('--limpar', $argv, true));
} catch (\RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

echo "Dados de demonstração criados:\n";
foreach ($contagens as $tabela => $n) {
    printf("  %-18s %d\n", $tabela, $n);
}
echo "\nAcessos (senhas de teste):\n";
foreach (Semeador::USUARIOS as [$nome, $email, $senha, $perfil]) {
    printf("  %-11s %-24s %s\n", $perfil, $email, $senha);
}
echo "  (usuário inativo: inativo@ddc.local / Inativo@123)\n";

<?php

declare(strict_types=1);

// Gera as cobranças da competência (padrão: mês atual). Idempotente. Agendar no cron do cPanel (diário).
// Uso: php api/bin/cobrancas.php [AAAA-MM]

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Aplicacao\Financeiro\GeracaoCobrancas;
use App\Infra\Auditoria\ContextoOperacao;
use App\Infra\Config;
use App\Infra\Relogio;
use App\Kernel;

Kernel::carregarEnv();
$container = Kernel::container();
$container->get(ContextoOperacao::class)->definirSistema('cron:gerar-cobrancas');

$alvo = $argv[1] ?? null;
if ($alvo !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $alvo) !== 1) {
    fwrite(STDERR, "Competência inválida. Use AAAA-MM.\n");
    exit(1);
}
$competencia = $alvo !== null
    ? new DateTimeImmutable($alvo . '-01')
    : $container->get(Relogio::class)->hoje();

$r = $container->get(GeracaoCobrancas::class)->gerar($competencia);
printf("[%s] competência %s: %d criada(s), %d já existente(s), %d ignorado(s)\n", date('Y-m-d H:i:s'), $r['competencia'], $r['criadas'], $r['existentes'], $r['ignorados']);

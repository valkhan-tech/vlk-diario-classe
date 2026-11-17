<?php

declare(strict_types=1);

// Remove sessões expiradas, tokens de recuperação vencidos e tentativas de login antigas (diário, via cron).
// Uso: php api/bin/limpeza.php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Aplicacao\Acesso\LimpezaTecnica;
use App\Kernel;

Kernel::carregarEnv();
$r = Kernel::container()->get(LimpezaTecnica::class)->executar();
printf("[%s] limpeza: %d sessão(ões), %d recuperação(ões), %d tentativa(s)\n", date('Y-m-d H:i:s'), $r['sessoes'], $r['redefinicoes'], $r['tentativas']);

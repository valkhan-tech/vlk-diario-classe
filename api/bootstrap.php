<?php

declare(strict_types=1);

// Entrada da API (chamada por public/api.php). Toda a lógica está em src/.

require __DIR__ . '/vendor/autoload.php';

\App\Kernel::executar();

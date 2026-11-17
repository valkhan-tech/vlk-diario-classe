<?php

declare(strict_types=1);

// Gera api/storage/openapi.json a partir dos atributos dos controllers.
// Uso: php api/bin/gerar-openapi.php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Infra\Config;
use App\Infra\Docs\OpenApiGerador;
use App\Kernel;

Kernel::carregarEnv();
$caminho = (new OpenApiGerador(new Config()))->gravar();
echo "OpenAPI gerado em {$caminho}\n";

<?php

declare(strict_types=1);

namespace App\Http\OpenApi;

use OpenApi\Attributes as OA;

/** Metadados globais da documentação OpenAPI (gerada a partir dos atributos dos controllers). */
#[OA\Info(
    title: 'Diário de Classe — API',
    version: '1.0.0',
    description: 'API do Diário de Classe (https://ddc.cpod.com.br). Autenticação por cookie de sessão (HttpOnly) obtido em POST /api/v1/sessao. Requisições que gravam exigem o cabeçalho X-Requested-With: XMLHttpRequest. Erros em application/problem+json.'
)]
#[OA\Server(url: '/', description: 'Mesmo domínio')]
#[OA\SecurityScheme(securityScheme: 'sessao', type: 'apiKey', in: 'cookie', name: 'ddc_sessao')]
final class Documentacao
{
}

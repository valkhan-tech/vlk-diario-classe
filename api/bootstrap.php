<?php

declare(strict_types=1);

// PLACEHOLDER: o bootstrap real (Slim, container, middlewares, rotas) será criado
// na primeira entrega. Por ora, responde 501 para deixar claro que a API não existe ainda.

http_response_code(501);
header('Content-Type: application/problem+json; charset=utf-8');
echo json_encode([
    'type' => 'about:blank',
    'title' => 'API em construção',
    'status' => 501,
], JSON_UNESCAPED_UNICODE);

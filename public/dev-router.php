<?php

declare(strict_types=1);

// Router apenas para desenvolvimento local (reproduz o .htaccess):
//   php -S 127.0.0.1:8080 -t public public/dev-router.php
// Em produção quem manda é o Apache + public/.htaccess.

$caminho = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (preg_match('#^/api(/|$)#', $caminho) === 1) {
    require __DIR__ . '/api.php';
    return true;
}

if ($caminho !== '/' && is_file(__DIR__ . $caminho)) {
    return false; // deixa o servidor embutido entregar o arquivo estático
}

$index = __DIR__ . '/index.html';
if (is_file($index)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($index);
    return true;
}

http_response_code(404);
echo 'Front ainda não compilado (use o servidor do Vite em desenvolvimento).';
return true;

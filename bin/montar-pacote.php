<?php

declare(strict_types=1);

// Monta release/ddc-<versao>.zip com public/ (front já compilado) e api/ (vendor sem dev, openapi.json).
// Executado DENTRO do container (veja bin/release.mjs): php bin/montar-pacote.php <versao>

$raiz = dirname(__DIR__);
$versao = $argv[1] ?? date('Ymd-His');
$staging = $raiz . '/release/staging';
$zipPath = $raiz . "/release/ddc-{$versao}.zip";

function rrmdir(string $d): void
{
    if (!is_dir($d)) {
        return;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($d);
}

/** @param list<string> $excluir caminhos relativos (prefixos) a ignorar */
function copiar(string $origem, string $destino, array $excluir = []): void
{
    $origem = rtrim($origem, '/');
    @mkdir($destino, 0775, true);
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($origem, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        $rel = str_replace('\\', '/', substr($item->getPathname(), strlen($origem) + 1));
        foreach ($excluir as $prefixo) {
            if ($rel === $prefixo || str_starts_with($rel, $prefixo . '/')) {
                continue 2;
            }
        }
        $alvo = $destino . '/' . $rel;
        if ($item->isDir()) {
            @mkdir($alvo, 0775, true);
        } else {
            copy($item->getPathname(), $alvo);
        }
    }
}

function executar(string $cmd): void
{
    passthru($cmd, $codigo);
    if ($codigo !== 0) {
        fwrite(STDERR, "Falhou: {$cmd}\n");
        exit(1);
    }
}

if (!is_file($raiz . '/public/index.html')) {
    fwrite(STDERR, "Front não compilado (public/index.html ausente). Rode 'npm run build' em web/.\n");
    exit(1);
}

rrmdir($staging);
@mkdir($raiz . '/release', 0775, true);

// public/: front compilado + api.php + .htaccess (sem o router de desenvolvimento)
copiar($raiz . '/public', $staging . '/public', ['dev-router.php']);

// api/: sem vendor de desenvolvimento, testes, .env, logs e arquivos de ferramentas
copiar($raiz . '/api', $staging . '/api', [
    'vendor', 'tests', '.env', '.phpunit.cache', '.phpunit.result.cache', 'phpunit.xml', 'phpstan.neon',
    'storage/logs', 'storage/openapi.json', 'VERSION',
]);
@mkdir($staging . '/api/storage/logs', 0775, true);
file_put_contents($staging . '/api/storage/logs/.gitkeep', '');
file_put_contents($staging . '/api/VERSION', $versao . "\n");

executar('cd ' . escapeshellarg($staging . '/api') . ' && composer install --no-dev --optimize-autoloader --no-interaction --quiet');
executar('cd ' . escapeshellarg($staging . '/api') . ' && php bin/gerar-openapi.php');

// zip
if (is_file($zipPath)) {
    unlink($zipPath);
}
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Não foi possível criar o zip.\n");
    exit(1);
}
$total = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($staging, FilesystemIterator::SKIP_DOTS)) as $arquivo) {
    $rel = str_replace('\\', '/', substr($arquivo->getPathname(), strlen($staging) + 1));
    $zip->addFile($arquivo->getPathname(), $rel);
    $total++;
}
$zip->close();
rrmdir($staging);

printf("Pacote: %s (%d arquivos, %.1f MB)\n", str_replace($raiz . '/', '', $zipPath), $total, filesize($zipPath) / 1048576);

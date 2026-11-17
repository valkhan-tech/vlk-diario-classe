<?php

declare(strict_types=1);

// Gera UM arquivo SQL para instalar o sistema em um banco VAZIO:
// todas as migrações (em ordem) + o primeiro administrador (troca de senha obrigatória no 1º acesso).
//
// Uso: php api/bin/gerar-instalacao.php "Nome" email@dominio.com "SenhaInicial1" [arquivo-de-saida.sql]
// Padrão de saída: release/instalacao.sql (pasta ignorada pelo Git). Importe no phpMyAdmin.

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Dominio\Acesso\SenhaPolitica;
use App\Infra\Banco\Migrador;

if ($argc < 4) {
    fwrite(STDERR, "Uso: php api/bin/gerar-instalacao.php \"Nome\" email@dominio.com \"SenhaInicial1\" [saida.sql]\n");
    exit(1);
}

[$nome, $email, $senha] = [trim($argv[1]), mb_strtolower(trim($argv[2])), $argv[3]];
$saida = $argv[4] ?? dirname(__DIR__, 2) . '/release/instalacao.sql';

if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Nome ou e-mail inválido.\n");
    exit(1);
}
if (!SenhaPolitica::valida($senha)) {
    fwrite(STDERR, SenhaPolitica::MENSAGEM . "\n");
    exit(1);
}

$pasta = dirname(__DIR__) . '/migrations';
$arquivos = array_map('basename', glob($pasta . '/*.sql') ?: []);
sort($arquivos);
foreach ($arquivos as $a) {
    if (preg_match(Migrador::PADRAO_NOME, $a) !== 1) {
        fwrite(STDERR, "Nome de migração fora do padrão: {$a}\n");
        exit(1);
    }
}

$q = static fn (string $v): string => "'" . str_replace(['\\', "'"], ['\\\\', "''"], $v) . "'";
$sql = "-- Diário de Classe — instalação em banco VAZIO\n"
    . '-- Gerado em ' . date('d/m/Y H:i') . ' · ' . count($arquivos) . " migrações + primeiro administrador\n"
    . "-- Importe no phpMyAdmin (aba Importar) com o banco já selecionado.\n\n"
    . "SET NAMES utf8mb4;\n\n";

foreach ($arquivos as $a) {
    $sql .= "-- ============================================================\n-- Migração: {$a}\n-- ============================================================\n";
    $sql .= rtrim((string) file_get_contents($pasta . '/' . $a)) . "\n\n";
}

$sql .= "-- ============================================================\n-- Primeiro administrador (troca de senha obrigatória no 1º acesso)\n-- ============================================================\n";
$sql .= "INSERT INTO usuarios (nome, email, senha_hash, perfil, ativo, troca_senha_obrigatoria)\nVALUES ("
    . $q($nome) . ', ' . $q($email) . ', ' . $q(password_hash($senha, PASSWORD_DEFAULT)) . ", 'admin', 1, 1);\n";

@mkdir(dirname($saida), 0775, true);
file_put_contents($saida, $sql);
printf("Gerado: %s (%d migrações, administrador %s)\n", $saida, count($arquivos), $email);

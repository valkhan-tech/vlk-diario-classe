<?php

declare(strict_types=1);

// Gera o SQL do PRIMEIRO administrador (para colar no phpMyAdmin, em produção).
// Uso: php api/bin/gerar-semente-admin.php "Nome" email@dominio.com [senha-temporaria]
// Sem a senha, gera uma aleatória e a imprime UMA vez. O usuário é obrigado a trocá-la no 1º acesso.

if ($argc < 3) {
    fwrite(STDERR, "Uso: php api/bin/gerar-semente-admin.php \"Nome\" email@dominio.com [senha-temporaria]\n");
    exit(1);
}

$nome = $argv[1];
$email = mb_strtolower(trim($argv[2]));
$senha = $argv[3] ?? substr(bin2hex(random_bytes(8)), 0, 10) . 'A1';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "E-mail inválido.\n");
    exit(1);
}
if (mb_strlen($senha) < 8 || !preg_match('/\p{L}/u', $senha) || !preg_match('/\d/', $senha)) {
    fwrite(STDERR, "A senha deve ter pelo menos 8 caracteres, com letras e números.\n");
    exit(1);
}

$hash = password_hash($senha, PASSWORD_DEFAULT);
$q = static fn (string $v): string => "'" . str_replace(["\\", "'"], ["\\\\", "''"], $v) . "'";

echo "-- Primeiro administrador (troca de senha obrigatória no primeiro acesso)\n";
echo "INSERT INTO usuarios (nome, email, senha_hash, perfil, ativo, troca_senha_obrigatoria)\n";
echo 'VALUES (' . $q($nome) . ', ' . $q($email) . ', ' . $q($hash) . ", 'admin', 1, 1);\n\n";
fwrite(STDERR, "Senha temporária (anote; não será exibida de novo): {$senha}\n");

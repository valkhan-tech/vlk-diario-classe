// Monta o pacote de entrega: testes -> análise estática -> build do front -> release/ddc-<versao>.zip
// Uso (na raiz do repositório, com o Docker de desenvolvimento no ar):
//   node bin/release.mjs            roda tudo
//   node bin/release.mjs --sem-testes   (não recomendado)
import { execSync } from 'node:child_process';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const raiz = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const semTestes = process.argv.includes('--sem-testes');

const run = (cmd, cwd = raiz) => execSync(cmd, { stdio: 'inherit', cwd });
const noContainer = (cmd) =>
  run(`docker compose exec -T app sh -c "cd /var/www/html && ${cmd}"`);

const agora = new Date();
const p = (n) => String(n).padStart(2, '0');
const versao = `${agora.getFullYear()}${p(agora.getMonth() + 1)}${p(agora.getDate())}-${p(agora.getHours())}${p(agora.getMinutes())}`;

console.log(`\n== Entrega ${versao} ==`);

if (!semTestes) {
  console.log('\n[1/4] Testes da API (unitários + integração)');
  noContainer('php api/bin/migrar.php --teste --zerar && cd api && composer test');
  console.log('\n[2/4] Análise estática (PHPStan)');
  noContainer('cd api && composer analyse');
  console.log('\n[3/4] Testes do front');
  run('npm test', resolve(raiz, 'web'));
} else {
  console.log('\n(!) Testes ignorados por --sem-testes');
}

console.log('\n[4/4] Build do front e montagem do pacote');
run('npm run build', resolve(raiz, 'web'));
noContainer(`php bin/montar-pacote.php ${versao}`);

console.log(`
Próximos passos (manual):
 1. Aplique no phpMyAdmin as migrações novas de api/migrations/ (em ordem de nome).
 2. Envie release/ddc-${versao}.zip pelo Gerenciador de Arquivos do cPanel e extraia em /home/domains/ddc.cpod.com.br/
 3. Confira https://ddc.cpod.com.br/api/v1/health (deve mostrar a versão ${versao}).
`);

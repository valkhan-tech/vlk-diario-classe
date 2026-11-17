// Remove a saída antiga do front em ../public (sem tocar em api.php, .htaccess e dev-router.php).
import { rmSync, existsSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const publico = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..', 'public');
for (const alvo of ['assets', 'index.html']) {
  const caminho = resolve(publico, alvo);
  if (existsSync(caminho)) rmSync(caminho, { recursive: true, force: true });
}
console.log('public/ limpo (assets e index.html).');

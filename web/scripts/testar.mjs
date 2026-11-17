// Executa o Vitest com a letra da unidade em MAIÚSCULA (Windows).
// Com "e:\..." e "E:\..." o mesmo arquivo vira dois módulos e o Vitest falha com
// "failed to find the current suite". Em outros sistemas é só repasse.
import { spawnSync } from 'node:child_process';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const pasta = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const cwd = pasta.replace(/^[a-z]:/, (u) => u.toUpperCase());
const bin = resolve(cwd, 'node_modules', 'vitest', 'vitest.mjs');
const r = spawnSync(process.execPath, [bin, 'run', ...process.argv.slice(2)], { cwd, stdio: 'inherit' });
process.exit(r.status ?? 1);

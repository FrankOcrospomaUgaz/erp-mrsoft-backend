import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const maxConfigBytes = 8 * 1024;
const dynamicExecutionPattern = new RegExp(`\\bnew\\s+${['Func', 'tion'].join('')}\\b|\\b${['child', 'process'].join('_')}\\b`, 'i');
const suspiciousConfigCode = /\beval\s*\(|\batob\s*\(|\bfetch\s*\(|\bexec(?:Sync)?\s*\(|\bspawn(?:Sync)?\s*\(/i;
const suspiciousScript = /\bnode\s+init\b|\bnode\s+-e\b|\bcurl\b|\bwget\b|\binvoke-webrequest\b|\beval\s*\(|\batob\s*\(/i;

if (fs.existsSync(path.join(root, 'init'))) {
    throw new Error('Se bloqueó el archivo init: este proyecto no requiere un inicializador externo.');
}

const packageJson = JSON.parse(fs.readFileSync(path.join(root, 'package.json'), 'utf8'));
for (const [name, command] of Object.entries(packageJson.scripts ?? {})) {
    if (suspiciousScript.test(command)) {
        throw new Error(`Se bloqueó el script npm "${name}" por contener un cargador o ejecutor remoto sospechoso.`);
    }
}

for (const name of ['vite.config.js', 'vite.config.ts', 'vite.config.mjs', 'vite.config.cjs']) {
    const filename = path.join(root, name);
    if (!fs.existsSync(filename)) continue;

    const stats = fs.statSync(filename);
    const source = fs.readFileSync(filename, 'utf8');
    if (stats.size > maxConfigBytes || source.split(/\r?\n/).some((line) => line.length > 2048)) {
        throw new Error(`Se bloqueó ${name}: el archivo excede el tamaño normal permitido.`);
    }
    if (suspiciousConfigCode.test(source) || dynamicExecutionPattern.test(source)) {
        throw new Error(`Se bloqueó ${name}: contiene código capaz de descargar o ejecutar código externo.`);
    }
}

console.log('Configuración de build verificada.');

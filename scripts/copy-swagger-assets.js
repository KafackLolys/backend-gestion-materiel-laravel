import { copyFileSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const source = resolve(root, 'node_modules/swagger-ui-dist');
const destination = resolve(root, 'public/swagger-ui');
const assets = [
    'favicon-16x16.png',
    'favicon-32x32.png',
    'swagger-ui-bundle.js',
    'swagger-ui-standalone-preset.js',
    'swagger-ui.css',
];

mkdirSync(destination, { recursive: true });

for (const asset of assets) {
    copyFileSync(resolve(source, asset), resolve(destination, asset));
}

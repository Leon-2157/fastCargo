import { copyFile, mkdir } from 'node:fs/promises';

await mkdir('internal/dashboard/embedded/assets', { recursive: true });
await copyFile('node_modules/alpinejs/dist/cdn.min.js', 'internal/dashboard/embedded/assets/alpine.min.js');

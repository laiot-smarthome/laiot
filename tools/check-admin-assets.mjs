import { existsSync, statSync } from 'node:fs';

const required = [
  'public/assets/admin/mydomo.js',
  'public/assets/admin/mydomo.css',
];

const missing = required.filter((file) => !existsSync(file) || statSync(file).size === 0);

if (missing.length) {
  console.error('Admin assets are incomplete:');
  for (const file of missing) {
    console.error('  - ' + file);
  }
  console.error('Run `npm run build` (admin bundle + code editor chunk + charts chunk + code sample highlighter) or the matching build:* script.');
  process.exit(1);
}

console.log('Admin assets OK: ' + required.join(', '));

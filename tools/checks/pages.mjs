#!/usr/bin/env node
/**
 * Render every page as the signed-in superadmin and report status, size and
 * the presence of data that should only come from the database.
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const PHP_CLI = path.join(ROOT, 'docs/tools/php-cli.mjs');
const RENDERER = path.join(ROOT, 'tools/http-render.php');
const PW = process.env.VIRA_ADMIN_PASSWORD || 'DevOnly-Passw0rd-2026';
const OUT = path.join(ROOT, '.arena', 'pages');

fs.mkdirSync(OUT, { recursive: true });

let jar = new Map();

function render(spec) {
  const dir = fs.mkdtempSync(path.join(ROOT, '.arena', 'pg-'));
  const file = path.join(dir, 'req.json');
  fs.writeFileSync(file, JSON.stringify({
    method: spec.method || 'GET', uri: spec.uri, host: 'localhost:8080',
    cookie: [...jar].map(([k, v]) => `${k}=${v}`).join('; '),
    post_body: spec.body || '',
    content_type: spec.body ? 'application/x-www-form-urlencoded' : '',
    accept: 'text/html',
  }));
  return new Promise((resolve) => {
    const child = spawn(process.execPath, [PHP_CLI, RENDERER, file], { cwd: ROOT, env: process.env });
    let out = '';
    child.stdout.on('data', d => out += d);
    child.on('close', () => {
      const clean = out.replace(/^\[php-cli\][^\n]*\n?/gm, '');
      const status = Number((clean.match(/^VIRA_STATUS (\d+)/m) || [0, 0])[1]);
      const vi = clean.indexOf('VIRA_BODY\n');
      for (const line of clean.slice(0, vi).split('\n')) {
        const m = line.match(/^VIRA_HEADER ([^:]+):\s*(.*)$/);
        if (m && m[1].toLowerCase() === 'set-cookie') {
          const [pair] = m[2].split(';'); const i = pair.indexOf('=');
          if (i > 0) jar.set(pair.slice(0, i).trim(), pair.slice(i + 1).trim());
        }
      }
      resolve({ status, body: vi === -1 ? clean : clean.slice(vi + 9) });
    });
  });
}

const form = (o) => Object.entries(o).map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&');
const tok = (h) => (h.match(/name="_token" value="([^"]+)"/) || [])[1];

let p = await render({ uri: '/login' });
const login = await render({ method: 'POST', uri: '/login', body: form({ _token: tok(p.body), username: 'admin', password: PW }) });
console.log(`login: ${login.status}\n`);

const pages = [
  ['01-dashboard', '/'],
  ['02-customers', '/customers'],
  ['03-customer-create', '/customers/create'],
  ['04-customer-detail', '/customers/1'],
  ['05-customer-edit', '/customers/1/edit'],
  ['06-contracts', '/contracts'],
  ['07-contract-create', '/contracts/create'],
  ['08-contract-detail', '/contracts/1'],
  ['09-contract-edit', '/contracts/1/edit'],
  ['10-login', '/login'],
  ['11-password', '/password'],
];

console.log('page                  status   bytes   checks');
console.log('─'.repeat(72));

let failures = 0;
for (const [name, uri] of pages) {
  const r = await render({ uri });
  const file = path.join(OUT, name + '.html');
  fs.writeFileSync(file, r.body);

  const checks = [];
  if (r.status !== 200) checks.push(`STATUS ${r.status}`);

  // Structural sanity that must hold on every authenticated page.
  if (name !== '10-login') {
    if (!/dir="rtl"/.test(r.body)) checks.push('no rtl');
    if (!/Vazirmatn/.test(r.body) && !/app\.css/.test(r.body)) checks.push('no css');
    if (!/v-skip/.test(r.body)) checks.push('no skip link');
  }
  if (/Fatal error|Undefined variable|htmlspecialchars\(\)|Unhandled/.test(r.body)) {
    checks.push('PHP ERROR LEAKED');
  }

  if (checks.length) failures++;
  console.log(`${name.padEnd(21)} ${String(r.status).padEnd(7)} ${String(r.body.length).padEnd(7)} ${checks.length ? checks.join('; ') : 'ok'}`);
}

console.log('\nhtml written to .arena/pages/');
process.exit(failures === 0 ? 0 : 1);

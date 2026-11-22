#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const PHP_CLI = path.join(ROOT, 'docs/tools/php-cli.mjs');
const RENDERER = path.join(ROOT, 'tools/http-render.php');
let jar = new Map();

function render(spec) {
  const dir = fs.mkdtempSync(path.join(ROOT, '.arena', 'x-'));
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
const form = o => Object.entries(o).map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&');
const tok = h => (h.match(/name="_token" value="([^"]+)"/) || [])[1];

const p = await render({ uri: '/login' });
await render({ method: 'POST', uri: '/login', body: form({ _token: tok(p.body), username: 'viewer1', password: process.env.VIRA_VIEWER_PASSWORD }) });

const denied = await render({ uri: '/customers/create' });
console.log('signed-in 403: status =', denied.status);
console.log('  has sidebar (stays in shell):', /v-sidebar/.test(denied.body));
console.log('  has persian message:', /دسترسی غیرمجاز/.test(denied.body));
console.log('  has back-to-dashboard:', /بازگشت به داشبورد/.test(denied.body));
console.log('  body bytes:', denied.body.length);

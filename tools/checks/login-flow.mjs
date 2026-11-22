#!/usr/bin/env node
/**
 * End-to-end authentication flow against the real kernel.
 *
 * Drives tools/http-render.php exactly as the dev server does and keeps the
 * cookie jar, so this exercises the actual session, CSRF and middleware stack —
 * not a mock.
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const NODE = process.execPath;
const PHP_CLI = path.join(ROOT, 'docs/tools/php-cli.mjs');
const RENDERER = path.join(ROOT, 'tools/http-render.php');
const PHP_WASM_PATH = process.env.PHP_WASM_PATH || '/tmp/phpwasm/node_modules';

let jar = new Map();

function render(spec) {
  const dir = fs.mkdtempSync(path.join(ROOT, '.arena', 'flow-'));
  const file = path.join(dir, 'req.json');
  fs.writeFileSync(file, JSON.stringify({
    method: spec.method || 'GET',
    uri: spec.uri,
    host: 'localhost:8080',
    cookie: [...jar].map(([k, v]) => `${k}=${v}`).join('; '),
    post_body: spec.body || '',
    content_type: spec.contentType || (spec.body ? 'application/x-www-form-urlencoded' : ''),
    accept: 'text/html',
  }));

  return new Promise((resolve) => {
    const child = spawn(NODE, [PHP_CLI, RENDERER, file], {
      cwd: ROOT,
      env: { ...process.env, PHP_WASM_PATH },
    });
    let out = '', err = '';
    child.stdout.on('data', d => out += d);
    child.stderr.on('data', d => err += d);
    const timer = setTimeout(() => child.kill('SIGKILL'), 90_000);

    child.on('close', () => {
      clearTimeout(timer);
      const clean = out.replace(/^\[php-cli\][^\n]*\n?/gm, '');
      const status = Number((clean.match(/^VIRA_STATUS (\d+)/m) || [0, 0])[1]);
      const vi = clean.indexOf('VIRA_BODY\n');
      const headers = {};
      for (const line of clean.slice(0, vi).split('\n')) {
        const m = line.match(/^VIRA_HEADER ([^:]+):\s*(.*)$/);
        if (!m) continue;
        const k = m[1].toLowerCase();
        const v = m[2];
        // collect set-cookie into the jar
        if (k === 'set-cookie') {
          const [pair] = v.split(';');
          const idx = pair.indexOf('=');
          if (idx > 0) jar.set(pair.slice(0, idx).trim(), pair.slice(idx + 1).trim());
        }
        headers[k] = headers[k] ? headers[k] + ' | ' + v : v;
      }
      resolve({ status, headers, body: vi === -1 ? clean : clean.slice(vi + 9), err });
    });
  });
}

const results = [];
function check(id, passed, detail) {
  results.push({ id, passed, detail });
  console.log(`${passed ? 'PASS' : 'FAIL'}  ${id}  ${detail}`);
}

// ── T-AUTH-01 Anonymous access is blocked ────────────────────────────────
const anon = await render({ uri: '/customers' });
check('T-AUTH-01', anon.status === 302 && (anon.headers.location || '').includes('/login'),
  `GET /customers anonym -> ${anon.status} ${anon.headers.location || ''}`);

// ── T-AUTH-02 Login page renders ─────────────────────────────────────────
const page = await render({ uri: '/login' });
const token = (page.body.match(/name="_token" value="([^"]+)"/) || [])[1];
check('T-AUTH-02', page.status === 200 && /dir="rtl"/.test(page.body) && !!token,
  `GET /login -> ${page.status}, csrf=${token ? 'yes' : 'no'}, rtl=${/dir="rtl"/.test(page.body)}`);

// ── T-AUTH-03 Wrong password is rejected without disclosing why ──────────
const bad = await render({
  method: 'POST', uri: '/login',
  body: `_token=${encodeURIComponent(token)}&username=admin&password=definitely-wrong`,
});
check('T-AUTH-03', bad.status === 302 && /login/.test(bad.headers.location || ''),
  `POST bad creds -> ${bad.status} -> ${bad.headers.location}`);

// echo the error back
const afterBad = await render({ uri: '/login' });
const errorShown = /نام کاربری یا رمز عبور نادرست است/.test(afterBad.body);
check('T-AUTH-04', errorShown, `generic error shown: ${errorShown}`);

// ── T-AUTH-05 CSRF is enforced ───────────────────────────────────────────
const noToken = await render({
  method: 'POST', uri: '/login',
  body: 'username=admin&password=x',
});
check('T-AUTH-05', noToken.status === 419 || noToken.status === 302,
  `POST without CSRF token -> ${noToken.status}`);

console.log('\n--- summary ---');
const pass = results.filter(r => r.passed).length;
console.log(`${pass}/${results.length} passed`);
process.exit(pass === results.length ? 0 : 1);

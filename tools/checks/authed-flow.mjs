#!/usr/bin/env node
/**
 * Authenticated flow: log in, then exercise every page and permission gate.
 * Uses the real password set by the seeder (read from a local file the seed
 * step wrote into .arena/, never from the repository).
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

const PASSWORD = process.env.VIRA_ADMIN_PASSWORD;
if (!PASSWORD) { console.error('VIRA_ADMIN_PASSWORD required'); process.exit(2); }

let jar = new Map();

function render(spec) {
  const dir = fs.mkdtempSync(path.join(ROOT, '.arena', 'auth-'));
  const file = path.join(dir, 'req.json');
  fs.writeFileSync(file, JSON.stringify({
    method: spec.method || 'GET',
    uri: spec.uri,
    host: 'localhost:8080',
    cookie: [...jar].map(([k, v]) => `${k}=${v}`).join('; '),
    post_body: spec.body || '',
    content_type: spec.body ? 'application/x-www-form-urlencoded' : '',
    accept: 'text/html',
  }));
  return new Promise((resolve) => {
    const child = spawn(NODE, [PHP_CLI, RENDERER, file], { cwd: ROOT, env: { ...process.env, PHP_WASM_PATH } });
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
      const cookies = [];
      for (const line of clean.slice(0, vi).split('\n')) {
        const m = line.match(/^VIRA_HEADER ([^:]+):\s*(.*)$/);
        if (!m) continue;
        const k = m[1].toLowerCase(), v = m[2];
        if (k === 'set-cookie') cookies.push(v);
        headers[k] = headers[k] ? headers[k] + ' | ' + v : v;
      }
      for (const c of cookies) {
        const [pair] = c.split(';');
        const i = pair.indexOf('=');
        if (i > 0) jar.set(pair.slice(0, i).trim(), pair.slice(i + 1).trim());
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

// 1. login
const page = await render({ uri: '/login' });
const token = (page.body.match(/name="_token" value="([^"]+)"/) || [])[1];

const login = await render({
  method: 'POST', uri: '/login',
  body: `_token=${encodeURIComponent(token)}&username=admin&password=${encodeURIComponent(PASSWORD)}`,
});
const loggedIn = login.status === 302 && !/\/login/.test(login.headers.location || '');
check('T-AUTH-06', loggedIn, `POST valid creds -> ${login.status} -> ${login.headers.location}`);

// 2. dashboard (may redirect to password change because must_change_password=true)
const dash = await render({ uri: '/' });
const forcedPw = dash.status === 302 && /password/.test(dash.headers.location || '');
check('T-AUTH-07', forcedPw || dash.status === 200,
  `GET / after login -> ${dash.status} ${dash.headers.location || ''} (forced password change = ${forcedPw})`);

// 3. change the password (required before anything else)
const pwPage = await render({ uri: '/password' });
const pwToken = (pwPage.body.match(/name="_token" value="([^"]+)"/) || [])[1];
check('T-AUTH-08', pwPage.status === 200 && !!pwToken, `GET /password -> ${pwPage.status}`);

const newPassword = PASSWORD + 'Xy9';
const change = await render({
  method: 'POST', uri: '/password',
  body: `_token=${encodeURIComponent(pwToken)}&_method=PUT`
      + `&current_password=${encodeURIComponent(PASSWORD)}`
      + `&password=${encodeURIComponent(newPassword)}`
      + `&password_confirmation=${encodeURIComponent(newPassword)}`,
});
check('T-AUTH-09', change.status === 302 && !/password/.test(change.headers.location || ''),
  `PUT /password -> ${change.status} -> ${change.headers.location}`);

// 4. every module page now reachable
for (const [id, uri, expect] of [
  ['T-PAGE-01', '/', 200],
  ['T-PAGE-02', '/customers', 200],
  ['T-PAGE-03', '/customers/create', 200],
  ['T-PAGE-04', '/contracts', 200],
  ['T-PAGE-05', '/contracts/create', 200],
]) {
  const r = await render({ uri });
  check(id, r.status === expect, `GET ${uri} -> ${r.status}`);
  if (r.status === 200) {
    const hasShell = /v-sidebar/.test(r.body) && /dir="rtl"/.test(r.body);
    check(id + '-shell', hasShell, `  sidebar+rtl rendered: ${hasShell} (${r.body.length} bytes)`);
  }
}

// 5. 404 for an unknown route
const nf = await render({ uri: '/this-does-not-exist' });
check('T-PAGE-06', nf.status === 404 && /صفحه پیدا نشد/.test(nf.body),
  `GET /nope -> ${nf.status}, persian 404 = ${/صفحه پیدا نشد/.test(nf.body)}`);

// 6. logout
const outToken = ((await render({ uri: '/' })).body.match(/name="_token" value="([^"]+)"/) || [])[1];
const out = await render({ method: 'POST', uri: '/logout', body: `_token=${encodeURIComponent(outToken)}` });
check('T-AUTH-10', out.status === 302 && /login/.test(out.headers.location || ''),
  `POST /logout -> ${out.status} -> ${out.headers.location}`);

const after = await render({ uri: '/customers' });
check('T-AUTH-11', after.status === 302 && /login/.test(after.headers.location || ''),
  `GET /customers after logout -> ${after.status}`);

console.log('\n--- summary ---');
const pass = results.filter(r => r.passed).length;
console.log(`${pass}/${results.length} passed`);
fs.writeFileSync(path.join(ROOT, '.arena', 'new-password.txt'), newPassword);
process.exit(pass === results.length ? 0 : 1);

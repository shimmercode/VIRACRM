#!/usr/bin/env node
/**
 * HTTP-level authorization proof.
 *
 * Logs in as a user holding only the `viewer` role and then requests every
 * privileged URL *directly*. If hiding links were the only control, these
 * would all succeed; they must return 403.
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

const USER = process.env.VIRA_VIEWER_USER || 'viewer1';
const PASSWORD = process.env.VIRA_VIEWER_PASSWORD || 'DevOnly-Viewer-2026x';

let jar = new Map();

function render(spec) {
  const dir = fs.mkdtempSync(path.join(ROOT, '.arena', 'z-'));
  const file = path.join(dir, 'req.json');
  fs.writeFileSync(file, JSON.stringify({
    method: spec.method || 'GET', uri: spec.uri, host: 'localhost:8080',
    cookie: [...jar].map(([k, v]) => `${k}=${v}`).join('; '),
    post_body: spec.body || '',
    content_type: spec.body ? 'application/x-www-form-urlencoded' : '',
    accept: 'text/html',
  }));
  return new Promise((resolve) => {
    const child = spawn(NODE, [PHP_CLI, RENDERER, file], { cwd: ROOT, env: { ...process.env, PHP_WASM_PATH } });
    let out = '';
    child.stdout.on('data', d => out += d);
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
        const k = m[1].toLowerCase(), v = m[2];
        if (k === 'set-cookie') {
          const [pair] = v.split(';');
          const i = pair.indexOf('=');
          if (i > 0) jar.set(pair.slice(0, i).trim(), pair.slice(i + 1).trim());
        }
        headers[k] = headers[k] ? headers[k] + ' | ' + v : v;
      }
      resolve({ status, headers, body: vi === -1 ? clean : clean.slice(vi + 9) });
    });
  });
}

const results = [];
const check = (id, ok, detail) => { results.push({ id, ok }); console.log(`${ok ? 'PASS' : 'FAIL'}  ${id}  ${detail}`); };

// log in as the viewer
const page = await render({ uri: '/login' });
const token = (page.body.match(/name="_token" value="([^"]+)"/) || [])[1];
const login = await render({
  method: 'POST', uri: '/login',
  body: `_token=${encodeURIComponent(token)}&username=${USER}&password=${encodeURIComponent(PASSWORD)}`,
});
check('T-AZ-00', login.status === 302 && !/\/login/.test(login.headers.location || ''),
  `viewer login -> ${login.status} -> ${login.headers.location}`);

// allowed pages
for (const [id, uri] of [['T-AZ-01', '/customers'], ['T-AZ-02', '/contracts'], ['T-AZ-03', '/']]) {
  const r = await render({ uri });
  check(id, r.status === 200, `allowed GET ${uri} -> ${r.status}`);
}

// the create link must NOT be in the HTML (UI respects permission)
const list = await render({ uri: '/customers' });
const hasCreateLink = /href="http:\/\/localhost:8080\/customers\/create"/.test(list.body);
check('T-AZ-04', !hasCreateLink, `create button hidden for viewer: ${!hasCreateLink}`);

// explicit API-level refusals: direct URL access
for (const [id, uri, label] of [
  ['T-AZ-05', '/customers/create', 'customer create form'],
  ['T-AZ-06', '/contracts/create', 'contract create form'],
  ['T-AZ-07', '/password', null], // allowed, sanity that we're authenticated
]) {
  const r = await render({ uri });
  if (label === null) { check(id, r.status === 200, `GET ${uri} -> ${r.status}`); continue; }
  const refused = r.status === 403 && /دسترسی غیرمجاز/.test(r.body);
  check(id, refused, `direct GET ${uri} -> ${r.status}, persian 403 = ${/دسترسی غیرمجاز/.test(r.body)}`);
}

// POST must be refused too, not just the form
const postToken = ((await render({ uri: '/customers' })).body.match(/name="_token" value="([^"]+)"/) || [])[1] || token;
const post = await render({
  method: 'POST', uri: '/customers',
  body: `_token=${encodeURIComponent(postToken)}&type=legal&company_name=Hack+Co&status=lead`,
});
check('T-AZ-08', post.status === 403, `direct POST /customers -> ${post.status}`);

// the manager role must be refused financial data it lacks
const del = await render({ method: 'POST', uri: '/customers/1', body: `_token=${encodeURIComponent(postToken)}&_method=DELETE` });
check('T-AZ-09', del.status === 403, `direct DELETE /customers/1 -> ${del.status}`);

console.log('\n--- summary ---');
const pass = results.filter(r => r.ok).length;
console.log(`${pass}/${results.length} passed`);
process.exit(pass === results.length ? 0 : 1);

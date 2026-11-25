#!/usr/bin/env node
/**
 * asset-basepath-matrix.mjs — what `ASSET_URL=/` does under three deployments.
 *
 * THE QUESTION THIS ANSWERS
 * -------------------------
 * `ASSET_URL=/` makes asset URLs root-relative, which is correct when the app is
 * served at the root of a domain. It is NOT automatically correct for every
 * deployment: a CRM installed at `example.com/crm/` would resolve
 * `/build/css/app.css` to `example.com/build/css/app.css` — outside the
 * application — and render unstyled. That is the same symptom we just fixed,
 * arriving by a different route.
 *
 * So "it works in the preview" is not the claim. The claim has to be scoped, and
 * the scope has to be measured. This script runs the application behind a
 * reverse proxy in three configurations and records which one loads its assets:
 *
 *   A.  served at the domain root              ASSET_URL=/         works
 *   B.  installed under /crm, the mistake       ASSET_URL=/         BROKEN
 *   C.  installed under /crm, configured        ASSET_URL=/crm      works
 *
 * The first attempt at this matrix assumed root-relative URLs "just work"
 * behind a path prefix. They do not, and the measurement said so: at
 * example.com/crm/login the browser resolves `/build/app.css` against the
 * *origin root*, producing example.com/build/app.css — outside the application.
 * That is the original symptom, reached by a different road.
 *
 * So the finding is narrower and more useful than "it works": ASSET_URL must
 * equal the path the application is served from. "/" for a root install,
 * "/crm" for a sub-directory install. The two are not interchangeable, and a
 * deployment that gets it wrong shows an unstyled page rather than an error.
 *
 * WHAT THIS IS NOT
 * ----------------
 * This is a Node reverse proxy and PHP-WASM, not Apache/cPanel on PHP-FPM. It
 * characterises the *URL resolution rule*, which is the part that breaks. It
 * does not prove anything about .htaccess, mod_rewrite, or a real host's
 * handling of SCRIPT_NAME. Those remain unverified until run on the host.
 *
 * Usage: node tools/checks/asset-basepath-matrix.mjs
 */
import fs from 'node:fs';
import http from 'node:http';
import path from 'node:path';
import net from 'node:net';
import { spawn } from 'node:child_process';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const OUT = path.join(ROOT, 'docs/evidence/review/asset-basepath-matrix.json');
const BASE_DB = path.join(ROOT, 'database/database.sqlite');

/*
 * Every server this script starts is registered here and stopped in the
 * `finally` below. Without that, a scenario that throws (a refused connection,
 * a timeout) leaves a listener behind — and behind the preview proxy a stale
 * listener is reachable, which is exactly the bug that put a test server in
 * front of the client. Cleanup must not depend on the happy path.
 */
const RUNNING = [];

const freePort = () => new Promise((resolve, reject) => {
  const s = net.createServer();
  s.unref();
  s.on('error', reject);
  s.listen(0, '0.0.0.0', () => {
    const { port } = s.address();
    s.close(() => resolve(port));
  });
});

const waitUp = async (port) => {
  const until = Date.now() + 60_000;
  while (Date.now() < until) {
    try {
      const r = await fetch(`http://127.0.0.1:${port}/login`, { signal: AbortSignal.timeout(2000) });
      if (r.ok || r.status === 302) return true;
    } catch { /* not yet */ }
    await new Promise((r) => setTimeout(r, 300));
  }
  return false;
};

/**
 * Start an app server with a given ASSET_URL against its own database copy.
 * Each scenario gets a fresh server because ASSET_URL is read at boot.
 */
const startApp = async (name, assetUrl) => {
  const dir = path.join(ROOT, '.arena/flows', `basepath-${name}`);
  fs.rmSync(dir, { recursive: true, force: true });
  fs.mkdirSync(dir, { recursive: true });
  const db = path.join(dir, 'database.sqlite');
  fs.copyFileSync(BASE_DB, db);

  const port = await freePort();
  const env = {
    ...process.env,
    PHP_WASM_PATH: process.env.PHP_WASM_PATH || '/tmp/phpwasm/node_modules',
    DB_DATABASE: db,
  };
  if (assetUrl === null) delete env.ASSET_URL;
  else env.ASSET_URL = assetUrl;

  const server = spawn(process.execPath, [path.join(ROOT, 'tools/dev-server.mjs'), '--port', String(port)], {
    cwd: ROOT, env, stdio: ['ignore', 'pipe', 'pipe'],
  });
  let log = '';
  server.stdout.on('data', (d) => { log += d; });
  server.stderr.on('data', (d) => { log += d; });

  if (!(await waitUp(port))) {
    server.kill('SIGKILL');
    throw new Error(`${name}: app did not start\n${log}`);
  }

  const handle = {
    port,
    close: () => {
      server.kill('SIGTERM');
      try { fs.rmSync(dir, { recursive: true, force: true }); } catch { /* gone */ }
    },
  };
  RUNNING.push(handle);
  return handle;
};

/**
 * A reverse proxy with configurable behaviour, standing in for Apache.
 *
 *   stripPrefix: remove /crm before forwarding (what a well-configured proxy does)
 *   keepPrefix:  forward the full path (what a misconfigured sub-directory does)
 */
const startProxy = (appPort, { prefix = '', strip = true, origin, port = 0 }) => new Promise((resolve) => {
  const server = http.createServer((req, res) => {
    let target = req.url;

    if (prefix && strip) {
      if (!target.startsWith(prefix)) {
        res.writeHead(404, { 'content-type': 'text/plain' });
        res.end(`not under ${prefix}`);
        return;
      }
      target = target.slice(prefix.length) || '/';
    }

    const headers = { ...req.headers, host: origin, 'x-forwarded-host': origin, 'x-forwarded-proto': 'http' };
    delete headers.connection;

    const up = http.request(`http://127.0.0.1:${appPort}${target}`, { method: req.method, headers }, (r) => {
      res.writeHead(r.statusCode, r.headers);
      r.pipe(res);
    });
    up.on('error', (e) => {
      res.writeHead(502, { 'content-type': 'text/plain' });
      res.end('proxy: ' + e.message);
    });
    req.pipe(up);
  });
  server.listen(port, '0.0.0.0', () => {
    RUNNING.push({ port, close: () => new Promise((r) => server.close(r)) });
    resolve(server);
  });
});

const requireFrom = createRequire(path.resolve(process.env.VIRA_PLAYWRIGHT || '/tmp/pwuse', 'noop.js'));
const { chromium } = requireFrom('playwright-core');

const browser = await chromium.launch({
  executablePath: process.env.VIRA_CHROMIUM || '/tmp/chromium/package/chromium-bin',
  args: [
    '--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu',
    '--host-resolver-rules=MAP crm.local 127.0.0.1',
  ],
  env: {
    ...process.env,
    LD_LIBRARY_PATH: process.env.VIRA_CHROMIUM_LIBS || '/tmp/chromium/libs/lib:/tmp/chromium/libs/lib64',
  },
});

/**
 * Load a page and report what the browser actually got.
 * `expectPath` is the path an asset must use to reach the application.
 */
const probe = async (url, expectPath) => {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();

  const assets = [];
  page.on('response', (r) => {
    const t = r.request().resourceType();
    if (['stylesheet', 'script', 'font'].includes(t)) {
      assets.push({ type: t, status: r.status(), path: new URL(r.url()).pathname });
    }
  });

  await page.goto(url, { waitUntil: 'networkidle' });
  await page.waitForTimeout(300);

  const dom = await page.evaluate(() => ({
    href: document.querySelector('link[rel=stylesheet]')?.getAttribute('href') || '',
    sheetsLoaded: [...document.querySelectorAll('link[rel=stylesheet]')].every((l) => l.sheet !== null),
    font: getComputedStyle(document.body).fontFamily.split(',')[0],
  }));

  await ctx.close();

  const css = assets.filter((a) => a.type === 'stylesheet');
  const fonts = assets.filter((a) => a.type === 'font');

  return {
    dom,
    requests: { stylesheets: css.length, fonts: fonts.length },
    allAssetPathsStartWith: expectPath,
    assetPathsOk: assets.length > 0 && assets.every((a) => a.path.startsWith(expectPath)),
    allAsset200: assets.length > 0 && assets.every((a) => a.status === 200),
    styled: dom.sheetsLoaded === true && /Vazirmatn/i.test(dom.font),
  };
};

const results = [];

/* Runs one scenario and always tears down what it started. */
const scenario = async (fn) => {
  try {
    await fn();
  } finally {
    while (RUNNING.length) {
      const h = RUNNING.pop();
      try { await h.close(); } catch { /* already down */ }
    }
  }
};

/* ── A: served at the domain root, ASSET_URL=/ ─────────────────────────── */
await scenario(async () => {
  const app = await startApp('root', '/');
  const proxyPort = await freePort();
  const origin = `crm.local:${proxyPort}`;
  const proxy = await startProxy(app.port, { origin, port: proxyPort });

  const r = await probe(`http://${origin}/login`, '/build/');
  results.push({
    scenario: 'A',
    label: 'domain root · ASSET_URL=/',
    assetUrl: '/',
    expectedWorks: true,
    ...r,
  });

});

/* ── B: installed under /crm with ASSET_URL=/ — the mistake ────────────── */
await scenario(async () => {
  const app = await startApp('prefix-wrong', '/');
  const proxyPort = await freePort();
  const origin = `crm.local:${proxyPort}`;
  const proxy = await startProxy(app.port, { prefix: '/crm', strip: true, origin, port: proxyPort });

  /*
   * The browser asks for /crm/login and the proxy strips /crm before forwarding.
   * The page emits "/build/app.css"; the browser resolves that against the
   * origin root, so it requests /build/app.css — which is not under /crm and
   * never reaches the application. The page arrives unstyled.
   */
  const r = await probe(`http://${origin}/crm/login`, '/crm/build/');
  results.push({
    scenario: 'B',
    label: 'installed under /crm · ASSET_URL=/ (the mistake)',
    assetUrl: '/',
    expectedWorks: false,
    ...r,
  });

});

/* ── C: installed under /crm with ASSET_URL=/crm — correct ─────────────── */
await scenario(async () => {
  const app = await startApp('prefix-right', '/crm');
  const proxyPort = await freePort();
  const origin = `crm.local:${proxyPort}`;
  const proxy = await startProxy(app.port, { prefix: '/crm', strip: true, origin, port: proxyPort });

  const r = await probe(`http://${origin}/crm/login`, '/crm/build/');
  results.push({
    scenario: 'C',
    label: 'installed under /crm · ASSET_URL=/crm (correct)',
    assetUrl: '/crm',
    expectedWorks: true,
    ...r,
  });

});

await browser.close();

const compliant = results.every((r) => r.styled === r.expectedWorks && r.assetPathsOk === r.expectedWorks);

const report = {
  generatedAt: new Date().toISOString(),
  caveat: 'Node reverse proxy + PHP-WASM. Characterises URL resolution, not Apache/'
    + 'cPanel/mod_rewrite behaviour. Production compatibility remains unverified.',
  results,
  compliantWithExpectations: compliant,
  conclusion: {
    rule: 'ASSET_URL must equal the path prefix the application is served from.',
    rootDeployment: 'root install -> ASSET_URL=/ (scenario A)',
    subDirectory: 'sub-directory install -> ASSET_URL=/<prefix> (scenario C)',
    theMistake: 'sub-directory install with ASSET_URL=/ shows an unstyled page '
      + 'and no error (scenario B)',
    productionDefault: 'ASSET_URL unset -> absolute URLs from APP_URL; correct when '
      + 'APP_URL matches the public origin, which is the normal cPanel case',
  },
};

fs.mkdirSync(path.dirname(OUT), { recursive: true });
fs.writeFileSync(OUT, JSON.stringify(report, null, 2));

console.log('\n── base path matrix ───────────────────────────────────');
for (const r of results) {
  console.log(`  ${r.scenario.padEnd(3)} ${r.label.padEnd(48)} `
    + `styled=${String(r.styled).padEnd(5)} pathsOk=${String(r.assetPathsOk).padEnd(5)} `
    + `styled-as-expected=${r.styled === r.expectedWorks ? 'yes' : 'NO'}`);
  console.log(`      css href: ${r.dom.href || '(none)'}`);
}
/*
 * The runner parses "N/M passed". Each scenario counts as one assertion:
 * it passes when the page styled exactly when it was expected to.
 */
const scenarioPass = results.filter((r) => r.styled === r.expectedWorks && r.assetPathsOk === r.expectedWorks).length;
console.log(`\n--- summary ---\n${scenarioPass}/${results.length} passed`);
console.log(`${compliant ? 'MATRIX BEHAVED AS EXPECTED' : 'MATRIX DID NOT BEHAVE AS EXPECTED'}`);
console.log(`evidence: ${path.relative(ROOT, OUT)}`);
process.exit(compliant ? 0 : 1);

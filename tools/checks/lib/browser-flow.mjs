#!/usr/bin/env node
/**
 * VIRA CRM — isolated dev server for the browser suites.
 *
 * The browser suites (layout, accessibility, login, calendar) drive a real HTTP
 * server rather than the PHP renderer directly, so isolating them means
 * isolating the *server*: each suite starts its own `tools/dev-server.mjs` on a
 * private port, pointed at a private copy of the seeded database.
 *
 * Before this existed, the browser suites wrote sessions and audit rows into
 * the shared database. That was invisible until `run-all.mjs` compared row
 * counts before and after and reported `sessions 158 -> 170`. A suite that
 * mutates shared state is exactly what this phase set out to eliminate, so the
 * browser suites got the same treatment as the PHP flows.
 */
import fs from 'node:fs';
import path from 'node:path';
import net from 'node:net';
import { spawn } from 'node:child_process';
import { createRequire } from 'node:module';

import { ROOT } from './flow-harness.mjs';

const BASE_DB = process.env.VIRA_BASE_DB || path.join(ROOT, 'database/database.sqlite');
const FLOW_ROOT = process.env.VIRA_FLOW_ROOT || path.join(ROOT, '.arena/flows');

/** Ask the OS for a free port, then release it. */
function freePort() {
  return new Promise((resolve, reject) => {
    const server = net.createServer();
    server.unref();
    server.on('error', reject);
    server.listen(0, '0.0.0.0', () => {
      const { port } = server.address();
      server.close(() => resolve(port));
    });
  });
}

async function waitForServer(port, timeoutMs = 60_000) {
  const deadline = Date.now() + timeoutMs;

  while (Date.now() < deadline) {
    try {
      const res = await fetch(`http://127.0.0.1:${port}/login`, {
        signal: AbortSignal.timeout(3000),
      });

      if (res.ok || res.status === 302) return true;
    } catch {
      // not up yet
    }

    await new Promise((r) => setTimeout(r, 400));
  }

  return false;
}

/**
 * Start an isolated server + database for a browser suite.
 *
 * Returns { baseUrl, password, chromium, browser, close }.
 */
export async function startBrowserFlow(name, { viewport = { width: 1440, height: 900 } } = {}) {
  const dir = path.join(FLOW_ROOT, name);
  const db = path.join(dir, 'database.sqlite');

  fs.rmSync(dir, { recursive: true, force: true });
  fs.mkdirSync(dir, { recursive: true });
  fs.copyFileSync(BASE_DB, db);
  for (const suffix of ['-wal', '-shm']) {
    if (fs.existsSync(BASE_DB + suffix)) fs.copyFileSync(BASE_DB + suffix, db + suffix);
  }

  // Give this suite its own admin password, in this database only.
  const password = `Browser-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`;
  const { php } = await import('./flow-harness.mjs');
  const prepared = await php(path.join(ROOT, 'tools/checks/flow-user.php'), [db, 'admin', password, '0']);

  if (!/^OK /m.test(prepared.out)) {
    throw new Error(`could not prepare admin for ${name}: ${prepared.out}${prepared.err}`);
  }

  const port = await freePort();
  const server = spawn(process.execPath, [
    path.join(ROOT, 'tools/dev-server.mjs'),
    '--port', String(port),
  ], {
    cwd: ROOT,
    env: {
      ...process.env,
      PHP_WASM_PATH: process.env.PHP_WASM_PATH || '/tmp/phpwasm/node_modules',
      // The renderer forwards this to every request it handles.
      DB_DATABASE: db,
    },
    stdio: ['ignore', 'pipe', 'pipe'],
  });

  let serverLog = '';
  server.stdout.on('data', (d) => { serverLog += d; });
  server.stderr.on('data', (d) => { serverLog += d; });

  const up = await waitForServer(port);

  if (!up) {
    server.kill('SIGKILL');
    throw new Error(`${name}: dev server did not start on :${port}\n${serverLog}`);
  }

  const requireFrom = createRequire(
    path.resolve(process.env.VIRA_PLAYWRIGHT || '/tmp/pwuse', 'noop.js')
  );

  let chromium;
  try {
    ({ chromium } = requireFrom('playwright-core'));
  } catch {
    server.kill('SIGKILL');
    throw new Error('playwright-core not found. Set VIRA_PLAYWRIGHT.');
  }

  const browser = await chromium.launch({
    executablePath: process.env.VIRA_CHROMIUM || '/tmp/chromium/package/chromium-bin',
    args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu', '--font-render-hinting=none'],
    env: {
      ...process.env,
      LD_LIBRARY_PATH: process.env.VIRA_CHROMIUM_LIBS
        || '/tmp/chromium/libs/lib:/tmp/chromium/libs/lib64',
    },
  });

  return {
    baseUrl: `http://127.0.0.1:${port}`,
    password,
    db,
    chromium,
    browser,
    context: await browser.newContext({ viewport }),
    async close() {
      try { await browser.close(); } catch { /* already gone */ }
      server.kill('SIGTERM');
      await new Promise((r) => setTimeout(r, 250));
      try { server.kill('SIGKILL'); } catch { /* already gone */ }
      fs.rmSync(dir, { recursive: true, force: true });
    },
  };
}

/** Log in through the UI, leaving the session cookie in the context. */
export async function uiLogin(page, baseUrl, username, password) {
  await page.goto(`${baseUrl}/login`, { waitUntil: 'domcontentloaded' });
  await page.fill('#username', username);
  await page.fill('#password', password);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.click('button[type=submit]'),
  ]);
}

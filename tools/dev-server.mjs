#!/usr/bin/env node
/**
 * VIRA CRM — development HTTP server.
 *
 * Renders every request through the real Laravel kernel inside the PHP-WASM
 * interpreter, so what you see in a browser is the actual Blade output of the
 * actual application, not a mock-up.
 *
 * WHY NOT php -S: there is no native php binary in this environment.
 * WHY NOT php://input: the WASM CLI SAPI does not populate it, so POST bodies
 * are handed to tools/http-render.php through an environment variable.
 *
 * This is a development/preview harness. It is not evidence of host
 * compatibility and is not deployed.
 *
 *   node tools/dev-server.mjs [--port 8080]
 */
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const NODE = process.execPath;
const PHP_CLI = path.join(ROOT, 'docs/tools/php-cli.mjs');
const RENDERER = path.join(ROOT, 'tools/http-render.php');
const PHP_WASM_PATH = process.env.PHP_WASM_PATH || '/tmp/phpwasm/node_modules';

const portArg = (() => {
  const i = process.argv.indexOf('--port');
  return i !== -1 ? Number(process.argv[i + 1]) : Number(process.env.PORT || 8080);
})();

const MIME = {
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.mjs': 'text/javascript; charset=utf-8',
  '.woff2': 'font/woff2',
  '.woff': 'font/woff',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.ico': 'image/x-icon',
  '.json': 'application/json',
  '.webp': 'image/webp',
};

/** Serve a file from public/ if it exists. */
function serveStatic(pathname, res) {
  const safe = path.normalize(pathname).replace(/^(\.\.[/\\])+/, '');
  const filePath = path.join(ROOT, 'public', safe);

  if (!filePath.startsWith(path.join(ROOT, 'public'))) {
    return false;
  }
  if (!fs.existsSync(filePath) || !fs.statSync(filePath).isFile()) {
    return false;
  }

  const ext = path.extname(filePath).toLowerCase();
  res.writeHead(200, {
    'Content-Type': MIME[ext] || 'application/octet-stream',
    'Cache-Control': 'public, max-age=3600',
  });
  fs.createReadStream(filePath).pipe(res);
  return true;
}

function readBody(req) {
  return new Promise((resolve) => {
    const chunks = [];
    req.on('data', (c) => chunks.push(c));
    req.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')));
  });
}

/**
 * Run one request through PHP and parse the framed output.
 *
 * The request is handed over as a JSON file, not as environment variables.
 * The PHP-WASM runtime does not inherit the parent environment (getenv()
 * returns false for everything), so an env-based handover silently produced a
 * request for "/" no matter what the browser asked for. A file path in argv is
 * the one channel that works.
 */
function renderRequest({ method, uri, cookie, body, contentType, host, accept, userAgent }) {
  const specPath = path.join(
    fs.mkdtempSync(path.join(ROOT, '.arena', 'req-')),
    'request.json'
  );

  fs.writeFileSync(
    specPath,
    JSON.stringify({
      method,
      uri,
      host: host || 'localhost',
      cookie: cookie || '',
      post_body: body || '',
      content_type: contentType || '',
      accept: accept || 'text/html',
      user_agent: userAgent || 'vira-dev-server',
    })
  );

  return new Promise((resolve) => {
    const child = spawn(NODE, [PHP_CLI, RENDERER, specPath], {
      cwd: ROOT,
      env: { ...process.env, PHP_WASM_PATH },
    });

    let out = '';
    let err = '';
    child.stdout.on('data', (d) => (out += d));
    child.stderr.on('data', (d) => (err += d));

    const timer = setTimeout(() => child.kill('SIGKILL'), 90_000);

    child.on('close', () => {
      clearTimeout(timer);

      // php-cli.mjs writes a marker line to stderr; strip our own framing noise.
      const clean = out.replace(/^\[php-cli\][^\n]*\n?/gm, '');

      const statusMatch = clean.match(/^VIRA_STATUS (\d+)/m);
      const bodyIndex = clean.indexOf('VIRA_BODY\n');

      if (!statusMatch || bodyIndex === -1) {
        resolve({
          status: 500,
          headers: { 'content-type': 'text/plain; charset=utf-8' },
          body: `Renderer produced no framed response.\n\nSTDOUT:\n${clean.slice(0, 4000)}\n\nSTDERR:\n${err.slice(0, 4000)}`,
        });
        return;
      }

      const headerBlock = clean.slice(0, bodyIndex);
      const headers = {};

      for (const line of headerBlock.split('\n')) {
        const m = line.match(/^VIRA_HEADER ([^:]+):\s*(.*)$/);
        if (!m) continue;

        const key = m[1].toLowerCase();
        const value = m[2];

        // `set-cookie` is the one header Node refuses to accept as a single
        // newline-joined string ("Invalid character in header content").
        // Multiple cookies must be passed to writeHead() as an array.
        if (key === 'set-cookie') {
          headers[key] = headers[key] ? [...headers[key], value] : [value];
          continue;
        }

        headers[key] = headers[key] ? `${headers[key]}, ${value}` : value;
      }

      resolve({
        status: Number(statusMatch[1]),
        headers,
        body: clean.slice(bodyIndex + 'VIRA_BODY\n'.length),
      });
    });
  });
}

const server = http.createServer(async (req, res) => {
  const url = new URL(req.url, `http://${req.headers.host || 'localhost'}`);
  const pathname = decodeURIComponent(url.pathname);

  // Static assets first: fonts and CSS must not pay the PHP boot cost.
  if (serveStatic(pathname, res)) {
    return;
  }

  const body = await readBody(req);

  try {
    const result = await renderRequest({
      method: req.method,
      uri: req.url,
      cookie: req.headers.cookie,
      body,
      contentType: req.headers['content-type'],
      host: req.headers.host,
      accept: req.headers.accept,
      userAgent: req.headers['user-agent'],
    });

    const outHeaders = { ...result.headers };
    delete outHeaders['transfer-encoding'];
    delete outHeaders['content-length'];
    delete outHeaders.connection;

    res.writeHead(result.status, outHeaders);
    res.end(result.body);
  } catch (err) {
    res.writeHead(500, { 'Content-Type': 'text/plain; charset=utf-8' });
    res.end(`Dev server error: ${err?.message ?? err}`);
  }
});

server.listen(portArg, '0.0.0.0', () => {
  console.log(`VIRA dev server → http://0.0.0.0:${portArg}`);
  console.log(`root: ${ROOT}`);
  console.log(`php:  ${PHP_WASM_PATH} (WASM 8.3) — development harness only`);
});

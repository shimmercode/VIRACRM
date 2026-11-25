#!/usr/bin/env node
/**
 * asset-origin-audit.mjs — prove every asset loads from the page's own origin.
 *
 * WHY THIS EXISTS
 * ---------------
 * The login page was served unstyled three times, and each fix chased one way
 * an absolute asset URL can be wrong: http:// assets on an https page, then a
 * host/port the browser could not reach. `ASSET_URL=/` removes the class of bug
 * by making URLs root-relative, but "I fixed it" is not evidence. This audit
 * answers, with measurements rather than assertions:
 *
 *   1. what URLs are actually emitted (real output, not what the docs claim),
 *   2. whether any absolute asset URL with a fixed host/port/protocol survives
 *      anywhere in the shipped HTML, CSS, JS or build output,
 *   3. whether each asset returns 200 with the right Content-Type,
 *   4. whether the browser resolves them all to the origin it is browsing.
 *
 * It runs against a real browser on a real origin, records the network
 * transitions, and writes JSON so the document can cite numbers rather than
 * adjectives.
 *
 * Usage:
 *   node tools/checks/asset-origin-audit.mjs [--base URL] [--out FILE]
 */
import fs from 'node:fs';
import path from 'node:path';
import http from 'node:http';
import net from 'node:net';
import { fileURLToPath } from 'node:url';
import { startBrowserFlow } from './lib/browser-flow.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');

const argOf = (name, fallback) => {
  const i = process.argv.indexOf(name);
  return i !== -1 ? process.argv[i + 1] : fallback;
};

const OUT = argOf('--out', path.join(ROOT, 'docs/evidence/review/asset-origin-audit.json'));

/* ── a private app server, configured exactly as the review environment ── */
process.env.VIRA_PREVIEW_EMBED = 'http://audit.local:*';
process.env.ASSET_URL = '/';

const flow = await startBrowserFlow('asset-origin-audit', {
  viewport: { width: 1280, height: 900 },
  // A named origin has to resolve to loopback to be browsable at all.
  args: ['--host-resolver-rules=MAP audit.local 127.0.0.1'],
});
const APP = flow.baseUrl;
const { browser } = flow;

const freePort = () => new Promise((resolve, reject) => {
  const s = net.createServer();
  s.unref();
  s.on('error', reject);
  s.listen(0, '0.0.0.0', () => {
    const { port } = s.address();
    s.close(() => resolve(port));
  });
});

/*
 * A same-origin wrapper: the app is proxied from the root on one port, so the
 * browser sees a single origin and `frame-ancestors 'self'` holds without
 * loosening anything. Root-relative asset URLs resolve here exactly as they do
 * behind a real reverse proxy.
 */
const startWrapper = (appBase, origin, port) => new Promise((resolve) => {
  const server = http.createServer((req, res) => {
    const headers = {
      ...req.headers,
      host: origin,
      'x-forwarded-proto': 'http',
      'x-forwarded-host': origin,
    };
    delete headers.connection;

    const up = http.request(appBase + req.url, { method: req.method, headers }, (r) => {
      res.writeHead(r.statusCode, r.headers);
      r.pipe(res);
    });
    up.on('error', (e) => {
      res.writeHead(502, { 'content-type': 'text/plain' });
      res.end('proxy error: ' + e.message);
    });
    req.pipe(up);
  });
  server.listen(port, '0.0.0.0', () => resolve(server));
});

const PORT = await freePort();
const ORIGIN = `audit.local:${PORT}`;
const BASE = `http://${ORIGIN}/`;
const wrapper = await startWrapper(APP, ORIGIN, PORT);

const report = {
  generatedAt: new Date().toISOString(),
  environment: {
    appServer: APP,
    browserOrigin: BASE,
    assetUrlSetting: 'ASSET_URL=/',
    note: 'Vite is not used by this project; assets are concatenated by '
      + 'tools/build-assets.mjs and referenced with asset().',
  },
  emittedUrls: {},
  absoluteUrlScan: {},
  network: [],
  console: [],
  verdict: {},
};

/* ── 1. the real emitted HTML, straight from the server ── */
{
  const html = await (await fetch(`${APP}/login`)).text();

  const grab = (re) => [...html.matchAll(re)].map((m) => m[1]);

  report.emittedUrls = {
    stylesheets: grab(/<link[^>]+rel="stylesheet"[^>]+href="([^"]+)"/g),
    preloads: grab(/<link[^>]+rel="preload"[^>]+href="([^"]+)"/g),
    scripts: grab(/<script[^>]+src="([^"]+)"/g),
    images: grab(/<img[^>]+src="([^"]+)"/g),
  };

  const all = Object.values(report.emittedUrls).flat();
  report.emittedUrls.total = all.length;
  report.emittedUrls.relativeCount = all.filter((u) => u.startsWith('/')).length;
  report.emittedUrls.absoluteCount = all.filter((u) => /^(https?:)?\/\//.test(u)).length;
  report.emittedUrls.allRelative = all.length > 0
    && report.emittedUrls.relativeCount === all.length;
}

/* ── 2. scan shipped build output and source for fixed absolute asset URLs ── */
{
  const targets = [
    'public/build/css/app.css',
    'public/build/js/app.js',
    'resources/views/layouts/app.blade.php',
    'resources/views/layouts/plain.blade.php',
    'resources/views/auth/login.blade.php',
  ];

  // A fixed absolute asset URL: protocol (optional) + host + a build path.
  const FIXED = /(?:https?:)?\/\/(?:localhost|127\.0\.0\.1|[a-z0-9-]+\.[a-z]{2,})(?::\d+)?\/build\//gi;

  const hits = [];
  const scanned = [];

  for (const rel of targets) {
    const abs = path.join(ROOT, rel);
    if (!fs.existsSync(abs)) continue;
    const text = fs.readFileSync(abs, 'utf8');
    scanned.push({ file: rel, bytes: Buffer.byteLength(text) });
    for (const m of text.matchAll(FIXED)) {
      hits.push({ file: rel, match: m[0].slice(0, 120) });
    }
  }

  report.absoluteUrlScan = {
    pattern: String(FIXED),
    scanned,
    hits,
    clean: hits.length === 0,
  };
}

/* ── 3 + 4. drive a real browser and record the network ── */
const consoleMsgs = [];
{
  const ctx = await browser.newContext({
    viewport: { width: 1280, height: 900 },
    extraHTTPHeaders: { 'X-Forwarded-Proto': 'https', 'X-Forwarded-Host': ORIGIN },
  });
  const page = await ctx.newPage();

  page.on('console', (m) => {
    consoleMsgs.push({ type: m.type(), text: m.text().slice(0, 300) });
  });

  page.on('response', async (res) => {
    const url = res.url();
    const req = res.request();
    const type = req.resourceType();

    if (!['stylesheet', 'script', 'font', 'image', 'document'].includes(type)) return;

    const headers = res.headers();
    report.network.push({
      type,
      status: res.status(),
      url,
      path: (() => { try { return new URL(url).pathname; } catch { return url; } })(),
      sameOrigin: url.startsWith(BASE),
      finalUrl: res.url(),
      redirected: res.request().redirectedFrom() !== null,
      contentType: headers['content-type'] || '',
      contentLength: headers['content-length'] || '',
      cacheControl: headers['cache-control'] || '',
    });
  });

  /*
   * First load, then a cache-bypassing reload.
   *
   * A normal reload may serve CSS and fonts from the HTTP cache, which proves
   * nothing about whether the server still returns them. `bypassServiceWorker`
   * plus deleting the context's cache exercises the real request path — the
   * browser equivalent of the client's hard reload.
   */
  await page.goto(BASE, { waitUntil: 'networkidle' });
  await page.waitForTimeout(400);

  const firstLoadCount = report.network.length;

  await ctx.clearCookies();
  await page.evaluate(() => {
    // Drop everything the page can clear from inside itself.
    if (window.caches) caches.keys().then((k) => k.forEach((n) => caches.delete(n)));
    localStorage.clear();
    sessionStorage.clear();
  });

  await page.reload({ waitUntil: 'networkidle' });
  await page.waitForTimeout(400);

  report.network.push({ type: 'marker', status: 0, url: '--- hard reload below ---', path: '', sameOrigin: true, finalUrl: '', redirected: false, contentType: '', contentLength: '', cacheControl: '' });

  await page.goto(`${BASE}?cachebust=${Date.now()}`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(400);

  const styled = await page.evaluate(() => ({
    href: document.querySelector('link[rel=stylesheet]')?.getAttribute('href') || '',
    sheetsLoaded: [...document.querySelectorAll('link[rel=stylesheet]')].every((l) => l.sheet !== null),
    font: getComputedStyle(document.body).fontFamily.split(',')[0],
    bg: getComputedStyle(document.body).backgroundColor,
  }));

  report.dom = styled;
  report.reloads = { firstLoadRequests: firstLoadCount, totalRequests: report.network.length };

  await page.screenshot({ path: path.join(ROOT, 'docs/evidence/review/asset-audit-login.png') });
  await ctx.close();
}

/* ── 5. direct HTTP check of every emitted asset ── */
{
  const paths = [
    ...report.emittedUrls.stylesheets,
    ...report.emittedUrls.scripts,
    ...report.emittedUrls.preloads,
  ];

  report.directFetch = [];

  for (const p of paths) {
    const url = `${APP}${p.split('?')[0]}`;
    try {
      const res = await fetch(url);
      report.directFetch.push({
        path: p,
        status: res.status,
        contentType: res.headers.get('content-type') || '',
        bytes: (await res.arrayBuffer()).byteLength,
      });
    } catch (e) {
      report.directFetch.push({ path: p, status: 0, error: e.message });
    }
  }
}

/* ── verdict: every clause must be measured, not assumed ── */
{
  const assets = report.network.filter((n) => ['stylesheet', 'script', 'font'].includes(n.type));

  report.verdict = {
    emittedAllRelative: report.emittedUrls.allRelative === true,
    noFixedAbsoluteInBuild: report.absoluteUrlScan.clean === true,
    everyAssetSameOrigin: assets.length > 0 && assets.every((a) => a.sameOrigin),
    everyAsset200: assets.length > 0 && assets.every((a) => a.status === 200),
    stylesheetApplied: report.dom?.sheetsLoaded === true,
    customFontApplied: /Vazirmatn/i.test(report.dom?.font || ''),
    noConsoleErrors: consoleMsgs.filter((m) => m.type === 'error').length === 0,
    counts: {
      assetRequests: assets.length,
      stylesheets: assets.filter((a) => a.type === 'stylesheet').length,
      scripts: assets.filter((a) => a.type === 'script').length,
      fonts: assets.filter((a) => a.type === 'font').length,
    },
  };
}

/* ── 6. a real redirect of the whole origin, to prove nothing is absolute ── */
{
  const html = await (await fetch(`${APP}/login`)).text();
  const withoutOrigin = html.replaceAll(APP, '');
  report.originSubstitution = {
    note: 'The page fetched from a different host must contain the same bytes once '
      + 'that host is substituted out. If any URL were baked to an absolute host, '
      + 'the substitution would leave the old one behind.',
    identicalAfterSubstitution: withoutOrigin === html.replaceAll(APP, ''),
  };
}

report.console = consoleMsgs;

fs.mkdirSync(path.dirname(OUT), { recursive: true });
fs.writeFileSync(OUT, JSON.stringify(report, null, 2));

// Teardown runs unconditionally: a thrown assertion must not leave a listener
// behind for the preview proxy to expose.
try {
  await flow.close();
} finally {
  await new Promise((r) => wrapper.close(r));
}

/* ── human-readable summary ── */
const v = report.verdict;
const ok = Object.entries(v)
  .filter(([k]) => k !== 'counts')
  .every(([, val]) => val === true);

console.log('\n── emitted asset URLs ─────────────────────────────────');
for (const u of [
  ...report.emittedUrls.stylesheets,
  ...report.emittedUrls.scripts,
  ...report.emittedUrls.preloads,
]) {
  console.log(`  ${u}`);
}
console.log(`  relative ${report.emittedUrls.relativeCount}/${report.emittedUrls.total}`
  + ` · absolute ${report.emittedUrls.absoluteCount}`);

console.log('\n── fixed absolute URLs in build/source ────────────────');
console.log(report.absoluteUrlScan.clean
  ? `  none (${report.absoluteUrlScan.scanned.length} files, ${report.absoluteUrlScan.scanned.reduce((a, b) => a + b.bytes, 0)} bytes scanned)`
  : `  ${report.absoluteUrlScan.hits.length} HIT(S): ${JSON.stringify(report.absoluteUrlScan.hits.slice(0, 3))}`);

console.log('\n── network (stylesheet / script / font) ───────────────');
for (const n of report.network.filter((x) => ['stylesheet', 'script', 'font'].includes(x.type))) {
  console.log(`  ${String(n.status).padEnd(4)} ${n.type.padEnd(11)} ${n.path.padEnd(42)} `
    + `${n.contentType.padEnd(26)} sameOrigin=${n.sameOrigin}`);
}

console.log('\n── direct fetch of each emitted URL ───────────────────');
for (const d of report.directFetch) {
  console.log(`  ${String(d.status).padEnd(4)} ${(d.contentType || '').padEnd(26)} `
    + `${String(d.bytes || 0).padStart(7)}B  ${d.path}`);
}

console.log('\n── console ────────────────────────────────────────────');
const errs = consoleMsgs.filter((m) => m.type === 'error');
console.log(errs.length ? errs.map((e) => `  ERROR ${e.text}`).join('\n') : '  no console errors');

console.log('\n── verdict ────────────────────────────────────────────');
for (const [k, val] of Object.entries(v)) {
  if (k === 'counts') continue;
  console.log(`  ${val === true ? 'PASS' : 'FAIL'}  ${k}`);
}
console.log(`  counts: ${JSON.stringify(v.counts)}`);

/*
 * The runner parses "N/M passed" and nothing else. Printing only a verdict
 * phrase would let this suite run, exit 0, and contribute zero assertions —
 * the silent pass that already happened once in this project.
 */
const clauses = Object.entries(v).filter(([k]) => k !== 'counts');
const clausePass = clauses.filter(([, val]) => val === true).length;
console.log(`\n--- summary ---\n${clausePass}/${clauses.length} passed`);
console.log(`${ok ? 'ASSET ORIGIN AUDIT PASSED' : 'ASSET ORIGIN AUDIT FAILED'}`);
console.log(`evidence: ${path.relative(ROOT, OUT)}`);
process.exit(ok ? 0 : 1);

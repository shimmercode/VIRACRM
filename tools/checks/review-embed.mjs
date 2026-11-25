#!/usr/bin/env node
/**
 * Proves the review environment is actually viewable in a browser — not just
 * that curl gets a 200.
 *
 * THE FAILURE THIS GUARDS
 * -----------------------
 * Every request returned 200 for a while when the environment was unusable:
 * the page was served with `X-Frame-Options: DENY`, so the preview iframe
 * refused it, and every asset URL was `http://`, so an https page blocked the
 * stylesheet and rendered raw unstyled HTML. Neither shows up in a status code.
 * The client reported it as "the environment opens nothing", then as "the login
 * page has no UI".
 *
 * So this asserts what a person actually needs:
 *   1. the page loads inside an iframe,
 *   2. the stylesheet applies (the body renders in the product's own font),
 *   3. sign-in works,
 *   4. a preview page renders its banner and breadcrumb.
 *
 * ISOLATION
 * ---------
 * The suite starts its own app server on a free port and its own wrapper server
 * on another, and shuts both down. A previous version pointed at a fixed port,
 * which meant a leaked test server stayed reachable through the preview proxy
 * and the client opened it by accident.
 *
 * Usage: node tools/checks/review-embed.mjs
 */
import fs from 'node:fs';
import http from 'node:http';
import path from 'node:path';
import net from 'node:net';
import { fileURLToPath } from 'node:url';
import { startBrowserFlow } from './lib/browser-flow.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');

const results = [];
const record = (name, ok, detail) => {
  results.push({ name, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name.padEnd(46)} ${detail}`);
};

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
 * A stand-in for the preview panel.
 *
 * Everything except the outer document is proxied to the app, from the root.
 * Proxying only a sub-path would serve this wrapper's HTML in place of the
 * app's root-relative "/build/css/app.css" and the page would render unstyled —
 * a harness artefact indistinguishable from the real regression.
 *
 * The wrapper and the frame share one origin, so `frame-ancestors 'self'` is
 * satisfied honestly rather than by loosening the policy for the test.
 */
const SHELL = `<!doctype html><html><head>
<style>html,body{margin:0;height:100%}iframe{width:100%;height:1000px;border:0}</style>
</head><body><iframe src="/login"></iframe></body></html>`;

const startWrapper = (appBase, origin, port = 0) => new Promise((resolve) => {
  const server = http.createServer((req, res) => {
    if (req.url === '/' || req.url === '/__shell') {
      res.writeHead(200, { 'content-type': 'text/html; charset=utf-8' });
      res.end(SHELL);
      return;
    }

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

process.env.VIRA_PREVIEW_EMBED = 'http://wrapper.local:*';

const flow = await startBrowserFlow('review-embed', {
  viewport: { width: 1280, height: 1000 },
  // The wrapper must be a real origin the CSP can name, so it is browsed by
  // hostname with an explicit mapping to loopback.
  args: ['--host-resolver-rules=MAP wrapper.local 127.0.0.1'],
});
const { browser } = flow;
const PASSWORD = flow.password;

/*
 * Pick the port first, then start once.
 *
 * The forwarded host must carry the port: without it the app emits
 * `http://wrapper.local/build/...` on the default port, the asset request goes
 * nowhere, and the page renders unstyled — a harness bug that looks exactly
 * like the real mixed-content failure. Choosing the port before the server
 * starts keeps the origin and the listener in agreement.
 */
const WRAPPER_PORT = await freePort();
const WRAPPER_ORIGIN = `wrapper.local:${WRAPPER_PORT}`;
const WRAPPER = `http://${WRAPPER_ORIGIN}/`;
const wrapper = await startWrapper(flow.baseUrl, WRAPPER_ORIGIN, WRAPPER_PORT);

const ctx = await browser.newContext({
  viewport: { width: 1280, height: 1000 },
  extraHTTPHeaders: { 'X-Forwarded-Proto': 'https', 'X-Forwarded-Host': 'wrapper.local' },
});
const page = await ctx.newPage();

const consoleErrors = [];
page.on('console', (m) => { if (m.type() === 'error') consoleErrors.push(m.text()); });

await page.goto(WRAPPER, { waitUntil: 'domcontentloaded' });

/*
 * Wait for the child frame to commit a URL.
 *
 * frames() lists a frame as soon as the element is parsed, with an empty URL
 * until navigation commits. Reading it too early reports "refused" for a page
 * that is merely still loading — the same false negative that made this look
 * like a CSP problem long after the CSP was correct.
 */
const frame = await page.waitForFunction(() => {
  const el = document.querySelector('iframe');
  return el && el.contentWindow && el.contentWindow.location.href.includes('/login');
}, null, { timeout: 25_000 })
  .then(() => page.frames().find((f) => f.url().includes('/login')))
  .catch(() => null);

record('page loads inside an iframe', !!frame,
  frame ? 'frame attached'
    : `refused (saw: ${page.frames().map((f) => f.url()).join(' | ') || 'none'})`);

if (frame) {
  await frame.waitForLoadState('domcontentloaded').catch(() => {});

  const styled = await frame.evaluate(() => {
    const body = getComputedStyle(document.body);
    const links = [...document.querySelectorAll('link[rel=stylesheet]')];
    return {
      sheets: links.length,
      loaded: links.every((l) => l.sheet !== null),
      font: body.fontFamily,
      bg: body.backgroundColor,
    };
  });

  record('stylesheet loads (no mixed content)', styled.loaded && styled.sheets > 0,
    `${styled.sheets} sheet(s), applied=${styled.loaded}`);

  /*
   * Assert the custom face won, not merely that some font is set. An earlier
   * version asked "does the stack avoid serif", which matches "sans-serif" and
   * failed on a fully styled page — a check that was wrong about its subject.
   */
  record('page is styled (custom font applied)', /Vazirmatn/i.test(styled.font),
    `font=${styled.font.split(',')[0]}, bg=${styled.bg}`);

  try {
    /*
     * Submit with requestSubmit() rather than click(). Inside a frame, click()
     * races the navigation and Playwright can report the frame's URL as still
     * /login after the server accepted the credentials.
     */
    await frame.fill('input[name=username]', 'admin');
    await frame.fill('input[name=password]', PASSWORD);
    await Promise.all([
      frame.waitForNavigation({ waitUntil: 'domcontentloaded' }).catch(() => {}),
      frame.evaluate(() => document.querySelector('form').requestSubmit()),
    ]);
    await frame.waitForTimeout(400);

    record('sign-in works in the browser', !frame.url().includes('/login'),
      frame.url().replace(`http://wrapper.local:${WRAPPER_PORT}`, '') || '/');

    await frame.goto(`${WRAPPER}review`);
    const chrome = await frame.evaluate(() => ({
      banner: !!document.querySelector('.v-review-banner'),
      crumb: !!document.querySelector('.v-review-crumb'),
      title: document.title,
    }));

    record('catalogue renders', /VIRA|ويرا/.test(chrome.title), `title: ${chrome.title}`);

    /*
     * The index is the top of the review surface, so it carries no breadcrumb;
     * the preview pages do. Assert each where it belongs rather than demanding
     * a crumb on a page that is the destination.
     */
    await frame.goto(`${WRAPPER}review/preview/roles`, { waitUntil: 'domcontentloaded' });
    const preview = await frame.evaluate(() => ({
      banner: !!document.querySelector('.v-review-banner'),
      crumb: !!document.querySelector('.v-review-crumb'),
    }));
    record('preview page renders its banner', preview.banner, 'v-review-banner present');
    record('preview page has a way back', preview.crumb, 'breadcrumb present');
  } catch (err) {
    record('signed-in flow', false, err.message.slice(0, 90));
  }
}

/*
 * One console message is expected and harmless here: Chrome ignores
 * Cross-Origin-Opener-Policy on a non-secure origin, and this harness runs on
 * plain http. Filtering it keeps the check meaningful instead of permanently
 * red for a reason that cannot occur behind the real https proxy.
 */
const realErrors = consoleErrors.filter((e) => !/Cross-Origin-Opener-Policy/i.test(e));
record('no console errors on load', realErrors.length === 0,
  realErrors.length ? realErrors[0].slice(0, 90) : 'clean');

await flow.close();
await new Promise((r) => wrapper.close(r));

const failed = results.filter((r) => !r.ok).length;
console.log(`\n--- summary ---\n${results.length - failed}/${results.length} passed`);
process.exit(failed === 0 ? 0 : 1);

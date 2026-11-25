#!/usr/bin/env node
/**
 * Proves the review environment is actually viewable in a browser — not just
 * that curl gets a 200.
 *
 * The failure this guards is subtle: curl showed 200 the whole time the
 * environment was unusable, because the page was served with
 * `X-Frame-Options: DENY` and every asset URL was `http://`. Both are invisible
 * to a status-code check. So this asserts the three things a person needs:
 *
 *   1. The page loads inside an iframe (no frame-ancestors refusal).
 *   2. The stylesheet actually applied (so the app is not raw HTML).
 *   3. A preview page renders its banner and breadcrumb.
 *
 * Usage: node tools/checks/review-embed.mjs
 */
import path from 'node:path';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import { reviewPassword } from './lib/review-credentials.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
// Must be the plain-http server: this harness drives http and the review
// server forces https for the preview proxy. See review-authz.mjs for why.
const BASE = process.env.VIRA_EMBED_APP || 'http://127.0.0.1:4174';
const HOST = 'inho6k4773onmck1wzvls.e2b.app';
const PASSWORD = reviewPassword();

/*
 * The wrapper is served from a host the CSP must allow. Testing a frame from a
 * bare 127.0.0.1 origin would be refused — correctly — because the policy names
 * 'self' and the preview domain, not localhost. So the test browses the wrapper
 * by its own hostname (mapped to loopback via --host-resolver-rules), which is
 * a genuine same-origin embed rather than one that only passes because the
 * policy was loosened.
 */
const WRAPPER_HOST = process.env.VIRA_EMBED_HOST || 'preview.local';
const WRAPPER = `http://${WRAPPER_HOST}:4300/`;

const requireFrom = createRequire(path.resolve(process.env.VIRA_PLAYWRIGHT || '/tmp/pwuse', 'noop.js'));
const { chromium } = requireFrom('playwright-core');

const browser = await chromium.launch({
  executablePath: process.env.VIRA_CHROMIUM || '/tmp/chromium/package/chromium-bin',
  args: [
    '--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu',
    // Resolve the wrapper's hostname to loopback so it is a real origin.
    `--host-resolver-rules=MAP ${process.env.VIRA_EMBED_HOST || 'preview.local'} 127.0.0.1`,
  ],
  env: {
    ...process.env,
    LD_LIBRARY_PATH: process.env.VIRA_CHROMIUM_LIBS || '/tmp/chromium/libs/lib:/tmp/chromium/libs/lib64',
  },
});

const results = [];
const record = (name, ok, detail) => {
  results.push({ name, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name.padEnd(46)} ${detail}`);
};

// Pretend to be the preview proxy: HTTPS origin, forwarded to the dev server.
const ctx = await browser.newContext({
  ignoreHTTPSErrors: true,
  extraHTTPHeaders: {
    // The app is told it is being served over https on the preview host, which
    // is what the real proxy does.
    'X-Forwarded-Proto': 'https',
    'X-Forwarded-Host': WRAPPER_HOST,
    'X-Forwarded-Port': '4300',
  },
});
const page = await ctx.newPage();

const consoleErrors = [];
page.on('console', (m) => { if (m.type() === 'error') consoleErrors.push(m.text()); });

/**
 * Load the page inside a real iframe served from a wrapper document, which is
 * how the preview panel shows it. A frame-ancestors refusal surfaces here as a
 * failed request rather than a silent blank box.
 */
/*
 * The wrapper must be served from a real origin, not setContent(). An
 * about:blank document has an opaque origin and a null base URL, so a relative
 * frame-ancestors source matches nothing and the frame is refused — a false
 * failure that looks exactly like the real one.
 */
await page.goto(WRAPPER, { waitUntil: 'domcontentloaded' });

const appUrl = (suffix = '') => `${WRAPPER.replace(/\/$/, '')}/app${suffix}`;
/*
 * Wait for the child frame to actually commit a URL.
 *
 * frames() lists a frame as soon as the element is parsed, with an empty URL
 * until navigation commits. Reading it too early yields "refused" for a page
 * that is merely still loading — the same false negative that made this feel
 * like a CSP problem long after the CSP was correct.
 */
const frame = await page.waitForFunction(() => {
  const el = document.querySelector('iframe');
  return el && el.contentWindow && el.contentWindow.location.href.includes('/login');
}, null, { timeout: 20_000 }).then(() => page.frames().find((f) => f.url().includes('/login')))
  .catch(() => null);
record('page loads inside an iframe', !!frame,
  frame ? 'frame attached' : `frame refused (saw: ${page.frames().map((f) => f.url()).join(' | ') || 'none'})`);

if (frame) {
  await frame.waitForLoadState('domcontentloaded').catch(() => {});

  // Did the stylesheet load? A mixed-content block leaves the computed font
  // at the browser default, which is the tell for "unstyled HTML".
  const styled = await frame.evaluate(() => {
    const body = getComputedStyle(document.body);
    const links = [...document.querySelectorAll('link[rel=stylesheet]')];
    return {
      sheets: links.length,
      loaded: links.every((l) => l.sheet !== null),
      bg: body.backgroundColor,
      font: body.fontFamily,
    };
  });

  record('stylesheet loads (no mixed content)', styled.loaded && styled.sheets > 0,
    `${styled.sheets} sheet(s), applied=${styled.loaded}`);
  /*
   * Assert the custom face won, not merely that some font is set. The earlier
   * test asked "does the stack avoid serif", which matches "sans-serif" and so
   * failed on a fully styled page — a check that was wrong about its own
   * subject. Comparing the rendered family to Vazirmatn is the real question.
   */
  const fontOk = /Vazirmatn/i.test(styled.font);
  record('page is styled (custom font applied)', fontOk,
    `font=${styled.font.split(',')[0]}`);
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

// Now sign in and open a preview page inside the same frame.
try {
  /*
   * Submit with requestSubmit() rather than click(). Inside a frame, click()
   * races the navigation and Playwright reports the *frame's* final URL as
   * still /login even after the server accepted the credentials. Driving the
   * form submission and then waiting for the frame's own navigation event is
   * deterministic.
   */
  await frame.fill('input[name=username]', 'review.admin');
  await frame.fill('input[name=password]', PASSWORD);
  await Promise.all([
    frame.waitForNavigation({ waitUntil: 'domcontentloaded' }).catch(() => {}),
    frame.evaluate(() => document.querySelector('form').requestSubmit()),
  ]);
  await frame.waitForTimeout(500);

  record('sign-in works in the browser', !frame.url().includes('/login'),
    frame.url().replace(WRAPPER.replace(/\/$/, ''), '') || '/');

  await frame.goto(`${WRAPPER.replace(/\/$/, '')}/review/preview/roles`);
  const chrome = await frame.evaluate(() => ({
    banner: !!document.querySelector('.v-review-banner'),
    crumb: !!document.querySelector('.v-review-crumb'),
    matrix: !!document.querySelector('.v-review-matrix'),
    sidebar: !!document.querySelector('.v-nav, .v-sidebar, nav'),
    title: document.title,
  }));

  record('preview page renders its banner', chrome.banner, 'v-review-banner present');
  record('preview page has a way back', chrome.crumb, 'breadcrumb present');
  record('roles matrix renders', chrome.matrix, 'v-review-matrix present');
  record('layout shell present', chrome.sidebar, `title: ${chrome.title}`);
} catch (err) {
  record('signed-in flow', false, err.message.slice(0, 80));
}

await browser.close();

const failed = results.filter((r) => !r.ok).length;
console.log(`\n--- summary ---\n${results.length - failed}/${results.length} passed`);
process.exit(failed === 0 ? 0 : 1);

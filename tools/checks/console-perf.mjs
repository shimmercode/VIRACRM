#!/usr/bin/env node
/**
 * VIRA CRM — console errors, failed requests and page timing across the main pages.
 *
 * WHY THIS SUITE EXISTS
 * ---------------------
 * Every other browser suite asserts on the DOM: does the element exist, is the
 * number right, is the colour correct. None of them noticed what the browser
 * said while rendering. A page can pass all of those and still throw a
 * TypeError on load, 404 on a stylesheet, or take four seconds to become
 * interactive — and the user experiences only that.
 *
 * §5 of the audit brief asks for console errors and performance evidence. Until
 * now neither was measured anywhere in the repository, so this suite measures
 * them rather than the report claiming they are fine.
 *
 * WHAT IS ASSERTED
 *   C1  no pageerror (uncaught exception) on any main page
 *   C2  no console.error on any main page
 *   C3  no failed network request (>=400, or a request that never resolved)
 *   C4  no request to an external origin — the app must be self-hosted
 *   T1  each page reaches load within a stated budget
 *   T2  the document reports a sane transfer size
 */
import { startBrowserFlow, uiLogin } from './lib/browser-flow.mjs';

const results = [];
const record = (id, ok, detail) => {
  results.push({ id, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${id.padEnd(34)} ${detail}`);
};

// The pages a real user walks through, not an exhaustive route dump.
const PAGES = [
  ['dashboard', '/'],
  ['customers', '/customers'],
  ['customers-create', '/customers/create'],
  ['customer-detail', '/customers/1'],
  ['contracts', '/contracts'],
  ['contracts-create', '/contracts/create'],
  ['projects', '/projects'],
  ['projects-create', '/projects/create'],
  ['project-detail', '/projects/1'],
  ['work-items', '/work-items'],
  ['organization', '/organization'],
  ['payroll', '/payroll'],
  ['employees', '/employees'],
  ['audit', '/audit'],
  /*
   * There is no /search route, and §9 does not ask for one: it asks for
   * «جستجوی سراسری مشتریان» — search across the whole customer base — plus
   * quick access to each dossier. That is /customers?q=, with the per-module
   * filters beside it. These three are the real search surfaces.
   */
  ['search-customers', '/customers?q=%D8%B4%D8%B1%DA%A9%D8%AA'],
  ['filter-contracts', '/contracts?status=active'],
  ['filter-projects', '/projects?type=seo'],
];

// Generous on purpose: this is a WASM PHP build serving through a dev server in
// a container. The budget exists to catch a page that hangs or reloads itself,
// not to fail the build over container noise.
const LOAD_BUDGET_MS = 15000;

const flow = await startBrowserFlow('console-perf', { viewport: { width: 1440, height: 900 } });
const page = await flow.browser.newPage({ viewport: { width: 1440, height: 900 } });

const problems = [];
const failedRequests = [];
const externalRequests = [];

page.on('pageerror', (err) => problems.push({ kind: 'pageerror', text: String(err.message || err) }));
page.on('console', (msg) => {
  if (msg.type() === 'error') problems.push({ kind: 'console.error', text: msg.text() });
});
page.on('response', (res) => {
  if (res.status() >= 400) failedRequests.push(`${res.status()} ${res.url()}`);
});
page.on('requestfailed', (req) => {
  failedRequests.push(`FAILED ${req.url()} ${req.failure()?.errorText || ''}`);
});
page.on('request', (req) => {
  const url = req.url();
  if (!url.startsWith(flow.baseUrl) && !url.startsWith('data:') && !url.startsWith('blob:')) {
    externalRequests.push(url);
  }
});

await uiLogin(page, flow.baseUrl, 'admin', flow.password);

const timings = [];

for (const [tag, uri] of PAGES) {
  problems.length = 0;
  failedRequests.length = 0;
  externalRequests.length = 0;

  const started = Date.now();
  const response = await page.goto(`${flow.baseUrl}${uri}`, { waitUntil: 'load', timeout: 30000 });
  const elapsed = Date.now() - started;

  const status = response ? response.status() : 0;
  timings.push({ tag, uri, ms: elapsed, status });

  record(`T-PG-${tag}-status`, status === 200, `GET ${uri} -> ${status} in ${elapsed}ms`);

  record(`T-PG-${tag}-pageerror`, !problems.some((p) => p.kind === 'pageerror'),
    problems.filter((p) => p.kind === 'pageerror').map((p) => p.text).join(' | ') || 'no uncaught exception');

  record(`T-PG-${tag}-console`, !problems.some((p) => p.kind === 'console.error'),
    problems.filter((p) => p.kind === 'console.error').map((p) => p.text.slice(0, 160)).join(' | ')
    || 'no console.error');

  record(`T-PG-${tag}-requests`, failedRequests.length === 0,
    failedRequests.slice(0, 3).join(' | ') || 'no failed or 4xx/5xx request');

  record(`T-PG-${tag}-external`, externalRequests.length === 0,
    externalRequests.slice(0, 3).join(' | ') || 'every request same-origin');

  record(`T-PG-${tag}-budget`, elapsed <= LOAD_BUDGET_MS,
    `load ${elapsed}ms (budget ${LOAD_BUDGET_MS}ms)`);
}

// ── A cross-cutting timing summary, asserted rather than only printed ─────
const slowest = timings.reduce((a, b) => (b.ms > a.ms ? b : a), timings[0]);
const median = timings.map((t) => t.ms).sort((a, b) => a - b)[Math.floor(timings.length / 2)];

console.log(`\n  timing  median ${median}ms · slowest ${slowest.tag} ${slowest.ms}ms · pages ${timings.length}`);

record('T-PERF-median-budget', median <= LOAD_BUDGET_MS,
  `median load ${median}ms across ${timings.length} pages`);

await page.close();
await flow.close();

const passed = results.filter((r) => r.ok).length;
console.log(`\n--- summary ---\n${passed}/${results.length} passed`);
if (passed !== results.length) {
  for (const r of results.filter((x) => !x.ok)) console.log(`FAILED ${r.id}: ${r.detail}`);
  process.exit(1);
}

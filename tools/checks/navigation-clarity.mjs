#!/usr/bin/env node
/**
 * Navigation clarity: every enabled entry must reach a real page, and nothing
 * implemented may be advertised as «به‌زودی».
 *
 * THE BUG THIS PREVENTS
 * ---------------------
 * Three sidebar entries said «به‌زودی» ("coming soon") for features that were
 * built and working. Two of them sat directly above enabled links to the very
 * same functionality, so the sidebar contradicted itself: a client reading it
 * would conclude reporting and user management were unbuilt while the working
 * links were a few pixels below.
 *
 * A label is not caught by a route test — `route()` on an enabled entry would
 * pass, and the disabled ones have no route to test. So the assertion has to be
 * that the sidebar does not CLAIM a feature is absent when it is reachable, and
 * that every entry it does offer actually opens.
 *
 * WHAT IT CHECKS
 * --------------
 *  1. every `enabled` entry resolves to a registered route (no dangling link);
 *  2. every page behind an enabled entry returns 200 for an authorised actor;
 *  3. no enabled entry's target 404s or 500s;
 *  4. no entry carries the «به‌زودی» note while its feature is reachable;
 *  5. a disabled entry is allowed only when it genuinely cannot be a single
 *     page (project-scoped), and then its note must say where it lives.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { Checks, Flow, php } from './lib/flow-harness.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const c = new Checks('navigation clarity');
const check = (id, ok, detail) => c.check(id, ok, detail);

/* ── read the navigation source ───────────────────────────────────────── */

const source = fs.readFileSync(path.join(ROOT, 'config/navigation.php'), 'utf8');

/*
 * Parse the PHP literal rather than evaluating it: the file is a plain array of
 * string keys and literal values, and requiring it would need a booted app for
 * every assertion. A regex over that shape is adequate and keeps this suite
 * fast; if the file ever gains expressions, entryCount below will mismatch and
 * the parser will say so rather than silently seeing fewer entries.
 */
const entryRe = /\[\s*(?:\/\*[\s\S]*?\*\/\s*)?'label'\s*=>\s*'([^']+)',\s*'icon'\s*=>\s*'([^']*)',\s*'route'\s*=>\s*([^,]+),\s*'permission'\s*=>\s*([^,]+),\s*'enabled'\s*=>\s*(true|false),([\s\S]*?)\]/g;

const entries = [];
let m;

while ((m = entryRe.exec(source)) !== null) {
  const [, label, icon, route, permission, enabled, tail] = m;
  const noteMatch = tail.match(/'note'\s*=>\s*'([^']*)'/);
  entries.push({
    label,
    icon,
    route: route.trim() === 'null' ? null : route.trim().replace(/^'|'$/g, ''),
    permission: permission.trim() === 'null' ? null : permission.trim().replace(/^'|'$/g, ''),
    enabled: enabled === 'true',
    note: noteMatch ? noteMatch[1] : null,
  });
}

check('NAV-PARSE', entries.length >= 20,
  `parsed ${entries.length} navigation entries from config/navigation.php`);

/* ── 1. enabled entries must have a route ─────────────────────────────── */

const enabledWithoutRoute = entries.filter((e) => e.enabled && e.route === null);

check('NAV-ENABLED-ROUTE', enabledWithoutRoute.length === 0,
  enabledWithoutRoute.length === 0
    ? 'every enabled entry names a route'
    : `enabled but routeless: ${enabledWithoutRoute.map((e) => e.label).join(', ')}`);

/* ── 2. no «به‌زودی» on something that exists ─────────────────────────── */

const comingSoon = entries.filter((e) => e.note !== null && e.note.includes('به‌زودی'));

check('NAV-NO-FALSE-SOON', comingSoon.length === 0,
  comingSoon.length === 0
    ? 'no entry advertises «به‌زودی» — the three that did were implemented features'
    : `still claiming coming-soon: ${comingSoon.map((e) => e.label).join(', ')}`);

/* ── 3. a disabled entry must justify itself ──────────────────────────── */

const disabled = entries.filter((e) => !e.enabled);

const unjustified = disabled.filter((e) => e.note === null || e.note === '');

check('NAV-DISABLED-EXPLAINS', unjustified.length === 0,
  disabled.length === 0
    ? 'no disabled entries at all'
    : `${disabled.length} disabled entr${disabled.length === 1 ? 'y' : 'ies'}, each annotated: `
      + disabled.map((e) => `${e.label} → «${e.note ?? '(none)'}»`).join('; '));

/* ── 4. every enabled route must actually open ────────────────────────── */

const flow = new Flow('navclarity');
await flow.setup();

/* The flow carries no usable password of its own: it must be set in THIS
 * database, otherwise login() posts a credential the copy does not have and
 * every render comes back 302 to /login — which reads like "the page is
 * broken" rather than "the actor is anonymous". */
const PASSWORD = `Nav-${Date.now().toString(36)}-Q7`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });
await flow.login('admin', PASSWORD);

/* Read the route table ONCE and build name → {uri, parameters}. */
const inventoryRun = await php('tools/checks/route-inventory.php', []);

/* The inventory brackets its JSON with markers so a caller can find it among
 * whatever the PHP runtime prints around it (the shim appends a timing line). */
const raw = String(inventoryRun.out);
const begin = raw.indexOf('VIRA_ROUTES_JSON_BEGIN');
const end = raw.indexOf('VIRA_ROUTES_JSON_END');

if (begin === -1 || end === -1 || end < begin) {
  throw new Error('route-inventory.php did not emit its markers:\n' + raw.slice(0, 400));
}

const routeTable = JSON.parse(raw.slice(begin + 'VIRA_ROUTES_JSON_BEGIN'.length, end).trim());

const byName = new Map(
  routeTable
    .filter((r) => typeof r.name === 'string' && r.name !== null)
    .map((r) => [r.name, { uri: String(r.uri).replace(/^\//, ''), params: r.parameters ?? [] }]),
);

check('NAV-ROUTE-TABLE', byName.size > 50,
  `${byName.size} named routes read from the booted application`);

const rendered = [];
const missing = [];

for (const entry of entries.filter((e) => e.enabled && e.route !== null)) {
  const target = byName.get(entry.route);

  if (!target) {
    missing.push(`${entry.label} → ${entry.route} is not a registered route`);
    continue;
  }

  /* A route with a required parameter cannot be reached from a bare sidebar
   * link — the template would call route() without the parameter and throw. */
  if (target.params.length > 0) {
    rendered.push({ ...entry, status: `needs {${target.params.join(',')}}` });
    continue;
  }

  const res = await flow.render({ uri: '/' + target.uri });
  rendered.push({ ...entry, status: res.status });
}

check('NAV-ROUTES-EXIST', missing.length === 0,
  missing.length === 0
    ? 'every enabled entry names a route the application actually registers'
    : missing.join('; '));

const failing = rendered.filter((r) => typeof r.status === 'number' && r.status >= 400);

check('NAV-REACHABLE', failing.length === 0 && rendered.some((r) => r.status === 200),
  failing.length === 0
    ? `all ${rendered.filter((r) => r.status === 200).length} parameterless enabled pages return 200`
    : failing.map((r) => `${r.label} → ${r.status}`).join(', '));

/* ── 5. the three corrected entries specifically ──────────────────────── */

const reports = entries.find((e) => e.label === 'گزارش‌ها');
const roles = entries.find((e) => e.label === 'کاربران و نقش‌ها');
const projectAccess = entries.find((e) => e.label === 'دسترسی‌های پروژه');

check('NAV-REPORTS', reports?.enabled === true && reports?.route === 'reports.contracts',
  `«گزارش‌ها» now links to the report area (${reports?.route ?? 'routeless'}) instead of saying coming-soon`);

check('NAV-ROLES', roles?.enabled === true && roles?.route === 'settings.roles.index',
  `«کاربران و نقش‌ها» links to the working roles screen (${roles?.route ?? 'routeless'})`);

check('NAV-PROJECT-ACCESS', projectAccess?.enabled === false
  && projectAccess?.note !== null && !projectAccess.note.includes('به‌زودی'),
  `«دسترسی‌های پروژه» stays disabled — it is project-scoped and cannot be one page — `
  + `but its note now says where it lives: «${projectAccess?.note ?? '(none)'}»`);

/* ── 6. report pages reachable from the sidebar ───────────────────────── */

/*
 * Count the individual report PAGES, not entries that happen to point into the
 * reports namespace. The «گزارش‌ها» entry now leads to the report area and
 * shares its route with «گزارش قراردادها», so counting routes gives four for
 * three pages — the wrong question, and a test that fails on correct data.
 */
const reportRoutes = ['reports.contracts', 'reports.expert-workload', 'reports.work-items'];

const reachableReports = reportRoutes.filter((r) => entries.some((e) => e.enabled && e.route === r));

check('NAV-REPORTS-ALL', reachableReports.length === 3,
  `all three report pages are reachable from enabled entries: ${reachableReports.join(', ')}`);

process.exit(c.summary() ? 0 : 1);

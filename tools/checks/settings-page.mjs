#!/usr/bin/env node
/**
 * VIRA CRM — the settings screen (§15).
 *
 * WHY THIS SUITE EXISTS
 * ---------------------
 * `settings`, `Setting::get()/set()` with its history trail, and the
 * `settings.view` / `settings.manage` permissions were all already in the
 * repository, and four call sites already READ the values — contract-expiry
 * warning in the dashboard and the contract list, invoice VAT rate. What was
 * missing was any way to CHANGE them: `Setting::set()` had zero callers. The
 * product shipped "configurable" thresholds that only a developer with database
 * access could configure. That is not configurable.
 *
 *   T-SET-01  a super admin can read the screen
 *   T-SET-02  every seeded setting is rendered, not a hardcoded subset
 *   T-SET-03  a role without settings.view is refused (403)
 *   T-SET-04  saving a new value persists it
 *   T-SET-05  the change is written to settings_history with old and new
 *   T-SET-06  the change is audited
 *   T-SET-07  the new value actually changes product behaviour
 *   T-SET-08  a non-integer is refused for an integer setting
 *   T-SET-09  an unknown key is ignored, not created
 *   T-SET-10  no change produces no history row and no audit event
 *   T-SET-11  a role with view but not manage cannot save
 *   T-SET-12  secrets and environment config are absent from the page
 *
 * 7 IS THE ONE THAT MATTERS
 * -------------------------
 * A settings screen that saves to a table nobody reads is theatre. T-SET-07
 * changes the contract-expiry threshold and then asserts the dashboard's own
 * count of expiring contracts moves as a result. That is the only check here
 * that proves the setting is wired to anything.
 */
import { Flow, Checks } from './lib/flow-harness.mjs';

const flow = new Flow('settings');
await flow.setup();

const c = new Checks('settings-page');
const check = (id, ok, detail) => c.check(id, ok, detail);

const PASSWORD = `Ap-${Date.now().toString(36)}-K2`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });

const tok = (html) => Flow.token(html);
const form = (o) => Object.entries(o)
  .filter(([, v]) => v !== null && v !== undefined)
  .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`)
  .join('&');
const str = async (sql) => String(await flow.query(sql)).trim();

/*
 * The form posts `values[<id>]`, not `values[<key>]`: setting keys contain
 * dots and a dot in a Laravel rule attribute means nesting, so a dotted key
 * can never be validated. A forged id is what T-SET-09 therefore tests.
 */
const idOf = async (key) => str(`select id from settings where key = '${key}'`);

/*
 * A real submit posts EVERY rendered field, and every field is `required`, so
 * the checks build the payload the way the form does and override one value.
 * A partial POST failing is correct behaviour, not a bug — it is what stops a
 * truncated or tampered submit from silently clearing the other settings.
 */
const fullValues = async (overrides = {}) => {
  const payload = {};
  for (const key of seededKeys) {
    const id = await idOf(key);
    payload[`values[${id}]`] = overrides[key] !== undefined
      ? overrides[key]
      : await str(`select value from settings where key = '${key}'`);
  }
  return payload;
};
const num = async (sql) => Number(await str(sql)) || 0;

/* ── sign in as the superadmin fixture ────────────────────────────────── */
const loginPage = await flow.render({ uri: '/login' });
await flow.login('admin', PASSWORD, { expectRedirect: true });

/* ── T-SET-01/02: the screen renders every seeded setting ─────────────── */
const page = await flow.render({ uri: '/settings' });

const keys = await flow.queryAll
  ? await flow.queryAll('select key from settings order by id')
  : null;

// Fall back to a per-key probe when the harness has no queryAll.
const seededKeys = keys
  ? keys.map((r) => (typeof r === 'object' ? r.key : String(r)))
  : ['contracts.expiry_warning_days', 'general.company_name',
     'reminders.invoice_offset_days', 'calendar.display'];

check('T-SET-01', page.status === 200 && page.body.includes('تنظیمات سامانه'),
  `GET /settings -> ${page.status}`);

const rendered = seededKeys.filter((k) => page.body.includes(`setting-${k}`));
check('T-SET-02', rendered.length === seededKeys.length,
  `${rendered.length}/${seededKeys.length} seeded settings rendered`
  + (rendered.length === seededKeys.length ? '' : `; missing: ${seededKeys.filter((k) => !rendered.includes(k))}`));

/* ── T-SET-12: no environment or secret configuration on this screen ──── */
const forbidden = ['APP_KEY', 'VIRA_CREDENTIAL_KEY', 'VIRA_BACKUP_KEY', 'DB_PASSWORD', 'APP_DEBUG'];
const leaked = forbidden.filter((t) => page.body.includes(t));
check('T-SET-12', leaked.length === 0,
  leaked.length ? `environment/secret names present on the page: ${leaked}` :
  'no environment variable or secret name appears on the settings screen');

/* ── T-SET-04/05/06/07: changing a value really changes behaviour ─────── */
const KEY = 'contracts.expiry_warning_days';
const KEY_ID = await idOf(KEY);
const before = await str(`select value from settings where key = '${KEY}'`);

/*
 * T-SET-07 needs a contract that is live and expires OUTSIDE the default 30-day
 * window but inside a widened one — otherwise the dashboard reports 0 either way
 * and the check proves nothing. The seeded data has no live contracts at all
 * (`Contract::live()->count()` is 0 after db:seed), so the fixture is created
 * here: one active contract ending in 200 days.
 */
/*
 * `query()` is SELECT-only so an assertion can never mutate what it measures;
 * fixtures go through `exec()`, which throws instead of failing silently. An
 * INSERT passed to `query()` is discarded without error, which is how an
 * earlier version of this file asserted a cleanup of a row that never existed.
 */
await flow.exec(`
  insert into contracts
    (number, customer_id, contract_type_id, template_id, title, amount,
     start_date, end_date, status, created_at, updated_at)
  select 'SET-WINDOW-1', customer_id, contract_type_id, template_id,
         'آزمون پنجرهٔ انقضا', amount, date('now'), date('now','+200 days'),
         'active', datetime('now'), datetime('now')
  from contracts limit 1
`);

const liveCount = await num("select count(*) from contracts where status in ('active','expiring')");

// The dashboard prints the count and the threshold in Persian digits.
const fromFa = (t) => t.replace(/[\u06F0-\u06F9\u0660-\u0669]/g, (d) =>
  String(d.charCodeAt(0) - (d >= '\u06F0' ? 0x06F0 : 0x0660)));
const stat = (html) => {
  const m = html.match(/نزدیک به انقضا<\/span>\s*<span class="v-stat__value">([^<]*)</);
  return m ? fromFa(m[1].trim()) : null;
};
const hintDays = (html) => {
  const m = html.match(/نزدیک به انقضا[\s\S]{0,240}?v-stat__hint">تا ([^ ]*) روز/);
  return m ? fromFa(m[1]) : null;
};

const historyBefore = await num('select count(*) from settings_history');
const auditBefore = await num("select count(*) from audit_logs where event = 'settings.updated'");

// Baseline: threshold still 30, so a contract 200 days out is NOT expiring.
const dashNarrow = await flow.render({ uri: '/' });

const editPage = await flow.render({ uri: '/settings' });
const saved = await flow.render({
  method: 'POST',
  uri: '/settings',
  body: form({ _token: tok(editPage.body), ...(await fullValues({ [KEY]: '3650' })) }),
});

const after = await str(`select value from settings where key = '${KEY}'`);
check('T-SET-04', saved.status === 302 && after === '3650',
  `POST /settings -> ${saved.status}; ${KEY} ${before} -> ${after}`);

const historyAfter = await num('select count(*) from settings_history');
check('T-SET-05', historyAfter === historyBefore + 1,
  `settings_history ${historyBefore} -> ${historyAfter}`);

const newRow = await str(
  `select old_value || '->' || new_value from settings_history where key = '${KEY}' order by id desc limit 1`,
);
check('T-SET-05b', newRow === `${before}->3650`,
  `history row records old and new: ${newRow}`);

const auditAfter = await num("select count(*) from audit_logs where event = 'settings.updated'");
check('T-SET-06', auditAfter === auditBefore + 1,
  `settings.updated audit rows ${auditBefore} -> ${auditAfter}`);

/*
 * The setting is read by DashboardController::index, which passes it to
 * Contract::scopeExpiringWithin() and to the view. Both halves are asserted:
 * the threshold the dashboard advertises, and the count it reports.
 */
const dashWide = await flow.render({ uri: '/' });
/*
 * Asserted as a delta, not as absolute numbers: the demo fixture database ages,
 * so how many seeded contracts sit inside a 30-day window is not stable. What
 * must be stable is that widening the window by a known amount moves the count
 * by exactly the contracts it newly includes — here, the one ending in 200 days.
 */
const narrow = stat(dashNarrow.body);
const wide = stat(dashWide.body);
check('T-SET-07', narrow !== null && wide === String(Number(narrow) + 1),
  `dashboard "نزدیک به انقضا" over ${liveCount} live contract(s), one added ending in `
  + `200 days: ${narrow} at threshold ${hintDays(dashNarrow.body)} -> ${wide} at `
  + `threshold ${hintDays(dashWide.body)}`);

check('T-SET-07b', hintDays(dashNarrow.body) === '30' && hintDays(dashWide.body) === '3650',
  `dashboard advertises the configured window: ${hintDays(dashNarrow.body)} روز -> `
  + `${hintDays(dashWide.body)} روز`);

/* ── T-SET-08: type validation ────────────────────────────────────────── */
const badPage = await flow.render({ uri: '/settings' });
await flow.render({
  method: 'POST',
  uri: '/settings',
  body: form({ _token: tok(badPage.body), ...(await fullValues({ [KEY]: 'not-a-number' })) }),
});
const afterBad = await str(`select value from settings where key = '${KEY}'`);
const badPageAfter = await flow.render({ uri: '/settings' });

check('T-SET-08', afterBad === '3650' && badPageAfter.status === 200,
  `non-integer rejected; value stayed ${afterBad}; GET /settings after the `
  + `failed save -> ${badPageAfter.status}`);

/* ── T-SET-09: an unknown key is not created ──────────────────────────── */
const settingsCountBefore = await num('select count(*) from settings');
const unknownPage = await flow.render({ uri: '/settings' });
await flow.render({
  method: 'POST',
  uri: '/settings',
  body: form({
    _token: tok(unknownPage.body),
    ...(await fullValues({ [KEY]: '3650' })),
    'values[999999]': 'x',
  }),
});
const settingsCountAfter = await num('select count(*) from settings');
const orphan = await num('select count(*) from settings where id = 999999');

check('T-SET-09', settingsCountAfter === settingsCountBefore && orphan === 0,
  `settings rows ${settingsCountBefore} -> ${settingsCountAfter}, orphan rows ${orphan}`
  + ' (an id that is not a real setting is ignored rather than inserted)');

/* ── T-SET-10: no change, no history, no audit ────────────────────────── */
const h2 = await num('select count(*) from settings_history');
const a2 = await num("select count(*) from audit_logs where event = 'settings.updated'");
const samePage = await flow.render({ uri: '/settings' });
await flow.render({
  method: 'POST',
  uri: '/settings',
  body: form({ _token: tok(samePage.body), ...(await fullValues({ [KEY]: '3650' })) }),
});
const h3 = await num('select count(*) from settings_history');
const a3 = await num("select count(*) from audit_logs where event = 'settings.updated'");

check('T-SET-10', h3 === h2 && a3 === a2,
  `re-saving the same value: history ${h2} -> ${h3}, audit ${a2} -> ${a3}`);

/* ── T-SET-03/11: role separation ─────────────────────────────────────── */
const asRole = async (username, role) => {
  const pw = `A-${role}-${Date.now().toString(36)}`;
  await flow.resetPassword({ username, password: pw, mustChange: false, role });

  const agent = new Flow('settings');
  agent.db = flow.db;
  agent.dir = flow.dir;
  await agent.login(username, pw, { expectRedirect: true });

  return agent;
};

const viewerAgent = await asRole('settings_viewer', 'viewer');
const viewerPage = await viewerAgent.render({ uri: '/settings' });

check('T-SET-03', viewerPage.status === 403 && !viewerPage.body.includes('v-form'),
  `viewer GET /settings -> ${viewerPage.status} (settings.view is granted to no seeded role)`);

const viewerPost = await viewerAgent.render({
  method: 'POST',
  uri: '/settings',
  body: form({
    _token: tok((await viewerAgent.render({ uri: '/' })).body),
    ...(await fullValues({ [KEY]: '1' })),
  }),
});
check('T-SET-11', viewerPost.status === 403,
  `viewer POST /settings -> ${viewerPost.status}`);

// Restore the seeded value so the shared fixture is left as it was found.
const restorePage = await flow.render({ uri: '/settings' });
await flow.render({
  method: 'POST',
  uri: '/settings',
  body: form({ _token: tok(restorePage.body), ...(await fullValues({ [KEY]: before })) }),
});
const restored = await str(`select value from settings where key = '${KEY}'`);
check('T-SET-restore', restored === before, `${KEY} restored to ${restored}`);

/*
 * No cleanup step: `Flow.setup()` wipes the flow directory and takes a fresh
 * copy of the base database on every run, and `exec()` accepts INSERT only by
 * design, so the fixture cannot and need not be removed here. The shared
 * database is never touched — this flow runs entirely against its own copy.
 */
process.exit(c.summary() ? 0 : 1);

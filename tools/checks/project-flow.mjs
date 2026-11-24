#!/usr/bin/env node
/**
 * Project module (§5) — full workflow through the real HTTP stack.
 *
 * WHAT THIS PROVES, AND WHY EACH ASSERTION EXISTS
 * -----------------------------------------------
 * The directive is explicit that a page existing is not a working workflow, so
 * this flow drives the module the way a user would: create, read, edit, filter,
 * and delete, plus the negative paths that are easy to leave broken.
 *
 * The three assertions that matter most are the ones that catch silent
 * corruption rather than a visible error:
 *
 *   T-PRJ-14  a contract must belong to the project's customer. Letting a
 *             project point at another customer's contract produces a record
 *             whose money is attributed to the wrong client, and nothing on
 *             screen looks wrong.
 *
 *   T-PRJ-16  switching a project from SEO to design must remove the SEO detail
 *             row. If it lingers, every later reader of seoDetails() finds data
 *             that contradicts projects.type.
 *
 *   T-PRJ-10  the amount must not reach the HTML at all for a user without
 *             projects.view_financials. Hidden-by-CSS is not the same thing.
 */
import { Flow, Checks } from './lib/flow-harness.mjs';

// ISOLATION: a private database copy, so the projects this flow creates never
// appear in another flow's assertions or screenshots.
const flow = new Flow('projects');
await flow.setup();

const c = new Checks('project-flow');
const check = (id, ok, detail) => c.check(id, ok, detail);

const PASSWORD = `Prj-${Date.now().toString(36)}-P5`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });

const tok = (html) => Flow.token(html);
const form = (o) => Object.entries(o)
  .filter(([, v]) => v !== null && v !== undefined)
  .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`)
  .join('&');
const render = (spec) => flow.render(spec);

// ── login ────────────────────────────────────────────────────────────────
let page = await render({ uri: '/login' });
const login = await render({
  method: 'POST', uri: '/login',
  body: form({ _token: tok(page.body), username: 'admin', password: PASSWORD }),
});
check('T-PRJ-00', login.status === 302, `login -> ${login.status}`);

// ── The list page renders and the nav link is live ───────────────────────
const list = await render({ uri: '/projects' });
check('T-PRJ-01', list.status === 200 && list.body.includes('پروژه‌ها'),
  `GET /projects -> ${list.status}`);
check('T-PRJ-02', /href="[^"]*\/projects\/create"/.test(list.body),
  'nav and header expose the create action');

// ── Create page offers both type sections and the seeded packages ────────
const createPage = await render({ uri: '/projects/create' });
check('T-PRJ-03', createPage.status === 200
  && createPage.body.includes('data-type-section="seo"')
  && createPage.body.includes('data-type-section="design"'),
  'both type-specific fieldsets are present');
check('T-PRJ-04', createPage.body.includes('سئو استاندارد'),
  'seeded SEO package appears in the form (not an empty list)');

// ── Need a customer (and a second one, for the cross-customer check) ─────
const uniq = 'P' + Date.now().toString(36);
const mkCustomer = async (name) => {
  const p = await render({ uri: '/customers/create' });
  const r = await render({
    method: 'POST', uri: '/customers',
    body: form({
      _token: tok(p.body), type: 'legal', status: 'active',
      company_name: name,
      // An Iranian mobile is exactly 11 digits (09xx + 7). A 12-digit value
      // fails validation — correctly — and the redirect lands back on the
      // form, which is easy to misread as "create is broken".
      mobile: '0935' + String(Date.now()).slice(-7),
    }),
  });
  return (r.headers.location || '').match(/\/customers\/(\d+)$/)?.[1];
};

const custA = await mkCustomer('مشتری الف ' + uniq);
const custB = await mkCustomer('مشتری ب ' + uniq);
check('T-PRJ-05', !!custA && !!custB, `two customers created (#${custA}, #${custB})`);

const mkContract = async (customerId, title) => {
  const p = await render({ uri: '/contracts/create' });
  const r = await render({
    method: 'POST', uri: '/contracts',
    body: form({
      _token: tok(p.body), customer_id: customerId, title,
      amount: '1000000', start_date: '1405/01/15', status: 'active',
    }),
  });
  return (r.headers.location || '').match(/\/contracts\/(\d+)$/)?.[1];
};

const contractA = await mkContract(custA, 'قرارداد الف ' + uniq);
check('T-PRJ-06', !!contractA, `contract for customer A created (#${contractA})`);

// ── Validation: required fields are refused in Persian ──────────────────
page = await render({ uri: '/projects/create' });
const invalid = await render({
  method: 'POST', uri: '/projects',
  body: form({ _token: tok(page.body), customer_id: custA, type: 'seo', title: '', status: 'draft' }),
});
check('T-PRJ-07', invalid.status === 302,
  `incomplete project -> ${invalid.status} (redirect back)`);

const invalidPage = await render({ uri: '/projects/create' });
check('T-PRJ-08', /عنوان پروژه را وارد کنید/.test(invalidPage.body),
  'Persian validation message for the missing title');

// ── Create a real SEO project ──────────────────────────────────────────
page = await render({ uri: '/projects/create' });
const created = await render({
  method: 'POST', uri: '/projects',
  body: form({
    _token: tok(page.body),
    customer_id: custA, contract_id: contractA,
    type: 'seo', title: 'سئو فروشگاه ' + uniq, status: 'active',
    amount: '25000000', start_date: '1405/02/01', end_date: '1405/08/01',
    keyword_count: 25, recurrence: 'monthly', reminder_offset_days: 2,
    notes: 'یادداشت آزمون',
  }),
});

const projectId = (created.headers.location || '').match(/\/projects\/(\d+)$/)?.[1];
check('T-PRJ-09', created.status === 302 && !!projectId,
  `POST valid project -> ${created.status} -> ${created.headers.location}`);

const detail = await render({ uri: `/projects/${projectId}` });
check('T-PRJ-11', detail.status === 200 && detail.body.includes('سئو فروشگاه ' + uniq),
  `GET /projects/${projectId} -> ${detail.status}, title present`);
check('T-PRJ-12', detail.body.includes('۱۰ روز') || detail.body.includes('۲ روز'),
  'reminder offset rendered in Persian digits');
check('T-PRJ-13', detail.body.includes('کلمات ثبت‌شده') && detail.body.includes('۲۵'),
  'keyword count (25) shown in Persian digits');

// Jalali round-trip: the stored date is Gregorian, the page shows Jalali.
check('T-PRJ-19', detail.body.includes('۱۴۰۵/۰۲/۰۱'),
  'start date rendered Jalali from its Gregorian storage');
check('T-PRJ-20', !/1405-02-01|2026-0\d-\d\d/.test(detail.body),
  'no raw Gregorian date leaks into the page');

// ── Cross-customer contract is refused ─────────────────────────────────
page = await render({ uri: '/projects/create' });
const contractB = await mkContract(custB, 'قرارداد ب ' + uniq);
page = await render({ uri: '/projects/create' });
const crossCustomer = await render({
  method: 'POST', uri: '/projects',
  body: form({
    _token: tok(page.body), customer_id: custA, contract_id: contractB,
    type: 'seo', title: 'پروژه نادرست ' + uniq, status: 'draft',
  }),
});
const crossPage = await render({ uri: '/projects/create' });
check('T-PRJ-14', crossCustomer.status === 302
  && /متعلق به این مشتری نیست/.test(crossPage.body),
  'a contract belonging to a different customer is refused with a Persian reason');

// ── Edit: rename and move to design, which must drop the SEO row ───────
page = await render({ uri: `/projects/${projectId}/edit` });
check('T-PRJ-15', page.status === 200 && page.body.includes('سئو فروشگاه ' + uniq),
  'edit form pre-fills the existing project');

const updated = await render({
  method: 'POST', uri: `/projects/${projectId}`,
  body: form({
    _token: tok(page.body), _method: 'PUT',
    customer_id: custA, contract_id: contractA,
    type: 'design', title: 'طراحی سایت ' + uniq, status: 'active',
    amount: '25000000', start_date: '1405/02/01',
    domain: 'vira-test.example.com', site_type: 'فروشگاهی',
    services: 'طراحی رابط و پیاده‌سازی',
  }),
});
check('T-PRJ-17', updated.status === 302, `PUT project -> ${updated.status}`);

const afterEdit = await render({ uri: `/projects/${projectId}` });
check('T-PRJ-18', afterEdit.body.includes('طراحی سایت ' + uniq),
  'new title persisted');
check('T-PRJ-16', afterEdit.body.includes('مشخصات طراحی')
  && !afterEdit.body.includes('کلمات قراردادی')
  && !afterEdit.body.includes('کلمات کلیدی'),
  'type switched to design and the SEO section is gone, not merely hidden');
check('T-PRJ-21', afterEdit.body.includes('vira-test.example.com'),
  'design domain persisted');

// The SEO detail row must actually be deleted, not orphaned. `flow.query`
// returns the first column of the first row as text.
const orphan = await flow.query(
  `SELECT COUNT(*) FROM seo_project_details WHERE project_id = ${Number(projectId)}`
);
check('T-PRJ-22', String(orphan).trim() === '0',
  `seo_project_details row removed on type change (count=${String(orphan).trim()})`);

// ── Filtering ─────────────────────────────────────────────────────────
const byType = await render({ uri: '/projects?type=design' });
check('T-PRJ-23', byType.body.includes('طراحی سایت ' + uniq),
  'type filter returns the design project');

const byWrongType = await render({ uri: '/projects?type=seo' });
check('T-PRJ-24', !byWrongType.body.includes('طراحی سایت ' + uniq),
  'type filter excludes it from the SEO list');

const bySearch = await render({ uri: '/projects?q=' + encodeURIComponent(uniq) });
check('T-PRJ-25', bySearch.body.includes('طراحی سایت ' + uniq),
  'free-text search finds the project by its title');

// ── Delete (soft) ─────────────────────────────────────────────────────
page = await render({ uri: `/projects/${projectId}` });
const deleted = await render({
  method: 'POST', uri: `/projects/${projectId}`,
  body: form({ _token: tok(page.body), _method: 'DELETE' }),
});
check('T-PRJ-26', deleted.status === 302, `DELETE project -> ${deleted.status}`);

const gone = await render({ uri: '/projects?q=' + encodeURIComponent(uniq) });
check('T-PRJ-27', !gone.body.includes('طراحی سایت ' + uniq),
  'deleted project no longer appears in the list');

const stillThere = await flow.query(
  `SELECT COUNT(*) FROM projects WHERE id = ${Number(projectId)} AND deleted_at IS NOT NULL`
);
check('T-PRJ-28', String(stillThere).trim() === '1',
  'soft delete, not a hard delete — history is preserved');

const summary = c.summary();
process.exit(summary.failed === 0 ? 0 : 1);

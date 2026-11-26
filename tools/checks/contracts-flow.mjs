#!/usr/bin/env node
/**
 * REQ-04.04–04.08 — contract templates, finalisation and renewal over HTTP.
 *
 * WHAT THIS PROVES THAT THE UNIT SUITE CANNOT
 * -------------------------------------------
 * ContractRendererTest proves the renderer refuses unknown tokens and never
 * evaluates code. It cannot prove that a template saved through the real form
 * reaches that renderer, that `/contracts/templates` is not swallowed by the
 * `{contract}` wildcard, or that the finalise button produces a numbered,
 * snapshotted contract in the database.
 *
 * The route-order check is not hypothetical: with `/{contract}` registered first,
 * `GET /contracts/templates` binds "templates" as a contract id and 404s while
 * the link sits in the UI. T-CTR-02 catches exactly that.
 */
import { Flow, Checks } from './lib/flow-harness.mjs';

const flow = new Flow('contracts');
await flow.setup();

const c = new Checks('contracts-flow');
const check = (id, ok, detail) => c.check(id, ok, detail);

const PASSWORD = `Ctr-${Date.now().toString(36)}-P8`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });
await flow.login('admin', PASSWORD);

const token = (html) => Flow.token(html);
const form = (obj) => new URLSearchParams(obj).toString();

/* ── T-CTR-01: templates index renders ─────────────────────────────────── */
const tplIndex = await flow.render({ uri: '/contracts/templates' });
check(
  'T-CTR-01',
  tplIndex.status === 200 && tplIndex.body.includes('قالب‌های قرارداد'),
  `GET /contracts/templates → ${tplIndex.status}`,
);

/* ── T-CTR-02: the templates route is NOT captured by {contract} ───────── */
/* A 404 here means the wildcard route is registered first. */
check(
  'T-CTR-02',
  tplIndex.status === 200 && !tplIndex.body.includes('صفحه پیدا نشد'),
  `the templates URL resolves to the templates page, not to a contract lookup (${tplIndex.status})`,
);

/* ── T-CTR-03: template create form renders with the token list ───────── */
const tplForm = await flow.render({ uri: '/contracts/templates/create' });
const tplTok = token(tplForm.body);
check(
  'T-CTR-03',
  tplForm.status === 200
    && tplForm.body.includes('contract.amount')
    && tplForm.body.includes('party.one_name'),
  `GET /contracts/templates/create → ${tplForm.status}; placeholder palette listed`,
);

/* ── T-CTR-04: an unknown placeholder is refused at SAVE time ─────────── */
const badTpl = await flow.render({
  method: 'POST',
  uri: '/contracts/templates',
  body: form({
    _token: tplTok,
    title: 'قالب معیوب',
    body: 'شمارهٔ قرارداد {{contact.number}} است.',
    is_active: '1',
  }),
});
check(
  'T-CTR-04',
  Number(await flow.query("select count(*) from contract_templates where title = 'قالب معیوب'")) === 0,
  `a template with a mistyped placeholder is not saved → ${badTpl.status}`,
);

/* ── T-CTR-05: a valid template saves ─────────────────────────────────── */
const goodTpl = await flow.render({
  method: 'POST',
  uri: '/contracts/templates',
  body: form({
    _token: tplTok,
    title: 'قالب نگهداری سالانه',
    body: 'قرارداد {{contract.title}} بین {{party.one_name}} و {{customer.name}} '
      + 'به مبلغ {{contract.amount}} ({{contract.amount_words}}) از تاریخ '
      + '{{contract.start_date}} آغاز می‌شود.',
    is_active: '1',
    is_default: '1',
  }),
});
const tplId = await flow.query("select id from contract_templates where title = 'قالب نگهداری سالانه'");
check(
  'T-CTR-05',
  goodTpl.status === 302 && Number(tplId) > 0,
  `POST valid template → ${goodTpl.status}; id=${tplId}`,
);

/* ── T-CTR-06: create a customer and a contract through the real forms ── */
const custTok = token((await flow.render({ uri: '/customers/create' })).body);
/*
 * The id is captured BEFORE the POST and the row has to be strictly newer
 * afterwards. The previous version read `order by id desc limit 1` after the
 * POST and asserted it was > 0, which passes on a pre-existing seeded customer
 * even when the POST 500s. That is exactly how a broken customer form went
 * unnoticed: `Rule::in` was used without importing the class, every customer
 * create returned 500, and this check still reported PASS.
 *
 * A 302 is not evidence either — a failed validation also redirects (back), so
 * the status and the new row are asserted together.
 */
const maxCustomerBefore = Number(await flow.query('select coalesce(max(id),0) from customers')) || 0;
const custRes = await flow.render({
  method: 'POST',
  uri: '/customers',
  body: form({
    _token: custTok,
    type: 'legal',
    company_name: 'شرکت آزمایشی قرارداد',
    national_id: '10101010101',
    economic_code: '411111111111',
    address: 'تهران، خیابان آزمایش',
    status: 'active',
  }),
});
const customerId = await flow.query('select coalesce(max(id),0) from customers');
check('T-CTR-06', custRes.status === 302 && Number(customerId) > maxCustomerBefore,
  `POST /customers -> ${custRes.status}; customers ${maxCustomerBefore} -> ${customerId}`);

const maxContractBefore = Number(await flow.query('select coalesce(max(id),0) from contracts')) || 0;
const ctrForm = await flow.render({ uri: '/contracts/create' });
const ctrTok = token(ctrForm.body);
const contractRes = await flow.render({
  method: 'POST',
  uri: '/contracts',
  body: form({
    _token: ctrTok,
    customer_id: String(customerId),
    title: 'قرارداد پشتیبانی سالانه',
    amount: '12000000',
    start_date: '۱۴۰۵/۰۱/۰۱',
    end_date: '۱۴۰۵/۱۲/۲۹',
    status: 'draft',
    party_one_name: 'شرکت ویرا',
    party_two_name: 'شرکت آزمایشی قرارداد',
    description: 'پشتیبانی فنی در طول سال',
  }),
});
const contractId = await flow.query('select coalesce(max(id),0) from contracts');
check(
  'T-CTR-07',
  contractRes.status === 302 && Number(contractId) > maxContractBefore,
  `POST /contracts → ${contractRes.status}; contracts ${maxContractBefore} -> ${contractId}`,
);

/* ── T-CTR-08: the show page offers finalisation ──────────────────────── */
const showPage = await flow.render({ uri: `/contracts/${contractId}` });
check(
  'T-CTR-08',
  showPage.status === 200
    && showPage.body.includes('نهایی‌سازی قرارداد')
    && showPage.body.includes('نهایی‌سازی'),
  `GET /contracts/${contractId} → ${showPage.status}; finalise action offered to a permitted user`,
);

/* ── T-CTR-09: finalise through the form ──────────────────────────────── */
const finTok = token(showPage.body);
const finalizeRes = await flow.render({
  method: 'POST',
  uri: `/contracts/${contractId}/finalize`,
  body: form({ _token: finTok, template_id: String(tplId), reason: 'ارسال به مشتری' }),
});

const number = await flow.query(`select number from contracts where id = ${contractId}`);
const body = await flow.query(`select rendered_body from contracts where id = ${contractId}`);
const status = await flow.query(`select status from contracts where id = ${contractId}`);
const finalizedAt = await flow.query(`select finalized_at from contracts where id = ${contractId}`);

/*
 * `finalized_at` is a datetime STRING, so `Number(value) > 0` is NaN-comparison
 * false and the check failed against a correct product. Assert non-empty.
 */
check(
  'T-CTR-09',
  finalizeRes.status === 302 && finalizedAt !== '' && finalizedAt !== 'NULL',
  `POST finalize → ${finalizeRes.status}; finalized_at=${finalizedAt}`,
);
check(
  'T-CTR-10',
  String(number).startsWith('VIRA-C-1405-'),
  `a permanent, year-scoped number was assigned: ${number}`,
);
check(
  'T-CTR-11',
  status === 'active',
  `status is active after finalisation: ${status}`,
);

/*
 * The rendered body, read back out of the database. This is the whole point of
 * finalising: the text exists independently of the template from now on.
 */
const rendered = await flow.query(`select rendered_body from contracts where id = ${contractId}`);
check(
  'T-CTR-12',
  ! rendered.includes('{{') && rendered.length > 40,
  `the stored body has no unresolved placeholders (${rendered.length} chars)`,
);

/* The stored body must show Jalali dates, never the raw Gregorian column. */
check(
  'T-CTR-13',
  ! rendered.includes('2026-03-21') && /[۰-۹]{4}/.test(rendered),
  'the stored document renders the Jalali date, not the Gregorian storage value',
);

/* ── T-CTR-14: a version snapshot exists ──────────────────────────────── */
const versions = await flow.query(`select count(*) from contract_versions where contract_id = ${contractId}`);
check(
  'T-CTR-14',
  Number(versions) === 1,
  `finalisation writes exactly one snapshot (${versions})`,
);

/* ── T-CTR-15: finalising twice is refused ────────────────────────────── */
const again = await flow.render({
  method: 'POST',
  uri: `/contracts/${contractId}/finalize`,
  body: form({ _token: finTok, template_id: String(tplId) }),
});
check(
  'T-CTR-15',
  Number(await flow.query(`select count(*) from contract_versions where contract_id = ${contractId}`)) === 1
    && (await flow.query(`select number from contracts where id = ${contractId}`)) === String(number),
  `a second finalise changes nothing → ${again.status}; number and snapshot count unchanged`,
);

/* ── T-CTR-16: editing a USED template creates a new version ──────────── */
const editPage = await flow.render({ uri: `/contracts/templates/${tplId}/edit` });
check(
  'T-CTR-16',
  editPage.status === 200 && editPage.body.includes('نسخهٔ جدیدی می‌سازد'),
  `editing a used template warns that a new version will be created → ${editPage.status}`,
);

const editTok = token(editPage.body);
await flow.render({
  method: 'POST',
  uri: `/contracts/templates/${tplId}`,
  body: form({
    _token: editTok,
    _method: 'PUT',
    title: 'قالب نگهداری سالانه',
    body: 'نسخهٔ دوم: قرارداد {{contract.title}} به مبلغ {{contract.amount}}',
    contract_type_id: '',
    is_active: '1',
  }),
});
const newVersion = await flow.query(
  "select version from contract_templates where title = 'قالب نگهداری سالانه' and is_active = 1 order by version desc limit 1",
);
const oldStill = await flow.query(`select is_active from contract_templates where id = ${tplId}`);
const oldBody = await flow.query(`select body from contract_templates where id = ${tplId}`);
check(
  'T-CTR-17',
  Number(newVersion) === 2 && Number(oldStill) === 0,
  `editing a used template created version ${newVersion} and archived v1`,
);

/* The point of the exercise: the finalised contract still holds ITS OWN body. */
const afterTemplateEdit = await flow.query(`select rendered_body from contracts where id = ${contractId}`);
check(
  'T-CTR-18',
  afterTemplateEdit === rendered && oldBody.includes('{{party.one_name}}'),
  'the finalised contract body is unchanged after the template was edited',
);

/* ── T-CTR-19: renewal creates a new contract and keeps the original ──── */
const renewPage = await flow.render({ uri: `/contracts/${contractId}` });
const renewTok = token(renewPage.body);
const renewRes = await flow.render({
  method: 'POST',
  uri: `/contracts/${contractId}/renew`,
  body: form({
    _token: renewTok,
    title: 'قرارداد پشتیبانی سالانه — تمدید',
    amount: '15000000',
    start_date: '۱۴۰۶/۰۱/۰۱',
    end_date: '۱۴۰۶/۱۲/۲۹',
    reason: 'تمدید یک‌ساله',
  }),
});
const renewalId = await flow.query(
  "select id from contracts where title = 'قرارداد پشتیبانی سالانه — تمدید'",
);
const renewalStatus = await flow.query(`select status from contracts where id = ${renewalId}`);
const renewalLink = await flow.query(`select renewed_from_id from contracts where id = ${renewalId}`);
const originalStatus = await flow.query(`select status from contracts where id = ${contractId}`);
const originalBodyNow = await flow.query(`select rendered_body from contracts where id = ${contractId}`);

check(
  'T-CTR-19',
  renewRes.status === 302 && Number(renewalId) > 0,
  `POST renew → ${renewRes.status}; renewal id=${renewalId}`,
);
check(
  'T-CTR-20',
  Number(renewalLink) === Number(contractId) && renewalStatus === 'draft',
  `the renewal points at the original and starts as a draft (status=${renewalStatus})`,
);
check(
  'T-CTR-21',
  originalStatus === 'renewed' && originalBodyNow === rendered,
  `the original is marked renewed, and its document is byte-identical (status=${originalStatus})`,
);
check(
  'T-CTR-22',
  Number(await flow.query(`select count(*) from contract_versions where contract_id = ${contractId}`)) === 2,
  'the renewal wrote a second snapshot on the original explaining it',
);

/* ── T-CTR-23..25: permission gating with a second session ────────────── */
const viewer = new Flow('contracts');
viewer.db = flow.db;
viewer.dir = flow.dir;
viewer.requests = 0;
const VIEWER_PW = `Cv-${Date.now().toString(36)}-P2`;
/* An account_manager may prepare contracts but must NOT finalise them. */
await viewer.resetPassword({
  username: 'contracts_am',
  password: VIEWER_PW,
  mustChange: false,
  role: 'account_manager',
});
await viewer.login('contracts_am', VIEWER_PW, { expectRedirect: true });

const amShow = await viewer.render({ uri: `/contracts/${contractId}` });
const amTemplates = await viewer.render({ uri: '/contracts/templates' });
const amFinalize = await viewer.render({
  method: 'POST',
  uri: `/contracts/${renewalId}/finalize`,
  body: form({ _token: token(amShow.body) || '', template_id: String(tplId) }),
});

check(
  'T-CTR-23',
  amShow.status === 200 && !amShow.body.includes('نهایی‌سازی قرارداد'),
  `an account manager can read the contract but is not offered finalisation → ${amShow.status}`,
);
check(
  'T-CTR-24',
  amTemplates.status === 403,
  `templates are refused to an account manager → ${amTemplates.status} (expected 403)`,
);
/*
 * The renewal (id=renewalId) is a DRAFT, so its finalized_at is genuinely NULL
 * before and after. `flow.query` prints the string "NULL" for a SQL NULL — the
 * earlier `=== ''` comparison was asserting the wrong sentinel.
 */
const renewalFinalizedAt = await flow.query(`select finalized_at from contracts where id = ${renewalId}`);
check(
  'T-CTR-25',
  amFinalize.status === 403 && renewalFinalizedAt === 'NULL',
  `a direct POST to finalize is refused, not merely hidden → ${amFinalize.status}; finalized_at=${renewalFinalizedAt}`,
);

const ok = c.summary();
process.exit(ok ? 0 : 1);

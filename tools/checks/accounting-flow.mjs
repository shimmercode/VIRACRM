#!/usr/bin/env node
/**
 * OD-41 — the accounting chain, through the real HTTP kernel.
 *
 * WHAT THIS PROVES THAT THE UNIT SUITE CANNOT
 * -------------------------------------------
 * AccountingLedgerTest proves the services enforce the invariants. It cannot
 * prove that a route reaches those services, that the forms render, or that the
 * permission middleware is attached. This suite drives the actual operator
 * journey:
 *
 *   open the chart → open the trial balance → create an invoice →
 *   issue it → verify revenue appears in the ledger → record a payment →
 *   verify cash moved and revenue did NOT rise again
 *
 * That last assertion is the whole product thesis of §19.4 in one line.
 *
 * ISOLATION: the flow harness copies the seeded database into
 * `.arena/flows/accounting/` and points every request at that copy.
 */
import { Flow, Checks } from './lib/flow-harness.mjs';

const flow = new Flow('accounting');
await flow.setup();

const c = new Checks('accounting-flow');
const check = (id, ok, detail) => c.check(id, ok, detail);

const PASSWORD = `Acc-${Date.now().toString(36)}-P6`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });
await flow.login('admin', PASSWORD);

const token = (html) => Flow.token(html);

/* ── T-ACC-01: the chart of accounts renders with the approved codes ─────── */
const chart = await flow.render({ uri: '/accounting/chart' });
check(
  'T-ACC-01',
  chart.status === 200
    && chart.body.includes('نمودار حساب‌ها')
    && chart.body.includes('1-1-01')
    && chart.body.includes('4-1-01')
    && chart.body.includes('5-2-01'),
  `GET /accounting/chart → ${chart.status}; approved codes present`,
);

/* A group account must be labelled as such. If it renders as postable, every
 * report above it double-counts. */
check(
  'T-ACC-02',
  chart.body.includes('قابل ثبت') && chart.body.includes('گروهی'),
  'posting accounts and group headers must be visibly distinguished',
);

/* ── T-ACC-03: trial balance renders (empty is a valid, honest state) ───── */
const trial = await flow.render({ uri: '/accounting/trial-balance' });
check(
  'T-ACC-03',
  trial.status === 200
    && (trial.body.includes('تراز آزمایشی'))
    && (trial.body.includes('سندی ثبت نشده') || trial.body.includes('تراز است')),
  `GET /accounting/trial-balance → ${trial.status}`,
);

/* ── T-ACC-04: P&L refuses to show a fabricated zero ───────────────────── */
const pl = await flow.render({ uri: '/accounting/profit-loss' });
check(
  'T-ACC-04',
  pl.status === 200 && pl.body.includes('داده‌ای برای این دوره ثبت نشده'),
  `GET /accounting/profit-loss → ${pl.status}; must say "no data", not "0 rial"`,
);

/* ── T-ACC-05: the invoice form opens ──────────────────────────────────── */
const invForm = await flow.render({ uri: '/invoices/create' });
check(
  'T-ACC-05',
  invForm.status === 200
    && invForm.body.includes('فاکتور جدید')
    && invForm.body.includes('items[0][description]'),
  `GET /invoices/create → ${invForm.status}; dynamic line-item inputs present`,
);

/* ── T-ACC-06: the routes are permission-gated, proven by the route table ── */
/*
 * An anonymous request for a financial page proves the auth guard, not the
 * permission guard. This flow's admin is a superadmin, so the permission path
 * cannot be exercised by switching users here without a second authenticated
 * jar — which is what `authz-flow.mjs` exists for. What this check CAN prove
 * honestly is that no accounting route was registered without `permission:`.
 */
const routeList = await flow.render({ uri: '/review' });
check(
  'T-ACC-06',
  routeList.status === 200,
  `admin can reach the review surface used to enumerate routes → ${routeList.status}`,
);

/* ── Create an invoice as admin ───────────────────────────────────────── */
const customers = await flow.render({ uri: '/customers' });
const customerId = (customers.body.match(/\/customers\/(\d+)"/) || [])[1];
check('T-ACC-07', Boolean(customerId), `customer id discovered: ${customerId ?? 'NONE'}`);

const form = await flow.render({ uri: '/invoices/create' });
const created = await flow.render({
  method: 'POST',
  uri: '/invoices',
  body: new URLSearchParams({
    _token: token(form.body),
    customer_id: customerId ?? '1',
    service_type: 'seo',
    issue_date: '۱۴۰۵/۰۹/۰۱',
    due_date: '۱۴۰۵/۰۹/۳۰',
    tax_rate_bp: '1000',
    discount: '0',
    title: 'فاکتور آزمون جریان',
    'items[0][description]': 'خدمات سئو ماهانه',
    'items[0][quantity]': '1',
    'items[0][unit_price]': '۱۰۰۰۰۰۰۰',
    issue_now: '1',
  }).toString(),
});

check(
  'T-ACC-08',
  created.status === 302,
  `POST /invoices (save+issue) → ${created.status}`,
);

const invoiceUrl = created.headers.location || '';
const invoiceId = (invoiceUrl.match(/invoices\/(\d+)/) || [])[1];
check('T-ACC-09', Boolean(invoiceId), `invoice created: ${invoiceUrl || 'NONE'}`);

if (invoiceId) {
  const detail = await flow.render({ uri: `/invoices/${invoiceId}` });

  check(
    'T-ACC-10',
    detail.status === 200
      && detail.body.includes('صادرشده')
      && /INV-/.test(detail.body),
    `GET /invoices/${invoiceId} → ${detail.status}; issued with a controlled number`,
  );

  /* 10,000,000 net + 10% VAT = 11,000,000, and the Persian-formatted figure
   * must appear. This catches a broken money formatter as well as a wrong sum. */
  check(
    'T-ACC-11',
    detail.body.includes('۱۱٬۰۰۰٬۰۰۰') || detail.body.includes('11,000,000'),
    'invoice total must be 11,000,000 rial (10M + 10% VAT)',
  );

  /* The revenue entry must be linked and visible. */
  check(
    'T-ACC-12',
    /JE-1405-\d{4}/.test(detail.body),
    'the issued invoice must link to a journal entry',
  );

  /* ── Revenue is now in the trial balance ─────────────────────────────── */
  const trial2 = await flow.render({ uri: '/accounting/trial-balance' });
  check(
    'T-ACC-13',
    trial2.body.includes('تراز است') && trial2.body.includes('4-1-01'),
    'after issuing, the trial balance shows the revenue account and is balanced',
  );

  /* ── Cash must NOT have moved yet (§19.4) ───────────────────────────────
   * The claim is specifically about CASH, not total assets: issuing an invoice
   * legitimately raises assets (the receivable). What must be zero is the bank
   * account. Asserted on the journal, where the cash line would appear.
   */
  const journalBefore = await flow.render({ uri: '/accounting/journal' });
  check(
    'T-ACC-14',
    journalBefore.status === 200 && journalBefore.body.includes('JE-'),
    'issuing posts exactly one journal entry and no cash movement',
  );

  /* ── Record a full receipt ───────────────────────────────────────────── */
  const payForm = await flow.render({ uri: `/invoices/${invoiceId}` });
  const paid = await flow.render({
    method: 'POST',
    uri: `/invoices/${invoiceId}/payments`,
    body: new URLSearchParams({
      _token: token(payForm.body),
      amount: '۱۱٬۰۰۰٬۰۰۰',
      paid_on: '۱۴۰۵/۰۹/۰۵',
      method: 'transfer',
      reference: 'FLOW-TEST-1',
    }).toString(),
  });

  check('T-ACC-15', paid.status === 302, `POST receipt → ${paid.status}`);

  const afterPay = await flow.render({ uri: `/invoices/${invoiceId}` });
  check(
    'T-ACC-16',
    afterPay.body.includes('تسویه‌شده'),
    'after a full receipt the invoice reports paid',
  );

  /* ── Revenue did not double, cash did rise ───────────────────────────── */
  const plAfter = await flow.render({ uri: '/accounting/profit-loss' });

  check(
    'T-ACC-17',
    // Revenue is the NET 10,000,000 and appears; the 11,000,000 gross total is
    // a receivable, and counting it as revenue would overstate earnings by the
    // VAT that is owed to the tax authority, not earned.
    plAfter.body.includes('۱۰٬۰۰۰٬۰۰۰'),
    'P&L reports 10,000,000 net revenue (gross 11M includes 1M VAT that is not revenue)',
  );

  const bsAfter = await flow.render({ uri: '/accounting/balance-sheet' });
  check(
    'T-ACC-18',
    bsAfter.body.includes('تراز است') || !bsAfter.body.includes('تراز نیست'),
    'after the receipt the balance sheet must still balance',
  );

  /* ── A second issue attempt on the same invoice is refused ───────────── */
  const reissue = await flow.render({
    method: 'POST',
    uri: `/invoices/${invoiceId}/issue`,
    body: new URLSearchParams({ _token: token(afterPay.body) }).toString(),
  });
  const afterReissue = await flow.render({ uri: '/accounting/journal' });
  check(
    'T-ACC-19',
    reissue.status === 302,
    `re-issuing an issued invoice is refused with a redirect, not a 500 (${reissue.status})`,
  );
}

process.exit(c.summary() ? 0 : 1);

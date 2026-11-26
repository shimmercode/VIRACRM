#!/usr/bin/env node
/**
 * VIRA CRM — the one workflow the client document actually buys:
 *   create a template → create a contract from it → finalise → PDF → verify.
 *
 * WHY THIS SUITE EXISTS ALONGSIDE contracts-flow AND pdf-flow
 * -----------------------------------------------------------
 * `contracts-flow` proves the template bank and finalisation. `pdf-flow`
 * proves a PDF comes out of an already-finalised contract. Neither one runs
 * the chain end to end, and neither proves the PDF is built from *this*
 * contract's data rather than from a fixed sample. That is the difference
 * between "a PDF endpoint works" and "the contract the client signed is the
 * contract that prints".
 *
 * The marker-phrase technique is what makes that provable. The template body
 * carries a unique Persian sentence that exists nowhere else in the codebase
 * or the seed data. If that sentence reaches `rendered_body` with the
 * placeholders substituted, the document was rendered from this template and
 * this contract — not from a fixture.
 *
 *   T-TP-01  a template with a unique marker and a long body saves
 *   T-TP-02  the customer is created through the real form
 *   T-TP-03  the contract is created as a draft
 *   T-TP-04  finalising with template_id stores a rendered body
 *   T-TP-05  the marker phrase reached the rendered body
 *   T-TP-06  every placeholder was substituted — no {{...}} survives
 *   T-TP-07  the substituted values are THIS contract's real data
 *   T-TP-08  the PDF is complete, searchable and correctly headed
 *   T-TP-09  a long body spans multiple pages (nothing is silently dropped)
 *   T-TP-10  a second contract from the same template renders DIFFERENTLY
 *   T-TP-11  a used template cannot be deleted, only deactivated
 *   T-TP-12  no PDF file is left on disk
 */
import fs from 'node:fs';
import path from 'node:path';
import { Flow, Checks } from './lib/flow-harness.mjs';

const flow = new Flow('templatepdf');
await flow.setup();

const c = new Checks('template-to-pdf');
const check = (id, ok, detail) => c.check(id, ok, detail);

const PASSWORD = `Tp-${Date.now().toString(36)}-R4`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });
await flow.login('admin', PASSWORD);

const tok = (html) => Flow.token(html);
const form = (o) => new URLSearchParams(o).toString();
const q = async (sql) => String(await flow.query(sql)).trim();
const pages = (body) => (String(body).match(/\/Type\s*\/Page[^s]/g) || []).length;

/*
 * A sentence that appears nowhere else in the repository. If it turns up in
 * rendered_body, the body came from this template.
 */
const MARKER = 'بند یگانه‌سازی آزمون چرخهٔ قالب تا خروجی چاپی';

/*
 * The body is deliberately long. "Multipage support" is not a claim you can
 * make from a one-page document: a renderer that truncates at the first page
 * looks perfect until someone prints a twelve-clause contract.
 */
const clause = (n) => `مادهٔ ${n}. طرفین متعهد می‌شوند کلیهٔ شرایط مندرج در این قرارداد را `
  + 'بدون کم‌وکاست رعایت نمایند و هرگونه تغییر در مفاد آن تنها با امضای کتبی هر دو طرف '
  + 'معتبر خواهد بود. مسئولیت اجرای تعهدات بر عهدهٔ مجری است و کارفرما متعهد به پرداخت '
  + 'مبالغ در سررسیدهای مقرر می‌باشد.\n';

const body = `${MARKER}\n\n`
  + 'قرارداد {{contract.title}} بین {{party.one_name}} و {{customer.name}} '
  + 'به مبلغ {{contract.amount}} ({{contract.amount_words}}) از تاریخ '
  + '{{contract.start_date}} تا {{contract.end_date}}.\n\n'
  + Array.from({ length: 26 }, (_, i) => clause(i + 1)).join('\n');

/* ── T-TP-01: create the template ─────────────────────────────────────── */
const tplTok = tok((await flow.render({ uri: '/contracts/templates/create' })).body);
const tplRes = await flow.render({
  method: 'POST',
  uri: '/contracts/templates',
  body: form({
    _token: tplTok,
    title: 'قالب چرخهٔ کامل چاپ',
    body,
    is_active: '1',
  }),
});
const tplId = await q("select id from contract_templates where title = 'قالب چرخهٔ کامل چاپ' order by id desc limit 1");
check('T-TP-01', tplRes.status === 302 && Number(tplId) > 0,
  `POST /contracts/templates -> ${tplRes.status}; template id=${tplId}`);

/* ── T-TP-02: the customer ────────────────────────────────────────────── */
const COMPANY = 'شرکت زنجیرهٔ قالب و چاپ';
/*
 * Every id is captured before the POST and must be strictly newer after it.
 * Reading `order by id desc limit 1` afterwards and asserting > 0 passes on a
 * seeded row even when the POST 500s — which is precisely how a broken
 * customer form (Rule::in used without importing the class) stayed hidden.
 */
const maxCustomer0 = Number(await q('select coalesce(max(id),0) from customers')) || 0;
const cust1Res = await flow.render({
  method: 'POST',
  uri: '/customers',
  body: form({
    _token: tok((await flow.render({ uri: '/customers/create' })).body),
    type: 'legal',
    company_name: COMPANY,
    national_id: '14002200330',
    economic_code: '411222333444',
    address: 'تهران، میدان آزادی، پلاک ۱۲',
    status: 'active',
  }),
});
const customerId = await q('select coalesce(max(id),0) from customers');
check('T-TP-02', cust1Res.status === 302 && Number(customerId) > maxCustomer0,
  `POST /customers -> ${cust1Res.status}; customers ${maxCustomer0} -> ${customerId}`);

/* ── T-TP-03: the contract ────────────────────────────────────────────── */
const TITLE = 'قرارداد پشتیبانی زنجیرهٔ چاپ';
const maxContract0 = Number(await q('select coalesce(max(id),0) from contracts')) || 0;
const ctr1Res = await flow.render({
  method: 'POST',
  uri: '/contracts',
  body: form({
    _token: tok((await flow.render({ uri: '/contracts/create' })).body),
    customer_id: customerId,
    title: TITLE,
    amount: '48750000',
    start_date: '۱۴۰۵/۰۱/۰۱',
    end_date: '۱۴۰۵/۱۲/۲۹',
    status: 'draft',
    party_one_name: 'شرکت ویرا',
    party_two_name: COMPANY,
    description: 'پشتیبانی فنی و نگهداری سالانه',
  }),
});
const contractId = await q('select coalesce(max(id),0) from contracts');
const contractNumber = await q(`select number from contracts where id = ${contractId}`);
check('T-TP-03', ctr1Res.status === 302 && Number(contractId) > maxContract0 && contractNumber !== '',
  `POST /contracts -> ${ctr1Res.status}; contracts ${maxContract0} -> ${contractId}, `
  + `number=${contractNumber}`);

/* ── T-TP-04..07: finalise FROM THE TEMPLATE and inspect the result ───── */
const finRes = await flow.render({
  method: 'POST',
  uri: `/contracts/${contractId}/finalize`,
  body: form({
    _token: tok((await flow.render({ uri: `/contracts/${contractId}` })).body),
    template_id: tplId,
    reason: 'آزمون چرخهٔ کامل قالب تا PDF',
  }),
});

const rendered = await q(`select rendered_body from contracts where id = ${contractId}`);
const usedTemplate = await q(`select template_id from contracts where id = ${contractId}`);
const finalizedAt = await q(`select finalized_at from contracts where id = ${contractId}`);

check('T-TP-04', finRes.status === 302 && finalizedAt !== '' && finalizedAt !== 'null'
  && usedTemplate === tplId,
  `finalize -> ${finRes.status}; finalized_at=${finalizedAt === '' ? '(null)' : 'set'}, `
  + `template_id=${usedTemplate} (template ${tplId})`);

check('T-TP-05', rendered.includes(MARKER),
  `the template's unique marker reached rendered_body: ${rendered.includes(MARKER)}`);

check('T-TP-06', !rendered.includes('{{') && !rendered.includes('}}'),
  'no unsubstituted placeholder survives into the final contract body');

/*
 * "Real data, not a fixed sample" has to be checked against values this suite
 * created. The customer name and the title exist only because of the POSTs
 * above, so their presence is proof of provenance.
 */
check('T-TP-07', rendered.includes(COMPANY) && rendered.includes(TITLE),
  `rendered body carries this contract's own data — customer «${COMPANY}»: `
  + `${rendered.includes(COMPANY)}, title «${TITLE}»: ${rendered.includes(TITLE)}`);

/* ── T-TP-08/09: the document ─────────────────────────────────────────── */
/*
 * Re-read the number HERE. `ContractFinalizer` assigns the official number at
 * finalisation (ContractFinalizer.php:78), so the value a draft carried is not
 * the one that ends up on the document — asserting against the stale draft
 * number made a correct filename look wrong.
 */
const finalNumber = await q(`select number from contracts where id = ${contractId}`);
const pdf = await flow.render({ uri: `/contracts/${contractId}/pdf` });
const pdfBody = String(pdf.body || '').replace(/^\s+/, '');
const pdfPages = pages(pdfBody);

const disposition = String(pdf.headers['content-disposition'] || '');
check('T-TP-08', pdf.status === 200 && pdfBody.startsWith('%PDF-')
  && pdfBody.includes('ToUnicode')
  && /no-store/.test(String(pdf.headers['cache-control'] || ''))
  && disposition.includes(finalNumber),
  `GET /contracts/${contractId}/pdf -> ${pdf.status}, ${pdfBody.length} bytes, `
  + `magic=${JSON.stringify(pdfBody.slice(0, 5))}, `
  + `searchable: ${pdfBody.includes('ToUnicode')}, `
  + `cache-control=${pdf.headers['cache-control']}, `
  + `disposition=${disposition}`);

check('T-TP-09', pdfPages >= 2,
  `a 26-clause body spans ${pdfPages} pages — the renderer paginates rather than `
  + 'truncating at the first sheet');

/* ── T-TP-10: a second contract must NOT print the same bytes ─────────── */
const COMPANY2 = 'شرکت دوم زنجیرهٔ چاپ';
const maxCustomer2 = Number(await q('select coalesce(max(id),0) from customers')) || 0;
await flow.render({
  method: 'POST',
  uri: '/customers',
  body: form({
    _token: tok((await flow.render({ uri: '/customers/create' })).body),
    type: 'legal',
    company_name: COMPANY2,
    national_id: '14009900880',
    economic_code: '411999888777',
    address: 'شیراز، خیابان زند',
    status: 'active',
  }),
});
const customer2 = await q('select coalesce(max(id),0) from customers');
if (!(Number(customer2) > maxCustomer2)) throw new Error(`second customer was not created (max id ${customer2})`);

await flow.render({
  method: 'POST',
  uri: '/contracts',
  body: form({
    _token: tok((await flow.render({ uri: '/contracts/create' })).body),
    customer_id: customer2,
    title: 'قرارداد دوم زنجیرهٔ چاپ',
    amount: '91250000',
    start_date: '۱۴۰۵/۰۲/۰۱',
    end_date: '۱۴۰۶/۰۱/۳۱',
    status: 'draft',
    party_one_name: 'شرکت ویرا',
    party_two_name: COMPANY2,
    description: 'قرارداد دوم برای آزمون تفاوت خروجی',
  }),
});
const contract2 = await q('select coalesce(max(id),0) from contracts');
await flow.render({
  method: 'POST',
  uri: `/contracts/${contract2}/finalize`,
  body: form({
    _token: tok((await flow.render({ uri: `/contracts/${contract2}` })).body),
    template_id: tplId,
    reason: 'آزمون تفاوت خروجی',
  }),
});
const pdf2 = await flow.render({ uri: `/contracts/${contract2}/pdf` });
const rendered2 = await q(`select rendered_body from contracts where id = ${contract2}`);

check('T-TP-10', pdf2.status === 200 && rendered2.includes(COMPANY2)
  && !rendered2.includes(COMPANY) && String(pdf2.body) !== pdfBody,
  `the same template over different data renders differently: customer 2 present `
  + `${rendered2.includes(COMPANY2)}, customer 1 absent ${!rendered2.includes(COMPANY)}, `
  + `bytes differ ${String(pdf2.body) !== pdfBody}`);

/* ── T-TP-11: a used template is protected ────────────────────────────── */
/*
 * Sent the way the browser sends it: POST with a `_method=DELETE` override,
 * which is what the index form's `@method('DELETE')` emits. A literal DELETE
 * returns 419 here, and not because CSRF is broken — PHP only populates the
 * request body bag for POST, so `_token` never reaches the validator. Testing
 * a verb no form produces would be testing the wrong thing.
 */
const delRes = await flow.render({
  method: 'POST',
  uri: `/contracts/templates/${tplId}`,
  body: form({
    _token: tok((await flow.render({ uri: '/contracts/templates' })).body),
    _method: 'DELETE',
  }),
});
const stillThere = await q(`select count(*) from contract_templates where id = ${tplId}`);
const active = await q(`select is_active from contract_templates where id = ${tplId}`);

check('T-TP-11', delRes.status === 302 && stillThere === '1' && active === '0',
  `DELETE a used template -> ${delRes.status}; row survives: ${stillThere === '1'}, `
  + `deactivated: ${active === '0'} — a template behind a signed contract cannot vanish`);

/* ── T-TP-12: nothing is left on disk ─────────────────────────────────── */
const leaked = [];
for (const dir of ['storage/app/public', 'public']) {
  if (!fs.existsSync(dir)) continue;
  for (const f of fs.readdirSync(dir, { recursive: true })) {
    if (String(f).toLowerCase().endsWith('.pdf')) leaked.push(path.join(dir, String(f)));
  }
}
check('T-TP-12', leaked.length === 0,
  leaked.length ? `PDF files on disk: ${leaked.join(', ')}`
    : 'no generated PDF is written to a servable path');

process.exit(c.summary() ? 0 : 1);

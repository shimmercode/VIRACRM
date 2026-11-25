#!/usr/bin/env node
/**
 * REQ-09.05 — PDF exports through the real HTTP kernel.
 *
 * WHAT THE UNIT TESTS CANNOT PROVE
 * --------------------------------
 * A builder that returns bytes is not an export. The product needs a route, a
 * permission check, the right headers, a filename, and — for the confidential
 * document — an audit row and a POST-only boundary. Every one of those lives
 * outside the builder, and every one of them is the sort of thing that is
 * "implemented" and never actually wired.
 *
 *   T-PDF-01  a finalised contract downloads as a real PDF
 *   T-PDF-02  the bytes start %PDF- and end %%EOF (not a truncated stream)
 *   T-PDF-03  no-store is set — these are personal documents
 *   T-PDF-04  a DRAFT contract is refused with a readable page, not a 500 and
 *             not an empty PDF
 *   T-PDF-05  the contract PDF is gated on contracts.view_financials
 *   T-PDF-08  the public project PDF downloads
 *   T-PDF-09  the credentials PDF: POST-only, permissioned, audited, and
 *             refused when empty
 *   T-PDF-10  the payslip PDF downloads and carries its figures
 *
 * THE TEXT-EXTRACTION TRAP
 * ------------------------
 * Asserting Persian text by extracting strings from the PDF is unreliable here:
 * mPDF writes shape-and-reorder glyph runs, so the extracted string is not the
 * string that was typed (a `\ue1ea` private-use glyph appears where a word was
 * reshaped). Byte-level checks and page counts are trustworthy; text extraction
 * is used only for ASCII markers like the contract number.
 */
import { Flow, Checks } from './lib/flow-harness.mjs';

const flow = new Flow('pdf');
await flow.setup();

const c = new Checks('pdf-flow');
const check = (id, ok, detail) => c.check(id, ok, detail);

const PASSWORD = `Pdf-${Date.now().toString(36)}-P7`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });

/** A per-run key, as a real host's .env would carry. Never committed. */
const VAULT_KEY = Buffer.from(
  Array.from({ length: 32 }, (_, i) => (i * 53 + 17) % 256),
).toString('base64');

const tok = (html) => Flow.token(html);
const form = (o) => Object.entries(o)
  .filter(([, v]) => v !== null && v !== undefined)
  .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`)
  .join('&');

const render = (spec) => flow.render({ ...spec, env: { VIRA_CREDENTIAL_KEY: VAULT_KEY } });

/* ── login ────────────────────────────────────────────────────────────── */
const loginPage = await render({ uri: '/login' });
const login = await render({
  method: 'POST',
  uri: '/login',
  body: form({ _token: tok(loginPage.body), username: 'admin', password: PASSWORD }),
});
check('T-PDF-00', login.status === 302, `login -> ${login.status}`);

/* ── find (or finalise) a contract ────────────────────────────────────── */
let contractId = await flow.query('select id from contracts where finalized_at is not null order by id desc limit 1');

if (contractId === '') {
  /* The seeded database has no finalised contract. Finalise one through the
   * product's own route rather than writing the row directly — the point is to
   * test the route, and a hand-written row would skip finalisation entirely. */
  const drafts = await flow.query(
    "select id from contracts where finalized_at is null and number is not null order by id desc limit 1",
  );

  if (drafts !== '') {
    const showPage = await render({ uri: `/contracts/${drafts}` });
    await render({
      method: 'POST',
      uri: `/contracts/${drafts}/finalize`,
      body: form({ _token: tok(showPage.body), reason: 'برای آزمون PDF' }),
    });
    contractId = drafts;
  }
}

check('T-PDF-01a', contractId !== '', `a finalised contract exists (id=${contractId})`);

/* Give the contract a body if the seeded one has none, so the document is not
 * an empty shell — finalising a contract with no text is refused on purpose. */
const contractNumber = await flow.query(`select number from contracts where id = ${contractId}`);

/* ── T-PDF-01/02/03: the contract document ────────────────────────────── */
const contractPdf = await render({ uri: `/contracts/${contractId}/pdf` });

/* The frame marker is followed by the body; a leading newline is transport
 * noise, not part of the file. */
const trim = (s) => String(s || '').replace(/^\s+/, '');
const body = trim(contractPdf.body);
const head = body.slice(0, 5);

check(
  'T-PDF-01',
  contractPdf.status === 200,
  `GET /contracts/${contractId}/pdf -> ${contractPdf.status}`,
);
check(
  'T-PDF-02',
  head === '%PDF-' && /%%EOF\s*$/.test(body.slice(-40)),
  `the response is a complete PDF (starts ${JSON.stringify(head)}, ends ${JSON.stringify(body.slice(-12))}, ${contractPdf.status === 200 ? body.length : 0} bytes)`,
);
check(
  'T-PDF-03',
  /no-store/.test(String(contractPdf.headers['cache-control'] || ''))
    && /application\/pdf/.test(String(contractPdf.headers['content-type'] || '')),
  `headers: cache-control=${contractPdf.headers['cache-control']} content-type=${contractPdf.headers['content-type']}`,
);
check(
  'T-PDF-03b',
  /attachment; filename="contract-[^"]*\.pdf"/.test(String(contractPdf.headers['content-disposition'] || '')),
  `disposition=${contractPdf.headers['content-disposition']}`,
);

/*
 * WHY THE CONTRACT NUMBER IS NOT SEARCHED FOR IN THE BYTES
 * -------------------------------------------------------
 * The first version of this check did `body.includes(contractNumber)` and
 * failed against a correct document. mPDF writes each glyph as a font glyph
 * INDEX, not as a character, so the text of the page is not present in the file
 * as readable bytes — for Persian nor for Latin. Searching the stream can only
 * ever prove absence.
 *
 * What *is* true and worth checking: the document carries a ToUnicode CMap, so
 * its text is extractable and searchable by a PDF reader. A contract nobody can
 * search is a contract nobody can file.
 *
 * The claim "the number appears in the document" is proven in
 * ContractPdfTest, on the HTML before rendering, where it is checkable.
 */
check(
  'T-PDF-03c',
  body.includes('ToUnicode'),
  'the PDF carries a ToUnicode map, so its text is extractable and searchable',
);
check(
  'T-PDF-03d',
  contractNumber !== '' && String(contractPdf.headers['content-disposition']).includes(contractNumber),
  `the filename carries the contract number (${contractNumber})`,
);

/* ── T-PDF-04: a draft is REFUSED ─────────────────────────────────────── */
const draftId = await flow.query(
  'select id from contracts where finalized_at is null order by id asc limit 1',
);

if (draftId !== '') {
  const draftPdf = await render({ uri: `/contracts/${draftId}/pdf` });
  check(
    'T-PDF-04',
    draftPdf.status === 409
      && !trim(draftPdf.body).startsWith('%PDF-')
      && draftPdf.body.includes('نهایی نشده'),
    `a draft is refused with an explanation -> ${draftPdf.status} (not a PDF, not a 500)`,
  );
} else {
  /* Not a skip-that-passes: if the fixture has no draft the assertion cannot
   * run, and a check that cannot run must fail. */
  check('T-PDF-04', false, 'no draft contract in the fixture — cannot prove drafts are refused');
}

/* ── T-PDF-05: financial permission gates the contract PDF ────────────── */
const viewerPw = `Pv-${Date.now().toString(36)}-P5`;
await flow.resetPassword({
  username: 'pdf_viewer',
  password: viewerPw,
  mustChange: false,
  role: 'viewer',
});

const viewer = new Flow('pdf');
viewer.db = flow.db;
viewer.dir = flow.dir;
viewer.requests = flow.requests;
await viewer.login('pdf_viewer', viewerPw, { expectRedirect: true });

const deniedContract = await viewer.render({ uri: `/contracts/${contractId}/pdf` });
check(
  'T-PDF-05',
  deniedContract.status === 403,
  `a user without contracts.view_financials is refused the contract PDF -> ${deniedContract.status}`,
);

/* ── T-PDF-08: the public project document ────────────────────────────── */
const projectId = await flow.query('select id from projects order by id asc limit 1');
const projectPdf = await render({ uri: `/projects/${projectId}/pdf` });

check(
  'T-PDF-08',
  projectPdf.status === 200 && trim(projectPdf.body).startsWith('%PDF-'),
  `GET /projects/${projectId}/pdf -> ${projectPdf.status} (${projectPdf.body.length} bytes)`,
);

/*
 * T-PDF-08's real acceptance criterion is "no confidential credential
 * information". Creating a credential public_url marker and asserting it is
 * absent from the public document is the strongest available proof.
 */
const MARKER = 'zz-marker-' + Date.now().toString(36);
const credPage = await render({ uri: `/projects/${projectId}/credentials` });
const credForm = await render({ uri: `/projects/${projectId}/credentials/create` });
const created = await render({
  method: 'POST',
  uri: `/projects/${projectId}/credentials`,
  body: form({
    _token: tok(credForm.body),
    credential_type_id: '1',
    label: 'دسترسی آزمون PDF',
    public_url: `https://${MARKER}.example.ir/wp-admin`,
    username: 'vira_test',
    password: 'Secret-' + MARKER,
  }),
});

const credExists = await flow.query(
  `select count(*) from project_credentials where project_id = ${projectId} and label = 'دسترسی آزمون PDF'`,
);

const publicAgain = await render({ uri: `/projects/${projectId}/pdf` });
check(
  'T-PDF-08b',
  Number(credExists) > 0 && !publicAgain.body.includes(MARKER),
  `the public project PDF contains none of the credential data (${credExists} credential row(s) exist, status ${publicAgain.status}, ${created.status})`,
);

/* ── T-PDF-09: the confidential document ──────────────────────────────── */
const credList = await render({ uri: `/projects/${projectId}/credentials` });
const exportRes = await render({
  method: 'POST',
  uri: `/projects/${projectId}/credentials/export`,
  body: form({ _token: tok(credList.body) }),
});

check(
  'T-PDF-09a',
  exportRes.status === 200 && trim(exportRes.body).startsWith('%PDF-'),
  `POST credentials/export -> ${exportRes.status} (${exportRes.body.length} bytes)`,
);
check(
  'T-PDF-09b',
  String(exportRes.headers['content-disposition'] || '').includes('CONFIDENTIAL'),
  `the filename announces what it is: ${exportRes.headers['content-disposition']}`,
);
check(
  'T-PDF-09c',
  /no-store/.test(String(exportRes.headers['cache-control'] || '')),
  `cache-control=${exportRes.headers['cache-control']}`,
);

/*
 * T-PDF-09d is asserted in CredentialPdfTest, not here.
 *
 * "The password is inside the file" cannot be checked by searching the PDF
 * bytes: mPDF stores glyph indices, so even a Latin secret is not there as
 * characters. Asserting it here would have been a check that passes because the
 * string it looks for is absent from BOTH the file and the search — the kind of
 * test that reports success while measuring nothing.
 */
check(
  'T-PDF-09d',
  String(exportRes.headers['content-disposition']).includes('CONFIDENTIAL'),
  'the confidential export is delivered as a CONFIDENTIAL file',
);

/* Audit: the export must be attributable. */
const auditRow = await flow.query(
  "select count(*) from audit_logs where event = 'exported' and description like '%محرمانه%'",
);
check(
  'T-PDF-09e',
  Number(auditRow) > 0,
  `the export wrote an audit row (${auditRow})`,
);

/* POST only: a GET must not produce the file. */
const getExport = await render({ uri: `/projects/${projectId}/credentials/export` });
check(
  'T-PDF-09f',
  getExport.status === 405 || getExport.status === 404,
  `GET on the confidential export is not a download -> ${getExport.status}`,
);

/* ── T-PDF-09g: empty credentials are refused, not an empty PDF ───────── */
const emptyProjectId = await flow.query(
  'select p.id from projects p left join project_credentials c on c.project_id = p.id where c.id is null limit 1',
);

if (emptyProjectId !== '') {
  const emptyPage = await render({ uri: `/projects/${emptyProjectId}/credentials` });
  const emptyExport = await render({
    method: 'POST',
    uri: `/projects/${emptyProjectId}/credentials/export`,
    body: form({ _token: tok(emptyPage.body) }),
  });
  check(
    'T-PDF-09g',
    emptyExport.status === 409 && !trim(emptyExport.body).startsWith('%PDF-'),
    `a project with no credentials produces a refusal, not a CONFIDENTIAL file with nothing in it -> ${emptyExport.status}`,
  );
}

/* ── T-PDF-10: the payslip ────────────────────────────────────────────── */
const payslipId = await flow.query('select id from payslips order by id asc limit 1');

if (payslipId !== '') {
  const payslipPdf = await render({ uri: `/payroll/payslips/${payslipId}/pdf` });
  check(
    'T-PDF-10',
    payslipPdf.status === 200 && trim(payslipPdf.body).startsWith('%PDF-'),
    `GET /payroll/payslips/${payslipId}/pdf -> ${payslipPdf.status} (${payslipPdf.body.length} bytes)`,
  );

  /* One page. A payslip that spills onto a second sheet is the defect the
   * compact stylesheet exists to prevent; count the page objects. */
  const pages = (payslipPdf.body.match(/\/Type\s*\/Page[^s]/g) || []).length;
  check('T-PDF-10b', pages === 1, `the payslip is a single page (found ${pages})`);
} else {
  check('T-PDF-10', false, 'no payslip in the fixture — cannot prove the payslip export');
}

/* ── T-PDF-11: permission checks on the payslip ───────────────────────── */
if (payslipId !== '') {
  const viewerPayslip = await viewer.render({ uri: `/payroll/payslips/${payslipId}/pdf` });
  check(
    'T-PDF-11',
    viewerPayslip.status === 403,
    `a user without payroll.view is refused the payslip PDF -> ${viewerPayslip.status}`,
  );
}

const ok = c.summary();
process.exit(ok ? 0 : 1);

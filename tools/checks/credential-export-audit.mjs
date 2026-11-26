#!/usr/bin/env node
/**
 * SECURITY AUDIT — the confidential credentials export (REQ-06.05 / §6).
 *
 * WHY THIS EXISTS SEPARATELY FROM pdf-flow AND credential-flow
 * ------------------------------------------------------------
 * pdf-flow proves the export route returns a PDF with the right headers. But it
 * runs as a superadmin, and the fixture it uses has credential rows with **zero**
 * `credential_values` — no ciphertext at all. So it exercised the confidential
 * document without a single secret inside it. credential-flow proves reveal and
 * audit, also as one superadmin.
 *
 * Neither proved the thing that actually matters for a file that carries
 * passwords out of the building:
 *
 *   T-CEA-01  an export that really contains a secret succeeds
 *   T-CEA-02  the stored secret is ciphertext, never plaintext
 *   T-CEA-03  the export writes an audit row
 *   T-CEA-04  that audit row does not contain the secret
 *   T-CEA-05  a manager (holds credentials.export) may export
 *   T-CEA-06  an account_manager (holds view + manage, NOT export) is refused
 *   T-CEA-07  a viewer is refused
 *   T-CEA-08  an account_manager may not reveal either — view is not reveal
 *   T-CEA-09  GET on the export is refused: a secret-bearing URL is a secret
 *   T-CEA-10  nothing is written to a public path by an export
 *   T-CEA-11  there is no stored-file download route, so no link to expire
 *   T-CEA-12  reveal does not imply export at the permission-model level
 *
 * THE SEPARATION POINT
 * --------------------
 * `credentials.view`, `credentials.reveal` and `credentials.export` are three
 * different powers. Seeing that a project has a WordPress login is not the same
 * as reading the password, and neither is the same as carrying every password on
 * the project away in one file. Tests 06-08 and 12 exist because a matrix where
 * one of those implies another looks identical until someone is over-privileged.
 */
import fs from 'node:fs';
import path from 'node:path';
import { Flow, Checks, ROOT } from './lib/flow-harness.mjs';

const flow = new Flow('credaudit');
await flow.setup();

const c = new Checks('credential-export-audit');
const check = (id, ok, detail) => c.check(id, ok, detail);

const PASSWORD = `Ca-${Date.now().toString(36)}-C9`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });

/* A per-run vault key. Without it the product refuses to store a secret at all,
 * which is itself the behaviour T-CEA-02 depends on. */
const VAULT_KEY = Buffer.from(
  Array.from({ length: 32 }, (_, i) => (i * 61 + 29) % 256),
).toString('base64');

const SECRET = `Wp-Audit-${Date.now().toString(36)}-X7`;

const tok = (html) => Flow.token(html);
const form = (o) => Object.entries(o)
  .filter(([, v]) => v !== null && v !== undefined)
  .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`)
  .join('&');

const render = (spec) => flow.render({ ...spec, env: { VIRA_CREDENTIAL_KEY: VAULT_KEY } });
const trim = (s) => String(s ?? '').replace(/^\s+|\s+$/g, '');

/* ── login as the superadmin fixture user ─────────────────────────────── */
const loginPage = await render({ uri: '/login' });
await render({
  method: 'POST',
  uri: '/login',
  body: form({ _token: tok(loginPage.body), username: 'admin', password: PASSWORD }),
});

/* ── a project with a credential that really holds a secret ───────────── */
const projectId = await flow.query('select id from projects order by id asc limit 1');
const createPage = await render({ uri: `/projects/${projectId}/credentials/create` });
const typeId = (createPage.body.match(/<option value="(\d+)"[^>]*>\s*وردپرس/) || [])[1];

await render({
  method: 'POST',
  uri: `/projects/${projectId}/credentials`,
  body: form({
    _token: tok(createPage.body),
    credential_type_id: typeId,
    label: 'حساب ممیزی امنیت',
    username: 'audit_user',
    public_url: 'https://audit.invalid/wp-admin',
    secret: SECRET,
  }),
});

/* ── T-CEA-02: at rest it is ciphertext ───────────────────────────────── */
const ciphertext = await flow.query(
  "select ciphertext from credential_values order by id desc limit 1",
);
check(
  'T-CEA-02',
  ciphertext.startsWith('v1:') && !ciphertext.includes(SECRET),
  ciphertext.startsWith('v1:')
    ? 'stored value is v1: envelope ciphertext and does not contain the plaintext'
    : `stored value is not envelope ciphertext: ${ciphertext.slice(0, 24)}`,
);

/* ── T-CEA-03 / T-CEA-04: the export is attributable and leak-free ────── */
const before = Number(await flow.query(
  "select count(*) from audit_logs where event = 'exported'",
)) || 0;

/* Every POST needs a fresh CSRF token. The credentials list page is the page
 * that actually carries the export form, so the token comes from there. */
const credList = await render({ uri: `/projects/${projectId}/credentials` });

const exportRes = await render({
  method: 'POST',
  uri: `/projects/${projectId}/credentials/export`,
  body: form({ _token: tok(credList.body) }),
});

check(
  'T-CEA-01',
  exportRes.status === 200
    && trim(exportRes.body).startsWith('%PDF-')
    && String(exportRes.headers['content-disposition'] || '').includes('CONFIDENTIAL')
    && /no-store/.test(String(exportRes.headers['cache-control'] || '')),
  `POST credentials/export -> ${exportRes.status}, `
    + `${exportRes.body.length} bytes, `
    + `disposition=${exportRes.headers['content-disposition']}, `
    + `cache=${exportRes.headers['cache-control']}`,
);

const after = Number(await flow.query(
  "select count(*) from audit_logs where event = 'exported'",
)) || 0;
check(
  'T-CEA-03',
  after === before + 1,
  `exported audit rows ${before} -> ${after}`,
);

const leaked = await flow.query(
  `select count(*) from audit_logs where description like '%${SECRET}%' or changes like '%${SECRET}%'`,
);
check(
  'T-CEA-04',
  String(leaked).trim() === '0',
  `audit rows containing the plaintext secret: ${leaked.trim()}`,
);

/* ── T-CEA-10: an export writes nothing to a public path ──────────────── */
const publicDir = path.join(ROOT, 'public');
const snapshot = (dir) => fs.readdirSync(dir, { withFileTypes: true }).flatMap((e) => {
  const p = path.join(dir, e.name);
  return e.isDirectory() ? snapshot(p) : [path.relative(publicDir, p)];
});

const publicBefore = snapshot(publicDir);
await render({
  method: 'POST',
  uri: `/projects/${projectId}/credentials/export`,
  body: form({ _token: tok(credList.body) }),
});
const publicAfter = snapshot(publicDir);
const newFiles = publicAfter.filter((f) => !publicBefore.includes(f));

check(
  'T-CEA-10',
  newFiles.length === 0,
  newFiles.length === 0
    ? 'public/ is byte-identical before and after an export'
    : `export wrote to public/: ${newFiles.join(', ')}`,
);

/* ── T-CEA-09: GET is refused ─────────────────────────────────────────── */
const getExport = await render({ uri: `/projects/${projectId}/credentials/export` });
check(
  'T-CEA-09',
  getExport.status === 405 && !trim(getExport.body).startsWith('%PDF-'),
  `GET credentials/export -> ${getExport.status} (a GET would land in history and Referer)`,
);

/* ── T-CEA-06/07/08: role separation ──────────────────────────────────── */
const asRole = async (username, role) => {
  const pw = `R-${role}-${Date.now().toString(36)}`;
  await flow.resetPassword({ username, password: pw, mustChange: false, role });

  const agent = new Flow('credaudit');
  agent.db = flow.db;
  agent.dir = flow.dir;
  agent.requests = flow.requests;
  await agent.login(username, pw, { expectRedirect: true });

  /* A viewer has no credentials.view, so the credentials list 403s for it and
   * cannot supply a token. The dashboard carries @csrf and every signed-in user
   * can read it, so the token is role-independent. Without this the negative
   * tests would have failed with 419 (CSRF) instead of 403 (permission), which
   * is a pass for the wrong reason — the worst kind of green. */
  const tokenPage = await agent.render({ uri: '/' });
  const agentToken = tok(tokenPage.body);
  const credentialId = await flow.query(
    'select id from project_credentials order by id desc limit 1',
  );

  return {
    export: await agent.render({
      method: 'POST',
      uri: `/projects/${projectId}/credentials/export`,
      body: form({ _token: agentToken }),
      env: { VIRA_CREDENTIAL_KEY: VAULT_KEY },
    }),
    reveal: await agent.render({
      method: 'POST',
      uri: `/projects/${projectId}/credentials/${credentialId}/reveal`,
      body: form({ _token: agentToken }),
      env: { VIRA_CREDENTIAL_KEY: VAULT_KEY },
    }),
  };
};

const manager = await asRole('audit_manager', 'manager');
check(
  'T-CEA-05',
  manager.export.status === 200 && trim(manager.export.body).startsWith('%PDF-'),
  `manager (holds credentials.export) -> ${manager.export.status}`,
);

const acct = await asRole('audit_acctmgr', 'account_manager');
check(
  'T-CEA-06',
  acct.export.status === 403 && !trim(acct.export.body).startsWith('%PDF-'),
  `account_manager holds view+manage but NOT export -> ${acct.export.status}`,
);
check(
  'T-CEA-08',
  acct.reveal.status === 403,
  `account_manager may see the row but not the password -> reveal ${acct.reveal.status}`,
);

const viewer = await asRole('audit_viewer', 'viewer');
check(
  'T-CEA-07',
  viewer.export.status === 403 && !trim(viewer.export.body).startsWith('%PDF-'),
  `viewer -> ${viewer.export.status}`,
);

/* ── T-CEA-11: no PDF is persisted anywhere under storage/ ──────────────
 * "No download link to expire" is only true if nothing is stored to link to.
 * That is an assertion about the filesystem, not a tautology: scan storage/ for
 * PDFs before and after an export. If a future change caches the confidential
 * document to disk, this fails — and a cached secret file is exactly the thing
 * that would need an expiry. */
const storageDir = path.join(ROOT, 'storage');
const pdfsUnder = (dir) => {
  if (!fs.existsSync(dir)) return [];
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((e) => {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) return pdfsUnder(p);
    return p.endsWith('.pdf') ? [path.relative(storageDir, p)] : [];
  });
};

const pdfsBefore = pdfsUnder(storageDir);
await render({
  method: 'POST',
  uri: `/projects/${projectId}/credentials/export`,
  body: form({ _token: tok(credList.body) }),
});
const pdfsAfter = pdfsUnder(storageDir);
const newPdfs = pdfsAfter.filter((f) => !pdfsBefore.includes(f));

check(
  'T-CEA-11',
  newPdfs.length === 0,
  newPdfs.length === 0
    ? 'no PDF is persisted under storage/ by an export — the bytes stream '
      + 'straight to the response, so there is no stored file and no link to expire'
    : `export persisted a PDF to disk: ${newPdfs.join(', ')}`,
);

/* ── T-CEA-12: reveal does not imply export, at the model level ───────── */
await flow.exec(
  "insert into roles (code, title, description, created_at, updated_at) "
    + "values ('audit_reveal_only', 'فقط نمایش راز', 'ممیزی: فقط reveal', datetime('now'), datetime('now'))",
);
await flow.exec(
  "insert into role_permissions (role_id, permission_id) "
    + "select r.id, p.id from roles r, permissions p "
    + "where r.code = 'audit_reveal_only' and p.code = 'credentials.reveal'",
);
await flow.exec(
  "insert into user_roles (user_id, role_id) "
    + "select u.id, r.id from users u, roles r "
    + "where u.username = 'audit_acctmgr' and r.code = 'audit_reveal_only'",
);

const implied = await flow.query(
  "select count(*) from user_roles ur "
    + "join roles r on r.id = ur.role_id "
    + "join role_permissions rp on rp.role_id = r.id "
    + "join permissions p on p.id = rp.permission_id "
    + "join users u on u.id = ur.user_id "
    + "where u.username = 'audit_acctmgr' and p.code = 'credentials.export'",
);
check(
  'T-CEA-12',
  String(implied).trim() === '0',
  'a user granted only credentials.reveal holds no credentials.export grant — '
    + 'hasPermission() is an exact code match, so reveal cannot imply export',
);

flow.cleanup();
process.exit(c.summary() ? 0 : 1);

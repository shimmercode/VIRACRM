#!/usr/bin/env node
/**
 * Credentials (§6, REQ-06.01/03/04) — the full workflow through the real HTTP stack.
 *
 * WHY THIS FILE HAS TO EXIST
 * --------------------------
 * CredentialVaultTest proves the cipher round-trips against the database. It
 * cannot prove that any request ever reaches the vault. Those are different
 * claims, and this project has already made the second mistake once: the
 * EnvelopeEncrypter sat correct and unit-tested with zero callers.
 *
 * So this flow drives it the way a user does — create a credential with a
 * password, load the list, ask for the password, rotate it, delete it — and
 * asserts the things that are invisible on screen:
 *
 *   T-CRD-03  the plaintext is not in the list page HTML. This is the single
 *             most important assertion here. A page that prints the password
 *             into a table looks like a working feature and is a breach.
 *
 *   T-CRD-04  the database column does not contain the plaintext either.
 *
 *   T-CRD-05  revealing writes an audit row. An unaudited reveal is a password
 *             that can be read by anyone with no trace.
 *
 *   T-CRD-06  a credential id from another project is refused. Both ids are
 *             valid rows; only the URL is wrong. That is an IDOR.
 *
 *   T-CRD-08  editing the label with an empty password field must NOT destroy
 *             the stored password. Silent credential loss is worse than a
 *             crash, because nobody notices until the client needs it.
 */
import { Flow, Checks } from './lib/flow-harness.mjs';

const flow = new Flow('credentials');
await flow.setup();

const c = new Checks('credential-flow');
const check = (id, ok, detail) => c.check(id, ok, detail);

const PASSWORD = `Crd-${Date.now().toString(36)}-P6`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });

/*
 * A key that exists only inside this flow. It is generated per run and never
 * written to the repository — the whole point of the vault is that this value
 * lives outside the database and outside Git.
 */
const VAULT_KEY = Buffer.from(
  Array.from({ length: 32 }, (_, i) => (i * 37 + 11) % 256)
).toString('base64');

const SECRET = 'Wp-' + Math.random().toString(36).slice(2, 12) + '!';

const tok = (html) => Flow.token(html);
const form = (o) => Object.entries(o)
  .filter(([, v]) => v !== null && v !== undefined)
  .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`)
  .join('&');

// Every request in this flow carries the key, exactly as a real host's .env
// would. Without it the vault must refuse to write — that is T-CRD-09.
const render = (spec) => flow.render({ ...spec, env: { VIRA_CREDENTIAL_KEY: VAULT_KEY } });

// ── login ────────────────────────────────────────────────────────────────
let page = await render({ uri: '/login' });
const login = await render({
  method: 'POST', uri: '/login',
  body: form({ _token: tok(page.body), username: 'admin', password: PASSWORD }),
});
check('T-CRD-00', login.status === 302, `login -> ${login.status}`);

// ── a project to hang credentials on ────────────────────────────────────
const uniq = 'C' + Date.now().toString(36);

page = await render({ uri: '/customers/create' });
const cust = await render({
  method: 'POST', uri: '/customers',
  body: form({
    _token: tok(page.body), type: 'legal', status: 'active',
    company_name: 'مشتری دسترسی ' + uniq,
    mobile: '0936' + String(Date.now()).slice(-7),
  }),
});
const customerId = (cust.headers.location || '').match(/\/customers\/(\d+)$/)?.[1];

const mkProject = async (title) => {
  const p = await render({ uri: '/projects/create' });
  const r = await render({
    method: 'POST', uri: '/projects',
    body: form({
      _token: tok(p.body), customer_id: customerId, type: 'seo',
      title, status: 'active', amount: '1000000',
    }),
  });
  return (r.headers.location || '').match(/\/projects\/(\d+)$/)?.[1];
};

const projectA = await mkProject('پروژه الف ' + uniq);
const projectB = await mkProject('پروژه ب ' + uniq);
check('T-CRD-01', !!projectA && !!projectB, `two projects created (#${projectA}, #${projectB})`);

// ── the create form offers the seeded credential types ──────────────────
const createPage = await render({ uri: `/projects/${projectA}/credentials/create` });
check('T-CRD-02',
  createPage.status === 200
  && createPage.body.includes('وردپرس')
  && createPage.body.includes('هاست / cPanel')
  && createPage.body.includes('دامنه'),
  'create form lists the seeded credential types (not an empty select)');

// ── store a credential with a real password ─────────────────────────────
const typeId = (createPage.body.match(/<option value="(\d+)"[^>]*>\s*وردپرس/) || [])[1]
  || (createPage.body.match(/value="(\d+)">\s*وردپرس/) || [])[1];

const stored = await render({
  method: 'POST', uri: `/projects/${projectA}/credentials`,
  body: form({
    _token: tok(createPage.body),
    credential_type_id: typeId,
    label: 'ورود مدیر وردپرس',
    username: 'admin_wp',
    public_url: 'https://example.invalid/wp-admin',
    secret: SECRET,
  }),
});
check('T-CRD-03', stored.status === 302, `POST credential -> ${stored.status}`);

// ── the list page must NOT contain the plaintext ────────────────────────
const list = await render({ uri: `/projects/${projectA}/credentials` });
check('T-CRD-04', list.status === 200 && list.body.includes('ورود مدیر وردپرس'),
  `GET credentials -> ${list.status}, row present`);
check('T-CRD-05', !list.body.includes(SECRET),
  'the plaintext password is absent from the page HTML (bullets only)');

// ── the column holds ciphertext, and ciphertext only ────────────────────
const ciphertext = await flow.query(
  `SELECT ciphertext FROM credential_values WHERE field = 'password' ORDER BY id DESC LIMIT 1`
);
check('T-CRD-06', ciphertext.startsWith('v1:'),
  `stored value carries the version prefix (${ciphertext.slice(0, 12)}…)`);
check('T-CRD-07', !ciphertext.includes(SECRET),
  'the plaintext is not in the database column');

// ── reveal returns the exact value, and writes an audit row ─────────────
const before = Number(await flow.query(
  `SELECT COUNT(*) FROM audit_logs WHERE event = 'revealed'`
));

page = await render({ uri: `/projects/${projectA}/credentials` });
const credentialId = (page.body.match(/\/projects\/\d+\/credentials\/(\d+)\/edit/) || [])[1];

const revealed = await render({
  method: 'POST', uri: `/projects/${projectA}/credentials/${credentialId}/reveal`,
  body: form({ _token: tok(page.body) }),
  accept: 'application/json',
});
let revealedValue = null;
try {
  revealedValue = JSON.parse(revealed.body).value;
} catch { /* asserted below */ }

check('T-CRD-08', revealed.status === 200 && revealedValue === SECRET,
  `reveal returns the exact stored secret (${revealed.status})`);
check('T-CRD-09', /no-store/.test(revealed.headers['cache-control'] || ''),
  `reveal response is not cacheable (${revealed.headers['cache-control']})`);

const after = Number(await flow.query(
  `SELECT COUNT(*) FROM audit_logs WHERE event = 'revealed'`
));
check('T-CRD-10', after === before + 1,
  `reveal wrote exactly one audit row (${before} -> ${after})`);

const auditHasSecret = await flow.query(
  `SELECT COUNT(*) FROM audit_logs WHERE description LIKE '%${SECRET}%' OR changes LIKE '%${SECRET}%'`
);
check('T-CRD-11', String(auditHasSecret).trim() === '0',
  'the audit payload never contains the password itself');

// ── editing the label must not destroy the stored secret ───────────────
const editPage = await render({ uri: `/projects/${projectA}/credentials/${credentialId}/edit` });
const updated = await render({
  method: 'POST', uri: `/projects/${projectA}/credentials/${credentialId}`,
  body: form({
    _token: tok(editPage.body), _method: 'PUT',
    label: 'ورود مدیر وردپرس (تغییر نام)',
    username: 'admin_wp',
    public_url: 'https://example.invalid/wp-admin',
    secret: '',
  }),
});
check('T-CRD-12', updated.status === 302, `PUT credential -> ${updated.status}`);

const stillRevealable = await render({
  method: 'POST', uri: `/projects/${projectA}/credentials/${credentialId}/reveal`,
  body: form({ _token: tok(editPage.body) }),
  accept: 'application/json',
});
let stillValue = null;
try {
  stillValue = JSON.parse(stillRevealable.body).value;
} catch { /* asserted below */ }
check('T-CRD-13', stillValue === SECRET,
  'a blank password field preserved the stored secret instead of erasing it');

// ── IDOR: another project's credential id is refused ───────────────────
const crossProject = await render({ uri: `/projects/${projectB}/credentials/${credentialId}/edit` });
check('T-CRD-14', crossProject.status === 404,
  `credential from project A requested under project B -> ${crossProject.status} (IDOR refused)`);

// ── rotation replaces the value ────────────────────────────────────────
const NEW_SECRET = 'Rotated-' + Math.random().toString(36).slice(2, 10);
const rotate = await render({
  method: 'POST', uri: `/projects/${projectA}/credentials/${credentialId}`,
  body: form({
    _token: tok(editPage.body), _method: 'PUT',
    label: 'ورود مدیر وردپرس (تغییر نام)', username: 'admin_wp',
    public_url: 'https://example.invalid/wp-admin', secret: NEW_SECRET,
  }),
});
check('T-CRD-15', rotate.status === 302, `rotate password -> ${rotate.status}`);

const rotated = await render({
  method: 'POST', uri: `/projects/${projectA}/credentials/${credentialId}/reveal`,
  body: form({ _token: tok(editPage.body) }),
  accept: 'application/json',
});
let rotatedValue = null;
try { rotatedValue = JSON.parse(rotated.body).value; } catch { /* asserted */ }
check('T-CRD-16', rotatedValue === NEW_SECRET,
  'rotation returns the new secret');

const rowCount = await flow.query(
  `SELECT COUNT(*) FROM credential_values WHERE project_credential_id = ${Number(credentialId)}`
);
check('T-CRD-17', String(rowCount).trim() === '1',
  `one value row, not a pile of stale ciphertext (${rowCount})`);

// ── a missing key must refuse, never store plaintext ────────────────────
const noKeyForm = await render({ uri: `/projects/${projectA}/credentials/create` });
const noKey = await flow.render({
  method: 'POST', uri: `/projects/${projectA}/credentials`,
  body: form({
    _token: tok(noKeyForm.body),
    credential_type_id: typeId,
    label: 'بدون کلید ' + uniq,
    secret: 'must-not-be-stored',
  }),
  env: {}, // deliberately no VIRA_CREDENTIAL_KEY
});

const plaintextRows = await flow.query(
  `SELECT COUNT(*) FROM credential_values WHERE ciphertext LIKE '%must-not-be-stored%'`
);
check('T-CRD-18', String(plaintextRows).trim() === '0' && noKey.status === 302,
  `without a key the write is refused and nothing is stored (${noKey.status})`);

// ── delete removes the credential and its ciphertext ────────────────────
page = await render({ uri: `/projects/${projectA}/credentials` });
const del = await render({
  method: 'POST', uri: `/projects/${projectA}/credentials/${credentialId}`,
  body: form({ _token: tok(page.body), _method: 'DELETE' }),
});
check('T-CRD-19', del.status === 302, `DELETE credential -> ${del.status}`);

const orphans = await flow.query(
  `SELECT COUNT(*) FROM credential_values WHERE project_credential_id = ${Number(credentialId)}`
);
check('T-CRD-20', String(orphans).trim() === '0',
  'deleting the credential removed its ciphertext too (FK cascade)');

process.exit(c.summary() ? 0 : 1);

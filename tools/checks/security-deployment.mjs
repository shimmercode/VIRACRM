/**
 * Security and HTTPS deployment acceptance (§10, gate 3).
 *
 * `security-headers.mjs` covers the response headers and cookie flags. This
 * covers the deployment-side controls that a header check cannot reach, and
 * which were previously asserted only by reading the code:
 *
 *   · client IP detection behind a reverse proxy, including the spoofing case
 *     that makes a wildcard TRUSTED_PROXIES dangerous;
 *   · session invalidation on logout and expiry by lifetime;
 *   · real key generation, and that the generated key actually works;
 *   · backup destination validation against path traversal and a symlink that
 *     resolves into the document root;
 *   · no key material leaking into the log.
 *
 * Everything runs on a private flow database. HTTPS termination itself cannot
 * be exercised here — there is no TLS terminator — and is reported BLOCKED
 * rather than passed.
 */
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { Checks, Flow, php } from './lib/flow-harness.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const c = new Checks('security deployment');
const check = (id, ok, detail) => c.check(id, ok, detail);

const SHARED = path.join(ROOT, 'database/database.sqlite');
const sha = (p) => crypto.createHash('sha256').update(fs.readFileSync(p)).digest('hex');
const sharedBefore = sha(SHARED);

const PROXY = '10.0.0.5';        // the configured reverse proxy
const CLIENT = '203.0.113.9';    // the real end user, per X-Forwarded-For
const ATTACKER = '198.51.100.7'; // a direct client that is NOT the proxy

const work = path.join(ROOT, '.arena/security-deployment');
fs.rmSync(work, { recursive: true, force: true });
fs.mkdirSync(work, { recursive: true });

const flow = new Flow('secdeploy');
await flow.setup();

const PASSWORD = `Sd-${Date.now().toString(36)}-Q4`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });

/** Log in as admin from a given peer address, with optional forwarded headers. */
async function loginAs({ remote_addr, headers = {}, env = {} }) {
  const f = new Flow(`secdep-${crypto.randomBytes(3).toString('hex')}`);
  await f.setup();
  await f.resetPassword({ password: PASSWORD, mustChange: false });

  const page = await f.render({ uri: '/login', remote_addr, env });
  const token = Flow.token(page.body);
  const res = await f.render({
    method: 'POST',
    uri: '/login',
    remote_addr,
    headers,
    env,
    body: `_token=${encodeURIComponent(token)}&username=admin&password=${encodeURIComponent(PASSWORD)}`,
  });

  const ip = String(await f.query('select last_login_ip from users where username = \'admin\'')).trim();
  const cookie = (page.headers['set-cookie'] ?? '').split(' | ')
    .find((s) => s.toLowerCase().includes('session')) ?? '';

  return { f, res, ip, cookie };
}

/* ═══ Client IP behind a reverse proxy ══════════════════════════════════ */

const noProxyCfg = await loginAs({
  remote_addr: '127.0.0.1',
  headers: { 'X-Forwarded-For': CLIENT },
});
check('SD-IP-UNSET', noProxyCfg.ip === '127.0.0.1',
  `TRUSTED_PROXIES unset: a client-supplied X-Forwarded-For: ${CLIENT} is IGNORED`
  + ` and last_login_ip is the real peer ${noProxyCfg.ip} — the safe default`);

const trusted = await loginAs({
  remote_addr: PROXY,
  headers: { 'X-Forwarded-For': CLIENT },
  env: { TRUSTED_PROXIES: PROXY },
});
check('SD-IP-TRUSTED', trusted.ip === CLIENT,
  `TRUSTED_PROXIES=${PROXY} and the request really came from ${PROXY}:`
  + ` last_login_ip is the forwarded client ${trusted.ip}, not the proxy`);

const spoof = await loginAs({
  remote_addr: ATTACKER,
  headers: { 'X-Forwarded-For': CLIENT },
  env: { TRUSTED_PROXIES: PROXY },
});
check('SD-IP-SPOOF', spoof.ip === ATTACKER,
  `TRUSTED_PROXIES=${PROXY} but the request came from ${ATTACKER}: the forged`
  + ` X-Forwarded-For is ignored and last_login_ip is ${spoof.ip}. This is why`
  + ` TRUSTED_PROXIES must never be '*' — a wildcard would record ${CLIENT} here`
  + ` and let one attacker impersonate any address past IP throttling`);

/* ═══ Session invalidation ══════════════════════════════════════════════ */

const sess = await loginAs({ remote_addr: '127.0.0.1' });
const authed = await sess.f.render({ uri: '/' });
check('SD-SESS-AUTH', authed.status === 200,
  `an authenticated request returns ${authed.status} before logout`);

/*
 * Count AUTHENTICATED sessions only. `sessions.user_id` is populated by the
 * database driver, and that is the row that matters: a flow legitimately also
 * holds an anonymous row created before login, and logout leaves a fresh
 * anonymous row behind, so a total row count is the same before and after and
 * proves nothing either way.
 */
const authedSessions = async (f) => Number(await f.query('select count(*) from sessions where user_id is not null'));

const beforeAuthed = await authedSessions(sess.f);
const logoutPage = await sess.f.render({ uri: '/' });
const logoutToken = Flow.token(logoutPage.body);
const logout = await sess.f.render({
  method: 'POST', uri: '/logout',
  body: `_token=${encodeURIComponent(logoutToken)}`,
});
const after = await sess.f.render({ uri: '/' });
const afterAuthed = await authedSessions(sess.f);

check('SD-SESS-LOGOUT', after.status >= 300 && after.headers.location?.includes('/login'),
  `after POST /logout the same cookie is rejected: / returns ${after.status}`
  + ` → ${after.headers.location ?? '(no redirect)'}`);

/*
 * Not a row count. Invalidate() destroys the old session and the follow-up
 * anonymous request immediately creates a new one, so the total is unchanged —
 * counting rows would have "passed" on a logout that destroyed nothing. The
 * assertion that means something is that the specific authenticated id is gone.
 */
check('SD-SESS-ROWGONE', beforeAuthed > 0 && afterAuthed === 0,
  `authenticated session rows: ${beforeAuthed} before POST /logout,`
  + ` ${afterAuthed} after. The row is destroyed rather than flagged, so a`
  + ` captured cookie cannot be replayed`);

/* ═══ Session expiry by lifetime ════════════════════════════════════════ */

const exp = await loginAs({ remote_addr: '127.0.0.1' });
const liveCheck = await exp.f.render({ uri: '/' });
// Beyond the 120-minute lifetime. Flow::exec() is INSERT-only by contract, so
// this goes through a guarded helper that refuses the shared database.
const aged = await php('tools/checks/session-age.php', [exp.f.db, String(130 * 60)]);
const afterExpiry = await exp.f.render({ uri: '/' });

check('SD-SESS-EXPIRY', /aged [1-9]\d* session/.test(aged.out)
  && liveCheck.status === 200
  && afterExpiry.status >= 300 && (afterExpiry.headers.location ?? '').includes('/login'),
  `${aged.out.trim().split('\n')[0]}; the same cookie works while fresh`
  + ` (${liveCheck.status}) and is refused once last_activity is pushed past`
  + ` SESSION_LIFETIME=120 (${afterExpiry.status} → ${afterExpiry.headers.location ?? '(no redirect)'})`);

/* ═══ Key generation ════════════════════════════════════════════════════ */

const PROBE = 'tools/checks/artisan-probe.php';
const dbA = path.join(work, 'keys.sqlite');
fs.copyFileSync(SHARED, dbA);

const keyRun = await php(PROBE, [dbA, '--', 'vira:backup-key']);
const emitted = (keyRun.out.match(/[A-Za-z0-9+/]{43}=/) || [])[0] ?? '';
const decoded = emitted ? Buffer.from(emitted, 'base64') : Buffer.alloc(0);

check('SD-KEY-32', decoded.length === 32,
  `vira:backup-key emits base64 that decodes to exactly ${decoded.length} bytes`
  + ` (AES-256 needs 32)`);

const keyRun2 = await php(PROBE, [dbA, '--', 'vira:backup-key']);
const emitted2 = (keyRun2.out.match(/[A-Za-z0-9+/]{43}=/) || [])[0] ?? '';
check('SD-KEY-RANDOM', emitted !== '' && emitted !== emitted2,
  `two runs produce different keys — it is generated, not a constant`);

/* The generated key must actually be accepted by the backup path. */
const keyDir = path.join(work, 'withkey');
const roundTrip = await php(PROBE, [dbA, `VIRA_BACKUP_KEY=${emitted}`, '--',
  'vira:backup', `--path=${keyDir}`]);
const made = fs.existsSync(keyDir) ? fs.readdirSync(keyDir).filter((f) => f.endsWith('.vbk1')) : [];

check('SD-KEY-WORKS', roundTrip.out.includes('[exit 0]') && made.length === 1,
  `the freshly generated key produces a container (${made[0] ?? 'none'}) —`
  + ` generation and consumption agree on the format`);

/* ═══ Backup destination: traversal and symlink ═════════════════════════ */

const traversal = await php(PROBE, [dbA, `VIRA_BACKUP_KEY=${emitted}`, '--',
  'vira:backup', '--path=.arena/../public/backups']);
check('SD-BK-TRAVERSAL', !traversal.out.includes('[exit 0]')
  && traversal.out.includes('document root'),
  `--path=.arena/../public/backups is refused — the '..' is resolved before the`
  + ` check, so it cannot be used to walk into the document root`);

const linkDir = path.join(work, 'linkdir');
fs.mkdirSync(linkDir, { recursive: true });
const link = path.join(linkDir, 'escape');
try { fs.symlinkSync(path.join(ROOT, 'public'), link, 'dir'); } catch { /* exists */ }

const symlinkRun = await php(PROBE, [dbA, `VIRA_BACKUP_KEY=${emitted}`, '--',
  'vira:backup', `--path=${link}/backups`]);
const escaped = fs.existsSync(path.join(ROOT, 'public/backups'));

check('SD-BK-SYMLINK', !symlinkRun.out.includes('[exit 0]')
  && symlinkRun.out.includes('document root') && !escaped,
  `a symlink pointing at public/ is refused — realpath() follows the link, so`
  + ` the destination is judged by where it lands, not by how it is spelled`);

check('SD-BK-PUBCLEAN', !fs.existsSync(path.join(ROOT, 'public/backups'))
  && fs.readdirSync(path.join(ROOT, 'public')).every((f) => !f.startsWith('vira-backup')),
  `public/ holds ${fs.readdirSync(path.join(ROOT, 'public')).join(', ')} —`
  + ` no container reached the document root`);

/* ═══ No secret material in the log ═════════════════════════════════════ */

const logFile = path.join(ROOT, 'storage/logs/laravel.log');
const logText = fs.existsSync(logFile) ? fs.readFileSync(logFile, 'utf8') : '';
const leaked = emitted !== '' && logText.includes(emitted);
const leakedAny = /VIRA_BACKUP_KEY=[A-Za-z0-9+/=]{20,}/.test(logText);

check('SD-LOG-CLEAN', !leaked && !leakedAny,
  `${logText ? `${logText.length} bytes of` : 'no'} log content contains no`
  + ` VIRA_BACKUP_KEY value or assignment`);

/* ═══ no-store on an authenticated data page ════════════════════════════ */

const dataFlow = new Flow('secdepdata');
await dataFlow.setup();
await dataFlow.resetPassword({ password: PASSWORD, mustChange: false });
await dataFlow.login('admin', PASSWORD);
const data = await dataFlow.render({ uri: '/customers' });
const cc = data.headers['cache-control'] ?? '';

check('SD-NOSTORE', data.status === 200 && cc.includes('no-store'),
  `/customers returns ${data.status} with Cache-Control: ${cc} — a page listing`
  + ` customer records is never storeable by a shared cache`);

/* ═══ Isolation ═════════════════════════════════════════════════════════ */

check('SD-ISO', sha(SHARED) === sharedBefore,
  `database/database.sqlite unchanged: ${sharedBefore.slice(0, 16)}…`);

fs.rmSync(work, { recursive: true, force: true });
try { fs.rmSync(link, { force: true }); } catch { /* already gone */ }

process.exit(c.summary() ? 0 : 1);

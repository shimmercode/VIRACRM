#!/usr/bin/env node
/**
 * VIRA CRM — isolated flow harness.
 *
 * WHY THIS EXISTS
 * ---------------
 * The regression flows used to share one database. That made them order
 * dependent in three ways:
 *
 *   1. `authed-flow` changes the admin password (it verifies the forced-change
 *      path), so every later flow logged in with a stale credential.
 *   2. Sessions are stored in the `sessions` table of that same database, so
 *      signing in or out in one flow invalidated another flow's cookie.
 *   3. `crud-flow` inserts customers and contracts, which then appeared in
 *      screenshots and changed counts other checks asserted against.
 *
 * Running them in one shell loop therefore produced a spurious `1/13` for
 * `crud-flow` even though nothing was broken. A test suite whose result depends
 * on execution order is not a test suite.
 *
 * THE FIX
 * -------
 * Every flow gets a private SQLite database, created by copying the seeded
 * database into `.arena/flows/<name>/`. The renderer receives `DB_DATABASE` as
 * a per-request env override (see tools/http-render.php), so the flow can
 * create records, rotate passwords and burn sessions without any other flow
 * observing it. The shared database is never written to.
 *
 * Each flow also gets its own cookie jar, and a password it controls
 * (`resetPassword()`) rather than one it inherits from whatever ran before.
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';

// lib/ -> checks/ -> tools/ -> repo root
export const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');

const NODE = process.execPath;
const PHP_CLI = path.join(ROOT, 'docs/tools/php-cli.mjs');
const RENDERER = path.join(ROOT, 'tools/http-render.php');
const PHP_WASM_PATH = process.env.PHP_WASM_PATH || '/tmp/phpwasm/node_modules';
const BASE_DB = process.env.VIRA_BASE_DB || path.join(ROOT, 'database/database.sqlite');
const FLOW_ROOT = process.env.VIRA_FLOW_ROOT || path.join(ROOT, '.arena/flows');

/** Run one PHP script through the WASM CLI and return its stdout. */
export function php(script, args = [], { env = {} } = {}) {
  return new Promise((resolve) => {
    const child = spawn(NODE, [PHP_CLI, script, ...args], {
      cwd: ROOT,
      env: { ...process.env, PHP_WASM_PATH, ...env },
    });

    let out = '';
    let err = '';
    child.stdout.on('data', (d) => { out += d; });
    child.stderr.on('data', (d) => { err += d; });

    const timer = setTimeout(() => child.kill('SIGKILL'), 120_000);
    child.on('close', () => {
      clearTimeout(timer);
      resolve({ out: out.replace(/^\[php-cli\][^\n]*\n?/gm, ''), err, raw: out });
    });
  });
}

/**
 * A flow with its own database, cookie jar and password.
 *
 * Names must be unique per flow; the directory is wiped on every run so a
 * previous run cannot leak state into this one.
 */
export class Flow {
  constructor(name) {
    this.name = name;
    this.dir = path.join(FLOW_ROOT, name);
    this.db = path.join(this.dir, 'database.sqlite');
    this.jar = new Map();
    this.requests = 0;
  }

  /** Wipe the flow directory and take a fresh copy of the seeded database. */
  async setup() {
    if (!fs.existsSync(BASE_DB)) {
      throw new Error(`no seeded database at ${BASE_DB}; run tools/seed.php --demo first`);
    }

    fs.rmSync(this.dir, { recursive: true, force: true });
    fs.mkdirSync(this.dir, { recursive: true });

    // `.sqlite-wal` / `-shm` must not travel with the copy, or SQLite may
    // replay a journal written by a different database.
    fs.copyFileSync(BASE_DB, this.db);
    for (const suffix of ['-wal', '-shm']) {
      if (fs.existsSync(BASE_DB + suffix)) {
        fs.copyFileSync(BASE_DB + suffix, this.db + suffix);
      }
    }

    this.jar.clear();
    this.requests = 0;

    return this;
  }

  /** Path of the request file for the next render; kept unique per request. */
  _specFile() {
    this.requests += 1;

    return path.join(this.dir, `req-${String(this.requests).padStart(4, '0')}.json`);
  }

  /** Perform one HTTP request through the real kernel, against this flow's DB. */
  render(spec) {
    const file = this._specFile();

    fs.writeFileSync(file, JSON.stringify({
      method: spec.method || 'GET',
      uri: spec.uri,
      host: 'localhost:8080',
      cookie: [...this.jar].map(([k, v]) => `${k}=${v}`).join('; '),
      post_body: spec.body || '',
      content_type: spec.body ? 'application/x-www-form-urlencoded' : '',
      accept: spec.accept || 'text/html',
      // The isolation: this flow's database, applied before bootstrap.
      //
      // A caller's own `env` is merged on top rather than replaced — otherwise
      // passing `VIRA_CREDENTIAL_KEY` would silently drop DB_DATABASE and the
      // request would run against the SHARED database instead of the flow's
      // copy. That is precisely the isolation failure this harness exists to
      // prevent, so the merge direction is not incidental.
      env: { DB_DATABASE: this.db, ...(spec.env || {}) },
    }));

    return new Promise((resolve) => {
      const child = spawn(NODE, [PHP_CLI, RENDERER, file], {
        cwd: ROOT,
        env: { ...process.env, PHP_WASM_PATH },
      });

      let out = '';
      let err = '';
      child.stdout.on('data', (d) => { out += d; });
      child.stderr.on('data', (d) => { err += d; });

      const timer = setTimeout(() => child.kill('SIGKILL'), 120_000);
      child.on('close', () => {
        clearTimeout(timer);

        const clean = out.replace(/^\[php-cli\][^\n]*\n?/gm, '');
        const status = Number((clean.match(/^VIRA_STATUS (\d+)/m) || [0, 0])[1]);
        const bodyAt = clean.indexOf('VIRA_BODY\n');
        const head = bodyAt === -1 ? clean : clean.slice(0, bodyAt);

        const headers = {};
        const cookies = [];
        for (const line of head.split('\n')) {
          const m = line.match(/^VIRA_HEADER ([^:]+):\s*(.*)$/);
          if (!m) continue;
          const k = m[1].toLowerCase();
          if (k === 'set-cookie') cookies.push(m[2]);
          headers[k] = headers[k] ? `${headers[k]} | ${m[2]}` : m[2];
        }

        for (const c of cookies) {
          const [pair] = c.split(';');
          const i = pair.indexOf('=');
          if (i > 0) this.jar.set(pair.slice(0, i).trim(), pair.slice(i + 1).trim());
        }

        resolve({
          status,
          headers,
          body: bodyAt === -1 ? clean : clean.slice(bodyAt + 9),
          err,
        });
      });
    });
  }

  /** Extract a CSRF token from a rendered page. */
  static token(html) {
    return (html.match(/name="_token" value="([^"]+)"/) || [])[1]
      || (html.match(/name="csrf-token" content="([^"]+)"/) || [])[1];
  }

  /**
   * Set this flow's admin password to a value the flow owns.
   *
   * Runs against the flow's private database, so no other flow sees it and no
   * file outside `.arena/flows/<name>/` records it.
   */
  async resetPassword({ username = 'admin', password, mustChange = false } = {}) {
    const result = await php(path.join(ROOT, 'tools/checks/flow-user.php'), [
      this.db, username, password, mustChange ? '1' : '0',
    ]);

    if (!/^OK /m.test(result.out)) {
      throw new Error(`could not prepare ${username} in ${this.name}: ${result.out}${result.err}`);
    }

    return password;
  }

  /** Log in and leave the session cookie in this flow's jar. */
  async login(username, password, { expectRedirect = true } = {}) {
    const page = await this.render({ uri: '/login' });
    const token = Flow.token(page.body);

    if (!token) throw new Error(`${this.name}: no CSRF token on /login`);

    const res = await this.render({
      method: 'POST',
      uri: '/login',
      body: `_token=${encodeURIComponent(token)}`
        + `&username=${encodeURIComponent(username)}`
        + `&password=${encodeURIComponent(password)}`,
    });

    if (expectRedirect && res.status !== 302) {
      throw new Error(`${this.name}: login returned ${res.status}, expected 302`);
    }

    return res;
  }

  /** Counters over a flow's own database, read without touching the app. */
  async query(sql) {
    const result = await php(path.join(ROOT, 'tools/checks/flow-query.php'), [this.db, sql]);

    return result.out.trim();
  }

  /** Remove the flow directory. Keeps `.arena/` from growing without bound. */
  cleanup() {
    fs.rmSync(this.dir, { recursive: true, force: true });
  }
}

/** Tiny assertion recorder shared by every flow. */
export class Checks {
  constructor(name) {
    this.name = name;
    this.results = [];
  }

  check(id, passed, detail) {
    this.results.push({ id, passed: Boolean(passed), detail });
    console.log(`${passed ? 'PASS' : 'FAIL'}  ${id.padEnd(14)} ${detail}`);

    return passed;
  }

  get passed() {
    return this.results.filter((r) => r.passed).length;
  }

  get failed() {
    return this.results.length - this.passed;
  }

  summary() {
    console.log(`\n--- ${this.name}: ${this.passed}/${this.results.length} passed ---`);

    return this.failed === 0;
  }
}

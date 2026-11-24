#!/usr/bin/env node
/**
 * review-env.mjs — one command to operate the CRM Review Environment.
 *
 *   node tools/review-env.mjs init     create the review database copy
 *   node tools/review-env.mjs seed     fill it with realistic fake data
 *   node tools/review-env.mjs reset    remove exactly the seeded rows
 *   node tools/review-env.mjs status   row counts
 *   node tools/review-env.mjs serve    start the review server on :4173
 *   node tools/review-env.mjs url      print the URL and the state
 *
 * Every command prints what it did in plain language, because the person
 * running it may be the client, not the developer.
 *
 * SAFETY MODEL
 * ------------
 * The review instance only ever opens database/review/crm-review.sqlite. The
 * file lives in a git-ignored directory, the seeder refuses any path outside
 * it, and the server is started with DB_DATABASE pointed at it — so a bug in
 * one command cannot reach the development database, let alone production.
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const REVIEW_DB = path.join(ROOT, 'database/review/crm-review.sqlite');
const BASELINE_DB = path.join(ROOT, 'database/database.sqlite');
const SCRIPT = path.join(ROOT, 'tools/review-seed.php');
const PORT = Number(process.env.REVIEW_PORT || 4173);

const php = (args) => {
  const r = spawnSync(process.execPath, [path.join(ROOT, 'docs/tools/php-cli.mjs'), SCRIPT, ...args], {
    cwd: ROOT, encoding: 'utf8', timeout: 600_000,
  });
  if (r.status !== 0) {
    // php-cli.mjs exits 0 even when the script exits non-zero, so the guard
    // messages arrive on stdout prefixed with "REFUSED".
    console.error((r.stdout || '') + (r.stderr || ''));
    process.exit(r.status ?? 1);
  }
  if ((r.stdout || '').includes('REFUSED')) {
    console.error(r.stdout);
    process.exit(3);
  }
  process.stdout.write(r.stdout || '');
};

const command = process.argv[2] || 'status';

switch (command) {
  case 'init': {
    if (!fs.existsSync(BASELINE_DB)) {
      console.error(`baseline database missing: ${BASELINE_DB}`);
      process.exit(1);
    }
    fs.mkdirSync(path.dirname(REVIEW_DB), { recursive: true });
    if (fs.existsSync(REVIEW_DB)) {
      const backup = REVIEW_DB.replace(/\.sqlite$/, `.backup-${Date.now()}.sqlite`);
      fs.renameSync(REVIEW_DB, backup);
      console.log(`existing review database moved to ${path.relative(ROOT, backup)}`);
    }
    fs.copyFileSync(BASELINE_DB, REVIEW_DB);
    const kb = Math.round(fs.statSync(REVIEW_DB).size / 1024);
    console.log(`created ${path.relative(ROOT, REVIEW_DB)} (${kb} KB) from the baseline schema`);
    console.log('the baseline database itself was only read, never written');
    break;
  }

  case 'seed':
    if (!fs.existsSync(REVIEW_DB)) {
      console.error('no review database yet — run: node tools/review-env.mjs init');
      process.exit(1);
    }
    php(['seed', REVIEW_DB]);
    break;

  case 'reset':
    if (!fs.existsSync(REVIEW_DB)) {
      console.error('no review database yet — run: node tools/review-env.mjs init');
      process.exit(1);
    }
    php(['reset', REVIEW_DB]);
    break;

  case 'status':
    if (!fs.existsSync(REVIEW_DB)) {
      console.log('review database: NOT CREATED (run: node tools/review-env.mjs init)');
      process.exit(0);
    }
    php(['status', REVIEW_DB]);
    console.log(`\nserver  node tools/review-env.mjs serve   (port ${PORT})`);
    console.log(`review  VIRA_REVIEW_ENABLED is read from .env and must be true`);
    break;

  case 'serve': {
    if (!fs.existsSync(REVIEW_DB)) {
      console.error('no review database yet — run: node tools/review-env.mjs init && node tools/review-env.mjs seed');
      process.exit(1);
    }
    const child = spawn(process.execPath, [path.join(ROOT, 'tools/dev-server.mjs'), '--port', String(PORT)], {
      cwd: ROOT,
      stdio: 'inherit',
      env: { ...process.env, DB_DATABASE: REVIEW_DB },
    });
    child.on('exit', (code) => process.exit(code ?? 0));
    break;
  }

  case 'url':
    console.log(`local   http://127.0.0.1:${PORT}/review`);
    console.log(`preview https://${PORT}-{sandboxId}.e2b.app/review`);
    break;

  default:
    console.error(`unknown command: ${command}`);
    console.error('commands: init | seed | reset | status | serve | url');
    process.exit(2);
}

#!/usr/bin/env node
/**
 * VIRA CRM — migration safety on a disposable database.
 *
 * WHY THIS SUITE EXISTS
 * ---------------------
 * A schema change is the one kind of edit that can destroy data, and it cannot
 * be exercised against `database/database.sqlite`: that file is what the product
 * develops against, and no migration may run against it.
 *
 * So every assertion here runs against a throwaway file under `.arena/`, created
 * empty and driven through the real migrator by tools/checks/migration-probe.php
 * — which refuses, by path, to touch anything that is not scratch.
 *
 * The subject is 2026_02_02_000100_add_website_url_to_seo_project_details
 * (REQ-05.02, §5-1 «آدرس وب‌سایت»), but the shape of the test applies to any
 * additive migration: up, existing rows survive, down, up again.
 *
 *   T-MS-01  the probe refuses the shared database
 *   T-MS-02  the probe refuses a path outside the scratch directories,
 *            including one that reaches the shared file by traversal
 *   T-MS-03  the full migration chain applies to an empty database
 *   T-MS-04  the new column exists after up
 *   T-MS-05  a row written BEFORE the column existed survives the up, and its
 *            new column reads back NULL rather than being rewritten
 *   T-MS-06  rollback removes the column and nothing else
 *   T-MS-07  rows written before the rollback survive it
 *   T-MS-08  re-migrating restores the column
 *   T-MS-09  a value stored in the column persists across a re-read
 *   T-MS-10  the migration is idempotent: a second up on an already-migrated
 *            database is a no-op, not a fatal
 *   T-MS-11  the shared database is byte-identical throughout
 */
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { Checks, php } from './lib/flow-harness.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const SHARED_DB = path.join(ROOT, 'database/database.sqlite');
const PROBE = path.join(ROOT, 'tools/checks/migration-probe.php');
const WORK = path.join(ROOT, '.arena/migration-safety');
const DB = path.join(WORK, 'probe.sqlite');

const c = new Checks('migration-safety');
const check = (id, ok, detail) => c.check(id, ok, detail);

const sha = (file) => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
const sharedBefore = sha(SHARED_DB);

const probe = async (dbArg, command) => {
  const r = await php(PROBE, [dbArg, command]);

  return { out: `${r.out ?? ''}${r.err ?? ''}` };
};

/**
 * One statement through python3's sqlite3. There is no sqlite3 CLI here, and
 * opening the file from PHP would mean booting Laravel just to read a column.
 */
const sql = (statement, db = DB) => {
  const script = 'import sqlite3, json, sys\n'
    + `c = sqlite3.connect(${JSON.stringify(db)})\n`
    + `print(json.dumps(c.execute(${JSON.stringify(statement)}).fetchall(), default=str))\n`;
  const res = spawnSync('python3', ['-c', script], { encoding: 'utf8' });

  if (res.status !== 0) {
    throw new Error(`sql failed: ${res.stderr}`);
  }

  return JSON.parse(res.stdout.trim());
};

const write = (statement, db = DB) => {
  const script = 'import sqlite3\n'
    + `c = sqlite3.connect(${JSON.stringify(db)})\n`
    + `c.execute(${JSON.stringify(statement)})\n`
    + 'c.commit()\n';
  const res = spawnSync('python3', ['-c', script], { encoding: 'utf8' });

  if (res.status !== 0) {
    throw new Error(`write failed: ${res.stderr}`);
  }
};

const columns = () => sql('pragma table_info(seo_project_details)')[0]
  ? sql('pragma table_info(seo_project_details)').map((r) => r[1])
  : [];

/* ── T-MS-01/02: the probe's own guards ───────────────────────────────── */
const refusedShared = await probe('database/database.sqlite', 'migrate');
check('T-MS-01', refusedShared.out.includes('refusing to run a migration against the shared database'),
  'migration-probe.php pointed at database/database.sqlite -> '
  + refusedShared.out.trim().split('\n')[0]);

const refusedTraversal = await probe('.arena/../database/database.sqlite', 'migrate');
const refusedOutside = await probe('../escape.sqlite', 'migrate');
check('T-MS-02', refusedTraversal.out.includes('shared database')
  && refusedOutside.out.includes('only .arena/ or /tmp/ paths are allowed'),
  `traversal ".arena/../database/database.sqlite" -> ${refusedTraversal.out.trim().split('\n')[0]}; `
  + `"../escape.sqlite" -> ${refusedOutside.out.trim().split('\n')[0]}`);

/* ── T-MS-03: a clean database through the whole chain ────────────────── */
fs.rmSync(WORK, { recursive: true, force: true });
fs.mkdirSync(WORK, { recursive: true });
fs.writeFileSync(DB, '');

// Step back one migration so the subject migration has not run yet.
const up1 = await probe(path.relative(ROOT, DB), 'migrate');
const back = await probe(path.relative(ROOT, DB), 'migrate:rollback');
const beforeColumns = columns();

check('T-MS-03', up1.out.includes('DONE') && !up1.out.includes('MIGRATION FAILED')
  && back.out.includes('add_website_url_to_seo_project_details'),
  `empty database migrated through the chain and stepped back one: `
  + `${beforeColumns.includes('website_url') ? 'column still present' : 'subject column absent, as intended'}`);

/* ── T-MS-05 (setup): a row that predates the column ──────────────────── */
write(`insert into projects (id, customer_id, type, title, status, amount, start_date,
  created_at, updated_at) values (9501, 1, 'seo', 'پروژهٔ پیش از ستون', 'active', 1000000,
  date('now'), datetime('now'), datetime('now'))`);
write(`insert into customers (id, type, company_name, status, created_at, updated_at)
  values (1, 'legal', 'شرکت آزمون', 'active', datetime('now'), datetime('now'))`);
write(`insert into seo_project_details (project_id, keyword_count, recurrence,
  reminder_offset_days, created_at, updated_at)
  values (9501, 12, 'monthly', 2, datetime('now'), datetime('now'))`);

const rowBefore = sql('select project_id, keyword_count from seo_project_details where project_id = 9501');

/* ── T-MS-04: up adds the column ──────────────────────────────────────── */
const up2 = await probe(path.relative(ROOT, DB), 'migrate');
const afterColumns = columns();

check('T-MS-04', !up2.out.includes('MIGRATION FAILED')
  && afterColumns.includes('website_url') && afterColumns.includes('financial_notes'),
  `re-migrating -> columns ${afterColumns.join(', ')}`);

/* ── T-MS-05: the pre-existing row ────────────────────────────────────── */
const rowAfter = sql('select project_id, keyword_count, website_url from seo_project_details where project_id = 9501');
const rowIntact = rowBefore.length === 1 && rowAfter.length === 1
  && rowAfter[0][0] === rowBefore[0][0] && String(rowAfter[0][1]) === String(rowBefore[0][1]);

check('T-MS-05', rowIntact && rowAfter[0]?.[2] === null,
  `the row written before the column existed survived the up unchanged `
  + `(keyword_count ${rowBefore[0]?.[1]} -> ${rowAfter[0]?.[1]}) and its new column reads ${rowAfter[0]?.[2]}`);

/* ── T-MS-06/07: rollback ─────────────────────────────────────────────── */
const down = await probe(path.relative(ROOT, DB), 'migrate:rollback');
const downColumns = columns();

check('T-MS-06', !down.out.includes('MIGRATION FAILED') && !downColumns.includes('website_url')
  && !downColumns.includes('financial_notes')
  && downColumns.includes('keyword_count') && downColumns.includes('project_id'),
  `rollback removed website_url and nothing else: ${downColumns.join(', ')}`);

const rowAfterDown = sql('select project_id, keyword_count from seo_project_details where project_id = 9501');
check('T-MS-07', rowAfterDown.length === 1 && String(rowAfterDown[0][1]) === '12',
  `the row is still there after the rollback: ${JSON.stringify(rowAfterDown[0])}`);

/* ── T-MS-08/09: re-up, and a value survives a re-read ────────────────── */
const up3 = await probe(path.relative(ROOT, DB), 'migrate');
write(`update seo_project_details set website_url = 'https://example.ir/blog' where project_id = 9501`);
const stored = sql('select website_url from seo_project_details where project_id = 9501');
const stillThere = sql('select project_id from seo_project_details where project_id = 9501');

check('T-MS-08', !up3.out.includes('MIGRATION FAILED') && columns().includes('website_url')
  && stillThere.length === 1,
  `re-migrating restored the column; the pre-existing row is still present (${stillThere.length})`);

check('T-MS-09', stored[0]?.[0] === 'https://example.ir/blog',
  `a URL written to the new column reads back: ${stored[0]?.[0]}`);

/* ── T-MS-10: idempotent ──────────────────────────────────────────────── */
const up4 = await probe(path.relative(ROOT, DB), 'migrate');
check('T-MS-10', !up4.out.includes('MIGRATION FAILED')
  && sql('select count(*) from seo_project_details where project_id = 9501')[0][0] === 1,
  `a second up on an already-migrated database is a no-op: ${up4.out.trim().split('\n')[0] || '(nothing pending)'}`);

/* ── T-MS-11: the shared database ─────────────────────────────────────── */
check('T-MS-11', sha(SHARED_DB) === sharedBefore,
  `database/database.sqlite sha256 unchanged: ${sha(SHARED_DB).slice(0, 16)}…`);

process.exit(c.summary() ? 0 : 1);

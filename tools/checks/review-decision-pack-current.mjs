#!/usr/bin/env node
/**
 * The generated client decision pack must match its generator.
 *
 * A generated document that is not checked goes stale silently, and a stale
 * decision pack is worse than none: the client could approve a default that the
 * source no longer proposes. This wrapper exists so the freshness check runs as
 * part of the regression suite instead of only when someone remembers it.
 */
import { spawnSync } from 'node:child_process';
import path from 'node:path';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');

const r = spawnSync(process.execPath,
  [path.join(ROOT, 'docs/tools/generate-review-decision-pack.mjs'), '--check'],
  { cwd: ROOT, encoding: 'utf8' });

const out = r.stdout || '';
process.stdout.write(out);
process.stderr.write(r.stderr || '');

// The regression runner parses "N/M passed". Report the two things actually
// asserted: the document is fresh, and it carries 9 x 11 fields.
const fresh = (r.status ?? 1) === 0;
const detail = fs.readFileSync(path.join(ROOT, 'docs/CRM_REVIEW_CLIENT_DECISIONS.md'), 'utf8');
const decisions = (detail.match(/^## \d+\. OD-/gm) || []).length;
const fields = (detail.match(/^\| (?:۱|۲|۳|۴|۵|۶|۷|۸|۹|۱۰|۱۱) \|/gm) || []).length;
const shapeOk = decisions === 9 && fields === 99;

console.log(`\n${(fresh ? 1 : 0) + (shapeOk ? 1 : 0)}/2 passed  (fresh=${fresh}, ${decisions} decisions, ${fields} fields)`);
process.exit(fresh && shapeOk ? 0 : 1);

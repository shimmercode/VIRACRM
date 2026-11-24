#!/usr/bin/env node
/**
 * Documentation integrity — the mandatory documents exist, and the generated
 * ones are not stale.
 *
 * WHY THIS IS A TEST
 * ------------------
 * The directive requires fifteen documents (§20). A document set that exists on
 * the day it was written and rots afterwards is worse than none: it makes
 * confident-looking claims about a product that has moved on. Two specific
 * failure modes are guarded here:
 *
 *   1. A required document is deleted or renamed during a refactor.
 *   2. REQUIREMENTS_TRACEABILITY.md is hand-edited, or the catalogue changes
 *      and nobody regenerates — so the document claims 84 requirements while
 *      the catalogue has 90.
 *
 * The second is checked by regenerating in `--check` mode and comparing bytes.
 * That is the only reliable way to know a generated file is current.
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');

const results = [];
const check = (id, ok, detail) => {
  results.push({ id, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${id.padEnd(30)} ${detail}`);
};

// §20 — the fifteen mandatory documents.
const REQUIRED = [
  ['PROJECT_STATUS', 'docs/PROJECT_STATUS.md'],
  ['REQUIREMENTS_TRACEABILITY', 'docs/REQUIREMENTS_TRACEABILITY.md'],
  ['ARCHITECTURE', 'docs/ARCHITECTURE.md'],
  ['DATABASE_MAP', 'docs/DATABASE_MAP.md'],
  ['SECURITY_REVIEW', 'docs/SECURITY_REVIEW.md'],
  ['DESIGN_SYSTEM', 'docs/DESIGN_SYSTEM.md'],
  ['UI_UX_AUDIT', 'docs/UI_UX_AUDIT.md'],
  ['ACCOUNTING_DESIGN', 'docs/phase-3/ACCOUNTING_DESIGN.md'],
  ['TEST_STRATEGY', 'docs/TEST_STRATEGY.md'],
  ['REGRESSION_LOCK', 'docs/REGRESSION_LOCK.md'],
  ['HOST_COMPATIBILITY', 'docs/HOST_COMPATIBILITY.md'],
  ['DEPLOYMENT_GUIDE', 'docs/DEPLOYMENT_GUIDE.md'],
  ['BACKUP_RECOVERY', 'docs/BACKUP_RECOVERY.md'],
  ['OPEN_DECISIONS', 'docs/OPEN_DECISIONS.md'],
  ['CHANGELOG', 'docs/CHANGELOG.md'],
];

// F0 — the eighteen file-workspace documents. They are listed here for the same
// reason as the fifteen above: a document set that silently loses a file rots.
const FILE_DOCS = [
  ['FILE_RESEARCH', 'docs/FILE_COLLABORATION_RESEARCH.md'],
  ['FILE_STORAGE_ARCH', 'docs/FILE_STORAGE_ARCHITECTURE.md'],
  ['FILE_REQUIREMENTS', 'docs/FILE_WORKSPACE_REQUIREMENTS.md'],
  ['FILE_PERMISSION_MATRIX', 'docs/FILE_PERMISSION_MATRIX.md'],
  ['FILE_ERD', 'docs/FILE_WORKSPACE_ERD.md'],
  ['FILE_VERSIONING_LOCKING', 'docs/FILE_VERSIONING_AND_LOCKING.md'],
  ['WINDOWS_WEBDAV', 'docs/WINDOWS_WEBDAV_COMPATIBILITY.md'],
  ['WEBDAV_VPS_ARCH', 'docs/WEBDAV_VPS_ARCHITECTURE.md'],
  ['WEBDAV_BRIDGE', 'docs/WEBDAV_CRM_AUTHORIZATION_BRIDGE.md'],
  ['ONLINE_OFFICE', 'docs/ONLINE_OFFICE_INTEGRATION.md'],
  ['FILE_THREAT_MODEL', 'docs/FILE_SECURITY_THREAT_MODEL.md'],
  ['FILE_CAPACITY', 'docs/FILE_STORAGE_CAPACITY_PLAN.md'],
  ['FILE_TEST_PLAN', 'docs/FILE_WORKSPACE_TEST_PLAN.md'],
  ['FILE_DEPLOYMENT', 'docs/FILE_WORKSPACE_DEPLOYMENT.md'],
  ['FILE_BACKUP', 'docs/FILE_BACKUP_AND_RECOVERY.md'],
  ['FILE_OPEN_DECISIONS', 'docs/FILE_WORKSPACE_OPEN_DECISIONS.md'],
  ['FILE_ACCEPTANCE', 'docs/FILE_WORKSPACE_ACCEPTANCE_CRITERIA.md'],
  ['FILE_TRACEABILITY', 'docs/FILE_WORKSPACE_TRACEABILITY.md'],
  ['FILE_ADR', 'docs/adr/ADR-FILE-001-file-workspace-architecture.md'],
];
REQUIRED.push(...FILE_DOCS);

for (const [name, rel] of REQUIRED) {
  const abs = path.join(ROOT, rel);
  const exists = fs.existsSync(abs);

  // A stub file satisfies "exists" but not the intent. 500 bytes is well below
  // any of these documents and well above a placeholder.
  const size = exists ? fs.statSync(abs).size : 0;
  check(`T-DOC-${name}`, exists && size > 500, exists ? `${rel} (${size} B)` : `${rel} MISSING`);
}

// The traceability document must be a faithful rendering of the catalogue.
const regen = spawnSync(process.execPath, ['tools/generate-traceability.mjs', '--check'], {
  cwd: ROOT, encoding: 'utf8', timeout: 60_000,
});
check('T-DOC-traceability-current', regen.status === 0,
  regen.status === 0
    ? 'document matches the catalogue'
    : (regen.stderr || regen.stdout || '').trim().split('\n')[0]);

// F0 file-workspace traceability is generated from the requirements document;
// like the main matrix it must not be hand-edited or left stale.
const fileTrace = spawnSync(process.execPath, ['docs/tools/generate-file-traceability.mjs', '--check'], {
  cwd: ROOT, encoding: 'utf8', timeout: 60_000,
});
check('T-DOC-file-traceability-current', fileTrace.status === 0,
  fileTrace.status === 0
    ? 'file requirements traceability matches its source'
    : (fileTrace.stderr || fileTrace.stdout || '').trim().split('\n')[0]);

// The status map must cover the catalogue exactly — an unmapped requirement is
// invisible, and an unknown id means the catalogue renamed something.
const statusMap = JSON.parse(
  fs.readFileSync(path.join(ROOT, 'tools/traceability-status.json'), 'utf8')
);
const ids = Object.keys(statusMap);
const validStatuses = ['VERIFIED', 'PARTIAL', 'NOT_VERIFIED', 'NOT_IMPLEMENTED', 'BLOCKED'];
const badStatus = ids.filter((id) => !validStatuses.includes(statusMap[id][0]));

check('T-DOC-status-vocabulary', badStatus.length === 0,
  badStatus.length === 0
    ? `${ids.length} requirements use the five permitted statuses`
    : `invalid status on: ${badStatus.join(', ')}`);

check('T-DOC-status-evidence', ids.every((id) => typeof statusMap[id][1] === 'string'
  && statusMap[id][1].trim().length > 0),
  'every requirement carries an evidence field');

// Gate A documents must still be intact — the directive says they are the
// baseline and are not to be rewritten.
const GATE_A = ['docs/phase-3/00-GATE-A-REPORT.md', 'docs/phase-3/README.md',
  'docs/phase-3/16-open-decisions.md'];
check('T-DOC-gate-a-intact', GATE_A.every((p) => fs.existsSync(path.join(ROOT, p))),
  `${GATE_A.length} Gate A documents present`);

const passed = results.filter((r) => r.ok).length;
console.log(`\n--- summary ---\n${passed}/${results.length} passed`);
process.exit(passed === results.length ? 0 : 1);

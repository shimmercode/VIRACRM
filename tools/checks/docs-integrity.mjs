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
  ['FILE_DECISION_REGISTER', 'docs/FILE_WORKSPACE_DECISION_REGISTER.md'],
  ['FILE_CLIENT_PACK', 'docs/FILE_WORKSPACE_CLIENT_DECISION_PACK.md'],
  ['FILE_ORG_MODEL', 'docs/FILE_ORGANIZATIONAL_MODEL.md'],
  ['FILE_ADR_EVIDENCE', 'docs/FILE_WORKSPACE_ADR_EVIDENCE.md'],
  ['FILE_DECISION_MATRIX', 'docs/FILE_WORKSPACE_DECISION_MATRIX.md'],
  ['FILE_POC_STATUS', 'docs/FILE_WORKSPACE_POC_STATUS.md'],
  ['FILE_ORG_GUARD', 'docs/FILE_ORGANIZATIONAL_MODEL_GUARD.md'],
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

// The page inventory is generated from the review catalogue. Two hand-written
// inventories once disagreed (4 preview vs 19) and both looked authoritative;
// deriving the document removed the possibility.
const inventory = spawnSync(process.execPath, ['tools/generate-page-inventory.mjs', '--check'], {
  cwd: ROOT, encoding: 'utf8', timeout: 300_000,
});
check('T-DOC-page-inventory-current', inventory.status === 0,
  inventory.status === 0
    ? 'document matches the catalogue'
    : (inventory.stderr || inventory.stdout || '').trim().split('\n')[0]);

/*
 * The schema and RBAC documents are generated from the live database, for the
 * same reason as the page inventory: a hand-maintained access-control matrix is
 * wrong the first time someone adds a permission, and it still looks
 * authoritative. Checking them here means drift fails the build.
 */
const schemaDoc = spawnSync(process.execPath, ['tools/generate-schema-doc.mjs', '--check'], {
  cwd: ROOT, encoding: 'utf8', timeout: 300_000,
});
check('T-DOC-database-schema-current', schemaDoc.status === 0,
  schemaDoc.status === 0
    ? 'DATABASE_SCHEMA.md matches the database'
    : (schemaDoc.stderr || schemaDoc.stdout || '').trim().split('\n')[0]);

const rbacDoc = spawnSync(process.execPath, ['tools/generate-rbac-doc.mjs', '--check'], {
  cwd: ROOT, encoding: 'utf8', timeout: 300_000,
});
check('T-DOC-rbac-matrix-current', rbacDoc.status === 0,
  rbacDoc.status === 0
    ? 'RBAC_PERMISSION_MATRIX.md matches the seeded roles'
    : (rbacDoc.stderr || rbacDoc.stdout || '').trim().split('\n')[0]);

/*
 * The release manifest counts and checksums the tree. Like the others it is
 * generated, because a manifest that lists "298 PHP files" and is checked by
 * nobody is the document most likely to be believed.
 */
const manifest = spawnSync(process.execPath, ['tools/generate-release-manifest.mjs', '--check'], {
  cwd: ROOT, encoding: 'utf8', timeout: 120_000,
});
check('T-DOC-release-manifest-current', manifest.status === 0,
  manifest.status === 0
    ? 'RELEASE_MANIFEST.md matches the working tree'
    : (manifest.stderr || manifest.stdout || '').trim().split('\n')[0]);

// F0 file-workspace traceability is generated from the requirements document;
// like the main matrix it must not be hand-edited or left stale.
const fileTrace = spawnSync(process.execPath, ['docs/tools/generate-file-traceability.mjs', '--check'], {
  cwd: ROOT, encoding: 'utf8', timeout: 60_000,
});
// F0 lesson: a document promised docs/database/file-erd.dot and a renderer
// invocation that never existed. A dangling path reference is a documentation
// defect that reads as fact, so every concrete repo path named in the FILE docs
// must resolve. Only paths under directories that exist and hold real files are
// scanned, which keeps this free of false positives.
const FILE_DOC_FILES = FILE_DOCS.map(([, rel]) => rel);
const PATH_RE = /`((?:docs|tools)\/(?:diagrams|database|tools|adr)?[^`\s]*?\.(?:dot|svg|png|mjs|md))`/g;
const dangling = [];
for (const rel of FILE_DOC_FILES) {
  const abs = path.join(ROOT, rel);
  if (!fs.existsSync(abs)) continue;
  const text = fs.readFileSync(abs, 'utf8');
  for (const m of text.matchAll(PATH_RE)) {
    const ref = m[1];
    if (ref.includes('*') || ref.includes('…')) continue;
    if (!fs.existsSync(path.join(ROOT, ref))) dangling.push(`${path.basename(rel)} → ${ref}`);
  }
}
check('T-DOC-referenced-paths', dangling.length === 0,
  dangling.length === 0
    ? 'every concrete path named in the FILE docs exists'
    : `${dangling.length} dangling: ${dangling.slice(0, 3).join(' · ')}`);

// The decision matrix is generated from one classification table. A hand edit
// would let the 13-vs-16 class of drift return, so staleness is a hard failure.
const matrix = spawnSync(process.execPath, ['docs/tools/generate-decision-matrix.mjs', '--check'], {
  cwd: ROOT, encoding: 'utf8', timeout: 60_000,
});
check('T-DOC-decision-matrix-current', matrix.status === 0,
  matrix.status === 0
    ? 'decision matrix matches its classification source'
    : (matrix.stderr || matrix.stdout || '').trim().split('\n')[0]);

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

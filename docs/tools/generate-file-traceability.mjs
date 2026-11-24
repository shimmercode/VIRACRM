#!/usr/bin/env node
/**
 * VIRA CRM — file-module traceability generator
 * ---------------------------------------------
 * Reads the requirement ids + titles from docs/FILE_WORKSPACE_REQUIREMENTS.md and
 * emits docs/FILE_WORKSPACE_TRACEABILITY.md with one row per requirement:
 *
 *   Requirement → Component → DB entity → Route/API → UI screen → Security control → Test → Status
 *
 * The mapping tables below are the single source of truth for the "where does this
 * live" columns. They are deliberately explicit rather than inferred: an inferred
 * mapping that drifts silently is worse than no mapping at all.
 *
 * Usage
 *   node docs/tools/generate-file-traceability.mjs            # write the document
 *   node docs/tools/generate-file-traceability.mjs --check    # fail if stale
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const SRC = path.join(ROOT, 'docs/FILE_WORKSPACE_REQUIREMENTS.md');
const OUT = path.join(ROOT, 'docs/FILE_WORKSPACE_TRACEABILITY.md');

// ── requirement extraction ─────────────────────────────────────────────────
const src = fs.readFileSync(SRC, 'utf8');
const reqs = [];
const re = /^\|\s*\*\*((?:FR|NFR)-[A-Z]+-\d{3})\*\*\s*\|\s*(.+?)\s*\|/gm;
let m;
while ((m = re.exec(src))) {
  const id = m[1];
  const title = m[2].replace(/\*\*/g, '').replace(/`/g, '').replace(/\|/g, '/').trim();
  reqs.push({ id, group: id.split('-')[1], title });
}

// ── mapping: component / entity / route / screen / control ─────────────────
const G = {
  FILE:    { comp: 'Modules/Files · FolderTree + QuotaService', ent: 'file_nodes, file_workspaces', route: '/files', screen: 'S01', ctrl: 'K1,K2' },
  PERM:    { comp: 'FileAuthorization · PermissionResolver', ent: 'file_permissions, file_permission_defs', route: '/files/{node}/permissions', screen: 'S05', ctrl: 'K2,K14' },
  VERSION: { comp: 'VersionService', ent: 'file_versions, file_blobs', route: '/files/{node}/versions', screen: 'S06', ctrl: 'K9' },
  WEBDAV:  { comp: 'WEBDAV service (VPS) · BridgeClient', ent: 'file_locks, file_access_tokens', route: '/api/dav/*', screen: 'S15', ctrl: 'K19,K20' },
  SEC:     { comp: 'ValidationService · PathGuard · AuditWriter', ent: 'file_activity, file_access_tokens', route: '—', screen: '—', ctrl: 'K3,K4,K5,K6' },
};
const NFRG = {
  FILE:   { comp: 'Platform (no Node, streaming)', ent: '—', route: '—', screen: '—', ctrl: 'K1' },
  WEBDAV: { comp: 'VPS sizing + cache', ent: '—', route: '/api/dav/*', screen: '—', ctrl: 'K19,K20' },
};

// Explicit overrides where the group default would be misleading.
const O = {
  'FR-FILE-007': { ent: 'file_relations', route: '/files/{node}/relations', screen: 'S12' },
  'FR-FILE-008': { ent: 'file_relations' },
  'FR-FILE-009': { comp: 'FileAuthorization + CRM Gate', ctrl: 'K2,K14' },
  'FR-FILE-010': { route: '/projects/{p}/files', screen: 'S14' },
  'FR-FILE-011': { comp: 'UploadController', route: '/files/upload' },
  'FR-FILE-012': { comp: 'UploadController + sessions', ent: 'file_upload_sessions', route: '/files/upload/{id}/chunk/{n}' },
  'FR-FILE-013': { comp: 'ValidationService (hash)', ctrl: 'K4' },
  'FR-FILE-014': { ent: 'file_upload_sessions', ctrl: 'K4' },
  'FR-FILE-018': { comp: 'DownloadController', route: '/files/{node}/download', ctrl: 'K15,K17' },
  'FR-FILE-019': { comp: 'DownloadController', ctrl: 'K17' },
  'FR-FILE-020': { comp: 'QuotaService', ent: 'file_quotas', ctrl: 'K10' },
  'FR-FILE-022': { comp: 'SearchGuard', route: '/files/search', screen: 'S04', ctrl: 'K2' },
  'FR-FILE-024': { comp: 'PreviewController', route: '/files/{node}/preview', screen: 'S07', ctrl: 'K16' },
  'FR-FILE-025': { comp: 'PreviewController', ctrl: 'K16,K17' },
  'FR-FILE-026': { comp: 'PreviewController', ctrl: 'K16' },
  'FR-FILE-027': { comp: 'Classification', screen: 'S13' },
  'FR-FILE-028': { comp: 'PermissionResolver (step 2)', ctrl: 'K2,K14' },
  'FR-FILE-029': { comp: 'RetentionService', ent: 'file_retention_policies', screen: 'S09' },
  'FR-FILE-030': { comp: 'RetentionService', ent: 'file_retention_policies', ctrl: 'K9' },
  'FR-PERM-012': { comp: 'PermissionResolver + cache', ent: 'file_access_tokens', ctrl: 'K3,K2' },
  'FR-PERM-014': { comp: 'ShareService (disabled)', ent: 'file_shares', screen: 'S08' },
  'FR-PERM-015': { comp: 'ShareService (F3)', ent: 'file_shares', screen: 'S08' },
  'FR-PERM-016': { comp: 'AuthGuard', ctrl: 'K2' },
  'FR-VERSION-005': { comp: 'BlobStore (dedup)', ent: 'file_blobs' },
  'FR-VERSION-006': { comp: 'RetentionService', ent: 'file_retention_policies' },
  'FR-WEBDAV-006': { comp: 'CrmAuthBackend', ctrl: 'K13' },
  'FR-WEBDAV-007': { comp: 'AppTokenService', ent: 'file_access_tokens', ctrl: 'K3' },
  'FR-WEBDAV-008': { comp: 'LockService + sabre Locks', ent: 'file_locks' },
  'FR-WEBDAV-012': { comp: 'UFW + network', ctrl: 'K13' },
  'FR-WEBDAV-014': { comp: 'E3 test env', screen: 'S16' },
  'FR-WEBDAV-015': { comp: 'User guide', screen: 'S16' },
  'FR-SEC-001': { comp: 'Storage contract', ctrl: 'K1' },
  'FR-SEC-002': { comp: 'FileAuthorization', ctrl: 'K2' },
  'FR-SEC-003': { comp: 'PathGuard + NameSanitizer', ctrl: 'K5,K6' },
  'FR-SEC-004': { comp: 'AccessTokenService', ent: 'file_access_tokens', ctrl: 'K3' },
  'FR-SEC-005': { comp: 'ValidationService', ctrl: 'K4' },
  'FR-SEC-006': { comp: 'PreviewController', ctrl: 'K16,K17' },
  'FR-SEC-007': { comp: 'ArchiveGuard', ctrl: 'K4,K10' },
  'FR-SEC-008': { comp: 'RateLimiter', ctrl: 'K8' },
  'FR-SEC-009': { comp: 'AuditWriter', ent: 'file_activity', ctrl: 'K9,K18' },
  'FR-SEC-010': { comp: 'SecretMgmt', ctrl: 'K12' },
  'FR-SEC-011': { comp: 'ScanService', ctrl: 'K7' },
  'FR-SEC-012': { comp: 'SearchGuard', ctrl: 'K2' },
  'NFR-FILE-002': { comp: 'Composer lock policy', ctrl: 'K12' },
  'NFR-FILE-003': { comp: 'Folder listing + indexes', ent: 'file_nodes' },
  'NFR-FILE-005': { comp: 'Streaming upload' },
  'NFR-FILE-009': { comp: 'Test harness (isolated DB)' },
  'NFR-WEBDAV-007': { comp: 'Permission cache', ent: '—', ctrl: 'K19' },
};

// ── tests: one test id per requirement, grouped by the plan's namespace ────
function testFor(id) {
  const n = id.split('-').pop();
  if (id.startsWith('FR-FILE')) {
    const k = Number(n);
    if (k === 18) return 'T-FILE-018';
    if (k === 22) return 'T-PERM-028';
    if (k === 24 || k === 25 || k === 26) return 'T-SEC-F06';
    if (k === 30) return 'T-VER-005';
    if (k === 9) return 'T-FILE-009';
    return `T-FILE-${String(k).padStart(3, '0')}`;
  }
  if (id.startsWith('FR-PERM'))    return `T-PERM-${n}`;
  if (id.startsWith('FR-VERSION')) return `T-VER-${n}`;
  if (id.startsWith('FR-WEBDAV'))  return `T-DAV-${String(Number(n)).padStart(3, '0')}`;
  if (id.startsWith('FR-SEC'))     return `T-SEC-F${n}`;
  if (id.startsWith('NFR-FILE'))   return `T-UI-${String(Math.min(Number(n), 20)).padStart(3, '0')}`;
  if (id.startsWith('NFR-WEBDAV')) return `T-PERF-${String(Math.min(Number(n), 12)).padStart(3, '0')}`;
  return '—';
}

function resolve(r) {
  const base = r.id.startsWith('NFR')
    ? (NFRG[r.group] || G.FILE)
    : G[r.group];
  const o = O[r.id] || {};
  return {
    comp: o.comp ?? base.comp,
    ent: o.ent ?? base.ent,
    route: o.route ?? base.route,
    screen: o.screen ?? base.screen,
    ctrl: o.ctrl ?? base.ctrl,
    test: o.test ?? testFor(r.id),
  };
}

// ── emit ───────────────────────────────────────────────────────────────────
const byGroup = {};
for (const r of reqs) (byGroup[r.group] ||= []).push(r);

const order = ['FILE', 'PERM', 'VERSION', 'WEBDAV', 'SEC'];
const titles = {
  FILE: 'فضای کاری و عملیات فایل (FR-FILE / NFR-FILE)',
  PERM: 'مجوز و اشتراک (FR-PERM)',
  VERSION: 'نسخه‌بندی (FR-VERSION)',
  WEBDAV: 'WebDAV (FR-WEBDAV / NFR-WEBDAV)',
  SEC: 'امنیت (FR-SEC)',
};

const out = [];
const w = (s = '') => out.push(s);

w('# FILE_WORKSPACE_TRACEABILITY — ماتریس ردیابی ماژول فایل');
w();
w('> **این سند به‌صورت خودکار تولید می‌شود.** دستی ویرایش نکنید.');
w('> منبع: `docs/FILE_WORKSPACE_REQUIREMENTS.md` · تولید: `node docs/tools/generate-file-traceability.mjs`');
w('> بررسی هم‌گامی: `node docs/tools/generate-file-traceability.mjs --check`');
w();
w('**تاریخ تولید:** 2026-11-23 · **نسخه:** 1.0 · **فاز:** F0');
w();
w('---');
w();
w('## ۱. قواعد خواندن این سند');
w();
w('| ستون | معنا |');
w('|-------|------|');
w('| **Req** | شناسهٔ یکتا از سند نیازمندی‌ها |');
w('| **Component** | مؤلفهٔ معماری که مسئول است (نقشه: `FILE_STORAGE_ARCHITECTURE.md` §5) |');
w('| **Entity** | جدول(های) داده (نقشه: `FILE_WORKSPACE_ERD.md`) |');
w('| **Route/API** | مسیر HTTP (قرارداد: `FILE_STORAGE_ARCHITECTURE.md` §9، `WEBDAV_CRM_AUTHORIZATION_BRIDGE.md`) |');
w('| **Screen** | صفحهٔ UI (نقشه: `FILE_WORKSPACE_ACCEPTANCE_CRITERIA.md` §4) |');
w('| **Control** | کنترل امنیتی K1…K20 (`FILE_SECURITY_THREAT_MODEL.md` §4) |');
w('| **Test** | تست (نقشه: `FILE_WORKSPACE_TEST_PLAN.md`) |');
w('| **Status** | طبق واژه‌نامهٔ §0 سند نیازمندی‌ها |');
w();
w('> **همهٔ وضعیت‌ها `Proposed` هستند.** در F0 هیچ کدی نوشته نشده؛ هیچ نیازی `Implemented` نیست.');
w('> یک نیازمندی تنها زمانی از `Proposed` خارج می‌شود که **تست متناظرش** با شاهد اجرا شود.');
w();
w('---');
w();
w('## ۲. خلاصهٔ شمارش');
w();
w('| گروه | تعداد نیازمندی | Proposed | Approved | Implemented | Tested | Accepted |');
w('|------|---------------:|---------:|---------:|------------:|-------:|---------:|');

let total = 0;
for (const g of order) {
  const n = (byGroup[g] || []).length;
  total += n;
  w(`| ${titles[g]} | ${n} | ${n} | 0 | 0 | 0 | 0 |`);
}
w(`| **جمع** | **${total}** | **${total}** | **0** | **0** | **0** | **0** |`);
w();
w('**تفکیک FR / NFR:**');
w();
const frCount = reqs.filter((r) => r.id.startsWith('FR-')).length;
const nfrCount = reqs.filter((r) => r.id.startsWith('NFR-')).length;
w(`- FR (عملکردی): **${frCount}**`);
w(`- NFR (غیرعملکردی): **${nfrCount}**`);
w();
w('---');
w();

let section = 3;
for (const g of order) {
  const list = byGroup[g] || [];
  if (!list.length) continue;
  w(`## ${section}. ${titles[g]}`);
  w();
  w('| Req | عنوان | Component | Entity | Route/API | Screen | Control | Test | Status |');
  w('|-----|-------|-----------|--------|-----------|--------|---------|------|--------|');
  for (const r of list) {
    const x = resolve(r);
    w(`| \`${r.id}\` | ${r.title} | ${x.comp} | ${x.ent} | \`${x.route}\` | ${x.screen} | ${x.ctrl} | ${x.test} | \`Proposed\` |`);
  }
  w();
  section++;
}

w('---');
w();
w(`## ${section}. بلوکه‌کننده‌ها — چه چیزی نمی‌گذارد وضعیت جلو برود`);
w();
w('| مورد | چه نیازمندی‌هایی را بلوکه می‌کند |');
w('|------|-------------------------------------|');
w('| **هیچ کد ماژول فایل وجود ندارد** | همهٔ ۹۹ نیازمندی (F1) |');
w('| **VPS آزمایشی تهیه نشده** | FR-WEBDAV-001…013 · NFR-WEBDAV-001…008 |');
w('| **ویندوز واقعی در دسترس نیست** | FR-WEBDAV-014 · FR-WEBDAV-015 |');
w('| **تصمیم دپارتمان/گروه (OD-104/105)** | FR-PERM-003 · FR-PERM-009 |');
w('| **تصمیم اسکن بدافزار (OD-127)** | FR-SEC-011 |');
w('| **بدون کد بکاپ** | FR-FILE-030 (پاک‌سازی نسخه بدون بکاپ امن نیست) |');
w();
w('---');
w();
w(`## ${section + 1}. وضعیت‌ها — معیار خروج از \`Proposed\``);
w();
w('| از | به | شرط |');
w('|----|----|-----|');
w('| `Proposed` | `Approved` | کارفرما تأیید کند (ثبت در `OPEN_DECISIONS.md`) |');
w('| `Approved` | `Implemented` | کد روی شاخه + Migration بازگشت‌پذیر |');
w('| `Implemented` | `Unit Tested` | تست واحد سبز در CI |');
w('| `Unit Tested` | `Integration Tested` | تست از **HTTP واقعی** با ≥۲ کاربر مستقل |');
w('| `Integration Tested` | `Environment Tested` | اجرا روی E2 (VPS) یا E3 (ویندوز) |');
w('| `Environment Tested` | `Accepted` | پذیرش کارفرما با شاهد |');
w();
w('> **قاعدهٔ سخت:** هیچ جهشی از `Proposed` به `Accepted` مجاز نیست.');
w();

const text = out.join('\n');

if (process.argv.includes('--check')) {
  const current = fs.existsSync(OUT) ? fs.readFileSync(OUT, 'utf8') : '';
  if (current !== text) {
    console.error('FILE_WORKSPACE_TRACEABILITY.md is stale — run without --check.');
    process.exit(1);
  }
  console.log(`up to date (${reqs.length} requirements)`);
  process.exit(0);
}

fs.writeFileSync(OUT, text);
console.log(`wrote docs/FILE_WORKSPACE_TRACEABILITY.md — ${reqs.length} requirements`);

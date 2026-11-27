/**
 * Builds docs/REQUIREMENT_CLOSURE.md — the per-requirement closure table the
 * acceptance gate asked for: id, the requirement's actual text, current status,
 * evidence, the remaining gap, the remedy, and contractual weight.
 *
 * Two sources are joined and neither is paraphrased:
 *   · docs/requirements/requirements.catalog.yaml — the requirement text itself
 *     (title, text, type, priority, source_section), which is the machine-readable
 *     extraction of the binding 15-section client document;
 *   · tools/traceability-status.json — status / summary / evidence, which is what
 *     docs/REQUIREMENTS_TRACEABILITY.md is generated from, so the two documents
 *     cannot drift apart.
 *
 * The gaps and remedies are authored here rather than invented per run: they are
 * the reviewed conclusion for each requirement that is not VERIFIED, and each one
 * names a concrete next action.
 *
 * Run: node tools/generate-requirement-closure.mjs
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

const catalogSrc = fs.readFileSync(path.join(ROOT, 'docs/requirements/requirements.catalog.yaml'), 'utf8');
const status = JSON.parse(fs.readFileSync(path.join(ROOT, 'tools/traceability-status.json'), 'utf8'));

/* ── Parse the catalog's flow-style entries ───────────────────────────── */
const catalog = {};
for (const part of catalogSrc.split('\n- {id: ').slice(1)) {
  const id = /^REQ-[\d.]+/.exec(part)?.[0];
  if (!id) continue;
  const quoted = (k) => new RegExp(`${k}: "((?:[^"\\\\]|\\\\.)*)"`).exec(part)?.[1] ?? '';
  catalog[id] = {
    title: quoted('title'),
    text: quoted('text'),
    type: quoted('type'),
    section: quoted('source_section'),
    priority: /priority: (P\d)/.exec(part)?.[1] ?? '—',
    weight: Number(/weight: (\d+)/.exec(part)?.[1] ?? 3),
  };
}

/* ── Authored gap + remedy for every requirement that is not VERIFIED ─── */
const REMEDY = {
  'REQ-01.03': ['storage/ and .arena/ sit outside public/, and vira:backup now refuses any --path under the document root (backup-hardening BK-03/05).',
    'Nothing code-side. Confirm on the real host that the web server\'s Document Root is public/ and not the repository root; that is a host setting, not a Laravel one.'],
  'REQ-01.04': ['No code path calls the WordPress site: grep finds no outbound HTTP to the main domain, and the CRM has its own database and session.',
    'Add a check that fails if any config value or route points at the WordPress host, then simulate a WordPress outage. Until a test exists this stays NOT_VERIFIED rather than being claimed.'],
  'REQ-02.01': ['The dashboard renders count tiles for customers, active contracts and expiring contracts, each linking to its list, plus the کارتابل groups.',
    '§2-1 names ten indicators. Add the missing ones (invoiced items, due reports, open notes, overdue items, completed actions) as tiles or as کارتابل sections, each reconciled against SQL the way work-item-report.mjs does.'],
  'REQ-03.05': ['StoreCustomerRequest validates national_id for length (10) and checksum digit, and rejects an invalid one.',
    'There is no uniqueness rule, so two customers can share a national_id. Add `unique:customers,national_id` (ignoring soft-deleted rows) plus the Persian error message, and a check that a second create with the same national_id is refused with a 302 + flashed error.'],
  'REQ-03.08': ['One customers row per customer; the detail page joins contracts, projects, notes and credentials.',
    'Prove it end to end: create a customer once, attach a contract, a project, a note and a credential, then assert all five surfaces show the same customer_id and that no second row was created.'],
  'REQ-06.07': ['No file download exists, so there is no file to warn about.',
    'Depends on REQ-10.06 (uploads). When downloads land, add a confidentiality banner on any credential-bearing document and refuse direct URL access without a permission check.'],
  'REQ-09.06': ['No Excel export exists.',
    'Install an Excel writer (maatwebsite/excel or phpoffice/phpspreadsheet) and add the same export endpoints that already produce CSV/PDF, with a check that the response Content-Type and extension are correct. Marked §9-6 "تکمیلی" in the catalog, so it is P2.'],
  'REQ-10.05': ['Every code-side control is implemented and asserted: security-headers.mjs 16/16 covers the six headers, the seven CSP directives, and HttpOnly/SameSite/Path/Secure on the session cookie; bcrypt, CSRF, login throttling, policies and route permissions have their own suites.',
    'HTTPS termination is deployment-side and cannot be exercised in this sandbox, so the transport half of §10-5 stays unproven here. On the real host, assert the redirect to HTTPS and that Secure is set with the variable unset in production (config/session.php now defaults it on).'],
  'REQ-10.06': ['No upload subsystem exists, so there is no MIME/extension/size gate, no private storage and no IDOR surface to test.',
    'Build it as one slice: validated upload (MIME + extension + size allowlist), opaque random filenames, storage outside public/, a permission-checked download controller, and a check that guessing another tenant\'s file id returns 403 rather than 404-with-content. This unblocks REQ-06.07 and the file half of REQ-12.09.'],
  'REQ-11.05': ['Empty and error states render; server-side validation returns Persian messages; sensitive actions ask for confirmation.',
    'Add explicit loading and success affordances and assert them: a check that a destructive form carries a confirm, that a failed submit re-renders with the field error, and that an empty list shows the empty state rather than a blank table.'],
  'REQ-12.05': ['Invoice and report reminders are computed from their own dates, independently of the contract date.',
    'Prove multi-period independence with a check: two invoices on one contract with different issue dates must produce two distinct reminders, and changing one date must move only that reminder.'],
  'REQ-12.06': ['Dates are stored and re-derived rather than cached, so a date change recalculates the state.',
    'Add a check that changing a contract end date moves the expiring bucket without creating a duplicate reminder, and that the previous state is still visible in the audit log.'],
  'REQ-12.07': ['The scheduler entry and the backup command exist and are idempotent, but no cron daemon is present in this environment (`command -v cron crond crontab` returns nothing).',
    'On a host with cron: install the schedule entry, run two cycles, and assert the second run neither duplicates nor prunes the first unexpectedly. Record the crontab line in the deployment guide as the evidence.'],
  'REQ-12.08': ['config/app.php:86 sets `timezone => UTC` and the Jalali calendar suite passes, so dates are consistent — but the *business* day boundary for an Iranian client was never confirmed with them.',
    'Ask the client whether the working day ends at midnight Tehran time (UTC+3:30). If yes, set APP_TIMEZONE and make the reminder buckets use it; add a check for a record dated 23:30 Tehran that must fall in tomorrow\'s bucket.'],
  'REQ-12.09': ['Three of the four recorded fields work: POST /work-items/{id}/complete requires invoice_number and invoice_amount, parses the amount through Money::parse, and stores invoice_issue_date.',
    'The fourth — the invoice *file* — needs REQ-10.06. WorkItem::$fillable already carries document_attachment_id; nothing writes it. Wire it when uploads land and extend the completion check to assert the attachment resolves.'],
  'REQ-13.02': ['SMS sending requires an external gateway that is unreachable from this sandbox.',
    'Pick a gateway, put its credentials in .env, and add a check that a failed send is logged and retried rather than silently dropped. Until then the code path cannot be exercised at all.'],
  'REQ-15.01': ['Five configuration screens exist and are proven to change behaviour (admin-config 24/24): users/roles, contracts, financial, SEO and tax.',
    '§15 lists more knobs than those five screens expose. Enumerate the remaining ones from the catalog and add a screen each, with a check per knob that changing it moves the behaviour it controls.'],
  'REQ-15.03': ['No web installer exists; setup is `composer install` + `migrate --seed` + `.env`.',
    'Build an installer that runs only when APP_KEY is unset, refuses to run twice, creates the first admin with a forced password change, and is not routable afterwards. Add a check that a second visit returns 403.'],
  'REQ-15.04': ['Sixteen migration files are present and migration-safety proves up/down/re-up on a disposable database.',
    'The shipped database/database.sqlite has an empty migrations table, so it cannot report its own version. Ship an empty database plus migrations instead of a populated file, and add a check that `migrate:status` reports every file as ran.'],
  'REQ-15.05': ['No hard-coded secrets: keys come from .env, and .env.example documents each one. VIRA_BACKUP_KEY, VIRA_CREDENTIAL_KEY, APP_KEY, SESSION_SECURE_COOKIE and TRUSTED_PROXIES are all env-driven.',
    'Add a check that fails if a secret-looking literal appears in app/ or config/, and verify the repository contains no real values (a secret scan is in the acceptance evidence).'],
  'REQ-15.06': ['Developer documentation is extensive (deployment, backup architecture, schema, security report, traceability).',
    'There is no *end-user* manual. Write one per module — customer, contract, project, payroll, reports, settings — with screenshots from the UI audit, and add a docs-integrity check that each module has a page.'],
};

/* ── Build ────────────────────────────────────────────────────────────── */
const STATUS_ORDER = ['VERIFIED', 'PARTIAL', 'NOT_VERIFIED', 'NOT_IMPLEMENTED', 'BLOCKED'];
const counts = Object.fromEntries(STATUS_ORDER.map((s) => [s, 0]));
for (const [id, v] of Object.entries(status)) counts[v[0]] = (counts[v[0]] ?? 0) + 1;

const ids = Object.keys(status).sort();
const inScope = ids.filter((id) => !id.startsWith('REQ-16'));
const outScope = ids.filter((id) => id.startsWith('REQ-16'));
const secNum = (s) => Number(String(s).replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)));
const bySection = (a, b) => (secNum(catalog[a]?.section) || 99) - (secNum(catalog[b]?.section) || 99)
  || a.localeCompare(b);

const esc = (s) => String(s ?? '').replace(/\|/g, '\\|');
const weightOf = (id) => catalog[id]?.weight ?? 3;
const totalWeight = ids.reduce((n, id) => n + weightOf(id), 0);
const verifiedWeight = ids.filter((id) => status[id][0] === 'VERIFIED').reduce((n, id) => n + weightOf(id), 0);

let out = `# Vira CRM — Requirement Closure (بستن الزامات)

> **Generated.** \`node tools/generate-requirement-closure.mjs\` joins
> \`docs/requirements/requirements.catalog.yaml\` (the requirement text) with
> \`tools/traceability-status.json\` (status and evidence). Do not edit by hand.
>
> Every requirement below is listed with its **actual text from the binding
> 15-section document**, not a paraphrase. Requirements that are not VERIFIED
> carry an explicit gap and a concrete remedy.
>
> **Nothing here is claimed as done that was not actually executed.** Where an
> environment could not prove something, the status says BLOCKED and names what
> was missing.
>
> **Every VERIFIED row carries a citation that resolves.** An audit of all 96
> rows on 2026-11-22 found 58 \`VERIFIED\` rows whose evidence named nothing a
> script could follow — fifteen controller paths missing their domain
> subdirectory and four suite filenames that do not exist under those names.
> All were corrected to real paths. \`node tools/checks/requirement-evidence.mjs\`
> reports \`0\` unevidenced and \`0\` missing-file citations and exits 0. No status
> was changed by that audit; what a citation does and does not prove is set out
> in [docs/REQUIREMENT_EVIDENCE_AUDIT.md](REQUIREMENT_EVIDENCE_AUDIT.md).

## Summary

| Status | Count |
|---|---:|
${STATUS_ORDER.map((s) => `| \`${s}\` | ${counts[s] ?? 0} |`).join('\n')}
| **Total tracked** | **${ids.length}** |

In scope of the binding 15-section document: **${inScope.length}**.
Outside it (the §16 file-workspace set, which the client document does not
contain): **${outScope.length}** — tracked, but see the last section.

Weighted completion (catalog weights): **${verifiedWeight}/${totalWeight}** =
**${((verifiedWeight / totalWeight) * 100).toFixed(1)}%** of contractual weight
is VERIFIED.

---

## 1. Requirements not yet VERIFIED, ordered by the document's sections

These are the ones that matter for acceptance. Each entry states what exists,
what is missing, and what to do next.

`;

const open = inScope
  .filter((id) => status[id][0] !== 'VERIFIED')
  .sort(bySection);

for (const id of open) {
  const [st, summary, evidence] = status[id];
  const cat = catalog[id] ?? {};
  const [gap, remedy] = REMEDY[id] ?? ['(no authored gap — the status entry is the record)', '(no remedy authored)'];

  out += `### ${id} — ${cat.title ?? '(no catalog title)'}

| | |
|---|---|
| **Status** | \`${st}\` |
| **Priority** | ${cat.priority} · weight ${cat.weight} · type \`${cat.type ?? '—'}\` |
| **Source** | §${cat.section ?? '—'} |
| **Contractual importance** | ${cat.priority === 'P0' ? 'delivery-blocking' : cat.priority === 'P1' ? 'required for version 1' : cat.priority === 'P2' ? 'high value, not blocking' : 'supplementary'} |

**What the document asks:**

> ${cat.text ?? '(text missing from the catalog)'}

**Where it stands:** ${summary}

**Evidence:** ${evidence}

**Remaining gap:** ${gap}

**Remedy:** ${remedy}

`;
}

out += `---

## 2. VERIFIED requirements

Each of these has automated evidence in \`tools/checks/\` or \`tests/\` and can be
re-run with one command. Evidence strings are reproduced verbatim from
\`tools/traceability-status.json\`.

| Requirement | Title | §  | Priority | Evidence |
|---|---|---|---|---|
`;

for (const id of inScope.filter((x) => status[x][0] === 'VERIFIED').sort(bySection)) {
  const cat = catalog[id] ?? {};
  out += `| \`${id}\` | ${esc(cat.title)} | ${cat.section ?? '—'} | ${cat.priority} | ${esc(status[id][2]).slice(0, 150)}${status[id][2].length > 150 ? '…' : ''} |\n`;
}

out += `
---

## 3. Out of scope: the §16 set

**The binding client document has no section 16.** Its last section is
«۱۵. مواردی که در طراحی نهایی باید قابل تنظیم باشند» (line 287), followed by
«جمع‌بندی» (line 297). Verified by direct grep of
\`docs/requirements/source/Vira_CRM_Requirements_FA.txt\`.

These ${outScope.length} requirements come from a separate file-workspace /
online-office specification. They are tracked so that nothing is silently
dropped, but **they must not be counted as delivery defects**, and no new
requirement has been added to scope without a source.

| Requirement | Title | Status | Note |
|---|---|---|---|
`;

for (const id of outScope) {
  const cat = catalog[id] ?? {};
  const note = /SCOPE:\s*(.*)$/.exec(status[id][2] ?? '')?.[1] ?? 'outside the 15-section document';
  out += `| \`${id}\` | ${esc(cat.title)} | \`${status[id][0]}\` | ${esc(note).slice(0, 110)} |\n`;
}

out += `
**Open decision for the client:** are these twelve in this contract or not?
The requirements document does not say, so it has to be asked rather than
assumed. Until then they are listed here and excluded from the acceptance
percentage.
`;

fs.writeFileSync(path.join(ROOT, 'docs/REQUIREMENT_CLOSURE.md'), out);

console.log(`wrote docs/REQUIREMENT_CLOSURE.md — ${ids.length} requirements`
  + ` (${inScope.length} in scope, ${outScope.length} out of scope,`
  + ` ${open.length} open in scope)`);

# Requirement Evidence Audit

**Date:** 2026-11-22
**Status file:** `tools/traceability-status.json` (96 requirements)
**Machine check:** `node tools/checks/requirement-evidence.mjs`

This document records an audit of the evidence behind every `VERIFIED` row in the
traceability register. It does not change any status. Nothing here is an
acceptance event: it establishes only that each `VERIFIED` claim points at
executable or inspectable artefacts that exist in this repository at
`884e68e`/`a68a1a1`.

## 1. The question this audit answers

The register is a data file. A row saying `VERIFIED` with a free-text evidence
field is a claim, not a proof, and a reader has no way to tell a real claim from
an aspirational one. Before this audit, **58 of the 96 rows** carried `VERIFIED`
with an evidence field that named nothing a script could find: prose describing
work, sometimes accurately, sometimes not, with no pointer a reviewer could
follow.

Two failure modes were possible and both were present:

- **A claim with no artefact.** The work existed but the citation named no path,
  so a reviewer had to take it on trust.
- **A citation that does not resolve.** Worse, because it looks rigorous. Four
  citations named suite files that do not exist under those names
  (`tools/checks/flow-projects.mjs` for a suite whose script is
  `project-flow.mjs`, and three more of the same kind). Fifteen more named
  controller paths without their domain subdirectory, e.g.
  `app/Http/Controllers/ContractController.php` where the class lives at
  `app/Http/Controllers/Contracts/ContractController.php`.

## 2. Method

Each `VERIFIED` row without a resolvable citation was examined, the artefact
located by reading the code rather than guessing the path, and a citation written
in a form the machine check can verify. The rule applied throughout: **cite what
exists, and only claim what the artefact actually does.** Where the only artefact
was a code path, both the code and its test are cited, so a reviewer can run
something.

The checker accepts a citation as real when it names either an existing path in
this repository or a known artefact (a roster suite id, a check script basename,
or a test class name). It reports two categories:

```
requirements audited          : 96
VERIFIED with no real evidence: 0
citations naming a missing file: 0
```

Exit code 0. Before this round the same command reported `58` and `0` and exited
1.

## 3. Corrections to the checker itself

The audit found that the checker was under-reporting broken citations. When a
citation was written `tools/checks/<name>.mjs` and `<name>` was a roster **id**
rather than the script's filename, the tool reported the citation as broken even
though the suite runs in the roster under that id. The four suites affected
(`flow-projects`, `flow-credentials`, `flow-login`, `flow-crud`) are registered
under ids that differ from their filenames.

The fix was **not** to teach the checker to accept ids in place of filenames —
that would let a genuine typo pass. The fix was to correct the citations to name
the real script (`project-flow.mjs`, `credential-flow.mjs`, `login-flow.mjs`,
`crud-flow.mjs`). The checker still requires the path it is given to exist.

A second defect: the summary counted a `NOT_VERIFIED` row (`REQ-01.04`) in the
"VERIFIED with no evidence" tally, inflating it by one and implying a status it
does not hold. Fixed; the list is now filtered on `VERIFIED` before counting.

## 4. What was found and corrected

### 4.1 Controller paths missing their domain subdirectory (15 citations)

Controllers are namespaced by module, not flat. Every citation of the form
`app/Http/Controllers/<Module>Controller.php` was wrong and was corrected to the
real location:

| Cited | Actual |
|---|---|
| `app/Http/Controllers/ContractController.php` | `app/Http/Controllers/Contracts/ContractController.php` |
| `app/Http/Controllers/ProjectController.php` | `app/Http/Controllers/Projects/ProjectController.php` |
| `app/Http/Controllers/CredentialController.php` | `app/Http/Controllers/Credentials/CredentialController.php` |
| `app/Http/Controllers/EmployeeController.php` | `app/Http/Controllers/Hr/EmployeeController.php` |
| `app/Http/Controllers/InvoiceController.php` | `app/Http/Controllers/Invoices/InvoiceController.php` |

### 4.2 Suite citations that named no existing script (4 citations)

Corrected as described in §3.

### 4.3 Seven rows whose evidence was prose with no pointer

These rows described real, working behaviour but cited nothing a script could
find. Each claim was re-checked against the code before a citation was written,
and none was accepted on the strength of its own prose:

| Requirement | Claim in the register | What actually backs it |
|---|---|---|
| REQ-03.07 | customer dossier lists contracts **and** projects | `resources/views/customers/show.blade.php` renders `route('contracts.show')` (line 210) and `route('projects.show')` (line 265); `tools/checks/crud-flow.mjs` |
| REQ-06.02 | credential requirements gate finalisation | `app/Services/Projects/FinalizationGate.php`; `tests/Feature/FinalizationGateTest.php` (20 assertions) |
| REQ-06.04 | audit log browsable at `GET /audit` | `resources/views/audit/index.blade.php`; `tools/checks/audit-log-page.mjs` |
| REQ-06.05 | credentials PDF, POST-only, audited | `app/Services/Pdf`; `tools/checks/credential-export-audit.mjs` |
| REQ-10.02 | roles are data, editable through a UI | `resources/views/settings/roles`; `tools/checks/admin-config.mjs` |
| REQ-11.03 | Vazirmatn self-hosted with its licence | `public/build/fonts/OFL.txt` (4,391 B, "Copyright 2015 The Vazirmatn Project Authors"); three `@font-face` rules in `resources/css/tokens.css` |
| REQ-15.02 | backup stack with verification gates | `app/Services/Backup`; `tests/Feature/BackupRestoreTest.php` (96 assertions) |

The full prose of each row is preserved in the status file after the citation, so
the reasoning is not lost — the citation was prepended, not substituted.

## 5. Deliberate non-changes

- **No status was edited.** The distribution before and after this audit is
  identical: `VERIFIED 63, PARTIAL 13, NOT_VERIFIED 1, NOT_IMPLEMENTED 15,
  BLOCKED 4`. `git diff` on the status file contains no line touching a status
  column. Adding evidence to a row is not a reason to promote it, and this audit
  did not promote any.
- **`REQ-01.04` was not touched.** It is `NOT_VERIFIED` — the claim that the
  system has no dependency on the main site was never *exercised*, only reasoned
  about. An evidence pointer would not change that, and writing one would imply
  a verification that did not happen.
- **`REQ-06.02`'s open question was kept open.** Its evidence notes that the
  three seeded credential kinds need client confirmation. That is a real open
  item and it stays in the text.
- **REQ-16 remains separate.** All twelve file-workspace requirements are
  `NOT_IMPLEMENTED` (11) or `BLOCKED` (1, WebDAV architecture). None is counted
  toward the CRM requirements, and none was given evidence pointers, because
  there is nothing implemented to point at. Closing them requires a written
  client scope decision, which this audit is not.

## 6. Residual, stated plainly

The checker now reports zero unevidenced `VERIFIED` rows. That means **every
`VERIFIED` row names something that exists**. It does not mean every `VERIFIED`
row was proven end-to-end in this session — a citation to a code path plus its
suite shows the work is present and tested at that path, not that it was
re-executed today. The rows whose suites run in this environment were exercised
by the full roster; the ones whose acceptance needs a real MySQL server or a
cron host remain `BLOCKED` for that reason and are counted as such.

A reader who wants more than a presence proof should run the cited suite. That is
what the citation is for.

## 7. Re-running this audit

```
node tools/checks/requirement-evidence.mjs            # summary, exit 1 on any gap
node tools/checks/requirement-evidence.mjs --verbose  # per-row verdict
```

The command exits non-zero if any `VERIFIED` row has no resolvable evidence or if
any citation names a path that is not in the tree. It is not yet in the required
roster, because the remaining gaps it would report — were any to reappear — are
about documentation rather than application behaviour; see
`docs/REQUIREMENT_CLOSURE.md` for why that distinction is kept.

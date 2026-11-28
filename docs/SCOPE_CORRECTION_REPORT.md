# Scope Correction Report — VIRA CRM vs Digital Menu SaaS

**Report date:** 2026-11-22
**Issued by:** the agent executing this session
**Purpose:** confirm that Directive A (`P0 CORE STABILIZATION`, WP-01…WP-08) was
misdirected, and record whether its correct target is reachable.

---

## 1. Directive A belongs to Digital Menu SaaS — confirmed

I agree with the correction. The evidence for it is what I reported before
implementing anything:

| Premise in Directive A | VIRA CRM |
|---|---|
| Baseline `446c3c2de08991af18984433230d8eda60bb280a` | Not an object in this repository |
| `FINAL-REPORT-RECONCILIATION.md` | Not present |
| Ordering engine | `app/**/*order*` — absent |
| Cart / checkout | absent |
| Coupon model | absent |
| Theme system | absent |
| Modifiers / inventory / variants | absent |
| Tracking-token host routing | absent |

VIRA CRM is a Persian RTL CRM for a digital-marketing agency: contracts,
projects, work items, customers, payroll, reports. None of the eight work
packages has a subject to operate on.

**Directive A belongs to Digital Menu SaaS. Confirmed.**

---

## 2. VIRA CRM was the wrong target — confirmed

No WP-01…WP-08 work was performed in VIRA CRM, no branch
`pre-pilot-p0-hardening` was created, and no baseline was "preserved" — there was
no such baseline to preserve. The only trace of Directive A in this repository is
`docs/P0-HARDENING-GIT-AUDIT.md`, which records the non-execution with reasons.
There is no Digital Menu test manifest, evidence directory, or implementation in
this tree.

**VIRA CRM was the wrong target. Confirmed.**

---

## 3. The correct Digital Menu repository and baseline have NOT been verified

**This is the part of the correction that fails.**

The correction names:

- Repository: `digital-menu-saas`
- Canonical root: `/home/user`
- Branch: `main`
- Original baseline: `d14ee0a2c0abd2cddf97db41e89cbe1805b8fe25`
- Frozen HEAD: `446c3c2de08991af18984433230d8eda60bb280a`

Checks (1) through (5) of the required pre-flight were run. Results:

| # | Check | Result |
|---|---|---|
| 1 | Repository root | **FAILS** — `/home/user` is not a Git repository at all (`fatal: not a git repository`). It contains `.bashrc`, `.profile`, `.npm`, `.cache`, and one directory: `VIRACRM`. |
| 2 | Branch | Cannot verify — no repository to ask. |
| 3 | Full Git SHAs | **Both absent.** `git cat-file -t` → `could not get object info` for both. GitHub commit search across public GitHub returns `total_count: 0` for each. |
| 4 | Baseline and required project files | **FAILS** — no `digital-menu-saas` directory anywhere on the filesystem (`find /` finds nothing matching `digital-menu`/`digital_menu`), no archive that could be the source. |
| 5 | Ordering engine, theme system, coupon model, tracking-token host routing | Cannot inspect — no code. The only repository on this machine is VIRA CRM, where all of these are absent as shown in §1. |

Additional checks:

- GitHub account `shimmercode`: `gh repo list` shows no repository matching
  `menu`, `restaurant`, or `digital`.
- `gh api repos/shimmercode/digital-menu-saas` → **404**.
- The only Git repositories on this machine are `/home/user/VIRACRM/.git`,
  `/tmp/composer-src/.git`, and `/tmp/skillprobe/ui-ux-skill/.git` — the latter
  two are scratch clones from the Gate 1 investigation, not project checkouts.

**Conclusion: the Digital Menu SaaS repository is not present in this
environment. Checks 1, 3, and 4 fail outright; checks 2 and 5 cannot be performed.**

---

## 4. WP-01 through WP-08 are NOT ready to execute — and this report will not claim they are

The correction asks me to confirm that "WP-01 through WP-08 are ready to be
executed in the correct repository."

**They are not, and I cannot confirm it.** The repository does not exist here.
Claiming readiness would be exactly the kind of unverified assertion this whole
engagement has been built to avoid — the same failure mode as reporting a gate
PASS without a command that produced it.

The honest status of each pre-flight item:

```
1. Verify repository root .................... FAILED (not a git repo)
2. Verify current branch ..................... NOT PERFORMED (no repo)
3. Verify full Git SHA ....................... FAILED (neither object exists)
4. Verify baseline + required files .......... FAILED (no checkout on disk)
5. Confirm domain artefacts belong here ...... NOT PERFORMED (no code)
6. Read Directive A + acceptance criteria .... DONE (in the original message)
7. Do not reuse VIRA CRM reports ............. OBSERVED (nothing reused)
```

Per the correction's own rule — *"If any baseline or required artifact differs,
stop and report the discrepancy before implementation"* — I am stopping and
reporting.

### What is actually needed

The Digital Menu repository needs to be made available in this environment
before any work package can start:

- **Option A — clone it.** If it exists under a name or owner I did not probe,
  give me the URL and I will clone it and re-run checks 1–5.
- **Option B — provide it.** Check it out at `/home/user/digital-menu-saas`, or
  point the canonical root at wherever it actually lives.
- **Option C — upload it.** Attach a source archive to this conversation.

The environment cannot fetch it on its own: `gh` is authenticated as
`shimmercode` and that account has no such repository, and the two named commits
are not publicly indexed on GitHub.

---

## 5. No VIRA CRM file or commit was modified by this scope correction

Verified after all of the above:

```
HEAD      84f827de9e4f5d108b5e7bb015d4fdd78c06172a
branch    arena/3b43de84-viracrm
dirty     0
synced    yes (origin/arena/3b43de84-viracrm)
commits since 84f827d   0
```

The only change this round is this document. Nothing was cherry-picked,
nothing was copied between projects, and no evidence directory was mixed.

VIRA CRM's standing decision is unchanged and independent:

> **NOT PRODUCTION READY — EXTERNAL INFRASTRUCTURE BLOCKERS**
> Gates 1, 2, 3 BLOCKED; Gate 4 PARTIAL; Gates 5 and 6 PASS.
> Definitive regression: 74/74 suites, 1906 assertions, `RUNNER_EXIT=0` at
> `1f29da0`, shared database `69d8b91f7ef3ba23…` unchanged.

---

## 6. Corrections to the instruction that I have applied

- **"Latest reported regression SHA: `1f29da0`"** — that is where the gate
  results were *proven*, but it is not current HEAD. Three documentation commits
  followed it (`db3aff3`, `84f827d`): the Gate 6 result written into the report,
  the report's own HEAD reference corrected, and the manifest regenerated.
  Current HEAD is `84f827d`. The three intervening commits touch documentation
  and the manifest only — no application code, no tests.

- **Canonical root `/home/user`** — I have not treated this as a repository
  root because it is not one. If Digital Menu is meant to live at
  `/home/user/digital-menu-saas`, that path does not currently exist.

Everything else in the correction is accepted without reservation.

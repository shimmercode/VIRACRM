# Final Requirement Closure

**Gate 5 of the final infrastructure gate.**
**Date:** 2026-11-22 · **Status:** **PASS** — with one substantive correction to
what was being tracked.

---

## 1. Summary

| Metric | Value |
|---|---|
| Requirements tracked | **97** (was 96) |
| In scope of the 15-section contract | 85 |
| Out of scope (REQ-16 file workspace) | 12 |
| `VERIFIED` | 63 |
| `PARTIAL` | 13 |
| `NOT_VERIFIED` | 1 |
| `NOT_IMPLEMENTED` | 15 |
| `BLOCKED` | **5** (was 4) |
| `VERIFIED` rows with unresolvable evidence | **0** |
| Citations naming a missing file | **0** |

Machine check, exit 0:

```
$ node tools/checks/requirement-evidence.mjs
requirements audited          : 97
VERIFIED with no real evidence: 0
citations naming a missing file: 0
```

---

## 2. The correction that matters

Gate 3 asked for REQ-12.07's **exact wording** rather than an assumption. The
assumption was wrong.

**REQ-12.07 is the reminder scheduler**, not backup:

```yaml
title: "زمان‌بندی واقعی، Idempotent و قابل مشاهده"
text:  "Cron/Scheduler مستقل از بازدید کاربر؛ … ثبت وضعیت Job و خطاهای Scheduler."
source_section: "۵-۲/۱۲"
```

§5-2 of the contract is «یادآوری خودکار فاکتور و گزارش سئو» — invoice and
SEO-report reminders. No backup anywhere in it.

**The contractual backup clause was untracked entirely.**
`docs/requirements/source/Vira_CRM_Requirements_FA.txt:221`, §10:

> تهیه نسخه پشتیبان منظم از پایگاه داده و فایل‌های بارگذاری‌شده.

No catalog entry. No status row. No evidence. The backup work was measured
against a requirement that does not exist, while the requirement that does exist
was invisible.

### 2.1 Actions taken

| Action | Detail |
|---|---|
| `REQ-10.09` added to catalog | verbatim text, §10 line 221, weight 4, P0, module `Backup` |
| `REQ-10.09` added to tracker | **BLOCKED** — «منظم» needs a scheduler, a cron daemon and a MySQL server |
| `REQ-15.02` mis-attribution corrected | the correction is recorded in the evidence text, not silently rewritten |

Why `BLOCKED` and not `VERIFIED`: the code exists and is tested (96 + 38
assertions, 23/23 + 16/16 command-level). But the contractual word is **«منظم»** —
*regular*. Nothing in the application schedules `vira:backup`
(`routes/console.php` registers only `inspire`), and no cron daemon exists here.
Marking it VERIFIED would be marking a capability that has never once executed on
a schedule.

---

## 3. Contractual baseline preserved

The binding 15-section document is in the repository and is the source of every
requirement text:

```
docs/requirements/source/Vira_CRM_Requirements_FA.txt
304 lines · 15 sections (۱..۱۵) · confirmed by direct read
```

Requirement texts in `docs/REQUIREMENT_CLOSURE.md` are quoted from this document,
not paraphrased from a brief. The newest entry follows the same rule — the
«منظم» sentence is copied verbatim, with its line number recorded.

---

## 4. REQ-16 kept separate

All twelve file-workspace requirements remain outside the CRM scope:

| Status | Count |
|---|---|
| `NOT_IMPLEMENTED` | 11 |
| `BLOCKED` | 1 (`REQ-16.06`, WebDAV architecture) |

No status was changed, no item was folded into the CRM count, and no evidence
pointer was invented for work that does not exist. Closing these needs a written
client scope decision that this round does not have and does not claim.

---

## 5. Evidence integrity

The audit of 2026-11-22 found 58 `VERIFIED` rows whose evidence named nothing a
script could resolve, including fifteen controller paths missing their domain
subdirectory and four suite filenames that do not exist under those names. All
were corrected to real paths. The method and every correction are in
`docs/REQUIREMENT_EVIDENCE_AUDIT.md`.

The checker was corrected in two places:

- it counted a `NOT_VERIFIED` row in the "VERIFIED with no evidence" tally;
- it needed fixing rather than loosening — teaching it to accept roster ids in
  place of filenames would have let genuine typos through, so the **citations**
  were corrected.

A citation proves a claim is **present and tested at that path**. It does not
prove the suite was re-executed today for that specific row. Rows whose acceptance
needs a MySQL server or a cron host remain `BLOCKED` for that reason.

---

## 6. REQ-12.07 remains open, on its own terms

Correcting the misattribution does not close it. REQ-12.07 needs:

- `Cron/Scheduler` execution independent of user visits;
- resistance to repeated execution (idempotent job handling);
- unique job identifiers and recorded job status/errors.

None of that can be exercised without a scheduler and a running daemon. It stays
**BLOCKED**, as does its sibling REQ-12.08 (calendar-day boundary, which also
carries an open employer decision about the day boundary and execution hour).

Two requirements, two gaps, genuinely distinct — and now, finally, two IDs.

---

## 7. What is still open, by requirement

The 22 open in-scope requirements fall into three groups:

| Group | Count | Nature |
|---|---:|---|
| Infrastructure-blocked | 5 | MySQL backup, scheduled backup, TLS/proxy, `composer.lock`, cron-dependent |
| Awaiting client decision | 8 | scope/behaviour questions recorded in the decision register |
| Genuinely not implemented | 9 | features deferred out of v1 scope by the contract's own priorities |

Full per-requirement detail with the exact gap and remedy:
`docs/REQUIREMENT_CLOSURE.md`.

---

## 8. Verdict

**PASS** for Gate 5, in the only sense that gate can pass: every status is
supported by evidence that resolves, the contractual baseline is intact, REQ-16
is separate, and a requirement that had been invisible for weeks is now tracked —
as BLOCKED, because it is.

Gate 5 is about **honesty of the register**, not about how many rows say
VERIFIED. On that measure the register is now accurate. It was not before: it
graded backup against a scheduler requirement and did not track the backup
requirement at all.

**Owner:** none outstanding for the register itself. The five `BLOCKED` rows are
owned by infrastructure and are listed in `docs/FINAL_INFRASTRUCTURE_GATE_REPORT.md`.

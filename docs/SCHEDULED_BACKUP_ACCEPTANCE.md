# Scheduled Backup Acceptance

**Gate 3 of the final infrastructure gate.**
**Date:** 2026-11-22 · **Status:** **BLOCKED** — and the requirement it was being
measured against was the wrong one.

---

## 1. The finding

The gate asked for REQ-12.07's exact contractual wording rather than an
assumption. The assumption was wrong, and it had been wrong for weeks.

**REQ-12.07 is the reminder scheduler.** Catalog:

```yaml
- {id: REQ-12.07, group: REQ-12, type: doc, priority: P0, weight: 5,
   title: "زمان‌بندی واقعی، Idempotent و قابل مشاهده",
   text: "Cron/Scheduler مستقل از بازدید کاربر؛ مقاوم در برابر اجرای چندباره؛
          شناسه یکتا و پردازش Idempotent؛ ثبت وضعیت Job و خطاهای Scheduler.",
   source_section: "۵-۲/۱۲", source_lines: "99-127,237-251"}
```

Contract source §5-2 is «یادآوری خودکار فاکتور و گزارش سئو» — automatic invoice
and SEO-report reminders. **Nothing in REQ-12.07 concerns backups.**

It has nevertheless been cited as *the* backup scheduling gap in this project,
including inside REQ-15.02's own evidence text:

> `scheduling is REQ-12.07 (BLOCKED, no cron on host) — code is not the gap`

That sentence points at the wrong requirement while sounding precise.

## 2. The requirement that was never tracked

The contractual backup clause is at `docs/requirements/source/Vira_CRM_Requirements_FA.txt:221`,
inside **§10 — کاربران، نقش‌ها و امنیت** (users, roles and security):

> **تهیه نسخه پشتیبان منظم از پایگاه داده و فایل‌های بارگذاری‌شده.**
> *Produce a regular backup of the database and the uploaded files.*

At the start of this round it had:

- no entry in `docs/requirements/requirements.catalog.yaml`,
- no row in `tools/traceability-status.json`,
- no evidence of any kind.

So the backup work was being graded against a requirement that does not exist,
while the requirement that does exist — and that the work actually implements —
was invisible. A gate cannot be closed against an untracked requirement, and it
cannot be closed against a different one either. This is how a gap stays open
while looking tracked.

### 2.1 What was changed

- **`REQ-10.09` added** to the catalog: verbatim text, §10, line 221, weight 4,
  P0, module `Backup`, tests `BackupRestoreTest`, `BDM-01`, `BK-11`.
- **`REQ-10.09` added** to the tracker, status **BLOCKED** — not VERIFIED, for
  the reasons in §3.
- **REQ-15.02's mis-attribution corrected**, with the correction recorded in the
  evidence text rather than silently replaced.

Totals after the change: **97** requirements, 85 in scope, `VERIFIED 63`,
`PARTIAL 13`, `NOT_VERIFIED 1`, `NOT_IMPLEMENTED 15`, `BLOCKED 5`.

The traceability generator rejected the new status row until a catalog entry
existed — correctly. A status without requirement text is a dangling record, and
the tool refusing to write it is the guard working.

---

## 3. Why the gate stays BLOCKED

The contractual word is **«منظم»** — regular, recurring. That is a property of
execution, not of code. Two independent facts prevent showing it:

### 3.1 The application schedules nothing

`routes/console.php` contains exactly one entry:

```php
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
```

No `vira:backup`, no `Schedule::command(...)`, nothing in `bootstrap/app.php` or
`app/Console/Kernel.php`. The command exists and works; nothing calls it on a
schedule.

### 3.2 There is no cron daemon

No init system on this host, so even a correct crontab line would never fire.
Writing one into a document and calling the requirement verified is the specific
failure this project has been guarding against: **a crontab nothing executes is
not a schedule.**

`docs/CUTOVER_RUNBOOK.md` §7.4 already states both facts, and
`tools/checks/cutover-runbook.mjs` asserts that it does (`RB-NO-CRON-CLAIM`), so
the runbook cannot quietly start claiming a backup schedule exists.

---

## 4. What is verified about backups

Not nothing — the parts that are code.

| Suite | Result |
|---|---|
| `backup-hardening.mjs` | **16/16** |
| `backup-driver-matrix.mjs` | **23/23** (3 rows BLOCKED) |
| `BackupRestoreTest` | 96 assertions |
| `BackupDriverMatrixTest` | 38 assertions |

Proven: encryption at rest (AES-256-GCM), key gates, document-root and traversal
refusal (including symlink), wrong-key and corrupted-container refusal with the
target's data unchanged, retention after success only, and a full SQLite round
trip of 57 tables / 484 rows.

**Not proven:** that any of it runs on a schedule, and that the MySQL success
path works (see `docs/MYSQL_PRODUCTION_BACKUP_ACCEPTANCE.md`).

---

## 5. Closing the gate

On the deployment host, with cron installed and running.

```bash
# 1. Install the entry (do not run the command by hand and call it scheduled)
echo '15 2 * * * cd /var/www/viracrm && php artisan vira:backup >> /var/log/vira-backup.log 2>&1' | crontab -

# 2. Confirm cron is alive
systemctl status cron          # or: service cron status

# 3. WAIT for the daemon. Two cycles minimum.
#    Running `php artisan vira:backup` manually proves the command, not the schedule.

# 4. Evidence that the daemon did it:
ls -lt /var/backups/viracrm | head            # two new containers, datestamped
tail -20 /var/log/vira-backup.log             # two successful runs
grep -c "backup_ok" ...                       # two audit rows, timestamps matching cron
```

### 5.1 Retention

```bash
# VIRA_BACKUP_KEEP_DAILY=7 WEEKLY=4 MONTHLY=6
# Pruning runs only AFTER a successful backup, so a failed run never deletes the
# last good container. Verify by forcing a failure:
VIRA_BACKUP_KEY= php artisan vira:backup      # must refuse
ls /var/backups/viracrm | wc -l               # unchanged
```

### 5.2 Failure alerts and recovery

```bash
# A backup that fails silently is not a backup. Confirm the operator learns:
VIRA_BACKUP_KEY= php artisan vira:backup; echo "exit=$?"    # expect 1
grep backup_failed <audit table>

# Recovery: take a container, restore into a throwaway database, compare counts.
# See docs/MYSQL_PRODUCTION_BACKUP_ACCEPTANCE.md §5.
```

### 5.3 Destination outside the document root

```bash
# Must be REFUSED:
php artisan vira:backup --path=public/backups     # expect exit 1, "document root"
php artisan vira:backup --path=public/a/b/c       # nested, same refusal
ls public/backups                                  # must not exist
```

---

## 6. Pass criteria

| Criterion | Requirement |
|---|---|
| Cron daemon running | `systemctl status cron` active |
| Entry installed | `crontab -l` shows it |
| Fired by the daemon | container timestamps match the schedule, not a manual run |
| Exit codes | 0 per run, visible in the log |
| Backup integrity | each container passes its own verification gate |
| Retention | pruning obeys `VIRA_BACKUP_KEEP_*`; never on failure |
| Failure alerting | a failed run is visible and audited |
| Destination | outside `public/`, proven by the refusal tests |

---

## 7. Note on REQ-12.07 itself

Correcting the misattribution does not close REQ-12.07 — it is a real
requirement about the reminder scheduler, and it remains **BLOCKED** for its own
reason: it needs `Cron/Scheduler` execution that this host cannot provide, plus
the open employer decision on the calendar-day boundary noted in REQ-12.08.

Two requirements, two gaps, both genuinely blocked. They are no longer the same
gap, and the difference now has a requirement ID.

---

## 8. Verdict

**BLOCKED — external infrastructure blocker.** The application fills no schedule
and no daemon exists to fill one. The requirement is now tracked as
`REQ-10.09`, so the gap has an ID and can be closed against the right text.

**Owner:** infrastructure owner (cron) plus the database owner (MySQL acceptance).
**Blocks production:** yes — «منظم» is unimplemented, not merely unverified.

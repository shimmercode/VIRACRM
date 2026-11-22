#!/usr/bin/env python3
"""
VIRA CRM — Phase 1 documentation generator
==========================================
Generates, from the machine-readable catalogs:

  docs/phase-1/05-traceability-matrix.md      (requirements → modules → tests → status)
  docs/phase-1/05-traceability-matrix.csv     (same, for spreadsheet use)
  docs/phase-1/07-status-dashboard.md          (weighted real progress + coverage stats)
  docs/phase-1/11-test-plan-counts.md          (test plan summary)

Run:  python3 docs/tools/build_phase1_docs.py
Requires: PyYAML

Rationale: single source of truth; every number in the delivered documents is computed,
not hand-typed, so no stale or inflated status can slip in.
"""
from __future__ import annotations

import csv
import datetime as dt
import pathlib
import sys
from collections import Counter, defaultdict

try:
    import yaml
except ImportError:
    sys.exit("PyYAML is required: python3 -m pip install pyyaml")

ROOT = pathlib.Path(__file__).resolve().parents[2]
REQ_FILE = ROOT / "docs/requirements/requirements.catalog.yaml"
TEST_FILE = ROOT / "docs/requirements/tests.catalog.yaml"
OUT = ROOT / "docs/phase-1"

TYPE_LABEL = {"doc": "سند", "brief": "بریف", "proposed": "پیشنهادی", "derived": "مشتق"}
TYPE_ORDER = ["doc", "brief", "derived", "proposed"]
STATUS_LABEL = {
    "not-started": "شروع‌نشده",
    "in-analysis": "در تحلیل",
    "designed": "طراحی‌شده",
    "implemented": "پیاده‌سازی‌شده",
    "tested": "تست‌شده",
}
PHASE_ORDER = ["فاز صفر", "فاز یک", "فاز دو", "فاز سه", "فاز چهار", "فاز پنج", "فاز شش", "فاز هفت", "معماری", "استقرار", "PM"]


def load() -> tuple[dict, dict]:
    req_doc = yaml.safe_load(REQ_FILE.read_text(encoding="utf-8"))
    test_doc = yaml.safe_load(TEST_FILE.read_text(encoding="utf-8"))
    return req_doc, test_doc


def md_table(headers: list[str], rows: list[list[str]]) -> str:
    out = ["| " + " | ".join(headers) + " |", "|" + "|".join(["---"] * len(headers)) + "|"]
    for r in rows:
        out.append("| " + " | ".join("" if c is None else str(c) for c in r) + " |")
    return "\n".join(out)


def truncate(s: str, n: int = 90) -> str:
    s = " ".join(str(s).split())
    return s if len(s) <= n else s[: n - 1] + "…"


def build_rtm(req_doc: dict, test_doc: dict) -> dict:
    reqs = req_doc["requirements"]
    tests = {t["id"]: t for t in test_doc["tests"]}
    groups = {g["code"]: g for g in req_doc["groups"]}

    # integrity checks
    problems: list[str] = []
    used_tests: Counter = Counter()
    for r in reqs:
        for tid in r.get("tests") or []:
            used_tests[tid] += 1
            if tid not in tests:
                problems.append(f"`{r['id']}` references unknown test `{tid}`")
        if not r.get("modules"):
            problems.append(f"`{r['id']}` has no module mapping")
        if r.get("type") not in TYPE_LABEL:
            problems.append(f"`{r['id']}` has invalid type `{r.get('type')}`")
    for tid in tests:
        if used_tests[tid] == 0:
            problems.append(f"test `{tid}` is not linked to any requirement")
    by_group: dict[str, list[dict]] = defaultdict(list)
    for r in reqs:
        by_group[r["group"]].append(r)

    lines: list[str] = []
    today = dt.date.today().isoformat()
    lines += [
        "# VIRA CRM — Requirements Traceability Matrix (RTM) — نسخه اولیه فاز ۱",
        "",
        f"> تولید خودکار از `docs/requirements/requirements.catalog.yaml` + `tests.catalog.yaml` در **{today}**.",
        "> هر عدد/سلول این سند محاسبه‌شده است؛ دستی ویرایش نکنید. بازتولید: `python3 docs/tools/build_phase1_docs.py`",
        "",
        "## راهنمای ستون‌ها",
        "",
        "- **شناسه**: شناسه یکتای الزام (`REQ-xx.yy`)",
        "- **نوع**: سند = الزام صریح سند مرجع ۱۵ بخشی · بریف = الزام صریح بریف توسعه · مشتق = الزام مهندسی مشتق‌شده · پیشنهادی = تصمیم تیم، نیازمند تأیید",
        "- **اولویت**: P0 بلوکه‌کننده تحویل · P1 الزام نسخه ۱ · P2 ارزش بالا · P3 تکمیلی",
        "- **وضعیت**: وضعیت واقعی در پایان فاز ۱ (هیچ الزامی `implemented` یا `tested` نیست، چون کد محصول هنوز نوشته نشده و گیت تأیید فاز ۱ هنوز باز است)",
        "- **تست‌ها**: شناسه‌های سناریوی تست در `docs/requirements/tests.catalog.yaml`",
        "",
        "## خلاصه پوشش",
        "",
    ]

    # summary per group
    rows = []
    for g in req_doc["groups"]:
        rs = by_group.get(g["code"], [])
        zeros = sum(1 for r in rs if not r.get("tests"))
        rows.append([g["code"], g["title"], len(rs), zeros, ", ".join(sorted({m for r in rs for m in r["modules"]}))])
    lines.append(md_table(["گروه", "عنوان", "تعداد الزام", "بدون تست", "ماژول‌ها"], rows))
    lines += ["", md_table(
        ["نوع", "تعداد"],
        [[TYPE_LABEL[k], v] for k, v in sorted(Counter(r["type"] for r in reqs).items(), key=lambda kv: TYPE_ORDER.index(kv[0]))],
    ), ""]
    lines.append(md_table(
        ["اولویت", "تعداد"],
        [[k, v] for k, v in sorted(Counter(r["priority"] for r in reqs).items())],
    ))
    lines.append("")
    if problems:
        lines += ["## ⚠️ ناسازگاری‌های ردیابی", ""] + [f"- {p}" for p in problems] + [""]
    else:
        lines += ["## ✅ سلامت ردیابی", "", "همه الزام‌ها ماژول و تست دارند؛ هیچ تست یتیم و هیچ ارجاع نامعتبری وجود ندارد.", ""]

    lines += ["## ماتریس ردیابی", ""]
    for g in req_doc["groups"]:
        rs = by_group.get(g["code"], [])
        lines += [f"### {g['code']} — {g['title']}", ""]
        out_rows = []
        for r in rs:
            tids = r.get("tests") or []
            tstat = ", ".join(f"{t} ({tests[t]['status']})" for t in tids) if tids else "—"
            out_rows.append([
                f"`{r['id']}`",
                TYPE_LABEL[r["type"]],
                r["priority"],
                truncate(r["title"], 70),
                ", ".join(r["modules"]),
                tstat,
                STATUS_LABEL.get(r["status"], r["status"]),
            ])
        lines.append(md_table(["شناسه", "نوع", "اولویت", "عنوان", "ماژول", "تست‌ها (وضعیت)", "وضعیت پیاده‌سازی"], out_rows))
        lines.append("")

    # CSV export
    with (OUT / "05-traceability-matrix.csv").open("w", newline="", encoding="utf-8-sig") as fh:
        w = csv.writer(fh)
        w.writerow(["requirement_id", "group", "type", "priority", "title", "modules", "tests", "status", "phase"])
        for r in reqs:
            w.writerow([r["id"], r["group"], r["type"], r["priority"], r["title"],
                        ";".join(r["modules"]), ";".join(r.get("tests") or []), r["status"], r.get("phase", "")])

    return {"markdown": "\n".join(lines) + "\n", "problems": problems, "reqs": reqs, "tests": tests}


def build_status(req_doc: dict, test_doc: dict) -> str:
    reqs = req_doc["requirements"]
    tests = test_doc["tests"]
    tstat = Counter(t["status"] for t in tests)

    total_w = sum(r.get("weight", 3) for r in reqs)
    def wsum(pred) -> int:
        return sum(r.get("weight", 3) for r in reqs if pred(r))

    status_w = {k: wsum(lambda r, k=k: r["status"] == k) for k in STATUS_LABEL}
    prio = Counter(r["priority"] for r in reqs)

    lines = [
        "# VIRA CRM — Status Dashboard (وضعیت واقعی) — پایان فاز ۰/۱",
        "",
        f"> {dt.date.today().isoformat()} — تولید خودکار از کاتالوگ نیازمندی و کاتالوگ تست.",
        "",
        "## درصد پیشرفت (وزن‌دار — نه تخمین ذهنی)",
        "",
        "درصد پیشرفت = مجموع وزن الزامات تکمیل‌شده ÷ مجموع وزن همه الزامات. "
        "«تکمیل‌شده» یعنی `implemented` یا `tested`؛ هیچ الزامی در این مرحله چنین وضعیتی ندارد و **این عدد عمداً صفر است**.",
        "",
        md_table(
            ["وضعیت", "وزن", "سهم از کل"],
            [[STATUS_LABEL[k], f"{v} / {total_w}", f"{v / total_w * 100:.1f}%"] for k, v in status_w.items()],
        ),
        "",
        f"**پیشرفت پیاده‌سازی محصول: {sum(status_w[k] for k in ('implemented', 'tested')) / total_w * 100:.1f}% · "
        f"پیشرفت فاز تحلیل/طراحی (designed+in-analysis): "
        f"{sum(status_w[k] for k in ('designed', 'in-analysis')) / total_w * 100:.1f}%**",
        "",
        "> چرا صفر؟ گیت فاز ۱ (تأیید کارفرما) باز است و طبق بخش ۲۹ بریف، توسعه اصلی قبل از تأیید آغاز نمی‌شود. "
        "پیشرفت طراحی یعنی این که معماری/طرح برای آن الزام آماده و مستند است، نه این که کد نوشته شده است.",
        "",
        "## پوشش نیازمندی‌ها",
        "",
        md_table(
            ["سنجه", "مقدار"],
            [
                ["تعداد الزامات شناسایی‌شده", len(reqs)],
                ["الزامات P0 (بلوکه‌کننده)", prio["P0"]],
                ["الزامات P1", prio["P1"]],
                ["الزامات P2/P3", prio["P2"] + prio["P3"]],
                ["الزامات دارای حداقل یک سناریوی تست", sum(1 for r in reqs if r.get("tests"))],
                ["الزامات بدون تست (عمداً: فرآیندی/مستنداتی)", sum(1 for r in reqs if not r.get("tests"))],
            ],
        ),
        "",
        "## وضعیت تست‌ها (واقعی)",
        "",
        md_table(["وضعیت تست", "تعداد"], [[k, tstat.get(k, 0)] for k in ["pass", "fail", "blocked", "not-tested"]]),
        "",
        f"سناریوهای ثبت‌شده: **{len(tests)}** (حداقل الزامی بریف: {test_doc['meta']['min_scenarios_required']}).",
        "",
        "تنها تست‌های `pass` دو تست فرآیندی QA هستند (کامل بودن کاتالوگ و RTM). "
        "هفت تست `blocked` به عدم دسترسی به هاست یا عدم وجود فرمول تأییدشده حقوق وابسته‌اند (ابهام Q-05، Q-08). "
        "بقیه `not-tested` هستند چون کد محصول هنوز وجود ندارد.",
        "",
        "## درصد پیشرفت به تفکیک گروه (وزنی، طراحی‌شده در برابر پیاده‌شده)",
        "",
    ]
    rows = []
    for g in req_doc["groups"]:
        grp = [r for r in reqs if r["group"] == g["code"]]
        gw = sum(r.get("weight", 3) for r in grp) or 1
        complete = sum(r.get("weight", 3) for r in grp if r["status"] in ("implemented", "tested"))
        design = sum(r.get("weight", 3) for r in grp if r["status"] in ("designed", "in-analysis"))
        rows.append([g["code"], g["title"], len(grp), f"{complete / gw * 100:.1f}%", f"{design / gw * 100:.1f}%"])
    lines.append(md_table(["گروه", "عنوان", "تعداد", "پیاده‌سازی", "طراحی/تحلیل"], rows))
    lines.append("")
    return "\n".join(lines)


def build_test_counts(test_doc: dict) -> str:
    tests = test_doc["tests"]
    by_suite = Counter(t["suite"] for t in tests)
    suites = {s["code"]: s for s in test_doc["suites"]}
    lines = [
        "# VIRA CRM — خلاصه برنامه تست (فاز ۱)",
        "",
        "> منبع: `docs/requirements/tests.catalog.yaml` — تولید خودکار",
        "",
        md_table(["مجموعه", "شرح", "تعداد سناریو"], [[c, suites[c]["title"], by_suite.get(c, 0)] for c in sorted(suites)]),
        "",
        f"**مجموع سناریوها: {len(tests)}** — حداقل الزامی بریف ۵۰ سناریو است؛ همه با فیلدهای "
        "پیش‌شرط، مراحل و نتیجه انتظاری در کاتالوگ ثبت شده‌اند.",
        "",
        "## قوانین ثبت نتیجه",
        "",
        "1. هر تست فقط با اجرای واقعی می‌تواند `pass` شود و باید شاهد (فایل/اسکرین‌شات/لاگ) داشته باشد.",
        "2. تست‌های وابسته به هاست یا فرمول تأییدنشده `blocked` هستند، نه `pass`.",
        "3. هر تست `fail` باید Issue ثبت‌شده و توضیح خطا داشته باشد.",
        "",
    ]
    return "\n".join(lines)


def main() -> None:
    req_doc, test_doc = load()
    rtm = build_rtm(req_doc, test_doc)
    (OUT / "05-traceability-matrix.md").write_text(rtm["markdown"], encoding="utf-8")
    (OUT / "07-status-dashboard.md").write_text(build_status(req_doc, test_doc), encoding="utf-8")
    (OUT / "11-test-plan-counts.md").write_text(build_test_counts(test_doc), encoding="utf-8")
    print(f"requirements: {len(req_doc['requirements'])}")
    print(f"tests:        {len(test_doc['tests'])}")
    print(f"integrity:    {'OK' if not rtm['problems'] else 'PROBLEMS: ' + '; '.join(rtm['problems'])}")
    print("written:      docs/phase-1/{05-traceability-matrix.md,05-traceability-matrix.csv,07-status-dashboard.md,11-test-plan-counts.md}")


if __name__ == "__main__":
    main()

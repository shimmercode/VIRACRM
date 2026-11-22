# docs/src — Source code used to generate Phase 1 deliverables

This folder contains the **PHP sources executed with a real PHP 8.3 runtime** (PHP-WASM) to produce
verified evidence for the Phase 1 deliverable, before the Laravel application skeleton exists.

| File | Purpose | Output |
|---|---|---|
| `jalali-golden-verify.php` | **Current.** Verifies the morilog/jalali v3.5.0 algorithm against the 41-case golden set (PASS on PHP 8.2 + 8.3) | console + JSON report |
| `d2-daymath-verify.php` | **Current.** Proves D-2 is two calendar days, not 48 hours, independent of any calendar algorithm | console + JSON report |
| `jalali-smoke-test.php` | ⚠️ **SUPERSEDED** — 2820 arithmetic calendar, wrong for 1404/1437. Kept only as a negative control | console + JSON report |
| `php-runtime-smoke-test.php` | Verifies PHP runtime capabilities needed by the architecture (openssl AES-256-GCM, mbstring, json, sqlite, date/timezone, bcmath decimals) | console + JSON report |

These are **prototype verification scripts**, not product code. Product code will live under
`app/Modules/...` after the employer approves the Phase 1 gate.

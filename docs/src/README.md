# docs/src — Source code used to generate Phase 1 deliverables

This folder contains the **PHP sources executed with a real PHP 8.3 runtime** (PHP-WASM) to produce
verified evidence for the Phase 1 deliverable, before the Laravel application skeleton exists.

| File | Purpose | Output |
|---|---|---|
| `jalali-smoke-test.php` | Verifies Jalali↔Gregorian conversion, calendar boundaries, and D-2 day math | console + JSON report |
| `php-runtime-smoke-test.php` | Verifies PHP runtime capabilities needed by the architecture (openssl AES-256-GCM, mbstring, json, sqlite, date/timezone, bcmath decimals) | console + JSON report |

These are **prototype verification scripts**, not product code. Product code will live under
`app/Modules/...` after the employer approves the Phase 1 gate.

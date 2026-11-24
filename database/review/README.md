# Review environment database

This directory holds the **private database copy used by the CRM Review
Environment**. It is not production data and it is never committed.

| File | Purpose |
|---|---|
| `crm-review.sqlite` | The review database. Created by `tools/review-env.mjs init`, seeded with `seed`, cleared with `reset`. |
| `.credentials` | Generated demo passwords for the current seed run. Git-ignored. |

## Why a separate copy

Two reasons, both non-negotiable:

1. **The review instance must not be able to write to a real installation.**
   A separate file means a seed, a reset, or a reviewer clicking "delete" can
   never reach customer records.
2. **Reset must be honest.** "Reset the demo data" is only truthful if the
   reset has an unambiguous boundary. Here that boundary is a file.

## Commands

```bash
node tools/review-env.mjs init     # create this copy from the baseline schema
node tools/review-env.mjs seed     # fill it with realistic fake data
node tools/review-env.mjs reset    # remove only the rows the seeder owns
node tools/review-env.mjs status   # row counts, no personal values
```

`tools/review-seed.php` refuses to run against any file outside this directory.

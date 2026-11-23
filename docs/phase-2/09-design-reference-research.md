# Design reference research — patterns, not appearance

**Date:** 2026-11-22 · **Method:** pages fetched and screenshots inspected directly; no
recommendation is based on remembered or assumed design.

Reference products are studied for **patterns only**. Nothing here proposes copying a visual
appearance, and no change to `#450073` or `#D2AB67` is proposed.

---

## What was actually inspected

| Source | How | What it gave |
|--------|-----|--------------|
| **attio.com** | page fetched | Live product surface in the page: sidebar (`Home / Tasks / Notes / Reports / Automations / Sequences / Records`), a record list with `Company · ICP Score · Owner` columns, quick-action bar (`⌘K`), meeting/activity timeline |
| **attio.com** (dashboard shot) | screenshot viewed | KPI cards with inline deltas, a funnel chart, a two-series trend line, a bar chart, a donut — one screen, one question each |
| **linear.app** | page fetched | Grouped issue list: collapsible group headers with counts (`Backlog 8`, `Todo 71`, `In Progress 3`, `Done 53`), inline row metadata (id, status icon, labels, date, assignee) |
| **linear.app** (list shot) | screenshot viewed | Confirmed the grouping pattern; rows carry at most 3 metadata chips before it becomes noise |
| **monday.com** | page fetched | Board/table as the product's spine; per-department templates; heavy use of colour-coded status columns |
| **saasui.design** `/crm-dashboard-ui-design` | page fetched | Catalogue: 115 CRM screens from 6 products (HubSpot, Attio, Close, folk, Zoho, Clay), organised by pattern |
| **saasui.design** `/pattern/table` | page fetched | 160 table screens from 81 apps — evidence of how common each table decision is |
| **Close CRM** (leads list) | screenshot viewed | Dense table, filter chips with `×`, column-header menus (`···`), **row actions revealed on hover at the far end of the row** |

Not usable as evidence, and therefore not cited: mobbin, dribbble and behance were not
fetched as pages — their content is behind app shells or infinite feeds that return no
inspectable structure. Two images surfaced by search were hosted on those domains and are
cited by their origin instead. This is a gap in coverage, not a judgement about them.

---

## Pattern 1 — Grouped lists with counted headers (Linear)

**What the pattern is.** Rather than one flat sorted list, rows are grouped by status, and
each group header states its own count: `In Progress 3`, `Todo 71`.

**Why it works.** It answers "what is the shape of my work" without a chart or a filter
visit. The count is in the header, so the user does not count rows.

**Fit for Vira CRM: good, later.** Vira's lists already sort and filter, and status is a
first-class column on both `/customers` and `/contracts`. Grouping would be a genuine
improvement — but it is a **behaviour change to a list that currently passes 178 layout
assertions**, and the user's standing instruction is no unnecessary change. It is also only
worth doing once there are enough rows to group (7 customers today).

**RTL note.** Group headers in RTL put the count on the left of the label; the pattern is
direction-neutral because the count rides with the label, not a fixed edge.

**Verdict:** document as a candidate. Do not implement this phase.

---

## Pattern 2 — Row actions revealed on hover (Close CRM) — and why it failed here

**What the pattern is.** The action icons (`⋯`, edit, etc.) sit at the far end of the row and
appear only on hover. It keeps dense tables visually calm.

**What I found in Vira CRM.** This is *exactly* the arrangement that produced the mobile
defect this phase fixed. Under RTL the actions column is the **leftmost**, at the far end of
the reading direction. It is fine with a mouse — hover reaches it. On touch there is no
hover, so the buttons are simply not visible until the user swipes the table sideways: at
390px, 41% of the table and the entire Actions column were off-screen.

Close CRM can get away with hover-reveal because it is a desktop-first tool with no phone
claim. Vira CRM's requirement (REQ-11.01) explicitly demands desktop, tablet **and** mobile.

**Pattern worth taking instead:** Close's **filter chips** — each active filter is a pill with
its own `×`, so the user can see and remove one condition without reopening the whole filter
bar. Vira's filter bar keeps state in the querystring and has no chip representation, so a
user cannot tell what is filtered without scrolling up to the selects.

**Verdict:** reject hover-reveal for touch (already done — card view labels every action).
Adopt filter chips as a candidate; do not implement this phase.

---

### Grouped list headers — proposal only, NOT implemented

Per the review, this is documented rather than built. It is recorded here so the pattern and
its intended home are not lost, and explicitly **not** implemented in this phase.

**The pattern (Linear).** Rows grouped by a status column, each group carrying its own count
in the header: `In Progress 3`, `Todo 71`, `Done 53`. Collapsible, and the header sticks while
its group scrolls.

**Where it would apply in Vira CRM, and where it would not:**

| Page | Suitable? | Why |
|------|-----------|-----|
| `/contracts` | **Yes** | Status is a first-class column (`draft`, `active`, `expiring`, `expired`, `cancelled`) and the contract lifecycle is the thing a user is scanning for. Grouping by status would answer "what needs attention" without a filter visit. |
| `/customers` | **Partly** | Status exists (`lead`, `prospect`, `active`, `inactive`, `archived`), but the list is small and sorted by recency. Grouping 7 records into 5 groups adds more chrome than insight. Not worth it until volume grows. |
| `/customers/{id}` sub-lists | No | These are already scoped to one customer; grouping would be noise. |
| `/` dashboard | No | Not a list. |

**Prerequisites before it could be built:**

1. The controller must return rows ordered by status first, then by the existing sort — a
   grouping UI over an arbitrary order would produce duplicated group headers.
2. Pagination and grouping interact badly: 20 rows per page split across groups shows partial
   groups, and the counts would describe the page, not the whole set. The count would have to
   come from a separate aggregate query.
3. It is a **behaviour change** to a list that currently passes 178 layout assertions plus the
   new 44 overflow and 187 matrix assertions.

**Recommendation:** defer until `/contracts` has enough rows for grouping to pay for itself,
and until pagination-versus-count semantics are decided. Not a phase-2 change.

## Pattern 3 — KPI cards with inline deltas (Attio)

**What the pattern is.** A small number of cards, each one metric with its own change
indicator, above the charts. One question per card.

**Fit for Vira CRM: already partly there.** The dashboard has KPI cards. Attio's discipline —
*one* number and *one* delta per card, never three — is the part worth enforcing, and Vira's
cards already follow it.

**What Vira should *not* take:** Attio's chart density. Its dashboard carries a funnel, a
trend line, a bar chart and a donut on one screen. Vira's requirement asks for "prominent but
non-intrusive urgent and overdue items" (REQ-11.05) — a reminder list, not an analytics
suite. Adding four charts would bury the overdue items that are the screen's actual job.

**RTL note.** Charts in RTL are a known trap: time axes conventionally still read
left-to-right, but Persian numeric formatting must be preserved. Vira currently renders
Jalali dates in tables, not charts, so this risk is latent rather than present.

**Verdict:** pattern already satisfied. No change.

---

## Pattern 4 — Column density and metadata budget (Linear + Close)

**What the pattern is.** Both products cap the metadata per row. Linear allows roughly three
chips (id, labels, date) before a row becomes unreadable; Close uses fixed-width columns with
truncation.

**What I measured in Vira CRM (from the 1024px work this phase).** The customer table's six
columns have natural widths of 198 / 79 / 133 / 142 / 74 / 94 px — total 687px. That is
already lean. At 1024px the table fits exactly once the fixed 720px floor is removed.

**Verdict:** no density change needed. The measured table is already at the budget these
products use. This is worth stating because it was the temptation to "fix" the 1024px
overflow by hiding a column — which would have removed information to solve a problem that
the layout caused.

---

## What should improve in the current Design System

Only items that survived the "is this actually a usability gain, in RTL" test:

| # | Improvement | Source pattern | Cost | Blocked on |
|---|-------------|----------------|------|------------|
| 1 | **Filter chips** — active filters as removable pills | Close CRM | Small, additive | Approval (behaviour change to filter bar) |
| 2 | **Grouped list headers with counts** | Linear | Medium | Needs row volume to be worth it |
| 3 | **Tooltip component** (see Design System doc, G1) | ubiquitous | Small | Approval |
| 4 | **Component contract page** (G6) | — | Documentation only | Approval |

Explicitly **rejected** after inspection:

| Rejected | Why |
|----------|-----|
| Hover-revealed row actions | Unusable on touch; caused the 41%-hidden defect this phase fixed |
| Attio's chart-heavy dashboard | Would bury the overdue-reminder list that REQ-11.05 makes the dashboard's job |
| monday.com's colour-coded status columns | Colour as the primary status signal fails contrast and colour-blindness; Vira's badges already carry an icon and a word |
| Dark mode (Linear) | Not requested; doubles the surface every contrast assertion covers |
| Sidebar information architecture from Attio/Linear | Attio's nav has 9 top-level items plus team sections; Vira's 22 routes over 4 roles do not need it, and restructuring navigation is out of scope |

---

## RTL findings

None of the three products is RTL, so each pattern had to be re-examined rather than copied:

1. **Hover-revealed actions are worse in RTL, not neutral.** The actions column moves to the
   opposite end from the one a Western design assumed, so any pattern that depends on the
   actions being "reachable on the right" breaks twice over: wrong side *and* no hover.
2. **Counted group headers are safe** — the count travels with the label.
3. **Filter chips are safe** — a pill is a single unit; only its row wrapping needs
   `flex-wrap`, which Vira already uses in `.v-filterbar`.
4. **Column-order conventions do not transfer.** Close CRM puts the record name first and
   actions last; Vira does the same, which in RTL means name-right, actions-left. That is
   correct for RTL and is what card view preserved.

---

## Summary

Three real products inspected with visible evidence, two more (saasui.design catalogue pages)
providing pattern frequency. The useful outcome is mostly **negative**: the inspection
confirmed that Vira's table density, KPI card discipline and RTL column order are already
correct, and that the two things worth borrowing (filter chips, grouped headers) are
behavioural changes that must wait for approval.

The one positive finding is that the mobile defect had a named cause in this industry's
default table pattern — hover-revealed actions at the end of the row — which is why fixing it
was correct rather than stylistic.

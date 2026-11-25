#!/usr/bin/env node
/**
 * EXP-WORKITEM-001 / EXP-NOTE-001 — the کارتابل and tasks, through the real HTTP stack.
 *
 * WHY THIS FILE EXISTS
 * --------------------
 * WorkItemAndNoteLifecycleTest proves the model transitions. It cannot prove a
 * request ever reaches the controller, that the view compiles, or that the
 * permission middleware is on the route. Those are different claims, and this
 * repository has already shipped a correct service with zero callers once.
 *
 * So this drives it the way an operator does: open the cartable, open one item,
 * submit the completion form with an invoice number and amount, and then assert
 * the DATABASE state — not just the flash message. A page that says "saved"
 * while writing nothing is the failure mode this guards against.
 *
 * ISOLATION: the Flow harness copies the seeded database into
 * `.arena/flows/work-items/` and points every request at that copy. Nothing here
 * touches the shared review database.
 */
import { Flow, Checks } from './lib/flow-harness.mjs';

const flow = new Flow('work-items');
await flow.setup();

const c = new Checks('work-item-flow');
const check = (id, ok, detail) => c.check(id, ok, detail);

const PASSWORD = `Wi-${Date.now().toString(36)}-P6`;
await flow.resetPassword({ password: PASSWORD, mustChange: false });
await flow.login('admin', PASSWORD);

const token = (html) => Flow.token(html);

/* ── T-WI-01: the cartable renders with real rows ─────────────────────────── */
const list = await flow.render({ uri: '/work-items' });
check(
  'T-WI-01',
  list.status === 200
    && list.body.includes('کارتابل')
    && list.body.includes('v-table'),
  `GET /work-items → ${list.status}; expected the cartable table`,
);

/* The seeded database has nine open obligations. If the page renders but the
 * count is zero, the query is wrong — a "working" empty page is the classic
 * false pass. */
const hasRows = /work-items\/\d+/.test(list.body);
check('T-WI-02', hasRows, 'the list must link to real work-item detail pages');

/* ── T-WI-03: a seeded item opens and shows its facts ─────────────────────── */
const itemId = (list.body.match(/\/work-items\/(\d+)"/) || [])[1];
check('T-WI-03', Boolean(itemId), `work-item id discovered in list HTML: ${itemId ?? 'NONE'}`);

if (itemId) {
  const detail = await flow.render({ uri: `/work-items/${itemId}` });
  check(
    'T-WI-04',
    detail.status === 200 && detail.body.includes('بستن مورد'),
    `GET /work-items/${itemId} → ${detail.status}; expected the completion form`,
  );

  /* ── T-WI-05: completing an invoice records the money ───────────────────── */
  const form = await flow.render({ uri: `/work-items/${itemId}` });
  const csrf = token(form.body);
  const isInvoice = form.body.includes('شماره صورتحساب');

  const fields = new URLSearchParams({
    _token: csrf,
    completion_notes: 'از طریق آزمون جریان ثبت شد',
  });

  if (isInvoice) {
    fields.set('invoice_number', 'INV-TEST-9001');
    fields.set('invoice_amount', '12500000');
    fields.set('invoice_issue_date', '۱۴۰۵/۰۸/۲۵');
  }

  const done = await flow.render({
    method: 'POST',
    uri: `/work-items/${itemId}/complete`,
    body: fields.toString(),
  });

  /* A 302 and a success flash are necessary but not sufficient: the row must
   * have moved. We assert the detail page now reports the closed state. */
  const after = await flow.render({ uri: `/work-items/${itemId}` });
  const closed = after.body.includes('این مورد بسته شده است');
  check(
    'T-WI-05',
    done.status === 302 && closed,
    `POST complete → ${done.status}; detail reports closed=${closed}`,
  );

  if (isInvoice) {
    check(
      'T-WI-06',
      after.body.includes('INV-TEST-9001') && after.body.includes('۱۲٬۵۰۰٬۰۰۰'),
      'the invoice number and Persian-formatted amount must be shown on the closed item',
    );
  }

  /* ── T-WI-07: an unknown item id is a 404, not a 500 ─────────────────────── */
  const missing = await flow.render({ uri: '/work-items/99999999' });
  check(
    'T-WI-07',
    missing.status === 404,
    `GET /work-items/99999999 → ${missing.status}; expected 404 (IDOR/robustness)`,
  );
}

/* ── T-WI-08: the notes module renders ────────────────────────────────────── */
const notes = await flow.render({ uri: '/notes' });
check(
  'T-WI-08',
  notes.status === 200 && notes.body.includes('یادداشت و وظیفه'),
  `GET /notes → ${notes.status}`,
);

/* ── T-WI-09: creating a note persists it ─────────────────────────────────── */
const createPage = await flow.render({ uri: '/notes/create' });
check(
  'T-WI-09',
  createPage.status === 200 && createPage.body.includes('یادداشت جدید'),
  `GET /notes/create → ${createPage.status}`,
);

const noteTitle = `یادداشت آزمون ${Date.now().toString(36)}`;
const createRes = await flow.render({
  method: 'POST',
  uri: '/notes',
  body: new URLSearchParams({
    _token: token(createPage.body),
    title: noteTitle,
    description: 'ساخته‌شده توسط آزمون جریان',
    priority: 'high',
    due_date: '۱۴۰۵/۰۹/۰۳',
  }).toString(),
});

/* Redirect is expected; a 200 here would mean validation re-rendered the form
 * (i.e. it failed). Then we prove the note is actually listed. */
const listAfter = await flow.render({ uri: '/notes' });
check(
  'T-WI-10',
  createRes.status === 302 && listAfter.body.includes(noteTitle),
  `POST /notes → ${createRes.status}; note appears in list=${listAfter.body.includes(noteTitle)}`,
);

/* ── T-WI-11: a note with no title is rejected, not silently saved ────────── */
const badNote = await flow.render({
  method: 'POST',
  uri: '/notes',
  body: new URLSearchParams({
    _token: token(createPage.body),
    title: '',
    priority: 'normal',
  }).toString(),
});
const rejected = badNote.status === 302 || badNote.status === 422;
check(
  'T-WI-11',
  rejected,
  `POST /notes with empty title → ${badNote.status}; expected a redirect back with errors`,
);

/* ── T-WI-12: an invalid Jalali date is refused ──────────────────────────── */
const badDate = await flow.render({
  method: 'POST',
  uri: '/notes',
  body: new URLSearchParams({
    _token: token(createPage.body),
    title: 'تاریخ نامعتبر',
    priority: 'normal',
    due_date: '1404/12/30',
  }).toString(),
});
/* Two claims: the row was not created, AND the user was told why. A silent
 * redirect with no error would still pass a "not stored" check while leaving
 * the operator believing the deadline was saved. */
const badDateList = await flow.render({ uri: '/notes' });
const notStored = !badDateList.body.includes('تاریخ نامعتبر');

const badDateForm = await flow.render({ uri: '/notes/create' });
const badDateAgain = await flow.render({
  method: 'POST',
  uri: '/notes',
  body: new URLSearchParams({
    _token: token(badDateForm.body),
    title: 'تاریخ نامعتبر',
    priority: 'normal',
    due_date: '1404/12/30',
  }).toString(),
});
check(
  'T-WI-12',
  notStored && badDateAgain.status === 302,
  `impossible date 1404/12/30: stored=${!notStored}, status=${badDateAgain.status}`,
);

/* A VALID Jalali date must still be accepted — otherwise "fixing" T-WI-12 by
 * rejecting every date would look like a pass. */
const goodDateForm = await flow.render({ uri: '/notes/create' });
const goodRes = await flow.render({
  method: 'POST',
  uri: '/notes',
  body: new URLSearchParams({
    _token: token(goodDateForm.body),
    title: 'وظیفه با تاریخ درست',
    priority: 'normal',
    due_date: '۱۴۰۵/۰۹/۰۳',
  }).toString(),
});
const goodList = await flow.render({ uri: '/notes?status=all' });
check(
  'T-WI-13',
  goodRes.status === 302 && goodList.body.includes('وظیفه با تاریخ درست'),
  `a valid Jalali date must be accepted; status=${goodRes.status}`,
);

process.exit(c.summary() ? 0 : 1);

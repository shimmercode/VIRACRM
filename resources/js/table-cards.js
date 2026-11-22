/**
 * VIRA CRM — responsive list tables.
 *
 * Below 768px a six-column list is wider than the screen. The CSS alone would
 * let the wrapper scroll, but the Actions column sits at the far end (RTL), so
 * the user had to swipe before they could open or edit anything — measured at
 * 390px, 41% of the table width was off-screen, all of it controls and status.
 *
 * This script generates a card view from the table that is already in the DOM:
 * each row becomes a card, each cell a labelled field, and the cell carrying the
 * row's primary link becomes the card title. It reuses the server-rendered HTML,
 * so there is no second source of truth to keep in sync and no extra request.
 *
 * If anything about the table is unexpected — no header cells, no rows — the
 * script does nothing and the scrollable table remains. That is the fallback,
 * and it is the current behaviour, so a failure here cannot make things worse.
 */
(function () {
  'use strict';

  // Only primary links become the card title; they hold the record name.
  var PRIMARY = '.v-table__primary';
  var SUB = '.v-table__sub';
  var ACTIONS = '.v-table__actions';

  // Accepts an element or a string. Header cells are converted to strings once
  // and reused, so this must not assume a DOM node — passing a string to the
  // element branch yields '' and silently drops every field.
  function textOf(node) {
    if (typeof node === 'string') return node.replace(/\s+/g, ' ').trim();
    return ((node && node.textContent) || '').replace(/\s+/g, ' ').trim();
  }

  function buildCard(tr, headers, index) {
    var cells = Array.prototype.slice.call(tr.children);
    if (!cells.length) return null;

    var card = document.createElement('li');
    card.className = 'v-card-row';

    var head = document.createElement('div');
    head.className = 'v-card-row__head';

    var titleWrap = document.createElement('div');
    var primary = tr.querySelector(PRIMARY);
    var title = document.createElement(primary ? 'a' : 'span');
    title.className = 'v-card-row__primary';

    // Prefer the link's own text: cell 0 also contains the sub line, and using
    // the whole cell duplicated the record code in the card heading.
    var firstLine = textOf(cells[0]).split('·')[0].trim();
    title.textContent = primary ? (textOf(primary) || firstLine) : firstLine;

    if (primary) {
      title.href = primary.getAttribute('href');
    }
    titleWrap.appendChild(title);

    var sub = tr.querySelector(SUB);
    if (sub && textOf(sub)) {
      var subEl = document.createElement('div');
      subEl.className = 'v-card-row__sub';
      subEl.textContent = textOf(sub);
      titleWrap.appendChild(subEl);
    }

    head.appendChild(titleWrap);

    // The first cell usually also holds a status badge; move it to the corner.
    var badge = cells[0].querySelector('.v-badge, [class*="status"]');
    if (badge) head.appendChild(badge.cloneNode(true));

    card.appendChild(head);

    var fields = document.createElement('dl');
    fields.className = 'v-card-row__fields';

    cells.forEach(function (cell, i) {
      if (i === 0) return;                       // already the title
      if (cell.classList.contains('v-table__actions')) return;

      // A status cell is already shown as the corner badge.
      if (badge && cell.contains(badge)) return;

      var label = textOf(headers[i]);
      var value = textOf(cell);
      if (!label || !value) return;

      var field = document.createElement('div');
      field.className = 'v-card-row__field';

      var dt = document.createElement('span');
      dt.className = 'v-card-row__label';
      dt.textContent = label;

      var dd = document.createElement('p');
      dd.className = 'v-card-row__value';
      if (cell.getAttribute('dir')) dd.setAttribute('dir', cell.getAttribute('dir'));

      // Keep badges rendered rather than flattened to text.
      var inlineBadge = cell.querySelector('.v-badge');
      if (inlineBadge) {
        dd.appendChild(inlineBadge.cloneNode(true));
      } else {
        dd.textContent = value;
      }

      field.appendChild(dt);
      field.appendChild(dd);
      fields.appendChild(field);
    });

    if (fields.childElementCount) card.appendChild(fields);

    var actionCell = tr.querySelector(ACTIONS);
    if (actionCell) {
      var actions = actionCell.querySelector('.v-actions');
      var links = (actions || actionCell).querySelectorAll('a, button');
      if (links.length) {
        var bar = document.createElement('div');
        bar.className = 'v-card-row__actions';

        Array.prototype.forEach.call(links, function (link) {
          var clone = link.cloneNode(true);

          // The icon button has only an aria-label/title. On touch there is no
          // tooltip, so give it a visible word too.
          var label = clone.getAttribute('aria-label') || clone.getAttribute('title') || '';
          var icon = clone.querySelector('svg');
          if (icon && label) {
            clone.setAttribute('aria-label', label);
            var word = document.createElement('span');
            word.className = 'v-card-row__action-text';
            // "مشاهدهٔ شرکت …" -> "مشاهده"
            word.textContent = label.split(' ')[0].replace(/[ٔ\u0654]/g, '');
            clone.appendChild(word);
          }

          bar.appendChild(clone);
        });

        card.appendChild(bar);
      }
    }

    return card;
  }

  function enhance(wrap) {
    if (!wrap || wrap.getAttribute('data-cards')) return;

    var table = wrap.querySelector('table');
    if (!table) return;

    var headRow = table.querySelector('thead tr');
    var bodyRows = table.querySelectorAll('tbody tr');
    if (!headRow || !bodyRows.length) return;

    var headers = Array.prototype.map.call(headRow.children, textOf);

    var list = document.createElement('ul');
    list.className = 'v-cards';
    list.setAttribute('role', 'list');

    // A card is a visual restatement of a row, so it must not be announced
    // twice by a screen reader. The table is the accessible source of truth
    // until it is hidden, at which point the cards take over.
    var built = 0;
    Array.prototype.forEach.call(bodyRows, function (tr, i) {
      var card = buildCard(tr, headers, i);
      if (card) {
        list.appendChild(card);
        built += 1;
      }
    });

    if (!built) return;

    wrap.appendChild(list);

    // Only now hide the table, so a mid-way failure leaves it visible.
    wrap.setAttribute('data-cards', 'ready');

    // Whichever is hidden should also be hidden from assistive tech.
    var sync = function () {
      var cards = window.matchMedia('(max-width: 767px)').matches;
      table.setAttribute('aria-hidden', cards ? 'true' : 'false');
      list.setAttribute('aria-hidden', cards ? 'false' : 'true');
    };
    sync();
    window.addEventListener('resize', sync, { passive: true });
  }

  function init() {
    document.querySelectorAll('.v-tablewrap').forEach(enhance);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

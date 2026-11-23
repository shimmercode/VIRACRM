/**
 * VIRA CRM — interface behaviour.
 *
 * DELIBERATE CONSTRAINT: this file is loaded with `script-src 'self'` and no
 * `'unsafe-eval'` or `'unsafe-inline'`. Every behaviour below is therefore
 * written as a small, explicit function bound with addEventListener — no inline
 * onclick, no string-to-function evaluation, no templating in markup.
 *
 * Alpine.js is vendored alongside and used for stateful components (menus,
 * dropdowns). Alpine's CSP build is the one shipped, so `x-data` objects must be
 * plain data, never expression strings evaluated from markup.
 *
 * Everything degrades: if this file fails to load, the pages still render and
 * every form still submits. The only losses are the password reveal toggle,
 * the mobile drawer and the dropdown menus — none of which is a security
 * control.
 */
(function () {
    'use strict';

    // ── Password reveal (login, password change) ──────────────────────────
    function initPasswordToggles() {
        document.querySelectorAll('[data-password-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = document.getElementById(btn.getAttribute('data-password-toggle'));
                if (!input) return;

                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.setAttribute('aria-pressed', show ? 'true' : 'false');
                btn.setAttribute('aria-label', show ? 'پنهان کردن رمز عبور' : 'نمایش رمز عبور');
                input.focus({ preventScroll: true });
            });
        });
    }

    // ── Mobile drawer ─────────────────────────────────────────────────────
    function initDrawer() {
        var drawer = document.querySelector('[data-drawer]');
        if (!drawer) return;

        var lastFocused = null;

        function open() {
            lastFocused = document.activeElement;
            drawer.hidden = false;
            document.body.classList.add('v-app--drawer-open', 'is-locked');
            var trigger = document.querySelector('[data-drawer-open]');
            if (trigger) trigger.setAttribute('aria-expanded', 'true');
            // Scope to the dialog, not the whole drawer: the scrim is a
            // <button> that comes first in the DOM, so querying the drawer put
            // focus on the backdrop behind the panel instead of inside it.
            var first = focusable()[0];
            if (first) first.focus();
        }

        function close() {
            drawer.hidden = true;
            document.body.classList.remove('v-app--drawer-open', 'is-locked');
            var trigger = document.querySelector('[data-drawer-open]');
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
            if (lastFocused) lastFocused.focus();
        }

        document.querySelectorAll('[data-drawer-open]').forEach(function (el) {
            el.addEventListener('click', open);
        });
        document.querySelectorAll('[data-drawer-close]').forEach(function (el) {
            el.addEventListener('click', close);
        });

        // The dialog's own focusable controls. The scrim is excluded on
        // purpose: it is a click target for dismissing, not part of the dialog,
        // and tabbing to it means tabbing to something the user cannot see.
        function focusable() {
            var panel = drawer.querySelector('.v-drawer__panel');
            if (!panel) return [];

            return Array.prototype.filter.call(
                panel.querySelectorAll('a[href], button:not([disabled]), input, select, textarea, [tabindex]'),
                function (el) {
                    return el.tabIndex >= 0 && el.offsetParent !== null;
                }
            );
        }

        // Escape closes, and focus stays inside while open. Without trapping,
        // tabbing moves into the page behind the scrim, which is invisible and
        // confusing on a phone.
        drawer.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { close(); return; }
            if (e.key !== 'Tab') return;

            var items = focusable();
            if (!items.length) return;

            var first = items[0];
            var last = items[items.length - 1];

            // If focus somehow left the dialog, pull it back in.
            if (!drawer.contains(document.activeElement) || document.activeElement === drawer.querySelector('.v-drawer__scrim')) {
                e.preventDefault(); first.focus(); return;
            }

            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault(); last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault(); first.focus();
            }
        });
    }

    // ── Dropdown menus (quick create, profile) ────────────────────────────
    function initMenus() {
        var menus = Array.prototype.slice.call(document.querySelectorAll('[data-menu]'));

        function closeAll(except) {
            menus.forEach(function (menu) {
                if (menu === except) return;
                var panel = menu.querySelector('[data-menu-panel]');
                var trigger = menu.querySelector('[data-menu-trigger]');
                if (panel) panel.hidden = true;
                if (trigger) trigger.setAttribute('aria-expanded', 'false');
            });
        }

        menus.forEach(function (menu) {
            var trigger = menu.querySelector('[data-menu-trigger]');
            var panel = menu.querySelector('[data-menu-panel]');
            if (!trigger || !panel) return;

            trigger.addEventListener('click', function (e) {
                e.stopPropagation();
                var willOpen = panel.hidden;
                closeAll(menu);
                panel.hidden = !willOpen;
                trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            });

            panel.addEventListener('click', function (e) { e.stopPropagation(); });
        });

        document.addEventListener('click', function () { closeAll(null); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeAll(null);
        });
    }

    // ── Sidebar collapse (desktop) ────────────────────────────────────────
    //
    // The state class lives on <body> (`v-app--collapsed`), which is what the
    // stylesheet targets. Putting it on the shell wrapper instead would leave
    // the sidebar at full width — a silent no-op.
    function initShellCollapse() {
        var btn = document.querySelector('[data-shell-collapse]');
        if (!btn) return;

        // Remember the preference: a user who collapsed the sidebar to see more
        // table columns does not want it reopening on every navigation.
        var stored = null;
        try { stored = window.localStorage.getItem('vira.sidebar.collapsed'); } catch (e) {}

        function apply(collapsed) {
            document.body.classList.toggle('v-app--collapsed', collapsed);
            btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            btn.setAttribute('aria-label', collapsed ? 'باز کردن منو' : 'جمع کردن منو');
            try {
                window.localStorage.setItem('vira.sidebar.collapsed', collapsed ? '1' : '0');
            } catch (e) {}
        }

        apply(stored === '1');

        btn.addEventListener('click', function () {
            apply(!document.body.classList.contains('v-app--collapsed'));
        });
    }

    // ── Dismissible alerts ────────────────────────────────────────────────
    function initAlerts() {
        document.querySelectorAll('[data-alert]').forEach(function (alert) {
            var close = alert.querySelector('[data-alert-close]');
            if (close) {
                close.addEventListener('click', function () { alert.remove(); });
            }
        });
    }

    // ── Confirm irreversible actions ──────────────────────────────────────
    function initConfirm() {
        document.querySelectorAll('[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (!window.confirm(form.getAttribute('data-confirm'))) {
                    e.preventDefault();
                }
            });
        });
    }

    // ── Prevent double submit ─────────────────────────────────────────────
    // REQ-11.05 names "Loading" as one of four required states. Between submit
    // and the next page render there is a real wait, and before this the form
    // looked idle during it: the button was disabled but visually unchanged, so
    // a second click looked reasonable and nothing said "working".
    //
    // The visible ring is pure CSS (`.v-btn.is-loading`). This function is
    // responsible for the two things CSS cannot do: telling assistive tech the
    // form is busy, and refusing the second submit.
    function initSubmitGuard() {
        document.querySelectorAll('form[data-guard]').forEach(function (form) {
            form.addEventListener('submit', function () {
                var btn = form.querySelector('[type="submit"]');
                if (!btn || form.dataset.submitted) return;

                form.dataset.submitted = '1';
                btn.disabled = true;
                btn.classList.add('is-loading');

                // `aria-busy` on the form makes the in-flight state programmatic
                // rather than only visual.
                form.setAttribute('aria-busy', 'true');

                // A live region exists only for the duration of the request; it
                // announces once and is left in the DOM so the message is not
                // cut off mid-utterance.
                var status = document.createElement('span');
                status.className = 'vira-sr-only';
                status.setAttribute('role', 'status');
                status.setAttribute('aria-live', 'polite');
                status.textContent = btn.getAttribute('data-loading-label') || 'در حال ارسال…';
                form.appendChild(status);
            });
        });
    }

    // ── Jalali date mask (contract form) ──────────────────────────────────
    // Accepts Persian or Latin digits and inserts the slashes. This is a
    // convenience only: the server re-parses the value and rejects anything
    // that is not a real Jalali date.
    function initDateMasks() {
        document.querySelectorAll('[data-jalali]').forEach(function (input) {
            input.addEventListener('input', function () {
                var v = input.value
                    .replace(/[۰-۹]/g, function (d) { return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d)); })
                    .replace(/[٠-٩]/g, function (d) { return String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)); })
                    .replace(/[^\d]/g, '')
                    .slice(0, 8);

                var out = v.slice(0, 4);
                if (v.length > 4) out += '/' + v.slice(4, 6);
                if (v.length > 6) out += '/' + v.slice(6, 8);
                input.value = out;
            });
        });
    }

    // ── Error summary focus ───────────────────────────────────────────────
    // After a failed submit the page re-renders with an error summary at the
    // top. A sighted user sees it; a keyboard or screen-reader user is left at
    // the top of a long form with no signal that anything happened. Moving
    // focus to the summary announces it and gives a starting point.
    function initErrorSummary() {
        var summary = document.getElementById('error-summary');
        if (!summary) return;

        // Only when this is a re-render after a submit — not on a fresh GET.
        summary.focus({ preventScroll: false });
    }

    function boot() {
        initPasswordToggles();
        initDrawer();
        initMenus();
        initShellCollapse();
        initAlerts();
        initConfirm();
        initSubmitGuard();
        initDateMasks();
        initErrorSummary();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();

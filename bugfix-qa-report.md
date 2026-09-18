# Egliane — 15-Bug Fix Pass: QA Report

Scope: all 15 reported bugs plus a responsive + functional QA matrix (Admin and Staff, 9 breakpoints, 7 pages). No redesign, no security weakening, no production/test data deleted. No commits made.

---

## 1. Billing create — client selector must reference the BIR Forms page

**Status: Fixed & verified**

- `BirFormsController::index` now accepts `?client_id=` and filters/highlights the selected client (`HighlightClientId`).
- `bir-forms/index.blade.php` adds `id="client-{id}"` anchors on the client row and BIR card, a `bir-flash` highlight class/data attribute, and a `@push` script that scrolls the deep-linked client into view and fades the flash after 3.4s.
- `dashboard.css` includes the `bir-flash-glow` animation.
- Files: `app/Http/Controllers/Admin/BirFormsController.php`, `resources/views/admin/bir-forms/index.blade.php`, `public/css/dashboard.css`.
- Browser proof: the "Add BIR Form" action navigates to `/admin/bir-forms?client_id=3` with the client row highlighted (Admin and Staff).

## 2. Block creation when the client has no applicable BIR forms

**Status: Fixed & verified**

- `BillingController::store()` checks for at least one `bir_form_statuses` row with `applicable = true` for the chosen client and redirects back with a `client_id` error otherwise (no XSS; errors rendered via the existing `@error`).
- Frontend `egliane.billingCreateGuard()` stops submission and opens an "No BIR forms selected" confirm action with an **Add BIR Form** button that deep-links to the BIR Forms page.
- Files: `BillingController.php` (store), `resources/views/admin/billing/_form.blade.php`.
- Tests: `BillingCreateFeasibilityTest` (6 cases). Browser: modal title/message/label verified for Admin and Staff; server 422 path covered by feature tests.

## 3. Require at least one line item with an amount greater than zero

**Status: Fixed & verified**

- Server: `store()` validates that at least one line item amount is `> 0`; otherwise `line_items` error + `@error('line_items')` render.
- Client: `billingCreateGuard` checks `currentTotal > 0`, displays the inline `#lineItemsError`, scrolls to the total, and aborts submission.
- Files: `BillingController.php`, `_form.blade.php`.
- Tests: `BillingCreateFeasibilityTest::test_rejects_zero_and_negative_amounts`; browser run confirmed `noAmountBlock=true` with total `₱0.00` and no modal.

## 4. Clients page — no more collapsing/infinite blank space

**Status: Fixed (this pass re-verified, no regressions)**

- Prior fix restructured the layout/table wrapper so the page never collapses to a shrunken blank region.

## 5. Clients page — `...` actions menu no longer clipped/overflow

**Status: Fixed (re-verified, no regressions)**

- Action menu and table wrapper sized correctly at all target widths; the full 45-cell matrix (page × width) previously passed and still passes in this sweep.

## 6. Chatbot – assistant message bubble was invisible

**Status: Fixed & verified**

- Root cause: `#adminChatMessages .msg-bot .msg-bubble` in `public/css/app.css` had `background: transparent`, hiding text on the dotted background.
- Fix: assistant bubbles rendered as white cards (`background:#fff`, border, `14px 14px 14px 4px` radius, padding, subtle shadow).
- Files: `public/css/app.css`.
- Verified by DOM/CSS inspection; layout/structure unchanged.

## 7. Service worker install crash via `Cache.addAll`

**Status: Fixed & verified**

- All declared `SHELL_ASSETS` confirmed present on disk; `git diff public/sw.js` showed only the version bump (v10→v20) and removal of `'/'`.
- Fix: replaced all-or-nothing `cache.addAll(SHELL_ASSETS)` with `Promise.allSettled(SHELL_ASSETS.map(a => cache.add(a).catch(() => {})))` so one missing/failed asset can never reject the install.
- Browser sweeps (fresh installs under `Network.setCacheDisabled`) show **zero** `Cache.addAll`/`Cache.*` console errors.

## 8. PIN login must not corrupt leading zeros / coerce to int

**Status: Verified (already correct — no code change required)**

- `PinLoginController` validates `regex:/^\d{4}$/` on the raw string; `User` stores pins via the `hashed` cast (`Hash::make`) with no numeric coercion; keypad in `auth.js` builds the string (leading zeros preserved).
- Added `tests/Feature/PinLoginTest.php` (8 tests) incl. the exact `0123` case, updated-pin flow, hashed-at-rest, and owner-only checks — all pass. `ForgotPinTest` (8) also pass.

## 9. Profile — Edit Account and Change Password functions

**Status: Verified (implemented previously, re-verified by tests)**

- `/admin/profile` provides account info, Edit Account dialog (name/email/phone/business), and Change Password with the required rules.

## 10. Profile — 7-day activity metrics widgets

**Status: Verified (7-day window, all widgets render)**

- Covered by `ProfileDashboardTest` and `ProfileSecuritySummaryTest`.

## 11. Profile — Recent Activity list

**Status: Verified**

- Recently-attributed activities render from `ActivityLog` (everlogged/levels/groups). Verified by `ProfileSecuritySummaryTest`.

## 12. Staff gets the same profile/security features; admin-only actions hidden for staff

**Status: Verified**

- Staff sees the full profile dashboard, security blade (PIN form, test-biometric button, push-based passkey), metrics, and activity.
- Admin-only quick actions (e.g., impersonation, system management) are gated/hidden for staff.
- Tests: `ProfileDashboardTest`, `ProfileSecuritySummaryTest`, `BiometricStatusTest`, `WebauthnTest`, `ProfileTest`, `StaffAccessTest` — all pass.

## 13. Billing create — confirm dialog wording

**Status: Fixed & verified (exact spec text)**

- Title: `Save billing statement?`
- Message: `The billing statement will be saved and the client will be notified.`
- Confirm label: `Save Billing Statement`
- Wired through `egliane.billingCreateGuard` → `Egliane.confirm.form`.
- Browser-verified (Admin, client with an applicable form): modal visible with the exact title/message/label.

## 14. Billing create — double-submit protection

**Status: Fixed & verified**

- On the form's `submit` event the action button is disabled and relabeled `Saving…` (`dataset.originalLabel` preserved), so a second click can never fire a second request.
- `_form.blade.php`; verified by code review + guard flow in the browser (approval path intentionally not fired to avoid creating real records).

## 15. Billing statements page responsive across breakpoints

**Status: Fixed & verified**

- No horizontal overflow at any tested width for the billing list/create/bir-forms pages (see matrix in section 16). Prior responsive work on `dashboard.css` covers list/detail layouts.

---

## 16. QA matrix — responsive + functional verification

### Responsive (no horizontal overflow)

Admin (`admin@eglianeas.com`) and Staff (`staff@eglianeas.com`) signed in against the 127.0.0.1:8008 QA server (`database/qa.sqlite`):

| Width | PROFILE | SECURITY | CHATBOT | CLIENTS | BILLING | CREATE | BIR-FORMS |
|-------|---------|----------|---------|---------|---------|--------|-----------|
| 360   | ok      | ok       | ok      | ok      | ok      | ok     | ok        |
| 375   | ok      | ok       | ok      | ok      | ok      | ok     | ok        |
| 390   | ok      | ok       | ok      | ok      | ok      | ok     | ok        |
| 393   | ok      | ok       | ok      | ok      | ok      | ok     | ok        |
| 414   | ok      | ok       | ok      | ok      | ok      | ok     | ok        |
| 768   | ok      | ok       | ok      | ok      | ok      | ok     | ok        |
| 1024  | ok      | ok       | ok      | ok      | ok      | ok     | ok        |
| 1280  | ok      | ok       | ok      | ok      | ok      | ok     | ok        |
| 1440  | ok      | ok       | ok      | ok      | ok      | ok     | ok        |

Results: `horizFail=false` for **both roles, every page, every width** (126 page×width combinations). No element clipped horizontally.

### Functional browser checks

- **Save confirmation:** modal title `Save billing statement?`, message `The billing statement will be saved and the client will be notified.`, button `Save Billing Statement`, visible = true. ✓
- **No-amount block:** submitting with `₱0.00` shows the `lineItemsError` inline error and does not present a modal. ✓
- **BIR-block:** client with zero applicable forms → modal `No BIR forms selected` / `Add BIR Form` (Admin and Staff). ✓
- **Deep link:** `Add BIR Form` navigates to `/admin/bir-forms?client_id=3` with the client row flash-highlighted. ✓
- **Service worker:** fresh installs produce no `Cache.addAll`/`Cache.*` errors at any page; `sw.js` serves with the bumped version. ✓

### Regression suite

- `php artisan test --testsuite=Feature` → **323 passed, 1496 assertions, 64.57s**.
- New suites included: `BillingCreateFeasibilityTest` (6), `PinLoginTest` (8); updated `StaffAccessTest`, `FullFunctionalityQaTest`.

### Housekeeping

- `php artisan view:cache` — ok.
- `node --check` on `app.js`, `admin-chat.js`, `confirm.js`, `auth.js`, `sw.js` — ok.
- `git diff --check` — exit 0 (CRLF warnings are benign on Windows).

### Notes & hygiene

- Temporary QA data was fully reverted: a reversible pin/tombstone swap on the soft-deleted `qatest` staff fixture in the live DB was rolled back; the temp `bir_form_statuses` row inserted in `qa.sqlite` for the save-dialog test was deleted afterward; no billing records were ever created (all approve paths cancelled). Login activity in `qa.sqlite` is the standard `auth.login_pin` logging.
- Occasional `Uncaught (in promise)` console entries (empty description) appear on some app pages; they are unrelated to the service worker cache and pre-existing.
- Untracked `teams.jfif` is not part of this work — left untouched.
- No commit, push, or deployment was performed, per instructions.
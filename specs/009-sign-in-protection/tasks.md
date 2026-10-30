# Tasks: sign-in protection, automated account creation and sessions

Feature `009-sign-in-protection`, PBI #305, AUTH-15. From `plan.md`. Every task names its test (M-7). Task-to-issue
generation is disabled: these are not GitHub issues.

Integration scenarios run on the disposable runtime only, serially. Settings scenarios snapshot and restore exactly what
they change; lockout scenarios use disposable identities and remove only their own attempt rows.

## Phase 1: Fixtures

- [x] T001 Write `_sign_in.php`: snapshot and restore CoreX's settings, apply the baseline through the command, remove a
      scenario's attempt rows by identity hash, open and restore registration settings, read a page's signup token.
      Test: used by t46 to t48.
- [x] T002 Write `mizzey-site/tests/browser/sign-in-flows.cjs`, finding Playwright as `browser-matrix.cjs` does, driving
      Chromium through the account, checkout and order-received forms and the sign-in links, and printing JSON. Test:
      used by t49.

## Phase 2: Failing scenarios first

- [x] T003 [US1] Write `t46-sign-in-limit.php` (AC-1, FR-002, AC-4). Test: itself.
- [x] T004 [US2] Write `t47-signup-check.php` (AC-2, AC-4). Test: itself.
- [x] T005 [US3] Write `t48-sessions.php` (AC-3). Test: itself.
- [x] T005a Write `t49-sign-in-browser.php` (AC-2 in a browser, FR-002). Test: itself.
- [x] T006 Run t46 to t49 before the Mizzey code and keep the output as `evidence/before.txt`. Each must fail where the
      Mizzey code is the claim. Captured after the code, against `main`, with the final scenarios; see `verification.md`.

## Phase 3: The protection

- [x] T007 [US1] Add `sign-in-baseline` to `Cli/SecurityCommand.php` (FR-001). Test: t46.
- [x] T008 [US1] Translate CoreX's lockout message in `SignInMessages.php`. Test: t46.
- [x] T009 [US2] Create `src/Security/SignupCheck.php`, the verifier and the atomic claim (FR-005). Test: t47.
- [x] T010 [US2] Create `src/Security/SignupRoutes.php`: the four adapters, the insertion guard with one permit per
      insertion, the inventory (FR-003, FR-004). Test: t47.
- [x] T011 [US2] Add the rendering: the form fields, `assets/signup-check.js`, the Store API schema. Test: t47's browser
      cases.
- [x] T012 Add the four rows to `BaselineReport.php` (FR-007), adapting t41 for `corex_login_protection`. Test: t41,
      t46, t47.

## Phase 4: Records and verification

- [x] T013 Extend `docs/security-baseline.md`: the session policy, the limit, the signup method and its limits,
      recovery (FR-006, FR-008). Test: docs review.
- [x] T014 Codex code review; record it in `analysis.md`. Test: `analysis.md`.
- [x] T015 Reset the runtime to the clean baseline and run the whole suite serially; commit `evidence/`. Test: the suite.
- [x] T016 Write `verification.md`, keeping technical verification, staging and client acceptance apart. Test: the scope
      trace.

## Not done until later

- [ ] T017 Staging: deploy, apply the sign-in baseline on the owner's say-so, verify cache bypass on every token-bearing
      page and `Secure` cookies over HTTPS, run the browser flows there (DOD-04).
- [ ] T018 Client approval through MS-UAT-2026-027 (DOD-09).

## Not tasks

- S-1 (edge rate limit), S-2 (per-address account limit), S-3 (a challenge service): offered, not approved.
- Registration screens, Google sign-in, guest conversion: later #305 slices.

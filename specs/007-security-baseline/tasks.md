# Tasks: security baseline, the site-level controls

Feature `007-security-baseline`, PBI #269, NFR-05. From `plan.md`. Every task names its test (M-7). Task-to-issue
generation is disabled: these are not GitHub issues.

Integration scenarios run on the disposable runtime only, serially, and never while another session is using it.

## Phase 1: Failing scenarios first

- [ ] T001 [US1] Write `mizzey-site/tests/integration/t39-security-exposure.php` (AC-1, AC-2, AC-3). Test: itself.
- [ ] T002 [US2] Write `t40-security-accounts.php` (AC-4, AC-5, AC-6), intercepting mail with `pre_wp_mail`. Test:
      itself.
- [ ] T003 [US3] Write `t41-security-check.php` (AC-7, AC-8). Test: itself.
- [ ] T004 Run t39 to t41 before any production code and keep the output as `evidence/before.txt`. Each must fail.

## Phase 2: The controls

- [ ] T005 [US1] Create `src/Security/Disclosure.php` and `XmlRpc.php` (FR-001, FR-002). Test: t39.
- [ ] T006 [US1] Create `AccountDiscovery.php` (FR-003). Test: t39.
- [ ] T007 [US2] Create `ApplicationPasswords.php` (FR-004). Test: t40.
- [ ] T008 [US2] Create `SignInMessages.php` (FR-005). Test: t40.
- [ ] T009 [US2] Create `RecoveryLimiter.php` and the translation files (FR-006). Test: t40.
- [ ] T010 [US3] Create `CommentSurface.php` (FR-007). Test: t41.
- [ ] T011 [US3] Create `BaselineReport.php` and `Cli/SecurityCommand.php` (FR-008). Test: t41.
- [ ] T012 Create `Security.php` and register it in `mizzey-site.php`. Test: t39 to t41 pass.

## Phase 3: Verification and records

- [ ] T013 Write `docs/security-baseline.md`: each control, its hook, its setting and its test, for the handover
      (US-27-03). Test: docs-guard.
- [ ] T014 Run the guards on the diff (wp-guard, woo-guard, test-guard, clean-code-guard, docs-guard as relevant).
      Test: `analysis.md`.
- [ ] T015 Reset the runtime to the clean baseline and run the whole suite serially; commit `evidence/final-suite.txt`.
      Test: the suite, t02 to t29 verdicts unchanged.
- [ ] T016 Write `verification.md`, keeping technical verification, staging and client acceptance apart. Test: the
      scope trace.

## Not done until later

- [ ] T017 Staging: deploy, run `wp mizzey security check` there (DOD-04). Needs the owner's approval for the deploy.
- [ ] T018 Client approval through MS-UAT-2026-027 (DOD-09).

## Not tasks

- CoreX login lockout and form challenges: AUTH-15 (#305). Two-factor sign-in: ADM-131 (#275).
- Server headers, archive refusal, edge limits, the trusted-proxy setting: server changes on the owner's approval.
- S-1: not approved, not built.

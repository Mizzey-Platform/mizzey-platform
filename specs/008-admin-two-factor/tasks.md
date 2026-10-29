# Tasks: two-factor sign-in for admin accounts

Feature `008-admin-two-factor`, PBI #275, ADM-131. From `plan.md`. Every task names its test (M-7). Task-to-issue
generation is disabled: these are not GitHub issues.

Integration scenarios run on the disposable runtime only, serially, and never while another session is using it. Each
two-factor scenario applies the baseline and restores the earlier policy when it finishes.

## Phase 1: Runtime and fixtures

- [ ] T001 Install WP 2FA 4.2.1 in the disposable runtime from the verified zip (the owner's one-plugin exception to the
      wp-content rule, 29 October 2026); provision its key with `wp config set`; add both to `reset-runtime.sh`. Test: the
      reset, and `two_factor_key` in the check.
- [ ] T002 Write `_two_factor.php`: apply and restore the baseline, enrol a fixture user (TOTP and backup codes), compute
      RFC 6238 codes, sign in over HTTP in two steps. Test: used by t42 to t45.

## Phase 2: Failing scenarios first

- [ ] T003 [US1] Write `t42-two-factor-sign-in.php` (AC-1, AC-2, FR-007). Test: itself.
- [ ] T004 [US3] Write `t43-two-factor-policy.php` (AC-2, AC-5). Test: itself.
- [ ] T005 [US2] Write `t44-two-factor-enrolment-guard.php` (AC-3). Test: itself.
- [ ] T006 [US3] Write `t45-two-factor-recovery.php` (AC-4). Test: itself.
- [ ] T007 Run t42 to t45 before the Mizzey code and keep the output as `evidence/before.txt`. Each must fail where the
      Mizzey code is the claim (the command, the guard, the check rows).

## Phase 3: The integration

- [ ] T008 [US2] Create `src/Security/TwoFactor.php`, registered from `Security::register()` (FR-003). Test: t44.
- [ ] T009 [US3] Add `two-factor-baseline` to `Cli/SecurityCommand.php` (FR-002). Test: t43.
- [ ] T010 [US3] Add the two-factor rows to `BaselineReport.php` (FR-005). Test: t43.
- [ ] T011 Pin `wp-2fa` 4.2.1 in `stack.lock.json` as tested once t42 to t45 pass (FR-001). Test: the suite.
- [ ] T012 Provision the staging key in `wp-config-server.php.tmpl` and `staging_server.py` (FR-004). Test: the staging
      tool's own unit tests, and the check on staging.

## Phase 4: Records and verification

- [ ] T013 Write `docs/two-factor-recovery.md` and extend `docs/security-baseline.md` (FR-006). Test: t45 follows its
      steps; docs review.
- [ ] T014 Codex code review; record it in `analysis.md`. Test: `analysis.md`.
- [ ] T015 Reset the runtime to the clean baseline and run the whole suite serially; commit `evidence/`. Test: the
      suite, t02 to t41 verdicts unchanged.
- [ ] T016 Write `verification.md`, keeping technical verification, staging and client acceptance apart. Test: the
      scope trace.

## Not done until later

- [ ] T017 The release packer bundles `wp-2fa` from wordpress.org: its own `internal:tooling` pull request.
- [ ] T018 Staging: deploy, apply the baseline on the owner's say-so, enrol the staging staff, run the check and the HTTP
      scenarios there (DOD-04).
- [ ] T019 Client approval through MS-UAT-2026-027 (DOD-09).

## Not tasks

- S-1 (application passwords refused for enforced users): not approved, not built.
- The searchable log of logins and security events (ADM-132 to ADM-134): the next #275 slice.
- Passkeys: off.

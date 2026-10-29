# Analysis: two-factor sign-in for admin accounts

The review record for T014. Read-only reviews by Codex (gpt-6.1-sol, effort high, the re-review medium, one
thread), each finding checked against WP 2FA 4.2.1's source and the runtime before it was applied.

## Before the specification

A static assessment of WP 2FA 4.2.1 (the approved component, TDD row 20) against ADM-131. What it established, and what
this feature does with it:

| WP 2FA provides | Used here |
|---|---|
| A second-factor challenge after the password, on `wp-login.php` and on WooCommerce's account page (both reach `wp_login`) | Yes: AC-1, tested in both languages |
| Role-based enforcement, set in its own policy screen | Yes: the baseline command sets D-21's roles once; the owner changes them afterwards (AC-2) |
| Authenticator app (TOTP) and backup codes | Yes: the baseline's methods |
| A redirect of unenrolled staff to set-up, on some administration pages only | Not enough on its own: REST and background requests were reachable. Mizzey's enrolment guard closes them (AC-3) |
| Administrator reset of another user's second factor | Yes: AC-4, through the real endpoint |
| An encryption key, falling back to one stored in the database | The key comes from configuration; the check reviews any database copy |
| Application passwords | Not challenged: native WordPress authenticates them before WP 2FA. Recorded in `docs/security-baseline.md`; S-1 offered, not built |

## Specification review (FIX FIRST, applied)

Eight fixes in the first round. The material ones: AC-5 is an engineering safeguard, not a contractual criterion; AC-2
promises only that an unenrolled customer-only account is not challenged; the publisher's "Tested up to: 7.1" is not a
7.1.4 claim, so `stack.lock.json` says tested only after this feature's scenarios pass; the baseline command's owned
fields are listed in full, including the exclusions it must clear; the guard's exceptions are named exactly and WP 2FA's
own checks stay in force on them. The second round added one fix: t41 runs on a deliberately unenforced runtime, so it
expects `two_factor_roles` to say review and the check to exit 1; t43 proves the exit 0 on the full baseline.

## Code review (FIX FIRST, applied)

| Finding | Applied |
|---|---|
| `profile.php?show=wp-2fa-setup&page=...` (or `import=`) passed the set-up exception and dispatched another screen | The exception also requires no `page` and no `import`; t44 tries both |
| The policy row passed with email codes, passkeys or a changed expiry action | The row checks the whole baseline; t43 drifts each one |
| The command accepted any non-empty policy hash | It compares the stored hash with WP 2FA's own calculation for the stored policy |
| t43 registered its restore after its first change, and its database-key probe could delete an existing row | The restore is registered first; the probe insert is checked and nothing pre-existing is removed |
| t45 checked capabilities, not the reset | t45 calls the real reset endpoint as an enrolled shop manager (refused) and an enrolled administrator (allowed), then the reset account must enrol again |
| The REST exception and the REST challenge were not exercised; the plan misdescribed REST failures | t44 covers the exception, t42 the wrong code, retry and right code; the plan is corrected |

## Re-review of the fixes (FIX FIRST, applied)

The code fixes were accepted. Two test assertions were too loose: the REST wrong-code case read a `success` field that
WP 2FA does not send (it answers 200 with `status: false`), so a missing field passed; and the forged-transaction case
accepted any error code. Both now assert WP 2FA's exact answer, checked against `class-api-login.php`: 200 with
`status: false` for a wrong code, 403 `invalid_request` for a forged transaction.

## Found by mutation

Found by mutation rather than review: with the exclusions left out of the baseline, t43 still passed, because no
exclusion was stored. t43 now seeds an excluded role, an excluded user and an enforced customer, and the baseline must
clear all three. `evidence/mutation.txt`: four deliberate defects, each caught by its scenario.

Reported and not acted on, with the reason:

- The pending-staff fact reads at most 500 staff accounts and reports none while the bypass is on. It is reported, not
  judged, and the bypass row already says review.
- CoreX's lockout records a first-factor success before the second factor. That is CoreX's record, not a sign-in.
- Application passwords for administrators stay available (D-21); S-1 would refuse them for enrolled staff.

## House rules

No em dash, no emoji, no heading below h3 in the changed files. Command and screen text is translatable
(`test_storefront_strings`) and the catalogue template is regenerated. No credential or key value is written to the
repository; the staging key is generated on the server and kept in its state directory.

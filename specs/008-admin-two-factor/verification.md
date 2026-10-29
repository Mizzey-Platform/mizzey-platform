# Verification: two-factor sign-in for admin accounts

Three states, kept apart (constitution M-7): technically verified, verified on staging, accepted by the client. Only
the first is claimed here.

## 1. Technical verification, 30 October 2026, development runtime

Stack: WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, WCML 5.5.7, CoreX v0.47.0, WP 2FA 4.2.1 (the wordpress.org zip,
SHA-256 `43b38e97f43f418ec0acf3434587a39c20975844640a66a474825709c205c548`).

| Evidence | What it shows |
|---|---|
| `evidence/before.txt` | t42 to t45 against the site code on `main` with WP 2FA active: each fails. Recorded after the code was written, because T007 was not captured beforehand; each stops at the missing baseline command |
| `evidence/mutation.txt` | Four deliberate defects (the REST guard, the set-up exception, the policy row, the baseline's exclusions), each caught by its scenario |
| `evidence/clean-baseline.txt` | The runtime reset to the clean baseline before the suite (exit 0) |
| `evidence/final-suite.txt`, `final-suite.json` | The whole suite, serially: 41 scenarios, 35 pass, 6 fact-finding, 0 failed |
| `evidence/final-rerun.txt` | t42 and t44 again after the re-review tightened two assertions: both pass |

After the suite, the runtime's WP 2FA policy is back to the state it was found in (not enforced): each scenario
restores it.

| AC | Scenario | Result |
|---|---|---|
| AC-1 | t42 | Pass: after enrolment a password alone gives no session on `wp-login.php` and on the account page in English and Arabic; a valid code completes sign-in; through the REST challenge a wrong code is refused, a replacement transaction is issued and the right code completes sign-in |
| AC-2 | t42, t43 | Pass: the baseline requires administrators and shop managers; an unenrolled customer is not challenged; when the owner narrows the roles in WP 2FA's setting, a fresh request follows the owner's choice |
| AC-3 | t44 | Pass: a pending staff member's existing and new sessions reach only the set-up screen, WP 2FA's enrolment and sign-out actions and its sign-in route; other pages redirect to set-up, administration actions, REST and background requests are refused, and the set-up screen cannot dispatch another screen |
| AC-4 | t45 | Pass: each backup code works once; the runbook's commands work; the reset endpoint refuses an enrolled shop manager and allows an enrolled administrator, after which the reset account must enrol again |
| AC-5 | t41, t43 | Pass: the check exits 0 on the full baseline; with the plugin deactivated, the bypass on, a database fallback key, a leftover key row or a drifted policy, the matching row says review and the check exits 1; the staff still to enrol are reported |

Unit and repository checks: `python -m unittest discover -s tools/tests -t .`, `repo_checks.py`, `discovery.check`,
`house_rules.py` and `scope_trace.py`, as reported in the pull request.

## 2. Staging

Not done. T017 (the release packer bundling WP 2FA, its own pull request) and T018 wait: the server needs its
encryption key provisioned (`staging_server.py server-config`), and the baseline is applied and the staging staff
enrolled only on the owner's say-so. The person's own staging review (DOD-04) is separate from any automated check.

## 3. Client acceptance

Not done. T019, through MS-UAT-2026-027 (DOD-09).

# Implementation Plan: two-factor sign-in for admin accounts

**Branch**: `008-admin-two-factor` | **Date**: 2026-10-29 | **Spec**: [spec.md](spec.md)

## Summary

WP 2FA 4.2.1 (free) provides enrolment, the challenge, backup codes and the owner's role setting. Mizzey adds three
small things around it: a Developer command that applies the D-21 baseline through WP 2FA's own settings API, a guard
that keeps an enrolment-pending staff session away from REST and background requests, and two-factor rows in the
security check. Staging gets a per-server encryption key. A recovery runbook covers lost factors.

## Technical Context

| | |
|---|---|
| Runs in | WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, CoreX v0.47.0, WP 2FA 4.2.1 |
| Storage | WP 2FA's own options and user meta; nothing of Mizzey's |
| Dependencies | WP 2FA 4.2.1, from wordpress.org (sha256 `43b38e97f43f418ec0acf3434587a39c20975844640a66a474825709c205c548`) |
| Testing | Integration scenarios over real HTTP; TOTP codes computed in the test from the stored, decrypted secret (RFC 6238: SHA-1, six digits, 30 s) |

## Constitution Check

| Principle | Held? | How |
|---|---|---|
| M-1 scope traced | Yes | ADM-131 only; ADM-130, 132 to 134 are later #275 slices |
| M-4 native first | Yes | The approved free component does the work; Mizzey covers only its recorded gap (REST and AJAX before enrolment) and reporting |
| One authority (ADR-0003) | Yes | The policy is WP 2FA's; the baseline command applies it once through WP 2FA's API; Mizzey never rewrites it |
| M-9 languages | Yes | The administration is English (ADM-159); the challenge is reached from both languages' account URLs |

## Design

| Piece | Responsibility |
|---|---|
| `src/Security/TwoFactor.php` | The enrolment guard (FR-003): on `rest_authentication_errors` and `admin_init` for AJAX, when WP 2FA says the current user must enrol and has not (`User_Helper::is_enforced()` and not `is_user_using_two_factor()`), refuse with 403, allowing WP 2FA's REST namespaces and its enrolment AJAX actions. Inert when WP 2FA is not active |
| `src/Security/Cli/SecurityCommand.php` | Adds `two-factor-baseline` (FR-002): merges the baseline into the current policy and saves it with `\WP2FA\WP2FA::update_plugin_settings()` |
| `src/Security/BaselineReport.php` | Rows (FR-005): `two_factor_active`, `two_factor_roles`, `two_factor_bypass`, `two_factor_key`; fact `two_factor_pending_staff` |
| `stack.lock.json` | `wp-2fa` 4.2.1, tested |
| `mizzey-site/tests/staging/wp-config-server.php.tmpl`, the staging tool | A per-server `WP2FA_ENCRYPT_KEY`, generated once and kept in the server's shared state, never in the repository (FR-004) |
| `mizzey-site/tests/integration/baseline/reset-runtime.sh` | Activates `wp-2fa`; the runtime's `wp-config.php` gets a disposable key through `wp config set` |
| `docs/two-factor-recovery.md` | The runbook (FR-006); `docs/security-baseline.md` gains the rows and the application-password boundary |

## Criterion to test map

| AC | Scenario | What it asserts |
|---|---|---|
| AC-1 | `t42-two-factor-sign-in` | Over HTTP, on `wp-login.php` and the account page in both languages: an enrolled administrator with the password alone gets the challenge and no `wordpress_logged_in` cookie that authenticates; a computed TOTP completes sign-in; a wrong code is refused and fires `wp_login_failed` |
| AC-2 | `t42-two-factor-sign-in`, `t43-two-factor-policy` | The baseline command sets the enforced roles; a shop manager is enforced; a customer signs in with no challenge; changing the roles in WP 2FA's setting takes effect |
| AC-3 | `t44-two-factor-enrolment-guard` | A signed-in, not yet enrolled shop manager: a privileged REST call with a valid nonce is refused, a non-WP 2FA AJAX action is refused, WP 2FA's enrolment AJAX is allowed; once enrolled, the same calls work |
| AC-4 | `t45-two-factor-recovery` | A backup code completes sign-in once and fails on reuse; an administrator's reset removes the user's factor and they must enrol again; the runbook's sole-administrator steps work on a disposable user |
| AC-5 | `t43-two-factor-policy` | The check passes every two-factor row on the baseline; with the plugin deactivated, the bypass defined, or the key moved into the database, the matching row says review and the command exits 1 |
| FR-007 | `t42-two-factor-sign-in` | Home and a product page as a visitor load no `wp-2fa` asset |

## Risks

| Risk | Mitigation |
|---|---|
| A plugin update changes its internals (helpers, option names) | Pinned at 4.2.1; the guard and the check use its public helpers and fail closed (review) if they are missing |
| Lock-out during tests on the shared runtime | Scenarios use their own users and never enrol `pilot_admin`; they remove their users |
| The application-password boundary | Documented; S-1 offered, not built |

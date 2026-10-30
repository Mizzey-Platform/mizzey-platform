# Implementation Plan: two-factor sign-in for admin accounts

**Branch**: `008-admin-two-factor` | **Date**: 2026-10-29 | **Spec**: [spec.md](spec.md)

## Summary

WP 2FA 4.2.1 (free) provides enrolment, the challenge, backup codes and the owner's role setting. Mizzey adds three
small things around it: a Developer command that applies the D-21 baseline through WP 2FA's own settings API, a guard
that keeps an enrolment-pending staff session away from administration pages, actions, REST and background requests,
and two-factor rows in the security check. Staging gets one stable per-server encryption key. A recovery runbook covers
lost factors.

## Technical Context

| | |
|---|---|
| Runs in | WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, CoreX v0.47.0, WP 2FA 4.2.1 |
| Storage | WP 2FA's own options and user meta; nothing of Mizzey's |
| Dependencies | WP 2FA 4.2.1, from wordpress.org (sha256 `43b38e97f43f418ec0acf3434587a39c20975844640a66a474825709c205c548`) |
| Testing | Request behaviour over real HTTP. For enrolled fixtures, generate an encrypted key with `\WP2FA\Authenticator\Authentication::generate_key()` and pass it with the `WP_User` to `\WP2FA\Methods\TOTP::set_user_method($user, $encryptedKey)`; generate recovery codes with `\WP2FA\Methods\Backup_Codes::generate_codes($user)`. Read the fixture secret with `TOTP::get_totp_decrypted($user)` and compute RFC 6238 codes independently (SHA-1, six digits, 30 s). Secrets and codes never reach evidence. Separate users or later time steps for successive sign-ins, because TOTP replay is refused |

## Constitution Check

| Principle | Held? | How |
|---|---|---|
| M-1 scope traced | Yes | ADM-131 only; ADM-130, 132 to 134 are later #275 slices |
| M-4 native first | Yes | The approved free component does the work; Mizzey covers only its recorded gaps and reporting |
| One authority (ADR-0003) | Yes | The policy is WP 2FA's; the baseline command applies it once through WP 2FA's API; Mizzey never rewrites it |
| M-9 languages | Yes | The administration is English (ADM-159); the challenge is reached from both languages' account URLs |

## Design

| Piece | Responsibility |
|---|---|
| `src/Security/TwoFactor.php` | FR-003: obtain the actual current `WP_User` and evaluate `User_Helper::is_enforced($user, true) && ! User_Helper::is_user_using_two_factor($user)`. REST guard at `rest_authentication_errors` priority 110, preserving an existing `WP_Error` and evaluating authenticated users even when the incoming result is true. Administration guard at `admin_init` priority 0, before action dispatch. Only the enrolment exceptions below are permitted. Inactive when WP 2FA is inactive or `defined('DISABLE_2FA_LOGIN') && DISABLE_2FA_LOGIN` |
| `src/Security/Cli/SecurityCommand.php` | Adds `two-factor-baseline` (FR-002). Reads the stored `wp_2fa_policy` through WP 2FA's `Settings_Utils`; uses plugin defaults when absent and refuses malformed stored data. Replaces the baseline-owned fields, preserving unrelated ones, then calls `\WP2FA\WP2FA::update_plugin_settings($policy, false, WP_2FA_POLICY_SETTINGS_NAME)`. Verifies persistence and the policy hash; effective behaviour is verified in a fresh request, because helper caches can keep earlier values within one process |
| `src/Security/BaselineReport.php` | FR-005: `two_factor_active` checks activation, the pinned version and the enforcement and guard hooks; `two_factor_roles` checks an enforcing policy covering both baseline roles, with no exclusions, and the baseline methods and no grace; `two_factor_bypass` fails only when `defined('DISABLE_2FA_LOGIN') && DISABLE_2FA_LOGIN`; `two_factor_key` requires a non-empty configuration key, no plugin fallback marker (`WP2FA_SECRET_IS_IN_DB`), and no `wp_2fa_secret_key` option row (existence only, never the value). Fact `two_factor_pending_staff` counts unenrolled staff, independently of cached status. All four are in `BaselineReport::CONTROLS` |
| `stack.lock.json` | Pin `wp-2fa` 4.2.1; mark it tested only after the recorded compatibility scenarios pass |
| `mizzey-site/tests/staging/wp-config-server.php.tmpl`, `staging_server.py` | A dedicated key placeholder before `wp-settings.php`. `cmd_server_config` reuses a validated, base64-encoded random key from an owner-only file under the existing `STATE` directory, generating it only for initial provisioning; never a fresh salt. Never regenerated on deployment or restore; backed up separately. Configuration checks report presence or errors without printing the key |
| `mizzey-site/tests/integration/baseline/reset-runtime.sh` | Provisions the disposable runtime's key with `wp config set` before WP 2FA first loads, then activates it. The baseline is not applied here: the two-factor scenarios apply it and restore the policy after themselves |
| `docs/two-factor-recovery.md` | The runbook (FR-006); `docs/security-baseline.md` gains the rows and the application-password boundary |

REST exception: POST `/wp-2fa-methods/v1/login/validate`, matched as a normalised route, not by URL substring. No other
WP 2FA route is exempt; `wp-2fa-passkeys/v1` is not needed by this baseline.

AJAX exceptions, matched by exact action name: `regenerate_authentication_key`, `validate_authcode_via_ajax`,
`wp2fa_run_ajax_generate_json`, `send_backup_codes_email`, `wp2fa_dismiss_required_intro` and `wp2fa_logout_account`.
If the owner later enables email enrolment, `send_authentication_setup_email` is added, with the plugin's method checks
kept. Policy saves, `remove_user_2fa`, `unlock_account` and prefix matches are never exempt. `custom_ajax_logout` has no
registered handler in this package and is not an exception.

Ordinary administration access: the own-account enrolment screen, GET `profile.php?show=wp-2fa-setup` without an action
parameter, is permitted; other pending-user page requests are redirected there; action POSTs, `admin-post.php`
included, are refused. WordPress logout stays available.

## Criterion to test map

| AC | Scenario | What it asserts |
|---|---|---|
| AC-1 | `t42-two-factor-sign-in` | Over HTTP, on `wp-login.php` and the account page in both languages: an enrolled administrator with the password alone gets the challenge and no authenticating cookie; a computed TOTP completes sign-in; wrong codes are refused on both the HTML and REST challenge paths; HTML and REST provider failures fire `wp_login_failed`; a REST failure returns a replacement transaction nonce while retry remains available, and the retry with the right code completes sign-in |
| AC-2 | `t42-two-factor-sign-in`, `t43-two-factor-policy` | The baseline command sets the owned fields and keeps unrelated ones; a shop manager is enforced; an unenrolled customer-only account signs in with no challenge; changing the roles in WP 2FA's setting takes effect in a fresh request |
| AC-3 | `t44-two-factor-enrolment-guard` | Over HTTP, unenrolled administrator and shop-manager sessions cannot reach administration pages, admin-post actions, privileged REST with a valid nonce, or unlisted AJAX actions. Immediate access and a pre-existing session after a policy change are both tested. Enrolment is completed through the issued setup key and nonce using `validate_authcode_via_ajax`, then normal access is proven. Policy-save and reset actions are refused while pending. A mixed customer and staff role and an unenrolled administrator's application password are covered |
| AC-4 | `t45-two-factor-recovery` | A backup code completes sign-in once and fails on reuse; an administrator's reset removes the user's factor and they must enrol again; a shop manager cannot reset an administrator; the runbook's sole-administrator steps work on a disposable user |
| AC-5 | `t43-two-factor-policy` | The check passes every two-factor row on the baseline; with the plugin deactivated, `DISABLE_2FA_LOGIN=true`, a missing configuration key, a database fallback, or a leftover database key row, the matching row says review and the command exits 1; an absent bypass and `DISABLE_2FA_LOGIN=false` both pass |
| FR-008 | `t50-two-factor-issuer` | In-process, WP 2FA's QR code equals the one WP 2FA draws for "title:login" with the title as issuer, and without Mizzey's filter the one for the address; a colon is removed; a translated title changes nothing; other `site_url()` calls are untouched. Over HTTP, the set-up screen's new-key request returns the QR code for the title |
| FR-007 | `t42-two-factor-sign-in` | Home, product and ordinary account responses as a visitor and as an unenrolled customer-only user load no WP 2FA assets; staff challenge and enrolment responses are tested separately |

Programmatic enrolment prepares sign-in fixtures; it does not prove the enrolment screen or its AJAX flow. At least one
HTTP test submits the actual issued key and verification code with the plugin's nonce.

The baseline is applied by each two-factor scenario and the earlier policy restored after it, so the existing
privileged fixtures (`_workflows.php`, `t40-security-accounts.php`) run unenforced in the rest of the suite. No test user
is exempted from the guard. Global policy, activation, bypass and key tests restore state and run in their own,
sequential processes.

## Risks

| Risk | Mitigation |
|---|---|
| A plugin update changes its internals (helpers, option names) | Pinned at 4.2.1. Missing required symbols fail the check; with the plugin active and the bypass off, an inability to evaluate pending enrolment refuses protected requests. Plugin inactivity and the deliberate emergency bypass leave the guard inactive and are reported separately |
| Lock-out during tests on the shared runtime | Scenarios use their own users and never enrol `pilot_admin`; they remove their users and restore the policy |
| The application-password boundary | Documented; S-1 offered, not built |
| Staging staff must enrol on their next sign-in once the baseline is applied there | Applied on the owner's say-so after deploy; the runbook explains enrolment |

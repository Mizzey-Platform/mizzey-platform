# Feature Specification: two-factor sign-in for admin accounts

**Feature Branch**: `008-admin-two-factor`

**Created**: 2026-10-29

**Status**: Draft

**Work type**: requirement

**Input**: User description: "Start #275, admin two-factor sign-in (ADM-131), with Codex reviewing" (the owner, 29 October
2026). A slice of #275 (E-ADM-9): ADM-131 only. The mechanism is the owner's ruling of 21 October 2026 and the Technical
Design's component row 20: WP 2FA, free tier. D-21 sets the baseline roles: administrators and shop managers.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| ADM-131 | P1 | S1 | Two-factor authentication for admin accounts |

Story: Functional Specification MS-SPC-2026-032, US-21-02 (Two factor authentication for admin): "Two factor
authentication is available for admin accounts"; "It can be required for the roles the owner chooses"; "Recovery from a
lost second factor is documented and controlled".

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| ADM-130 | P1-L | Staff users, roles and permissions. Owned by #275, waits for the launch role set (#251). This slice enforces on the roles that exist: `administrator` and `shop_manager` |
| ADM-132, ADM-133, ADM-134 | P1-L, P1, P1 | The searchable log of logins and security events. Owned by #275, a later slice. Two-factor's own events (completed sign-in, failed second factor) are what that log will record |
| NFR-05 | P1 | Admin protection. Owned by #269; this slice adds to it and to `docs/security-baseline.md` |
| AUTH-15 | P1 [key] | Sign-in rate limiting, CoreX login protection. Owned by #305. WP 2FA's challenge runs after the password, so CoreX counts password failures and failed second factors on the browser path |
| D-21 | Owner standard | Two-factor sign-in for administrators and shop managers |

## Open contract items

None. The Technical Design names the component (row 20, nil cost) and its risk TR-08 (a component lagging the platform).
WP 2FA 4.2.1 declares "Tested up to: 7.1". Compatibility with our exact WordPress 7.1.4 stack remains to be verified by
this feature's runtime tests.

## Contractual acceptance criteria and engineering checks [checked]

AC-1, AC-2 and AC-4 express US-21-02's contractual outcomes. AC-3 verifies the implementation of AC-2. AC-5 is an
engineering safeguard, not an additional contractual obligation. The D-21 baseline roles are the owner's standard, not
roles fixed by ADM-131.

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | Two-factor sign-in is available for admin accounts: an administrator enrols an authenticator app with backup codes, after which a password alone gives no session, on the WordPress sign-in page and on the account page in both languages, and a valid code completes sign-in | ADM-131 | final |
| AC-2 | Two-factor can be required for the roles the owner chooses: the requirement is a setting the owner can change; the shipped baseline requires it for administrators and shop managers, and an unenrolled customer-only account is not challenged; Mizzey adds no customer enrolment surface | ADM-131 | final |
| AC-3 | A staff member who must enrol and has not yet done so can do nothing privileged until enrolled: not through the administration, the REST API or background requests | ADM-131 | final |
| AC-4 | Recovery from a lost second factor is documented and controlled: backup codes work once each; another administrator can reset a staff member's second factor; the procedure for a sole administrator who has lost everything is written down and needs server access | ADM-131 | final |
| AC-5 | One command reports whether two-factor protection is in force: the component active, the baseline in effect, no emergency bypass, the encryption key provided by configuration and absent from the database, and the staff still to enrol (an engineering safeguard supporting ADM-131, not a contractual criterion) | ADM-131 | final |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A stolen password is not enough (Priority: High)

**Independent Test**: an enrolled administrator signs in with the right password and no code, and has no session; with a
code from the authenticator, they do.

**Acceptance Scenarios**:

1. **Given** an enrolled administrator, **When** they sign in with only the password, **Then** they are asked for a
   code and hold no authenticated session (AC-1).
2. **Given** that challenge, **When** they enter a valid code or an unused backup code, **Then** sign-in completes; a
   wrong or reused code is refused (AC-1, AC-4).
3. **Given** an unenrolled customer-only account, **When** it signs in, **Then** no second factor is asked (AC-2).

### User Story 2 - New staff cannot skip enrolment (Priority: High)

**Independent Test**: a new shop manager signs in and can only reach the enrolment screen until they have enrolled.

**Acceptance Scenarios**:

1. **Given** a shop manager not yet enrolled, **When** they sign in, **Then** they are taken to enrolment (AC-2).
2. **Given** that session, **When** it opens another administration page, posts an administration action, calls a
   privileged REST route or a background request, **Then** it is refused or sent to enrolment, except the enrolment steps
   themselves and sign-out (AC-3).

### User Story 3 - The Developer can show protection is in force, and recover safely (Priority: Medium)

**Independent Test**: `wp mizzey security check` lists the two-factor rows; the recovery runbook resets a user.

**Acceptance Scenarios**:

1. **Given** the baseline, **When** the check runs, **Then** every two-factor row passes (AC-5).
2. **Given** a staff member who lost their phone, **When** an administrator resets their second factor, **Then** they
   must enrol again at next sign-in (AC-4).

### Edge Cases

- **Application passwords:** an enrolled administrator's application password authenticates the REST API without a
  second factor. That is WordPress's own behaviour, which the plugin leaves in place, and D-21 keeps application passwords
  for administrators (integrations use WooCommerce API keys). Recorded as a known boundary in the handover list; changing
  it is the owner's decision (S-1). A pending (unenrolled) administrator is refused by the guard either way.
- **A user holding both a customer and a staff role** is enforced as staff: the baseline excludes no role.
- **A former staff member who becomes customer-only** but kept an enrolment is still challenged; the runbook says to
  reset their factor when their staff role is removed.
- **Deactivating the plugin** silently drops enforcement, and the emergency bypass (`DISABLE_2FA_LOGIN`) turns it off for
  everyone; the check reports both, and the runbook limits the bypass.
- **WPML:** the challenge and the enrolment screens are reached from language-prefixed account URLs; the administration
  stays English (ADM-159).
- **CoreX login protection** (AUTH-15) records a password success before the second factor; a completed two-factor
  sign-in is a separate event (`wp_2fa_user_authenticated`), which ADM-132 will log.

## Native coverage

| Need | Provided by | Decision |
|---|---|---|
| Enrolment, TOTP, backup codes, the challenge on both sign-in pages | WP 2FA 4.2.1 free | Used as is |
| Role enforcement, grace period, admin reset of another user | WP 2FA settings and user actions | Used as is; the owner changes them in WP 2FA's own screens |
| TOTP secret encryption | WP 2FA, key from `WP2FA_ENCRYPT_KEY` | The key is provided by server configuration before the plugin first loads, never left in the database |
| Applying the baseline | WP 2FA's `WP2FA::update_plugin_settings()` | A Developer command applies it once; afterwards the setting is the owner's |
| Privileged pages, actions, REST and background requests before enrolment | Partly: WP 2FA redirects some administration pages, conditionally, and skips REST and AJAX | Mizzey refuses or redirects them until enrolment (AC-3) |
| Protection status | Not covered | Rows in `wp mizzey security check` |

## Requirements

### Functional Requirements

- **FR-001**: WP 2FA 4.2.1, free, MUST be installed and active, pinned in `stack.lock.json` (marked tested only after
  this feature's scenarios pass), and shipped in the release from wordpress.org with its checksum (the packer change is
  its own tooling pull request).
- **FR-002**: `wp mizzey security two-factor-baseline` MUST apply, through WP 2FA's own settings API, the baseline. Its
  owned policy fields are `enforcement-policy=certain-roles-only`, `enforced_roles=[administrator, shop_manager]`, empty
  `enforced_users`, `excluded_users` and `excluded_roles`, `superadmins-role-exclude=no`, `enable_totp=enable_totp`,
  empty `enable_email`, disabled `enable_passkeys`, empty `enable_passkeys_certain_roles`, `backup_codes_enabled=yes`,
  `grace-policy=no-grace-period` and `grace-policy-after-expire-action=configure-right-away`. Unrelated fields are
  preserved. It is not re-applied automatically: afterwards WP 2FA's settings remain the owner's authority (ADR-0003).
- **FR-003**: While WP 2FA requires the authenticated user to enrol and they have not, REST, administration and
  administration action requests MUST permit only the explicitly listed own-account enrolment operations and logout.
  Existing authentication errors and WP 2FA's nonce, capability and method checks MUST remain intact. The guard is
  inactive when the plugin is inactive or its emergency bypass is enabled; the check reports either condition.
- **FR-004**: `WP2FA_ENCRYPT_KEY` MUST be provisioned before WP 2FA first loads, through server configuration outside
  the repository. Staging keeps one stable key for this installation across deployments, server-config regeneration,
  reset and restore. Existing enrolled installations retain their effective key when moving it from database storage to
  configuration; verify decryption before removing the database copy. Recovery of an enrolled database requires recovery
  of its matching key.
- **FR-005**: `wp mizzey security check` MUST report, as controls: WP 2FA active at the pinned version with its hooks and
  the guard in place, an enforcing baseline policy, no emergency bypass, and the key provided by configuration with no
  database copy (presence only, never the value); and, as a fact, the staff not yet enrolled.
- **FR-006**: A recovery runbook MUST document: backup codes, an administrator resetting another user, a staff member
  leaving or becoming customer-only, and the sole administrator who lost device and codes (server access, WP 2FA's own
  reset helper, session revocation, re-enrolment), and the limits on the emergency bypass.
- **FR-007**: Ordinary catalogue and customer-account responses MUST load no WP 2FA assets. Staff second-factor
  challenge and enrolment responses are explicit exceptions.
- **FR-008** (an addition the owner asked for on 30 October 2026, not a register obligation): the entry a staff
  member's authenticator app shows MUST carry the site's title as stored ("Mizzey" on the live site, "Mizzey
  (staging)" on staging), not the site's address, with colons removed and unaffected by translation. Only WP 2FA's
  two name builders are affected; every other `site_url()` result is unchanged. An empty title leaves WP 2FA's own
  name. The app's logo is outside the site's control: authenticator apps show logos only for brands on their own
  lists.

### Key Entities

None of Mizzey's. WP 2FA keeps its policy in its options and each user's enrolment in user meta.

## Optional safeguards (not owed)

| Id | Safeguard | Why | Approval needed |
|---|---|---|---|
| S-1 | Refuse application passwords for users WP 2FA enforces | Closes the application-password path without a second factor | Yes: it reverses D-21's administrator exception and breaks the suite's harness, which uses one |

## Future items (not built)

- The searchable log of logins and security events (ADM-132 to ADM-134): the next #275 slice.
- Passkeys: present in the plugin, off; a later decision.

## Success Criteria

- **SC-001**: Sign-in, enrolment, recovery and guard request scenarios pass over real HTTP from both language contexts,
  including staging. Command scenarios run through WP-CLI. The administration remains English.
- **SC-002**: The security check passes every two-factor row on the runtime and, once deployed, on staging.
- **SC-003**: Ordinary catalogue and account responses load no WP 2FA asset.

## Delivery stage

S1, under #275. Administration only; no storefront change.

## Assumptions

- The pinned stack: WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, CoreX v0.47.0, and WP 2FA 4.2.1.
- Staff are enrolled before they get privileged access on the live site.
- On the disposable runtime the baseline is applied by the two-factor scenarios themselves and restored after them, so
  the rest of the suite runs without it; no test user is exempted from the guard.

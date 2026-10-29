# Implementation Plan: security baseline, the site-level controls

**Branch**: `007-security-baseline` | **Date**: 2026-10-29 | **Spec**: [spec.md](spec.md)

## Summary

Seven small hook-based controls in the site plugin close what WordPress, WooCommerce, WPML and CoreX leave open by
default (generator marks, XML-RPC, account discovery, application passwords, account-revealing sign-in messages,
unlimited password recovery, comment surface), and one WP-CLI check reports them. CoreX's own controls are reused,
never duplicated: its generator tag is switched off through its own filter, and the recovery limiter takes the client
address from CoreX's `ClientAddress` service, the one its login lockout uses.

## Technical Context

| | |
|---|---|
| Language | PHP 8.3, strict types |
| Runs in | WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, WCML 5.5.7, CoreX v0.47.0 (`stack.lock.json`) |
| Storage | Expiring recovery transients, in WordPress options or the object cache; no setting, table or meta of its own |
| Dependencies | None added |
| Testing | Integration scenarios in `mizzey-site/tests/integration/` (`run.py`), against the disposable runtime only, over real HTTP where a visitor's view is the claim |

## Constitution Check

| Principle | Held? | How |
|---|---|---|
| M-1 scope traced | Yes | NFR-05 only, owned by #269; AUTH-15, AUTH-16 (#305) and ADM-131 (#275) are context rows, not claimed. The policy choices rest on D-21 (PR #421) |
| M-2 contradictions | None found | |
| M-4 native first | Yes | Every control is a native, WPML or CoreX hook; the one gap with no native control (recovery limit) is the only new logic |
| M-5 three lists | Yes | Criteria, one optional safeguard (S-1, not built), future items |
| M-7 evidence and tests | Yes | Every criterion has a scenario |
| M-9 languages | Yes | Visitor-facing text in both languages: the sign-in message reuses WordPress's translated string; the recovery refusal is a new `mizzey-site` string with its Arabic translation |
| One authority (ADR-0003) | Yes | No setting another component owns is written (FR-009); CoreX login protection is reported, not configured |
| CoreX client-site mode | Yes | Only `mizzey-site/`, `specs/` and the handover list in `docs/` change |

## Design

### Components, `mizzey-site/src/Security/`

| Class | Responsibility | Hooks |
|---|---|---|
| `Disclosure` | No generator mark from WordPress, WooCommerce, CoreX or WPML (FR-001); no pingback header or RSD link (FR-002, part) | `corex_generator_tag`, `the_generator`, `wp_head` removals (WPML's through its own object, at priority 0), `wp_headers` |
| `XmlRpc` | Refuse every `xmlrpc.php` request, `?rsd` included, before dispatch (FR-002) | `init` at priority 0: when `XMLRPC_REQUEST`, answer 403 and stop; `xmlrpc_enabled` false and an empty `xmlrpc_methods` kept as a second layer |
| `AccountDiscovery` | Users routes refused to visitors (FR-003) without widening any native permission; numeric `author` queries from visitors become genuine 404s; only the `users` sitemap provider removed | `rest_pre_dispatch`: refuse `/wp/v2/users` routes (pretty or `?rest_route=`) unless the user has `list_users` or `edit_posts`, or is signed in and asks for `/users/me`; otherwise return null so the native permission callbacks decide. `request`: clear a numeric `author` and set `error=404`. `wp_sitemaps_add_provider`: false for `users` only |
| `ApplicationPasswords` | Availability, and so authentication, judged on the target user: `manage_options` required, upstream refusals preserved (FR-004) | `wp_is_application_passwords_available_for_user`, returning `$available && user_can($user, 'manage_options')` |
| `SignInMessages` | `invalid_username`, `invalid_email`, `incorrect_password` become WordPress's own `authentication_failed` message (FR-005) | `authenticate`, priority 100, after CoreX's 30. `wp_authenticate()` fires `wp_login_failed` for every error except empty credentials, so CoreX still counts the failure |
| `RecoveryLimiter` | Fixed-window counters per account and per client address; a refusal adds an error before any email (FR-006) | `lostpassword_post`, which WordPress and WooCommerce both fire before sending; limits through `mizzey_recovery_limits` |
| `CommentSurface` | Close non-product comments, return the product result unchanged, close pings everywhere (FR-007) | `comments_open` with the post id, `pings_open` |
| `BaselineReport` | Each control's state, computed by exercising its hook, plus configuration facts (FR-008) | none |
| `Cli\SecurityCommand` | `wp mizzey security check`; table output; exit 1 when a control of this feature fails | registered only under WP-CLI |

`Security::register()` wires them from `mizzey-site.php`. The client address is resolved at request time:
`Corex\Boot::booted()` then `Boot::app()->container()->make(Corex\Http\ClientAddress::class)`; if CoreX has not booted,
`REMOTE_ADDR` validated as an address. No CoreX service is touched before `plugins_loaded`.

### Recovery limits

Defaults: 3 requests per account and 10 per client address in 15 minutes, filterable; each filtered value is validated
as a positive integer, and an invalid one falls back. The account key is the resolved user id, shared across username
and email, languages and both handlers. Unknown identities count against the address only. This limits abuse; it does
not conceal account existence (reaching the account limit is observable) and native recovery messages are unchanged.
Each counter stores its count and an absolute expiry; later writes keep only the remaining lifetime, and a refused
request neither counts nor extends it. Keys are hashed with `wp_hash`. Concurrency is best-effort: two requests read in
the same instant can both pass. The refusal message: "Too many password reset requests. Please wait a few minutes and
try again."

### Translations, and one defect found on the way

`mizzey-site/languages/mizzey-site.pot` gains the refusal string; `mizzey-site-ar.po` and its generated
`mizzey-site-ar.l10n.php` carry the Arabic, for the owner to review. Building this showed that the plugin's Arabic
file was never read under an Arabic request: the plugin and the theme both register a directory for the shared
`mizzey-site` domain, WordPress keeps one, and the theme's (empty) directory was left in place. No Arabic file existed
before, so nothing showed it. `Localisation` now answers `lang_dir_for_domain` with the plugin's directory when it holds
a file for the locale, and leaves the theme's directory otherwise. The template already covers the strings of both.

## Criterion to test map

| AC | Scenario | What it asserts |
|---|---|---|
| AC-1, AC-2 | `t39-security-exposure` | As a visitor over HTTP, in English and Arabic: home, a post, a product, the shop and both feeds carry no generator mark; no `X-Pingback` header, RSD or manifest link; `system.listMethods`, `system.getCapabilities`, `system.multicall`, `pingback.ping` and `?rsd` all answer 403 with no XML-RPC result |
| AC-3 | `t39-security-exposure` | Users collection and single user, pretty and `?rest_route=`, refused to a visitor; an editor keeps the collection while the native refusal of the edit context stands (in process); `?author=` for an existing and a missing account answers 404 with no slug, in both languages; the sitemap index lists no users sitemap and still lists products; the users sitemap does not exist |
| AC-4 | `t40-security-accounts` | Availability false for a customer and a shop manager, true for an administrator; a stored password fails for a customer and works for an administrator, in process and over HTTP with Basic authentication |
| AC-5 | `t40-security-accounts` | Wrong password, unknown username and unknown email give one answer; `wp_login_failed` fires for each; CoreX's lockout error and empty credentials keep their own answers; the right password signs in; over HTTP, the same message for a real and an unknown account on `wp-login.php` and on the account page in both languages |
| AC-6 | `t40-security-accounts` | In process, with mail intercepted: on both handlers the fourth request for one account is refused with no email; username and email share one allowance; the eleventh from one address is refused; a filtered limit applies; invalid settings fall back. Over HTTP on the account page, in both languages, the fourth request reads the refusal in the visitor's language. HTTP requests go to `example.invalid` accounts and their mail is not asserted; the in-process cases carry the email claim |
| AC-7 | `t41-security-check` | Posts and pages stored as open are closed; a product with reviews on stays open and one with reviews off stays closed; over HTTP, a comment on a post is refused with 403, a product review is accepted for moderation, a trackback is refused |
| AC-8 | `t41-security-check` | The report lists every control and each passes; with a control switched off in process the report says review; `wp mizzey security check` exits 0 in its own process, and exits 1 naming the control when one is switched off in that process |

## Project Structure

```text
mizzey-site/
  src/Security/{Security,Disclosure,XmlRpc,AccountDiscovery,ApplicationPasswords,SignInMessages,RecoveryLimiter,
                CommentSurface,BaselineReport}.php
  src/Security/Cli/SecurityCommand.php
  src/I18n/Localisation.php        the plugin's translation directory first (see Translations)
  languages/{mizzey-site.pot,mizzey-site-ar.po,mizzey-site-ar.l10n.php}
  tests/integration/{t39-security-exposure,t40-security-accounts,t41-security-check}.php
docs/security-baseline.md          the handover list of controls (US-27-03)
```

## Risks

| Risk | Mitigation |
|---|---|
| The suite's harness uses an administrator application password | AC-4 keeps administrators; t02 to t29 must keep their verdicts (full suite) |
| Recovery email during tests | In process, `pre_wp_mail` intercepts and counts. HTTP requests target `example.invalid` accounts on the disposable runtime; no fixture is written under `wp-content` |
| Behind the proxy every visitor shares one address | The limiter uses CoreX's `ClientAddress`; on staging that needs the trusted-proxy setting, a server change for the owner's approval and an AUTH-15 dependency too. Until then the address limit is generous (10) and the account limit carries the protection |
| Runtime shared with other sessions | Scenarios run serially, in their own processes, and remove their users and counters |

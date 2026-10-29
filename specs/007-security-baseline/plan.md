# Implementation Plan: security baseline, the site-level controls

**Branch**: `007-security-baseline` | **Date**: 2026-10-29 | **Spec**: [spec.md](spec.md)

## Summary

Seven small hook-based controls in the site plugin close what WordPress, WooCommerce and CoreX leave open by default
(disclosure, XML-RPC, account discovery, application passwords, account-revealing sign-in messages, unlimited password
recovery, comment surface), and one WP-CLI check reports them. CoreX's own controls are reused, never duplicated: its
generator tag is switched off through its own filter, and the recovery limiter takes the client address from CoreX's
`ClientAddress` service, the one its login lockout uses.

## Technical Context

| | |
|---|---|
| Language | PHP 8.3, strict types |
| Runs in | WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, WCML 5.5.7, CoreX v0.47.0 (`stack.lock.json`) |
| Storage | Transients for the recovery counters only; no option, table or meta |
| Dependencies | None added |
| Testing | Integration scenarios in `mizzey-site/tests/integration/` (`run.py`), against the disposable runtime only, over real HTTP where a visitor's view is the claim |

## Constitution Check

| Principle | Held? | How |
|---|---|---|
| M-1 scope traced | Yes | NFR-05 only, owned by #269; AUTH-15, AUTH-16 (#305) and ADM-131 (#275) are context rows, not claimed |
| M-2 contradictions | None found | |
| M-4 native first | Yes | Every control is a native or CoreX hook; the one gap with no native control (recovery limit) is the only new logic |
| M-5 three lists | Yes | Criteria, one optional safeguard (S-1, not built), future items |
| M-7 evidence and tests | Yes | Every criterion has a scenario |
| M-9 languages | Yes | Visitor-facing text in both languages: the sign-in message reuses WordPress's translated string; the recovery refusal is a new `mizzey-site` string with its Arabic translation |
| One authority (ADR-0003) | Yes | No setting another component owns is written (FR-009); CoreX login protection is reported, not configured |
| CoreX client-site mode | Yes | Only `mizzey-site/` and `specs/` change |

## Design

### Components, `mizzey-site/src/Security/`

| Class | Responsibility | Hooks |
|---|---|---|
| `Disclosure` | No generator mark (FR-001); no pingback header or RSD and manifest links (FR-002, part) | `corex_generator_tag`, `the_generator`, `get_the_generator_*`, `wp_head` removals, `wp_headers` |
| `XmlRpc` | No XML-RPC method, no pingbacks (FR-002) | `xmlrpc_enabled`, `xmlrpc_methods`, `pings_open` |
| `AccountDiscovery` | Users routes refused to visitors without `list_users` or `edit_posts`; numeric `author` query answered as not found; users sitemap removed (FR-003) | `rest_endpoints` permission wrap, `parse_request` or `request`, `wp_sitemaps_add_provider` |
| `ApplicationPasswords` | Available only with `manage_options` (FR-004) | `wp_is_application_passwords_available_for_user` |
| `SignInMessages` | `invalid_username`, `invalid_email`, `incorrect_password` become WordPress's own `authentication_failed` message (FR-005) | `authenticate`, priority 100, after CoreX's 30; `wp_login_failed` still fires because `wp_authenticate()` fires it on any error |
| `RecoveryLimiter` | Fixed-window counters per account and per client address; refusal adds an error before any email (FR-006) | `lostpassword_post`, which WordPress and WooCommerce both fire before sending; limits through `mizzey_recovery_limits` |
| `CommentSurface` | Comments and pings closed for every post type except `product` (FR-007) | `comments_open`, `pings_open` |
| `BaselineReport` | Computes each control's state from the live hooks and the configuration facts (FR-008) | none |
| `Cli\SecurityCommand` | `wp mizzey security check`; table output; exit 1 when a control of this feature fails | registered only under WP-CLI |

`Security::register()` wires them from `mizzey-site.php`. The client address is resolved at request time:
`Corex\Boot::booted()` then `Boot::app()->container()->make(Corex\Http\ClientAddress::class)`; if CoreX has not booted,
`REMOTE_ADDR` validated as an address. No CoreX service is touched before `plugins_loaded`.

### Recovery limits

Defaults: 3 requests per account and 10 per client address in 15 minutes, filterable. The account key is the user id
when the account exists; an unknown login counts against the address only, so an attacker cannot tell the difference
from the limit. Keys are hashed with `wp_hash`. The window is fixed from the first request; a refused request does not
extend it. The refusal message: "Too many password reset requests. Please wait a few minutes and try again."

### Translations

`mizzey-site/languages/mizzey-site.pot` gains the refusal string; `mizzey-site-ar.po` and its generated
`mizzey-site-ar.l10n.php` carry the Arabic. The Arabic wording is reviewed by the owner.

## Criterion to test map

| AC | Scenario | What it asserts |
|---|---|---|
| AC-1, AC-2 | `t39-security-exposure` | As a visitor over HTTP, in English and Arabic: home, a product and the feed carry no generator mark; no `X-Pingback` header, RSD or manifest link; `xmlrpc.php` `system.listMethods` returns no method and `pingback.ping` is refused |
| AC-3 | `t39-security-exposure` | `/wp-json/wp/v2/users` and `/wp-json/wp/v2/users/<id>` refused to a visitor and allowed to an editor; `?author=<id>` gives no redirect to an author slug and no slug in the body; the sitemap index lists no users provider |
| AC-4 | `t40-security-accounts` | A customer and a shop manager: application passwords unavailable; an administrator: available |
| AC-5 | `t40-security-accounts` | Over HTTP, on `wp-login.php` and the account page in both languages: a wrong password and an unknown account give the same message; `wp_login_failed` fires for each; a CoreX lockout error passes unchanged (in process) |
| AC-6 | `t40-security-accounts` | Over HTTP on both recovery pages and both languages: the fourth request for one account within the window is refused with the refusal message and sends no email; the eleventh from one address with varied accounts is refused; the filtered limits apply; counters are removed after the scenario |
| AC-7 | `t41-security-check` | A post and a page: comments and pings closed; a product: reviews still open |
| AC-8 | `t41-security-check` | `wp mizzey security check` lists every control, passes on the baseline, reports review when a control is switched off by a filter, exits 1 then |

## Project Structure

```text
mizzey-site/
  src/Security/{Security,Disclosure,XmlRpc,AccountDiscovery,ApplicationPasswords,SignInMessages,RecoveryLimiter,
                CommentSurface,BaselineReport}.php
  src/Security/Cli/SecurityCommand.php
  languages/{mizzey-site.pot,mizzey-site-ar.po,mizzey-site-ar.l10n.php}
  tests/integration/{t39-security-exposure,t40-security-accounts,t41-security-check}.php
docs/security-baseline.md      the handover list of controls (US-27-03)
```

## Risks

| Risk | Mitigation |
|---|---|
| The suite's harness uses an administrator application password | AC-4 keeps administrators; t11 to t29 must keep their verdicts (full suite) |
| Recovery email sending during tests | The scenario intercepts `pre_wp_mail` and counts, so no mail leaves the runtime |
| Behind the proxy every visitor shares one address | The limiter uses CoreX's `ClientAddress`; on staging that needs the trusted-proxy setting, which is a server change for the owner's approval and an AUTH-15 dependency too. Until then the address limit is generous (10) and the account limit carries the protection |
| Runtime shared with other sessions | Scenarios run serially, in their own processes, and remove their users and counters |

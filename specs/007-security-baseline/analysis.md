# Analysis: security baseline, the site-level controls

The review record for T014. Three independent read-only reviews by Codex (gpt-6.1-sol, effort high), each finding
checked against the code before it was applied.

## Before the specification

A static inventory of CoreX v0.47.0's security components against NFR-05 (`3a547659`). What it established, and what
this feature does with it:

| CoreX provides | Default | Used here |
|---|---|---|
| Login lockout (identity and address, 5 in 300 s, 900 s) | Off | No: AUTH-15, #305. Reported by the check |
| Custom login address | Off | No: a language-prefix mismatch under WPML is a static risk; #305 decides |
| Challenge services (reCAPTCHA v3, Turnstile, hCaptcha, honeypot) | `none` | No: they cover CoreX forms only; WooCommerce forms are #305's |
| Generator tag | On | Switched off through `corex_generator_tag` |
| `ClientAddress` (trusted-proxy aware) | Connection address | Used by the recovery limiter |
| Hardening checklist | Advisory | Not duplicated; the check reports configuration facts beside it |

CoreX has no lost-password limit, no XML-RPC, enumeration or application-password policy, and no 2FA. Those gaps are
this feature's, except 2FA (#275).

## Specification review (FIX FIRST, applied)

Eight fixes. The material one: emptying `xmlrpc_methods` leaves the XML-RPC server's own `system.*` methods and
`?rsd`, so the endpoint is now refused at `init` before dispatch. The others sharpened the comment and ping policy,
the REST approach (additive, native permissions still decide), the recovery limiter's claims (it does not conceal
account existence; the window is absolute), application passwords judged on the target user, the test map, and the
scope statement (a slice of NFR-05, policies resting on D-21).

## Code review (FIX FIRST, applied)

| Finding | Applied |
|---|---|
| An invalid negative window became one second | Invalid values fall back to the defaults; t40 asserts the defaults |
| t41 left its post and page on the runtime | Removed in `on_finish` |
| The report replayed the whole `request` chain, where WPML keeps state | The author policy is called directly; the current user is restored in `finally` |
| The report's success line claimed more than it checks | Docblock and message now say hook checks plus selected policy behaviour |
| The users-sitemap assertion allowed a 200 | Asserts 404 |
| The review case proved insertion, not a rated review | Asserts a `review` comment with rating 5 |

Reported and not acted on, with the reason:

- Window expiry and non-extension are not exercised by a test: they would need a 15-minute wait or a clock seam. The
  logic is small and was reviewed; the limit itself is tested.
- An authenticated customer's `/users/me`, administrator application-password subroutes, and Arabic `wp-login.php`
  are not separately tested. The REST rule passes them through to native permissions by construction.

## Found while building

The plugin's Arabic catalogue was never read under an Arabic request: the plugin and the theme both register a
directory for the shared `mizzey-site` domain, WordPress keeps one, and the theme's empty one was left in place. No
Arabic file had shipped before, so nothing showed it. Fixed in `Localisation` (plugin directory first when it has the
locale's file). t21's AC-8 probe assumed no catalogue ships; it now reads the first string of the shipped catalogue,
and keeps its temporary `.mo` for a site without one.

## House rules

No em dash, no emoji, no heading below h3 in the changed files. Visitor-facing and command text is translatable
(`test_storefront_strings`); every page fetch in the new scenarios reaches the storefront guard or states its
exemption (`test_storefront_guard`).

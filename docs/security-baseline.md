# Security baseline: the controls in place

The handover list US-27-03 asks for: "The security controls are listed in the handover documentation". It names
each control, where it acts, its setting and the test that proves it. Run `wp mizzey security check` to see the state
of every control on a site. Register row: NFR-05. Specification: `specs/007-security-baseline`. Standard: D-21.

This list grows as each part of the baseline lands. It does not claim the whole of NFR-05.

## Site controls (`mizzey-site/src/Security/`)

| Control | What a visitor or attacker sees | Where it acts | Setting | Test |
|---|---|---|---|---|
| No generator mark | No WordPress, WooCommerce, CoreX or WPML version or product mark in pages or feeds | `Disclosure` | none | t39 |
| XML-RPC refused | Every request to `xmlrpc.php`, `?rsd` included, answers 403; no pingback header or discovery link | `XmlRpc`, `Disclosure` | none | t39 |
| No account discovery | Visitors cannot read the users API, resolve `?author=` numbers or read a users sitemap; editors keep the author list the block editor needs | `AccountDiscovery` | none | t39 |
| Application passwords for administrators only | Customers and shop managers cannot create or use one; WooCommerce API keys are unaffected | `ApplicationPasswords` | none | t40 |
| One sign-in failure message | A wrong password and an unknown account read the same, on both sign-in pages and in both languages; CoreX's lockout message is kept | `SignInMessages` | none | t40 |
| Password-recovery limit | After 3 requests for one account, or 10 from one address, in 15 minutes, further requests are refused and no email is sent | `RecoveryLimiter` | filter `mizzey_recovery_limits`: `account`, `address`, `window` (seconds) | t40 |
| Comments only on products | Posts and pages take no comments; product reviews are as each product sets them; trackbacks and pingbacks are off | `CommentSurface` | none | t41 |

## Provided elsewhere

| Control | Owner | State |
|---|---|---|
| Sign-in lockout | CoreX login protection, configured under AUTH-15 (#305) | Reported by the check; off until #305 |
| Spam protection on registration and forms | CoreX challenge services, under AUTH-15 (#305) | Not yet |
| Two-factor sign-in for administrators | ADM-131 (#275) | Not yet |
| Security headers, refused files, edge rate limits | Server and edge, each change on the owner's approval | Partly: see the hosting configuration |

## When something is locked out

- A visitor refused by the recovery limit waits for the window to pass (15 minutes by default).
- An administrator locked out by CoreX: `wp corex security reset-login` (CoreX's own recovery).

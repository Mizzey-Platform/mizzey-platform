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
| Two-factor sign-in for staff | Administrators and shop managers sign in with a password and an authenticator code (or a single-use backup code); a password alone gives no session. Customers are not asked | WP 2FA 4.2.1 (free); baseline applied by `wp mizzey security two-factor-baseline` | WP 2FA's own settings, the owner's | t42, t43 |
| Nothing privileged before enrolment | A staff member still to set up two-factor reaches only the set-up screen: other administration pages, actions, the REST API and background requests are refused | `TwoFactor` | none | t44 |
| Recovery | Backup codes; another administrator resets a lost factor; the sole-administrator procedure needs server access | WP 2FA; `docs/two-factor-recovery.md` | none | t45 |

**Known boundary:** an administrator's application password reaches the REST API without a second factor, as WordPress
designs it; D-21 keeps application passwords for administrators. Revoke one that is no longer needed.

## Provided elsewhere

| Control | Owner | State |
|---|---|---|
| Sign-in lockout | CoreX login protection, configured under AUTH-15 (#305) | Reported by the check; off until #305 |
| Spam protection on registration and forms | CoreX challenge services, under AUTH-15 (#305) | Not yet |
| Two-factor sign-in for administrators | ADM-131 (#275) | In place: see the site controls above |
| Security headers, refused files, edge rate limits | Server and edge, each change on the owner's approval | Partly: see the hosting configuration |

## When something is locked out

- A visitor refused by the recovery limit waits for the window to pass (15 minutes by default).
- An administrator locked out by CoreX: `wp corex security reset-login` (CoreX's own recovery).

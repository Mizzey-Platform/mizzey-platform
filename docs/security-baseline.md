# Security baseline: the controls in place

The handover list US-27-03 asks for: "The security controls are listed in the handover documentation". It names
each control, where it acts, its setting and the test that proves it. Run `wp mizzey security check` to see the state
of every control on a site. Register rows: NFR-05, ADM-131, AUTH-15. Specifications: `specs/007-security-baseline`,
`specs/008-admin-two-factor`, `specs/009-sign-in-protection`. Standard: D-21.

This list grows as each part of the baseline lands. It does not claim the whole of NFR-05.

## Site controls (`mizzey-site/src/Security/`)

| Control | What a visitor or attacker sees | Where it acts | Setting | Test |
|---|---|---|---|---|
| No generator mark | No WordPress, WooCommerce, CoreX or WPML version or product mark in pages or feeds | `Disclosure` | none | t39 |
| XML-RPC refused | Every request to `xmlrpc.php`, `?rsd` included, answers 403; no pingback header or discovery link | `XmlRpc`, `Disclosure` | none | t39 |
| No account discovery | Visitors cannot read the users API, resolve `?author=` numbers or read a users sitemap; editors keep the author list the block editor needs | `AccountDiscovery` | none | t39 |
| Application passwords for administrators only | Customers and shop managers cannot create or use one; WooCommerce API keys are unaffected | `ApplicationPasswords` | none | t40 |
| One sign-in failure message | A wrong password and an unknown account read the same, on both sign-in pages and in both languages; CoreX's lockout keeps its own message, translated by Mizzey | `SignInMessages` | none | t40, t46 |
| Password-recovery limit | After 3 requests for one account, or 10 from one address, in 15 minutes, further requests are refused and no email is sent | `RecoveryLimiter` | filter `mizzey_recovery_limits`: `account`, `address`, `window` (seconds) | t40 |
| Comments only on products | Posts and pages take no comments; product reviews are as each product sets them; trackbacks and pingbacks are off | `CommentSurface` | none | t41 |
| Two-factor sign-in for staff | Administrators and shop managers sign in with a password and an authenticator code (or a single-use backup code); a password alone gives no session. Customers are not asked | WP 2FA 4.2.1 (free); baseline applied by `wp mizzey security two-factor-baseline` | WP 2FA's own settings, the owner's | t42, t43 |
| Nothing privileged before enrolment | A staff member still to set up two-factor reaches only the set-up screen: other administration pages, actions, the REST API and background requests are refused | `TwoFactor` | none | t44 |
| Recovery | Backup codes; another administrator resets a lost factor; the sole-administrator procedure needs server access | WP 2FA; `docs/two-factor-recovery.md` | none | t45 |
| Sign-in limit | After 5 wrong passwords for one login name (or email) from one address within 5 minutes, sign-in for that pair is refused for 15 minutes, even with the right password, with a translated message. Other names and other addresses are unaffected | CoreX login protection; baseline applied by `wp mizzey security sign-in-baseline` | CoreX's own screen, the owner's: threshold, window, lockout | t46, t49 |
| Check against scripted account creation | Every place an account can be created (account page, checkout, the order-received offer, WordPress registration if opened) needs a token the page issued for it, used once, between 3 seconds and 2 hours after the page was shown, with a hidden field left empty. Otherwise no account is created and a translated message asks the person to reload | `SignupCheck`, `SignupRoutes` | none | t47, t49 |
| Sessions | WordPress's own: see "Sessions" below | WordPress | filter `auth_cookie_expiration` | t48 |

**Known boundary:** an administrator's application password reaches the REST API without a second factor, as WordPress
designs it; D-21 keeps application passwords for administrators. Revoke one that is no longer needed.

## The check against scripted account creation, and its limits

Each account-creating page carries a token signed by the site for that page, with the time it was issued and a
random value. The token is accepted once, between 3 seconds and 2 hours after issue, and only with the hidden field
empty. Its use is recorded in the database for the token's whole life, so it cannot be replayed. No outside service,
script or cookie is involved, and nothing changes on screen.

This stops form bots and requests a script sends straight to the store. It does not prove a person is present: a
script that loads the page, waits and submits as a browser would passes, once per page load. Slowing that needs a
per-address limit (after the server's trusted-proxy setting) or a challenge service such as Cloudflare Turnstile, an
outside service the owner would have to approve. Staff creating customers, and server commands, are not checked.
Token-bearing pages (account, checkout, order received) must not be served from a page cache.

## Sessions

WordPress's own behaviour, unchanged and verified by t48:

| | |
|---|---|
| Cookies | The authentication and logged-in cookies are HTTP-only; over HTTPS the authentication cookie is also Secure |
| Lifetime | Sign-in lasts 2 days, or 14 with "remember me" (filter `auth_cookie_expiration`). Without "remember me" the browser forgets the cookies when it closes; with it they expire 12 hours after the sign-in does. An account created by WooCommerce signs in with "remember me" |
| Server side | Each sign-in is a session the server keeps with the same expiry; a cookie whose session has expired or been ended no longer works |
| Signing out | Ends that session only; the person's other devices stay signed in |
| Changing the password | Every earlier cookie stops working, on every device; the person who changed it gets new cookies and stays signed in |
| Not provided | No idle timeout, and no explicit SameSite attribute on the cookies (browsers treat them as Lax) |

WooCommerce's cart cookie identifies a cart, not a sign-in. Secure cookies need the site to detect HTTPS correctly
behind the proxy and an HTTPS home address; that is checked on staging.

## Provided elsewhere

| Control | Owner | State |
|---|---|---|
| Sign-in lockout | CoreX login protection, configured under AUTH-15 (#305) | Built: see the site controls above; on a site once its baseline command has run |
| Spam protection on registration | AUTH-15 (#305) | In place: the check against scripted account creation above |
| Two-factor sign-in for administrators | ADM-131 (#275) | In place: see the site controls above |
| Security headers, refused files, edge rate limits | Server and edge, each change on the owner's approval | Partly: see the hosting configuration |

## When something is locked out

- A visitor refused by the recovery limit waits for the window to pass (15 minutes by default).
- Anyone locked out by the sign-in limit waits for the lockout to pass (15 minutes by default), or signs in from
  another address.
- An administrator who cannot wait, with server access: `wp corex security reset-login`. It is emergency recovery,
  not a per-account unlock: it turns the sign-in limit off, sets default-address hiding off, resets CoreX's sign-in
  address to `corex-login` and releases every lockout. Afterwards run `wp mizzey security sign-in-baseline` again
  and `wp mizzey security check` to confirm the limit is back on.

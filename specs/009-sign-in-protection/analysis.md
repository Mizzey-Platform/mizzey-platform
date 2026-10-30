# Analysis: sign-in protection, automated account creation and sessions

The review record for T014. Read-only reviews by Codex (gpt-6.1-sol, effort high, the last re-review medium, one
thread), each finding checked against CoreX v0.47.0, WordPress 7.1.4 and WooCommerce 11.1.0 before it was applied.

## Before the specification

What CoreX, WordPress and WooCommerce provide for AUTH-15, and what this feature does with it:

| Provided | Used here |
|---|---|
| CoreX login protection: a lockout per submitted identity and address, off by default | Yes: the baseline command turns it on with the documented settings (AC-1) |
| CoreX's custom sign-in address, switched on with the lockout | Kept but not hidden: default addresses stay served, because the custom address does not match WPML's language prefix and staff and the two-factor challenge use `wp-login.php` |
| CoreX's challenge services (CAPTCHA, honeypot) | No: they protect CoreX forms only, not WooCommerce's |
| A check against scripted account creation on WooCommerce routes | Not provided: Mizzey's signup check (AC-2) |
| WordPress sessions: HTTP-only cookies, 2 or 14 days, sign-out and password change invalidation | Used as is, documented and verified (AC-3) |

## Specification review, round 1 (FIX FIRST, applied)

Twelve fixes. The material ones:

- **Account writers.** `woocommerce_registration_errors` covers callers of `wc_create_new_customer()`, not every account writer; the route is now identified from the native handler running, and unknown public callers fail closed. Staff keep native authorisation: WooCommerce customer creation needs `create_customers`, not `create_users`.
- **Checkout timing.** The checkout check must reject before the Store API handler creates or updates an order, not at account insertion.
- **The after-purchase form** is drawn in the browser, so its fields are added by a script.
- **Threat model.** The token proves server issuance for a route and an age, not a person; this is now stated, with the residual risk and the optional safeguards.
- **The rest.** The CoreX bucket is described as identity and address, and the custom address's URL filters are tested as browser flows. Lockout cleanup no longer uses the global reset. The session facts were corrected (cookie persistence, the Secure condition, password-change replacement cookies), the test map was widened, and the criterion statuses fixed.

## Specification review, round 2 (FIX FIRST, applied)

Eight fixes: the single-use claim is one atomic database insert, not a transient a cache could drop; the checkout
predicate uses WooCommerce's filtered checkout settings; the order-received context wraps that block's render callback,
not a `pre_render_block` window that can stay open; one permit authorises one insertion for its own email; the classic
checkout's still-registered handlers are reported as verified closed rather than inactive; the status row accepts the
owner's later settings; and the cache and proxy wording was corrected.

## Code review (FIX FIRST, applied)

| Finding | Applied |
|---|---|
| An account-page permit survived a later validation failure in the same request | WooCommerce's registration handler runs inside a wrapper whose permits end when it returns; a submission already refused spends no token. t47 regression; mutation caught |
| A thrown REST callback could leave a frame, or a permit, behind | The Store API checkout handler is wrapped and its permit ends with it; `restBefore` removes its frame if its own verification throws. Narrowed from wrapping every endpoint: a permit exists only in a checkout frame, and an orphan neutral frame can only refuse. The re-review accepted the narrowing |
| The checkout had no real trap field, and the script always sent it empty | A hidden text trap is added to the checkout and the offer and its value is sent; t49 fills it in the browser and the server refuses. Mutation caught |
| `sign_in_limit` passed without CoreX's failure recorder | The row requires both the lockout check and the recorder |
| The offer's inventory row inferred the wrapper from a hook | It reads the installed render callback |
| The self-test could leave a claim behind on an exception | Cleanup in `finally` |
| t48's Secure assertion followed the CLI process and never failed | It follows the target's scheme and fails over HTTPS when either cookie lacks Secure |
| t47 left a page; its exception assertion could not detect a leak; the retry case lacked a real retry order | Page removed; the assertion posts the attempted email; a real `checkout-draft` order is placed in the visitor's Store API session, left unchanged by a refused request and then placed by a valid one under the same id |

Re-review: READY.

## Found while building

- **WooCommerce 11.1.0 changed two things the plan assumed.** Egypt's states are ISO codes (`EG-01` to `EG-28`), so `EGC` fails address validation. And `GET /checkout` no longer creates a draft order.
- **Test judgement.** WooCommerce redirects customers away from `profile.php`, so sign-in for customers is judged on the account page. An early version of t48 passed one case for that wrong reason; each session case now has a positive control.
- **Reset keys.** Signing in, or asking for a new reset link, ends an earlier reset key, so the browser test visits the reset link first.
- **Headless clicks.** In headless Chromium, a pointer click on WooCommerce's Register button submitted nothing while the form itself submits normally. The browser test submits it with Enter and says so.

Mutation: five deliberate defects, each caught by its scenario (`evidence/mutation.txt`).

Reported and not acted on, with the reason:

- An orphan neutral frame left by a caught exception in some other REST endpoint could refuse a later anonymous account creation in the same PHP request. No such flow exists in the checkout; it fails closed.
- The checkout and the after-purchase offer need JavaScript; both already do.
- Cache behaviour and Secure cookies are verified on staging (T017), not on the HTTP runtime.

## House rules

No em dash, no emoji, no heading below h3 in the changed files. Visitor-facing text is translatable
(`test_storefront_strings`) and Arabic is supplied for the lockout message, the signup message and the trap's label; the
catalogue template is regenerated with its header kept. No external request, script or cookie is added.

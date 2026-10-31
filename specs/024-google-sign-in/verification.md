# Verification: Google sign-in

Three states, kept apart (constitution M-7): technically verified, verified on staging, accepted by the client. Only
the first is claimed here.

## 1. Technical verification, 31 October 2026, development runtime

Stack: WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, WCML 5.5.7. Google's token endpoint mocked in process; no
request reaches Google.

| Evidence | What it shows |
|---|---|
| t65 at the final commit | Passes |
| `evidence/mutation.txt` | Deliberate defects, each caught by t65 |
| `analysis.md` | The read-only review rounds, the last READY |

| AC | Result in t65 |
|---|---|
| AC-1 | Pass. Off until configured; [HTTP] the start's redirect, state cookie, nonce and PKCE; [HTTP] a callback without the cookie refused and its state spent; the ID token's issuer, audience, azp, expiry and nonce; the exchange with the verifier; the callback makes a new account through the real sign-up check, issues the sign-in cookie and returns to where it began |
| AC-2 | Pass. A new Google user is one account, its guest order linked; again, the same account; an existing customer's email signs in to it, a never-verified one's password replaced and sessions ended; an email Google is not the authority for, staff, and an unverified email sign in to nothing |

## 2. Staging

Not done. On the owner's approval, with a developer test OAuth client.

## 3. Client acceptance

Not done. Through MS-UAT-2026-027, with Stage 3 after Stage 2 (D-22), with the client's own OAuth client.

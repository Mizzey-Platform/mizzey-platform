# Verification: accounts

Three states, kept apart (constitution M-7): technically verified, verified on staging, accepted by the client. Only
the first is claimed here.

## 1. Technical verification, 31 October 2026, development runtime

Stack: WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, WCML 5.5.7.

| Evidence | What it shows |
|---|---|
| `evidence/final-suite.txt`, `final-suite.json` | The whole suite, serially, at `3a8305c` on `019-checkout`, the top of the Stage 3 stack, which holds this branch's code to its seventh review (`e1fdf73`), on the development runtime as it stood (not a fresh baseline reset): 55 scenarios, 5 failed. t58 passes. See the note below |
| t58 at the final commit | Passes, twice in a row. The only change after `e1fdf73` is two markup format strings written with plain placeholders, as the storefront strings check reads them |
| `evidence/mutation.txt` | Twenty-two deliberate defects, each caught by t58 |
| `analysis.md` | Seven read-only review rounds |
| `python -m unittest discover -s tools/tests -t .` | 422 tests pass |

**The five failures.** `t17` and `t25` fail because of eleven product records ("t11 B1-...", ids 56486 to 56496) that an
interrupted t11 run left in the development runtime on 31 October (see specs/013-launch-roles/verification.md). `t47`,
`t49` and `t52` checked out with Egyptian addresses without a district and without a stock source, which the later
slices of the stack require (specs/018 and specs/019); those tests are updated there and pass. None touches this
branch's code.

| AC | Result in t58 |
|---|---|
| AC-1 | Pass. The registration form requires only email and password; the mobile number and consent are optional, the consent unticked |
| AC-2 | Pass. The number kept in its local form; a malformed one refused; signing in with any spelling; claimed by one account across every writer; older numbers claimed once, shared ones reserved; a username spelled like a number does not win |
| AC-3 | Pass over HTTP. Sign-in starts a session; an unknown email and a wrong password get the one generic message |
| AC-4 | Pass. A reset key works once and is refused when expired |
| AC-5 | Pass. The verification email is sent at registration; the account is unverified until the key is used |
| AC-6 | Pass. Registering applies the template's welcome discount |
| AC-7, AC-10 | Pass over HTTP. A guest order, then a password alone on the confirmation page: the account carries name, phone and address and holds the order |
| AC-8 | Pass. The guest's earlier order stays unlinked until the email is verified |
| AC-9 | Pass. No consent recorded unless ticked; ticked, kept with when and where |
| AC-11 | Pass, in-process and over HTTP. One link by email, single-use, expiring, refused for staff, bound to the password, one winner of two; the same answer for every email, sent after it; links and resets share one limit; opening asks to confirm in the visitor's own session |

## 2. Staging

Not done. On the owner's approval, with the next release; it confirms PHP-FPM sends the link request's answer first.

## 3. Client acceptance

Not done. Through MS-UAT-2026-027, with Stage 3 after Stage 2 (D-22).

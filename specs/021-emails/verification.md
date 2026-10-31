# Verification: customer emails

Three states, kept apart (constitution M-7): technically verified, verified on staging, accepted by the client. Only
the first is claimed here.

## 1. Technical verification, 31 October 2026, development runtime

Stack: WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, WCML 5.5.7.

| Evidence | What it shows |
|---|---|
| t62 and t61 at the final commit | Pass, on the development runtime as it stood (not a fresh baseline reset). The whole suite runs with the Stage 3 stack before release |
| `evidence/mutation.txt` | Deliberate defects, each caught by t62, one shown equivalent |
| `analysis.md` | The read-only review rounds |

| AC | Result in t62 |
|---|---|
| AC-1 | Pass. The new account email names the welcome discount and its code; an expired code is not advertised |
| AC-2, AC-7 | Pass. A placed order's confirmation with its number, for a customer who declined marketing |
| AC-3 | Pass. The failed order email links to paying again |
| AC-4, AC-8 | Pass. One Shipped email with carrier and tracking; none on a repeat; an Arabic order's in Arabic from an English request, the language restored |
| AC-5 | Pass. The delivered confirmation; the invitation is t61's |
| AC-6 | Pass. The refunded order email |
| AC-9 | Recorded: the sending service and its domain authentication are #284's |

## 2. Staging

Not done. On the owner's approval: each event triggered and the email received in English and Arabic.

## 3. Client acceptance

Not done. Through MS-UAT-2026-027, with Stage 3 after Stage 2 (D-22).

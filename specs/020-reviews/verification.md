# Verification: product reviews

Three states, kept apart (constitution M-7): technically verified, verified on staging, accepted by the client. Only
the first is claimed here.

## 1. Technical verification, 31 October 2026, development runtime

Stack: WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, WCML 5.5.7.

| Evidence | What it shows |
|---|---|
| t61 at the final commit | Passes, on the development runtime as it stood (not a fresh baseline reset). The whole suite runs with the Stage 3 stack before release; this branch adds one scenario and changes no shared path but product comments |
| `evidence/mutation.txt` | Deliberate defects, each caught by t61, one shown equivalent |
| `analysis.md` | The read-only review rounds |
| `python -m unittest discover -s tools/tests -t .` | 422 tests pass |

| AC | Result in t61 |
|---|---|
| AC-1 | Pass. Eligible on a delivered order; not on processing, as a guest, on a line refunded in full by quantity or by amount, or for another product; a delivered line that cost nothing counts; the form post refuses the ineligible and a second review; a review without a rating refused on the form and REST; REST reviews keep their rating; WooCommerce's REST create and update refused without moderation rights; the form shows the reason in its place |
| AC-2 | Pass. A delivered customer's review is held, a returning commenter's too |
| AC-3 | Pass. The average is 0 while held and 4 once approved |
| AC-4 | Pass. One invitation to the billing email, linking the reviews; none on a second delivery; an Arabic order's in Arabic |

## 2. Staging

Not done. On the owner's approval, with the next release.

## 3. Client acceptance

Not done. Through MS-UAT-2026-027, with Stage 3 after Stage 2 (D-22), and, for who may review, the client's signed
Stage 1 Approval Record (D-17).

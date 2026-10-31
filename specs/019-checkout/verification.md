# Verification: checkout

Three states, kept apart (constitution M-7): technically verified, verified on staging, accepted by the client. Only
the first is claimed here.

## 1. Technical verification, 31 October 2026, development runtime

Stack: WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, WCML 5.5.7.

| Evidence | What it shows |
|---|---|
| `evidence/final-suite.txt`, `final-suite.json` | The whole suite, serially, at `3a8305c`, before the first review of this branch, on the development runtime as it stood (not a fresh baseline reset): 55 scenarios, 5 failed. See the note below |
| t60, t47, t49 and t52 at the final commit | Pass. t60 is this feature's; t47, t49 (the block checkout in a real browser) and t52 check out with an Egyptian address and now give it its district |
| `evidence/mutation.txt` | Deliberate defects, each caught by t60, and two shown equivalent |
| `analysis.md` | The read-only review rounds |

**The five failures.** `t17` and `t25`: the eleven leftover product records (see specs/013-launch-roles). `t47`, `t49`
and `t52`: their checkouts had no district, which this branch requires; updated here, and they pass.

| AC | Result in t60 |
|---|---|
| AC-1 | Pass. Governorate required, postcode and second line hidden; district and landmark for an Egyptian address only; [HTTP] an order keeps its district; paying an existing Egyptian order without one is refused; [HTTP] paying an existing order through the Store API keeps the district sent with it |
| AC-3 | Pass over HTTP. Shipping rates with costs, cash on delivery, the totals |
| AC-4 | Pass over HTTP. The note kept |
| AC-6 | Pass over HTTP. A missing district refused with a message naming it, in English and Arabic |
| AC-7 | Pass, against a mock and the development fixture (pending PRE-09). WooCommerce's order stock writers change nothing; each line reserved once; released once; a refusal undoes the others and a retry reserves; Store API and classic placements refused before payment; an edited order released by its record and resized when reserved again; an unanswered reserve re-sent under its key; a paid order reserved late or marked; trashing releases; the visitor's own reservation counts at the cart check; a quantity changed back and forth resized under new keys; a crash after a reserve, an undo or a release recovered from the record; an unanswered resize not counted as reserved; a second payment from a stale record keeps the first's reservation; a release settles an unanswered reserve first; [HTTP] an order reserved with the fixture, WooCommerce's stock untouched; no source, refused |
| AC-8 | Pass. A later price change leaves the item price |
| AC-2, AC-5 | Native (WooCommerce's saved address and block checkout) |

## 2. Staging

Not done. On the owner's approval: the fixture and cash on delivery switched on there, a test order in both languages.

## 3. Client acceptance

Not done. Through MS-UAT-2026-027, with Stage 3 after Stage 2 (D-22).

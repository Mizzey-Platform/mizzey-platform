# Verification: stock in the admin

Three states, kept apart (constitution M-7): technically verified, verified on staging, accepted by the client. Only
the first is claimed here, for the function layer. AC-1 and AC-2 stay pending PRE-09: the ERP mechanism that writes
the stock is not built.

## 1. Technical verification, 31 October 2026, development runtime

Stack: WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, WooCommerce Multilingual 5.5.7, PHP 8.3, MySQL 8.3.

| Evidence | What it shows |
|---|---|
| t67 at the final commit | Passes |
| `evidence/mutation.txt` | 20 deliberate defects, each caught by t67 |
| `analysis.md` | Five read-only review rounds, the last READY |
| The regression set | t12 to t14, t16, t18, t24, t26, t47, t51 to t53, t56, t59 to t61 and t64 pass; t15 passes or fails between runs on leftover records, as it does on main |

| Item | Result in t67 |
|---|---|
| FR-001 | Pass. A product's and a variation's save keep their stock settings and the rest of the edit; a refused 0 moves neither the visibility term nor the lookup row; a new product stores WooCommerce's defaults only; a bulk edit keeps its price, its answer and object holding the stored figure |
| FR-002 | Pass. The metadata API by key, by meta id and for every object, keys in another case or with a trailing space, raw updates and deletes by key, plain or quoted meta ids, an OR-ed condition, a multi-row insert, and WooCommerce's direct stock update change no stored stock and announce nothing; a value that reads like a stock key, the lookup rebuild and WPML's duplicate (price kept) go through; a deleted product leaves no rows |
| FR-003 | Pass. 403 for v3, v2, a nested variation, a batch, a delta and a v1 nested variation; 201 for a create and 200 for a rename without stock |
| FR-004 | Pass. The list and both editors show the source's figure (7, not the stored 12), read once, or that it is not known; the lock is loaded on the product screen |
| FR-005 | Pass. The projection writes the stock and brings the variable parent back in stock; a copy loaded before it and saved after it does not undo it |
| FR-006 | Pass. The three new messages are in the Arabic catalogue |

## 2. Staging

Not done. On the owner's approval.

## 3. Client acceptance

Not done. AC-1 and AC-2 are accepted with the ERP mechanism, after PRE-09 (#243).

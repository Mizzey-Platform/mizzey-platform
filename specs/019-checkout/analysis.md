# Analysis and review record: checkout

Written and built by Claude Code (lead). Codex (gpt-6.1-sol, read-only) reviewed as a second opinion. Every finding
was checked against WooCommerce 11.1.0's source before it was applied, and each fix has a t60 check that fails when the
fix is removed (`evidence/mutation.txt`), unless the record says why it cannot have one.

| Round | Verdict | Findings, and what was done |
|---|---|---|
| 1 | FIX FIRST | Four High, five Medium. Pay-for-order and payment callbacks bypassed the placement hooks: pay-for-order reserves before the gateway charges, and an order reaching a paid status without a reservation is reserved then, or marked for staff with a note when refused (a payment already taken cannot be refused). A failed order released, then retried, was taken as reserved; a reserve the source applied but never answered was asked again under a new key; an order edited after placement released its new quantity: the order keeps a reservation record per line (stock, quantity, key generation, state), a retry re-sends an unanswered reserve's key, a line undone or released asks with new keys, and releases use the record. A retried checkout was refused for the visitor's own reservation: it counts as theirs at the cart check. Trashing or deleting an unpaid order left its stock held: released first. A refused classic placement kept its coupons held: released. The district was required for every country: for an Egyptian address only. The HTTP refusal test could pass with the reservation hook removed: the hook is now checked with a source that answers stock but refuses the change |

Known limits, recorded in the specification: an undo or a release the source does not answer is marked unsettled for
reconciliation with the ERP (PRE-09); a paid order the source refuses is marked for staff rather than refused.

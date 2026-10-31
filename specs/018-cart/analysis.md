# Analysis and review record: cart

Written and built by Claude Code (lead). Codex (gpt-6.1-sol, read-only) reviewed as a second opinion. Every finding
was checked against WooCommerce 11.1.0's source before it was applied, and each fix has a t59 check that fails when the
fix is removed (`evidence/mutation.txt`).

| Round | Verdict | Findings, and what was done |
|---|---|---|
| 1 | FIX FIRST | One High, seven Medium. Signing in again on another device, with no guest cart, added the account's cart to itself: the merge runs only in the request where WooCommerce moved a guest session to the account. The merge capped by WooCommerce's own stock field: only the stock the source confirms caps it, shared by the lines of one stock item, with the one-at-a-time cap its own. Moving a cart line to the wishlist could be forged from another site with a guest's cookies: it needs a nonce tied to the visitor's session, given on the Store API cart. A free rate on offer counted as free shipping while a paid one was chosen: the chosen rate decides. An item target was promised where no free shipping method takes a promotion: not any more. WooCommerce's own order stock writers changed what the fixture answered: they are off here (LocalStockWriters), not only with checkout. Two requests could both take the fixture's last unit: its ledger is locked |
| 2 | FIX FIRST | Four Medium. The fixture could read a cached "no ledger yet" inside its lock: the caches are cleared there. A ledger write that failed was answered as done: it is unknown. The item target was promised beside a code that keeps automatic promotions out: not any more. WooCommerce's own stock checks still read its stock field: kept, as they are stricter than the adapter and never looser; recorded as a known limit |
| 3 | FIX FIRST | Two Medium. An item sold one at a time could be kept once per language record: one for the item across its lines. The item target was promised while another automatic promotion stood in the cart with promotions not combining, which would win instead: not then |
| 4 | FIX FIRST | One Medium. Another automatic promotion that becomes valid only at the item target could win there instead: with promotions not combining, no item target is promised while the store runs another automatic promotion |
| 5 | FIX FIRST | One Medium. With promotions combining, an individual-use automatic promotion could still win at the target: no item target is promised while an automatic promotion the free shipping cannot stand beside is running |

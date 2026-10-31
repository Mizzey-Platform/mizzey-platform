# Analysis and review record: launch business rules

Written and built by Claude Code (lead). Codex (gpt-6.1-sol, read-only) reviewed as a second opinion. Every finding
was checked against WooCommerce 11.1.0's source before it was applied, and each fix has a t57 check that fails when the
fix is removed (`evidence/mutation.txt`).

| Round | Verdict | Findings, and what was done |
|---|---|---|
| 1 | FIX FIRST | The welcome discount was not bound to its owner, could be claimed again by a recreated account, and ignored the account's own email for the first-order check: it is now owned, restricted to the customer's email, and claimed once per email. One promotion per order was enforced on the classic cart only: now also on orders and through the Store API, which replaces a code as the classic cart does. Automatic promotions were valued without the entered codes, and brand conditions were not copied to the welcome code. A refund could exceed what a line paid after discounts: a per-line ceiling |
| 2 | FIX FIRST | Four Medium. Individual-use coupons could be stacked on an order with combining on: `Combination::conflicts()` checks both ways under either setting. Automatic discounts were ranked without the entered codes' allocations, and free shipping was valued without the chosen discount: both simulated together. Concurrent registrations could both claim one welcome discount: the claim became atomic |
| 3 | FIX FIRST | Four Medium. The simulation applied coupons in a different order from WooCommerce's sequential discounts: now in WooCommerce's order (`sort_coupons_callback()`). Individual-use free shipping was valued beside a discount it cannot join: valued alone. A cart could keep codes made individual-use after they were applied: reconciled with the current boxes. The claim used `add_option()`, which upserts: now an insert that only one request can win. A candidate is credited only with what it adds to the entered codes |
| 4 | FIX FIRST | Three Medium. The free shipping minimum read the cart's live discounts, which an applied automatic promotion changes under sequential discounts, so the choice could alternate between calculations: it reads the simulated discounts. An entered free-shipping code was ignored in the shipping estimates: counted. An individual-use automatic promotion could outrank a compatible one beside an entered code and leave neither applied: it is not a candidate then |
| 5 | FIX FIRST | Two Medium. Free shipping was paired only with the best discount alone, so a larger discount that wins with free shipping was missed: every joinable discount is paired. The paid saving applied tax rounding to the difference of two discounts, crediting a candidate with the entered codes' rounding: it is what the customer pays without the candidate less what they pay with it |
| 6 | FIX FIRST | One Medium. A discount that loses money alone (it takes the cart under a free shipping minimum) was dropped before it was paired with free shipping, though the pair saves money: discounts are kept for pairing, and a positive saving is required of the whole option |

Recorded in the specification: a one-piaster tie can remain when tax is rounded at subtotal and shipping is taxed
(specs/011, known limit).

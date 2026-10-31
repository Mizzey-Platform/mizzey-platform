# Analysis and review record: promotion engine

Written and built by Claude Code (lead). Codex (gpt-6.1-sol, read-only) reviewed as a second opinion over eleven
rounds. Every finding was checked against WooCommerce 11.1.0's source before it was applied, and each fix from round 5
on has a t52 check that fails when the fix is removed (`evidence/mutation.txt`).

| Round | Verdict | Findings, and what was done |
|---|---|---|
| 1 | FIX FIRST | Eight findings: the cap patched items instead of lowering the effective amount; capped promotions on existing orders; free shipping valued at nothing; guest per-customer checks; the cache not cleared on every coupon change; and others. Applied (`0769f37`) |
| 2 | FIX FIRST | Six: the cap is now measured on a simulated calculation; the checkout's usage aliases (account, its email, the billing email, a guest's accounts); free-shipping value rules; provenance of the promotions this class applied; the switch-off behaviour. Applied (`385e1c2`) |
| 3 | FIX FIRST | Three: value is the customer's saving measured in isolation, so the promotion applied now never changes the comparison; a promotion that makes the customer pay more is not applied; email list union. Applied (`4be8651`) |
| 4 | FIX FIRST | Three: paid rates measured on the candidate's own cart; the discount's tax for a free shipping minimum measured line by line; email lists matched regardless of letter case. Applied (`2049149`) |
| 5 | FIX FIRST | The winner compared the discount before tax. Now the cut in what the customer pays, the discount's tax included, whatever the display setting (`a9c6c5d`) |
| 6 | FIX FIRST | Lines priced as WooCommerce's totals discount them (a tax-exempt customer's price entered with tax); shipping counted with its tax (`90f2c41`) |
| 7 | FIX FIRST | Rebuilt prices drifted by a fraction of a cent and could change which item a one-item promotion falls on; rebuilt from the unrounded tax (`4a8ee94`) |
| 8 | FIX FIRST | The discount was rounded before the free shipping minimum; fractions of a cent kept until the remaining total is rounded, as WooCommerce does (`f35cc0b`) |
| 9 | FIX FIRST | A false tie when tax is rounded per line; the removed tax is the line's rounded tax before less after (`9f0b753`) |
| 10 | FIX FIRST | Round 9's rounding reached the free shipping minimum; the rounded saving is kept apart from it. Subtotal tax rounding handled the same way for the saving. Both checked against WooCommerce's own totals and free shipping decision (`c0ee3a6`) |
| 11 | Recorded limit | A one-piaster tie when tax is rounded at subtotal and shipping is taxed. Not applied: neither setting is the store's, and the customer's difference is at most one piaster. Recorded in the specification (`eebafaf`) |

Accepted and recorded in the specification: a paid shipping rate that depends on the cart's discounted value is not
modelled (the store's rates are fixed per governorate); the one-piaster limit of round 11.

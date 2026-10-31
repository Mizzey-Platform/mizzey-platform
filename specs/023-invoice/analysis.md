# Analysis: the invoice

Read-only reviews by Codex (gpt-6.1-sol, effort high), each finding checked against WooCommerce 11.1.0's and WooCommerce
Multilingual's behaviour before it was applied, and each fix given a t64 check that fails when the fix is removed
(`evidence/mutation.txt`), unless the record says why it cannot have one.

| Round | Verdict | Findings, and what was done |
|---|---|---|
| 1 | FIX FIRST | One High, three Medium. Numbering failed open (a counter that did not move still handed out its number; a failed read became 1): it fails closed, the number kept only once the counter has moved past it, and a number another order holds skipped. The subtotal followed the viewer's currency precision: the lines' stored subtotals are summed. The other-customer check reused the buyer's nonce: it uses a nonce of the intruder's own. The expected amounts came from the invoice's formatter: independent numbers, a fee, a refund apart from the discount |
| 2 | FIX FIRST | Two Medium. WooCommerce Multilingual filters the decimals option too: the decimals are read from the database for the order's own currency. A trashed order's number could be reissued: trashed orders are checked |
| 3 | FIX FIRST | One Medium. A failed check for a number already held looped under the numbering lock: it stops issuing at once, and the loop is bounded |
| 4 | READY | No High or Medium findings |

Limits, recorded: two requests at once cannot be made in one PHP process, so the numbering lock has no concurrency
test; a database write failure is checked by its read-back, not simulated.

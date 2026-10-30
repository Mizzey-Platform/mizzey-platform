# Analysis and review record: order status model

Written and built by Claude Code (lead). Codex (gpt-6.1-sol, read-only) reviewed as a second opinion, five rounds,
until READY. Every finding was checked against WooCommerce 11.1.0's source before it was applied.

| Round | Verdict | Findings, and what was done |
|---|---|---|
| 1 | FIX FIRST | Drafts bypassed validation; late payment and refunds after cancellation blocked while payment side effects ran; customers would see COD Collected and get the completed email late; cash orders could skip COD Collected; creation skipped the cancellation reason; `woocommerce_order_edit_status` fires before save; stale objects; payment changes had no transition note; Payment Failed label; report status lists; test coverage. All applied: drafts limited, Cancelled may go to Confirmed or Refunded, customer view and email rule, cash route, reasons on creation, list-button pre-check and a documented caveat, storage-based checks, a supplementary note, labels, the legacy report filter, a rebuilt t51 |
| 2 | FIX FIRST | Stale rollback could erase dates and still fire hooks; creation bypassed the cash route; refused cash payment completion still ran payment side effects; REST creation returned 201 for a status the guard then undid. Applied: stale copies refused outright with dates restored from storage, the cash route at creation, cash payment completion steered to Confirmed, starting statuses limited |
| 3 | FIX FIRST | The new-order rule was skipped on save; the cash override also ran inside the paid-date check; a message listed the wrong statuses. Applied |
| 4 | FIX FIRST | A payment mark could leak when payment completion skipped or aborted. Applied, then replaced in round 5 |
| 5 | READY | The mark was removed: the override runs only once payment completion has set the paid date |

Recorded limits: `woocommerce_order_edit_status` fires inside a manual `set_status()` before the save, so a refused move
made by code with `$manual = true` has already fired it; every staff entry point is checked first. The stale-copy check
reads storage just before the save, which closes the out-of-date object case but is not a database lock.

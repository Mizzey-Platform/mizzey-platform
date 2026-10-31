# Analysis and review record: the account area

Written and built by Claude Code (lead). Codex (gpt-6.1-sol, read-only) reviewed as a second opinion. Each fix has a
t63 check that fails when the fix is removed (`evidence/mutation.txt`), unless the record says why it cannot have one.

| Round | Verdict | Findings, and what was done |
|---|---|---|
| 1 | FIX FIRST | Three Medium, two Low. Two changes at once could overwrite each other's address: each change runs under a lock per customer. Deleting the default left none: the address left becomes the default and the checkout copy follows. The tracking stopped at Shipped for a failed delivery and left out the unhappy statuses: every status the order recorded is shown. Dates showed in UTC: in the store's time. The REST check of the nonce was not tested over HTTP: a request with the customer's cookies and no REST nonce is refused |
| 2 | READY | No High or Medium findings |
| 3 | READY | After the full suite's information-architecture check found the account's Wishlist at a second route (G-1 of specs/003), the menu item opens the storefront's Wishlist page instead: no High or Medium findings |

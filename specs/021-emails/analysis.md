# Analysis and review record: customer emails

Written and built by Claude Code (lead). Codex (gpt-6.1-sol, read-only) reviewed as a second opinion. Every finding
was checked against WooCommerce 11.1.0's source before it was applied, and each fix has a t62 check that fails when the
fix is removed (`evidence/mutation.txt`), unless the record says why it cannot have one.

| Round | Verdict | Findings, and what was done |
|---|---|---|
| 1 | FIX FIRST | One High, two Medium. The Shipped mark could be read from a cached order while another request sent it: read from storage under the lock. An expired or used-up welcome code was still advertised: only a usable one. The retry address was text, not a link: a link whose text is the address, so the plain text email keeps it |

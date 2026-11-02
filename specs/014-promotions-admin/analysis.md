# Analysis and review record: promotions managed from admin

Written and built by Claude Code (lead). Codex (gpt-6.1-sol, read-only) reviewed as a second opinion, two rounds.

| Round | Verdict | Findings, and what was done |
|---|---|---|
| 1 | FIX FIRST | Pause as WordPress's Draft status was unsound: WooCommerce records and reverses a use by looking the coupon up by code, which finds no draft, so counts drift while paused; a code that is the coupon's own number still loads as a draft; and the test flushed caches and faked a use. Redesigned: a Paused box keeps the coupon published and is refused in WooCommerce's own validity check; the test pauses through the coupon screen, records a use from a real order, reverses it by cancelling while paused, and covers a numeric code. An order that already has the promotion keeps it, also when its coupons are recalculated: recorded in the specification |
| 2 | READY | No High or Medium findings |

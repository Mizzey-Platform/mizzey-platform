# Tasks: cart

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Each task names its test. `t59` is `mizzey-site/tests/integration/t59-cart.php`.

| Id | Task | Test |
|---|---|---|
| T001 | `CartStock`: every line asked of the gateway, failing closed | t59 AC-7 |
| T002 | `FixtureStockSource`: the non-production stand-in, read-only | t59 AC-7 |
| T003 | `CartMerge`: every sign-in marks the merge; combine, cap, save, tell | t59 AC-6, AC-9, AC-10, AC-13 |
| T004 | `GuestCartRetention`: the window as a setting | t59 AC-11 |
| T005 | `FreeShippingProgress` on the Store API cart | t59 AC-4 |
| T006 | `CartSuggestions` from the Upsells field | t59 AC-5 |
| T007 | `Wishlist::fromCart()` and its route | t59 AC-2 |
| T008 | Wire into `mizzey-site.php`; Arabic for every customer-facing message | t59, t39 |
| T009 | Write `t59-cart`, the native rows included | itself |
| T010 | Full suite; mutation checks; Codex review | suite |
| T011 | Staging, on the owner's approval: the fixture switched on, t59 on staging | `verification.md` |
| T012 | Client acceptance through MS-UAT-2026-027, with Stage 3 after Stage 2 (D-22) | `verification.md` |

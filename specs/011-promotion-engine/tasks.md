# Tasks: promotion engine

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Each task names its test. `t52` is `mizzey-site/tests/integration/t52-promotion-engine.php`.

| Id | Task | Test |
|---|---|---|
| T001 | `PromotionSettings`: read the five settings; coupon screen fields; save after WooCommerce's nonce check; start after expiry refused | t52 AC-8, AC-5 |
| T002 | `PromotionRules`: start date, minimum items, first order only, capped promotions refused on existing orders; translated reasons | t52 AC-2, AC-5, AC-7 |
| T003 | `DiscountCap`: running total per promotion, capped per item | t52 AC-7 |
| T004 | `AutomaticPromotions`: measure, choose (Q1), apply and remove; cache | t52 AC-3 |
| T005 | Arabic for the refusal messages | t52 AC-2 |
| T006 | Wire into `mizzey-site.php` | t52 |
| T007 | Write `t52-promotion-engine`, the native rows included (rewards, value, products, categories, brands, limits, expiry, exclusions) | itself |
| T008 | Full suite on a clean baseline; mutation checks; Codex review | suite |
| T009 | Staging, on the owner's approval: a promotion created on the coupon screen and seen in the cart | `verification.md` |
| T010 | Client acceptance through MS-UAT-2026-027 (DOD-09), with Stage 3 after Stage 2 (D-22) | `verification.md` |

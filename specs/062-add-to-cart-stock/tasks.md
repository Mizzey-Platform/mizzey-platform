# Tasks: adding to the cart takes account of the stock source

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

| Id | Task | Test |
|---|---|---|
| T001 | `t102-add-to-cart-stock`, run red before the check was registered | itself |
| T002 | `AddToCartStock` on `woocommerce_add_to_cart_validation`, through the gateway, failing closed | t102 AC-1 to AC-5 |
| T003 | Arabic for the three new messages; `.l10n.php` rebuilt | t102 AC-6 |
| T004 | The scenarios that add over HTTP (t56, t57, t84, t92, t94, t95) switch the stand-in on for their run | those scenarios |
| T005 | Full suite; mutation checks; self-review (Codex paused): done, 98 scenarios (t15 intermittent, passes alone), 8 of 8 defects caught | `evidence/` |
| T006 | Staging: t102 on the release: done, v0.1.23 (`evidence/staging-2026-11-05.txt`) | `evidence/` |

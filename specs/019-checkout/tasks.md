# Tasks: checkout

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Each task names its test. `t60` is `mizzey-site/tests/integration/t60-checkout.php`.

| Id | Task | Test |
|---|---|---|
| T001 | `EgyptianAddress`: the country locale and the district and landmark fields | t60 AC-1, AC-6 |
| T002 | `OrderStock`: WooCommerce's order stock writers off | t60 AC-7 |
| T003 | `OrderStock`: reserve at placement, undo and refuse, release on cancellation or failure | t60 AC-7 |
| T004 | Wire into `mizzey-site.php`; Arabic for every message and label | t60, t39 |
| T005 | Write `t60-checkout`, the native rows included | itself |
| T006 | Full suite; mutation checks; Codex review | suite |
| T007 | Staging, on the owner's approval: the fixture and cash on delivery switched on, a test order placed in both languages | `verification.md` |
| T008 | Client acceptance through MS-UAT-2026-027, with Stage 3 after Stage 2 (D-22) | `verification.md` |

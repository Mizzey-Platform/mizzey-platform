# Tasks: customer emails

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Each task names its test. `t62` is `mizzey-site/tests/integration/t62-emails.php`.

| Id | Task | Test |
|---|---|---|
| T001 | `EmailAdditions`: the welcome discount details and the pay-again link | t62 AC-1, AC-3 |
| T002 | `Shipped` and `ShippedEmail`, once, in the order's language | t62 AC-4, AC-8 |
| T003 | Wire into `mizzey-site.php`; Arabic for every line | t62, t39 |
| T004 | Write `t62-emails`, WooCommerce's own emails included | itself |
| T005 | Full suite; mutation checks; Codex review | suite |
| T006 | Staging, on the owner's approval: each event triggered and the email received in English and Arabic | `verification.md` |
| T007 | Client acceptance through MS-UAT-2026-027, with Stage 3 after Stage 2 (D-22) | `verification.md` |

# Tasks: the launch business rules

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Each task names its test. `t57` is `mizzey-site/tests/integration/t57-launch-rules.php`.

| Id | Task | Test |
|---|---|---|
| T001 | `WelcomeDiscount`: template fields, issue on account creation, the customer's own candidate, template refused | t57 AC-1 |
| T002 | `Combination`: the setting and the individual-use rule; `AutomaticPromotions::winners()` | t57 AC-4 |
| T003 | Verify BR-002, BR-006, BR-008, BR-009, BR-010 natively | t57 AC-2, AC-5, AC-7 to AC-9 |
| T004 | Arabic for the refusal; wire into `mizzey-site.php` | t57 |
| T005 | Full suite; mutation checks; Codex review | suite |
| T006 | Staging, on the owner's approval: t57 on staging, with the OD-03 template set up | `verification.md` |
| T007 | BR-003 and BR-007 through the stock seam with #255, after PRE-09 | `verification.md` |
| T008 | Client acceptance through MS-UAT-2026-027, with Stage 3 after Stage 2 (D-22); the client's OD-03 and OD-04 values | `verification.md` |

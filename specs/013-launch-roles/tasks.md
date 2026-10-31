# Tasks: launch staff roles, the part Stage 3 needs

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Each task names its test. `t54` is `mizzey-site/tests/integration/t54-launch-roles.php`.

| Id | Task | Test |
|---|---|---|
| T001 | `RefundCapability::grant()`: given once to Administrator and Shop manager, a removal kept | t54 FR-001, AC-1, AC-2 |
| T002 | `guardAjax()` on the order screen's refund and delete-refund actions | t54 AC-4 |
| T003 | `guardRest()` on the REST refunds routes | t54 AC-4 |
| T004 | Wire into `mizzey-site.php` | t54 |
| T005 | Write `t54-launch-roles`, the standard role set's capabilities included | itself |
| T006 | Full suite; mutation checks; Codex review | suite |
| T007 | Staging, on the owner's approval: t54 on staging | `verification.md` |
| T008 | Client acceptance through MS-UAT-2026-027, with Stage 3 after Stage 2 (D-22) | `verification.md` |

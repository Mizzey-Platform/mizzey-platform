# Tasks: order status model

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Each task names its test. `t51` is `mizzey-site/tests/integration/t51-order-status-model.php`.

| Id | Task | Test |
|---|---|---|
| T001 | `StatusModel`: statuses, labels, transition table, delivered set, paid statuses, meta keys, helpers | t51 AC-1, AC-4, AC-5 |
| T002 | `StatusRegistry`: register the five statuses, insert and rename labels, paid statuses | t51 AC-1 |
| T003 | `TransitionGuard` last guard: refuse a forbidden move on save without side effects; record first entry; store the cancellation reason | t51 AC-2 (code), AC-3 (system, customer), AC-8, FR-010 |
| T004 | `TransitionGuard` order screen: priority-1 check, reason required, error through the meta box | t51 AC-2 (screen), AC-3 (screen), AC-7 |
| T005 | `TransitionGuard` bulk: filter refused orders, one-time notice | t51 AC-2 (bulk), AC-3 (bulk), AC-7 |
| T006 | `TransitionGuard` REST: 400 on a forbidden move or a staff cancellation without reason; attribute staff changes | t51 AC-2 (REST), AC-3 (REST), AC-7 |
| T007 | `CancellationField`: the reason field and its timeline note | t51 AC-3 |
| T008 | Arabic labels in `languages/` (po and compiled PHP) | t51 AC-1 |
| T009 | Wire into `mizzey-site.php` | t51 |
| T010 | Write `t51-order-status-model` | itself |
| T011 | Full suite on a clean baseline; guards (wp-guard, woo-guard, test-guard, clean-code-guard) | suite |
| T012 | Staging, on the owner's approval: the order screen in a browser in English, a forbidden move and a cancellation without a reason refused | `verification.md` |
| T013 | Client acceptance through MS-UAT-2026-027 (DOD-09) | `verification.md` |

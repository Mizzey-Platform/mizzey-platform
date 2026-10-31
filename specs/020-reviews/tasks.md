# Tasks: product reviews

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Each task names its test. `t61` is `mizzey-site/tests/integration/t61-reviews.php`.

| Id | Task | Test |
|---|---|---|
| T001 | `ReviewEligibility`: D-17 on the form post, XML-RPC and REST; the form closed with the reason | t61 AC-1 |
| T002 | `ReviewModeration`: every review held; the rating required | t61 AC-1, AC-2, AC-3 |
| T003 | `ReviewInvitation` and its email, once, in the order's language | t61 AC-4 |
| T004 | Wire into `mizzey-site.php`; Arabic for every customer-facing message | t61, t39 |
| T005 | Full suite; mutation checks; Codex review | suite |
| T006 | Second part of #309: up to 5 images per review, moderated with it (D-23, a gift) | not started |
| T007 | Staging, on the owner's approval | `verification.md` |
| T008 | Client acceptance through MS-UAT-2026-027, with Stage 3 after Stage 2 (D-22) | `verification.md` |

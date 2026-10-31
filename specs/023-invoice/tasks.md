# Tasks: the invoice

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Each task names its test. `t64` is `mizzey-site/tests/integration/t64-invoice.php`.

| Id | Task | Test |
|---|---|---|
| T001 | mPDF bundled in `mizzey-site/lib/`, pinned, fonts trimmed; recorded in `stack.lock.json` | t64 AC-2 (the PDF) |
| T002 | `InvoiceSettings` and `InvoiceNumber` | t64 AC-1 |
| T003 | `InvoiceDocument` and `InvoicePdf` | t64 AC-2 |
| T004 | `InvoiceRoutes`: the download, the account, orders list and order screen links | t64 AC-3, AC-4 |
| T005 | Wire into `mizzey-site.php`; Arabic for every label | t64, t39 |
| T006 | Mutation checks; Codex review | `evidence/mutation.txt`, `analysis.md` |
| T007 | Staging, on the owner's approval: the release pack carries `lib/` | `verification.md` |
| T008 | Client acceptance through MS-UAT-2026-027, with Stage 3 after Stage 2 (D-22), after OD-09 and CR-12 | `verification.md` |

# Tasks: ERP adapter seam

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Each task names its test. `t53` is `mizzey-site/tests/integration/t53-erp-adapter-seam.php`.

| Id | Task | Test |
|---|---|---|
| T001 | `StockSource` contract, `UnconfiguredStockSource`, the two failure kinds | t53 AC-1, AC-3, AC-4 |
| T002 | `StockIdentity`: the source record from the translation group's own rows | t53 AC-2 |
| T003 | `StockGateway`: resolve, bounded retries, operation key kept, logging, fail closed, no WooCommerce stock write | t53 AC-3 to AC-6, FR-007 |
| T004 | Wire into `mizzey-site.php` | t53 |
| T005 | Write `t53-erp-adapter-seam` with a contract-keeping mock and a recording logger | itself |
| T006 | Full suite; mutation checks; Codex review | suite |
| T007 | Staging, on the owner's approval: t53 on staging | `verification.md` |
| T008 | Final acceptance after PRE-09 and the client's approval (M-3) | `verification.md` |

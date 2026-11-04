# Tasks: the Egyptian address in full

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Each task names its test. `t90` is `mizzey-site/tests/integration/t90-egyptian-address.php`.

| Id | Task | Test |
|---|---|---|
| T001 | `EgyptianAddress`: reading order, labels, phone required, the kind, building, floor and apartment fields | t90 AC-1, AC-2 |
| T002 | `EgyptianAddress`: one address | t90 AC-3 |
| T003 | `EgyptianAddress`: the second line on every save, in the order's language; the packing slip's landmark; the invoice's and customer record's line | t90 AC-4 |
| T004 | `AddressBook`: the same parts and rules, copied to the checkout's prefill | t90 AC-5; t63, t73 |
| T005 | Arabic for every new label and the line | t90 AC-4 |
| T006 | The existing scenarios' checkout and address bodies carry the new parts | t47, t60, t63, t73 |
| T007 | Full gates; mutation checks; review (Codex rounds 1 and 2, then a self-review: Codex is paused) | `evidence/` |
| T008 | Staging: t90 and t60 on the release: done, v0.1.20 (`evidence/staging-2026-11-04.txt`) | PR |

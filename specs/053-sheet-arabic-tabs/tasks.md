# Tasks: the product sheet carries its Arabic, and its tabs

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Each task names its test. `t93` is `mizzey-site/tests/integration/t93-sheet-arabic-tabs.php`.

| Id | Task | Test |
|---|---|---|
| T001 | `Workbook`, `ExportReader::isWorkbook()`, the `tab` on rows and reports | t93 AC-1, AC-2 |
| T002 | `Mapping`: Arabic fields, optional columns, the Arabic suffix; `HeaderCheck` | t93 AC-3, AC-6 |
| T003 | `CatalogueLoad`: Arabic category levels, brands and options | t93 AC-4, AC-5 |
| T004 | W11 | t93 AC-6 |
| T005 | The revised sheet (`product-sheet-v2.csv`) and the mapping 1.1.0 | t93 AC-3; t91 |
| T006 | Full gates; mutation checks; self-review (Codex paused) | `evidence/` |
| T007 | Staging: the revised sheet validated and loaded on the release | PR |

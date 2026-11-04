# Tasks: the importer reads Mizzey's product sheet

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Each task names its test. `t91` is `mizzey-site/tests/integration/t91-product-sheet.php`.

| Id | Task | Test |
|---|---|---|
| T001 | `Mapping`: prefixes, their validation, `forHeader()` | t91 AC-2 |
| T002 | `HeaderCheck`: F4 for a prefixed column that cannot be read | t91 AC-3 |
| T003 | The preview, validation and load expand the mapping from the file's header; option names with spaces | t91 AC-2, AC-4 |
| T004 | The plugin's mapping reads the sheet; the fixtures keep their own mapping | t91 AC-1; t30 to t38, t86 |
| T005 | Full gates; mutation checks; self-review (Codex paused) | `evidence/` |
| T006 | Staging: the sample sheet validated and loaded on the release | PR |

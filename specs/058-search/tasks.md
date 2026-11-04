# Tasks: the store's own product search

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Each task names its test. `t98` is `mizzey-site/tests/integration/t98-search.php`.

| Id | Task | Test |
|---|---|---|
| T001 | `Normalise` | t98 AC-2 |
| T002 | `SearchIndex` and `wp mizzey search reindex` | t98 AC-1 |
| T003 | `ProductSearch`: condition, order | t98 AC-1, AC-3, AC-7 |
| T004 | `Synonyms` and its screen | t98 AC-3, AC-4 |
| T005 | Suggestions and empty-state suggestions | t98 AC-5 |
| T006 | The term log | t98 AC-6 |
| T007 | Full gates; mutation checks; self-review (Codex paused) | `evidence/` |
| T008 | Staging: t98 on the release: done, v0.1.21 (`evidence/staging-2026-11-05.txt`) | PR |

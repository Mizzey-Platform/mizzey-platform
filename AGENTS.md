# Mizzey: agent entry point

This is the authoritative instruction file for any agent or developer in this repository. `CLAUDE.md` points here.

This is a **CoreX client site** for **Option B, the Launch Platform**. The engagement, scope authority and current
phase are in [README.md](README.md). The rules are in the
[constitution](.specify/memory/constitution.md). Read both before changing anything.

## Role Gate: CLIENT SITE MODE

- Edit only this client source: `mizzey-site/` (namespace `MizzeySite\`), `mizzey-theme/`, `specs/`, `docs/`,
  `tools/` and the repository configuration.
- **Never** edit CoreX framework internals, the pinned checkout in `../app/corex/`, `../app/wp/wp-content/`, or `dist/`.
- For a framework bug or a reusable component, stop and raise a CoreX task. Never patch CoreX for one client.
- Site identity: namespace `MizzeySite\`, text domain `mizzey-site`, REST namespace `mizzey/v1`, CSS prefix
  `--mizzey-`, option and CPT prefix `mizzey_`. Use `wp corex make:*` generators.

## Before any work: classify it

| If the work... | It is | It needs |
|---|---|---|
| adds or changes behaviour a customer, client or staff user sees or relies on | `requirement` | A Feature Register obligation id (P1, P1-L, P1-E, DLV), a spec under `specs/` |
| changes governance, CI, tooling, test infrastructure or documentation, with no behaviour change | `internal:<category>` | A clear statement of no client-facing change |
| fixes a vulnerability or keeps a dependency supported | `internal:security-maintenance` | A statement of what behaviour changes, if any |
| has no obligation id and would change behaviour | A Change Request (MS-CHG-2026-028) | Mustafa. **Stop.** Do not build it |

A valid register id does not make every behaviour in scope. Check each criterion against the wording of its row.

## Workflow for requirement work (Spec Kit owns it)

1. `git switch main && git pull`.
2. `/speckit-specify` creates `specs/NNN-<slug>/spec.md` and the branch `NNN-<slug>`. Fill the Register trace,
   Contractual acceptance criteria, Native coverage, Optional safeguards and Future or Option C sections.
3. `/speckit-clarify` for open questions, then `/speckit-plan` (Constitution Check), then `/speckit-tasks`, then
   `/speckit-analyze`.
4. `/speckit-implement`, only in `mizzey-site/` or `mizzey-theme/`, with tests for every task.
5. Run the guards on the diff (wp-guard, woo-guard, test-guard, clean-code-guard, docs-guard as relevant).
6. Run the local checks (see [CONTRIBUTING.md](CONTRIBUTING.md)). Open a PR from the template. Squash-merge after
   CI passes and the PR is reviewed.

Internal work skips steps 2 to 4 and uses a branch named `<category>/<slug>`.

**Spec Kit is the only planning system.** The guard skills and other technique skills (debugging, TDD,
verification) support the work. They must not write their own plan, spec or task documents. Task-to-issue
generation is disabled.

## Hard rules

- **Contradictions are escalated, not resolved.** Open items are in `docs/scope/open-items.json`. CX-01 (the
  Accountant role) is open. Cite it and mark dependent criteria pending. Never decide it in code or criteria.
- **P1-E rows** get no final criteria before PRE-09 is approved. Choose no ERP mechanism before the ERP team's input.
- **Native first.** Custom code needs a recorded gap.
- **The contractual stage never moves.** Engineering timing is noted separately.
- **Report three states separately:** workflow complete, technically verified, contractually accepted.
- **No** force-push, history rewrite, direct push to `main`, issue bulk-creation or closure, Project changes, paid
  plans, production probes, engagement-document edits or CoreX-internal changes without Mustafa's explicit approval.
- Discovery probes run only against the disposable local runtime, never production.
- Working language is English. The storefront is bilingual; the admin is English only (ADM-159).
- Record decisions in `DECISIONS.md` (and an ADR for architecture), and keep `PROGRESS.md` current.

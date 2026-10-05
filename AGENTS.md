# Mizzey: agent entry point

This is the authoritative instruction file for any agent or developer in this repository. `CLAUDE.md` points here.

This is a **CoreX client site** for **Option B, the Launch Platform**. The engagement, the contract authority model
and the current phase are in [README.md](README.md). The rules are in the
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
2. **Create the branch first.** In this setup Spec Kit 0.10.1 creates the spec directory but not the git branch
   (branch creation belongs to an optional git extension, which is not installed). Ask Spec Kit for the next name,
   then create the branch with exactly that name:
   `./.specify/scripts/powershell/create-new-feature.ps1 -Json -DryRun -ShortName <slug> "<description>"`
   returns `BRANCH_NAME` (for example `001-product-cost-capture`); then `git switch -c <BRANCH_NAME>`.
3. Run `/speckit-specify` and state `SPECIFY_FEATURE_DIRECTORY=specs/<BRANCH_NAME>` in the request. Without it,
   the skill picks its own short name, and the directory can differ from the branch. It copies the Mizzey template
   to `specs/<BRANCH_NAME>/spec.md`. The scope check fails a PR that adds a spec whose directory does not match the
   branch name. Fill the Register trace, Contractual acceptance criteria, Native coverage, Optional safeguards and
   Future or Option C sections.
4. `/speckit-clarify` for open questions, then `/speckit-plan` (Constitution Check), then `/speckit-tasks`, then
   `/speckit-analyze`.
5. `/speckit-implement`, only in `mizzey-site/` or `mizzey-theme/`, with tests for every task.
6. Run the guards on the diff (wp-guard, woo-guard, test-guard, clean-code-guard, docs-guard as relevant).
7. Run the local checks (see [CONTRIBUTING.md](CONTRIBUTING.md)). Open a PR from the template. Squash-merge after
   CI passes and the PR is reviewed.

Internal work skips steps 2 to 5 and uses a branch named `<category>/<slug>`. It may touch only the paths its
category allows (`PATH_POLICY` in `tools/scope_trace.py`). A new top-level directory is never internal work.

**Spec Kit is the only planning system.** The guard skills and other technique skills (debugging, TDD,
verification) support the work. They must not write their own plan, spec or task documents. Task-to-issue
generation is disabled, and the agent-context skill is removed.

## Hard rules

- **Contradictions are escalated, not resolved.** Open items are in `docs/scope/open-items.json`. Only Mustafa
  closes one, in `DECISIONS.md`. **CX-01 is resolved by D-10: the Accountant role exists at launch**, least
  privilege, with refunds a separate capability. It is an owner decision, not a client confirmation, and ROLE-06
  still reads DEF in the register. A spec may trace ROLE-06 only with every dependent criterion `pending CX-01`
  (D-11), and acceptance waits for the client's acknowledgement of the Scope Clarification MS-CLR-2026-037.
  **CX-02 to CX-06 are resolved by D-12**, as owner decisions that are not client confirmations and that grant no
  engineering id: the rows the register defers (ROLE-05, ROLE-10, MIG-18, ADM-120, ADM-141, HOME-12, ADM-62) stay
  untraceable and unbuilt. Read the item's `resolution` in `docs/scope/open-items.json` before touching a row it
  names: it says what is built and what is not. A criterion that rests on one is written against the contracted
  row, cites the CX id, and is `provisional` or `pending CX-nn`, never `final`, until the client confirms the
  reading. A new contradiction is still escalated, never decided in a spec.
- **One row, one PBI.** `docs/scope/backlog-ownership.json` says which PBI accepts each delivery row, and
  `tools/tests/test_backlog_ownership.py` checks it against the register. Before starting a PBI, read its issue and
  its entry there. Change ownership only through an `internal:governance` PR, then regenerate the records with
  `tools/gen_backlog_docs.py`. Never cite a row another PBI owns.
- **A missing input is classified before it is called a blocker** (constitution M-10). Build through
  configuration, a working default, a placeholder, a fixture, a mock adapter, a contract or sandbox credentials,
  and leave the real value to an acceptance or launch gate. Never record an owner default as client-confirmed.
- **The ERP stays behind the adapter boundary**, built against mocks and contracts on fixed invariants: the ERP is
  the stock source of truth; one commercial item or variant is one ERP stock identity whatever the language; the
  store holds no independent authoritative stock; a final sale fails closed when authoritative stock cannot be
  validated; no duplicate decrement; idempotency, retry, logging and reconciliation paths exist. Production ERP
  specifics stay pending PRE-09.
- **Missing brand identity does not stop structural design.** Wireframes, responsive states, both reading
  directions, component architecture and neutral design tokens proceed; the final identity is applied later
  through tokens and assets. Approval of the interface design still waits for OD-01.
- **Development and staging run on the developer's machine**, the staging and demonstration copy isolated, on its
  own database, with synthetic data only, reviewed through a Cloudflare Tunnel. Production hosting stays the
  client's written decision (OD-27). Staging is built, reset, backed up and restored with
  `mizzey-site/tests/staging/staging.py` (`docs/staging-environment.md`); its destructive commands need
  `MIZZEY_CONFIRM_STAGING=yes` and refuse any other environment. **A feature's DOD-04 check is run on staging
  through the real request a browser makes**: a scenario that passes inside one process is not that check, as
  #246 showed. A scripted pass is recorded as scripted, and is never called a person's look or acceptance.
- **P1-E rows** get no final criteria before PRE-09 is approved. Choose no ERP mechanism before the ERP team's input.
- **Native first.** Custom code needs a recorded gap.
- **The contractual stage never moves.** Engineering timing is noted separately.
- **Report three states separately:** workflow complete, technically verified, contractually accepted.
- **"Pre-development complete" is two statuses, never one (D-12).** *Engineering pre-development readiness* is
  about whether the next executable PBIs can start. *Contractual pre-development acceptance* stays pending until
  PRE-07, PRE-03b, PRE-08, PRE-09 and the ROLE-06 clarification really meet their contract conditions. The
  second is not called a development blocker unless a specific dependent PBI genuinely cannot proceed.
- **No** force-push, history rewrite, direct push to `main`, issue bulk-creation or closure, Project changes, paid
  plans, production probes, engagement-document edits or CoreX-internal changes without Mustafa's explicit approval.
- Discovery probes run only against the disposable local runtime, never production.
- Working language is English. The storefront is bilingual; the admin is English only (ADM-159).
- Record decisions in `DECISIONS.md` (and an ADR for architecture), and keep `PROGRESS.md` current.

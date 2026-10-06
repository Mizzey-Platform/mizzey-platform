# Engineering tooling

What is installed in this repository, where it came from, and what each check does.

## Spec Kit

| | |
|---|---|
| Version | Spec Kit 0.10.1, integration `claude`, PowerShell scripts, sequential feature numbering (`.specify/init-options.json`) |
| Owns | Specifications, clarification, plans, tasks, consistency analysis and implementation (`/speckit-specify`, `/speckit-clarify`, `/speckit-plan`, `/speckit-tasks`, `/speckit-analyze`, `/speckit-implement`, `/speckit-checklist`, `/speckit-constitution`) |
| Branches | Spec Kit 0.10.1 creates the spec directory, not the git branch. Branch creation belongs to its optional git extension, which is not installed. Get the name from `create-new-feature.ps1 -Json -DryRun`, run `git switch -c <name>`, then `/speckit-specify` with `SPECIFY_FEATURE_DIRECTORY=specs/<name>`. Verified in a disposable export on 22 Sep 2026 |
| Constitution | `.specify/memory/constitution.md` (Mizzey, inherits CoreX v1.2.2) |
| Templates | `.specify/templates/`. The spec template adds Register trace, Contractual acceptance criteria, Native coverage, Optional safeguards and Future or Option C sections. User-story priority is High/Medium/Low, because P1 to P3 are register scope values |
| Not installed | `speckit-taskstoissues` (removed from `.specify/integrations/claude.manifest.json`). The agent-context extension and its `speckit-agent-context-update` skill were removed with `specify extension remove agent-context`, because they rewrite `CLAUDE.md`. `tools/repo_checks.py` fails if either returns, or if `CLAUDE.md` gains a Spec Kit managed block |
| Session state | `.specify/feature.json` (the current feature directory) is per-session and git-ignored |

An upgrade of Spec Kit (`specify` re-init) may reinstall removed skills or re-enable hooks. Do it on a branch,
and check the diff against this page.

## Skills (`.claude/skills/`)

| Skill | Source | Pinned at | Licence |
|---|---|---|---|
| `speckit-*` (8) | [github/spec-kit](https://github.com/github/spec-kit) 0.10.1 scaffold | as installed 22 Sep 2026 | MIT |
| `wp-guard`, `woo-guard`, `test-guard`, `clean-code-guard`, `docs-guard` | [amElnagdy/guard-skills](https://github.com/amElnagdy/guard-skills) | `ffa26036b7b5e77b20b5d679304a703b6fd1a43d` | MIT, copy in each skill folder |

The guard skills are byte-identical to upstream at that commit, plus the upstream LICENSE file in each folder. The CoreX copies differ from upstream only in line endings.
They review diffs. They do not produce plans or specs.

Not versioned here: personal Claude Code settings (`.claude/settings.local.json`, `.claude/settings.json`),
credentials, and machine-specific paths.

## Checks

| Tool | Scope | Fails when |
|---|---|---|
| `python -m discovery.check` | whole tree | the discovery datasets break their schema or gate rules |
| `tools/repo_checks.py` | whole tree | task-to-issue generation or agent-context returns; `CLAUDE.md` gains a managed block; `stack.lock.json` is malformed or disagrees with `corex.lock`; `register-ids.json` disagrees with `docs/scope/SOURCE.md`; `open-items.json` cites an unknown id |
| `tools/scope_trace.py` | every added, modified, renamed and deleted path; changed specs; the PR body | classification missing, invalid or duplicated; a path outside the category's allowed classes, or not covered by `PATH_POLICY`; missing `Sensitive changes:`, `Delivery impact:`, `Security evidence:` or `Security change:` lines; requirement ids unknown, non-obligation or untraced; a spec deleted or moved; a new spec directory not matching the branch; trace ids unknown, non-obligation, or with the wrong scope or stage; any Traces token that is not a traced register id; a criterion status outside `final`, `provisional`, `pending CX-nn / PRE-09 / OD-nn`; P1-E criteria `final` before PRE-09; an open contradiction not cited |
| `tools/design_inventory.py` | `design/inventory/`, `design/sources.json`, `design/coverage.md` | a contracted surface cites no delivery row, a deferred or later-phase row, or a row its PBI does not own; a surface outside contracted scope cites a row or does not say why it exists; a flow step, component or placeholder points at a missing surface; a customer-facing surface ignores Arabic; a delivery row is on no surface and has no `no_surface` reason; a build PBI differs from the accepting PBI without a reason, or is invented for a surface nothing builds; an owner decision is recorded as a client confirmation; an open or provisional interaction option claims a selected pattern; `coverage.md` is stale. Run in CI by `tools/tests/test_design_inventory.py` |
| `tools/design_wireframes.py` | `design/wireframes/manifest.json`, and every HTML and CSS file under `design/wireframes/` | the manifest and the inventory disagree: a surface without an entry, a briefed surface without a brief or a purpose, an artefact the manifest does not list or one it lists that does not exist, a surface marked reviewed with a required state uncovered, a second-release surface outside its own pass; or a wireframe stops being a design prototype: script in any form, a server or template tag, anything fetched from outside, a product library, a colour that is not a grey, a web font, a missing or contradictory language and direction, an unnamed surface, a motion note without its four facts, a stylesheet large enough to be a library. Run in CI by `tools/tests/test_design_wireframes.py` |
| `tools/design_briefs.py` | `design/briefs/` | a committed brief is not what the generator writes from the inventory, a listed brief is missing, or a brief exists that the manifest does not list. `--register` merges the signed wording into working copies and refuses to write them inside the repository. Run in CI by `tools/tests/test_design_briefs.py` |
| `tools/house_rules.py` | added lines and added or renamed files | em dash or emoji on an added markdown line; an added contract document, archive, key or env file; anything new under `docs/engagement/` |
| `tools/run_trusted.py` | the PR | runs the base branch's `scope_trace.py` and `house_rules.py` (trusted) and the PR's own copies (proposed), and fails if any run fails. Lists changed checker, CI, hook, constitution, scope-record and deployment files |
| `tools/extract_register_ids.py --check` | local only (needs the register) | `register-ids.json` is stale against a given register |

What the checks cannot do: judge whether a criterion's behaviour is really supported by its row's wording, or
whether a declared delivery impact or security change is true. Those are review judgements. The checks make them
explicit.

Historical files are never re-judged. The house rules look only at added lines, and the paths listed in
`tools/house_rules.py` `EXCLUDED` are skipped entirely.

## CI (`.github/workflows/ci.yml`)

- Runner `ubuntu-24.04` (PHP 8.3.6, matching local). Python 3.10 via `actions/setup-python`.
- Actions pinned to commit SHAs: `actions/checkout` v7.0.1, `actions/setup-python` v7.0.0. Only GitHub-owned
  actions are used.
- `baseline` (every event): discovery gate and tests, tooling tests (including the abuse regression tests), repo
  checks, PHP lint.
- `changes` (pull requests): `tools/run_trusted.py`.
  - **Bootstrap:** PR #235 introduces the checker, so `main` has none. The runner says so, runs the proposed
    checker only, and the checker and CI diff are reviewed by hand.
  - **Afterwards:** the base branch's checker is exported with `git archive` and run against the PR, alongside the
    PR's own copy, whose unit tests run in `baseline`.
- `main-push` (pushes to `main`): spec, deletion and house-rule checks on what reached `main`. There is no PR body
  on a push, so classification cannot be checked there.
- `GITHUB_TOKEN` is read-only. No secrets are used. No artifacts are uploaded, so nothing from the checkout leaves
  the runner. The historical Option C contract copies in `docs/engagement/` were removed from the tree under D-03;
  git history keeps them, and `tools/house_rules.py` blocks anything new being added there.
- **Limits.** On the free plan, CI cannot be made a required check on this private repository. It reports and does
  not block. A PR can edit `ci.yml` itself to skip the trusted run; that edit is listed as governance-sensitive and
  needs a `Sensitive changes:` line, but it is not prevented. Running the checker from `main` does not make CI
  tamper-proof.

## Local push guard (`.githooks/pre-push`)

Enable once per clone with `git config core.hooksPath .githooks`. It blocks pushes to `main` and non-fast-forward
pushes. An approved direct push sets `MIZZEY_ALLOW_MAIN_PUSH=1` for that one command, and is recorded in
`DECISIONS.md`. It is a local safeguard, not branch protection.

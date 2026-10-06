# Contributing

How work gets into `main`. The rules behind this are in the
[constitution](.specify/memory/constitution.md), and the day-to-day agent workflow is in [AGENTS.md](AGENTS.md).

## Branches

`main` only, trunk based. Every change arrives by pull request and is squash-merged.

| Work | Branch |
|---|---|
| Requirement (Spec Kit) | `NNN-<slug>`: the `BRANCH_NAME` from `create-new-feature.ps1 -DryRun`, created with `git switch -c` before `/speckit-specify` (Spec Kit 0.10.1 does not create branches here). The spec directory must be `specs/<same name>/` |
| Internal | `<category>/<slug>`, for example `governance/option-b-foundation` or `ci/cache-pip` |
| Defect against accepted work | `fix/<slug>`, classified `requirement` with the ids of the story it fixes |

## Commits

Conventional Commits, as in CoreX: `feat:`, `fix:`, `docs:`, `test:`, `ci:`, `chore:`, `refactor:`. The subject
line says what changed. The body says why.

## Pull request classification

The PR template carries these lines. `tools/scope_trace.py` reads them, and each may appear only once.

```text
Classification: requirement
Requirement ids: ADM-27, RPT-11
```

```text
Classification: internal:governance
Client-facing behaviour change: none
Sensitive changes: adds the trusted-checker step to CI
```

Internal categories: `governance`, `ci`, `test-infrastructure`, `tooling`, `security-maintenance`, `documentation`.

**Paths.** Every added, modified, renamed and deleted path is classified by `PATH_POLICY` in
`tools/scope_trace.py`, and each category may touch only its path classes:

| Path class | Examples | Allowed for |
|---|---|---|
| feature-spec | `specs/NNN-*/` | requirement |
| site-code | `mizzey-site/`, `mizzey-theme/` (not their `tests/`) | requirement, security-maintenance |
| site-tests | `mizzey-site/tests/`, `mizzey-theme/tests/` | requirement, test-infrastructure, security-maintenance |
| delivery-tooling | `tools/build-dist.mjs`, `tools/corex-sync.mjs` | requirement, tooling, security-maintenance |
| version-lock | `corex.lock`, `composer.*`, `package*.json` | requirement, security-maintenance |
| governance-control | CI workflows, hooks, the checkers, constitution, `docs/scope/`, `stack.lock.json`, AGENTS, CLAUDE, CONTRIBUTING, `.gitignore` | governance, ci |
| workflow-config | `.specify/`, `.claude/skills/`, `.github/` templates | governance, ci |
| tooling | other `tools/`, `discovery/`, `scripts/` | governance, tooling, security-maintenance |
| test-infra | `tools/tests/`, `discovery/tests/` | all except documentation |
| design | `design/**/*.md`, `design/**/*.json`: the UX and design source of truth. Markdown and JSON only | requirement, governance, documentation |
| docs | `docs/**/*.md`, `docs/engagement/`, README, PROGRESS, DECISIONS, COREX-WORKAROUNDS | all |
| unclassified | anything else, including any new top-level directory | **nobody**: extend the policy in an internal:governance PR first |

Feature specs are never deleted or moved. A new spec's directory must match the branch name.

**Extra lines.** `Sensitive changes:` whenever governance-control, delivery-tooling or version-lock paths change.
`Delivery impact:` whenever delivery-tooling or version-lock paths change, saying what changes in the deployed
artifact or why nothing does. `security-maintenance` also needs `Security evidence:` (a CVE or GHSA id, an advisory
or upgrade URL, or `local: <defect and how it was found>` together with a regression test in the PR) and
`Security change:`. These lines make the change reviewable. They do not prove it safe.

## Checks

Run these before pushing. CI runs the same ones.

```bash
python -m discovery.check
python -m unittest discover -s discovery/tests -t .
python -m unittest discover -s tools/tests -t .
python tools/repo_checks.py
python tools/design_inventory.py
python tools/run_trusted.py --base origin/main --body-file <your PR body>
```

Plus `php -l` on any PHP you changed, and the Pest suite once `mizzey-site/` has tests.

## Enforcement, honestly stated

The organization is on the GitHub free plan, which does not enforce branch protection or required status checks on
private repositories. CI therefore **reports** and does not **block** a merge. From the PR after #235, CI also runs the checker from the
base branch, so a PR cannot quietly weaken the checker that judges it. A PR can still edit the workflow itself;
that change is listed in the CI output and must be reviewed. The local pre-push hook
(`git config core.hooksPath .githooks`) blocks direct pushes to `main` and non-fast-forward pushes from a machine
where it is enabled. It is advisory: `--no-verify` bypasses it, and it is not server-side protection. Until a paid
plan is approved, the rule "merge only with green CI and a review" is kept by discipline.

## Definition of done

A PR is ready when CI is green, the guards have been run on the diff, each acceptance criterion has a verification
line, and the three states (workflow complete, technically verified, contractually accepted) are reported
separately, without claiming acceptance that has not happened.

# Contributing

How work gets into `main`. The rules behind this are in the
[constitution](.specify/memory/constitution.md), and the day-to-day agent workflow is in [AGENTS.md](AGENTS.md).

## Branches

`main` only, trunk based. Every change arrives by pull request and is squash-merged.

| Work | Branch |
|---|---|
| Requirement (Spec Kit) | `NNN-<slug>`, created by `/speckit-specify`, matching `specs/NNN-<slug>/` |
| Internal | `<category>/<slug>`, for example `governance/option-b-foundation` or `ci/cache-pip` |
| Defect against accepted work | `fix/<slug>`, classified `requirement` with the ids of the story it fixes |

## Commits

Conventional Commits, as in CoreX: `feat:`, `fix:`, `docs:`, `test:`, `ci:`, `chore:`, `refactor:`. The subject
line says what changed. The body says why.

## Pull request classification

The PR template carries these lines. `tools/scope_trace.py` reads them.

```text
Classification: requirement
Requirement ids: ADM-27, RPT-11
```

```text
Classification: internal:governance
Client-facing behaviour change: none
```

Internal categories: `governance`, `ci`, `test-infrastructure`, `tooling`, `security-maintenance`, `documentation`.
Only `security-maintenance` may touch `mizzey-site/` or `mizzey-theme/`, or state a behaviour change, and it must
say what changes. An internal PR never adds or edits a feature spec.

## Checks

Run these before pushing. CI runs the same ones.

```bash
python -m discovery.check
python -m unittest discover -s discovery/tests -t .
python -m unittest discover -s tools/tests -t .
python tools/repo_checks.py
python tools/scope_trace.py --base origin/main --body-file <your PR body>
python tools/house_rules.py --base origin/main
```

Plus `php -l` on any PHP you changed, and the Pest suite once `mizzey-site/` has tests.

## Enforcement, honestly stated

The organization is on the GitHub free plan, which does not enforce branch protection or required status checks on
private repositories. CI therefore **reports** and does not **block** a merge. The local pre-push hook
(`git config core.hooksPath .githooks`) blocks direct pushes to `main` and non-fast-forward pushes from a machine
where it is enabled. It is advisory: `--no-verify` bypasses it, and it is not server-side protection. Until a paid
plan is approved, the rule "merge only with green CI and a review" is kept by discipline.

## Definition of done

A PR is ready when CI is green, the guards have been run on the diff, each acceptance criterion has a verification
line, and the three states (workflow complete, technically verified, contractually accepted) are reported
separately, without claiming acceptance that has not happened.

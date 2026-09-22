# Engineering tooling

What is installed in this repository, where it came from, and what each check does.

## Spec Kit

| | |
|---|---|
| Version | Spec Kit 0.10.1, integration `claude`, PowerShell scripts, sequential feature numbering (`.specify/init-options.json`) |
| Owns | Specifications, clarification, plans, tasks, consistency analysis and implementation (`/speckit-specify`, `/speckit-clarify`, `/speckit-plan`, `/speckit-tasks`, `/speckit-analyze`, `/speckit-implement`, `/speckit-checklist`) |
| Constitution | `.specify/memory/constitution.md` (Mizzey, inherits CoreX v1.2.2) |
| Templates | `.specify/templates/`. The spec template adds Register trace, Contractual acceptance criteria, Native coverage, Optional safeguards and Future or Option C sections. User-story priority is High/Medium/Low, because P1 to P3 are register scope values |
| Disabled | `speckit-taskstoissues` is not installed (removed from `.specify/integrations/claude.manifest.json`); `tools/repo_checks.py` fails if it returns. The agent-context hooks in `.specify/extensions.yml` are disabled because they rewrite `CLAUDE.md` |

An upgrade of Spec Kit (`specify` re-init) may reinstall removed skills or re-enable hooks. Do it on a branch,
and check the diff against this page.

## Skills (`.claude/skills/`)

| Skill | Source | Pinned at | Licence |
|---|---|---|---|
| `speckit-*` (9) | [github/spec-kit](https://github.com/github/spec-kit) 0.10.1 scaffold | as installed 22 Sep 2026 | MIT |
| `wp-guard`, `woo-guard`, `test-guard`, `clean-code-guard`, `docs-guard` | [amElnagdy/guard-skills](https://github.com/amElnagdy/guard-skills) | `ffa26036b7b5e77b20b5d679304a703b6fd1a43d` | MIT, copy in each skill folder |

The guard skills are byte-identical to upstream at that commit, plus the upstream LICENSE file in each folder. The CoreX copies differ from upstream only in line endings.
They review diffs. They do not produce plans or specs.

Not versioned here: personal Claude Code settings (`.claude/settings.local.json`, `.claude/settings.json`),
credentials, and machine-specific paths.

## Checks

| Tool | Scope | Fails when |
|---|---|---|
| `python -m discovery.check` | whole tree | the discovery datasets break their schema or gate rules |
| `tools/repo_checks.py` | whole tree | task-to-issue generation is re-enabled; `stack.lock.json` is malformed or disagrees with `corex.lock`; `register-ids.json` disagrees with `docs/scope/SOURCE.md`; `open-items.json` cites an unknown id |
| `tools/scope_trace.py` | changed specs and the PR body | no or invalid classification; requirement PR without obligation ids, or with ids not traced in a spec; internal PR changing behaviour, site code or feature specs; spec trace ids that are unknown, non-obligation, or have the wrong scope or stage; criteria citing untraced ids; P1-E criteria marked final before PRE-09; an open contradiction not cited |
| `tools/house_rules.py` | added lines and added files | em dash or emoji on an added markdown line; an added contract document, archive, key or env file; anything new under `docs/engagement/` |
| `tools/extract_register_ids.py --check` | local only (needs the register) | `register-ids.json` is stale against a given register |

What the scope check cannot do: judge whether a criterion's behaviour is really supported by the wording of the row
it cites. That is the reviewer's job, made reviewable by the per-criterion Traces column.

Historical files are never re-judged. The house rules look only at added lines, and the paths listed in
`tools/house_rules.py` `EXCLUDED` are skipped entirely.

## CI (`.github/workflows/ci.yml`)

- Runner `ubuntu-24.04` (PHP 8.3.6, matching local). Python 3.10 via `actions/setup-python`.
- Actions pinned to commit SHAs: `actions/checkout` v7.0.1, `actions/setup-python` v7.0.0. Only GitHub-owned
  actions are used.
- `GITHUB_TOKEN` is read-only. No secrets are used. No artifacts are uploaded, so nothing from the checkout
  (including the historical contract copies still in `docs/engagement/` until D-03) leaves the runner.
- On the free plan, CI cannot be made a required check on this private repository. It reports and does not block.

## Local push guard (`.githooks/pre-push`)

Enable once per clone with `git config core.hooksPath .githooks`. It blocks pushes to `main` and non-fast-forward
pushes. An approved direct push sets `MIZZEY_ALLOW_MAIN_PUSH=1` for that one command, and is recorded in
`DECISIONS.md`. It is a local safeguard, not branch protection.

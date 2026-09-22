## Summary

<!-- What changed and why, in two or three sentences. -->

## Classification

<!--
Keep exactly one of the blocks below, uncommented, and delete the others. tools/scope_trace.py reads these lines,
and each line may appear only once.

Requirement work: behaviour a customer, client or staff user sees or relies on.
Internal work: governance, ci, test-infrastructure, tooling, security-maintenance, documentation.
Internal work never adds client-facing functionality, and may touch only the paths its category allows
(PATH_POLICY in tools/scope_trace.py; table in CONTRIBUTING.md).
-->

Classification: requirement
Requirement ids: <!-- obligation ids from the Feature Register, e.g. ADM-27, RPT-11 -->
Spec: <!-- specs/NNN-slug/spec.md; a new spec's directory must match this branch name -->

<!--
Classification: internal:governance
Client-facing behaviour change: none
-->

<!--
Classification: internal:security-maintenance
Client-facing behaviour change: <what changes for users, or none>
Security evidence: <CVE-/GHSA- id, advisory or upgrade URL, or "local: <defect and how it was found>" plus a regression test>
Security change: <what the change does, and for whom>
-->

<!--
Add when the PR touches governance-sensitive paths (checkers, CI, hooks, constitution, docs/scope, stack.lock.json,
AGENTS, CLAUDE, CONTRIBUTING, .gitignore) or build, deployment or version-lock paths:
Sensitive changes: <each sensitive file, and why>
Add when the PR touches tools/build-dist.mjs, tools/corex-sync.mjs, corex.lock, composer.* or package*.json:
Delivery impact: <what changes in the deployed artifact, or why nothing does>
-->

## Scope check (requirement work)

- [ ] Every behaviour in this PR is supported by the wording of the register row it cites, not only by the id
- [ ] Open contract items touching these ids (docs/scope/open-items.json) are cited, and none is resolved here
- [ ] The contractual stage of each id is unchanged
- [ ] Anything beyond the cited rows has been raised as a Change Request (MS-CHG-2026-028), not built

## How each acceptance criterion was verified

<!-- One line per AC row in the spec. Say how it was checked, not that it was. -->

| AC | Criterion | Verified by | Result |
|---|---|---|---|
| AC-1 | | | |

## Status, reported separately

- Workflow: <!-- complete / not complete -->
- Technical verification: <!-- verified / partly verified (what is open) / not verified -->
- Contractual acceptance: <!-- not applicable here; happens under MS-UAT-2026-027 -->

## Checks

- [ ] CI green on this PR
- [ ] Guard skills run on the diff (name them)
- [ ] Storefront changes checked in English and Arabic, right to left (the admin is English only, ADM-159)
- [ ] Money, stock, permission, refund, payment or shipment-state changes covered by automated tests
- [ ] `stack.lock.json` updated if a version changed
- [ ] Docs, `DECISIONS.md` and `PROGRESS.md` updated where relevant

## Unresolved decisions and limitations

<!-- Anything still open, and any CI limitation that applies. -->

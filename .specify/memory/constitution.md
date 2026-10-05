# Mizzey Launch Platform Constitution

**Version**: 1.1.0 | **Ratified**: pending (governance PR #235) | **Last Amended**: 2026-10-05

This constitution governs every specification, plan, task and pull request in this repository. Spec Kit reads it
through `/speckit-plan` (Constitution Check) and `/speckit-analyze`.

## Inheritance

Mizzey is a CoreX client site. The CoreX constitution v1.2.2 (CoreX repository, `.specify/memory/constitution.md`,
at the commit pinned in `corex.lock`) applies in full to code written here: principles I to X, the Guard Gate,
the Environment Gate and the Pre-Implementation Confirmation Rule. This document adds the Mizzey rules. Where the
two conflict for client-site work, this document wins. Nothing here authorises editing CoreX internals.

## Authority model

The contract documents are held outside this repository. Their order of priority is set by the Services Agreement
MS-AGR-2026-023 v1.4, section 2.1, and is not restated or changed here:

1. **The Services Agreement** governs the contractual terms and the precedence between the documents.
2. **Annex A, the Feature Register MS-ANX-2026-006 v1.5**, is the authoritative definition of contracted feature
   scope ("for anything about scope"). Its ids, scope values and stages are mirrored in
   `docs/scope/register-ids.json`, with the source hash recorded in `docs/scope/SOURCE.md`.
3. **Annex B, the Statement of Work MS-SOW-2026-024**, for anything about money or dates.
4. **The remaining annexes**, including the Functional Specification MS-SPC-2026-032 v1.2 (acceptance criteria
   per story), the Technical Design MS-TDD-2026-033 v1.2, and, once approved, the ERP Integration Specification
   (PRE-09), which governs every P1-E row.

Inside this repository, approved specifications (`specs/`) and accepted ADRs (`docs/adr/`) **implement** those
obligations. They cannot expand, reduce or override them. Where an ADR refines the Technical Design, a correction
the client should see goes through the document route. This constitution, `AGENTS.md` and `CONTRIBUTING.md` govern
how engineering work is done. They create no contractual obligation.

## Mizzey Principles

### M-1. Scope is traced, and tracing is not proof

Functional work, meaning any behaviour a client, customer or staff user can see or rely on, must trace to a
Feature Register row whose scope creates an obligation: **P1, P1-L, P1-E or DLV**. DEF, P2, P3, OUT and DEC
rows create no obligation.

A register id is **necessary but not sufficient**. Each acceptance criterion must be supported by the wording of the
row it cites. A behaviour that goes beyond that wording is out of scope, even when the id is valid. It becomes a
Change Request under MS-CHG-2026-028, or is recorded goodwill, and never a task.

### M-2. Contradictions are escalated, never resolved in code

When a detailed register row and a narrative passage disagree, the disagreement is recorded in
`docs/scope/open-items.json` with a CX id, and raised with Mustafa for the Stage 1 review. No spec, criterion,
test, code path or governance text may settle it. A spec that touches an affected id cites the CX id, and marks
dependent criteria "pending". Open at ratification: **CX-01, the Accountant role** (ROLE-06), resolved by D-10
on 5 October 2026.

**Closing a contradiction.** A contradiction is closed only by Mustafa's decision, recorded in `DECISIONS.md` and
in the item's `resolution` in `docs/scope/open-items.json`. The original evidence stays in the record, and the
resolution says whether the client has confirmed it. A resolution does not change the register. Where it needs a
row to read differently, that correction goes through the document route, and until
`docs/scope/register-ids.json` carries it no criterion traces that row as an obligation (M-1).

### M-3. P1-E waits for PRE-09

An ERP-dependent (P1-E) row gets no final acceptance criteria until PRE-09 is approved. Work on it before then is
limited to seams that hold whatever PRE-09 decides. No ERP mechanism is chosen before the ERP team's input.

### M-4. Native first

A requirement is met with WooCommerce, WPML/WCML or an approved plugin before any custom code. Custom code needs a
gap recorded with evidence (probe, verdict, source reference or failing test) in the spec's Native coverage table.

### M-5. Three lists

Every spec keeps apart (a) contractual criteria, (b) optional developer safeguards, which need approval and are
never presented to the client as deliverables, and (c) future or Option C items, which are recorded and not built.

### M-6. Contractual stage is fixed

The stage in the register (S1 or S2) is the contractual stage. When engineering work happens earlier or later,
that timing is noted in the spec. It never moves the contractual stage.

### M-7. Evidence and tests

Nothing is reported done without a verification someone else can repeat. Every task names its test. Money, stock,
permission, refund, payment and shipment-state paths always have automated tests. Acceptance-criterion statuses
are exactly `final`, `provisional`, or `pending <CX-nn | PRE-09 | OD-nn>`; no other wording is accepted. Three
states are reported separately and never merged: **workflow complete**, **technically verified**, and **contractually accepted**.
Contractual acceptance happens only through the Acceptance and UAT Plan MS-UAT-2026-027.

### M-8. Versions are pinned, upgrades are regression-tested

The tested versions of WordPress, WooCommerce, CoreX, WPML/WCML and every selected payment or shipping plugin are
recorded in `stack.lock.json`. A local runtime that passes is evidence for those versions only, not a production
compatibility guarantee. Every upgrade follows the regression path in `docs/stack-and-upgrades.md`.

### M-9. Languages

Working discussion and engineering documents are in English. The storefront is bilingual, English primary with
Arabic fully supported, right to left. The admin interface is English only (ADM-159); an Arabic admin is P2
(ADM-159a). Tests and evidence follow that split.

### M-10. A missing input blocks development only when nothing can stand in for it

A missing client or vendor value does not block development when the functionality can be built safely through
configuration, a professional working default, a placeholder, a fixture, a fake or mock adapter, an interface or
contract, sandbox credentials, or a later acceptance or launch gate. Every open input is placed in one class:

| Class | Meaning |
|---|---|
| Blocks development now | Nothing above can stand in for it, or a predecessor PBI is not delivered |
| Blocks final acceptance | The work is built and verified; contractual acceptance waits for the input |
| Blocks production or launch only | Development and staging proceed; the live site waits |
| Content or client input | Copy, imagery, names, policy text and similar, held as placeholders |
| Vendor or account input | Credentials, merchant approval, provider accounts, held behind a sandbox or a fake |
| ERP input | Waits for PRE-09 behind the adapter boundary (M-3) |
| Configurable working default | An owner-approved default the client can change later |

**This never invents client approval and never weakens contractual acceptance.** A working default is recorded
as an owner decision with `client_confirmed: false` in `docs/scope/open-items.json`, and a criterion that rests on
one is `provisional` or `pending OD-nn`, never `final`, until the client confirms it. M-1, M-3 and M-7 apply
unchanged.

## Work categories

| Category | Needs a register id | May change client-facing behaviour |
|---|---|---|
| `requirement` | Yes, at least one obligation id, cited in a spec | Yes, within M-1 |
| `internal:governance` | No | No |
| `internal:ci` | No | No |
| `internal:test-infrastructure` | No | No |
| `internal:tooling` | No | No |
| `internal:security-maintenance` | No | Only to fix a vulnerability or keep a dependency supported, with evidence |
| `internal:documentation` | No | No |

Internal work items are not client change requests, and they are not a route to new functionality. An internal
PR that adds or changes behaviour a user can see is misclassified. It is re-raised as `requirement` with a register
id, or as a Change Request.

**Path policy.** Every changed path (added, modified, renamed from or to, deleted) is classified by the explicit
allow-list `PATH_POLICY` in `tools/scope_trace.py`. Each category may touch only its permitted path classes. A path
the policy does not cover, including any new top-level application directory, is rejected for every category until
the policy is extended by an `internal:governance` PR. Feature specs are never deleted or moved.

**Extra declarations.** Governance-sensitive paths (checkers, CI, hooks, constitution, scope records, stack lock,
agent instructions) need a `Sensitive changes:` line. Build, deployment and dependency or version-lock paths also
need a `Delivery impact:` line. `security-maintenance` needs `Security evidence:` (a CVE or GHSA id, an advisory or
upgrade URL, or `local:` with the defect and how it was found, plus a regression test in the same PR) and
`Security change:`. A declaration makes a change reviewable. It does not prove the change is safe; the reviewer
decides.

**Trusted checker.** Once the checker is on `main` (after PR #235), CI runs the base branch's checker against every
PR, as well as the PR's own copy. PR #235 itself is the bootstrap: only its proposed checker exists. This does not
stop a PR from editing the workflow itself, and on the free plan nothing blocks a merge server-side. Changes to the
checker, CI, hooks, constitution and scope records are listed in the CI output for explicit review.

## Operating rules

- **Spec Kit owns specifications, clarification, plans and tasks.** `/speckit-specify`, `/speckit-clarify`,
  `/speckit-plan`, `/speckit-tasks`, `/speckit-analyze`, `/speckit-implement`. Other skills (the guard skills,
  debugging or TDD techniques) support engineering and verification. They never produce a competing plan, spec or
  task list.
- **Task-to-issue generation is disabled** until the backlog workflow is approved. The `speckit-taskstoissues`
  skill is not installed, and CI fails if it appears. The agent-context extension and skill are removed, so
  nothing rewrites `CLAUDE.md`.
- **Approval gates.** No force-push, history rewrite, issue bulk-creation or closure, Project change, paid plan,
  production probe, engagement-document edit or CoreX-internal change without Mustafa's explicit approval, recorded
  in `DECISIONS.md`.
- **Engagement documents are read-only here.** A defect in a client document goes to the document route
  (`brand-render/`, outside this repository).
- **Guard Gate.** The relevant guard skills run on the diff before a PR is marked ready.

## Governance

Amendments are made by pull request classified `internal:governance`, with a version bump (semantic: MAJOR removes
or redefines a principle, MINOR adds one, PATCH clarifies) and a `DECISIONS.md` entry.

# Mizzey Launch Platform Constitution

**Version**: 1.0.0 | **Ratified**: pending (governance PR) | **Last Amended**: 2026-09-22

This constitution governs every specification, plan, task and pull request in this repository. Spec Kit reads it
through `/speckit-plan` (Constitution Check) and `/speckit-analyze`.

## Inheritance

Mizzey is a CoreX client site. The CoreX constitution v1.2.2 (CoreX repository, `.specify/memory/constitution.md`,
at the commit pinned in `corex.lock`) applies in full to code written here: principles I to X, the Guard Gate,
the Environment Gate and the Pre-Implementation Confirmation Rule. This document adds the Mizzey rules. Where the
two conflict for client-site work, this document wins. Nothing here authorises editing CoreX internals.

## Source-of-truth hierarchy

1. **Feature Register MS-ANX-2026-006 v1.5**, Annex A to Services Agreement MS-AGR-2026-023. The only definition
   of scope. Held outside this repository; its ids, scope values and stages are mirrored in
   `docs/scope/register-ids.json`, generated with a recorded source hash (`docs/scope/SOURCE.md`).
2. Services Agreement MS-AGR-2026-023 and Statement of Work MS-SOW-2026-024.
3. Functional Specification MS-SPC-2026-032 v1.2: acceptance criteria per story.
4. Technical Design MS-TDD-2026-033 v1.2: architecture intent, refined by accepted ADRs in `docs/adr/`.
5. ERP Integration Specification (PRE-09): not yet written. Governs every P1-E row once approved.
6. This constitution, then `AGENTS.md`, then `CONTRIBUTING.md`.
7. Specs, plans and tasks in `specs/`. They implement the documents above and never override them.

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
dependent criteria "pending". Open at ratification: **CX-01, the Accountant role** (ROLE-06).

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
permission, refund, payment and shipment-state paths always have automated tests. Three states are reported
separately and never merged: **workflow complete**, **technically verified**, and **contractually accepted**.
Contractual acceptance happens only through the Acceptance and UAT Plan MS-UAT-2026-027.

### M-8. Versions are pinned, upgrades are regression-tested

The tested versions of WordPress, WooCommerce, CoreX, WPML/WCML and every selected payment or shipping plugin are
recorded in `stack.lock.json`. A local runtime that passes is evidence for those versions only, not a production
compatibility guarantee. Every upgrade follows the regression path in `docs/stack-and-upgrades.md`.

### M-9. Languages

Working discussion and engineering documents are in English. The storefront is bilingual, English primary with
Arabic fully supported, right to left. The admin interface is English only (ADM-159); an Arabic admin is P2
(ADM-159a). Tests and evidence follow that split.

## Work categories

| Category | Needs a register id | May change client-facing behaviour |
|---|---|---|
| `requirement` | Yes, at least one obligation id, cited in a spec | Yes, within M-1 |
| `internal:governance` | No | No |
| `internal:ci` | No | No |
| `internal:test-infrastructure` | No | No |
| `internal:tooling` | No | No |
| `internal:security-maintenance` | No | Only to fix a vulnerability or keep a dependency supported. States the behaviour change, if any |
| `internal:documentation` | No | No |

Internal work items are not client change requests, and they are not a route to new functionality. An internal
PR that adds or changes behaviour a user can see is misclassified, and must be re-raised as `requirement` with a
register id, or as a Change Request.

## Operating rules

- **Spec Kit owns specifications, clarification, plans and tasks.** `/speckit-specify`, `/speckit-clarify`,
  `/speckit-plan`, `/speckit-tasks`, `/speckit-analyze`, `/speckit-implement`. Other skills (the guard skills,
  debugging or TDD techniques) support engineering and verification. They never produce a competing plan, spec or
  task list.
- **Task-to-issue generation is disabled** until the backlog workflow is approved. The `speckit-taskstoissues`
  skill is not installed, and CI fails if it appears.
- **Approval gates.** No force-push, history rewrite, issue bulk-creation or closure, Project change, paid plan,
  production probe, engagement-document edit or CoreX-internal change without Mustafa's explicit approval, recorded
  in `DECISIONS.md`.
- **Engagement documents are read-only here.** A defect in a client document goes to the document route
  (`brand-render/`, outside this repository).
- **Guard Gate.** The relevant guard skills run on the diff before a PR is marked ready.

## Governance

Amendments are made by pull request classified `internal:governance`, with a version bump (semantic: MAJOR removes
or redefines a principle, MINOR adds one, PATCH clarifies) and a `DECISIONS.md` entry.

# Feature Specification: [FEATURE NAME]

**Feature Branch**: `[###-feature-name]`

**Created**: [DATE]

**Status**: Draft

**Work type**: requirement

**Input**: User description: "$ARGUMENTS"

<!--
  MIZZEY RULES FOR THIS TEMPLATE (constitution M-1 to M-6). tools/scope_trace.py checks the sections marked [checked].

  - Scope comes only from the Feature Register MS-ANX-2026-006 (docs/scope/register-ids.json). A register id is
    necessary for traceability but it is not proof that every behaviour written here is in scope: each criterion
    must be supported by the wording of the row it cites.
  - Priority below is High / Medium / Low. Never write P1, P2 or P3 as a priority: those are register scope values.
  - The admin interface is English only (ADM-159). Arabic and right to left apply to the storefront.
  - Keep three lists apart: contractual criteria, optional safeguards, future or Option C items.
  - A register contradiction is recorded in docs/scope/open-items.json and cited here by its CX id. It is never
    resolved inside a spec.
-->

## Register trace [checked]

<!--
  One row per register id this feature is owed under. Scope and Stage must equal the register exactly.
  Only obligation scopes belong here: P1, P1-L, P1-E, DLV. Rows that set limits (DEF, P2, OUT and so on) go in
  "Context rows" below.
-->

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| [ADM-00] | [P1] | [S1] | [...] |

## Context rows (no obligation)

| ID | Scope | Why it matters here |
|---|---|---|
| [ROLE-00] | [DEF] | [...] |

## Open contract items

<!-- Cite every docs/scope/open-items.json entry that touches an id above, for example "CX-01 (pending)". Write
     "None" if there are none. -->

- None

## Contractual acceptance criteria [checked]

<!--
  Every row cites at least one id from the Register trace table, and only ids from it. Criteria come from the
  Functional Specification MS-SPC-2026-032 story or the register row, quoted or closely restated. A criterion that
  waits on an open item says so in Status (for example "pending CX-01"). For P1-E ids, Status is "provisional"
  until PRE-09 is approved.
-->

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | [...] | [ADM-00] | final |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - [Brief Title] (Priority: High)

[Describe this user journey in plain language]

**Why this priority**: [Explain the value and why it has this priority level]

**Independent Test**: [How this can be tested on its own]

**Acceptance Scenarios** (each maps to an AC row):

1. **Given** [initial state], **When** [action], **Then** [expected outcome] (AC-1)

---

### Edge Cases

- What happens when [boundary condition]?
- How does the system handle [error scenario]?

## Native coverage

<!-- What WooCommerce, WPML/WCML or an approved plugin already provides, with evidence (probe id, source file and
     line, or a test). Custom code is justified only by a gap recorded here. -->

| Capability | Evidence | Status |
|---|---|---|
| [...] | [P-000 / file:line] | [VERIFIED / PREVIOUSLY TESTED / UNVERIFIED] |

## Requirements

### Functional Requirements

- **FR-001**: System MUST [...] (AC-1)

### Key Entities *(include if feature involves data)*

- **[Entity]**: [What it represents]

## Optional safeguards (not owed)

<!-- Developer-quality measures. Each needs Mustafa's approval and is never presented to the client as a
     deliverable. Write "None" if there are none. -->

| Id | Safeguard | Why | Custom code? |
|---|---|---|---|
| S-1 | [...] | [...] | [...] |

## Future or Option C items (not built)

<!-- Recorded so nothing is lost. Never built under this spec. -->

| Item | Where it sits |
|---|---|
| [...] | [register id and scope, or "Option C"] |

## Success Criteria

- **SC-001**: [Measurable, checkable outcome]

## Delivery stage

<!-- The contractual stage comes from the Register trace. When engineering work happens earlier or later than that
     stage, say so here. It never changes the contractual stage. -->

Contractual stage: [S1]. Engineering timing: [...].

## Assumptions

- [...]

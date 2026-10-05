# Pre-development deliverables: index and status

5 October 2026. Owner PBI: #266, for PRE-01, PRE-02, PRE-03a, PRE-04, PRE-05, PRE-06 and PRE-08. The other three
rows are listed for completeness with their own owners. Decided in D-12, item 3.

**What this index is.** For each row: the artefact, its document id and version, whether it exists, and whether
the client has approved it. Existence and approval are separate columns on purpose. **No approval is recorded
here that did not happen, and no date is recorded that cannot be shown.**

**What it is not.** It rewrites no signed or issued document. Where something was missing, a new versioned
companion was written beside the issued documents, and it is named as a companion.

Commercial figures are not reproduced: the repository is public (D-08), and fees and costs stay in the contract
documents.

## The seven rows of #266

| Row | Register wording | Artefact | Id and version | Exists | Engineering status | Client approval |
|---|---|---|---|---|---|---|
| PRE-01 | Technical design and architecture document | Technical Design, Architecture and Data Model | MS-TDD-2026-033, version 1.2 | Yes. In the REV4 pack as file 07, "Stage 1 review" | **Complete as issued.** Two ADRs that refine its data model are still Proposed | **Pending.** Approved on the Stage 1 Approval Record, which is blank |
| PRE-02 | Technology stack proposal with reasoning and expected operating cost | Feature Register Part Two, 2.3 and 2.4; Technical Design sections 2, 3 and 7; Statement of Work section 8 | MS-ANX-2026-006, version 1.5; MS-TDD-2026-033, version 1.2; MS-SOW-2026-024 | Yes. In the pack as files 03, 07 and 02 | **Complete as issued**, with two open items the documents declare themselves: the licensed search component, and hosting with its budget (OD-27, OD-14) | **Stack: by signature of the register**, which says the stack becomes part of the approved baseline on approval of the register. **Document: pending**, with the Stage 1 set |
| PRE-03a | Sitemap and user flows. Delivered in the Functional Specification | Functional Specification, **and the companion** [Sitemap and Principal User Flows](PRE-03a-sitemap-and-user-flows.md) | MS-SPC-2026-032, version 1.2; companion version 1.0 | Specification: yes, pack file 06. **Companion: prepared on 5 October 2026, not yet delivered to the client** | **Complete.** The specification carries the pages and journeys as traced stories; the companion makes the sitemap and the flows visible, adds no scope, and lists 38 open points it does not resolve | **Pending**, with PRE-08 |
| PRE-04 | Project broken into milestones and deliverables | Statement of Work section 4; Project Plan and Schedule | MS-SOW-2026-024; MS-PLN-2026-025, version 1.4 | Yes. In the pack as file 02 and in the reference folder | **Complete as issued.** No evidence gap found, and nothing added | **None required** by the row. The stages are agreed by signing the Statement of Work |
| PRE-05 | External integrations identified, with accounts and costs required from you | Register Section I, Part Two and Part Five; What You Need to Provide; Statement of Work section 8; Technical Design section 7; **and the companion** [External Integrations, Accounts and Inputs](PRE-05-integrations-accounts-inputs.md) | MS-ANX-2026-006, version 1.5; MS-DEP-2026-026, version 1.3; companion version 1.0 | Issued documents: yes. **Companion: prepared on 5 October 2026, not yet delivered to the client** | **Complete.** The companion gathers sixteen integrations and services in one inventory and records the three inputs no issued document itemised: the email sending service, the Google sign-in credentials and the content delivery network. It states no figure | **None required** by the row |
| PRE-06 | Requirements needing change or carrying technical risk identified, this document | The Feature Register itself: Part One 1.1 to 1.7, Part Four, Part Six. Supported by Technical Design section 10 | MS-ANX-2026-006, version 1.5 | Yes. Pack file 03, the document the client signs | **Complete as issued.** Its known defects are recorded, not edited: CX-01 to CX-06 in `docs/scope/open-items.json` | **By signature of the register** |
| PRE-08 | Functional specification, delivered in Stage 1 and approved before Stage 2 is accepted | Functional Specification: User Stories and Acceptance Criteria; Acceptance and UAT Plan | MS-SPC-2026-032, version 1.2; MS-UAT-2026-027, version 1.4 | Yes. Pack file 06, "Stage 1 review" | **Issued for review, and not rebuilt.** Corrections already known are made in the Stage 1 review at no charge | **Approval pending / sent date not evidenced** |

## PRE-08, and why no deemed approval is claimed

The Services Agreement treats anything submitted for review as approved after 10 Working Days without a written
response. **That rule is not applied here.** It runs from the date of submission, and no record on file shows the
date the pack was sent: the only copy of the covering message is an unaddressed draft. A date is not assumed.
The Statement of Work also requires the Stage 1 set to be approved in writing on the Stage 1 Approval Record, and
that record is blank.

So the status is exactly: **approval pending / sent date not evidenced.** It becomes "approved" when the Approval
Record is signed, or "deemed approved" only if a dated submission is produced and the period has run without a
response. Detail: [the PRE-08 status record](../2026-10-05-pre08-functional-specification-status.md).

**Approval of PRE-08 is an acceptance gate, not a development gate.** The register ties it to the acceptance of
Stage 2, and no clause makes a dependent implementation impossible without it.

## The three rows owned elsewhere

| Row | Owner | Status |
|---|---|---|
| PRE-03b, interface design | #250 | **Not approved, and not presented.** Structural design proceeds on neutral tokens. It is never called approved interface design, and approval waits for the brand identity (OD-01). See [the structural design plan](../2026-10-05-structural-design-plan.md) |
| PRE-07, store operations walkthrough | #267 | **Overdue in its literal timing, and not held.** Prepared, and the staging environment it needs now exists locally. It is complete only when the session takes place. See [the walkthrough package](../2026-10-05-pre07-store-operations-walkthrough.md) |
| PRE-09, ERP Integration Specification | #243 | **Not written.** The question pack is ready to send: [the ERP pack](../erp-pack/README.md). Approval waits for the ERP team's answers and the client |

## What "delivered" can be shown to mean

| Fact | Evidence |
|---|---|
| The REV4 pack exists and holds the issued documents | The pack archive, whose hash is recorded in `docs/scope/SOURCE.md` |
| The Developer signed the three signed documents on 22 September 2026 | The internal change log of the pack |
| The client signed | Reported by Mustafa on 4 October 2026. **No countersigned copy and no signature date is on file** |
| The pack was sent, and when | **Not evidenced** |
| Any Stage 1 approval by the client | **Not evidenced.** The Approval Record is blank |
| The two companions were delivered to the client | **Not delivered.** They are prepared here. Issuing them as client documents goes through the document route |

## Status of #266

| State | Where it stands |
|---|---|
| Technically complete | **Yes, for every artefact that can be produced internally.** Each of the seven rows has a named, versioned artefact |
| Delivered to the client | The five issued documents are in the pack. The two companions are not delivered |
| Contractually accepted | **No.** PRE-01, PRE-02, PRE-03a and PRE-08 wait for the Stage 1 Approval Record |

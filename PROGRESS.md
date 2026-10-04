# Mizzey: Progress

Live status. First action each session: read this, then continue from **Next**.

## Now (4 October 2026)

- Engagement: Option B, the Launch Platform. Contract pack MZ-02 REV4, register MS-ANX-2026-006 v1.5.
- Repository `Mizzey-Platform/mizzey-platform` (private), history intact.
- Governance foundation merged (PR #235, 22 Sep 2026): constitution, Spec Kit, skills, scope traceability, CI,
  trusted-checker runner, push guard.
- **The product-cost pilot is merged.** PR #237 squash-merged on 4 October 2026 as `93d647b`
  (`specs/001-product-cost-capture`; ADM-27, RPT-11, MIG-13; contractual stage S1), from the reviewed head
  `4068b32`, whose tree the squash commit matches exactly. CI on `main` green (`main-push` and `baseline` pass).
  Workflow pilot: completed. Unblocked technical functionality: verified. Contractual acceptance: pending CX-01 and
  OD-12. **ADM-27 and RPT-11 are technically verified, not contractually accepted**, and must not be described as
  fully delivered while AC-6 and AC-7 are open. The closure record, with the twelve findings it preserves, is the
  "Pilot closed" section of `specs/001-product-cost-capture/verification.md`. **The feature is not reopened.**
- Phase change: out of the pilot, into controlled Option B delivery planning. Three planning records exist and are
  awaiting review:
  - `docs/2026-10-04-option-b-backlog-structure.md`, the proposed epic and PBI structure, built from the 595
    delivery rows (P1, P1-L, P1-E, DLV) and their sequencing.
  - `docs/2026-10-04-multilingual-data-integrity-workstream.md`, the cross-feature risk matrix the pilot exposed,
    classified per area and mapped to register ids.
  - `docs/2026-10-04-erp-technical-meeting-questions.md`, the question set for the PRE-09 meeting.

## Next

1. **Probe B1, multilingual stock reduction.** The first technical measurement after the pilot. Does an order
   against the Arabic product reduce stock on the English original, or only on the Arabic post, and can one
   physical unit be sold in both languages? Same mechanism as the cost defect, worse consequence. The full
   specification (fixtures, ten steps, what is measured from four sources) is in
   `docs/2026-10-04-multilingual-data-integrity-workstream.md`. **Measure first: no stock fix is implemented until
   this probe has established the behaviour.**
2. **Probe A12** after it: which product and variation fields WPML and WCML synchronise on `save_post`, and which
   meta-only update paths bypass that. Each result classified measured working, measured defect or hypothesis. No
   fix is created for a field outside Option B.
3. **Governance PR #238**: the observed runtime versions (MySQL 8.3.0, WordPress 7.1.2 as `observed_runtime`
   beside `compatibility_pin`). Open, CI green, `corex.lock` untouched.
4. Then the first real Option B features, in the order recommended in the backlog structure document: the ERP
   adapter seam (E-ERP-1, which is P1 and not P1-E), the bilingual foundation (E-FND-1, E-FND-2), and the
   translation-group-aware inventory report (E-RPT-2, a contracted P1-L row with a measured defect).
5. Prepare for the ERP meeting from the fifteen "Must answer in the meeting" decisions in
   `docs/2026-10-04-erp-technical-meeting-questions.md`.

## Blocked or waiting

| Item | Waiting on |
|---|---|
| CX-01 Accountant role, and which staff roles hold "financial permission" (AC-6 of the cost pilot) | Stage 1 review with the client |
| OD-12 product cost basis, who enters it, and whether a deliberate zero must differ from an uncosted product (AC-7) | Client, before catalogue load |
| PRE-09 ERP Integration Specification (19 P1-E rows plus MIG-14) | Joint meeting with the ERP team, targeted week 4 |
| OD-01 brand name, logo, visual identity | Client. Blocks all design, therefore all 152 storefront rows |
| OD-27 written instruction on hosting outside Egypt | Client. Blocks all infrastructure |
| OD-15 expected product count, order volume, traffic; OD-14 monthly operating budget | Client. Infrastructure sizing |
| CR-06 a real, unmodified Amazon sample export; CR-08 / OD-26 image and content rights in writing | Client. Blocks the import specification and the image load |
| Bosta status code legend (SHIP-14, ADR-0002) | Client or Bosta |
| COD remittance cycle OD-20 (SHIP-16, ADR-0002) | Client |

The full decision list to group into the next Stage 1 review is in
`docs/2026-10-04-option-b-backlog-structure.md`.

## Deferred by decision

Closing and labelling the 232 Option C issues (D-07), a new Option B GitHub Project, bulk-creating the backlog, a
paid GitHub plan, ERP implementation and general storefront implementation. Option C material
(`scripts/stories.json`, Project #4) is kept as historical reference only.

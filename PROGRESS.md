# Mizzey: Progress

Live status. First action each session: read this, then continue from **Next**.

## Now (4 October 2026)

- Engagement: Option B, the Launch Platform. Contract pack MZ-02 REV4, register MS-ANX-2026-006 v1.5.
- Repository `Mizzey-Platform/mizzey-platform` (private), history intact.
- Governance foundation merged (PR #235, 22 Sep 2026): constitution, Spec Kit, skills, scope traceability, CI,
  trusted-checker runner, push guard.
- **The product-cost pilot is complete and approved** at head `4068b32`
  (`specs/001-product-cost-capture`; ADM-27, RPT-11, MIG-13; contractual stage S1). Workflow pilot: completed.
  Unblocked technical functionality: verified. Contractual acceptance: pending CX-01 and OD-12. The closure record,
  with the twelve findings it preserves, is the "Pilot closed" section of
  `specs/001-product-cost-capture/verification.md`. **The feature is not reopened.**
- Phase change: out of the pilot, into controlled Option B delivery planning. Three planning records exist and are
  awaiting review:
  - `docs/2026-10-04-option-b-backlog-structure.md`, the proposed epic and PBI structure, built from the 595
    delivery rows (P1, P1-L, P1-E, DLV) and their sequencing.
  - `docs/2026-10-04-multilingual-data-integrity-workstream.md`, the cross-feature risk matrix the pilot exposed,
    classified per area and mapped to register ids.
  - `docs/2026-10-04-erp-technical-meeting-questions.md`, the question set for the PRE-09 meeting.

## Next

1. **Merge PR #237** (product-cost pilot). Approved at head
   `4068b322908bbadb4260a4482ede85039c8c4afc`, CI green, mergeable, scope unchanged since review. The squash-merge
   was blocked by the local permission classifier and needs Mustafa to allow it or run it. Then verify the `main`
   commit and its CI, sync the local checkout, and confirm the runtime is clean.
2. **Pilot close-out PR** (this branch): the Store API wording correction, MIG-13 promoted from a note to a
   required migration precondition, and the pilot closure record. Opens after #237 merges.
3. **Data-integrity probe B1**: does an order against the Arabic product reduce stock on the English original, or
   only on the Arabic post? Same mechanism as the cost defect, worse consequence. Highest-priority unblocked work.
4. **Data-integrity inventory A12**: which fields WPML copies on `save_post`, and which of them a meta-only save
   can leave stale. Bounds the whole problem.
5. **Governance follow-up** (separate `internal:governance` PR): record WordPress 7.1.2 and MySQL 8.3.0 in
   `stack.lock.json`. Prepared on branch `governance/environment-version-record`.
6. Then the first real Option B features, in the order recommended in the backlog structure document: the ERP
   adapter seam (E-ERP-1, which is P1 and not P1-E), the bilingual foundation (E-FND-1, E-FND-2), and the
   translation-group-aware inventory report (E-RPT-2, a contracted P1-L row with a measured defect).

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

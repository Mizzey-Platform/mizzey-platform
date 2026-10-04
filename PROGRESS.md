# Mizzey: Progress

Live status. First action each session: read this, then continue from **Next**.

## Now (4 October 2026)

- **Repository visibility: PRIVATE**, restored 4 October after a temporary public period for external review.
  Confirmed by two independent reads.

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
- `main` is `2206dd6`: the pilot (#237), its close-out and the corrected planning package (#239), and the runtime
  version record (#238) are all merged, each with green CI.
- **Probes B1 and A12 have run** (`t17-multilingual-stock.php`, `t18-synced-fields.php`). Results, with the
  classifications, are in `docs/2026-10-04-multilingual-data-integrity-workstream.md`. Headline: stock is **not** a
  second cost defect, because WCML hooks the stock write itself; but **price is**, and a variation translation
  group can be corrupted by a batch in one process, which permits overselling. No fix implemented.
- Phase change: out of the pilot, into controlled Option B delivery planning. The planning records:
  - `docs/2026-10-04-option-b-backlog-structure.md`, the proposed epic and PBI structure, built from the 595
    delivery rows (P1, P1-L, P1-E, DLV) and their sequencing.
  - `docs/2026-10-04-multilingual-data-integrity-workstream.md`, the cross-feature risk matrix the pilot exposed,
    classified per area and mapped to register ids.
  - `docs/2026-10-04-erp-technical-meeting-questions.md`, eighteen "Must answer" decisions plus the full
    questionnaire as an appendix.
  - `docs/2026-10-04-github-project-proposal.md`, the board fields, views, templates and the first fifteen PBIs.
    A proposal: nothing created.
  - `docs/2026-10-04-public-exposure-assessment.md`, the exposure and purge-impact assessment.

## Next

1. **Decide on the history purge.** The repository was public for a period and the removed `docs/engagement/`
   files were reachable from old commits. It is private again. The assessment is
   `docs/2026-10-04-public-exposure-assessment.md`: no client personal data, no counterparty signature, no payment
   details, 0 forks; the one genuine sensitivity is the developer's own signature image in two PDFs. **Nothing has
   been rewritten.** Recommended first step regardless of the purge decision: rotate the signature asset.
2. **The price gap**, from probe A12: `regular_price` and `sale_price` do not reach the Arabic record on a
   code-level save. Same mechanism as the cost gap, on the field a customer pays. Proposed as the first PBI.
3. **A11**: does the wp-admin translation workflow produce a correct Arabic variable parent? It decides how much of
   the B1 corruption is a production concern rather than a WP-CLI artefact.
4. **The migration guard**, from probe B1: a batch that creates products in one process corrupts an existing
   translation group, which breaks variation stock synchronisation and overwrites Arabic variation titles. The
   catalogue migration is that batch.
5. ERP meeting preparation from the eighteen "Must answer" items in
   `docs/2026-10-04-erp-technical-meeting-questions.md`.
6. Then E-FND-1 and E-FND-2, the ERP adapter seam (E-ERP-1, P1 and not P1-E), and the translation-group-aware
   inventory report (E-RPT-2).

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

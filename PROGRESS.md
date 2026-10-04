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
- **The Option B delivery board exists**: https://github.com/orgs/Mizzey-Platform/projects/1, organisation-owned
  and private, with 11 fields, 6 views and the first 15 PBIs (#241 to #255). Status separates **Verified** from
  **Accepted**; #252 is the reference case, Verified and not Accepted. Project #4 and the 232 Option C issues are
  untouched.
- **Probes A11 and the price matrix have run** (`t19`, `t20`). **No fix was built from either, correctly.** A11:
  the translation-group corruption needs the WCML editor plus product creation in the same process; wp-admin,
  the WPML duplicate method and the native importer do not reproduce it, and sources-first sequencing prevents it,
  so the correction is a migration runbook invariant rather than runtime code. Price: **every launch-supported
  path already keeps the Arabic price correct**, including the scheduled-sales cron, so the native-first rule
  applies and no price synchronisation is built.
- **Git history is not rewritten** and will not be. The exposure decision is recorded as D-07: keep the history,
  keep the backup bundle, keep the repository private, and treat the old embedded signature image as exposed and
  deprecated. The purge stays documented as a future explicit decision only.
- Phase change: out of the pilot, into controlled Option B delivery. The planning records:
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

1. **One browser pass on the board**: apply the six view filters and groupings listed in
   `docs/2026-10-04-github-project-proposal.md`. The API cannot set them.
2. **Start #241**, the bilingual platform baseline. It is the only PBI in `Ready`: everything else waits on a
   client decision, on PRE-09, or on a predecessor.
3. **Obtain the client decisions**, in this order of value: **OD-01** brand identity (blocks all 152 storefront
   rows), **OD-27** hosting in writing (blocks all infrastructure), **CR-06** the real Amazon export (blocks the
   import specification), **CX-01** and **OD-12** (close the product-cost pilot contractually).
4. **The ERP meeting**, from the eighteen must-answer decisions in
   `docs/2026-10-04-erp-technical-meeting-questions.md`. It gates 19 P1-E rows plus MIG-14.
5. Then the seeded order: #242 information architecture, #245 the ERP adapter seam (P1, not P1-E), #246 the
   inventory report correction, #247 the migration sequencing invariant.

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

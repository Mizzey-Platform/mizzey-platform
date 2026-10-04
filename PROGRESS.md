# Mizzey: Progress

Live status. First action each session: read this, then continue from **Next**.

## Now (4 October 2026)

- **Repository visibility: PUBLIC**, deliberately, for an authorized external review period. Mustafa made it
  public so the live repository, pull requests and issues can be inspected independently. **This is intentional
  and authorized. Visibility is not to be changed**, and it is reconsidered only when Mustafa explicitly declares
  the review period complete. Recorded as D-08, which amends only the visibility part of D-07.
- Engagement: Option B, the Launch Platform. Contract pack MZ-02 REV4, register MS-ANX-2026-006 v1.5.
- Repository `Mizzey-Platform/mizzey-platform`, history intact and not rewritten.
- Governance foundation merged (PR #235, 22 Sep 2026): constitution, Spec Kit, skills, scope traceability, CI,
  trusted-checker runner, push guard.
- **The product-cost pilot is merged.** PR #237 squash-merged on 4 October 2026 as `93d647b`
  (`specs/001-product-cost-capture`; ADM-27, RPT-11, MIG-13; contractual stage S1), from the reviewed head
  `4068b32`, whose tree the squash commit matches exactly. CI on `main` green (`main-push` and `baseline` pass).
  Workflow pilot: completed. Unblocked technical functionality: verified. Contractual acceptance: pending CX-01 and
  OD-12. **ADM-27 and RPT-11 are technically verified, not contractually accepted**, and must not be described as
  fully delivered while the spec's AC-6 and AC-7 are open. The pilot's own evidence is its **12 scenarios, t02 to
  t16, 0 failed** on a clean baseline (`specs/001-product-cost-capture/evidence/final-suite.txt`); scenarios added
  afterwards are not product-cost acceptance evidence. The closure record, with the twelve findings it preserves,
  is the "Pilot closed" section of `verification.md`. **The feature is not reopened.**
- **`main` is `eadd2544393988aede311277ab57c64430a26953`**: the pilot (#237, squash `93d647b`), its close-out and
  corrected planning package (#239, `b7b3a98`), the runtime version record (#238, `2206dd6`), and the B1 and A12
  probes with the exposure assessment (#240, `eadd254`). Each merged with green CI. **PR #256 is open and not
  merged**, so no merge SHA for it is recorded here.
- **Probes B1 and A12 have run** (`t17-multilingual-stock.php`, `t18-synced-fields.php`). Results and
  classifications are in `docs/2026-10-04-multilingual-data-integrity-workstream.md`. B1: stock is **not** a
  second cost defect, because WCML hooks the WooCommerce stock write directly, so stock never depended on
  `save_post`. **Closed hypothesis.** A12: nine of fourteen fields do not reach the Arabic record on a code-level
  save, because thirteen of WCML's fourteen synchronisation components run from `save_post`. No fix implemented.
- **The Option B delivery board exists**: https://github.com/orgs/Mizzey-Platform/projects/1,
  "Mizzey Option B Delivery", organisation-owned, with 11 custom fields, 6 views and **the first 15 PBIs seeded,
  #241 to #255**. The remaining 45 to 55 PBIs are **not** created. Status separates **Verified** from
  **Accepted**; #252 is the reference case, Verified and not Accepted. Only **#241** is `Ready`. Project #4 and
  the 232 historical Option C issues are untouched.
- **Probes A11 and the price matrix have run** (`t19`, `t20`), and **both questions are closed as probes, with no
  fix built from either.** A11: the translation-group corruption needs the WCML translation editor **plus** product
  creation in the same process; wp-admin one-save-per-request, the WPML duplicate method and the native CSV
  importer do **not** reproduce it, and creating all sources first and translating second prevents it entirely.
  The correction is therefore a **migration sequencing invariant, not runtime code**, and it is the contracted
  outcome represented by **#247** (MIG-02, MIG-09, MIG-13, SSC-21); the probe itself stays
  `internal:test-infrastructure` and is not a requirement PBI. Price: every contracted price-maintenance path
  already keeps the Arabic price correct, including WooCommerce's scheduled-sales cron, so the native-first rule
  applies. **No price implementation exists and none is planned**; the stale-price risk on REST, WP-CLI and custom
  code is a developer-quality safeguard for any future slice that writes prices programmatically, not a PBI.
- **Git history is not rewritten and will not be.** D-07 stands on its substance: no rewrite, no force-push, no
  blob purge, the backup bundle retained, and the old embedded developer signature image exposed and deprecated
  and not reused in future signing packs. The purge stays a future explicit decision only. **Only D-07's
  visibility clause is superseded**, by D-08.
- Phase change: out of the pilot, into controlled Option B delivery. The planning records:
  - `docs/2026-10-04-option-b-backlog-structure.md`, the proposed epic and PBI structure, built from the 595
    delivery rows (P1, P1-L, P1-E, DLV) and their sequencing.
  - `docs/2026-10-04-multilingual-data-integrity-workstream.md`, the cross-feature risk matrix the pilot exposed,
    classified per area and mapped to register ids.
  - `docs/2026-10-04-erp-technical-meeting-questions.md`, eighteen "Must answer" decisions plus the full
    questionnaire as an appendix.
  - `docs/2026-10-04-github-project-proposal.md`, now the **record of the board that exists**: its fields, views,
    issue templates, and the fifteen seeded PBIs exactly as the live Project holds them.
  - `docs/2026-10-04-public-exposure-assessment.md`, the exposure and purge-impact assessment.

## Next

1. **PR #256** (the A11 and price-matrix probes, plus the record corrections) is open, CI green, and awaiting
   external review. Do not merge it until that review is complete.
2. **One browser pass on the board**: the six view filters and groupings listed in
   `docs/2026-10-04-github-project-proposal.md`. The GraphQL schema has no filter input, so the API cannot set
   them. Accepted, and no tooling is to be invented for it.
3. **Start #241**, the bilingual platform baseline, once #256 is merged. It is the only PBI in `Ready`.
4. **Obtain the client decisions**, in this order of value: **OD-01** brand identity (blocks all 152 storefront
   rows), **OD-27** hosting in writing (blocks all infrastructure), **CR-06** the real Amazon export (blocks the
   import specification), **CX-01** and **OD-12** (close the product-cost pilot contractually).
5. **The ERP meeting**, from the eighteen must-answer decisions in
   `docs/2026-10-04-erp-technical-meeting-questions.md`. It gates 19 P1-E rows plus MIG-14.
6. Then the seeded order: #242 information architecture, #246 the inventory report correction, #247 the migration
   sequencing invariant, #249 the import specification (after CR-06), #245 the ERP adapter seam.

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
| The end of the external review period, which is when repository visibility is reconsidered | Mustafa, explicitly |

The full decision list to group into the next Stage 1 review is in
`docs/2026-10-04-option-b-backlog-structure.md`.

## Deferred by decision

Closing and labelling the 232 Option C issues (D-07), creating the remaining 45 to 55 PBIs, a paid GitHub plan,
ERP implementation and general storefront implementation. Option C material
(`scripts/stories.json`, Project #4) is kept as historical reference only.

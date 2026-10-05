# Mizzey: Progress

Live status. First action each session: read this, then continue from **Next**.

## Now (5 October 2026)

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
- **`main` was `8d994351f866e38c781678da26de18078e0b7c35` before the D-11 record merged**: the D-10 record
  (#264, `3f074a9`), the inventory report correction (#265, `8d99435`), and before them the pilot (#237, squash `93d647b`), its close-out
  and corrected planning package (#239, `b7b3a98`), the runtime version record (#238, `2206dd6`), the B1 and A12
  probes with the exposure assessment (#240, `eadd254`), the A11 and price-matrix probes with three rounds of
  record corrections (#256, `7e39c4b`), the backlog-template reconciliation (#257, `b158a1c`), the
  one-accepting-owner board correction (#258, `e291ce6`), and **the bilingual platform baseline, the first real
  Option B feature (#259, squash `405a113`, merged 4 October 2026)**, its post-merge progress record (#260,
  `6bde6b4`), the stale #243 scope sentence corrected in two documents (#261, `276b50c`), and **the information
  architecture and URL structure, the second Option B feature (#262, squash `3ff7488`, merged 5 October 2026)**,
  and its post-merge progress record (#263, `c60d68b`). Each merged with green CI; `main-push` and `baseline` pass
  at `c60d68b`.
- **t17, t18, t19 and t20 are fact-finding scenarios**: they return no pass or fail verdict and record defects as
  findings. A suite line reading `0 failed` means the harness held and the contract scenarios t02 to t16 passed,
  **not** that every measured behaviour was correct.
- **Probes B1 and A12 have run** (`t17-multilingual-stock.php`, `t18-synced-fields.php`). Results and
  classifications are in `docs/2026-10-04-multilingual-data-integrity-workstream.md`. B1: stock is **not** a
  second cost defect, because WCML hooks the WooCommerce stock write directly, so stock never depended on
  `save_post`. **Closed hypothesis.** A12: nine of fourteen fields do not reach the Arabic record on a code-level
  save, because thirteen of WCML's fourteen synchronisation components run from `save_post`. No fix implemented.
- **The Option B delivery board exists**: https://github.com/orgs/Mizzey-Platform/projects/1,
  "Mizzey Option B Delivery", organisation-owned, with 11 custom fields, 6 views and **the first 15 PBIs seeded,
  #241 to #255**. The remaining 45 to 55 PBIs are **not** created. Status separates **Verified** from
  **Accepted**; #252 is the reference case, Verified and not Accepted. Project #4 and the 232 historical Option C
  issues are untouched.
- **The complete Stage 1 backlog is seeded (D-11, 5 October 2026): 74 PBIs on the board, #241 to #255 and #266 to
  #324.** 4 are `Verified` (#241, #242, #246, #252), 30 `Ready`, 40 `Blocked` on predecessor PBIs. Nine
  second-release slices are recorded and not created. **Every one of the 595 delivery rows has exactly one
  accepting owner, and each of the 572 Stage 1 rows is owned by a PBI that exists**:
  `docs/scope/backlog-ownership.json`, asserted by `tools/tests/test_backlog_ownership.py`. #244, #249 and #250
  were re-sliced because they cited rows that were not their outcome, and the register extraction was corrected
  for the customer journey rows (572 Stage 1 rows, not 562).
- **The inventory report correction is merged (#265, squash `8d99435`, 5 October 2026)**: `specs/004-inventory-report-one-item-once/`,
  RPT-10. AC-246-01 to AC-246-08 technically verified, 21 scenarios 0 failed on a clean baseline; AC-246-11
  `pending PRE-09`; not contractually accepted. Scope by D-246-1: the current standard stock report only.
- **D-10, 5 October 2026: owner decisions, CX-01 closed, blockers classified.** Constitution 1.1.0 adds M-10: a
  missing client or vendor value blocks development only when no default, placeholder, fixture, mock, contract or
  sandbox can stand in for it. **None of the owner decisions is a client confirmation.** OD-27 and OD-14 are
  production gates; development and staging run on the developer's machine, staging on synthetic data through a
  Cloudflare Tunnel. CX-01 is resolved: the Accountant role exists at launch, least privilege, refunds a separate
  capability. The ERP stays behind the adapter boundary on fixed invariants, and structural design proceeds on
  neutral tokens.
- **D-11, 5 October 2026: the pre-development closure.** Constitution 1.2.0: a spec may trace ROLE-06 only with
  every dependent criterion `pending CX-01`, so #251 can build the Accountant role while its acceptance waits for
  the client. Five further contradictions, CX-02 to CX-06, were recorded. Cash on delivery carries no
  value ceiling and no fee. PRE-01 to PRE-09 have owners and a status matrix; PRE-07, the store operations
  walkthrough, is **overdue and not held**.
- **D-12, 5 October 2026: CX-02 to CX-06 resolved by the owner, and two statuses for "pre-development
  complete".** None is a client confirmation and none makes a deferred row traceable: a restricted Marketing
  access profile only as far as MKT-21 to MKT-26 need it (not ROLE-05); a narrow shared audit-event record for
  the rows that explicitly require history (not ROLE-10); a persisted Import Run record for developer-operated
  runs (not MIG-18); ADM-12 and ADM-13 as dashboard counts with links (not ADM-120 or ADM-141); scheduling on the
  promotional banner alone (HOME-12 and ADM-62 stay P2). A criterion resting on one is `provisional` or
  `pending CX-nn`, never `final`. From now on *engineering pre-development readiness* and *contractual
  pre-development acceptance* are reported as two separate statuses.
- **Probes A11 and the price matrix have run** (`t19`, `t20`), are **merged**, and **both questions are closed as
  probes, with no fix built from either.** None of A11, A12, B1 or the price matrix is reopened. A11: the translation-group corruption needs the WCML translation editor **plus** product
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
  - `docs/2026-10-04-option-b-backlog-structure.md`, the epic and slice structure over the 595 delivery rows
    (P1, P1-L, P1-E, DLV), with the live board's field model, the exact delivery ids per register section, and an
    **ownership map assigning every one of the 595 rows to the slice that would own it**. Range shorthand is
    section context only and is never a scope citation.
  - `docs/2026-10-04-multilingual-data-integrity-workstream.md`, the cross-feature risk matrix the pilot exposed,
    classified per area and mapped to register ids.
  - `docs/2026-10-04-erp-technical-meeting-questions.md`, eighteen "Must answer" decisions plus the full
    questionnaire as an appendix.
  - `docs/2026-10-04-github-project-proposal.md`, now the **record of the board that exists**: its fields, views,
    issue templates, and the fifteen seeded PBIs exactly as the live Project holds them.
  - `docs/2026-10-04-public-exposure-assessment.md`, the exposure and purge-impact assessment.
- **The first implemented feature's records** are `specs/002-bilingual-platform-baseline/`: spec, clarification
  outcome, research (including the measured WordPress 7.1.2 lazy translation loading, evidence for 7.1.2 only),
  plan, checklist, tasks, implementation analysis, verification record, and the committed suite evidence. Two
  checks came with it: `tools/tests/test_storefront_strings.py`, which fails on hard-coded user-facing storefront
  text, and `tools/tests/test_spec_consistency.py`, which fails when the feature's artifacts contradict each
  other or when a browser row is marked verified.
- **The second implemented feature's records** are `specs/003-information-architecture-urls/`: spec, research,
  plan, checklist, tasks, implementation analysis, the URL map in both languages, the verification record, and
  the committed suite evidence. Two scenarios came with it, `t22-information-architecture.php` and
  `t23-seo-fundamentals.php`.

## Next

**No production PBI starts until Mustafa reviews the pre-development closure (D-11).**

1. **Mustafa's review of the closure.** The records are `DECISIONS.md` D-11,
   `docs/scope/backlog-ownership.json`, `docs/2026-10-05-stage1-ownership-audit.md` and
   `docs/2026-10-05-pre-deliverables-matrix.md`.
2. **Then the queue, in delivery order.** The first PBIs that are `Ready`: #243 the ERP specification's
   preparation, #244 local development and staging, #249 the import specification on a fixture, #245 the ERP
   adapter seam on mocks, #248 the order status model, #250 structural design on neutral tokens, #251 roles, #266
   the pre-development documents as an evidence and approval record, #268 the definition of done, #269 the
   cross-cutting standards. #247 follows #249, and #267, the walkthrough, follows #244.
3. **#241, #242 and #246 are merged and `Verified`, and stay open.** No criterion of any is contractually
   accepted. #241: AC-9, the browser matrix, UNEXERCISED. #242 and #246: DOD-04, manual testing on staging, not
   performed. #246: AC-246-11 `pending PRE-09`. All three wait for the staging environment of #244.
4. **Owner actions that are not engineering:** send the ERP question pack
   (`docs/2026-10-05-erp-question-pack.md`); send the Accountant Scope Clarification MS-CLR-2026-037 for the
   client's acknowledgement; hold the store operations walkthrough once staging exists
   (`docs/2026-10-05-pre07-store-operations-walkthrough.md`); sign in to Cloudflare when the tunnel is ready to
   connect. CX-02 to CX-06 are decided (D-12).
5. **One browser pass on the board**: the six view filters and groupings listed in
   `docs/2026-10-04-github-project-proposal.md`. The GraphQL schema has no filter input, so the API cannot set
   them. Accepted, and no tooling is to be invented for it.
6. **Collect the client's answers for acceptance and launch**, none of which stops development: OD-01, CR-06,
   OD-12, then the rest of the table below.

## Inputs still open, by class (D-10, constitution M-10)

**No client, vendor or ERP input blocks development of a Stage 1 PBI now.** A `Blocked` PBI is waiting for a
predecessor PBI and for nothing else; `docs/scope/backlog-ownership.json` names each predecessor. Two PBIs
outside Stage 1, #318 and #319, hold rows the register itself stages "Per PRE-09" and wait for #243.

| Class | Item | Waiting on |
|---|---|---|
| Blocks final acceptance | **AC-9 of #241** and **DOD-04 of #242 and #246**, the staging checks | The local staging environment, #244 |
| Blocks final acceptance | DOD-09, client approval of acceptance criteria, on every PBI | Client, through MS-UAT-2026-027 |
| Blocks final acceptance | OD-01 brand name, logo, visual identity: approval of the interface design | Client |
| Blocks final acceptance | CR-06 a real, unmodified Amazon sample export; CR-07 the account type | Client |
| Blocks final acceptance | OD-12 product cost basis, who enters it, zero versus missing (AC-7 of #252) | Client |
| Blocks final acceptance | The Accountant Scope Clarification MS-CLR-2026-037, prepared and **not acknowledged**, for the ROLE-06 criteria | Client |
| Blocks final acceptance | CX-02 to CX-06, resolved by the owner (D-12) and **not client-confirmed**: dependent criteria are `provisional` or `pending CX-nn` | Client confirmation of each reading |
| Blocks final acceptance | PRE-07 the store operations walkthrough, **overdue and not held**; PRE-08 approval of the Functional Specification | Client, with Mustafa |
| Blocks production or launch only | OD-27 written instruction on hosting outside Egypt, **not client-approved**; OD-14 operating budget | Client |
| Blocks production or launch only | OD-18 domain and legal entity details; CR-04 policy text; CR-05 photographs; CR-09 the full catalogue export | Client |
| Content or client input | D-242-1 to D-242-4, the page copy for IA-24 to IA-32, the final Arabic endpoint slug wording | Client |
| Content or client input | OD-08 return window; OD-09 VAT and invoicing; OD-13 authenticity and warranty policy; CR-08 / OD-26 image and content rights | Client |
| Vendor or account input | The Paymob merchant account and wallet approval; the carrier account, API key and rate card | Client and provider |
| Vendor or account input | Bosta status code legend (SHIP-14, ADR-0002); COD remittance cycle OD-20 (SHIP-16) | Client or Bosta |
| Vendor or account input | The Google, Meta and TikTok accounts | Client |
| ERP input | PRE-09 ERP Integration Specification (the 19 P1-E rows, MIG-14 among them) and AC-246-11 | Joint meeting with the ERP team, targeted week 4 |
| Configurable working default | OD-03, OD-04, OD-05, OD-15, OD-19, OD-21: owner decisions, **not client-confirmed** | Client confirmation, at any time |
| Repository visibility | The end of the external review period | Mustafa, explicitly |

CX-01 is **resolved by the owner** (D-10): the Accountant role exists at launch. The full decision list is in
`DECISIONS.md` D-10 and `docs/scope/open-items.json`.

## Deferred by decision

Closing and labelling the 232 Option C issues (D-07), creating the remaining 45 to 55 PBIs, a paid GitHub plan,
ERP implementation and general storefront implementation. Option C material
(`scripts/stories.json`, Project #4) is kept as historical reference only.

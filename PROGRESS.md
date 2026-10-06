# Mizzey: Progress

Live status. First action each session: read this, then continue from **Next**.

## Now (6 October 2026)

- **Design first: D-14 is proposed in PR #336, reviewed by the owner, and pending merge.** Functional PBI
  implementation is frozen by the owner's instruction: #249 is not started, no `006-` spec exists, and Spec Kit is
  not run for a functional PBI. The order is: signed scope and PBIs, the complete inventory of surfaces, flows and
  states, low-fidelity structural wireframes, the brand identity, high-fidelity UI and UX, the contracted working
  HTML and CSS design approved by the client, then storefront implementation. Delivery order only: the scope,
  PRE-03b, the register and every acceptance obligation are unchanged, and a wireframe is not a client deliverable.
- **`design/` is the UX source of truth, and it holds an inventory, not a design.** 154 surfaces, 22 flows, 72
  components and 34 open design inputs, each surface on its exact register rows. Of the 595 delivery rows, 409
  are carried by a surface and 186 are marked as having no surface with a reason; none is unaccounted for
  (`design/coverage.md`, generated and checked by `tools/design_inventory.py`). **No wireframe, brief or token
  exists**, and nothing committed is approved UI or UX. The signed documents stay in `final docs/`:
  `design/sources.json` points at them and copies none.
- **A surface records two PBIs: the one that accepts its rows, and the one that builds it.** No row changed
  owner. Seven surfaces are built by a PBI other than the accepting one, the order confirmation among them
  (accepted by #242, built by #255).
- **Seven design questions are decided by the owner (D-14, item 12)**: Western digits in both languages, numbered
  pagination, a one-page checkout, second-release surfaces inventoried and not wireframed in the first pass, the
  cash-collected state for staff only, reviews from signed-in customers with no verified-purchase badge, and a
  consent banner within what the selected tool supports. Owner design decisions, not client confirmations.
- **Design-dependency findings, reported and not acted on.** Read on the PBI that builds: five PBIs build a
  customer-facing surface in the first release and are marked as having no design dependency, #248, #283, #285,
  #288 and #301. Eighteen build additions to the standard administration only and stay marked `none` by owner
  ruling. `docs/scope/backlog-ownership.json` is unchanged.
- **CoreX stays a separate, pinned, disposable checkout** at v0.42.0. The merged `sites/` layout is not adopted.
  v0.43.0 is the next upgrade, in its own infrastructure PR before implementation resumes (D-14).
- **Option C material is history and is not moved yet.** `scripts/stories.json` and the two retired task
  templates under `discovery/tasks/` are marked as not to be used for design.
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
  is the "Pilot closed" section of `verification.md`. **The feature was reopened once, on 6 October 2026, for one
  repair to AC-3** (below, "#252 was repaired"); nothing else about it is reopened.
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
  #324.** After the D-12 closure: 5 are `Verified` (#241, #242, #246, #252, #266), 2 `In progress` (#243 awaiting
  ERP input, #244 open for production), 28 `Ready`, 39 `Blocked` on predecessor PBIs. Nine
  second-release slices are recorded and not created. **Every one of the 595 delivery rows has exactly one
  accepting owner, and each of the 572 Stage 1 rows is owned by a PBI that exists**:
  `docs/scope/backlog-ownership.json`, asserted by `tools/tests/test_backlog_ownership.py`. #244, #249 and #250
  were re-sliced because they cited rows that were not their outcome, and the register extraction was corrected
  for the customer journey rows (572 Stage 1 rows, not 562).
- **The inventory report correction is merged (#265, squash `8d99435`, 5 October 2026)**: `specs/004-inventory-report-one-item-once/`,
  RPT-10. AC-246-01 to AC-246-08 technically verified, 21 scenarios 0 failed on a clean baseline; AC-246-11
  `pending PRE-09`; not contractually accepted. Scope by D-246-1: the current standard stock report only.
- **The two verifications staging disproved are repaired, merged and re-run on staging (D-13, 5 October 2026).**
  Both issues went from Verified to In progress first, and came back only on the staging result.
  **#246** (PR #329, squash `f44caa0`): the correction had switched the session language to "all languages",
  which the multilingual plugin honours only inside wp-admin and WP-CLI, so it did nothing in the REST request the
  screen makes, and the scenario that passed ran in WP-CLI. The report's query now carries the plugin's own
  per-query switch, and the summary under the table counts physical items. `t26` reads the report through the
  signed-in HTTP request. **#242** (PR #331, squash `25c391e`): WooCommerce's default address word for the brand
  taxonomy is a translatable string, so an Arabic request built links nothing routed; the site now supplies
  `brand` in every language. The repair also found the collection archive answering 200 with nothing on it, now
  served with the platform's product archive template. `t28` follows every address a term archive points at.
  Staging, reset and reseeded from `25c391e`: stock pass 7 of 7, URL pass 54 of 54:
  `docs/2026-10-05-staging-regression-after-repairs.md`. Neither is contractually accepted.
- **A storefront scenario can no longer pass on a placeholder (PR #330, squash `c0a523f`).** The development
  baseline had left WooCommerce's "coming soon" page in front of every store page, and four scenarios had been
  reading it on fourteen fetches. The test baseline now opens the store, `_storefront.php` fails a scenario that
  is served a placeholder, and `t27` proves it against the real one. The suite on `main` is **24 scenarios, 0
  failed**: `docs/2026-10-05-storefront-placeholder-guard.md`.
- **The browser matrix has three rows exercised on real, current browsers**: Chrome 154, Edge 154 and the
  release Firefox 157. Playwright's Firefox build does not count (D-13). **AC-9 of #241 is still not verified**:
  Safari on a Mac, Safari on iOS and Chrome on Android are owed, on the devices or on a device cloud that really
  runs them.
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
- **The staging and demonstration environment exists (#244, PR #327, squash `b5a7bd3`, 5 October 2026)**:
  `specs/005-local-staging-environment/`, NFR-08 and NFR-09. A separate runtime on the developer's machine, on
  `http://127.0.0.1:8088`: own web server process, database, account, keys and uploads, the code of a named
  commit, invented data only, all mail captured, every screen marked. Backed up, and **restored from its backup
  and compared: PASS**. AC-244-01 to AC-244-11 technically verified; production is not provisioned (`pending
  OD-27`), so #244 is `In progress`. **It has no public address**: the tunnel needs one Cloudflare sign-in. How to
  run it: `docs/staging-environment.md`.
- **Staging found defects on its first day** (`docs/2026-10-05-staging-verification.md`), none corrected:
  #246's stock report omits an Arabic-only item outside the Arabic context and lists nothing shared inside it,
  although its scenario passes; #242's Arabic brand archive points its canonical and language links at addresses
  that answer 404; the empty cart page is 8 pixels too wide; simultaneous orders oversell the last units in
  every run (B6). #241's browser matrix was exercised on Chrome, Edge and a Firefox build, and **AC-9 is still
  not verified**: three rows need devices. The passes were scripted, in real browser windows: a person has not
  looked.
- **Staging and development did not end with the same translation settings, and now do (6 October 2026).** Both
  run the same baseline files, but WPML also works from a configuration it downloads from its publisher, and
  staging refuses outside requests. Staging was one custom field and one post type short: `_cogs_total_value`,
  the stored product cost, had no setting there. The staging reset now copies development's downloaded
  configuration, ends by comparing the two runtimes and fails on a difference (`staging.py parity`), and the
  seed switches cost capture on, which it never had: until then no product on staging held a cost and the cost
  field was not on the product form. Explained in `docs/staging-environment.md`, "The same translation settings
  as development". Internal test infrastructure, no client-facing change.
- **#252 was repaired and is `Verified` again (PR #334, squash `7856774`, 6 October 2026).** The investigation above showed that
  part of its verification rested on WPML's downloaded configuration: without WPML's setting for
  `_cogs_total_value`, a cost that is on the English product before its Arabic record is created does not reach
  the Arabic record in three of four cases. The update paths were never affected. The repair is one declaration,
  `mizzey-site/wpml-config.xml`, with the stored key, and no change to the logic of `CostTranslationSync`. On a
  clean baseline built without the download (`MIZZEY_WPML_REMOTE_CONFIG=off`) the new scenario t29 fails 1 of 4
  before it and passes 4 of 4 after, and the suite is 25 scenarios, 0 failed; on staging reset without
  development's configuration the same four cases and the wp-admin product form pass. The record that said no
  declaration was needed is corrected in `specs/001-product-cost-capture/`, "Reopened, 6 October 2026". **After
  the merge, staging was rebuilt from `main` at `7856774`: with no download present WPML holds the setting as
  copy and locked from the site's own file and t29 passes 4 of 4, and after an ordinary reset the settings
  comparison of staging and development passes. Contractual acceptance is unchanged: pending CX-01 and OD-12,
  not accepted.** The pilot and audit work on product cost ends here; the next work is #249.
- **The pre-development documents are technically complete (#266, `Verified`)**: `docs/pre-development/`. Two
  companions were written, a sitemap with the principal user flows (PRE-03a) and an inventory of integrations,
  accounts and inputs (PRE-05). Neither is delivered to the client yet. **PRE-08 reads "approval pending / sent
  date not evidenced"**, and the deemed-approval rule is not applied.
- **The ERP pack is ready to send and not sent**: `docs/erp-pack/`. The SKU is the contracted matching baseline
  (CR-18, ADM-34); the pack confirms how the SKU behaves and no longer asks what the key should be.
- **The Accountant clarification MS-CLR-2026-037 is at version 1.1**: "P1-L, S1" is the proposed classification
  of ROLE-06, effective only on signature. Not sent, not acknowledged; the signed register is untouched.
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

**Design first (D-14, pending merge in PR #336). No functional PBI starts, #249 included, until Mustafa says so.**
In order:

1. **Mustafa's ruling on the design-dependency findings** in `design/coverage.md`, section 5: the five PBIs that
   build a customer-facing surface and are marked as having no design dependency, and where the four platform
   screens no PBI builds are placed (section 4.2).
2. **Then the low-fidelity structural wireframes of the first release**, as design artefacts in `design/`, from
   generated briefs, in both reading directions, when Mustafa approves the start of that pass. Their file format
   is approved first and added to `PATH_POLICY`.
3. **The CoreX v0.43.0 upgrade**, evaluated and made in its own infrastructure PR, before application and
   storefront implementation resumes.
4. **When the PBIs that are not storefront screens resume** (#249, #245, #248 and the rest of the queue below) is
   Mustafa's decision. The queue is kept as the order they would follow.

The pre-development closure (D-12) is accepted, and the repairs staging showed were owed are merged and pass on
staging: `docs/2026-10-05-staging-regression-after-repairs.md`. No known engineering defect is open.

**Two statuses, reported separately (D-12):**

| Status | State |
|---|---|
| **Engineering pre-development readiness** | **Complete.** Normal Stage 1 development can proceed continuously; remaining external items are isolated to explicit acceptance, integration or launch gates |
| **Contractual pre-development acceptance** | **Pending.** PRE-07 is not held, PRE-03b is not approved, PRE-08 reads "approval pending / sent date not evidenced", PRE-09 is not written, and the Accountant clarification is not acknowledged |

1. **Mustafa's review of the repair report**, still owed from D-13. `DECISIONS.md` D-13,
   `docs/2026-10-05-staging-regression-after-repairs.md`, `docs/2026-10-05-storefront-placeholder-guard.md`, and
   the "Repair" sections of `specs/003-information-architecture-urls/verification.md` and
   `specs/004-inventory-report-one-item-once/verification.md`.
2. **The two corrections staging showed were owed are done.** Nothing is owed here before the queue.
3. **The queue, frozen by D-14, in the delivery order it would follow:** #249 the import specification on a fixture, #245 the ERP adapter seam
   on mocks, #248 the order status model, #250 the interface design (now the design-first work above), #251 roles, #270
   observability, #273 the data model, #275 staff accounts and the log, #276 product management, #280 order
   management. #247 follows #249. #268, #269, #271 and #272 are standing records kept alongside.
4. **Owner actions that are not engineering:**
   - **Run `cloudflared tunnel login` once.** It is the one manual step for the fixed staging address (D-13: a
     named tunnel, not a temporary one), and it needs a domain in the Cloudflare account. Everything else is
     prepared and is done after it: `docs/staging-environment.md`, "The Cloudflare Tunnel".
   - **Send the ERP pack**, after filling in the recipient and the dates: `docs/erp-pack/`.
   - **Send the Accountant clarification** MS-CLR-2026-037, version 1.1, for acknowledgement, **and the two PRE
     companions**, now rendered as client documents MS-CMP-2026-038 and MS-CMP-2026-039, for information. The
     package and a draft covering email are prepared outside this repository. Nothing has been sent.
   - **Hold the store operations walkthrough**, once the fixed address exists:
     `docs/2026-10-05-pre07-store-operations-walkthrough.md`, section 5a for the conditions and for what is shown
     as still to be completed.
   - **Look at staging**, and run the three device rows of the browser matrix on a Mac, an iPhone and an Android
     phone, or on a device cloud that really runs them.
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
| Blocks final acceptance | **AC-9 of #241**: three device rows of the browser matrix. **DOD-04 of #242 and #246**: a person's pass. The corrections staging showed were owed are merged and pass on staging | A Mac, an iPhone and an Android phone through the tunnel, or a device cloud that really runs them; Mustafa |
| Blocks final acceptance | DOD-09, client approval of acceptance criteria, on every PBI | Client, through MS-UAT-2026-027 |
| Blocks final acceptance | OD-01 brand name, logo, visual identity: approval of the interface design | Client |
| Blocks final acceptance | CR-06 a real, unmodified Amazon sample export; CR-07 the account type | Client |
| Blocks final acceptance | OD-12 product cost basis, who enters it, zero versus missing (AC-7 of #252) | Client |
| Blocks final acceptance | The Accountant Scope Clarification MS-CLR-2026-037, prepared and **not acknowledged**, for the ROLE-06 criteria | Client |
| Blocks final acceptance | CX-02 to CX-06, resolved by the owner (D-12) and **not client-confirmed**: dependent criteria are `provisional` or `pending CX-nn` | Client confirmation of each reading |
| Blocks final acceptance | PRE-07 the store operations walkthrough, **overdue and not held**, prepared with a runbook, and not held until the fixed staging address exists; PRE-08 approval of the Functional Specification, **approval pending / sent date not evidenced** | Client, with Mustafa |
| Blocks production or launch only | OD-27 written instruction on hosting outside Egypt, **not client-approved**; OD-14 operating budget | Client |
| Blocks production or launch only | OD-18 domain and legal entity details; CR-04 policy text; CR-05 photographs; CR-09 the full catalogue export | Client |
| Content or client input | D-242-1 to D-242-4, the page copy for IA-24 to IA-32, the final Arabic endpoint slug wording | Client |
| Content or client input | OD-08 return window; OD-09 VAT and invoicing; OD-13 authenticity and warranty policy; CR-08 / OD-26 image and content rights | Client |
| Vendor or account input | The Paymob merchant account and wallet approval; the carrier account, API key and rate card | Client and provider |
| Vendor or account input | Bosta status code legend (SHIP-14, ADR-0002); COD remittance cycle OD-20 (SHIP-16) | Client or Bosta |
| Vendor or account input | The Google, Meta and TikTok accounts | Client |
| ERP input | PRE-09 ERP Integration Specification (the 19 P1-E rows, MIG-14 among them) and AC-246-11. The question pack is ready to send | Mustafa sends the pack; then the joint meeting with the ERP team, targeted week 4 |
| Configurable working default | OD-03, OD-04, OD-05, OD-15, OD-19, OD-21: owner decisions, **not client-confirmed** | Client confirmation, at any time |
| Repository visibility | The end of the external review period | Mustafa, explicitly |

CX-01 is **resolved by the owner** (D-10): the Accountant role exists at launch. The full decision list is in
`DECISIONS.md` D-10 and `docs/scope/open-items.json`.

## Deferred by decision

Closing and labelling the 232 Option C issues (D-07), creating the remaining 45 to 55 PBIs, a paid GitHub plan,
ERP implementation and general storefront implementation. Option C material
(`scripts/stories.json`, Project #4) is kept as historical reference only.

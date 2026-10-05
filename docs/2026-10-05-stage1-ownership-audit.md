# Stage 1 ownership audit, 5 October 2026

The review behind the complete Stage 1 seeding (D-11). It read every delivery row of the Feature Register v1.5
against the slice that owned it, and it is the judgement part of the audit: whether a row's wording matches a
slice's outcome is something no checker can decide. The mechanical part is
`tools/tests/test_backlog_ownership.py`, which runs in CI against `docs/scope/backlog-ownership.json`.

## Status after the corrections

Everything below this section is the audit **as written, before its findings were applied**. What was applied:

| Finding | Applied |
|---|---|
| The register extraction read the wrong Stage column of the customer journey table | `tools/extract_register_ids.py` corrected and the mirror regenerated: the Stage 1 delivery rows are **572**, not 562; the second-release rows 15; the unstaged rows 8 |
| #244 cited four document and design rows | #244 owns `NFR-08, NFR-09`, and its Epic follows the register section, E-NFR |
| #250 cited the walkthrough and the functional specification | #250 owns `PRE-03b` |
| #249 cited the integrations list and the register itself | #249 owns `MIG-01, MIG-03, MIG-04, MIG-06, MIG-19` |
| Seven pre-development document rows had no correct owner | #266 owns `PRE-01, PRE-02, PRE-03a, PRE-04, PRE-05, PRE-06, PRE-08` |
| The store operations walkthrough had no owner | #267 owns `PRE-07` |
| The ten Stage 1 journey rows were held back | #322 owns them, once the extraction was corrected |
| The unstaged P1-E rows | #318 and #319 own them, with Stage `per PRE-09` |
| The dashboard figures | #294 owns ADM-10 and ADM-11, with #246's measurement as evidence |
| Questions 4 to 8 of section 5 | Recorded as open contradictions CX-02 to CX-06 in `docs/scope/open-items.json`. Not resolved |
| Question 3, cash on delivery | Decided in D-11: no value ceiling and no fee is contracted, so neither is built |

**Result: 74 PBIs on the board, 9 second-release slices recorded and not created, 595 delivery rows each owned
exactly once, 572 Stage 1 rows each owned by a PBI that exists.**

Where a later section says a slice is "held" or a count is 562, read it with this table. Slice keys such as
E-ADM-1 are resolved to issue numbers in `docs/scope/backlog-ownership.json`.

## 1. Coverage

| Measure | Count |
|---|---|
| Delivery rows in `register-ids.json` (P1, P1-L, P1-E, DLV) | 595 |
| Of which staged S1 in `register-ids.json` | 562 |
| S1 delivery rows owned by exactly one entry | 562 |
| S1 delivery rows with no owner (orphans) | 0 |
| Delivery rows with more than one owner | 0 |
| Non-obligation ids cited (DEF, P2, P3, OUT) | 0 |
| S1 entries citing a non-S1 row | 0 |
| All delivery rows, any stage, owned exactly once | 595 of 595 |

| Entries | Count | Delivery rows | Of which S1 |
|---|---|---|---|
| Existing PBIs, #241 to #255, after the three corrections | 15 | 119 | 118 |
| New Stage 1 PBIs, every row S1 | 56 | 444 | 444 |
| New PBIs whose rows are unstaged P1-E, proposed to be created now | 2 | 7 | 0 |
| Held back, not to be created now | 10 | 25 | 0 |
| **Total** | **83** | **595** | **562** |

**Zero orphans and zero duplicates against `register-ids.json`.** One qualification, which is question 1 in section 5: the extraction stages the eleven JRN rows `-`, while the signed register stages ten of them S1. Measured against the register's own stage column the S1 total is 572, of which 562 are on a PBI that exists or is proposed now and ten (JRN-01, JRN-02, JRN-03, JRN-04, JRN-05, JRN-06, JRN-07, JRN-08, JRN-09, JRN-10) have an owner, E-FND-3, that is held until the extraction is corrected.

The exact-id total across the fifteen existing PBIs moves from 126 to 119: #244 loses four rows, #249 two, #250 two and gains one.

## 2. Delivery rows whose stage is not S1, and the entry that holds each

`Stage, extracted` is the literal `register-ids.json` value. `Stage cell, register` is the text of the last Stage column of the row in the register markdown.

| Id | Scope | Stage, extracted | Stage cell, register | Entry | Created now |
|---|---|---|---|---|---|
| ADM-05 | P1-L | `S2` | S2 | E-ADM-1b Dashboard, second release: new and returning customers, conversion rate | no, held |
| ADM-07 | P1-L | `S2` | S2 | E-ADM-1b Dashboard, second release: new and returning customers, conversion rate | no, held |
| ADM-101 | P1-L | `S2` | S2 | E-ADM-6b Customers, second release: lifetime metrics and export under privacy controls | no, held |
| ADM-105 | P1-L | `S2` | S2 | E-ADM-6b Customers, second release: lifetime metrics and export under privacy controls | no, held |
| IA-23 | P1-L | `S2` | S2 | E-FND-2b Information architecture, second release | no, held |
| IA-33 | P1-L | `S2` | S2 | E-FND-2b Information architecture, second release | no, held |
| OBJ-06 | P1-L | `S2` | S2 | E-FND-4b Business objectives, second release | no, held |
| OBJ-07 | P1-L | `S2` | S2 | E-FND-4b Business objectives, second release | no, held |
| NFR-12a | P1-L | `S2` | S2 | E-NFR-1b Privacy, second release: automated data export and deletion requests | no, held |
| NOTF-14 | P1-L | `S2` | S2 | E-NOTF-1b Notifications, second release: admin-editable templates | no, held |
| PLP-11 | P1-L | `S2` | S2 | E-SF-3b Product listing, second release: filter by rating | no, held |
| ACCT-09 | P1-L | `S2` | S2 | E-SF-9b My Account, second release: reorder, notification preferences, account deletion request | no, held |
| ACCT-11 | P1-L | `S2` | S2 | E-SF-9b My Account, second release: reorder, notification preferences, account deletion request | no, held |
| ACCT-13 | P1-L | `S2` | S2 | E-SF-9b My Account, second release: reorder, notification preferences, account deletion request | no, held |
| ADM-149 | P1-E | `-` | Per PRE-09 | E-ADM-4b Inventory tasks whose method and timing PRE-09 sets | proposed |
| ADM-71 | P1-E | `-` | Per PRE-09 | E-ADM-4b Inventory tasks whose method and timing PRE-09 sets | proposed |
| ADM-73 | P1-E | `-` | Per PRE-09 | E-ADM-4b Inventory tasks whose method and timing PRE-09 sets | proposed |
| ADM-74 | P1-E | `-` | Per PRE-09 | E-ADM-4b Inventory tasks whose method and timing PRE-09 sets | proposed |
| ADM-75 | P1-E | `-` | Per PRE-09 | E-ADM-4b Inventory tasks whose method and timing PRE-09 sets | proposed |
| ADM-77 | P1-E | `-` | Per PRE-09 | E-ADM-4b Inventory tasks whose method and timing PRE-09 sets | proposed |
| ENT-04 | P1-E | `-` | Per PRE-09 | E-DATA-2 Inventory transaction records, as PRE-09 settles | proposed |
| JRN-01 | P1 | `-` | S1 | E-FND-3 The customer journey holds end to end, from discovery to after-sales | no, held |
| JRN-02 | P1 | `-` | S1 | E-FND-3 The customer journey holds end to end, from discovery to after-sales | no, held |
| JRN-03 | P1 | `-` | S1 | E-FND-3 The customer journey holds end to end, from discovery to after-sales | no, held |
| JRN-04 | P1 | `-` | S1 | E-FND-3 The customer journey holds end to end, from discovery to after-sales | no, held |
| JRN-05 | P1-L | `-` | S1 | E-FND-3 The customer journey holds end to end, from discovery to after-sales | no, held |
| JRN-06 | P1 | `-` | S1 | E-FND-3 The customer journey holds end to end, from discovery to after-sales | no, held |
| JRN-07 | P1 | `-` | S1 | E-FND-3 The customer journey holds end to end, from discovery to after-sales | no, held |
| JRN-08 | P1-L | `-` | S1 | E-FND-3 The customer journey holds end to end, from discovery to after-sales | no, held |
| JRN-09 | P1 | `-` | S1 | E-FND-3 The customer journey holds end to end, from discovery to after-sales | no, held |
| JRN-10 | P1 | `-` | S1 | E-FND-3 The customer journey holds end to end, from discovery to after-sales | no, held |
| JRN-11 | P1-L | `-` | S2 | E-FND-3b Reorder from order history, second release | no, held |
| PRE-09 | DLV | `-` | Per plan | #243 ERP Integration Specification approved (PRE-09) | exists |

33 rows: 14 staged S2 and 19 extracted as `-`. Of the `-` rows, seven are P1-E rows the register stages 'Per PRE-09', one is PRE-09 itself ('Per plan'), and eleven are the JRN rows of question 1.

Two delivery rows carry a conditional stage cell that the extraction reads as S1, and they are treated as S1 here: PAY-09 ('S1 if selected') and PAY-15 ('S1 if selected and approved'). Both are owned by E-ORD-2.

## 3. Rows cited where the register wording does not match the slice

### 3.1 Wrong owner: corrected in the dataset

| Id | Register wording, verbatim | Cited by | Described there as | Proposed owner |
|---|---|---|---|---|
| PRE-01 | Technical design and architecture document | #244 Infrastructure | 'Pre-development infrastructure deliverables' | E-PRE-7 Pre-development documents delivered and approved |
| PRE-02 | Technology stack proposal with reasoning and expected operating cost | #244 Infrastructure | 'Pre-development infrastructure deliverables' | E-PRE-7 Pre-development documents delivered and approved |
| PRE-03a | Sitemap and user flows. Delivered in the Functional Specification (PRE-08), which carries the information architecture and the end to end customer and staff journeys | #244 Infrastructure | 'The environment deliverables' | E-PRE-7 Pre-development documents delivered and approved |
| PRE-03b | Interface design, approved before storefront implementation begins. Delivered as working HTML and CSS screens under Statement of Work MS-SOW-2026-024 section 9, the design engagement [fee wording omitted here]. It is produced after the brand identity is approved and presented for approval in Stage 1. The screens are the approved artefact, so no separate wireframe stage is produced. The canonical design source is English (FIX-04a); the Arabic layouts are the right-to-left localisation of that approved design, not a separate design | #244 Infrastructure | 'The environment deliverables' | #250 Design system and interface design delivered as working HTML and CSS |
| PRE-05 | External integrations identified, with accounts and costs required from you | #249 Catalogue import specification | 'The pre-development import deliverables' | E-PRE-7 Pre-development documents delivered and approved |
| PRE-06 | Requirements needing change or carrying technical risk identified, this document | #249 Catalogue import specification | 'The pre-development import deliverables' | E-PRE-7 Pre-development documents delivered and approved |
| PRE-07 | Store operations walkthrough: a live demonstration of the actual admin environment, with the Section G10 list reviewed together and confirmed, before development begins | #250 Design system and interface design | 'the pre-development design deliverables' | E-PRE-8 Store operations walkthrough held on the live admin, with the Section G10 list confirmed |
| PRE-08 | Functional specification: every contracted requirement written as a user story with testable acceptance criteria, and the acceptance model that governs every later stage. Delivered in Stage 1 and approved before Stage 2 is accepted | #250 Design system and interface design | 'the pre-development design deliverables' | E-PRE-7 Pre-development documents delivered and approved |
| PRE-04 | Project broken into milestones and deliverables | Ownership map, E-PRE-3 Repository, CI and governance (unseeded) | 'Done in substance: the governance foundation landed 22 September' | E-PRE-7 Pre-development documents delivered and approved |
| MIG-15 | SEO fields are migrated where present in the agreed source | Ownership map, E-MIG-4 Images and alt text (unseeded) | An image row, gated on image rights | E-MIG-2 Attributes, categories, brands and SEO fields mapped into the store in the initial migration |

None of the descriptions quoted from the bodies of #244, #249 and #250 appears in the register. PRE-04's wording is a milestone breakdown, so the repository governance work does not show it done; whether an existing document satisfies it is for the owner to confirm (section 5, question 12).

### 3.2 #244: candidates for further infrastructure rows, none added

#244 keeps NFR-08 and NFR-09. Their wording: NFR-08, 'Backups / Database and media backup, retention, and a documented restore procedure'; NFR-09, 'Environments / Development, staging and production; risky changes never tested on the live site'.

| Id | Scope, stage | Register wording, verbatim | Reasoning | Verdict |
|---|---|---|---|---|
| NFR-02 | P1, S1 | Performance / Image optimisation, lazy loading, caching and CDN | Caching and a CDN are infrastructure. Image optimisation and lazy loading are theme and application work. The row cannot be closed by provisioning alone | Not provable. Stays E-NFR-1 |
| NFR-05 | P1, S1 | Security / HTTPS, secure password storage, RBAC, admin protection, input validation, rate limiting, secret management, dependency updates | HTTPS and secret management are infrastructure. Password storage, RBAC, input validation and rate limiting are application behaviour | Not provable. Stays E-NFR-1 |
| NFR-10 | P1-L, S1 | Observability / Application logs, error monitoring, integration logs, admin audit logs | Error monitoring and log retention are set up with the environments, but the row also names application, integration and admin audit logs | Not provable. Stays E-NFR-2 |
| NFR-13 | P1-L, S1 | Scalability / Growth in products, orders and integrations absorbed by resizing infrastructure and extending existing structures, rather than by re-implementing the core. Bounded by FIX-10: this is an engineering principle, not a guarantee that any future capability arrives without redesign | Names resizing infrastructure, but is an engineering principle bounded by FIX-10, not a thing provisioned | Not infrastructure. Stays E-NFR-1 |
| HND-04 | DLV, S1 | Domain, DNS, hosting and cloud access in your name | Hosting and cloud access are infrastructure in substance. The row is the act of putting them in the client's name at handover | A handover deliverable. Stays E-HND, with #244 as predecessor |
| HND-05 | DLV, S1 | Database schema, migrations, and backup and restore instructions | The backup and restore instructions are the written form of NFR-08. The row also covers schema and migrations | A handover deliverable. Stays E-HND |
| HND-07 | DLV, S1 | Staging environment plus test cases and acceptance results | The staging environment is #244's. The row hands it over with the test cases and acceptance results, which exist only at the end | A handover deliverable. Stays E-HND |
| HND-08 | DLV, S1 | Production deployment and a documented rollback procedure | Production deployment and rollback are infrastructure in substance, and the nearest candidate. The register files the row under Handover, and it cannot close before production hosting is decided (OD-27, OD-14) | Not added. Stays E-HND. Owner may move it |
| MKT-24 | P1-L, S1 | Third parties receive staging access for testing before any production change | An access rule on the staging environment, not the environment | Stays E-MKT-3 |

### 3.3 Partial matches: owner left unchanged, flagged

| Id | Register wording, verbatim | Where | What does not match | Proposal |
|---|---|---|---|---|
| INT-16 | Every integration behind an adapter with retries, logging and webhook handling | #245 ERP adapter seam | The row says every integration. #245 can evidence the ERP adapter only | Keep the owner. E-ORD-2, E-ORD-3 and E-INT-1 each carry an adapter criterion as evidence, and INT-16 is accepted last. Question 10 |
| SSC-08 | Footer content, links and columns | E-ADM-10; #253 is titled 'Header, navigation and footer' | No NAV row names a footer. SSC-08 is the only footer row and #253 does not cite it | Keep SSC-08 with E-ADM-10 and name it in #253's Dependencies, or drop 'footer' from the #253 title |
| ADM-159 | Administrative interface in English, presented consistently across the standard store screens and the screens built in this engagement | Ownership map, E-ADM-11 'Admin efficiency: bulk editing, search and export' | A language row, not bulk editing, search or export. Same register section, G10 | Keep the owner; the dataset retitles the slice to cover the section |
| ADM-160 | Admin usable on a mobile device for order status checks and stock updates, the stock part per PRE-09 | Ownership map, E-ADM-11 | A mobile-use row with a stock part per PRE-09 | As ADM-159 |
| AC-06 | Place Order clicked twice / Exactly one order created; no duplicate charge | Backlog structure, E-ACC-1 note: 'AC-06 and AC-07 are the cost pilot open items' | The register row is the double-click scenario. The pilot's open items are its own sixth and seventh criteria, CX-01 and OD-12 | Correct the note; ownership unchanged |
| AC-07 | Duplicate payment confirmation / Handled safely with no duplicate effect | The same note | The register row is the duplicate payment confirmation scenario | As AC-06 |
| MIG-14 | Stock quantity at the initial load, into your ERP or the store as PRE-09 settles | Board document and D-10: 'the 19 P1-E rows plus MIG-14' | MIG-14 is itself one of the 19 P1-E rows, so the phrase counts it twice | Correct the phrase |

Also inaccurate in the backlog structure, without an ownership consequence: its E-PRE table says PRE-09 is 'the only register row carrying no stage', while seven P1-E rows (ADM-71, ADM-73, ADM-74, ADM-75, ADM-77, ADM-149, ENT-04) also extract as `-`; and its E-FND table says every JRN row 'carries stage `-` in the register', which is true of the extraction and not of the register (question 1). The board snapshot lists #246 as Ready; the live board read on 5 October shows In progress.

## 4. Cash on delivery: the trace

**No PAY, CHK, ADM or BR row supports an order-value eligibility for a payment method, configurable payment-method conditions, or a configurable fee.** Every row in those four series was read; the rows below are every one that mentions cash on delivery, a payment method, a charge, a limit, a condition or eligibility, with the reading of each.

| Id | Scope, stage | Register wording, verbatim | Supports a value limit, a method condition or a fee? |
|---|---|---|---|
| PAY-09 | P1, S1 | Cash on Delivery capability. Built and tested. Enabled at launch only if you select it: the decision is open at OD-05, and no document should assume it | No. It contracts the capability and its launch switch, and points the open questions to OD-05 |
| PAY-10 | P2, - | COD verification by OTP or WhatsApp to reduce refusals | No, and not contracted: P2 |
| PAY-15 | P1, S1 | Mobile-wallet payment capability. Enabled at launch only if selected under OD-19 and approved for your merchant account by the payment provider | No. A method enabled or not, by selection and provider approval |
| PAY-01 | P1, S1 | Payment abstraction layer: provider changeable without rebuilding checkout | No. Provider abstraction, not method rules |
| CHK-03 | P1, S1 | Shipping method selection with cost | No. Shipping method and its cost |
| CHK-04 | P1, S1 | Payment method selection | No. Selection among the enabled methods; no condition on which are offered |
| CHK-08 | P1, S1 | Clear total breakdown including all charges | No. It obliges showing every charge that exists. It does not create a charge |
| ORD-14 | P1-L, S1 | COD Collected, Awaiting Remittance / Courier holds the cash; funds not yet received | No. A status for cash collected and not yet remitted |
| SHIP-16 | P1-L, S1 | Cash-on-delivery reconciliation: collected versus remitted recorded per order | No. Reconciliation per order |
| SHIP-01 | P1, S1 | Shipping zones and rates manageable from admin | No. Shipping zones and rates, not payment |
| SHIP-15 | P1, S1 | Rates configured as manual zones by governorate, matching your carrier rate card | No. Shipping rates by governorate |
| BR-001 | P1, S1 | Welcome discount on account creation: value, cap, expiry and eligibility all admin-configurable | No. The welcome discount's eligibility, not a payment method's |
| BR-002 | P1, S1 | Free shipping on two or more items: condition admin-configurable | No. The free shipping condition |
| ADM-111 | P1-L, S1 | Conditions, rewards, limits and exclusions, plus the combination setting in BR-004. General priority and stacking rules are PROMO-16 and PROMO-17, deferred | No. Promotion conditions and limits |
| ADM-85 | P1, S1 | Search/filter by order number, customer, phone, email, status, payment, date, carrier | No. Order filter by payment |
| ADM-86 | P1, S1 | Order detail: items, prices, discounts, customer, address, payment, shipment, notes, timeline | No. Payment shown in the order detail |
| ADM-124 | P1, S1 | Record a refund, full or partial, with its payment reference, from the standard order screen (RET-08) | No. The payment reference of a refund |

The other PAY rows (PAY-02, PAY-03, PAY-04, PAY-05, PAY-06, PAY-07, PAY-08, PAY-13, PAY-14, PAY-16, PAY-17, PAY-19) concern card data, the hosted flow, callbacks, duplicate protection, transaction records, refunds, the Paymob integration and settlement timing. The other BR rows (BR-003, BR-004, BR-005, BR-006, BR-007, BR-008, BR-009, BR-010) concern stock, promotion stacking, the price snapshot, refunds, guest checkout, the free-shipping override and a non-negative total. No other ADM or CHK row mentions payment at all. None of them places a condition on a payment method or adds a charge to one.

**Where the words 'value limits, zones, fees' do appear.** In Part Six, 'Decisions we need from you', which the register introduces as 'Business decisions, not features'. These rows carry no scope and no stage and are not in `register-ids.json`:

| Id | Register wording, verbatim | Blocks | Needed |
|---|---|---|---|
| OD-05 | Is COD offered? Value limits, zones, fees? | PAY-09 | Before checkout build |
| OD-19 | Which payment methods in the first release: cards only, or cards and wallets? | Checkout | Before checkout build |
| OD-20 | Carrier COD collection and remittance cycle | SHIP-16, reporting | Before shipping build |
| OD-29 | Add COD verification (PAY-10) to scope? (R-09) | Checkout | Before checkout build |

OD-05 asks a question. It does not state that a limit or a fee will be built.

**The Functional Specification does say it.** MS-SPC-2026-032, a lower-ranked annex than the register:

- US-07-03 Payment method selection, traces to CHK-04, PAY-09, PAY-15, PAY-14: 'Cash on delivery appears only if it is selected and configured, including any value limit, zone restriction or fee'.
- US-08-07 Cash on delivery capability, traces to PAY-09, ORD-14, SHIP-16: 'Enabling or disabling it is a configuration change, including any value limit, zone restriction or fee'.

Neither criterion is supported by the wording of a row it traces to, which constitution M-1 requires. The register and its own annex therefore disagree, and M-2 says such a disagreement is escalated, not resolved in a spec or in code (section 5, question 3).

**What the platform gives without custom code.** The native cash on delivery gateway in the local runtime (`app/wp/wp-content/plugins/woocommerce/includes/gateways/cod/class-wc-gateway-cod.php`) has six settings: enable, title, description, instructions, enable for shipping methods, accept for virtual orders. It has no order-value limit and no fee. That is a source reading, not a probe. A zone restriction is reachable through 'enable for shipping methods', because SHIP-15 defines rates as governorate zones. D-10 already records, at OD-05, that a value limit and a fee are example values only and that neither is native.

## 5. Open questions for the owner, each with a recommended default

**Settled while this was drafted, so not a question.** Decision D-246-1 (Mustafa, 5 October 2026), recorded in the working copy of `specs/004-inventory-report-one-item-once/spec.md`, which was uncommitted when read: RPT-10 is satisfied on the current standard stock report; the dashboard counts are out of #246 and belong to the owner of ADM-10 and ADM-11, with #246's measurement as evidence and a dependency; the older stock report screen has no register row. The dataset follows it: E-ADM-1 owns ADM-10 and ADM-11 and has #246 as its predecessor. An earlier read of the same file, minutes before, still showed the dashboard criterion as provisional, so the file was being edited during this work.

**1. The JRN rows are staged `-` by the extraction and S1 or S2 by the signed register.**

- Evidence: Register section A5 has the header `ID / Stage / Requirement / § / Scope / Stage`. `tools/extract_register_ids.py` takes `header.index('stage')`, the first of the two, which holds the journey step (Discovery, Find and so on), finds no S1 or S2 there and writes `-`. The last column reads S1 for JRN-01, JRN-02, JRN-03, JRN-04, JRN-05, JRN-06, JRN-07, JRN-08, JRN-09 and JRN-10 and S2 for JRN-11, and Part One 1.1 lists 'JRN-11 / ACCT-09' among the second-release rows. `check.py` reproduces the comparison. The register file's SHA-256 matches `docs/scope/SOURCE.md`.
- Recommended default: Correct the extractor to read the last Stage column, regenerate `register-ids.json` in its own governance change (S1 becomes 572, S2 15, unstaged 8), then seed E-FND-3 as a Stage 1 PBI and leave E-FND-3b with the second release. Until then do not create E-FND-3: its board Stage would read 'per PRE-09' for rows the contract stages S1. The dataset holds both entries.

**2. Seven P1-E rows are unstaged ('Per PRE-09'): ADM-71, ADM-73, ADM-74, ADM-75, ADM-77, ADM-149, ENT-04.**

- Evidence: The instruction gives every P1-E row an owner now, and forbids promoting an unstaged row to S1. The map's E-ADM-4 mixed two S1 rows with six unstaged ones.
- Recommended default: Create E-ADM-4b and E-DATA-2 now, as #243 was, with board Stage 'per PRE-09', ERP blocked yes and no acceptance criteria. The dataset marks both `create_now: true`, `stage1: false`. If the owner prefers, they wait and only the twelve S1 P1-E rows are seeded.

**3. Cash on delivery value limit and fee: the Functional Specification promises them, no register row does.**

- Evidence: Section 4.
- Recommended default: Record it as a contradiction in `docs/scope/open-items.json` and raise it at the Stage 1 review. Meanwhile build what PAY-09 words: the capability, tested, with an on and off switch, plus the native restriction by shipping method. Write no criterion for a value limit or a fee.

**4. MKT-21 and MKT-22 restrict 'the Marketing role', and the Marketing role profile, ROLE-05, is DEF.**

- Evidence: MKT-21: 'The Marketing role cannot install software, edit templates, or edit files' (P1, key). MKT-22: 'The Marketing role cannot access customer personal data beyond campaign needs' (P1-L). Part One 1.6 at ROLE-05: the standard commerce role set is applied instead. The Third-Party Access Protocol (MKT-26) also says third parties receive the Marketing role. This has the shape of CX-01.
- Recommended default: Record it as a contradiction and do not resolve it in code. In E-MKT-3, mark the criteria of MKT-21 and MKT-22 pending that item; MKT-23, MKT-24 and MKT-26 proceed.

**5. Three contracted rows need an audit trail the register defers.**

- Evidence: MKT-25: 'All third-party administrative actions recorded in the audit log' (P1-L). ENT-17: 'Audit Log / Actor, action, entity, before-and-after values, timestamp' (P1-L). ADM-50: 'Price and stock change history with user and timestamp. The stock part is P1-E (Part One 1.7)' (P1-L). Against them, ROLE-10: 'Sensitive operations logged: refunds, price changes, stock changes, permission changes' (DEF), and ADM-132 reduced by Part One 1.2 to logins, security events and order notes. Part One 1.7 confirms 'price change history is P1-L, unchanged'.
- Recommended default: Deliver ADM-50's price change history as a per-product history, since two places contract it. Treat MKT-25 and ENT-17 at ADM-132's reduced wording, mark their criteria provisional, and raise the gap as a contradiction.

**6. ENT-19 Import Run is P1 while import history, MIG-18, is DEF.**

- Evidence: ENT-19: 'Import Run / File, mapping used, row counts, outcome, actor, timestamp'. MIG-18: 'Import history: who, when, which file, row counts, outcome'. Part One 1.6 at MIG-18 offers 'The Developer's migration report for the initial load (MIG-19)' instead.
- Recommended default: One import-run record for the single Developer-performed migration, kept with the migration report. No admin screen and no history list. Raise as a contradiction.

**7. Two dashboard rows name things whose workflow is DEF.**

- Evidence: ADM-13: 'Pending returns and refunds' (P1) while the returns queue ADM-120 is DEF and no return request exists as data at launch. ADM-12: 'Orders requiring action' (P1) while the worked queue ADM-141 is DEF.
- Recommended default: Each is a count on the dashboard linking to the standard filtered order list. ADM-13 counts refunds pending only. No queue is built.

**8. SSC-09 contracts banner scheduling while section scheduling is P2.**

- Evidence: SSC-09: 'Promotional banners: create, schedule, target to a page, and remove' (P1-L). HOME-12: 'Section scheduling' (P2). ADM-62: 'Home page section scheduling' (P2).
- Recommended default: A start and an end date on the promotional banner section only. No general section scheduling.

**9. P1 rows worded on the ERP are not P1-E, so M-3 does not name them, yet their ERP half cannot be final.**

- Evidence: PLP-03, PDP-06, PDP-22, CART-10, CHK-12, BR-003, BR-007, ADM-10, ADM-11, ADM-34, ADM-146, AC-05, AC-15, and RPT-10 at P1-L.
- Recommended default: Keep the practice already used on #246, #254 and #255: the ERP half of each criterion is `pending PRE-09`, the input is classed ERP input, and the owning PBI is ERP blocked `partial`. The dataset does this.

**10. INT-16 is owned by #245, and it speaks of every integration.**

- Evidence: Section 3.3.
- Recommended default: Keep it at #245 and accept it last, on the evidence of the payment, shipping and email adapters as well. The alternative is to move INT-16 to E-NFR-1.

**11. Several P1-L rows state no reduction, in Part One 1.2 or in their own wording.**

- Evidence: Examples: AUTH-02 (register with phone number, with SMS and OTP at P2), AUTH-12, ADM-103, ADM-104, NOTF-12, NFR-10, NFR-11, ADM-04, ADM-08, CART-08.
- Recommended default: Write each criterion at the row's literal wording, met by the native or an approved-plugin capability (M-4), mark it provisional, and take the list to the Stage 1 review. For AUTH-02: the phone number is captured at registration and can be used to sign in, with no OTP.

**12. The pre-development documents: one PBI, and how much is already delivered.**

- Evidence: E-PRE-7 holds seven DLV rows. The Technical Design and the Functional Specification state that they satisfy PRE-01, PRE-02, PRE-06 and PRE-08 and were issued for review on 22 September 2026. No document cites PRE-04 or PRE-05 by id. PRE-07's row says 'before development begins', and development has begun.
- Recommended default: One PBI, E-PRE-7, as an evidence and approval record, scheduled first among the new PBIs; PRE-07 on its own, E-PRE-8, held as soon as the #244 staging environment is reachable, with its real date recorded. Splitting PRE-08 out is the alternative if its approval is expected to run long.

**13. Outcome overlaps the dataset leaves as the ownership map has them.**

- Evidence: RET-08 (E-ORD-4) and ADM-124 (E-ADM-8) describe one refund, and ADM-124 cites RET-08 in its own wording. INT-05, INT-09, INT-10 and INT-12 (E-INT-1) each have a twin in E-NOTF-1 or E-MKT-1. The MER rows, ADM-42, ADM-43, ADM-44, ADM-45 and SSC-22, SSC-23, SSC-24, SSC-25 are one mechanism seen from three slices. E-FND-1b and E-FND-4 are records, not build work.
- Recommended default: Keep the map for this seeding, as instructed, and build each shared mechanism once in the PBI its notes name. If fewer PBIs are wanted: merge E-ADM-8 into E-ORD-4, and dissolve E-INT-1 as the map already did for INT-01 and INT-02 (INT-05 to E-NOTF-1; INT-09, INT-10, INT-12 to E-MKT-1). That is two PBIs fewer and changes no row's scope.

**14. #244's Epic once it holds only NFR-08 and NFR-09.**

- Evidence: The board says an Epic follows the register's own sections. Both rows sit in section J.
- Recommended default: Change the Epic to E-NFR when the ids are corrected. The dataset keeps the live value, E-PRE, so that nothing on the board moves without the decision.

## 6. Differences from the ownership map, in full

| Change | Detail |
|---|---|
| #244 | Now `NFR-08, NFR-09`. Scope class recomputes from `mixed` to `P1` |
| #249 | Now `MIG-01, MIG-03, MIG-04, MIG-06, MIG-19`. Scope class recomputes from `mixed` to `P1-L` |
| #250 | Now `PRE-03b`. Scope class `DLV` and Stage `S1` unchanged |
| E-PRE-7, new | `PRE-01, PRE-02, PRE-03a, PRE-04, PRE-05, PRE-06, PRE-08` |
| E-PRE-8, new | `PRE-07` |
| E-PRE-3 | Dissolved: its one row, PRE-04, is in E-PRE-7 |
| E-MIG-2 | Gains MIG-15 from E-MIG-4 |
| E-ADM-4 | Split by stage: E-ADM-4 keeps `ADM-70, ADM-72`; E-ADM-4b takes `ADM-71, ADM-73, ADM-74, ADM-75, ADM-77, ADM-149` |
| E-FND-3 | Split by the register's stage: E-FND-3 keeps the ten rows the register stages S1; E-FND-3b takes JRN-11 |
| Everything else | As the map |

The map has 80 slices; the dataset has 83: one dissolved, four added.

`tools/tests/test_backlog_totals.py` reads the ownership map and `tools/tests/test_board_metadata.py` reads the board snapshot; neither reads the live board. After the board edit for #244, #249 and #250 both documents have to be regenerated in the same change, because the first test compares a seeded slice in the map with the snapshot.

Once the new PBIs have issue numbers, #255's Dependencies field should name the cart PBI (E-SF-6) and the owners of ERP-03 and ERP-05 (E-ERP-4, E-ERP-3) by number. The dataset leaves #255's predecessors as the live board has them.

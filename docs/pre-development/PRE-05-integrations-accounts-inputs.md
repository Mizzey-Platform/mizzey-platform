# External Integrations, Accounts and Inputs

Companion inventory for register row PRE-05

| | |
|---|---|
| Version | 1.0 |
| Date | 5 October 2026 |
| Status | Prepared. Not yet delivered to the client. No approval is required by the row's wording. It adds no scope and changes no cost: the issued documents govern. |
| Register row | PRE-05 (DLV, S1): "External integrations identified, with accounts and costs required from you" |
| Scope authority | Feature Register MS-ANX-2026-006 version 1.5 |
| Backlog owner | #266, pre-development documents |

## 1. How to read this

PRE-05 was delivered across several issued documents: the Feature Register (Section I, Part Two, Part Five and
Part Six), What You Need to Provide (MS-DEP-2026-026, version 1.3), the Statement of Work (MS-SOW-2026-024,
section 8) and the Technical Design (MS-TDD-2026-033, version 1.2, sections 7 to 9). This inventory gathers that
material in one place and records three inputs that none of them itemises. It edits none of them. Where this
inventory and an issued document differ, the issued document governs, and the Feature Register governs them all.

**No price, rate or estimate is repeated here.** Each cost entry says who pays, how it recurs, and where the
figure is printed.

### 1.1 The two blocking tests

- **Development-blocking** means nothing can stand in for the input: no provider sandbox or test mode, no fake
  or mock adapter, no fixture, no working default. This is constitution principle M-10.
- **Launch-blocking** means the live store cannot open without it. Each "Yes" cites the row or checklist item
  that makes it so.

An input can fail both tests and still hold up acceptance of a stage. That is a third thing, and it is noted in
the entry where it applies.

### 1.2 The fields of each entry

| Field | Meaning |
|---|---|
| Purpose | What the integration or service does for the store |
| Register rows | The rows that contract it, with scope and stage as the register mirror records them |
| Stage 1 owner | Who acts now: Developer, Client, Provider, or a pair |
| Client or provider account or input required | What has to exist, and in whose name |
| Credentials needed later | What is handed over, and by which stage or gate, in the words the sources use |
| Sandbox or mock availability | What stands in during development and staging |
| Recurring or estimated cost status | Who pays, the rhythm, and where the figure is printed. No figure |
| Development-blocking | Yes or no, by the test in 1.1 |
| Launch-blocking | Yes or no, with the row that makes it so |

### 1.3 How the ids were checked

Every feature id below was checked for scope and stage against `docs/scope/register-ids.json`, the
extracted mirror of register version 1.5. The CR, OD, EX and R ids belong to Parts One, Four, Five and Six of
the register, which the mirror does not carry; they were checked against the register text. AC, OPS and go-live
checklist references are to the Acceptance and UAT Plan, MS-UAT-2026-027.

Two general rules from the issued documents apply to every account below and are not repeated in each entry:

- "Every account is opened in the name of Mizzey, so that Mizzey owns it. The developer is given access to work
  with it, and that access is handed back at the end" (MS-DEP-2026-026 section 2; HND-04 and HND-06).
- Third-party costs are contracted and paid by the client in its own name, and the developer commits the client
  to none without prior approval (Services Agreement 5.4; Statement of Work section 8).

## 2. Summary

| # | Integration or service | Stage 1 owner | Development-blocking | Launch-blocking |
|---|---|---|---|---|
| 3.1 | The client's ERP, stock only | Developer and Client | No for the adapter seam, on a mock adapter. Work that depends on PRE-09 waits for its approval | Yes: go-live checklist item 7 |
| 3.2 | Payment provider, Paymob | Client and Provider | No | Yes: CR-01 "Launch blocked" |
| 3.3 | Shipping carrier, Bosta | Client and Developer | No | Yes: go-live checklist item 6 |
| 3.4 | Transactional email and the sending identity | Developer and Client | No | Yes: CR-12 "Launch blocked" |
| 3.5 | Google Analytics 4 and Google Tag Manager | Client | No | Yes, for what is switched on under OD-21: go-live checklist item 9 |
| 3.6 | Meta Pixel and Meta product catalogue feed | Client | No | Yes, if switched on under OD-21: go-live checklist item 9 |
| 3.7 | TikTok Pixel | Client | No | Yes, if switched on under OD-21: go-live checklist item 9 |
| 3.8 | Google Merchant Center product feed | Client | No | No: no row or checklist item names it |
| 3.9 | Google Search Console and Bing Webmaster verification | Client | No | No: no row or checklist item names it |
| 3.10 | Google sign-in credentials | Developer and Client | No | Yes: AC-19, through go-live checklist item 1 |
| 3.11 | Licensed components: WPML and the others in Technical Design section 7 | Developer and Client | No | Yes for the translation component, through AC-12. No for the rest |
| 3.12 | Content delivery network | Developer, then Client | No | No: no row or checklist item names it |
| 3.13 | Production hosting and backups | Client | No | Yes: CR-10, go-live checklist items 8 and 10 |
| 3.14 | Domain, DNS and the TLS certificate | Client | No | Yes: CR-12 "Launch blocked", go-live checklist item 8 |
| 3.15 | Code repository | Developer and Client | No | No: no row or checklist item names it |
| 3.16 | Local staging and demonstration, with the Cloudflare Tunnel | Developer | No | No |

No client, vendor or ERP input blocks development of a Stage 1 backlog item on 5 October 2026 (`PROGRESS.md`,
"Inputs still open, by class"). That statement rests on owner decisions recorded in D-10, which are not client
confirmations (section 5.3).

## 3. The inventory

### 3.1 The client's ERP, stock only

| Field | Entry |
|---|---|
| Purpose | The ERP is the source of truth for stock, and a sale on the store reduces the stock in the ERP. **Only stock is integrated** (EX-35). No mechanism is chosen before the ERP Integration Specification, PRE-09 |
| Register rows | ERP-01, ERP-02, ERP-04, ERP-05, ERP-10 (P1, S1). ERP-03, ERP-06, ERP-07, ERP-08 (P1-E, S1). ERP-09 (OUT). INT-16 (P1, S1). PRE-09 (DLV). Client rows CR-16, CR-17, CR-18. Decisions OD-16, OD-41, OD-42. Risk R-18. Exclusions EX-05, EX-35, EX-37 |
| Stage 1 owner | **Developer and Client.** Developer: the question pack and the preparation of the specification (#243), and the adapter seam on a mock adapter (#245). Client: a named ERP technical contact ("At kickoff"), the stock requirements, and the ERP developers at the joint technical meeting (CR-17, "target: week 4") |
| Client or provider account or input required | No account is opened by the developer. Inputs: the named contact; the ERP developers at the meeting; the client's written review of the specification within the review period of Services Agreement 6.4; the ERP test environment and interface documentation, with test data using the real SKUs (CR-16); every sellable product and variant carrying a SKU that matches the ERP (CR-18); the live ERP |
| Credentials needed later | "Access for the store", "As agreed in the ERP Integration Specification", "With the test environment" (MS-DEP-2026-026 section 5). The test environment "By the date agreed in PRE-09" (CR-16). The live ERP "Before stage 5". What the access consists of is not decided: interfaces and authentication are pending in the specification (register I2.2) |
| Sandbox or mock availability | A mock adapter behind the stock adapter seam, on the fixed invariants of D-10 item 5. Later, the ERP developers' test environment: ERP-10 requires testing "against a test environment rather than live stock" |
| Recurring or estimated cost status | The integration work is included in the fee (ERP-10, OD-16). The cost of building, hosting and running the ERP is the client's and is not itemised (Services Agreement 5.4; Statement of Work 7.2) |
| Development-blocking | **No** for the adapter seam and for everything outside the ERP-dependent rows: the mock adapter stands in. **The work that depends on the approved specification does not begin before its approval**, by PRE-09's own words, "before dependent work begins". That covers the 19 P1-E rows, MIG-14 among them, none of which can be final before it |
| Launch-blocking | **Yes.** Go-live checklist item 7: accepted against the approved specification "and connected to the live ERP". MS-DEP-2026-026 section 5: "Launch waits, unless a separate written agreement says otherwise". ERP-09 contracts no interim operation |

### 3.2 Payment provider, Paymob

| Field | Entry |
|---|---|
| Purpose | Card payments with 3-D Secure, mobile wallets where selected and approved, and refunds, through a hosted and tokenised flow with verified callbacks |
| Register rows | INT-01 (P1, S1). PAY-01, PAY-02, PAY-03, PAY-04, PAY-05, PAY-06, PAY-07, PAY-08, PAY-13, PAY-14, PAY-16, PAY-19 (P1, S1). PAY-17 (P1-L, S1). PAY-15 (P1 capability, conditional on OD-19). PAY-09 (P1 capability, conditional on OD-05). NFR-06 (P1, S1). Client row CR-01. Decisions OD-06, OD-19, OD-05. Risks R-06, R-08 |
| Stage 1 owner | **Client and Provider.** The client submits the merchant application with its commercial registration and tax card (kickoff checklist item 3). Paymob verifies the documents and then enables each live method individually. The developer holds no action on the account |
| Client or provider account or input required | An approved payment merchant account in the name of Mizzey (CR-01). CR-01 is written provider-neutral; Paymob is the developer recommendation at OD-06 |
| Credentials needed later | Not itemised by name in any issued document: CR-01 names the account only. Timing words: "Start first: longest lead item" (CR-01); "An approved payment merchant account" is needed before Stage 4 starts (Project Plan 3.4); "live, not in test mode, with a real transaction verified" at go-live (checklist item 5). Secrets are kept out of the repository (Technical Design section 9) |
| Sandbox or mock availability | Paymob in test mode (local environments plan; Project Plan 3.4, "integrated in test mode"). No payment plugin is installed on the local runtime yet: `stack.lock.json` records Paymob as "not-selected". Whether test mode can be used before the merchant account is approved is not stated in any source read |
| Recurring or estimated cost status | A processing charge per order, set by the provider and taken from sales, not a subscription. The rate is printed in Statement of Work section 8. The same section records no commerce platform fee |
| Development-blocking | **No.** The payment abstraction (PAY-01) is built against test mode or a fake adapter |
| Launch-blocking | **Yes.** CR-01: "Launch blocked". Go-live checklist item 5 |

Cash on delivery (PAY-09) needs no payment account. It is built and tested, and enabled at launch only if the
client selects it (OD-05). Collection and remittance run through the carrier (entry 3.3, SHIP-16, OD-20).

### 3.3 Shipping carrier, Bosta

| Field | Entry |
|---|---|
| Purpose | Individual shipments created from the order, tracking codes returned, pickup requests, carrier status reflected on the order through an explicit mapping, and cash on delivery collected and remitted |
| Register rows | INT-02 (P1-L, S1). SHIP-08, SHIP-09, SHIP-12, SHIP-13, SHIP-16 (P1-L, S1). SHIP-01, SHIP-03, SHIP-14, SHIP-15, SHIP-17 (P1, S1). SHIP-10, SHIP-11 (DEF, not included). Client row CR-03. Decisions OD-07, OD-20. Risk R-08 |
| Stage 1 owner | **Client and Developer.** Client: opens the carrier account. Developer: the status mapping needs the Bosta status code legend, which is not yet obtained (`stack.lock.json`; `PROGRESS.md` puts it with "Client or Bosta") |
| Client or provider account or input required | A carrier account in the name of Mizzey, an API key, and the rate card by governorate (CR-03). The carrier's cash on delivery collection and remittance cycle (OD-20). The Bosta status code legend. CR-03 is written provider-neutral; Bosta is the developer recommendation at OD-07 |
| Credentials needed later | The API key. "Before shipping build" (CR-03); "Before stage 4" (MS-DEP-2026-026 section 2; Project Plan section 4). Live "with a real shipment created" at go-live (checklist item 6) |
| Sandbox or mock availability | A fake adapter where no sandbox exists (local environments plan). The Bosta plugin, version 4.5.7, is installed and tested on the local runtime, with its status mapping dormant in that version (`stack.lock.json`). Whether Bosta offers a sandbox is not stated in any source read |
| Recurring or estimated cost status | Courier charges per parcel, on the carrier rate card, taken from sales (Statement of Work section 8). No account or plugin fee is itemised in any issued document |
| Development-blocking | **No.** The carrier abstraction is built against the installed plugin and a fake adapter |
| Launch-blocking | **Yes.** Go-live checklist item 6. CR-03 gives the consequence as "Checkout cannot be completed" |

### 3.4 Transactional email and the sending identity

| Field | Entry |
|---|---|
| Purpose | The customer notifications contracted at launch, by email only (OD-11), and the account emails: verification, password reset and the sign-in link |
| Register rows | INT-05 (P1, S1). NOTF-01, NOTF-02, NOTF-03, NOTF-04, NOTF-06, NOTF-11, NOTF-13 (P1, S1). NOTF-08, NOTF-12 (P1-L, S1). NOTF-14 (P1-L, S2). AUTH-04, AUTH-05 (P1, S1). AUTH-12 (P1-L, S1). Client row CR-12. Decisions OD-11 (confirmed), OD-18, OD-14 |
| Stage 1 owner | **Developer and Client.** Developer: name the sending service, which no issued document does (section 4.1). Client: the business email domain and the sending address |
| Client or provider account or input required | A business email domain, "For customer notifications to send reliably" (MS-DEP-2026-026 section 2). A sender email (CR-12), which OD-18 calls the sending email address. **A sending service account: not stated in any issued document.** Sending domain authentication: not stated in any issued document |
| Credentials needed later | Sending service credentials and sending domain authentication: not itemised in any issued document. Timing words for what is itemised: the business email domain "Before stage 4" (MS-DEP-2026-026); the sender email "Before launch" (CR-12, OD-18) |
| Sandbox or mock availability | A local mail catcher. All outgoing mail is captured and no message leaves the machine (local environments plan) |
| Recurring or estimated cost status | **Not itemised** in Statement of Work section 8 or in Services Agreement 5.4. OD-14 names email among the recurring costs that are the client's, without a figure |
| Development-blocking | **No.** The mail catcher stands in |
| Launch-blocking | **Yes.** CR-12, sender email: "Launch blocked". OPS-02 and AC-14 require the standard refund email to be sent, and go-live checklist items 1 and 2 require both to pass |

### 3.5 Google Analytics 4 and Google Tag Manager

| Field | Entry |
|---|---|
| Purpose | Measurement with the e-commerce event set. Tag Manager is the sanctioned route for every tracking script (MKT-23) |
| Register rows | INT-09 (P1, S1). MKT-01, MKT-23 (P1, S1). MKT-09, MKT-10 (P1-L, S1). The event rows of register Section L at their own scopes, EVT-14 (P1, S1) among them, with EVT-19 (DEF) not included. EVT-20 (DLV, S1). DOD-05 (P1, S1). Decision OD-21 |
| Stage 1 owner | **Client**: opens the Google account. The developer's Stage 1 part is the Event Tracking Plan (EVT-20), which needs no account |
| Client or provider account or input required | The Google analytics account, in the name of Mizzey, with the developer given access (MS-DEP-2026-026 section 2, the row "Analytics and advertising accounts", detailed as "Google, Meta, TikTok, as applicable to your launch decision"). The decision at OD-21 on what is switched on at launch |
| Credentials needed later | Access to the analytics property and the tag container. Not itemised by name in any issued document. "Before stage 4" (MS-DEP-2026-026 section 2; OD-21) |
| Sandbox or mock availability | Not stated for this provider. The local environments plan puts "any other provider" behind a fake adapter where no sandbox exists. MS-DEP-2026-026 records that without the accounts "Tracking cannot be verified" |
| Recurring or estimated cost status | Not itemised in any issued document. Running campaigns is excluded (EX-18) |
| Development-blocking | **No** |
| Launch-blocking | **Yes, for whatever is switched on under OD-21.** Go-live checklist item 9: "Analytics and advertising tracking verified" |

### 3.6 Meta Pixel and Meta product catalogue feed

| Field | Entry |
|---|---|
| Purpose | Advertising measurement on Facebook and Instagram, and the product feed that dynamic retargeting needs |
| Register rows | INT-10 (P1, S1). MKT-02 (P1, S1). MKT-05 (P1-L, S1). **Not contracted:** the server-side Meta Conversions API, INT-11 and MKT-08 (P2) |
| Stage 1 owner | **Client**: opens the Meta account |
| Client or provider account or input required | The Meta account, in the name of Mizzey (MS-DEP-2026-026 section 2). Which Meta asset holds the catalogue is not stated in any issued document. The decision at OD-21 |
| Credentials needed later | The pixel identifier and access to the catalogue. Not itemised by name in any issued document. "Before stage 4" (MS-DEP-2026-026 section 2; OD-21) |
| Sandbox or mock availability | Not stated for this provider. A fake adapter where no sandbox exists (local environments plan). By inference, not from a source: the feed is generated by the store without the account, and validating it with Meta needs the account |
| Recurring or estimated cost status | Not itemised in any issued document. Running campaigns is excluded (EX-18) |
| Development-blocking | **No** |
| Launch-blocking | **Yes, if switched on under OD-21.** Go-live checklist item 9. No row makes the catalogue feed a launch gate by itself |

### 3.7 TikTok Pixel

| Field | Entry |
|---|---|
| Purpose | Advertising measurement on TikTok with the standard e-commerce events |
| Register rows | MKT-03 (P1, key, S1). There is no INT row for TikTok. **Not contracted:** the TikTok catalogue feed, MKT-07 (P2) |
| Stage 1 owner | **Client**: opens the TikTok account |
| Client or provider account or input required | The TikTok advertising account, in the name of Mizzey (MS-DEP-2026-026 section 2). The decision at OD-21 |
| Credentials needed later | The pixel identifier. Not itemised by name in any issued document. "Before stage 4" (MS-DEP-2026-026 section 2; OD-21) |
| Sandbox or mock availability | Not stated for this provider. A fake adapter where no sandbox exists (local environments plan) |
| Recurring or estimated cost status | Not itemised in any issued document. Running campaigns is excluded (EX-18) |
| Development-blocking | **No** |
| Launch-blocking | **Yes, if switched on under OD-21.** Go-live checklist item 9 |

### 3.8 Google Merchant Center product feed

| Field | Entry |
|---|---|
| Purpose | The product feed that Google Shopping listings need |
| Register rows | INT-12 (P1-L, S1). MKT-06 (P1-L, key, S1) |
| Stage 1 owner | **Client**: opens the account |
| Client or provider account or input required | A Google Merchant Center account. **Not named separately in any issued document**: MS-DEP-2026-026 lists "Google" once, under analytics and advertising accounts. The backlog record for #284 names it as an account input |
| Credentials needed later | Access to the Merchant Center account, to submit and validate the feed. Timing not stated for this account; the general line is "Before stage 4" |
| Sandbox or mock availability | Not stated in any source read. By inference: the feed is generated by the store without the account, and validating it with Google needs the account |
| Recurring or estimated cost status | Not itemised in any issued document. Technical Design section 7 records free options for the product feed component |
| Development-blocking | **No** |
| Launch-blocking | **No.** No row or go-live checklist item names it. It remains a P1-L obligation |

### 3.9 Google Search Console and Bing Webmaster verification

| Field | Entry |
|---|---|
| Purpose | Site ownership verified with both search engines, so sitemaps can be submitted and indexing checked |
| Register rows | **Required by MKT-17 (P1, S1)**: "Google Search Console and Bing Webmaster verification". Related: MKT-15 (P1, S1) |
| Stage 1 owner | **Client**: opens the accounts. Nothing is needed in Stage 1 |
| Client or provider account or input required | A Google Search Console property and a Bing Webmaster account. **Neither is named in MS-DEP-2026-026.** The backlog record for #287 names both as account inputs |
| Credentials needed later | Access to both, or the verification values they issue. Timing not stated in any issued document. The Statement of Work places SEO work in Stage 4 |
| Sandbox or mock availability | None needed for development. By inference, not from a source: the verification itself needs the accounts and the live domain |
| Recurring or estimated cost status | Not itemised in any issued document |
| Development-blocking | **No** |
| Launch-blocking | **No.** No row or go-live checklist item names it. It remains a P1 obligation |

### 3.10 Google sign-in credentials

| Field | Entry |
|---|---|
| Purpose | A customer registers and signs in with a Google account, and one email address always resolves to one account |
| Register rows | AUTH-10 (P1, key, S1). AUTH-13 (P1, key, S1). CART-15 (P1, S1). AC-19 (P1, key, S1) |
| Stage 1 owner | **Developer and Client.** Developer: settle whether the component is a plugin or built (Technical Design section 3, row 13) and state exactly which credentials it needs. Client: hold the Google account they are issued under |
| Client or provider account or input required | Credentials issued by Google for the sign-in. **Not stated in any issued document** (section 4.2). Recorded here as a client-owned input under the general rule of MS-DEP-2026-026 section 2 |
| Credentials needed later | The sign-in credentials. No timing words in any issued document. The backlog record for #305 classes them as a vendor or account input |
| Sandbox or mock availability | Not stated for this provider. A fake adapter where no sandbox exists (local environments plan) |
| Recurring or estimated cost status | The component: free options available (Technical Design section 7). The credentials: not itemised in any issued document |
| Development-blocking | **No** |
| Launch-blocking | **Yes.** AC-19 is in the acceptance script, and go-live checklist item 1 requires every row of that script to pass |

### 3.11 Licensed components: WPML and the others in Technical Design section 7

| Field | Entry |
|---|---|
| Purpose | WPML delivers English and Arabic. A search enhancement component is used only if a licensed one is chosen for the curated Arabic synonym and correction list. Google sign-in, redirects, two-factor sign-in and product feeds use components with free options |
| Register rows | Translation: FIX-04, NFR-04 (P1, S1). Search: SRCH-06 (P1-L, S1), SRCH-07 (P1, S1). Others: AUTH-10 (P1, S1), MKT-13 (P1, S1), ADM-131 (P1, S1), MKT-05, MKT-06 (P1-L, S1). Risk R-14 |
| Stage 1 owner | **Developer and Client.** Developer: confirm the search decision, which Statement of Work section 8 says is "Confirmed at technical design" and Technical Design section 3 leaves as "Decision pending". Client: buys each licence in its own name, after approving its actual price |
| Client or provider account or input required | A vendor licence account in the name of Mizzey for WPML (R-14; Statement of Work section 8), and for a search component only if one is chosen |
| Credentials needed later | The licence registration for the production site. Timing not stated in any issued document. "Each is confirmed at its actual price before anything is bought" (Statement of Work section 8) |
| Sandbox or mock availability | A local test copy: WPML 4.9.7 with WooCommerce Multilingual 5.5.7, tested on the local runtime (`stack.lock.json`, which notes that the production licence is held by the client) |
| Recurring or estimated cost status | Recurring yearly, paid by the client in its own name, itemised in Statement of Work section 8 and Technical Design section 7. The search component and the optional paid plugin tiers are itemised there as ranges that apply only if chosen |
| Development-blocking | **No** |
| Launch-blocking | **Yes for the translation component**: the bilingual storefront is acceptance row AC-12, which go-live checklist item 1 requires to pass. No issued document gives a date for the licence purchase. **No** for the others |

### 3.12 Content delivery network

| Field | Entry |
|---|---|
| Purpose | Part of the contracted performance work: image optimisation, lazy loading, caching and a content delivery network |
| Register rows | NFR-02 (P1, S1) |
| Stage 1 owner | **Developer, then Client.** Developer: name the provider and say whether it needs an account, which no issued document does (section 4.3). Client: the account, if one is needed, under the general rule |
| Client or provider account or input required | **Not stated in any issued document.** No provider is named. Cloudflare appears in the engineering records only as the tunnel for local staging (entry 3.16), not as a production service |
| Credentials needed later | Not stated in any issued document. It follows the production hosting decision (entry 3.13) |
| Sandbox or mock availability | None needed. Development and staging run without one |
| Recurring or estimated cost status | **Not itemised** in Statement of Work section 8, Services Agreement 5.4 or Technical Design section 7 |
| Development-blocking | **No** |
| Launch-blocking | **No.** NFR-02 is a P1 obligation, but no row or go-live checklist item makes the network a launch gate |

### 3.13 Production hosting and backups

| Field | Entry |
|---|---|
| Purpose | The live environment, its backups and a tested restore. Cloudways is the recommended host on technical grounds, and it sits outside Egypt (R-03) |
| Register rows | NFR-08, NFR-09 (P1, S1). HND-04, HND-05, HND-08 (DLV, S1). Client row CR-10. Decisions OD-27, OD-14, OD-15. Risk R-03. Exclusion EX-20 |
| Stage 1 owner | **Client**: the written instruction on hosting location, given after any advice the client considers necessary, and the operating budget. The developer provisions nothing before that instruction |
| Client or provider account or input required | The written hosting instruction (CR-10, OD-27). The operating budget (OD-14). Confirmation or correction of the sizing assumption (OD-15). Then a hosting account in the name of Mizzey (HND-04) |
| Credentials needed later | Hosting and cloud access. "Before infrastructure setup" (CR-10, OD-27, OD-14); "Before stage 2" (MS-DEP-2026-026 section 2) |
| Sandbox or mock availability | Development and staging run on the developer's machine, staging on synthetic data only (D-10 item 2; local environments plan) |
| Recurring or estimated cost status | Recurring monthly, paid by the client in its own name, itemised in Statement of Work section 8. Backups are a separate monthly line there. The budget itself is open at OD-14 |
| Development-blocking | **No**, by the owner decision in D-10, which is not a client confirmation |
| Launch-blocking | **Yes.** CR-10: "Infrastructure cannot be provisioned". Go-live checklist items 8 and 10 |

### 3.14 Domain, DNS and the TLS certificate

| Field | Entry |
|---|---|
| Purpose | The address of the live store, the records that point it at the host, and HTTPS throughout |
| Register rows | NFR-05 (P1, S1). HND-04 (DLV, S1). Client row CR-12. Decision OD-18 |
| Stage 1 owner | **Client**: registers or confirms the domain |
| Client or provider account or input required | The domain, "registered in the name of Mizzey" (MS-DEP-2026-026 section 2). HND-04 hands over DNS in the client's name; no issued document lists DNS access as an input. The source of the certificate is not stated in any issued document |
| Credentials needed later | Access to the domain's DNS. "Before stage 5" (MS-DEP-2026-026 section 2); "Before launch" (CR-12, OD-18) |
| Sandbox or mock availability | None needed. Staging is reached through the tunnel (entry 3.16) |
| Recurring or estimated cost status | The domain: recurring yearly, paid by the client in its own name, listed in Statement of Work section 8 as variable. The certificate: not itemised in any issued document |
| Development-blocking | **No** |
| Launch-blocking | **Yes.** CR-12: "Launch blocked". Go-live checklist item 8: "Domain pointed, certificate valid, both languages reachable" |

### 3.15 Code repository

| Field | Entry |
|---|---|
| Purpose | Mizzey-specific code delivered to a repository the client owns, as it is written |
| Register rows | HND-01 (DLV, S1) |
| Stage 1 owner | **Developer and Client.** MS-DEP-2026-026 section 2: "Owned by Mizzey. Created for you if you prefer". Kickoff checklist item 7 |
| Client or provider account or input required | A repository account owned by Mizzey. The working repository exists as `Mizzey-Platform/mizzey-platform`. **Whether that organisation is "in the name of Mizzey" in the contract's sense is not confirmed in any source read** |
| Credentials needed later | Owner access for the client. "Before stage 2" (MS-DEP-2026-026 section 2) |
| Sandbox or mock availability | None needed: the repository is in use |
| Recurring or estimated cost status | Not itemised in any issued document. A paid plan is listed in `PROGRESS.md` as deferred by decision |
| Development-blocking | **No** |
| Launch-blocking | **No.** No row or go-live checklist item names it. MS-DEP-2026-026 gives the consequence as "Code cannot be delivered to you as it is written" |

### 3.16 Local staging and demonstration, with the Cloudflare Tunnel

| Field | Entry |
|---|---|
| Purpose | Development, and a separate staging and demonstration copy for manual testing, the browser matrix, client review and the store operations walkthrough. Staging is reachable from the internet through a Cloudflare Tunnel while a review is running |
| Register rows | NFR-09 (P1, S1), for development and staging only. DOD-04 (P1-L, S1). Backlog item #244 |
| Stage 1 owner | **Developer.** One manual step: the developer signs in to Cloudflare when the tunnel is ready to connect |
| Client or provider account or input required | **None from the client.** This is developer-operated infrastructure, not a client account. It holds synthetic data only and no real provider credential |
| Credentials needed later | None from the client |
| Sandbox or mock availability | It is the stand-in: test mode for Paymob, fake adapters for Bosta, the ERP and any other provider without a sandbox, and a mail catcher |
| Recurring or estimated cost status | Not a client cost. Not mentioned in any issued document |
| Development-blocking | **No.** It does hold up acceptance evidence: the staging checks under DOD-04 wait for it. The plan of 5 October 2026 records that staging "does not exist yet" |
| Launch-blocking | **No.** It is never production |

## 4. The three inputs that were not itemised before

### 4.1 The transactional email sending service

| Source | What it says |
|---|---|
| Feature Register | INT-05, "Transactional email", P1, S1. NOTF-13, "Compliance with email provider policies". CR-12 asks for a "sender email" before launch. OD-18 asks for the "sending email address". OD-14 names email among recurring costs "that is yours, not ours". HND-06 hands over email accounts under the client's ownership |
| What You Need to Provide | Section 2, the row "Business email domain": detail "For customer notifications to send reliably", needed "Before stage 4", and if late "Emails are more likely to reach spam folders" |
| Statement of Work section 8, Services Agreement 5.4 | No email line in either |
| Technical Design | Section 4: the notification module sends "Transactional email on the CoreX mail queue". Section 7 lists no email component |
| Functional Specification | "Sending complies with the email provider policies". INT-05 is not cited by any story |

**Not said in any issued document:** which service sends the mail, who opens its account, what credentials it
needs, how the sending domain is authenticated, and what it costs.

**Recorded here as:** an input, the sending service account and the sending domain authentication. Owner: the
developer names the service and its requirements; the client opens the account in its own name, as every other
account. Timing: the issued words are "Before stage 4" for the domain and "Before launch" for the sender
address. Cost status: recurring, the client's (OD-14), not itemised in any issued document.

### 4.2 The Google credentials that Google sign-in needs

| Source | What it says |
|---|---|
| Feature Register | AUTH-10, added by the developer, P1, key, S1. AUTH-13, one account per email. AC-19 in the acceptance script. HND-06 lists payment, shipping, email, SMS and analytics accounts at handover, and does not list sign-in |
| What You Need to Provide | Lists "Analytics and advertising accounts", detailed as Google, Meta and TikTok, which is tracking. Nothing on sign-in |
| Statement of Work section 8, Services Agreement 5.4 | Nothing |
| Technical Design | Section 3, row 13: "Sign-in with one account per email", built as "Plugin or Build". Section 7: "Free options available" for the component |
| Design Brief | Lists the Google sign-in screen. Nothing on credentials |

**Not said in any issued document:** that Google sign-in needs credentials issued by Google at all, under which
Google account they are created, who creates them, and when.

**Recorded here as:** an input, the Google sign-in credentials. Owner: the developer states
exactly what is needed once the component is settled; the client holds the Google account they are issued
under. Timing: none in any issued document; AC-19 must pass before go-live. Cost status: not itemised in any
issued document.

### 4.3 The content delivery network

| Source | What it says |
|---|---|
| Feature Register | NFR-02, "Image optimisation, lazy loading, caching and CDN", P1, S1 |
| Services Agreement | Section 14: performance work includes "content delivery network configuration" |
| Technical Design | Section 9: performance work covers "a content delivery network". Section 7 lists no such component |
| Client Scope Summary, Functional Specification | "CDN configuration"; "Caching and a content delivery network are in place" |
| What You Need to Provide, Statement of Work section 8 | Nothing |

**Not said in any issued document:** which network, whether it is part of the hosting plan or a separate
service, whether it needs an account, and what it costs.

**Recorded here as:** an input, the content delivery network account, if the chosen network needs one. Owner:
the developer names the network with the production hosting plan; the client opens any account in its own name.
Timing: none in any issued document; it follows the hosting instruction (OD-27) and the operating budget
(OD-14). Cost status: not itemised in any issued document.

### 4.4 Found while checking, and also not itemised

| Item | Position |
|---|---|
| Google Merchant Center account (INT-12, MKT-06) | Not named separately. Covered only by "Google" under analytics and advertising accounts. Entry 3.8 |
| Google Search Console and Bing Webmaster accounts (MKT-17) | Not named in MS-DEP-2026-026. Entry 3.9 |
| The payment credentials (INT-01) | CR-01 names the merchant account, not what is handed over. Entry 3.2 |
| The TLS certificate (NFR-05, go-live checklist item 8) | Its source and cost are not stated. Entry 3.14 |
| Error monitoring (NFR-10, P1-L, S1) | The row requires "error monitoring". OD-14 names monitoring among recurring costs. No service, account or cost is itemised in any issued document, and no source says that an external service is used |

## 5. What is still open, and whose it is

### 5.1 Accounts and credentials

The sources read record none of these as supplied. `PROGRESS.md` lists the first three groups as open vendor or
account inputs on 5 October 2026.

| Item | Whose | Needed, in the source's words | Source |
|---|---|---|---|
| Payment merchant account, approved, and wallet approval | Client and Provider | "Start first: longest lead item" | CR-01, PAY-15 |
| Carrier account, API key and rate card | Client | "Before shipping build"; "Before stage 4" | CR-03 |
| Bosta status code legend; remittance cycle | Client or Bosta | "Before shipping build" for OD-20 | SHIP-14, OD-20 |
| Google, Meta and TikTok accounts | Client | "Before stage 4" | MS-DEP-2026-026 section 2 |
| Merchant Center, Search Console and Bing Webmaster accounts | Client | Not stated | Entries 3.8, 3.9 |
| Business email domain; sender email | Client | "Before stage 4"; "Before launch" | MS-DEP-2026-026; CR-12 |
| Sending service account and sending domain authentication | Developer to specify, Client to open | Not stated | Section 4.1 |
| Google sign-in credentials | Developer to specify, Client to hold | Not stated | Section 4.2 |
| Content delivery network account, if needed | Developer to specify, Client to open | Not stated | Section 4.3 |
| WPML production licence | Client | Not stated | R-14 |
| Hosting account | Client | "Before infrastructure setup" | CR-10 |
| Domain | Client | "Before stage 5"; "Before launch" | CR-12, OD-18 |
| Named ERP technical contact | Client | "At kickoff" | MS-DEP-2026-026 section 5 |
| ERP developers at the joint technical meeting | Client | "Target: week 4" | CR-17; MS-DEP-2026-026 section 5 |
| ERP test environment, interface documentation, access for the store | Client, through its ERP developers | "By the date agreed in PRE-09" | CR-16 |
| Live ERP | Client | "Before stage 5" | MS-DEP-2026-026 section 5 |
| Cloudflare sign-in for the tunnel | Developer | When the tunnel is ready to connect | Local environments plan |

### 5.2 Decisions that are the client's

| Id | Decision | What it gates here | Register status |
|---|---|---|---|
| OD-27 | Hosting location, including whether customer data may be hosted outside Egypt | Production hosting, and everything in entries 3.12 to 3.14 | Still open |
| OD-14 | Operating budget for hosting and services | The production provider and plan | Still open |
| OD-15 | Expected product count, order volume and traffic | Production sizing | Still open, with a stated assumption to confirm or correct |
| OD-18 | Domain, sending email address, support numbers, legal entity details | Entries 3.4 and 3.14 | Still open |
| OD-19 | Payment methods in the first release: cards only, or cards and wallets | Which Paymob methods are requested (PAY-15) | Still open |
| OD-05 | Whether cash on delivery is offered | The launch switch of PAY-09 | Still open |
| OD-20 | Carrier collection and remittance cycle | SHIP-16 | Still open |
| OD-21 | Which contracted tracking integrations are switched on at launch | Entries 3.5 to 3.7 | Still open |
| OD-06, OD-07 | Payment provider and carrier | Entries 3.2 and 3.3 | Recommended by the developer, part of agreed scope on signature of the register |
| OD-29 | Whether to add cash on delivery verification (PAY-10, P2) | Nothing in this inventory unless added by change control | Still open |
| PRE-09 | Approval of the ERP Integration Specification, in writing | The ERP-dependent work | Not yet written |

### 5.3 Owner decisions this inventory relies on

D-10 of 5 October 2026 records working decisions by the engagement owner. **None is a client confirmation**, and
each carries `client_confirmed: false` in `docs/scope/open-items.json`. This inventory relies on four.

| Id | Owner decision | What stays the client's |
|---|---|---|
| OD-27 | Not decided. Development and staging run on the developer's machine on synthetic data, so the hosting instruction no longer blocks development or staging verification | The written instruction, before production infrastructure is provisioned (R-03, CR-10) |
| OD-14 | Not decided. A production gate, not a development blocker | The budget, the provider and the plan |
| OD-19 | Cards and mobile wallets are both launch-capable. Credentials and activation do not block the payment architecture | The selection, and activation by the provider on the merchant account |
| OD-21 | Google Analytics with Tag Manager, Meta and TikTok are all in the launch tracking plan | Which are switched on, and the accounts |

D-10 item 5 also fixes the invariants the ERP mock adapter is built on. It chooses no ERP mechanism.

### 5.4 Differences between sources, recorded and not resolved

| # | Difference |
|---|---|
| 1 | **Staging and the hosting instruction.** Technical Design section 8 and Statement of Work 4.2 say staging is set up in Stage 1 after the hosting instruction (CR-10). D-10 and the local environments plan run staging on the developer's machine through a tunnel, so that OD-27 does not gate it. HND-07 hands over a staging environment and MKT-24 gives third parties staging access. The plan itself says #244 "cannot be accepted until production exists" |
| 2 | **Hosting instruction timing.** CR-10 and OD-27: "Before infrastructure setup". MS-DEP-2026-026 and the Project Plan: "Before stage 2" |
| 3 | **Sender email.** MS-DEP-2026-026: the business email domain is needed "Before stage 4", and lateness means mail is "more likely to reach spam folders". CR-12 and OD-18: the sender email is needed "Before launch", and CR-12 reads "Launch blocked" |
| 4 | **Provider approval.** The register says OD-06 and OD-07 become agreed scope on signature. MS-DEP-2026-026 section 6 lists "Payment provider approved, Paymob or another" and "Carrier approved, Bosta or another" as separate decisions with an empty answer column. The sources read record no separate answer |
| 5 | **ERP test environment timing.** Statement of Work section 5: "by the start of Stage 4". CR-16 and MS-DEP-2026-026: "By the date agreed in" the ERP Integration Specification |
| 6 | **Carrier timing words.** CR-03: "Before shipping build". MS-DEP-2026-026 and the Project Plan: "Before stage 4" |
| 7 | **SMS and monitoring.** OD-14 names email, SMS and monitoring as recurring client costs, and HND-06 lists SMS accounts at handover. SMS is not contracted (INT-07, P2; OD-11). Statement of Work section 8 has no email, SMS or monitoring line |
| 8 | **The stage of PRE-09.** The register reads "Per plan". The mirror records "-". The board spells it "per PRE-09" |
| 9 | **Conditional payment rows.** The register gives PAY-09 and PAY-15 as a P1 capability, conditional on OD-05 and OD-19, with stage "S1 if selected". The mirror records both as P1, S1 |
| 10 | **Rows not cited in the specification.** INT-01 and INT-05 (both P1, S1) are cited by no story in Functional Specification version 1.2. The register governs |
| 11 | **Counting the ERP-dependent rows.** The mirror holds 19 P1-E rows with MIG-14 among them, as `PROGRESS.md` says. The pre-deliverables matrix writes "the 19 P1-E rows plus MIG-14" |

## 6. Checked and not required

These were checked against the register and are left out of section 3, because no row contracts them or because
the row needs no external account.

| Item | Rows | Finding |
|---|---|---|
| Meta Conversions API, server-side | INT-11, MKT-08 | P2. Not contracted |
| Server-side tag management | MKT-11 | P3. Not contracted |
| Snapchat Pixel; TikTok catalogue feed | MKT-04; MKT-07 | P2. Not contracted |
| Automated WhatsApp and SMS notifications | INT-03, INT-07 | P2. Launch is email only (OD-11) |
| WhatsApp click-to-chat link | CMS-10 (P1, S1) | A contact link, not a notification service. No account is stated; it uses the support number from CR-12 |
| Marketing email platform; Google Maps address validation; external search provider | INT-06; INT-08; INT-15 | P2. Not contracted |
| Instalments, kiosk payment, Instapay | PAY-20, PAY-21; PAY-22 | P2 and P3. Not contracted |
| Live carrier rates; a second carrier | SHIP-02; SHIP-06 | P2. Not contracted |
| Protection against automated account creation | AUTH-15 (P1, key, S1) | Contracted. No external service is named in any source |
| Consent management and cookie banner | MKT-10 (P1-L, S1) | Contracted. No external service is named in any source |
| Amazon | INT-13 (P3); R-07 | No integration and no access. Exports are performed by the client |

## 7. Coverage of INT-01 to INT-17

| Id | Integration | Scope, stage | Where in this document |
|---|---|---|---|
| INT-01 | Payment gateway, Paymob | P1, S1 | Entry 3.2 |
| INT-02 | Shipping carrier, Bosta | P1-L, S1 | Entry 3.3 |
| INT-03 | WhatsApp transactional notifications | P2 | No obligation. Section 6 |
| INT-04 | WhatsApp marketing automation | P3 | No obligation |
| INT-05 | Transactional email | P1, S1 | Entry 3.4 and section 4.1 |
| INT-06 | Marketing email platform | P2 | No obligation. Section 6 |
| INT-07 | SMS and OTP | P2 | No obligation. Section 6 |
| INT-08 | Google Maps address validation | P2 | No obligation. Section 6 |
| INT-09 | Google Analytics 4 and Tag Manager | P1, S1 | Entry 3.5 |
| INT-10 | Meta Pixel | P1, S1 | Entry 3.6 |
| INT-11 | Meta Conversions API | P2 | No obligation. Section 6 |
| INT-12 | Google Merchant Center product feed | P1-L, S1 | Entry 3.8 |
| INT-13 | Amazon and marketplace synchronisation | P3 | No obligation. Section 6 |
| INT-14 | ERP and accounting synchronisation beyond stock | P3 | No obligation (EX-35). Stock itself is entry 3.1 |
| INT-15 | External search provider | P2 | No obligation. Section 6 |
| INT-16 | Every integration behind an adapter with retries, logging and webhook handling | P1, key, S1 | A rule, not a service. It applies to entries 3.1 to 3.10 and is what lets a mock or fake stand in |
| INT-17 | Egyptian Tax Authority e-invoice and e-receipt | OUT | Excluded (EX-32) |

Services in section 3 that have no INT row: the ERP stock integration (ERP rows), the TikTok Pixel (MKT-03),
search engine verification (MKT-17), Google sign-in (AUTH-10), the licensed components (FIX-04, SRCH-06,
SRCH-07), the content delivery network (NFR-02), hosting and backups (NFR-08, NFR-09), the domain and
certificate (NFR-05, HND-04), and the code repository (HND-01).

# Cross-feature multilingual data integrity

Created 4 October 2026, at the close of the product-cost pilot (`specs/001-product-cost-capture`).

## Why this exists

The pilot was a cost feature. What it actually exposed is an identity problem that is not specific to cost:

1. **A write that does not fire `save_post` does not reach the translation.** `WC_Product_Data_Store_CPT::update()`
   calls `wp_update_post()` only when a post field changes, so a meta-only save never fires `save_post`, and WPML
   and WCML synchronise fields on `save_post`. Any field WPML copies is exposed to the same gap, through the same
   channels (REST, WP-CLI, front-end code, webhooks, cron).
2. **A SKU is not an identity.** A WPML duplicate carries its original's SKU, so one SKU identifies two products
   and `wc_get_product_id_by_sku()` returns whichever the query reaches first.
3. **One commercial item is two WordPress posts, each with its own meta rows.** Already proved to split a product
   report (P-019) and to double-count a stock report (P-020).

Points 1 and 3 together are the reason this workstream exists rather than a second cost fix. Stock is copied to
translations by `WCML\Synchronization\Component\Stock` on the same `save_post` path that cost used, while order
stock reduction goes through `wc_update_product_stock()`, which issues arithmetic SQL directly. If that write does
not fire `save_post`, the same gap that produced a stale Arabic cost would leave a stale Arabic stock figure.
**That has not been tested, and it must not be assumed in either direction.** It is item B1 below and is the
highest-priority probe in this workstream.

## Purpose

Verify that the Arabic and English records of a product represent the same underlying commercial item correctly,
across products, variations, orders, stock, the ERP integration and reports.

## How each item is classified

| Classification | Meaning |
|---|---|
| **Verified working** | Measured on a clean runtime, with evidence in this repository |
| **Verified defect** | Measured, and the result is wrong against a contracted obligation |
| **Not yet tested** | No measurement. A reasoned hypothesis is recorded where one exists, and labelled as a hypothesis |
| **Requires ERP clarification** | Cannot be settled before the ERP Integration Specification (PRE-09) |
| **Requires client clarification** | Depends on an open decision (CX-01, OD-nn) |
| **Future / Option C only** | Outside Option B scope. Recorded so it is not rediscovered, not built |

A classification is never upgraded by reading source code. Source reading produces a hypothesis; a probe on the
disposable runtime produces a classification.

---

## A. Canonical product and variation identity

| # | Verification | Register ids | Classification | Evidence or note |
|---|---|---|---|---|
| A1 | Source-language canonical product identity is resolvable and stable | ADM-27, RPT-11, FIX-04, NFR-04 | **Verified working** | t12: resolved from the WPML translation rows (`wpml_element_trid` plus `wpml_get_element_translations`) |
| A2 | Translation identity is resolved through WPML's own relationship, never by title or position | FIX-04, NFR-04 | **Verified working** | t12, t16 |
| A3 | Variation identity: a translated variation is matched to its own counterpart, never to a sibling | ADM-54, ADM-55, MIG-09 | **Verified working** | t12: changing the English S variation moved AR S and left AR L untouched, with every write recorded |
| A4 | Shared SKU behaviour: a duplicate carries its original's SKU | ADM-33, MIG-13 | **Verified working** (it does, by design) | t11, t16 |
| A5 | Duplicate SKU lookup: `wc_get_product_id_by_sku()` can return either member of the pair | MIG-13, MIG-01, MIG-03, MIG-06, MIG-09 | **Verified defect**, bounded | t11, t16. Not a data-corruption defect: both outcomes leave the pair consistent. It makes a SKU-addressed write non-deterministic, which is why MIG-13 now carries a precondition |
| A6 | Translation created after the product already exists, both methods, both product types | FIX-04, MIG-13 | **Verified working** | t11 part 1, four cases |
| A7 | Missing or deleted translation causes no write to an unrelated product | NFR-07 | **Verified working** | t12: deleted Arabic variation, and an untranslated product, each write only themselves |
| A8 | Separately authored versus duplicated translations behave the same for identity | FIX-04, SSC-12 | **Verified working** | t11, t14: WPML duplicate retains the source-language name until translated; the WCML editor produces an Arabic name. Identity is the same in both |
| A9 | WPML's cached `wpml_original_element_id` can name an unrelated product inside one process | - | **Verified defect** in WPML, worked around | `evidence/wpml-identity-cache.txt`. Never use it. Read identity from the translation rows |
| A10 | The Arabic parent of a translated variable product is registered `simple` and carries no variations | ADM-54, SSC-12, NFR-04 | **Verified defect**, unowned | Observed in t16 with WP-CLI-created translations. Cost is unaffected. It blocks nothing in the pilot and needs an owner before the Arabic storefront is accepted |
| A11 | Does the wp-admin translation flow build the Arabic variable parent correctly? | ADM-54, SSC-12 | **Not yet tested** | A10 was observed only with WP-CLI-created translations |
| A12 | Which other fields WPML copies on `save_post`, and which of them a meta-only save can therefore leave stale | NFR-07, FIX-04 | **Not yet tested** | Hypothesis: the gap is field-independent, so price, stock status, SKU, visibility and any copied custom field share it. Cost was the one field with a contracted financial consequence, so it was fixed first. **This is the inventory that tells us how large the problem is** |

**Rule, now binding:** never assume a SKU uniquely identifies the canonical WooCommerce record. Resolve the
source-language element through WPML translation identity before any write addressed by SKU.

---

## B. Stock integrity

Nothing in this area is verified. It is the largest untested risk in Option B, and it sits under `ERP-01`, which
makes the ERP the source of truth for stock. That changes what must be verified, but it does not remove the
WooCommerce-side question: the store still holds stockable product records in two languages.

| # | Verification | Register ids | Classification | Evidence or note |
|---|---|---|---|---|
| B1 | **Does an order against the Arabic product reduce stock on the English original, or only on the Arabic post?** | ERP-01, ERP-04, BR-007, CHK-12, ADM-70, ADM-72 | **Not yet tested** | Hypothesis, from the pilot's root cause plus P-020: `WCML\Synchronization\Component\Stock` copies `_stock` and `_stock_status` to translations on `save_post`, while `wc_update_product_stock()` issues arithmetic SQL. If that write does not fire `save_post`, each language version holds its own quantity and one physical unit can be sold twice. **Highest priority probe in this workstream.** It is the same mechanism as the cost gap, with a worse consequence |
| B2 | Whether translated products share or mirror stock, and which | ERP-01, ADM-70 | **Not yet tested** | P-020 established that both posts carry their own `wc_product_meta_lookup` stock row holding the same number, which is mirroring, not sharing. What keeps them equal, and when, is unknown |
| B3 | Variation stock behaviour across languages | ADM-70, ADM-72, MIG-09 | **Not yet tested** | Variations are separate WPML elements, so B1 and B2 apply per variation |
| B4 | Stock reduction from an Arabic order | ERP-04, BR-007 | **Not yet tested** | Covered by the B1 probe |
| B5 | Stock reduction from an English order | ERP-04, BR-007 | **Not yet tested** | Covered by the B1 probe |
| B6 | Concurrent Arabic and English orders against the same physical SKU | BR-003, CHK-12, AC-05, NFR-07 | **Not yet tested** | P-009 is `partial`: WooCommerce's stock write is atomic per statement, but validation and decrement are separate steps, and one PHP process cannot create simultaneity. The multilingual case is strictly worse, because the two checkouts may validate against two different rows. Needs a parallel load test against staging |
| B7 | Cancellation and stock restoration, both languages | ERP-06, BR-007, ADM-90 | **Not yet tested** | ERP-06 is P1-E, so the ERP half waits for PRE-09. The WooCommerce half does not |
| B8 | Failed payment: stock restored, both languages | ERP-06, PAY-04, BR-007 | **Not yet tested** | Same split as B7 |
| B9 | Return and restock | ERP-06, RET-07, ADM-123 | **Not yet tested** | Same split as B7 |
| B10 | Can any translation double-count physical inventory in a store-side figure? | RPT-10, ADM-70, NFR-07 | **Verified defect** for reporting | P-020: the low-stock report lists one physical item twice, once per language, each showing the full quantity. Whether the *sellable* balance double-counts is B1 |
| B11 | Whether a language-scoped admin stock view silently omits an Arabic-only product | RPT-10, ADM-159 | **Not yet tested** | P-020 found the REST dispatch path unscoped and explicitly did not rule out the opposite fault in a browser admin request. WCML removes the "All languages" option from the analytics switcher |

No ERP behaviour is implemented under this workstream. B1 to B6 and B10 to B11 are WooCommerce-side questions that
exist whatever the ERP turns out to do, and the answers change what the ERP adapter has to guarantee.

---

## C. ERP stock-only integration identity (preparation for PRE-09)

Nothing here is built, and no ERP behaviour is invented. This is the seam and the question set.

| # | Question to settle in PRE-09 | Register ids | Classification |
|---|---|---|---|
| C1 | What identity does the ERP use: SKU, ERP product id, ERP variant id, barcode, or another external key? | ERP-08, ADM-33 | **Requires ERP clarification** |
| C2 | Is that identity unique per physical item, and is it stable across ERP edits? | ERP-08 | **Requires ERP clarification** |
| C3 | How is a WooCommerce product or variation mapped to it, in both languages, so that the Arabic and English records are **one** ERP stock item and not two? | ERP-08, FIX-04, NFR-04 | **Requires ERP clarification.** The non-negotiable: the mapping is held once, on the source-language element, and the translation resolves to it through WPML identity (area A). This follows from `ERP-01` and does not need the ERP's input |
| C4 | Where does the mapping live, and who owns it when a translation is created or deleted? | ERP-08, ADM-27-style ownership | **Not yet tested** / design |
| C5 | Stock lookup: interface, granularity, latency, caching allowed | ERP-02, ERP-03 | **Requires ERP clarification** |
| C6 | Availability check at the contracted steps | ERP-02, ERP-03, ERP-05 | **Requires ERP clarification** |
| C7 | Reservation, if the ERP supports one at all | ERP-03, ADM-71 | **Requires ERP clarification** |
| C8 | Sale decrement: call shape, what identifies the line, what identifies the order | ERP-04 | **Requires ERP clarification** |
| C9 | Retries, duplicate callbacks, idempotency key | ERP-04, ERP-06, INT-16, NFR-07 | **Requires ERP clarification** |
| C10 | Concurrent orders against one ERP item | ERP-04, ERP-05, BR-003 | **Requires ERP clarification** |
| C11 | Failure handling and what the customer sees when the ERP cannot be reached | ERP-05, OD-41 | **Confirmed at the business level** (OD-41: the store does not confirm the sale). The customer experience is PRE-09 |
| C12 | Source-of-truth ownership in the store admin: what stock fields remain editable and what they mean | ERP-07, ADM-70 to ADM-77 | **Requires ERP clarification** |
| C13 | Restoration on cancel, failed payment and return | ERP-06 | **Requires ERP clarification** |
| C14 | Initial stock load destination | MIG-14 | **Requires ERP clarification** |

**The identity constraint is ours, not theirs.** Whatever key the ERP uses, the integration must not treat the
Arabic and English translations as separate ERP stock items. That is a direct consequence of ERP-01 and is settled
here, before the meeting, so the meeting is about the ERP's interface rather than about our data model.

---

## D. Order data integrity

An order is the one place where "what was true on the day" has to survive later catalogue edits. The pilot proved
one field of it.

| # | Verification | Register ids | Classification | Evidence or note |
|---|---|---|---|---|
| D1 | Which product identity an order line stores | ENT-08, ADM-86, ORD rows | **Verified working** (observed) | The line records the product or variation id that was actually ordered, so an Arabic order names the Arabic post. This is why P-019 splits |
| D2 | Cost snapshot survives a later product cost change | ADM-27, RPT-11, ENT-08 | **Verified working** | P-017, t04: 200 kept after the product cost became 999 |
| D3 | Price snapshot survives a later price change | BR-005, ENT-08 | **Verified working** | P-002 |
| D4 | SKU snapshot on the order line | ENT-08, ADM-86 | **Not yet tested** | Needs checking whether the SKU is stored on the line or read through the product at display time |
| D5 | Product name on the line, and whether a later translation edit changes a historical order | ENT-08, ADM-86, NFR-04 | **Verified working** for the sync; **not yet tested** generally | t14: the Arabic order line name was unchanged by the cost sync. Whether editing the Arabic product's title later rewrites the historical line is untested |
| D6 | Quantity, language context, order status | ENT-08, ORD-01 to ORD-14, ADM-87 | **Not yet tested** | Which language an order was placed in, and whether it is stored as order data rather than inferred |
| D7 | Shipment status where applicable | SHIP rows, ADM-89 | **Not yet tested** | P-018 is relevant: WooCommerce keeps no structured status history, only prose notes, which is a separate recorded defect |
| D8 | Reporting on an order must not depend on the product still existing in that language | RPT-01, RPT-10 | **Not yet tested** | Follows from D1 |

**Rule:** historical order facts must not depend on later product or translation changes. D2 and D3 hold. D4, D5
and D6 must be measured before the Stage 1 acceptance of the order and reporting rows.

---

## E. Reporting integrity

| Report | Register id | Scope | Source data | Multilingual risk | Classification |
|---|---|---|---|---|---|
| Sales: revenue, net sales, orders, units, AOV, discounts, shipping | RPT-01 | P1-L | Order level (`wc_order_stats`) | Low: order-level aggregation does not split by product | **Not yet tested**, low risk. P-019 deliberately excluded it |
| Inventory: low stock and out of stock | RPT-10 | P1-L | `WP_Query` over the product post type, plus ERP figures | **One physical item listed twice, each line showing the full quantity**; and possibly the opposite fault in a scoped admin view | **Verified defect** (P-020). Blocking on acceptance |
| Product cost captured from day one | RPT-11 | P1 | Order line meta `_cogs_total_value` | None remaining | **Verified working** (the pilot) |
| Funnel | RPT-08 | P1-L via GA4 | GA4 | Product-level identity inside GA4 | **Not yet tested** |
| Traffic | RPT-09 | P1-L via GA4 | GA4 | Low | **Not yet tested** |
| Products: best sellers, slow movers, conversion, stock cover | RPT-03 | **DEF** | `wc_order_product_lookup` | **Ranking and pagination break**: the WCML merge runs in PHP after the SQL has ordered and paginated, so a paginated page shows a wrong number under the right product name | **Verified defect** (P-019), but **deferred scope**. Record, do not build |
| Search terms | RPT-07 | **DEF** | - | - | **Future / Option C only** |
| Profitability, COGS, gross margin | RPT-02 | **P2** | Order line cost, captured now | Would inherit P-019's split if built | **Future / Option C only.** Not built. The pilot exists so that the *data* is right when it is |
| Customers, promotions, returns | RPT-04, RPT-05, RPT-06 | P2 | - | - | **Future / Option C only** |

**For each contracted report, the six failure modes the review asked about:**

| Failure mode | Where it is real today |
|---|---|
| One physical SKU split into separate report lines | RPT-10 (verified), RPT-03 (verified, deferred) |
| Double-counted sales | Not in RPT-01 (order level). Would appear in any product-level sales view |
| Double-counted stock | RPT-10, verified |
| Misstated units sold | RPT-03, verified, when paginated |
| Lost cost history | Closed by the pilot: cost is frozen on the order line and the two language versions cannot diverge |
| Mixed translated variation identities | Not observed; variations resolve correctly (A3). Untested inside reports |
| Inconsistent order statuses | P-018: no structured status history exists natively. Separate defect, area F |

**What must be captured accurately now, even though the report is later:** order line cost (done), the product
identity on the order line (D1), and the language context of the order (D6). RPT-02 is P2 and is not built, but
the history it would need is being written today, so a gap in D6 is expensive to repair later.

---

## F. Returns, refunds and shipment states

Contracted Option B scope only. The dedicated returns workflow and the Operations Console are DEF.

| # | Area | Register ids | Classification | Note |
|---|---|---|---|---|
| F1 | Cancellation: order state, stock restoration, both languages | ADM-90, BR-007, ERP-06 | **Not yet tested** | WooCommerce half testable now; ERP half is PRE-09 |
| F2 | Return to origin | RET rows, SHIP-16, ADM-123 | **Verified defect** in the carrier layer | P-012: WooCommerce has no Returned to Origin status, and the Bosta plugin's carrier status mapping is dormant (commented out). ADR-0002 covers the minimum shipment state record |
| F3 | Refund, full and partial | ADM-91, PAY-06 to PAY-08 | **Not yet tested** multilingually | P-014: the native refund API enforces a ceiling at the amount actually paid, which is sound |
| F4 | Stock restoration on refund or return | ERP-06, RET-07 | **Not yet tested** | Depends on B1: if the two language versions hold independent quantities, restoration has the same ambiguity as reduction |
| F5 | COD collected versus remitted | SHIP-16, OD-20 | **Requires client clarification** | OD-20 (carrier COD collection and remittance cycle) is open |
| F6 | Shipment status changes and their history | SHIP rows, ADM-89, ADM-86 | **Verified defect** | P-018: no structured, queryable status history natively, only prose order notes. ADR-0002 is the recorded minimum |
| F7 | Operational analytics on returns | RPT-06 | **Future / Option C only** | P2. Not built |

---

## Mapping to the Feature Register

Every item above is one of five things. They are kept apart deliberately, because only the first is contracted
behaviour and only the first can create a feature.

| Kind | What it is | Items | How it is delivered |
|---|---|---|---|
| **1. Contractual functionality** | Behaviour a register row obliges | B1 to B5, B7 to B9 (WooCommerce half), D1 to D8, E (RPT-01, RPT-10, RPT-11), F1, F3, F4, F6 | A Spec Kit feature under its register ids, inside an existing backlog slice |
| **2. Developer-quality safeguards** | Not separately contracted; needed to deliver NFR-07 (data integrity) and FIX-04 | A12 (the copied-field inventory), the identity helper the pilot already produced, the MIG-13 precondition | Inside the feature that needs it. Never its own PBI |
| **3. Verification and probes** | Measurement, no behaviour change | B1, B6, B11, A11, D4, D5, D6, E (GA4 rows) | `discovery/` probes, or integration scenarios in `mizzey-site/tests/`, classified `internal:test-infrastructure` |
| **4. Client and ERP decisions** | Cannot be settled by engineering | CX-01, OD-12, OD-20, OD-41 (settled), C1 to C14 | Stage 1 review, and PRE-09 |
| **5. Future or Option C capability** | Outside Option B | RPT-02, RPT-03, RPT-04 to RPT-07, ADM-76, INT-14, the Operations Console, the dedicated returns workflow | Recorded here. **Not built, no PBI, no issue** |

**A risk does not create a feature.** Where the behaviour behind a risk is outside Option B (RPT-02, RPT-03), the
risk is recorded and nothing is built. Where the behaviour is contracted, the verification attaches to the
existing slice that owns it rather than becoming a parallel workstream of its own.

## Priority order

| Rank | Item | Why first |
|---|---|---|
| 1 | **B1**, Arabic order stock decrement | Same mechanism as the cost defect, worse consequence (overselling one physical unit). Blocks the ERP adapter design, because what the store does with stock decides what the adapter must guarantee |
| 2 | **A12**, which fields WPML copies on `save_post` | Bounds the whole problem. Until it exists, every field is a suspected B1 |
| 3 | **C3**, one ERP stock item per commercial item | Must be settled before the ERP meeting, because it is our constraint, not a question for them |
| 4 | **D6**, the language context on the order | Cheap now, expensive to backfill. RPT-02 and reconciliation both need it |
| 5 | **B11** and **RPT-10** | A contracted report with a measured defect, blocking acceptance of its row |
| 6 | **B6**, concurrency | Needs staging and a parallel load test, so it cannot be done on the disposable runtime. Book it, do not block on it |

## Evidence already in this repository

| Source | What it establishes |
|---|---|
| `specs/001-product-cost-capture/verification.md` | The cost pilot, the `save_post` root cause, WPML identity, the SKU ambiguity |
| `discovery/data/probes.json` P-006 | WPML translates product data, variations and attribute taxonomy, staying in one translation group |
| P-008 | The CSV importer handles Arabic encoding and re-runs safely by SKU; the gap is operator safety, not parsing |
| P-009 (`partial`) | The stock write is atomic per statement; validation and decrement are separate steps; simultaneity untested |
| P-017 | Cost is frozen onto the order line natively |
| P-019 | The product report splits by language; the WCML merge recovers totals but not ranking, and is wrong under pagination |
| P-020 | The low-stock report lists one physical item twice; both posts carry their own stock row |
| P-012, P-018 | Carrier status mapping dormant; no structured order status history |
| `docs/adr/0001-native-cost-of-goods-sold.md`, `0002-minimum-shipment-state-record.md` | The two recorded architecture positions |

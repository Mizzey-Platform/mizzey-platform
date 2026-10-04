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
| A10 | The Arabic parent of a translated variable product is registered `simple` and carries no variations | ADM-54, SSC-12, NFR-04 | **Verified anomaly in the tested programmatic translation path.** Production relevance pending A11, and now **raised in priority**: B1 found a second, worse consequence of a corruptible Arabic variation registration | Measured, in t16, with translations created from WP-CLI, which is the only path tested. Not called a contracted storefront defect, because the path a human editor actually uses has not been tested: if the wp-admin flow builds the Arabic parent correctly, this is an artefact of programmatic creation and not a storefront fault. Cost correctness is unaffected either way, because variations are WPML elements in their own right and each one synchronises individually. Needs an answer before the Arabic storefront is accepted, and it belongs to the translation workflow (US-16-03) |
| A11 | Does the **wp-admin** translation workflow build the Arabic variable parent correctly? | ADM-54, SSC-12, MIG-02, SSC-21 | **Measured**, 4 Oct 2026 (t19): the corruption needs the **WCML editor translation plus product creation in the same process**. wp-admin one-save-per-request does not reach it, the WPML duplicate method does not, and the **native importer does not**. Sources-first sequencing prevents it entirely | The browser translation screen itself remains a staging check. See "A11 and the price matrix: measured" below |
| A12 | Which other fields WPML copies on `save_post`, and which of them a meta-only save can therefore leave stale | NFR-07, FIX-04 | **Measured**, 4 Oct 2026. The hypothesis holds for most fields: **9 of 14 do not follow** a code-level save, including **`regular_price` and `sale_price`**. Stock and cost are the exceptions, each for its own reason | t18. Thirteen of WCML's fourteen synchronisation components run on `save_post`; only Stock does not. See "B1 and A12: measured" below |

**Rule, now binding:** never assume a SKU uniquely identifies the canonical WooCommerce record. Resolve the
source-language element through WPML translation identity before any write addressed by SKU.

---

## B. Stock integrity

Two things here are measured, and the one that matters most is not. Stated precisely:

- **Measured.** A translated pair holds **separate stock rows**: P-020 found `wc_product_meta_lookup` carrying one
  stock row per language version, each holding the same number, so the same physical item is stored as two
  stockable products. That is mirroring, not sharing.
- **Measured defect.** The contracted low-stock report lists one physical item twice, each line showing the full
  quantity (B10, P-020). That is a defect in a delivery row, not a hypothesis.
- **Not yet verified.** Whether stock **reduction and restoration** behave correctly across translations. Nothing
  measures what an Arabic order does to the English original's quantity, and nothing measures what a
  cancellation, failed payment or return does. That is B1 and B7 to B9, and it is the largest unverified risk in
  Option B.

The area sits under `ERP-01`, which makes the ERP the source of truth for stock. That changes what has to be
verified, and it does not remove the WooCommerce-side question: the store still holds stockable product records in
two languages, and what it does with them decides what the ERP adapter has to guarantee.

| # | Verification | Register ids | Classification | Evidence or note |
|---|---|---|---|---|
| B1 | **Does an order against the Arabic product reduce stock on the English original, or only on the Arabic post?** | ERP-01, ERP-04, BR-007, CHK-12, ADM-70, ADM-72 | **Measured**, 4 Oct 2026: **working** for simple products and for variations whose translation group is intact; **measured defect** for variations once the group is corrupted, and that permits overselling | t17. The hypothesis that stock shared the cost gap was **refuted**: WCML hooks `woocommerce_product_set_stock` and `woocommerce_variation_set_stock` directly, so stock does not depend on `save_post`. See "B1 and A12: measured" below |
| B2 | Whether translated products share or mirror stock, and which | ERP-01, ADM-70 | **Measured working** | Mirroring, not sharing: each language version holds its own `_stock` and `wc_product_meta_lookup` row (P-020), and `WCML\Synchronization\Hooks::syncProductStock` on the stock write keeps them equal (t17) |
| B3 | Variation stock behaviour across languages | ADM-70, ADM-72, MIG-09 | **Measured defect**, conditional on group integrity | t17: correct in eight sequences; silently divergent once the variation's `icl_translations` row is rewritten. The sibling variation is never affected |
| B4 | Stock reduction from an Arabic order | ERP-04, BR-007 | **Measured working** for simple products and intact variations | t17 steps 3, 4, 9 |
| B5 | Stock reduction from an English order | ERP-04, BR-007 | **Measured working** for simple products and intact variations | t17 steps 2, 5 |
| B6 | Concurrent Arabic and English orders against the same physical SKU | BR-003, CHK-12, AC-05, NFR-07 | **Not yet tested** | P-009 is `partial`: WooCommerce's stock write is atomic per statement, but validation and decrement are separate steps, and one PHP process cannot create simultaneity. The multilingual case is strictly worse, because the two checkouts may validate against two different rows. Needs a parallel load test against staging |
| B7 | Cancellation and stock restoration, both languages | ERP-06, BR-007, ADM-90 | **Measured working** on the WooCommerce side | t17 step 8: an Arabic order of 2 reduced both records to 8, cancelling it restored both to 10. The ERP half is still PRE-09 |
| B8 | Failed payment: stock restored, both languages | ERP-06, PAY-04, BR-007 | **Not yet tested** | Same split as B7 |
| B9 | Return and restock | ERP-06, RET-07, ADM-123 | **Not yet tested** | Same split as B7 |
| B10 | Can any translation double-count physical inventory in a store-side figure? | RPT-10, ADM-70, NFR-07 | **Verified defect** for reporting; **not** for the sellable balance | P-020: the low-stock report lists one physical item twice. The sellable balance is B1, and it does not double-count for simple products or intact variations |
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
| C3 | How is a WooCommerce product or variation mapped to it, in both languages, so that the Arabic and English records resolve to **one** ERP stock item and not two? | ERP-08, FIX-04, NFR-04 | **Requires ERP clarification** for the key. The one-commercial-item invariant below is not theirs to redefine |
| C4 | Where the canonical mapping is stored, and who owns it when a translation is created or deleted | ERP-08 | **Architecture decision**, to finalise with PRE-09 and implementation design |
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

### The invariant, and the proposal that is not the invariant

**The invariant (mandatory).**

> The Arabic and English translations of one commercial item must resolve to the same single ERP stock item. They
> must never become two ERP stock balances.

This follows from `ERP-01`, which makes the ERP the source of truth for stock and forbids the store from operating
a balance that can disagree with it. Two ERP balances for one physical item is exactly that disagreement. The ERP
meeting determines the external key; it does not get to redefine this invariant.

**The current proposal (an architecture position, not a contractual requirement).**

1. Resolve the canonical commercial item through WPML translation identity, never by SKU alone and never by title
   or list position (area A).
2. Maintain **one** canonical ERP mapping per commercial item.
3. Translations resolve to that mapping rather than carrying their own.
4. **Where the mapping is physically stored, and by what mechanism, is an architecture decision to finalise with
   PRE-09 and the implementation design.** Holding it on the source-language record is the obvious candidate and
   is not the only one: a separate mapping table keyed by translation group would satisfy the invariant equally,
   and may be better when a source-language record is deleted or re-pointed.

`ERP-01` requires the single balance. It does not require any particular storage location, and this document does
not claim it does.

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

## Priority order, revised after B1 and A12

| Rank | Item | Why, now that the probes have run |
|---|---|---|
| 1 | **The migration sequencing invariant** (B1's trigger, characterised by A11) | It is the one measured path to overselling and to corrupted Arabic content, and it owns a requirement PBI through MIG-02 and SSC-21. The correction is sequencing, not code |
| 2 | **B11** and **RPT-10** | A contracted P1-L report with a measured defect (P-020), blocking acceptance of its row |
| 3 | **D6**, the language context on the order | Cheap now, expensive to backfill. Reconciliation and any later margin reporting both need it |
| 4 | **A10**, the Arabic variable parent | A11 narrowed its trigger but did not settle the browser translation flow, which stays a staging check |
| 5 | **The channel matrix for the other A12 fields that did not follow** | `weight`, dimensions, `catalog_visibility`, `manage_stock`. Each belongs to its owning slice, and each is currently a reasoned hypothesis from t15's channel pattern rather than a measurement |
| 6 | **B6**, concurrency | Needs staging and a parallel load test. Book it, do not block on it |

**Closed by the probes:** B1's core question, B2, B4, B5, B7 on the WooCommerce side, the bulk of A12, A11, and
the price question. **The price gap dropped off this list entirely**: no contracted path is defective, so there is
nothing to prioritise.

## A11 and the price matrix: measured, 4 October 2026

Both ran on a scripted clean baseline. Scenarios `t19-translation-group-integrity.php` and
`t20-price-integrity.php`. **No fix was implemented from either.**

**16 integration scenarios executed with no harness or test failure.** That is a runner statement, and it is
not a claim that every measured behaviour was correct. **t17, t18, t19 and t20 are fact-finding scenarios**: they
return no pass or fail verdict, and they deliberately record defects and unsupported-path behaviour as findings
rather than as failures. `0 failed` therefore means the harness held and every contract scenario (t02 to t16)
passed, not that nothing is wrong.

What those four actually recorded:

| Scenario | What it found |
|---|---|
| t17 (B1) | Stock shares one effective balance for simple products and for intact variation groups. A corrupted variation group **diverges and permits overselling** |
| t18 (A12) | **Nine of fourteen fields do not reach the Arabic record** on a code-level save |
| t19 (A11) | The corruption needs the WCML editor plus same-process product creation. **Three workflows corrupt; four do not.** Sequencing prevents it |
| t20 (price) | Every contracted price-maintenance path is correct. **REST, WP-CLI and custom code leave a stale Arabic price and charge the old amount** |

The evidence that matters is unchanged: A11 identified and bounded the sequencing defect, the price matrix proved
the contracted price-maintenance paths work, and the unsupported programmatic price paths remain documented
safeguards rather than built code.

### A11 verdict: a same-process sequencing defect, and sequencing alone prevents it

Seven workflows, each building a translated variable product and then doing further work in the same process. The
translation group was read from `icl_translations` directly, before and after.

| # | Workflow | Result |
|---|---|---|
| 1 | WP-CLI, WCML editor translation, one product created afterwards | **CORRUPTED** |
| 2 | WP-CLI, WCML editor translation, nothing afterwards | intact |
| 3 | A migration-shaped batch: ten products created afterwards | **CORRUPTED** |
| 4 | **All source products created first, translations only afterwards** | **intact** |
| 5 | Interleaved create, translate, create, translate | **CORRUPTED** |
| 6 | **WPML duplicate** instead of the WCML editor, one product afterwards | **intact** |
| 7 | **The native CSV importer** creating the later products | **intact** |

**Four things this settles.**

1. **It is not a general wp-admin defect.** Case 2 is intact, and wp-admin is one request per save, so an operator
   editing or translating products one at a time never reaches the trigger. The classification stays
   **Partial / workflow-dependent** and is not generalised.
2. **It needs the WCML translation editor.** Case 6 shows the WPML duplicate method does not corrupt. The trigger
   is specific to editor-authored translations.
3. **The native importer does not reproduce it.** Case 7 is intact, even though one import request creates several
   products. So the contracted import path is not the danger; a scripted migration using WooCommerce CRUD is.
4. **Sequencing alone prevents it.** Case 4 is intact with the same ten products created, simply in a different
   order. **Therefore the correction is a migration runbook invariant, not runtime code**, which is the standing
   preference when sequencing suffices.

**The damage, measured in full.** More than B1 could see:

- the **Arabic parent loses its `icl_translations` registration entirely** (trid, element type, language and
  source language all become NULL);
- the Arabic variations' **titles are overwritten** with the later product's name;
- the Arabic variations' **attributes are wiped** (`size=S` becomes empty), so the variation can no longer be
  selected;
- the English parent's child count changes, because variations detach;
- stock stops synchronising in both directions, and overselling becomes possible.

That is a direct failure of **MIG-02** ("Arabic content is not corrupted" by the import) and **SSC-21** ("product
content maintained separately in English and Arabic"), which is why it owns a requirement PBI rather than only a
probe.

**Still a staging check:** the wp-admin translation screen is a browser flow this runtime cannot drive. Case 2 and
the per-request nature of the admin cover the single-request case, and the browser flow itself is verified on
staging.

### Price verdict: every launch-supported path is correct, so no custom code is justified

Six channels, both price fields, both product types, with the amount an Arabic order actually charges.

| Channel | Contracted as a price-maintenance path? | regular_price | sale_price | Arabic order charged | Classification |
|---|---|---|---|---|---|
| wp-admin product form | **Yes**, ADM-25, ADM-26 | **FOLLOWED** | **FOLLOWED** | the new price | **Measured working** |
| wp-admin variations AJAX | **Yes**, ADM-33 | **FOLLOWED** | **FOLLOWED** | the new price | **Measured working** |
| Native CSV importer | **Yes**, MIG-13 | **FOLLOWED** | **FOLLOWED** | the new price | **Measured working** |
| Scheduled-sales cron | native behaviour | no divergence | no divergence | correct | **Measured working** |
| WooCommerce REST `/wc/v3` | **No contracted price row** | stale | stale | **the old price** | **Measured defect, unsupported path** |
| WP-CLI CRUD | **No contracted price row** | stale | stale | **the old price** | **Measured defect, unsupported path** |
| Front-end, webhook, cron code | **No contracted price row** | stale | stale | **the old price** | **Measured defect, unsupported path** |

**The native-first rule therefore applies: no price synchronisation is built.** Every path the register contracts
for price maintenance already keeps the Arabic price correct, and an Arabic order charges the synchronised amount.

**The scheduled-sales cron was tested specifically**, because it is the one price write a store makes without
anybody scripting anything. A sale and both its dates were set in a single wp-admin submission, leaving the pair
synchronised and on sale; the end date was then moved into the past by a raw meta write on **both** records, so
the only product-object save under test was the cron's. Afterwards both records agreed and the Arabic order
charged the correct amount. The expiry is evaluated per record at read time from each record's own
`_sale_price_dates_to`, which WCML copies on the admin save, so the two expire together.

**The residual risk, recorded as a development rule rather than a feature.** A price written through REST, WP-CLI
or custom code does not reach the Arabic record, and an Arabic order then charges the stale amount. No contracted
Option B path does that, and **ADM-28, scheduled price changes, is P2**, so no contracted feature performs
programmatic bulk price updates either. The rule that follows:

> Any code that writes a product price must either go through a path that fires `save_post`, or synchronise the
> translation explicitly. It is not safe to write `_regular_price` or `_sale_price` through CRUD alone.

That belongs to whichever slice ever writes a price programmatically, as a safeguard inside it. It is not a PBI,
and it is not a class built in advance for a caller that does not exist.

### Classification correction applied to both

| Finding | Classification | Why |
|---|---|---|
| A11, the probe | **internal:test-infrastructure / verification** | A probe is evidence. It is not a contractual PBI, and it is attached to the catalogue and migration work that owns the behaviour |
| The outcome A11 protects | **requirement PBI**, citing MIG-02, MIG-09, MIG-13, SSC-21 | The register independently obliges that the import does not corrupt Arabic content and that product content is maintained separately in both languages. The outcome is contracted; the probe is not |
| The price gap | **no requirement PBI** | No contracted price-maintenance path is defective. Inventing a PBI because a probe found something on an unsupported channel would be inventing scope |
| The price development rule | **developer-quality safeguard** | Attached to the slice that would need it, recorded here, built nowhere |

## Probe specification: B1, multilingual stock reduction

**Measure first. No fix is implemented until this probe has established the behaviour.** If it finds a defect, the
fix is scoped from the measurement, not from the hypothesis.

**Question.** When an order is placed against the Arabic product, is stock reduced on the English original, on the
Arabic translation, or on both? Can one physical unit be represented as independently sellable in both languages?

**Hypothesis, to be confirmed or refuted.** `WCML\Synchronization\Component\Stock` copies `_stock` and
`_stock_status` to translations on `save_post`. Order stock reduction goes through `wc_update_product_stock()`,
which the data store implements as arithmetic SQL (`meta_value +/- operand`, established in P-009). If that write
does not fire `save_post`, each language version keeps its own quantity, which is the same mechanism as the cost
gap with a worse consequence.

**Environment.** The disposable local runtime `../app/wp`, reset to the scripted clean baseline
(`mizzey-site/tests/integration/baseline/reset-runtime.sh`). Never production. Cost of Goods Sold is irrelevant
here and stays off.

**Fixtures.** Built through the existing harness (`_workflows.php`), so the translation methods match what the
pilot used:

| Fixture | Shape |
|---|---|
| F1 | Simple product, managed stock, quantity 5, with a WPML duplicate translation |
| F2 | Simple product, managed stock, quantity **1**, with a duplicate translation (the last-unit case) |
| F3 | Variable product with two variations, each managed with quantity 5, translated by the WCML editor |
| F4 | Simple product, managed stock, quantity 5, **untranslated** (control: isolates WPML from WooCommerce) |

**Measurements.** For every step, record the quantity and stock status of **both** language records, read from
both sources, because they can disagree:

- post meta `_stock` and `_stock_status` on each post id;
- `wc_product_meta_lookup` rows for each post id;
- `wc_get_product( $id )->get_stock_quantity()` for each;
- whether `save_post` fired for either post during the step, using the same in-request hook recorder t15 uses.

**Steps.**

| # | Action | What it establishes |
|---|---|---|
| 1 | Baseline read of all fixtures | The starting equality, and whether the two rows agree before anything happens |
| 2 | Place an **English** order for 1 unit of F1 | Does the English record reduce? Does the Arabic record follow? |
| 3 | Place an **Arabic** order for 1 unit of F1 | **The core question.** Does the English original reduce? |
| 4 | Place an Arabic order for 1 unit of F3's first variation | The same, per variation, and whether the sibling variation moves |
| 5 | Place an English order for 1 unit of F3's first variation | The mirror case for variations |
| 6 | Place an Arabic order for the **last unit** of F2, then attempt an English order for 1 unit of F2 | **Whether one physical unit is independently sellable in both languages.** The decisive step |
| 7 | Reverse step 6: English order for the last unit of F2 (fresh fixture), then attempt an Arabic order | The same question in the other direction |
| 8 | Cancel the order from step 3 | Restoration: which record is restored, and by how much (no ERP involved) |
| 9 | Repeat step 3 three times | Whether the behaviour is stable, or depends on cache state within one process |
| 10 | Control: place an order against F4 | That any divergence found is a translation effect, not a WooCommerce stock bug |

**Expected outputs.** A per-step table of both records from all four sources, a statement of which hooks fired, and
one of three verdicts: *measured working* (both language versions reflect one balance, and the last unit cannot be
sold twice), *measured defect* (they diverge, with the exact divergence recorded), or *partial* (with what could
not be measured in one process, as P-009 had to say about simultaneity).

**Deliberately out of scope.** True simultaneity (that is B6, and needs staging and a parallel load test), any ERP
call (B7 to B9 depend on PRE-09 for the ERP half), and any fix.

**Classification.** `internal:test-infrastructure`, as an integration scenario under `mizzey-site/tests/` beside
the pilot's scenarios, or a `discovery/` probe if it turns out to need the probe runner's isolation. It changes no
behaviour.

## Probe plan: A12, which fields WPML and WCML synchronise on `save_post`

Run after B1, because B1's answer tells us how much the rest of the inventory matters.

**Question.** Which product and variation fields do WPML and WCML copy to translations, through which hook, and
which meta-only update paths bypass that synchronisation?

**Method.**

1. **Enumerate the copied fields** from the running installation rather than from documentation: the WPML
   `translate-independently` and custom-field settings, WCML's synchronisation components (it registers one per
   concern, which is how `Component\Stock` was found), and the WPML configuration the plugins ship for
   WooCommerce. Record which hook each registration listens on.
2. **For each field, establish the write paths** WooCommerce itself uses: a CRUD setter plus `save()`, a post-field
   change, and any arithmetic or direct data-store write (as stock has). The pilot's root cause applies to any
   field whose write does not change a post field, because `WC_Product_Data_Store_CPT::update()` then skips
   `wp_update_post()` and `save_post` never fires.
3. **Measure, per field**: change it on the English original through each path, in a real request context for each
   channel the pilot already models (`admin-http`, `import-http`, `rest-http`, `crud-cli`, `crud-web`), and record
   whether the Arabic record followed and whether `save_post` fired.
4. **Classify each result** as *measured working*, *measured defect*, or *hypothesis* where a path could not be
   exercised. A field is never recorded as working because source reading suggests it should be.

**Output.** A field-by-field table: field, which plugin copies it, the hook, the channels where it follows, the
channels where it does not, and the classification. That table is what bounds the problem: it says how many
fields share the cost defect's shape, and therefore whether this is a handful of cases or a systemic one.

**Scope discipline.** **No fix is created for a field outside Option B.** A field that is only used by DEF, P2 or
P3 behaviour is recorded in the table and nothing more. A field inside a delivery row gets a fix scoped under that
row's own feature, not under this workstream, and not automatically.

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

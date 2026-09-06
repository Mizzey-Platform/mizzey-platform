# Mizzey.com Feature Register & Scope Definition

**Document:** MS-ANX-2026-001
**Version:** 1.0
**Date:** 30 August 2026
**Prepared by:** Mustafa Shaaban
**Prepared for:** Mizzey.com
**Source:** Ecommerce Website Requirements SRS v1.0 (AR), client document dated 19 August 2026
**Status:** Issued for client approval. Contract Annex A

---

## Purpose of this document

Your requirements document (§19.3) states that when the contract is divided into milestones, **every feature must be assigned to a phase**. This document is that assignment.

Every requirement, decision, constraint, journey stage, acceptance criterion and deliverable in your SRS has been read line by line and recorded here with a permanent reference number. Nothing has been dropped. Where a requirement is not being delivered at launch, it still appears here, clearly marked with when, or whether, it will be delivered.

This document does three things:

1. **Confirms what is being built**, precisely enough to test against.
2. **States plainly what is not being built at launch**, so no expectation goes unrecorded.
3. **Identifies the decisions, dependencies and risks** that need your attention, several of them urgently.

Once approved and signed, this register becomes Annex A to the Services Agreement and is the definitive scope of work. Anything not in this document is a Change Request.

---

## How to read this document

| Column | Meaning |
|---|---|
| **ID** | Permanent reference. Used in every document, change request and test case throughout the project. |
| **Requirement** | What is being delivered. |
| **§** | The section of your original SRS this comes from. **A dash means it did not come from your SRS**: it originates with us, and the row says so. |
| **Scope** | Contractual classification. |
| **Stage** | Delivery timing. |

### Where each requirement came from

Your SRS is the Scope Master. This register is the contractual definition of what is being built
under the current engagement. The two are not identical, and this section makes the difference
visible rather than leaving you to infer it.

| Source type | Meaning |
|---|---|
| **Original SRS requirement** | Stated in your requirements document. The § column cites the section |
| **Client business decision** | Decided by you, recorded with its reference |
| **Developer technical decision** | Our engineering choice, made where your SRS asked us to propose one |
| **Developer recommendation** | Our proposal, **pending your approval**. Not yet agreed scope |
| **Added contract commitment** | Beyond your SRS, added by us, and contractually binding once this register is signed |
| **Approved scope reduction or phasing** | An SRS item deliberately reduced or deferred, recorded openly as such |
| **Future vision, not contracted** | In your SRS long-term vision. No price, no date, no warranty |
| **Out of scope** | Genuinely excluded from this engagement and from the agreed vision |

**The distinction is about origin, not about whether we will deliver it.** An added contract
commitment marked P1 is as binding as an SRS requirement marked P1.

### Scope values

| Value | Meaning |
|---|---|
| **P1** | Phase 1 contract: delivered in full |
| **P1-L** | Phase 1 contract: delivered to a **defined reduced specification**. Every reduction is listed in Part One §1.2 |
| **P2** | Growth phase: **not contracted**. Quoted separately when requested |
| **P3** | Long-term vision: no commitment, no price, no date |
| **OUT** | Expressly excluded. Creates no obligation, warranty or technical guarantee |
| **DLV** | A contractual deliverable rather than a software feature |
| **DEC** | A business decision required from you. Not a feature |

### Stage values

| Value | Meaning |
|---|---|
| **S1** | **First release**: live when the store launches |
| **S2** | **Second release**: delivered after the store is live, within the Phase 1 contract |
| **-** | Not in Phase 1 |

> **Contract rule:** only rows marked **P1**, **P1-L** or **DLV** create a delivery obligation. Every other row is recorded for completeness and creates no obligation.

### Markers used in this register

| Marker | Meaning |
|---|---|
| [key] | **Key requirement.** Central to the store operating as agreed |
| [attn] | **Attention.** The row carries an exclusion, dependency or qualification that changes what is delivered |
| [urgent] | **Urgent.** A decision or input that blocks the work depending on it |
| [confirmed] | **Confirmed.** A decision already settled, recorded so it is not reopened |
| [add] Added | **Added contract commitment.** Not in your SRS. Added by us and binding once signed |
| [dev] Our decision | **Developer technical decision**, made where your SRS asked us to propose |
| [rec] Pending approval | **Developer recommendation**, awaiting your approval before it becomes agreed scope |

---
---

# [attn] PART ONE: WHAT IS *NOT* LIVE AT LAUNCH

**Please read this part first and in full.** This is where a surprise is most likely to be hiding.

Everything below appears in your requirements document and is **not live in the first release**. If any of it must move into the first release, tell us **now**, at approval, not during development.

---

## 1.1 Contracted, but delivered in the second release (S2)

| ID | Feature | § | What happens in the first release instead |
|---|---|---|---|
| JRN-11 / ACCT-09 | Reorder from order history | 4 / 6.9 | Customers can view past orders; one-click reorder follows |
| IA-23 / ACCT-11 | Notification preferences page | 5 / 6.9 | Unsubscribe links in emails |
| ACCT-13 | Account deletion request from the account page | 6.9 | Handled manually on request |
| PLP-11 | Filter by rating | 6.3 | All other filters are live |
| RPT-03 | Product performance report | 11 | Basic sales data available |
| RPT-07 | Search terms report | 11 | Search works; the reporting layer follows |
| ADM-05 | Dashboard: customers, new and returning | 10.1 | Revenue, orders and units are live |
| ADM-07 | Dashboard: conversion rate | 10.1 | Available in Google Analytics |
| ADM-28 | Scheduled price changes | 10.2 | Prices changed manually or in bulk |
| ADM-71 | Reserved stock tracking | 10.4 | On-hand and available stock are live |
| ADM-101 | Customer lifetime metrics | 10.6 | Profiles and order history are live |
| ADM-105 | Customer data export under privacy controls | 10.6 | Handled manually on request |
| NOTF-14 | Admin-editable notification templates | 9 | We change email wording for you on request |
| ENT-04 | Inventory transaction records | 14 | Stock levels and adjustments are live |
| NFR-12a | Automated data export and deletion request handling | 13 | Privacy policy and consent are live; requests handled manually |

> **The Phase 1 returns and refunds requirement has been expanded into a complete launch workflow.** Your SRS §8.4 and §16.2 already place a returns and refunds workflow in Phase 1; what has changed is its depth. Live at launch: the customer return request with items, quantity and reason, optional photo evidence, admin review and approve or reject with a recorded reason, return status visible to the customer, receipt and condition recording, refund processing to the original payment method, and the related notifications. **Later, separately contracted:** automated return collection and pickup logistics, advanced disposition handling, and return reason analytics (RET-05, RET-07, RET-09). **All fifteen of your acceptance criteria are verified at launch.**

## 1.2 Live at launch, to a REDUCED specification (P1-L)

Please confirm each is acceptable. The full boundary of every P1-L item is defined in the Assumptions and Exclusions Register, Section 2.

| ID | Feature | You get at launch | Not included |
|---|---|---|---|
| SRCH-06 | Arabic search handling | Curated synonym and correction list across your main categories and brands, including common Franco-Arabic spellings | Full automatic typo tolerance across the whole catalogue |
| REV-01→05 | Product reviews | Star ratings and text, moderated before publication | Photo/video reviews, verified-purchase badges, voting, Q&A |
| WISH-01→04 | Wishlist | Save, view, move to cart | Sharing, multiple lists, price-drop alerts |
| ADM-132 | Admin activity log | Sensitive actions logged and searchable | Forensic reporting and log analytics |
| EVT-01→19 | Analytics events | Standard e-commerce event set across the full funnel | A bespoke custom event plan beyond the standard set |
| DOD-04 | Automated testing | Tests covering critical business logic: pricing, promotions, refunds, stock, duplicate-order protection | Full automated coverage of every feature |
| HND-09 | Training | Written Arabic admin manual plus a live handover session | Extended training programme or video library |
| ADM-59, 60 | Home page management | You control content, images, links and section order | Free-form drag-and-drop building with arbitrary new section types |
| NOTF-01→08 [rec] | Customer notifications | **Email only at launch** | WhatsApp and SMS automation, proposed for a later separately contracted phase. **This is a reduction against your SRS §16.2**, which lists email plus WhatsApp and SMS in the Phase 1 list, subject to the providers available at launch. Recorded here as a recommendation, not slipped past you: see §1.3 and OD-11 |
| SHIP-15 | Shipping rates | Manual rate zones by governorate, matching your carrier rate card | Live rate lookup from the carrier at checkout |
| RET-03 | Return eligibility | Automatic check against the return window you configure | Complex per-product or per-category eligibility rule engine |
| ADM-147 | Inline product edit grid | Price and stock editable across many products on one screen | Full spreadsheet-style editing of every field |

## 1.3 Not contracted: available as a separate phase (P2)

| ID | Feature | Note |
|---|---|---|
| **LOY-22 to LOY-28** | **Customer referral programme** | Referral links, two-sided rewards and fraud controls. **Phase 2** in your SRS §16.3. Specified in full at Section W so it can be quoted without re-analysis |
| **IA-13 / EX-14** | **Gift cards and gift packaging** | Your SRS §16.3 places these in **Phase 2**. Not contracted here, and quoted separately when you want them |
| **ADM-159a** [dev] | **Arabic administrative interface** | The **customer-facing store is multilingual in the first release**, English primary with Arabic fully supported. The **admin panel your team uses is English only**, see Section G10 for the reasoning |
| INT-03, 07 | WhatsApp and SMS notifications | Requires Meta business verification and carries a per-message cost |
| PAY-20 | Instalments / BNPL (valU, Souhoola, Halan, SYMPL, Aman, Forsa, MidTakseet) | Each provider requires a separate commercial approval between you and them |
| PAY-21 | Kiosk / cash-in payment | - |
| PAY-10 | Cash-on-delivery OTP verification | **Decision requested**: see Risk R-09 |
| NOTF-09, 10 | Back-in-stock and abandoned-cart recovery | - |
| AUTH-10a | Social login beyond Google, Facebook, Apple | **Google sign-in is included in the first release** (AUTH-10) |
| MKT-08 | Meta Conversions API (server-side tracking) | - |
| MKT-04, 07 | Snapchat pixel, TikTok catalogue feed | - |
| ADM-94 | Manual order creation from admin | For telephone orders |
| ADM-102 | Customer segments, VIP, dormant, returning | - |
| ADM-112, 114 | Promotion eligibility preview, promotion performance report | - |
| ADM-46 | Frequently Bought Together | The cart-level upsell prompt **is** included |
| ADM-58 | Rules-based automatic collections | Manually curated collections are included |
| ADM-62 | Scheduled home page sections | - |
| ADM-76 | Full inventory transaction log | - |
| ADM-79 | Suppliers and purchase orders | - |
| IA-12 | Product comparison | - |
| IA-34 | Category SEO landing pages | Category pages themselves are included |
| RPT-02 | Profitability and margin reporting | [attn] The **cost field is captured from day one** so this can be built later against accurate history |
| RPT-04, 05, 06 | Customer lifetime value, promotion and returns reporting | - |
| RET-05, 07, 09 | Return shipping logistics, disposition handling, return analytics | **The returns and refunds workflow itself is in the first release** |
| SHIP-02, 06, 07 | Live carrier rates, multi-carrier selection, scheduled delivery | - |
| SHIP-18 | Carrier support tickets from the order screen | - |
| INT-08 | Google Maps address autocomplete | - |
| INT-15 | External search provider | - |
| MIG-21, 22 | Import undo, scheduled recurring imports | - |
| ROLE-06a | Accountant access to advanced financial reporting | The **Accountant role itself exists at launch** (ROLE-06) with access to the Phase 1 financial operations. What follows later is the advanced profitability and lifetime-value reporting it will eventually read (RPT-02, RPT-04) |
| EVT-17 | Promotion viewed and clicked (`promotion_view`, `promotion_click`) event | Refund (EVT-15) and return-requested (EVT-19) events are **in the first release** with the returns workflow |
| PDP-23 | Back-in-stock signup | - |
| REV-06→09 | Photo reviews, verified badges, voting, Q&A | - |
| WISH-05, 06 | Wishlist sharing, price-drop alerts | - |
| PROMO-20→22 | Buy X get Y, tiered discounts, bundle pricing | - |

## 1.4 Expressly excluded (OUT)

These create **no obligation, no warranty and no technical guarantee**.

| ID | Excluded | § |
|---|---|---|
| **EX-02** [attn] | **A warranty that the architecture can be converted into a marketplace.** Phase 1 is definitively single-vendor and includes no marketplace functionality. Your SRS §1.3 asks that the design should not prevent a future conversion, and we follow that: reasonable modular and extensible engineering, no unnecessary architectural coupling. What we cannot give is a **guarantee** that conversion will be possible without substantial redesign, because a marketplace is a different business model that does not exist yet and cannot be tested against. A future marketplace would be a substantial, separately analysed and separately contracted project whose effort, feasibility, cost and required technology cannot be fixed today. **Extensibility principle, not a conversion guarantee** | 1.3 |
| EX-05 | A full ERP system built from scratch. **Integration with an external ERP is a separate matter**: see §1.5 | 1.5 |
| **EX-13** | **Blog, articles, or any content-hub section.** Recorded from your stated position at OD-22, which you should correct at signature if it no longer holds | - |
| EX-15 | Multi-currency and multi-country operations. The launch market is Egypt in Egyptian Pounds (OD-02) | - |
| EX-16 | Full automated test coverage: critical business logic only | 17.1 |
| EX-17 | Content creation: product photography, copywriting, legal drafting | - |
| EX-18 | Running marketing, advertising or SEO campaigns. **The tools those campaigns need are included**, see Section V | - |
| EX-20 | Server administration beyond initial configuration | - |
| EX-21 | Training beyond the sessions in the Statement of Work | - |
| EX-22 | Cleaning, de-duplicating, translating or enriching Client-supplied data | - |
| EX-23 | Import of Amazon order history, customer records or customer personal data | - |
| EX-25 | Recovery of data you are unable to export from Amazon | - |
| EX-26 | Any warranty that a given Amazon report contains a given field | - |
| **EX-27** [attn] | **Replication of Amazon Seller Central's interface, workflows, terminology or feature set.** Your operational capability is defined exhaustively in Section G10. Anything not listed there is a Change Request | - |
| EX-28 | Amazon-style A+ / Enhanced Brand Content authoring tools | - |
| EX-29 | An advertising console or bid management inside the store admin | - |
| EX-30 | Buy-box, seller ratings or fulfilment-network features | - |
| EX-31 | Any performance, conversion, traffic or ranking outcome | - |
| EX-32 | Integration with the Egyptian Tax Authority e-invoice or e-receipt system. Any tax reporting obligation is met through your own accounting or ERP system, not through the store | - |
| EX-33 | Accounting, bookkeeping, tax filing or financial reconciliation services | - |
| EX-34 | Legal, tax or regulatory advice of any kind | - |

---

## 1.5 Your long-term vision, not contracted (P3)

Your SRS §16.4 and §16.5 describe a Phase 3 vision beyond the growth phase. **Those items have not
been dropped, and they are not excluded.** They are simply not part of this contract, and they were
never presented as such in your own phasing.

| ID | Future vision item | § |
|---|---|---|
| EX-01 / PLT-02 | A marketplace seller model: external seller accounts and commissions | 1.3 / 1.5 / 16.4 |
| EX-03 | Native iOS and Android applications, if a business case justifies them | 1.5 / 16.4 |
| EX-04 | Progressive Web App | 16.4 |
| EX-06 / HOME-13 | AI and personalised recommendations, and a personalised home page | 16.4 |
| EX-09 | Demand and inventory forecasting | 16.4 |
| EX-10 / INT-13 | Amazon and marketplace synchronisation | 16.4 |
| EX-11 / ADM-78 | Multi-warehouse inventory | 16.4 |
| LOY-01 to LOY-16 | Loyalty points programme: earning, redemption, balance, ledger and expiry (Section W) | 16.4 / 16.5 |
| LOY-17 to LOY-21 | VIP tiers: thresholds, benefits and progress (Section W) | 16.4 / 16.5 |
| - | Advanced referral and lifecycle automation, beyond the P2 referral programme (LOY-22 to LOY-28) | 16.4 |
| EX-12 | Advanced experimentation and A/B testing | 16.4 |
| INT-14 / PLT-08 | Advanced ERP and accounting synchronisation | 12 / 16.4 |

**What P3 means here, precisely.** No price. No delivery date. No commitment to deliver. And **no
architectural guarantee**: nothing in the current engagement warrants that a Phase 3 capability can
be added to the Phase 1 and Phase 2 implementation without redesign.

> ### [attn] An honest qualification about Phase 3
>
> The platform, technology stack and architecture may be reassessed after Phase 2. Phase 3
> capabilities may require substantial architectural change or a different technology approach.
> **Their presence in your SRS future vision is not a warranty that the Phase 1 and Phase 2
> implementation can deliver them without redesign or rebuild.**
>
> We build to reasonable modular and extensible engineering standards and avoid unnecessary
> coupling, which is the honest thing we can commit to today. That is a principle we follow, not a
> conversion guarantee we can test, and the difference matters when a Phase 3 decision is eventually
> taken.
>
> **None of this is a closed door.** A future feature may be brought forward into an earlier,
> separately contracted engagement by mutual agreement. Its original SRS phase records where it
> started; it does not restrict when you may commission it.

---
---

# PART TWO: PLATFORM DECISIONS AND RECOMMENDATIONS

Your SRS left the payment provider (OD-06), the shipping provider (OD-07) and the technology
stack (§19.1) to us to propose. This part sets out what we propose and why, and states plainly
what is our judgement rather than your instruction.

**Nothing in this part is agreed scope until you approve it.** On approval of this register, our
recommendations become part of the agreed solution baseline.

## 2.1 Payment provider: Paymob [rec]

**Developer recommendation: Paymob. Pending your approval.**

*Source type: Developer recommendation, in answer to SRS §18 OD-06, which left the payment
gateway and the required payment methods open. Your SRS does not name a provider.*

- Holds **PCI DSS Level 1** certification, the highest merchant-services security tier, and operates a hosted, tokenised checkout, so card data never touches the Mizzey servers. This is the correct security posture and reduces your own compliance burden considerably.
- Maintains an **official, actively maintained WooCommerce integration**: materially lower risk than a custom build against a raw API.
- Broadest method coverage of any Egyptian provider: cards, mobile wallets, kiosk, and the widest instalment network in the market.
- Payment callbacks are **cryptographically signed**, so the store can verify a payment confirmation genuinely came from Paymob and not an attacker.
- Card settlement is next business day.

**Alternatives considered:**

| Provider | Assessment |
|---|---|
| **Fawry** | Strongest cash reach in Egypt: over 300,000 payment points covering the large majority of households. Its advantage is reaching customers who will not use a card at all. **Worth adding as a second method later** if you find you are losing mass-market customers; less critical for a premium card-and-wallet audience |
| **Kashier** | Credible and well-regarded, good plugin coverage. Comparable for this use case but narrower method and instalment range |
| **Geidea, PayTabs, Amazon Payment Services** | All viable. None offers a decisive advantage over Paymob for a single-country Egyptian store |

**We do not recommend a different provider.** Should you choose one, the system is built with a
payment abstraction layer (PAY-01) precisely so the checkout flow does not have to be re-implemented
around a different provider. If you
tell us before the checkout build begins, we can accommodate the change **without a change fee where
the new provider's integration requirements are materially equivalent**. Any material difference in
implementation effort, plugin or licensing cost, refund handling, webhook and signature verification,
or testing will be identified and agreed with you in writing before work begins. The same principle
applies to substituting any third-party provider named in this register.

> ### Which payment methods are actually live at launch
>
> Approving Paymob does not by itself settle what a customer can pay with. The methods enabled on
> launch day depend on three separate things:
>
> 1. **Your selection.** Which methods you want offered (OD-19), and whether cash on delivery is
>    offered at all (OD-05). Both are business decisions and both are still open.
> 2. **Merchant approval.** Paymob enables each live method individually after verifying your
>    business documents. A method you select is not available until they approve it.
> 3. **The agreed launch scope.** What this register records as P1 once you have signed it.
>
> Until those three line up, no document should state which methods will be live, and this one
> does not.

> ### [attn] Critical path: start this before anything else
>
> **Choosing the provider does not remove the delay risk. Merchant approval does.**
>
> Paymob requires business documents uploaded and verified, then a further step enabling each requested live payment method. In Egypt the longest part is assembling those documents, and it sits entirely with you:
>
> - **Commercial registration**, السجل التجاري
> - **Tax card**, البطاقة الضريبية
>
> **Until this is approved, the store cannot take payment, no matter how complete the build is.** This is the single longest lead item in the project and it is entirely outside our control. Start it before design or development begins.

## 2.2 Shipping carrier: Bosta [rec]

**Developer recommendation: Bosta. Pending your approval.**

*Source type: Developer recommendation, in answer to SRS §18 OD-07, which left shipping providers,
zones, pricing and service levels open. Your SRS does not name a carrier.*

- The largest technology-led courier in Egypt, with the widest Cairo coverage and a merchant dashboard built for online sellers.
- Provides an **official WooCommerce integration** covering order dispatch, automatic tracking codes, bulk sending, bulk airway-bill printing, pickup requests and status retrieval.
- Handles **cash-on-delivery collection**, which matters in a market where cash remains the dominant preference.
- Opened the region's largest automated sorting hub in Cairo in January 2026, which should support volume growth.

**Alternatives considered:**

| Carrier | Assessment |
|---|---|
| **Mylerz** | Built specifically for e-commerce, same-day in selected corridors, fulfilment support, competitive on bulk. Slightly slower cash remittance. A strong second carrier |
| **Aramex** | Broadest nationwide coverage and strong brand trust at higher per-shipment cost. Suits higher-value shipments where delivery fee is a smaller share of the order, which may fit a premium catalogue |
| **R2S** | Coverage across all 27 governorates |
| **Egypt Post / EMS** | Lowest cost, broadest reach, slowest service |

**Bosta is a sound primary choice.** Two honest caveats:

1. **Most established Egyptian stores eventually run two carriers**: typically one for Cairo/Alexandria volume and one for wider governorate coverage or higher-value items. A carrier abstraction layer is built in so a second carrier can be added as a separately scoped piece of work rather than a re-implementation of shipping.
2. **The published integration has known limitations.** Rate configuration works at city rather than fine-grained location level, which is why launch rates are manual governorate zones (SHIP-15). There have also been reported cases of statuses mapping incorrectly, specifically, parcels returned to origin showing as delivered. **We treat this as a defect to design around, not to inherit.** SHIP-14 and SHIP-17 exist to build and test an explicit status mapping, so an unpaid returned parcel is never recorded as a completed sale.

**What we need from you once this recommendation is approved.** The carrier account in your name,
the API credentials, and your rate card by governorate. These are client responsibilities, not
decisions: see Part Five. Shipping integration cannot be completed without them.

> ### [attn] Cash on delivery: needs your decision
>
> If you offer COD, there is a gap of several business days between the customer paying the courier and the money reaching your account. Bosta's remittance cycle is typically **three to five business days after delivery confirmation**.
>
> **First: your dashboard revenue is not your cash position.** An order marked Delivered is not an order whose money you hold. The system tracks collected-versus-remitted separately (SHIP-16) so this stays visible rather than hidden.
>
> **Second, and more serious: COD refusal.** Published figures for the Egyptian market put refusal at roughly **12 to 18%**, with return-to-origin at **15% or higher** for operations that do not actively manage it. This is a business risk, not a software defect, but software helps. Order confirmation contact before dispatch, and COD verification (PAY-10), are the standard mitigations. **Please tell us whether you want COD verification in scope.**


## 2.3 Technology stack: WordPress and WooCommerce [dev]

**Developer technical decision.**

*Source type: Developer technical decision, made in answer to SRS §19.1, which requires the
developer to propose a technology stack with the reasons for it and its expected running and
maintenance cost. Your SRS names no technology, and this choice is ours, not yours.*

The store is built on **WordPress with WooCommerce as the commerce engine**. The reasons:

- **The commerce core is proven and already carries your fixed decisions.** Products with variants,
  stock, carts, orders, coupons, taxes and refunds are mature and battle-tested rather than written
  from scratch for this project. That is where most bespoke e-commerce projects lose their budget.
- **Both providers we recommend publish official, actively maintained integrations for it**
  (PAY-13, SHIP-08). Integrating against a raw API instead would add cost and risk for no gain.
- **Multilingual English and Arabic with full right-to-left support is well-established territory**
  here, which matters because a multilingual storefront is a fixed decision in your SRS §2, not an
  optional extra.
- **It does not lock you to us.** Your data model is standard and exportable, hosting is yours, and
  the code is delivered to a repository you own (HND-01). Another team can pick this up.

Where WooCommerce's stock behaviour is not sufficient on its own, this register says so explicitly
rather than assuming: BR-003, BR-005 and BR-007 are the rules we commit to regardless of platform
defaults.

**This becomes part of the approved solution baseline when you approve this register.** If you
would prefer a different technology, that conversation belongs before signature, not after.

## 2.4 Corex: our framework layer, not a different commerce platform [dev]

**Developer technical decision.**

*Source type: Developer technical decision. Not referred to in your SRS.*

**WooCommerce remains the commerce engine.** The layers sit in this order:

**WordPress** → **WooCommerce** → **Corex** → **Mizzey-specific modules**

Corex is our own reusable framework and application layer around and above WooCommerce. It provides
architectural structure, custom application modules, the Operations Console (Section G10), the
storefront management controls (Section G11), the integration adapters (INT-16), the migration
tooling (Section U) and the operational safeguards this register commits to.

**Corex is not a replacement for WooCommerce, not a separate commerce platform, not a SaaS
subscription, and not a third-party hosted service.** Public reference: `MustafaShaaban/corex`.

| | |
|---|---|
| **Corex** | Pre-existing reusable framework technology, maintained independently of the Mizzey implementation. Its use remains subject to the applicable Corex licence |
| **Mizzey-specific work** | Project-specific source code, configuration, theme, templates, content and data are delivered to you according to this agreement and Part Seven |
| **WordPress and WooCommerce** | Open-source software under their own licences. Nothing here changes that |

> [attn] **For your legal adviser.** The precise licence and intellectual-property wording belongs in
> the Services Agreement and should be read against the published Corex licence file rather than
> summarised here. This section describes the architecture and the delivery position; it does not
> attempt to state licence terms.

This is consistent with the handover commitments: HND-01 puts the source code in a repository you
own, HND-04 puts hosting and domain in your name, and HND-05 gives you the database and its
migrations. Nothing in the Corex arrangement holds your store hostage, and another team can operate
and change it.

## 2.5 What we added beyond your SRS [add]

Your SRS is the Scope Master, and most of this register traces back to it. Some of what we are
committing to does not, because we judged it necessary or valuable and chose to include it rather
than sell it to you later.

These are **added contract commitments**: not in your SRS, binding on us once this register is
signed, and delivered at the scope and stage shown against each.

| Added | Where | Why we added it |
|---|---|---|
| **Google sign-in** (AUTH-10) | P1 / S1 | The most common single cause of abandoned registration is the registration form itself |
| **Cart survives every path** (CART-13, CART-14) | P1 / S1 | Guest to account, device to device, and a merge that does not duplicate. One of the most common places online stores lose completed carts |
| **Duplicate-order and duplicate-charge protection** (PAY-05) | P1 / S1 | A double-click or a repeated provider callback must never create a second order or a second charge |
| **Cash-on-delivery reconciliation** (SHIP-16) | P1-L / S1 | Collected is not remitted. Without this the dashboard would overstate your cash position |
| **The Operations Console** (Section G10) | P1 / S1 | Your SRS asks for a dashboard. Running a store daily needs a work queue, not a set of charts |
| **Self-service storefront and product control** (Section G11) | P1 / S1 | Your stated objective of running the store without daily developer dependence needs this specified, not assumed |
| **Catalogue migration tooling** (Section U) | P1 / S1 | Your SRS asks for CSV and XLSX import. Moving a live Amazon catalogue safely needs considerably more than that. See Section U |
| **Marketing team enablement and the Third-Party Access Protocol** (Section V) | P1 / S1 | Protects the store from being broken by an agency that did not build it |
| **Added business rules** (BR-009, BR-010) | P1 / S1 | Defensive rules your SRS did not state and we are not willing to leave unstated |
| **This register itself** (PRE-06) and the store operations walkthrough (PRE-07) | DLV | Finding a gap before development is cheaper for both of us than finding it after |

---
---

# PART THREE: THE FEATURE REGISTER

# SECTION A: FOUNDATION

## A1. Vision & Scope (§1)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| VIS-01 | Premium store with a large, continuously refreshed curated selection across multiple categories | 1.1 | P1 | S1 |
| VIS-02 | Focus categories: trending, life-simplifying, premium/imported, gifts, high-value picks | 1.1 | P1 | S1 |
| VIS-03 | **Ease of discovery and purchase**: search, filtering, faceted browsing, rich product pages, cart and account management to a standard comparable with leading regional stores, as specified in Sections C and A6. Operational capability for your team is defined exhaustively in Section G10 | 1.1 | P1 | S1 |
| VIS-04 | Target audience: Class A / upper-middle, willing to pay for quality, convenience and trust | 1.2 | P1 | S1 |
| VIS-05 | Audience values authenticity, delivery speed, easy returns, an organised buying experience | 1.2 | P1 | S1 |
| VIS-06 | Audience seeks new, trending and imported products without lengthy searching | 1.2 | P1 | S1 |

> **A note on VIS-03.** Your SRS describes the target experience by reference to Amazon. We understand and share the intent: a store that is fast, complete and easy to operate, where nothing gets lost. We have translated that intent into a specific, testable capability list rather than leaving it as a comparison, because a comparison cannot be tested, and therefore cannot be accepted or disputed fairly by either side. **Section G10 is that list.** It is where the operational promise of this project actually lives, and we recommend reviewing it closely.

## A2. Business Objectives (§1.4)

| ID | Objective | § | Scope | Stage |
|---|---|---|---|---|
| OBJ-01 | Increase conversion rate from visit to purchase | 1.4 | P1 | S1 |
| OBJ-02 | Raise average order value via free-shipping thresholds, bundles and cross-sell | 1.4 | P1-L | S1 |
| OBJ-03 | Build a registered customer base usable for remarketing | 1.4 | P1-L | S1 |
| OBJ-04 | Create a premium experience reinforcing trust in authenticity and quality | 1.4 | P1 | S1 |
| OBJ-05 | **Manage products, inventory, orders and offers without daily dependence on the developer** | 1.4 | **P1** [key] | S1 |
| OBJ-06 | Own clear data on sales, customer behaviour and product demand | 1.4 | P1-L | S2 |
| OBJ-07 | Prepare the platform for future integrations | 1.4 | P1-L | S2 |

> **OBJ-05 is the acceptance test for the entire admin panel.** If you must contact the developer to change a price, add a product or run an offer, the admin has failed regardless of what the feature list says. Section G10 is written against this objective.

## A3. Platform Type (§1.3, §1.5)

| ID | Constraint | § | Scope | Stage |
|---|---|---|---|---|
| PLT-01 | Version 1 is single-vendor: products sold by the store itself | 1.3 | P1 | S1 |
| PLT-02 | Single-vendor at launch: no external seller accounts, no commissions. A marketplace seller model is Phase 3 vision (§1.5), not contracted | 1.3 / 1.5 | **P1 as single-vendor.** Marketplace: P3 | S1 |
| PLT-03 | Reasonable modular and extensible engineering, avoiding unnecessary coupling, so a future direction is not gratuitously blocked. **No conversion guarantee**: EX-02 | 1.3 | **P1 as a principle.** Guarantee: OUT | S1 |
| PLT-04 | External seller accounts and commission management | 1.5 | OUT | - |
| PLT-05 | Native iOS / Android applications | 1.5 | OUT | - |
| PLT-06 | Responsive mobile-first website | 1.5 | P1 | S1 |
| PLT-07 | Full ERP system built from scratch | 1.5 | OUT | - |
| PLT-09 | Gift cards and gift packaging | 5 / 16.3 | P2 | - |
| PLT-08 | Ability to connect to an external ERP or accounting system | 1.5 | P3 | - |

## A4. Fixed Core Decisions (§2)

Your SRS marks these as **قرارات أساسية مثبتة**: settled decisions rather than preferences.

| ID | Decision | § | Scope | Stage |
|---|---|---|---|---|
| FIX-01 | Products only, not services | 2 | P1 | S1 |
| FIX-02 | Single-vendor at launch | 2 | P1 | S1 |
| FIX-03 | Premium / curated / trend-driven identity: not a discount store | 2 | P1 | S1 |
| FIX-04 | **Multilingual storefront: English and Arabic with full LTR/RTL support** | 2 | **P1** [key] | S1 |
| FIX-04a | **English is the primary and default storefront language. Arabic is the second supported language, fully delivered at launch.** Client clarification, not a requirement of the SRS | - | **P1** [key] | S1 |
| FIX-05 | Mobile-first responsive with desktop and tablet support | 2 | P1 | S1 |
| FIX-06 | Welcome discount on account creation, admin-configurable | 2 | P1 | S1 |
| FIX-07 | Two or more items grants free shipping, condition admin-configurable | 2 | P1 | S1 |
| FIX-08 | Promotion engine manageable from admin; critical rules not hard-coded | 2 | P1 | S1 |
| FIX-09 | Guest checkout supported, with encouragement to register | 2 | P1 | S1 |
| FIX-10 | **Architecture follows modular and extensible engineering principles**, intended to support future integrations and features while reducing unnecessary rework. **No guarantee is given that a future P2 or P3 capability can be added without redesign or architectural change** | 2 | P1-L | S1 |

> ### Storefront language: English primary, Arabic fully supported
>
> **FIX-04 is a first-release requirement from your SRS §2.** The storefront is multilingual, English
> and Arabic, from the first release, with no hard-coded text anywhere in it.
>
> **FIX-04a records your clarification of which language leads.** It is related to the multilingual
> requirement in your SRS §2, but the English-primary and English-default direction is a later
> **client business decision and clarification**, not something your SRS stated. Its § column is
> therefore blank: it has no SRS source. *Source type: client business decision and clarification.
> Not a technical choice of ours.*
>
> **English is the primary and default storefront language.** The canonical interface is designed
> English-first: the design system, the primary page composition and component sizing are established
> on the English experience, and English is the default language a visitor lands on.
>
> **Arabic is the second supported language and is delivered in full at launch.** Complete
> right-to-left layouts, mirrored directional patterns where direction carries meaning, correct
> alignment, Arabic typography and spacing, correct icon direction, Arabic content, the language
> switcher, and functional parity with English.
>
> **What this does not mean.** Arabic is not deferred, not Phase 2, not reduced in function, not
> optional, and not partial. Every core storefront function available in English works in Arabic
> unless an exception is recorded elsewhere in this register. Arabic remains P1 / S1.
>
> **Acceptance expectation.** English is the canonical storefront design. Arabic must maintain
> equivalent core functionality and pass right-to-left layout validation (AC-12).
>
> **The mechanism is ours; the content is yours.** We build the multilingual framework, the language
> switcher, locale-aware formatting and the right-to-left layout system. You supply the storefront
> content in both languages: product names, descriptions, specifications, category names, policy text
> and campaign copy. See CR-15.
>
> **This applies to the storefront only.** The administrative interface is English: see Section G10.
> That decision is separate and is unchanged by this clarification.

## A5. Customer Journey (§4)

| ID | Stage | Requirement | § | Scope | Stage |
|---|---|---|---|---|---|
| JRN-01 | Discovery | Arrive at Home → see Trending / New / Collections | 4 | P1 | S1 |
| JRN-02 | Find | Search, category, filters or recommendations | 4 | P1 | S1 |
| JRN-03 | Evaluate | Product page: media, price, authenticity, stock, delivery, reviews, returns, related | 4 | P1 | S1 |
| JRN-04 | Intent | Add to Cart, Buy Now or Wishlist | 4 | P1 | S1 |
| JRN-05 | Upsell | Smart prompt: add one more item for free shipping | 4 | P1-L | S1 |
| JRN-06 | Identity | Guest checkout, Google sign-in, or register; welcome discount per rules; **cart carries through every path** | 4 | **P1** [key] | S1 |
| JRN-07 | Checkout | Address → shipping → payment → review → place order | 4 | P1 | S1 |
| JRN-08 | Confirmation | Order number, summary, estimated delivery, confirmation message | 4 | P1-L | S1 |
| JRN-09 | Fulfilment | Confirmed → Preparing → Shipped → Out for Delivery → Delivered | 4 | P1 | S1 |
| JRN-10 | After-sales | Review / Return / Refund / Support | 4 | P1 | S1 |
| JRN-11 | After-sales | **Reorder**: repeat a previous order from history | 4 | P1-L | S2 |
| JRN-12 | After-sales | Personalised recommendation after purchase | 4 | P3 | - |

## A6. Information Architecture (§5)

| ID | Page | Group | § | Scope | Stage |
|---|---|---|---|---|---|
| IA-01 | Home | Storefront | 5 | P1 | S1 |
| IA-02 | Products | Storefront | 5 | P1 | S1 |
| IA-03 | Categories | Storefront | 5 | P1 | S1 |
| IA-04 | Collections | Storefront | 5 | P1 | S1 |
| IA-05 | Brands | Storefront | 5 | P1 | S1 |
| IA-06 | Search Results | Storefront | 5 | P1 | S1 |
| IA-07 | Product Details | Storefront | 5 | P1 | S1 |
| IA-08 | Cart | Shopping | 5 | P1 | S1 |
| IA-09 | Checkout | Shopping | 5 | P1 | S1 |
| IA-10 | Order Confirmation | Shopping | 5 | P1 | S1 |
| IA-11 | Wishlist | Shopping | 5 | P1-L | S1 |
| IA-12 | Compare | Shopping | 5 | P2 | - |
| IA-13 | Gift Cards | Shopping | 5 / 16.3 | P2 | - |
| IA-14 | Login | Account | 5 | P1 | S1 |
| IA-15 | Register | Account | 5 | P1 | S1 |
| IA-16 | Forgot Password | Account | 5 | P1 | S1 |
| IA-17 | My Account | Account | 5 | P1 | S1 |
| IA-18 | Addresses | Account | 5 | P1 | S1 |
| IA-19 | Orders | Account | 5 | P1 | S1 |
| IA-20 | Order Details | Account | 5 | P1 | S1 |
| IA-21 | Tracking | Account | 5 | P1 | S1 |
| IA-22 | Returns | Account | 5 | P1 | S1 |
| IA-23 | Notification Preferences | Account | 5 | P1-L | S2 |
| IA-24 | About | Trust & Support | 5 | P1 | S1 |
| IA-25 | Contact Us | Trust & Support | 5 | P1 | S1 |
| IA-26 | Help / FAQ | Trust & Support | 5 | P1 | S1 |
| IA-27 | Authenticity Guarantee | Trust & Support | 5 | P1 | S1 |
| IA-28 | Shipping Policy | Trust & Support | 5 | P1 | S1 |
| IA-29 | Return & Refund Policy | Trust & Support | 5 | P1 | S1 |
| IA-30 | Privacy Policy | Trust & Support | 5 | P1 | S1 |
| IA-31 | Terms & Conditions | Trust & Support | 5 | P1 | S1 |
| IA-32 | Offer / campaign pages | Campaign & SEO | 5 | P1-L | S1 |
| IA-33 | Brand landing pages | Campaign & SEO | 5 | P1-L | S2 |
| IA-34 | Category SEO pages | Campaign & SEO | 5 | P2 | - |
| IA-35 | Curated collection pages | Campaign & SEO | 5 | P1 | S1 |
| IA-36 | Referral and loyalty pages | Campaign & SEO | 5 | P2 | - |

---

# SECTION B: USERS, ROLES & PERMISSIONS (§3)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| ROLE-01 | **Visitor**: browse, search, filter, view products, add to cart, temporary wishlist, register, guest checkout | 3 | P1 | S1 |
| ROLE-02 | **Customer**: visitor rights plus account, addresses, orders, tracking, saved wishlist, reviews, coupons | 3 | P1 | S1 |
| ROLE-03 | **Customer Service**: view customers and orders, add notes, update service statuses | 3 | P1-L | S2 |
| ROLE-04 | **Warehouse**: view orders to prepare, pick/pack/ship, update tracking and stock | 3 | P1-L | S1 |
| ROLE-05 | **Marketing**: banners, collections, coupons, home content, marketing reports; no sensitive financial access | 3 | P1-L | S1 |
| ROLE-06 | **Accountant, permission profile**: RBAC-scoped access to the financial operations that exist at launch. Payment records and transaction references (PAY-06), refunds (PAY-07, PAY-08), order financial detail (ADM-86), invoices (ADM-92), product cost (ADM-27, RPT-11) and the sales report (RPT-01) | 3 | **P1-L** [key] | S1 |
| ROLE-06a | **Accountant, advanced financial reporting**: profitability and margin analysis (RPT-02) and customer lifetime value (RPT-04). Becomes available to the role when those reports are delivered, and not before | 3 / 11 | P2 | - |
| ROLE-07 | **Admin**: products, orders, customers, offers, integrations, users | 3 | P1 | S1 |
| ROLE-08 | **Owner / Super Admin**: full access including roles, sensitive settings, audit logs | 3 | P1 | S1 |
| ROLE-09 | Role-based access enforced: no staff member receives full admin by default | 3 | P1-L | S1 |
| ROLE-10 | Sensitive operations logged: refunds, price changes, stock changes, permission changes | 3 | P1-L | S1 |

> **On the Accountant role.** Your SRS §3 defines it with payments, refunds, invoices and costs
> and margins, each subject to permissions. The financial *operations* it needs are all Phase 1
> capabilities, so the role and its permission profile exist **at launch** (ROLE-06). What arrives
> later is the advanced financial *reporting* it will read, because those reports are themselves
> later work (ROLE-06a, RPT-02, RPT-04). There is no period during which your accountant has no
> role: there is a period during which the role has fewer reports.

---

# SECTION C: STOREFRONT (§6)

## C1. Header & Navigation (§6.1)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| NAV-01 | Logo | 6.1 | P1 | S1 |
| NAV-02 | Language switcher (AR / EN) | 6.1 | **P1** | S1 |
| NAV-03 | Clear, prominent search bar | 6.1 | P1 | S1 |
| NAV-04 | Account entry point | 6.1 | P1 | S1 |
| NAV-05 | Wishlist entry point | 6.1 | P1 | S1 |
| NAV-06 | Cart icon with live item count | 6.1 | P1 | S1 |
| NAV-07 | Mega menu / category navigation, admin-manageable | 6.1 | P1 | S1 |
| NAV-08 | Mobile: simplified header | 6.1 | P1 | S1 |
| NAV-09 | Mobile: quick search | 6.1 | P1 | S1 |
| NAV-10 | Mobile: hamburger menu | 6.1 | P1 | S1 |

## C2. Home Page (§6.2)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| HOME-01 | Hero banner / slider, admin-managed | 6.2 | P1 | S1 |
| HOME-02 | Trending products section | 6.2 | P1 | S1 |
| HOME-03 | New arrivals section | 6.2 | P1 | S1 |
| HOME-04 | Featured collections section | 6.2 | P1 | S1 |
| HOME-05 | Category shortcuts | 6.2 | P1 | S1 |
| HOME-06 | Brand highlights | 6.2 | P1 | S1 |
| HOME-07 | Promotional / offer banners | 6.2 | P1 | S1 |
| HOME-08 | Trust signals: authenticity, delivery, returns | 6.2 | P1 | S1 |
| HOME-09 | Newsletter / account signup prompt | 6.2 | P1 | S1 |
| HOME-10 | Section content editable without a developer | 6.2 | P1 | S1 |
| HOME-11 | Section order changeable without a developer | 6.2 | P1-L | S1 |
| HOME-12 | Section scheduling | 6.2 | P2 | - |
| HOME-13 | Personalised home page | 6.2 / 16.4 | P3 | - |

## C3. Product Listing, Category & Collection (§6.3)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| PLP-01 | Product grid with image, name, price, badge | 6.3 | P1 | S1 |
| PLP-02 | Sale price displayed alongside original | 6.3 | P1 | S1 |
| PLP-03 | Stock status indication | 6.3 | P1 | S1 |
| PLP-04 | Quick add to cart | 6.3 | P1 | S1 |
| PLP-05 | Quick add to wishlist | 6.3 | P1 | S1 |
| PLP-06 | Filter by category | 6.3 | P1 | S1 |
| PLP-07 | Filter by brand | 6.3 | P1 | S1 |
| PLP-08 | Filter by price range | 6.3 | P1 | S1 |
| PLP-09 | Filter by attribute (size, colour, etc.) | 6.3 | P1 | S1 |
| PLP-10 | Filter by availability | 6.3 | P1 | S1 |
| PLP-11 | Filter by rating | 6.3 | P1-L | S2 |
| PLP-12 | Sort by newest | 6.3 | P1 | S1 |
| PLP-13 | Sort by price ascending / descending | 6.3 | P1 | S1 |
| PLP-14 | Sort by best selling | 6.3 | P1 | S1 |
| PLP-15 | Sort by relevance | 6.3 | P1 | S1 |
| PLP-16 | Pagination or infinite scroll | 6.3 | P1 | S1 |
| PLP-17 | Result count displayed | 6.3 | P1 | S1 |
| PLP-18 | Active filters visible and individually removable | 6.3 | P1 | S1 |
| PLP-19 | Empty-state handling with suggestions | 6.3 | P1 | S1 |
| PLP-20 | Category banner and description | 6.3 | P1 | S1 |
| PLP-21 | Breadcrumb navigation | 6.3 | P1 | S1 |
| PLP-22 | Mobile-optimised filter drawer | 6.3 | P1 | S1 |

## C4. Search (§6.4)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| SRCH-01 | Full-text search across products | 6.4 | P1 | S1 |
| SRCH-02 | Search across name, description, SKU, brand, category | 6.4 | P1 | S1 |
| SRCH-03 | Autocomplete / suggestions as you type | 6.4 | P1 | S1 |
| SRCH-04 | Search results page with the full filter set | 6.4 | P1 | S1 |
| SRCH-05 | Zero-result page with alternative suggestions | 6.4 | P1 | S1 |
| SRCH-06 | **Arabic search handling**: synonym and correction list covering main categories and brands, including common Franco-Arabic spellings | 6.4 | P1-L | S1 |
| SRCH-06a | Search operates correctly in both English and Arabic | 6.4 | P1 | S1 |
| SRCH-07 | Admin-managed synonym list | 6.4 | P1 | S1 |
| SRCH-08 | Search term logging for later analysis | 6.4 | P1 | S1 |
| SRCH-09 | External search engine provider | 6.4 | P2 | - |

## C5. Product Details Page (§6.5)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| PDP-01 | Image gallery with zoom | 6.5 | P1 | S1 |
| PDP-02 | Video support | 6.5 | P1 | S1 |
| PDP-03 | Product name and brand | 6.5 | P1 | S1 |
| PDP-04 | Price, sale price, saving displayed | 6.5 | P1 | S1 |
| PDP-05 | Variant selection (size, colour, etc.) | 6.5 | P1 | S1 |
| PDP-06 | Stock status per variant | 6.5 | P1 | S1 |
| PDP-07 | Quantity selector | 6.5 | P1 | S1 |
| PDP-08 | Add to Cart | 6.5 | P1 | S1 |
| PDP-09 | Buy Now | 6.5 | P1 | S1 |
| PDP-10 | Add to Wishlist | 6.5 | P1 | S1 |
| PDP-11 | Short description / key features | 6.5 | P1 | S1 |
| PDP-12 | Full description tab | 6.5 | P1 | S1 |
| PDP-13 | Specifications table | 6.5 | P1 | S1 |
| PDP-14 | Delivery estimate and shipping information | 6.5 | P1-L | S1 |
| PDP-15 | Returns policy summary | 6.5 | P1 | S1 |
| PDP-16 | Authenticity guarantee statement | 6.5 | P1 | S1 |
| PDP-17 | Customer reviews display | 6.5 | P1-L | S1 |
| PDP-18 | Related products | 6.5 | P1 | S1 |
| PDP-19 | Recently viewed | 6.5 | P1 | S1 |
| PDP-20 | Frequently bought together | 6.5 | P2 | - |
| PDP-21 | Share buttons | 6.5 | P1 | S1 |
| PDP-22 | Out-of-stock handling with clear messaging | 6.5 | P1 | S1 |
| PDP-23 | Back-in-stock notification signup | 6.5 | P2 | - |
| PDP-24 | Structured data markup for search engines | 6.5 | P1 | S1 |

## C6. Cart (§6.6)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| CART-01 | Line items with image, name, variant, price, quantity | 6.6 | P1 | S1 |
| CART-02 | Update quantity | 6.6 | P1 | S1 |
| CART-03 | Remove item | 6.6 | P1 | S1 |
| CART-04 | Move to wishlist | 6.6 | P1 | S1 |
| CART-05 | Subtotal, discount, shipping, total breakdown | 6.6 | P1 | S1 |
| CART-06 | Coupon entry with clear success and failure messages | 6.6 | P1 | S1 |
| CART-07 | **Free-shipping progress indicator**, "add one more item to qualify" | 6.6 | P1 | S1 |
| CART-08 | Cross-sell suggestions in cart | 6.6 | P1-L | S1 |
| CART-09 | Cart persists for logged-in customers across devices and sessions | 6.6 | P1 | S1 |
| CART-10 | Stock re-validated when the cart is viewed | 6.6 | P1 | S1 |
| CART-11 | Empty cart state with suggestions | 6.6 | P1 | S1 |
| CART-12 | Mini-cart in header | 6.6 | P1 | S1 |
| CART-13 [add] | **Guest cart survives registration**: a guest who fills a cart and then creates an account keeps every item | - | **P1** [key] | S1 |
| CART-14 [add] | **Guest cart survives sign-in**: a guest who fills a cart and then signs in has it merged with any saved cart, with no item lost and no duplicate line | - | **P1** [key] | S1 |
| CART-15 | **Guest cart survives Google sign-in**: identical behaviour through the Google flow | - | **P1** [key] | S1 |
| CART-16 | Guest cart survives browser close and return within a defined window | - | P1 | S1 |
| CART-17 | Guest wishlist transfers to the account on registration or sign-in | 6.10 | P1-L | S1 |
| CART-18 | Merge conflicts resolved predictably: quantities combined, stock limits respected, prices re-validated | - | P1 | S1 |

## C7. Authentication (§6.7)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| AUTH-01 | Register with email and password | 6.7 | P1 | S1 |
| AUTH-02 | Register with phone number | 6.7 | P1-L | S1 |
| AUTH-03 | Login | 6.7 | P1 | S1 |
| AUTH-04 | Forgot / reset password | 6.7 | P1 | S1 |
| AUTH-05 | Email verification | 6.7 | P1 | S1 |
| AUTH-06 | **Welcome discount applied on registration per configured rules** | 6.7 | P1 | S1 |
| AUTH-07 | Guest checkout | 6.7 | P1 | S1 |
| AUTH-08 | Guest order later linked to an account securely | 6.7 | P1 | S1 |
| AUTH-09 | Marketing consent captured separately from account creation | 6.7 | P1 | S1 |
| AUTH-10 [add] | **Sign in and register with a Google account** | - | **P1** [key] | S1 |
| AUTH-11 | **One-step account creation from the order confirmation**: a guest who has just ordered becomes a registered customer by setting a password only, with no re-entry of name, email, phone or address | - | **P1** [key] | S1 |
| AUTH-12 | **Passwordless email sign-in link** as an alternative to remembering a password | - | P1-L | S1 |
| AUTH-13 | A Google sign-in and a manual registration using the same email address resolve to **one account, never two** | - | **P1** [key] | S1 |
| AUTH-14 | Account creation requires no more than email, password and consent, no unnecessary mandatory fields | - | P1 | S1 |
| AUTH-15 | Secure session handling, protection against automated account creation, and rate limiting on login attempts | 13 | **P1** [key] | S1 |
| AUTH-16 | Password reset link is single-use and time-limited | 13 | P1 | S1 |
| AUTH-10a | Social login beyond Google, Facebook, Apple | - | P2 | - |

## C8. Checkout (§6.8)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| CHK-01 | Address entry with Egyptian structure: governorate, city, district, street, landmark | 6.8 | P1 | S1 |
| CHK-02 | Saved address selection for returning customers | 6.8 | P1 | S1 |
| CHK-03 | Shipping method selection with cost | 6.8 | P1 | S1 |
| CHK-04 | Payment method selection | 6.8 | P1 | S1 |
| CHK-05 | Order review step before confirmation | 6.8 | P1 | S1 |
| CHK-06 | Order notes field | 6.8 | P1 | S1 |
| CHK-07 | Coupon entry at checkout | 6.8 | P1 | S1 |
| CHK-08 | Clear total breakdown including all charges | 6.8 | P1 | S1 |
| CHK-09 | Terms acceptance | 6.8 | P1 | S1 |
| CHK-10 | Mobile-optimised checkout | 6.8 | P1 | S1 |
| CHK-11 | Validation with clear error messages in both languages | 6.8 | P1 | S1 |
| CHK-12 | Stock re-validated at the point of order placement | 6.8 | P1 | S1 |
| CHK-13 | Address autocomplete via maps | 6.8 | P2 | - |

## C9. My Account (§6.9)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| ACCT-01 | Dashboard overview | 6.9 | P1 | S1 |
| ACCT-02 | Profile details, editable | 6.9 | P1 | S1 |
| ACCT-03 | Change password | 6.9 | P1 | S1 |
| ACCT-04 | Address book: add, edit, delete, set default | 6.9 | P1 | S1 |
| ACCT-05 | Order history list | 6.9 | P1 | S1 |
| ACCT-06 | Order detail view with full breakdown | 6.9 | P1 | S1 |
| ACCT-07 | Order tracking | 6.9 | P1 | S1 |
| ACCT-08 | Saved wishlist | 6.9 | P1 | S1 |
| ACCT-09 | Reorder from history | 6.9 | P1-L | S2 |
| ACCT-10 | Returns from account | 6.9 | P1 | S1 |
| ACCT-11 | Notification preferences | 6.9 | P1-L | S2 |
| ACCT-12 | Download invoice | 6.9 | P1-L | S1 |
| ACCT-13 | Account deletion request | 6.9 | P1-L | S2 |

## C10. Wishlist, Recently Viewed & Compare (§6.10)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| WISH-01 | Add / remove from wishlist | 6.10 | P1 | S1 |
| WISH-02 | Wishlist persists for logged-in customers | 6.10 | P1 | S1 |
| WISH-03 | Temporary wishlist for guests | 6.10 | P1 | S1 |
| WISH-04 | Move from wishlist to cart | 6.10 | P1 | S1 |
| WISH-05 | Wishlist sharing | 6.10 | P2 | - |
| WISH-06 | Price-drop alerts | 6.10 | P2 | - |
| WISH-07 | Recently viewed products | 6.10 | P1 | S1 |
| WISH-08 | Product comparison | 6.10 | P2 | - |

## C11. Reviews & Q&A (§6.11)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| REV-01 | Star rating submission | 6.11 | P1 | S1 |
| REV-02 | Written review submission | 6.11 | P1 | S1 |
| REV-03 | **Moderation before publication** | 6.11 | P1 | S1 |
| REV-04 | Average rating on product page and listing | 6.11 | P1 | S1 |
| REV-05 | Post-delivery review invitation email | 6.11 | P1 | S1 |
| REV-06 | Photo and video reviews | 6.11 | P2 | - |
| REV-07 | Verified-purchase badge | 6.11 | P2 | - |
| REV-08 | Review helpfulness voting | 6.11 | P2 | - |
| REV-09 | Customer questions and answers | 6.11 | P2 | - |

## C12. Trust & Support Pages (§6.12)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| CMS-01 | About page | 6.12 | P1 | S1 |
| CMS-02 | **Authenticity guarantee page** | 6.12 | P1 | S1 |
| CMS-03 | Shipping policy page | 6.12 | P1 | S1 |
| CMS-04 | Contact page with form | 6.12 | P1 | S1 |
| CMS-05 | FAQ / help centre | 6.12 | P1 | S1 |
| CMS-06 | Privacy policy page | 6.12 | P1 | S1 |
| CMS-07 | Return and refund policy page | 6.12 | P1 | S1 |
| CMS-08 | Terms and conditions page | 6.12 | P1 | S1 |
| CMS-09 | **All the above editable without a developer** | 6.12 | P1 | S1 |
| CMS-10 | WhatsApp click-to-chat contact link | 6.12 | P1 | S1 |

> **Please note:** the *pages* are built by us. The *legal and policy text within them* is written and supplied by you, see CR-04.

---

# SECTION D: BUSINESS RULES (§7)

## D1. Promotion Engine (§7.1)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| PROMO-01 | Percentage discount | 7.1 | P1 | S1 |
| PROMO-02 | Fixed-amount discount | 7.1 | P1 | S1 |
| PROMO-03 | Free shipping reward | 7.1 | P1 | S1 |
| PROMO-04 | Condition: minimum cart value | 7.1 | P1 | S1 |
| PROMO-05 | Condition: minimum item count | 7.1 | P1 | S1 |
| PROMO-06 | Condition: specific products | 7.1 | P1 | S1 |
| PROMO-07 | Condition: specific categories | 7.1 | P1 | S1 |
| PROMO-08 | Condition: specific brands | 7.1 | P1 | S1 |
| PROMO-09 | Condition: first order only | 7.1 | P1 | S1 |
| PROMO-10 | Condition: customer group | 7.1 | P1-L | S1 |
| PROMO-11 | Automatic promotion (no code needed) | 7.1 | P1 | S1 |
| PROMO-12 | Coupon code promotion | 7.1 | P1 | S1 |
| PROMO-13 | Usage limit: total | 7.1 | P1 | S1 |
| PROMO-14 | Usage limit: per customer | 7.1 | P1 | S1 |
| PROMO-15 | Start and end date scheduling | 7.1 | P1 | S1 |
| PROMO-16 | Stacking rules: which promotions can combine | 7.1 | P1-L | S1 |
| PROMO-17 | Priority order between competing promotions | 7.1 | P1-L | S1 |
| PROMO-18 | Product / category exclusions | 7.1 | P1 | S1 |
| PROMO-19 | Maximum discount cap | 7.1 | P1 | S1 |
| PROMO-20 | Buy X get Y | 7.1 | P2 | - |
| PROMO-21 | Tiered discounts | 7.1 | P2 | - |
| PROMO-22 | Bundle pricing | 7.1 | P2 | - |
| PROMO-23 | **All rules configurable from admin without code changes** | 7.1 | **P1** [key] | S1 |

## D2. Launch Business Rules (§7.2)

| ID | Rule | § | Scope | Stage |
|---|---|---|---|---|
| BR-001 | **Welcome discount on account creation**: value, cap, expiry and eligibility all admin-configurable | 7.2 | P1 | S1 |
| BR-002 | **Free shipping on two or more items**: condition admin-configurable | 7.2 | P1 | S1 |
| BR-003 | **Stock validation.** An order is never confirmed for a quantity above the stock actually available | 7.2 | **P1** [key] | S1 |
| BR-004 | **Promotion stacking is explicit.** Whether the welcome discount, a coupon and free shipping can combine is a setting you control, not a hidden behaviour | 7.2 | **P1** [key] | S1 |
| BR-005 | **Price snapshot.** Every order item stores the price charged at the moment of order, and a later price change never alters a past order | 7.2 | **P1** [key] | S1 |
| BR-006 | **Refund integrity.** A refund never exceeds the amount actually paid for that item or order after discounts | 7.2 | **P1** [key] | S1 |
| BR-007 | **Stock is returned on cancellation.** A cancelled or failed order releases its stock back under the agreed reservation policy | 7.2 | **P1** [key] | S1 |
| BR-008 | **Guest checkout, with a route back.** A guest can complete an order, and afterwards be invited to create an account with that order linked to it securely | 7.2 | P1 | S1 |
| BR-009 [add] | Free-shipping promotion overrides the calculated shipping rate | - | P1 | S1 |
| BR-010 [add] | Discounts never produce a negative order total | - | P1 | S1 |

> **The numbering is deliberate.** BR-001 to BR-008 carry the same numbers as the business rules in your SRS §7.2, so the two documents can be read side by side. **BR-009 and BR-010 are ours**: additional rules your SRS did not state, which we are committing to anyway. They are as binding as the rest once this register is signed.

## D3. Merchandising & Premium Positioning (§7.3)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| MER-01 | Product badges, New, Trending, Limited, Best Seller | 7.3 | P1 | S1 |
| MER-02 | Featured product flagging | 7.3 | P1 | S1 |
| MER-03 | Manual product ordering within categories and collections | 7.3 | P1 | S1 |
| MER-04 | Curated collections | 7.3 | P1 | S1 |
| MER-05 | Related product assignment | 7.3 | P1 | S1 |
| MER-06 | Premium visual treatment: imagery, typography, spacing consistent with brand | 7.3 | P1 | S1 |
| MER-07 | Discount presentation consistent with premium positioning, not discount-store styling | 7.3 | P1 | S1 |

---

# SECTION E: ORDERS, PAYMENTS, SHIPPING & RETURNS (§8)

## E1. Order Status Model (§8.1)

| ID | Status | Meaning | § | Scope | Stage |
|---|---|---|---|---|---|
| ORD-01 | Pending Payment | Order created, awaiting payment | 8.1 | P1 | S1 |
| ORD-02 | Payment Failed | Payment failed; order not confirmed | 8.1 | P1 | S1 |
| ORD-03 | Confirmed | Payment or method accepted, ready to prepare | 8.1 | P1 | S1 |
| ORD-04 | Preparing | Warehouse picking and packing | 8.1 | P1 | S1 |
| ORD-05 | Shipped | Handed to carrier, tracking available | 8.1 | P1 | S1 |
| ORD-06 | Out for Delivery | With the courier | 8.1 | P1 | S1 |
| ORD-07 | Delivered | Delivered to customer | 8.1 | P1 | S1 |
| ORD-08 | Cancelled | Cancelled with recorded reason | 8.1 | P1 | S1 |
| ORD-13 | **Delivery Failed / Returned to Origin** | Parcel not delivered and returned: a distinct state, never recorded as Delivered | - | **P1** [key] | S1 |
| ORD-14 | **COD Collected, Awaiting Remittance** | Courier holds the cash; funds not yet received | - | **P1-L** [key] | S1 |
| ORD-09 | Return Requested | Customer requested a return | 8.1 | P1 | S1 |
| ORD-10 | Return Approved / Rejected | Return decision recorded | 8.1 | P1 | S1 |
| ORD-11 | Returned | Return received | 8.1 | P1 | S1 |
| ORD-12 | Refund Pending / Refunded / Partial | Financial compensation state | 8.1 | P1 | S1 |

## E2. Payments (§8.2)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| PAY-01 | **Payment abstraction layer**: provider changeable without rebuilding checkout | 8.2 | P1 | S1 |
| PAY-02 | **No card data stored on Mizzey servers** | 8.2 | P1 | S1 |
| PAY-03 | Secure hosted and tokenised payment flow | 8.2 | P1 | S1 |
| PAY-04 | Webhook handling with signature verification | 8.2 | P1 | S1 |
| PAY-05 [add] | **Duplicate protection**: a repeated callback or double-click on Place Order never creates a duplicate charge or order | 8.2 | **P1** [key] | S1 |
| PAY-06 | Transaction reference, status, amount, currency and timestamps stored against the order | 8.2 | P1 | S1 |
| PAY-07 | Full refund from the order screen | 8.2 | P1 | S1 |
| PAY-08 | Partial refund | 8.2 | P1 | S1 |
| PAY-09 | **Cash on Delivery capability.** Built and tested. **Enabled at launch only if you select it**: the decision is open at OD-05, and no document should assume it | 8.2 | **P1 capability, conditional on OD-05** | S1 if selected |
| PAY-10 | COD verification by OTP or WhatsApp to reduce refusals | 8.2 | **P2: decision requested** | - |
| PAY-13 | Paymob via its official WooCommerce integration, wrapped in an abstraction layer | 12 | P1 | S1 |
| PAY-14 | Card payments with embedded 3-D Secure authentication | 12 | P1 | S1 |
| PAY-15 | **Mobile-wallet payment capability. Enabled at launch only if selected under OD-19 and approved for your merchant account by the payment provider** | 12 | **P1 capability, conditional on OD-19** | S1 if selected and approved |
| PAY-16 | **Cryptographic verification of every payment callback** | 8.2 | **P1** [key] | S1 |
| PAY-17 | Refunds initiated from the order screen via the provider | 8.2 | P1 | S1 |
| PAY-19 | **Settlement timing documented**: paid status is distinct from funds received | 8.2 | P1 | S1 |
| PAY-20 | Instalments / BNPL providers | 12 | P2 | - |
| PAY-21 | Kiosk / cash-in payment | 12 | P2 | - |
| PAY-22 | Instapay | 12 | P3 | - |

## E3. Shipping (§8.3)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| SHIP-01 | Shipping zones and rates manageable from admin | 8.3 | P1 | S1 |
| SHIP-03 | Shipment created and tracking number linked to the order | 8.3 | P1 | S1 |
| SHIP-04 | Delivery estimate shown to the customer where available | 8.3 | P1-L | S1 |
| SHIP-05 | Free-shipping promotion overrides the shipping rate | 8.3 | P1 | S1 |
| SHIP-08 | Bosta via its official WooCommerce integration, wrapped in an abstraction layer | 12 | P1-L | S1 |
| SHIP-09 | Order pushed to Bosta with a tracking code returned automatically | 12 | P1-L | S1 |
| SHIP-10 | **Bulk dispatch**: send many orders to Bosta at once | 12 | P1-L | S1 |
| SHIP-11 | **Bulk airway-bill printing** | 12 | P1-L | S1 |
| SHIP-12 | Pickup requests created and tracked | 12 | P1-L | S1 |
| SHIP-13 | Carrier status retrieved and reflected on the order | 12 | P1-L | S1 |
| SHIP-14 | **Explicit carrier-status-to-order-status mapping**, documented and visible in admin | - | **P1** [key] | S1 |
| SHIP-15 | Rates configured as manual zones by governorate, matching your carrier rate card | 8.3 | P1 | S1 |
| SHIP-16 [add] | **Cash-on-delivery reconciliation**: collected versus remitted recorded per order | - | **P1-L** [key] | S1 |
| SHIP-17 | **Returned-to-origin handled as a distinct state**, never silently recorded as delivered | - | **P1** [key] | S1 |
| SHIP-02 | Live rate lookup from the carrier | 8.3 | P2 | - |
| SHIP-06 | Multiple carriers with automatic selection | 8.3 | P2 | - |
| SHIP-07 | Same-day, next-day or scheduled delivery | 8.3 | P2 | - |
| SHIP-18 | Carrier support tickets from the order screen | - | P2 | - |

## E4. Returns & Refunds (§8.4)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| RET-01 | Customer request: select order item, quantity, reason, description | 8.4 | **P1** [key] | S1 |
| RET-02 | Customer request: optional photographs | 8.4 | P1 | S1 |
| RET-03 | Eligibility checked automatically against the return window you configure | 8.4 | P1-L | S1 |
| RET-04 | Review: approve or reject with a recorded reason | 8.4 | **P1** [key] | S1 |
| RET-06 | Receipt of returned item and its condition recorded | 8.4 | P1 | S1 |
| RET-08 | Refund: full or partial, with payment reference and customer notification | 8.4 | **P1** [key] | S1 |
| RET-10 | Customer sees return status at every stage from their account | 8.4 | P1 | S1 |
| RET-05 | Return shipping, pickup arrangement and instructions | 8.4 | P2 | - |
| RET-07 | Disposition: return to stock, damaged, discard | 8.4 | P2 | - |
| RET-09 | Return reason analytics by product, category or supplier | 8.4 | P2 | - |

> **The full returns and refunds workflow is live in the first release.** A customer requests a return from their account, your team reviews and approves or rejects it with a recorded reason, the returned item is received and its condition logged, and the refund is issued against the original payment with the customer notified at each step.
>
> **Return shipping remains a manual arrangement at launch (RET-05).** The system records and tracks the return; arranging collection or drop-off is handled by your team with the carrier directly. Automated return logistics is a later phase.

---

# SECTION F: NOTIFICATIONS (§9)

**Recommended launch communication scope: transactional email in Phase 1**, with manual WhatsApp click-to-chat where applicable. WhatsApp Business API and SMS automation are proposed for a later, separately contracted phase (INT-03, INT-07): both need third-party business verification, provider setup and lead time, and both carry a per-message cost. Your SRS §18 OD-11 left the launch channels and providers open, so this is our recommendation and becomes agreed scope on signature.

| ID | Event | § | Scope | Stage |
|---|---|---|---|---|
| NOTF-01 | Account created: welcome, verification, discount details | 9 | P1 (email) | S1 |
| NOTF-02 | Order confirmed: order number and summary | 9 | P1 (email) | S1 |
| NOTF-03 | Payment failed: retry prompt | 9 | P1 (email) | S1 |
| NOTF-04 | Shipped: carrier and tracking number | 9 | P1 (email) | S1 |
| NOTF-06 | Delivered: confirmation and review invitation | 9 | P1 (email) | S1 |
| NOTF-11 | Sending governed by marketing and transactional consent | 9 | P1 | S1 |
| NOTF-12 | Frequency limits respected | 9 | P1-L | S1 |
| NOTF-13 | Compliance with email provider policies | 9 | P1 | S1 |
| NOTF-07 | Return update: approved, rejected, received | 9 | P1 (email) | S1 |
| NOTF-08 | Refund completed: amount and reference | 9 | P1 (email) | S1 |
| NOTF-14 | Admin-editable notification templates | 9 | P1-L | S2 |
| NOTF-05 | Out for delivery update | 9 | P2 | - |
| NOTF-09 | Back-in-stock notification | 9 | P2 | - |
| NOTF-10 | Abandoned cart recovery | 9 | P2 | - |

---

# SECTION G: ADMIN PANEL (§10)

## G1. Dashboard (§10.1)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| ADM-01 | Revenue | 10.1 | P1 | S1 |
| ADM-02 | Orders | 10.1 | P1 | S1 |
| ADM-03 | Units sold | 10.1 | P1 | S1 |
| ADM-04 | Average order value | 10.1 | P1-L | S1 |
| ADM-08 | Charts by day, week, month, custom range | 10.1 | P1-L | S1 |
| ADM-09 | Top products | 10.1 | P1 | S1 |
| ADM-10 | Low stock | 10.1 | P1 | S1 |
| ADM-11 | Out of stock | 10.1 | P1 | S1 |
| ADM-12 | Orders requiring action | 10.1 | P1 | S1 |
| ADM-13 | Pending returns and refunds | 10.1 | P1 | S1 |
| ADM-15 | Quick actions: add product, create coupon, view pending orders | 10.1 | P1 | S1 |
| ADM-05 | Customers: new and returning | 10.1 | P1-L | S2 |
| ADM-06 | Refunds and returns | 10.1 | P1-L | S1 |
| ADM-07 | Conversion rate | 10.1 | P1-L (via GA4) | S2 |
| ADM-14 | Previous-period comparison with % change | 10.1 | P2 | - |

## G2. Product Management (§10.2)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| ADM-20 | Create, edit, archive and clone products | 10.2 | P1 | S1 |
| ADM-21 | Draft, published and scheduled states | 10.2 | P1 | S1 |
| ADM-22 | Images and video | 10.2 | P1 | S1 |
| ADM-23 | Media ordering | 10.2 | P1 | S1 |
| ADM-24 | Alt text | 10.2 | P1 | S1 |
| ADM-25 | Regular price | 10.2 | P1 | S1 |
| ADM-26 | Sale price | 10.2 | P1 | S1 |
| ADM-27 | **Product cost**: captured from day one so margin reporting can later be built on accurate history | 10.2 | **P1** [key] | S1 |
| ADM-29 | Compare-at price | 10.2 | P1-L | S1 |
| ADM-30 | Variant attributes | 10.2 | P1 | S1 |
| ADM-31 | SKU per variant | 10.2 | P1 | S1 |
| ADM-32 | Barcode per variant | 10.2 | P1 | S1 |
| ADM-33 | Price per variant | 10.2 | P1 | S1 |
| ADM-34 | Stock per variant | 10.2 | P1 | S1 |
| ADM-35 | Weight and dimensions per variant | 10.2 | P1-L | S1 |
| ADM-36 | Tax class | 10.2 | P1-L | S1 |
| ADM-37 | Shipping flags | 10.2 | P1-L | S1 |
| ADM-38 | SEO slug | 10.2 | P1 | S1 |
| ADM-39 | SEO meta title | 10.2 | P1 | S1 |
| ADM-40 | SEO meta description | 10.2 | P1 | S1 |
| ADM-41 | Canonical and robots override | 10.2 | P1 | S1 |
| ADM-42 | Badges | 10.2 | P1 | S1 |
| ADM-43 | Featured flag | 10.2 | P1 | S1 |
| ADM-44 | Collection assignment | 10.2 | P1 | S1 |
| ADM-45 | Related products | 10.2 | P1 | S1 |
| ADM-47 | **Bulk import**: see Section U | 10.2 | P1 | S1 |
| ADM-48 | **Bulk export** | 10.2 | P1 | S1 |
| ADM-49 | Bulk update: price, stock, status, category | 10.2 | P1-L | S1 |
| ADM-50 | Price and stock change history with user and timestamp | 10.2 | P1-L | S1 |
| ADM-28 | Scheduled price changes | 10.2 | P2 | - |
| ADM-46 | Frequently bought together | 10.2 | P2 | - |

## G3. Categories, Brands, Collections & Content (§10.3)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| ADM-55 | Categories and subcategories with ordering, images and SEO | 10.3 | P1 | S1 |
| ADM-56 | Brand pages with logo, description and SEO | 10.3 | P1 | S1 |
| ADM-57 | Manually curated collections | 10.3 | P1 | S1 |
| ADM-59 | Home page section management | 10.3 | P1-L | S1 |
| ADM-60 | Home page section reordering | 10.3 | P1-L | S1 |
| ADM-61 | Home page images and calls to action | 10.3 | P1 | S1 |
| ADM-63 | **Static pages, policies and FAQ editable without code** | 10.3 | P1 | S1 |
| ADM-58 | Rules-based automatic collections | 10.3 | P2 | - |
| ADM-62 | Home page section scheduling | 10.3 | P2 | - |

## G4. Inventory (§10.4)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| ADM-70 | Stock on hand | 10.4 | P1 | S1 |
| ADM-72 | Stock available | 10.4 | P1 | S1 |
| ADM-73 | Stock adjustments with reason and user recorded | 10.4 | P1-L | S1 |
| ADM-74 | Low stock threshold, per SKU or global | 10.4 | P1 | S1 |
| ADM-75 | Low and out-of-stock alerts | 10.4 | P1 | S1 |
| ADM-77 | Bulk stock import | 10.4 | P1 | S1 |
| ADM-71 | Reserved stock | 10.4 | P1-L | S2 |
| ADM-76 | Full inventory transaction log | 10.4 | P2 | - |
| ADM-78 | Multi-warehouse | 10.4 / 16.4 | P3 | - |
| ADM-79 | Suppliers and purchase orders | 10.4 | P2 | - |
| ADM-80 | Demand forecasting | 10.4 | OUT | - |

## G5. Orders (§10.5)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| ADM-85 | Search/filter by order number, customer, phone, email, status, payment, date, carrier | 10.5 | P1 | S1 |
| ADM-86 | Order detail: items, prices, discounts, customer, address, payment, shipment, notes, timeline | 10.5 | P1 | S1 |
| ADM-87 | Update status with validation | 10.5 | P1 | S1 |
| ADM-88 | Internal notes | 10.5 | P1 | S1 |
| ADM-89 | Create and attach shipment and tracking | 10.5 | P1-L | S1 |
| ADM-90 | Cancel order | 10.5 | P1 | S1 |
| ADM-91 | Full or partial refund, subject to permissions | 10.5 | P1 | S1 |
| ADM-92 | Print / download invoice | 10.5 | P1-L | S1 |
| ADM-93 | Print / download packing slip | 10.5 | P1-L | S1 |
| ADM-94 | Manual order creation | 10.5 | P2 | - |

## G6. Customers (§10.6)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| ADM-100 | Customer profile: contacts, addresses, order history | 10.6 | P1 | S1 |
| ADM-103 | Internal notes not visible to the customer | 10.6 | P1-L | S1 |
| ADM-104 | Disable or suspend an account with reason | 10.6 | P1-L | S1 |
| ADM-101 | Total spend, AOV, returns, coupon usage | 10.6 | P1-L | S2 |
| ADM-105 | Export under privacy controls | 10.6 | P1-L | S2 |
| ADM-102 | Segments: new, returning, VIP, dormant | 10.6 | P2 | - |

## G7. Promotions & Coupons (§10.7)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| ADM-110 | Create automatic promotions or coupon codes | 10.7 | P1 | S1 |
| ADM-111 | Conditions, rewards, limits, priority, stacking, exclusions | 10.7 | P1-L | S1 |
| ADM-113 | Schedule start and end | 10.7 | P1 | S1 |
| ADM-115 | Pause or deactivate without deleting usage history | 10.7 | P1 | S1 |
| ADM-112 | Preview eligibility before activation | 10.7 | P2 | - |
| ADM-114 | Usage report: orders, revenue, discount cost | 10.7 | P2 | - |

## G8. Returns & Refunds Admin (§10.8)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| ADM-120 | **Queue of new return cases** | 10.8 | **P1** [key] | S1 |
| ADM-121 | Review evidence, reason and order history | 10.8 | P1 | S1 |
| ADM-122 | Approve, reject or partially approve | 10.8 | **P1** [key] | S1 |
| ADM-123 | Record returned condition | 10.8 | P1-L | S1 |
| ADM-124 | Initiate and record refund with payment reference | 10.8 | **P1** [key] | S1 |
| ADM-125 | Full audit trail | 10.8 | P1 | S1 |
| ADM-126 | Disposition: return to stock, damaged, discard | - | P2 | - |

## G9. Users, Roles & Audit (§10.9)

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| ADM-130 | Create staff users, roles and granular permissions | 10.9 | P1-L | S1 |
| ADM-131 | Two-factor authentication for admin accounts | 10.9 | P1 | S1 |
| ADM-132 | Searchable audit log of sensitive events | 10.9 | P1-L | S1 |
| ADM-133 | **The audit trail cannot be deleted from the interface by ordinary users** | 10.9 | P1 | S1 |
| ADM-134 | Logins and significant security events recorded | 10.9 | P1 | S1 |

---

## G10. [key] STORE OPERATIONS CONSOLE

**This section defines what you and your team can actually do, day to day, without contacting a developer.** It is the practical answer to OBJ-05, and the section we most recommend you review line by line before approving.

Rather than leaving daily operations scattered across separate screens, the build includes a **dedicated Operations Console**: a single admin page organised around the jobs your team performs every day, so nothing gets missed.

### The Operations Console

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| ADM-140 [add] | **Operations Console**: a dedicated admin landing page combining the day's outstanding work in one view | - | **P1** [key] | S1 |
| ADM-141 | **Orders requiring action queue** (new, unpaid, ready to dispatch, failed delivery) worked top to bottom | - | **P1** [key] | S1 |
| ADM-142 | **Stock requiring attention**: low, out of stock, missing image, unpublished | - | P1 | S1 |
| ADM-143 | **Today's summary**: orders, revenue, units, pending dispatch | - | P1 | S1 |
| ADM-144 | **Saved views**: save any filtered list and return to it in one click | - | P1-L | S1 |
| ADM-145 | Quick actions from the console without navigating away | - | P1 | S1 |

### Product operations

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| ADM-146 | Add a simple product (title, price, stock, image, category) in under three minutes | - | P1 | S1 |
| ADM-147 | **Inline edit grid**: change price and stock across many products on one screen, without opening each | - | **P1-L** [key] | S1 |
| ADM-148 | Bulk price change: by amount or percentage across a selection | - | P1 | S1 |
| ADM-149 | Bulk stock change: set or adjust across a selection | - | P1 | S1 |
| ADM-150 | Bulk publish, unpublish or archive | - | P1 | S1 |
| ADM-151 | Bulk category assignment | - | P1-L | S1 |
| ADM-152 | Find any product within seconds by SKU, title or barcode | - | P1 | S1 |
| ADM-153 | Duplicate an existing product as a starting point | - | P1 | S1 |

### Order operations

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| ADM-154 | Bulk order status change | - | P1 | S1 |
| ADM-155 | Bulk dispatch to carrier and bulk airway-bill printing | - | P1-L | S1 |
| ADM-156 | Bulk packing slip printing | - | P1-L | S1 |
| ADM-157 | **Order search by customer phone number** | - | **P1** [key] | S1 |
| ADM-158 | Order export to spreadsheet with selectable columns | - | P1 | S1 |

### Language and access

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| ADM-159 | **Administrative interface in English**, presented consistently across both standard store screens and the custom Operations Console | - | P1 | S1 |
| ADM-159a | Arabic administrative interface | - | P2 | - |
| ADM-160 | Admin usable on a mobile device for order status checks and stock updates | - | P1-L | S1 |
| ADM-161 | Product import and export: see Section U | - | P1 | S1 |

> **A note on the admin language.** The **customer-facing store is multilingual in the first release**: English primary and default, Arabic fully supported with complete right-to-left delivery. That is where language matters commercially, and it is delivered in full.
>
> The **administrative interface is English only**, and deliberately so. The custom Operations Console and import tools are built in English, and mixing an Arabic right-to-left standard interface with English left-to-right custom screens produces a less consistent and harder-to-learn experience than a single language throughout. English is also the language in which almost all e-commerce administrative terminology, SKU, variant, fulfilment, canonical, is documented and searchable, which makes staff training and self-service troubleshooting easier.
>
> **The administrator guide and training are delivered in Arabic**, covering the English interface. This is the arrangement most Egyptian operations teams work with comfortably.
>
> An Arabic admin interface can be added later as a separate phase if it is ever needed.

---

## G11. [key] SELF-SERVICE STOREFRONT & PRODUCT CONTROL

**Everything a customer sees is editable by you, without a developer.** This is the second half of OBJ-05: not only can your team run daily operations, they can change how the store looks and what it promotes.

### Storefront content

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| SSC-01 | **Every storefront text, image and link is editable from the admin**, nothing a customer reads is locked in code | - | **P1** [key] | S1 |
| SSC-02 | Home page sections: content, images, calls to action, and order | 10.3 | P1 | S1 |
| SSC-03 | Show or hide any home page section without deleting its content | - | P1 | S1 |
| SSC-04 | Category page banners, descriptions and hero imagery, per category | 10.3 | P1 | S1 |
| SSC-05 | Brand page content, per brand | 10.3 | P1 | S1 |
| SSC-06 | Collection page content and product ordering, per collection | 10.3 | P1 | S1 |
| SSC-07 | Header navigation and mega menu structure | 6.1 | P1 | S1 |
| SSC-08 | Footer content, links and columns | - | P1 | S1 |
| SSC-09 | Promotional banners: create, schedule, target to a page, and remove | - | P1-L | S1 |
| SSC-10 | Static and policy pages, including the FAQ | 10.3 | P1 | S1 |
| SSC-11 | Campaign and offer landing pages, built without a developer | 5 | P1-L | S1 |
| SSC-12 | **All of the above editable independently in English and Arabic** | - | **P1** [key] | S1 |
| SSC-13 | **Preview any change before it goes live** | - | P1 | S1 |
| SSC-14 | Revert a content change to its previous version | - | P1-L | S1 |

### Individual product control

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| SSC-20 | **Every field on a product is editable individually**: name, description, specifications, images, price, stock, category, brand, attributes, SEO | 10.2 | **P1** [key] | S1 |
| SSC-21 | Product content maintained separately in English and Arabic | - | **P1** [key] | S1 |
| SSC-22 | Per-product badge assignment, New, Trending, Limited, Best Seller | 10.2 | P1 | S1 |
| SSC-23 | Per-product featured flag controlling home page and collection prominence | 10.2 | P1 | S1 |
| SSC-24 | Per-product placement: which collections and which position within them | 10.2 | P1 | S1 |
| SSC-25 | Per-product related-product selection | 10.2 | P1 | S1 |
| SSC-26 | Per-product visibility: published, hidden, or scheduled | 10.2 | P1 | S1 |
| SSC-27 | Per-product SEO override: title, description, slug, canonical | 10.2 | P1 | S1 |
| SSC-28 | Per-product shipping and tax treatment | 10.2 | P1-L | S1 |
| SSC-29 | Changes to any product take effect on the storefront immediately on save | - | P1 | S1 |

> **The intent of this section is that you never need to contact a developer to change what your store says or sells.** Adding a product, rewriting a description, swapping a banner, reordering a collection, launching an offer page, hiding a product that has gone out of stock, all of it is yours.
>
> **Where a developer is still required:** creating an entirely new *type* of page layout or storefront component that does not already exist in the design system. Changing the content, imagery, wording, ordering, and visibility of everything built is self-service. Inventing a new structural element is a Change Request.

---

> **Please read EX-27 alongside this section.** The Operations Console is designed around your daily workflow and we believe it will handle everything your team needs. It is not, and is not intended to be, a reproduction of any specific third-party platform. The capabilities listed above are the complete operational commitment; anything not listed is a Change Request. **If a task you perform daily today is missing from this list, please tell us during approval.**

---

# SECTION H: REPORTS & ANALYTICS (§11)

| ID | Report | § | Scope | Stage |
|---|---|---|---|---|
| RPT-01 | Sales: revenue, net sales, orders, units, AOV, discounts, shipping | 11 | P1-L | S1 |
| RPT-08 | Funnel: session → product → cart → checkout → purchase | 11 | P1-L (via GA4) | S1 |
| RPT-09 | Traffic: channel, source, campaign | 11 | P1-L (via GA4) | S1 |
| RPT-10 | Inventory: low stock, out of stock, dead stock, adjustments | 11 | P1-L | S1 |
| RPT-11 | **Product cost captured from day one** | 11 | **P1** [key] | S1 |
| RPT-03 | Products: best sellers, slow movers, conversion, stock cover | 11 | P1-L | S2 |
| RPT-07 | Search: top terms, zero-result terms, search conversion | 11 | P1-L | S2 |
| RPT-02 | Profitability, COGS, gross margin, product margin | 11 | P2 | - |
| RPT-04 | Customers: new, returning, repeat rate, lifetime value | 11 | P2 | - |
| RPT-05 | Promotions: usage and incremental performance | 11 | P2 | - |
| RPT-06 | Returns: rate, reasons, patterns | 11 | P2 | - |

---

# SECTION I: INTEGRATIONS (§12)

> **Technical rule (§12):** every external integration is isolated behind a clear adapter with retries, logging and webhook handling, so no single provider becomes hard to move away from.

| ID | Integration | § | Scope | Stage |
|---|---|---|---|---|
| INT-01 | Payment gateway, **Paymob** | 12 | P1 | S1 |
| INT-02 | Shipping carrier, **Bosta** | 12 | P1-L | S1 |
| INT-05 | Transactional email | 12 | P1 | S1 |
| INT-09 | Google Analytics 4 and Tag Manager | 12 | P1 | S1 |
| INT-10 | Meta Pixel | 12 | P1 | S1 |
| INT-12 | Google Merchant Center product feed | 12 | P1-L | S1 |
| INT-16 | **Every integration behind an adapter with retries, logging and webhook handling** | 12 | **P1** [key] | S1 |
| INT-03 | WhatsApp transactional notifications | 12 | P2 | - |
| INT-06 | Marketing email platform | 12 | P2 | - |
| INT-07 | SMS / OTP | 12 | P2 | - |
| INT-08 | Google Maps address validation | 12 | P2 | - |
| INT-11 | Meta Conversions API | 12 | P2 | - |
| INT-15 | External search provider | 12 | P2 | - |
| INT-04 | WhatsApp marketing automation | 12 | P3 | - |
| INT-14 | ERP / accounting synchronisation | 12 | P3 | - |
| INT-13 | Amazon / marketplace synchronisation | 12 / 16.4 | P3 | - |
| INT-17 | Egyptian Tax Authority e-invoice / e-receipt | - | OUT (EX-32): handled through your accounting or ERP system | - |

---

# SECTION J: NON-FUNCTIONAL REQUIREMENTS (§13)

| ID | Area | Requirement | § | Scope | Stage |
|---|---|---|---|---|---|
| NFR-01 | Mobile First | All screens responsive; **testing begins on mobile, not as a later phase** | 13 | P1 | S1 |
| NFR-02 | Performance | Image optimisation, lazy loading, caching and CDN | 13 | P1 | S1 |
| NFR-03 | SEO | Clean URLs, meta, sitemap, robots, canonical, breadcrumbs, structured data, alt text | 13 | P1 | S1 |
| NFR-04 | Localisation | **English left-to-right as the default locale, Arabic right-to-left fully supported** | 13 | **P1** [key] | S1 |
| NFR-04a | Localisation | **No hard-coded text anywhere in the storefront interface** | 13 | P1 | S1 |
| NFR-04b | Localisation | Currency, number and date formatting per locale | 13 | P1 | S1 |
| NFR-05 | Security | HTTPS, secure password storage, RBAC, admin protection, input validation, rate limiting, secret management, dependency updates | 13 | P1 | S1 |
| NFR-06 | Payments | No card data stored; provider's secure integration pattern followed | 13 | P1 | S1 |
| NFR-07 | Data Integrity | Transaction safety and duplicate protection across payments, orders and inventory | 13 | **P1** [key] | S1 |
| NFR-08 | Backups | Database and media backup, retention, and a **documented restore procedure** | 13 | P1 | S1 |
| NFR-09 | Environments | Development, staging and production; **risky changes never tested on the live site** | 13 | P1 | S1 |
| NFR-10 | Observability | Application logs, error monitoring, integration logs, admin audit logs | 13 | P1-L | S1 |
| NFR-11 | Accessibility | Keyboard usability, labels, contrast, alt text, semantic structure | 13 | P1-L | S1 |
| NFR-12 | Privacy | Consent capture, privacy policy, data minimisation | 13 | P1 | S1 |
| NFR-12a | Privacy | Automated data export and deletion request handling | 13 | P1-L | S2 |
| NFR-13 | Scalability | Growth in products, orders and integrations absorbed by resizing infrastructure and extending existing structures, rather than by re-implementing the core. Bounded by FIX-10: this is an engineering principle, not a guarantee that any future capability arrives without redesign | 13 | P1-L | S1 |
| NFR-14 | Browser Support | Current Chrome, Safari, Edge, Firefox plus mobile browsers | 13 | P1 | S1 |

---

# SECTION K: DATA MODEL (§14)

| ID | Entity | Purpose | § | Scope | Stage |
|---|---|---|---|---|---|
| ENT-01 | Product | General product data | 14 | P1 | S1 |
| ENT-02 | Product Variant | SKU, barcode, attributes, price, stock, weight | 14 | P1 | S1 |
| ENT-03 | Category / Brand / Collection | Classification, discovery, SEO | 14 | P1 | S1 |
| ENT-05 | Customer / Address | Account, addresses, preferences | 14 | P1 | S1 |
| ENT-06 | Cart / Cart Item | Current cart and pricing | 14 | P1 | S1 |
| ENT-07 | Wishlist | Saved items | 14 | P1 | S1 |
| ENT-08 | Order / Order Item | **Snapshot of prices, discounts and products at the moment of purchase** | 14 | **P1** [key] | S1 |
| ENT-09 | Payment | Transaction, reference, status, amount | 14 | P1 | S1 |
| ENT-10 | Shipment | Carrier, tracking, rate, status | 14 | P1-L | S1 |
| ENT-11 | Promotion / Coupon | Conditions, rewards, eligibility, usage | 14 | P1 | S1 |
| ENT-16 | Admin User / Role / Permission | Staff permissions | 14 | P1 | S1 |
| ENT-15 | Notification | Channel, template, status, provider reference | 14 | P1-L | S1 |
| ENT-17 | Audit Log | Actor, action, entity, before-and-after values, timestamp | 14 | P1-L | S1 |
| ENT-19 | **Import Run** | File, mapping used, row counts, outcome, actor, timestamp | - | P1 | S1 |
| ENT-14 | Review | Rating, text, status | 14 | P1-L | S1 |
| ENT-04 | Inventory Transaction | Every stock movement and its reason | 14 | P1-L | S2 |
| ENT-12 | Return Request / Item | Reason, status, attachments, decision | 14 | P1 | S1 |
| ENT-13 | Refund | Amount, status, payment reference | 14 | P1 | S1 |
| ENT-18 | Supplier / Purchase Order | Procurement and cost | 14 | P2 | - |

---

# SECTION L: ANALYTICS EVENTS (§15)

| ID | Event | § | Scope | Stage |
|---|---|---|---|---|
| EVT-01 | Home page viewed (`view_home`) | 15 | P1 | S1 |
| EVT-02 | Category, collection or search results viewed (`view_item_list`) | 15 | P1 | S1 |
| EVT-03 | Search performed, with term and result count (`search`) | 15 | P1 | S1 |
| EVT-04 | Product page viewed (`view_item`) | 15 | P1 | S1 |
| EVT-05 | Product card clicked (`select_item`) | 15 | P1 | S1 |
| EVT-06 | Added to wishlist (`add_to_wishlist`) | 15 | P1 | S1 |
| EVT-07 | Added to cart (`add_to_cart`) | 15 | P1 | S1 |
| EVT-08 | Removed from cart (`remove_from_cart`) | 15 | P1 | S1 |
| EVT-09 | Cart viewed (`view_cart`) | 15 | P1 | S1 |
| EVT-10 | Coupon applied (`apply_coupon`) | 15 | P1-L | S1 |
| EVT-11 | Checkout started (`begin_checkout`) | 15 | P1 | S1 |
| EVT-12 | Shipping information added (`add_shipping_info`) | 15 | P1 | S1 |
| EVT-13 | Payment method selected (`add_payment_info`) | 15 | P1 | S1 |
| EVT-14 | **Purchase** (`purchase`): items, tax, shipping and discount values | 15 | P1 | S1 |
| EVT-16 | Registration and login (`sign_up`, `login`) | 15 | P1 | S1 |
| EVT-18 | Search with no results (`zero_result_search`) | 15 | P1-L | S1 |
| EVT-15 | Refund issued (`refund`) | 15 | P1-L | S1 |
| EVT-19 | Return requested (`return_request`) | 15 | P1-L | S1 |
| EVT-20 | **Event Tracking Plan**: defines every event, trigger, parameters, and the source of truth per business metric | 15 | **DLV** | S1 |
| EVT-17 | Promotion viewed and clicked | 15 | P2 | - |

---

# SECTION M: DEFINITION OF DONE (§17.1)

Adopted from your SRS. Applies to every feature.

| ID | Criterion | § | Scope | Stage |
|---|---|---|---|---|
| DOD-01 | Interface implemented as approved, across all mobile and desktop states | 17.1 | P1 | S1 |
| DOD-02 | Business logic and exception cases implemented: **not only the successful path** | 17.1 | P1 | S1 |
| DOD-03 | Validation and error messages clear and correct **in both English and Arabic** | 17.1 | P1 | S1 |
| DOD-04 | Tests written and executed per system layer, plus manual testing on staging | 17.1 | P1-L | S1 |
| DOD-05 | Analytics events verified where required | 17.1 | P1 | S1 |
| DOD-06 | Staff permissions tested | 17.1 | P1 | S1 |
| DOD-07 | No known errors affecting usage | 17.1 | P1 | S1 |
| DOD-08 | Settings needed for operation documented | 17.1 | P1 | S1 |
| DOD-09 | Client approval of acceptance criteria before a feature moves to production | 17.1 | P1 | S1 |

---

# SECTION N: ACCEPTANCE SCENARIOS (§17.2)

Your own critical test cases. These become the UAT script verbatim.

| ID | Scenario | Required result | § | Scope | Stage |
|---|---|---|---|---|---|
| AC-01 | Customer adds one item | Cart shows that adding one more unlocks free shipping | 17.2 | P1 | S1 |
| AC-02 | Customer adds two items | Shipping becomes free per the active promotion, **without code change** | 17.2 | P1 | S1 |
| AC-03 | New customer registers | Welcome discount applies only if conditions are met | 17.2 | P1 | S1 |
| AC-04 | Invalid coupon | Clear reason shown; total unchanged | 17.2 | P1 | S1 |
| AC-05 | Stock runs out during checkout | Unavailable quantity not confirmed; customer informed | 17.2 | P1 | S1 |
| AC-06 | Place Order clicked twice | Exactly one order created; no duplicate charge | 17.2 | P1 | S1 |
| AC-07 | Duplicate payment confirmation | Handled safely with no duplicate effect | 17.2 | P1 | S1 |
| AC-08 | Payment fails | Order does not incorrectly become Paid or Confirmed | 17.2 | P1 | S1 |
| AC-09 | Price changed after a prior purchase | The earlier order retains its original price | 17.2 | P1 | S1 |
| AC-10 | Partial refund | Cannot exceed the amount actually paid after discount | 17.2 | P1 | S1 |
| AC-11 | Staff without permission | Cannot refund, or edit prices or roles | 17.2 | P1 | S1 |
| AC-12 | English and Arabic | All core pages render correctly in both languages **without layout break**. English is the canonical design; **Arabic must maintain equivalent core functionality and pass right-to-left layout validation** | 17.2 | P1 | S1 |
| AC-13 | Guest checkout | Purchase succeeds without an account; order can later be linked securely | 17.2 | P1 | S1 |
| AC-14 | Customer return request | Customer sees status; admin sees workflow and history | 17.2 | P1 | S1 |
| AC-15 | Out of stock | Product page, cart and checkout consistent; no unintended sale | 17.2 | P1 | S1 |
| **AC-16** | **Parcel returned to origin** | Recorded as Returned, not Delivered; stock and payment state remain correct | - | **P1** [key] | S1 |
| **AC-17** | **Catalogue import re-run** | Re-running the same file updates existing products and creates no duplicates | - | **P1** [key] | S1 |
| **AC-18** | **Guest fills a cart, then registers or signs in** | The cart carries over intact: no items lost, no duplicates | - | **P1** [key] | S1 |
| **AC-19** | **Customer signs in with Google** | Account is created or matched correctly; no duplicate account is created for the same email | - | **P1** [key] | S1 |
| **AC-20** | **Client edits a product and a storefront section unaided** | Changes appear correctly on both the English and Arabic storefront with no developer involvement | - | **P1** [key] | S1 |

> [confirmed] **All of your acceptance criteria are verified at launch.** With the Phase 1 returns and refunds workflow expanded for launch, there is no longer any deviation from your stated acceptance set. Five further scenarios have been added covering returned parcels, catalogue re-import, cart persistence, Google sign-in and self-service content management.

---
---

# SECTION U: DATA MIGRATION & CATALOGUE IMPORT [key]

Your catalogue currently lives on Amazon. This section defines how it moves to Mizzey.

## U.1 Approach

We are building a **general-purpose, mapping-driven import tool** rather than one that understands only a single Amazon report format. Amazon changes its formats, and you may later want to import from a supplier spreadsheet or your own working file. A mapping layer means you tell the system what each column means once, and save that mapping for reuse.

> **Where this section comes from.** Your SRS asks for **CSV and XLSX import and export for products
> and inventory** (§16.2) and leaves the import source as an open question (§18, OD-17). That is the
> requirement.
>
> **Everything beyond it in this section is ours, added deliberately.** Encoding handling, preview
> and dry run, column mapping and saved mappings, validation, safe reruns, SKU-based updates rather
> than duplicates, variation handling, category and brand mapping, error reports, import history,
> background processing, Arabic content handling, and image handling subject to rights. Moving a
> live catalogue off Amazon with a plain spreadsheet import is how stores end up with duplicated
> products and mangled Arabic. These rows are **added contract commitments**: binding once this
> register is signed, and delivered at the scope and stage shown against each.

## U.2 What you need to know about Amazon exports

Setting expectations accurately before work begins, because what Amazon provides is narrower than most sellers expect:

- The **standard Inventory or Active Listings report** contains basic fields only, SKU, ASIN, title, price, quantity and a single image link. It does **not** contain full descriptions, bullet points, attributes or variation structure.
- The report containing complete listing data is the **Category Listings Report**. This is **not enabled by default**. You must open a case with Amazon Seller Support requesting activation: typically up to 24 hours, and it requires a Professional Seller account. Generated reports expire after roughly seven days.
- Amazon report files are usually **tab-delimited rather than comma-separated, and frequently use an encoding that corrupts Arabic text** if handled naively. Our import tool detects and handles this. Generic import plugins usually do not.

## U.3 Rows

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| MIG-01 | Accepts CSV, tab-delimited and Excel files | - | P1 | S1 |
| MIG-02 | **Automatic character-encoding detection, verified against Arabic content** | - | **P1** [key] | S1 |
| MIG-03 | Preview of the first rows before anything is written | - | P1 | S1 |
| MIG-04 | **Column mapping interface**: match your file's columns to store fields | - | **P1** [key] | S1 |
| MIG-05 | **Saved mapping profiles**, reusable on every future import | - | P1 | S1 |
| MIG-06 | **Dry run**: full validation report showing what will be created, updated, or fail and why, before anything changes | - | **P1** [key] | S1 |
| MIG-07 | **Safe re-runs**: importing the same file again updates by SKU and never creates duplicates | - | **P1** [key] | S1 |
| MIG-08 | Control over which fields an update may overwrite | - | P1-L | S1 |
| MIG-09 | **Product variations**: parent and child listings converted into proper variable products | - | **P1** [key] | S1 |
| MIG-10 | Attribute mapping: source columns to store attributes | - | P1 | S1 |
| MIG-11 | Category mapping table, saved and reusable | - | P1 | S1 |
| MIG-12 | Brand mapping | - | P1-L | S1 |
| MIG-13 | Price, sale price and **cost** imported | - | P1 | S1 |
| MIG-14 | Stock quantity imported | - | P1 | S1 |
| MIG-15 | SEO fields imported | - | P1-L | S1 |
| MIG-16 | **Images downloaded and stored on the Mizzey server**: subject to the rights confirmation at CR-08 | - | P1-L | S1 |
| MIG-17 | Alt text generated from product title where none supplied | - | P1-L | S1 |
| MIG-18 | Import history: who, when, which file, row counts, outcome | - | P1 | S1 |
| MIG-19 | Downloadable row-level error report, correctable and re-uploadable | - | P1 | S1 |
| MIG-20 | Imports run in the background and do not fail on large files | - | P1 | S1 |
| MIG-25 | **Export** in the same mapped format, so your catalogue stays portable | - | P1 | S1 |
| MIG-21 | Undo the last import run | - | P2 | - |
| MIG-22 | Scheduled recurring imports | - | P2 | - |
| MIG-23 | Live Amazon API synchronisation | - | OUT (EX-10) | - |
| MIG-24 | Amazon order history and customer data import | - | **OUT (EX-23)** | - |

> **Why order history is excluded.** Amazon does not release complete buyer contact information to sellers, and importing marketplace customer records into a separate platform, particularly for marketing use, raises consent obligations under Egyptian data protection law that we are not in a position to resolve on your behalf. We recommend building your Mizzey customer base through the store's own registration and consent flow. Please raise this with your legal adviser if you wish to reconsider.

## U.4 What we need from you: urgently

| ID | Required from you | Needed |
|---|---|---|
| **CR-06** [attn] | [confirmed] Confirmed available. A **real, unmodified sample export file** from your Amazon account, the actual file, not a screenshot or hand-built spreadsheet. It must include at least: one simple product, one product with variations, one product with Arabic text, and one product with several images. **Until we have this, the import specification cannot be finalised** | **Before build begins** |
| CR-07 | Confirmation of whether you hold a **Professional Seller account**, and whether the **Category Listings Report** is activated. This determines whether descriptions and variation data are available at all | Before build begins |
| **CR-08** [attn] | **Written confirmation that you hold the rights to use all product images and descriptions being imported.** See R-01, the most important item in this document from a legal standpoint | **Before build begins** |
| CR-09 | The full catalogue export for migration | Before catalogue load |

---

# SECTION V: MARKETING TEAM ENABLEMENT [key]

You have indicated you will engage a media buyer, SEO specialist or marketing agency. **Running those campaigns is not part of this contract (EX-18). Making sure they have every tool they need is.**

## V.1 Measurement & advertising

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| MKT-01 | Google Analytics 4 and Tag Manager with the full e-commerce event set | 12 | P1 | S1 |
| MKT-02 | Meta (Facebook / Instagram) Pixel | 12 | P1 | S1 |
| MKT-03 | **TikTok Pixel** with standard e-commerce events | - | **P1** [key] | S1 |
| MKT-05 | **Meta product catalogue feed**: required for dynamic retargeting | - | **P1-L** [key] | S1 |
| MKT-06 | **Google Merchant Center product feed**: required for Google Shopping | 12 | **P1-L** [key] | S1 |
| MKT-09 | **Campaign tracking preserved through to the order record**, so revenue can be attributed to specific campaigns | - | **P1-L** [key] | S1 |
| MKT-10 | Consent management and cookie banner, configured for consent-aware tracking | - | P1-L | S1 |
| MKT-04 | Snapchat Pixel | - | P2 | - |
| MKT-07 | TikTok catalogue feed | - | P2 | - |
| MKT-08 | Meta Conversions API (server-side) | 12 | P2 | - |
| MKT-11 | Server-side tag management | - | P3 | - |

> **Why the product feeds matter.** Without a product feed, your media buyer cannot run dynamic retargeting on Meta or list products on Google Shopping at all: two of the highest-return channels for a catalogue business. These were originally scheduled later; we have moved them into the launch because a marketing team arriving without them cannot do their job.

## V.2 Search engine optimisation

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| MKT-12 | Editable title, description and canonical tag on every product, category and page | 10.2 | P1 | S1 |
| MKT-13 | **Redirect manager**: permanent redirects created from admin without a developer | - | **P1** [key] | S1 |
| MKT-14 | Log of missing-page errors, visible in admin | - | P1-L | S1 |
| MKT-15 | XML sitemap and robots file control | 13 | P1 | S1 |
| MKT-16 | Structured data: product, organisation, breadcrumbs | 13 | P1 | S1 |
| MKT-17 | Google Search Console and Bing Webmaster verification | - | P1 | S1 |
| MKT-18 | **hreflang language targeting tags for English and Arabic** | - | P1 | S1 |
| MKT-19 | Blog / content hub: article, archive and category templates | - | **OUT (EX-13)** | - |
| MKT-20 | Campaign landing pages, created without a developer | 5 | P1-L | S1 |

> **A note on the blog.** You have confirmed that no blog or content hub is wanted, now or in any later phase, and it is recorded as EX-13. Content management is built into the platform, so if you ever change your mind, publishing articles costs nothing extra. What would not be automatic is *designing* article, archive and category templates that match the premium brand rather than a default theme. That would be a Change Request.

## V.3 Third-party access & site protection

**This subsection protects your investment.** Marketing agencies need to add tracking and change content. Given uncontrolled access, they can also unintentionally slow the site, break checkout, or introduce security problems, and by then it is often hard to establish what changed.

| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| MKT-21 | The Marketing role **cannot install software, edit templates, or edit files** | - | **P1** [key] | S1 |
| MKT-22 | The Marketing role cannot access customer personal data beyond campaign needs | - | P1-L | S1 |
| MKT-23 | **Google Tag Manager is the sanctioned route for all tracking scripts.** No third party edits site code directly | - | **P1** [key] | S1 |
| MKT-24 | Third parties receive **staging access** for testing before any production change | - | P1-L | S1 |
| MKT-25 | All third-party administrative actions recorded in the audit log | 10.9 | P1-L | S1 |
| MKT-26 | **Third-Party Access Protocol**: a short written document you give to any agency you engage | - | **DLV** | S1 |

**The protocol will state that:**

- Third parties receive the **Marketing** role. They are not granted Administrator or Owner access. Requests for elevated access must be made by you in writing, and you accept responsibility for any resulting issues.
- All tracking scripts, pixels and tags are deployed through Google Tag Manager. No third party edits template files, installs software, or modifies production directly.
- Structural or template changes requested by a third party are handled as Change Requests, tested on staging, and deployed by us.
- You notify us when a third party is granted or loses access.
- **Diagnosis or repair of problems caused by third parties is chargeable at the standard hourly rate**, and is not covered by warranty or drawn from support retainer hours.

---
---

# SECTION W: LOYALTY & REFERRAL PROGRAMME

**Not in the Phase 1 contract.** Nothing in this section is contracted or priced. Each part is quoted
as separate work whenever you decide to commission it.

**The phases here are your SRS's own, not ours.**

| Programme | Original SRS phase | Status |
|---|---|---|
| **Basic referral programme** (W.5) | **P2, Growth**: SRS §16.3 | Not contracted. Separately scoped and quoted when requested |
| **Loyalty points** (W.1 to W.3) | **P3, Advanced**: SRS §16.4, and §16.5 marks Loyalty/VIP as P3 Retention | Not contracted. No delivery obligation, no price, no date |
| **VIP tiers** (W.4) | **P3, Advanced**: SRS §16.4 and §16.5 | Not contracted. No delivery obligation, no price, no date |

Nothing here is being taken away from the first release, because your requirements document never
placed it there.

> **A future feature may be brought forward into an earlier separately contracted engagement by
> mutual agreement.** Its original SRS phase does not prevent you commissioning it earlier. The phase
> records where it started, not a restriction on when you may buy it.

There is a commercial reason for that sequencing beyond capacity: a loyalty programme needs customers to be loyal to. Launching one on day one, with no purchase history and no repeat buyers, rewards nobody and teaches you nothing. Commissioning it a few months after launch means the earning rates and tier thresholds are set against your real order values rather than guesses.

**Why it is specified here at all.** Writing the requirement now costs you nothing and protects two
things. The data model carries the fields these programmes need from day one, so adding them later
does not mean reworking orders and customers. And when you do commission any part of it, this section
is the specification, so the work can be quoted and built without a fresh round of analysis.

## W.1 Points earning

| ID | Requirement | Scope | Stage |
|---|---|---|---|
| LOY-01 | Points earned on completed orders, at a rate you configure | P3 | - |
| LOY-02 | Points awarded only once an order is delivered and past the return window, so refunded orders do not pay out | **P3** [key] | - |
| LOY-03 | Points reversed automatically if an order is refunded after points were awarded | **P3** [key] | - |
| LOY-04 | Bonus points on configurable actions: registration, first order, review submitted | P3 | - |
| LOY-05 | Earning rate adjustable by product, category or brand | P3 | - |

## W.2 Points redemption

| ID | Requirement | Scope | Stage |
|---|---|---|---|
| LOY-06 | Points redeemable at checkout as a discount | P3 | - |
| LOY-07 | Redemption value configurable, points to Egyptian Pounds | P3 | - |
| LOY-08 | Minimum points threshold before redemption is allowed | P3 | - |
| LOY-09 | Maximum share of an order payable in points | P3 | - |
| LOY-10 | Points redemption interacts correctly with coupons and promotions under the stacking rules | **P3** [key] | - |
| LOY-11 | Points returned to the customer if the order is cancelled or refunded | **P3** [key] | - |

## W.3 Points account and ledger

| ID | Requirement | Scope | Stage |
|---|---|---|---|
| LOY-12 | Points balance visible in the customer account | P3 | - |
| LOY-13 | Points history: earned, spent, expired, adjusted, with dates and reasons | P3 | - |
| LOY-14 | Points expiry after a configurable period of inactivity | P3 | - |
| LOY-15 | Expiry warning notification before points are lost | P3 | - |
| LOY-16 | Admin can adjust a customer's points manually, with a recorded reason and audit entry | **P3** [key] | - |

## W.4 VIP tiers

| ID | Requirement | Scope | Stage |
|---|---|---|---|
| LOY-17 | Tiers based on spend or points over a configurable window | P3 | - |
| LOY-18 | Tier thresholds and names configurable from admin | P3 | - |
| LOY-19 | Tier benefits: discount percentage, free shipping, or bonus earning rate | P3 | - |
| LOY-20 | Current tier and progress to the next tier shown in the account | P3 | - |
| LOY-21 | Tier changes notified to the customer | P3 | - |

## W.5 Referral programme

| ID | Requirement | Scope | Stage |
|---|---|---|---|
| LOY-22 | Unique referral link and code per customer | P2 | - |
| LOY-23 | Reward configurable for both the referrer and the new customer | P2 | - |
| LOY-24 | Reward issued only after the referred customer's first order is delivered and past the return window | **P2** [key] | - |
| LOY-25 | **Fraud controls: no self referral, one reward per new customer, minimum order value, and duplicate detection on email, phone and address** | **P2** [key] | - |
| LOY-26 | Referral status visible to the referrer: invited, ordered, rewarded | P2 | - |
| LOY-27 | Referral performance visible in admin: invites, conversions, reward cost | P2 | - |
| LOY-28 | Referral rewards capped per customer per period | P2 | - |

## W.6 Supporting records

| ID | Entity | Scope | Stage |
|---|---|---|---|
| ENT-20 | Points ledger: customer, amount, type, reason, related order, timestamp | P3 | - |
| ENT-21 | Referral record: referrer, referred customer, status, reward, timestamps | P2 | - |
| NOTF-15 | Points earned, points expiring, tier changed (P3). Referral rewarded (P2) | P2 / P3 (email) | - |
| ROLE-11 | Loyalty and referral settings restricted to Admin and Owner roles only | **P3 for loyalty, P2 for referral** [key] | - |

> ### [attn] Two things to settle before this is ever commissioned
>
> **Points are a financial liability.** Every point issued is money you owe against a future order. Points that never expire accumulate indefinitely on your balance sheet. This is why LOY-14 exists, and why LOY-02 and LOY-03 tie earning to delivered, non refunded orders. The accounting treatment needs to be agreed with your accountant before any earning rate is set.
>
> **Referral programmes attract abuse.** Without controls, one person creates multiple accounts and refers themselves, and you pay real discounts to a fake customer base. LOY-24 and LOY-25 are the defences and are not optional parts of the feature.

## W.7 Decisions required if this is commissioned

These are recorded so the quotation can be prepared quickly. **None of them blocks the first release**, and none needs an answer now.

| ID | Decision | Needed |
|---|---|---|
| OD-33 | Points earning rate. How many points per Egyptian Pound spent | If commissioned |
| OD-34 | Points value on redemption. What is a point worth | If commissioned |
| OD-35 | Minimum redemption threshold and maximum share of an order payable in points | If commissioned |
| OD-36 | Points expiry period, or no expiry | If commissioned |
| OD-37 | Do points and coupons combine, or is it one or the other | If commissioned |
| OD-38 | VIP tier names, thresholds and benefits | If commissioned |
| OD-39 | Referral reward for the referrer and for the new customer | If commissioned |
| OD-40 | Minimum order value for a referral to qualify | If commissioned |

---

# PART FOUR: RISK & COMPLIANCE REGISTER

Recorded so these are visible and owned, rather than discovered late.

> **Important:** the items below are operational and commercial observations from building e-commerce systems in this market. **They are not legal, tax or regulatory advice and must not be relied on as such.** Several concern areas of Egyptian law where you should take advice from a qualified Egyptian lawyer and tax adviser. We raise them because it would be worse not to.

| ID | Risk | Owner | Action required |
|---|---|---|---|
| **R-01** [attn] | **Rights in imported product images and descriptions.** As a reseller, a proportion of the imagery and copy on your Amazon listings will have been produced by brand owners, suppliers, photographers or Amazon. Importing that material into your own store is a different use from listing on Amazon and may carry rights obligations | **Client** | Provide the written confirmation at CR-08. Take legal advice if uncertain about any brand |
| **R-03** [attn] | **Hosting location and data protection.** **Cloudways is the recommended host on technical grounds**, managed infrastructure, strong performance, straightforward scaling. Its data centres are **outside Egypt**. Egypt's Personal Data Protection Law No. 151 of 2020 became fully operational when its Executive Regulations took effect in November 2025, and those regulations treat the *storage* of Egyptian personal data on servers outside Egypt as a cross-border transfer requiring **prior authorisation from the Data Protection Centre**. Hosting your customer data abroad is therefore a compliance question, not only a technical one | **Client** (as data controller) | **Confirm with an Egyptian lawyer that hosting outside Egypt is acceptable for your business, and obtain any licence required.** We will proceed with Cloudways on your written instruction. This must be settled before infrastructure is provisioned |
| **R-04** | **Electronic marketing consent.** The same regulations address direct electronic marketing. Your marketing team's email, SMS and WhatsApp activity is subject to consent obligations. The store captures marketing consent separately from account creation (AUTH-09) to support this | **Client** | Ensure your marketing team operates within your consent records |
| **R-05** | **Consumer protection disclosures.** Egyptian consumer protection law imposes requirements on distance selling, seller identity, total price disclosure, return rights, clear terms. We build the pages; the content is yours | **Client** | Have your policies (CR-04) reviewed by an Egyptian lawyer before launch |
| **R-06** [attn] | **Payment merchant approval is the longest lead item.** Commercial registration and tax card are prerequisites, and approval is outside our control | **Client** | **Start before design or development begins.** Nothing else in the project compensates for delay here |
| **R-07** | **Third-party platform account standing.** We do not access, operate or hold credentials for your Amazon Seller Central account at any point. All exports are performed by you using Amazon's own reporting tools. We accept no responsibility for the standing of your Amazon account | Shared | Perform all exports yourself. Never share Seller Central credentials with any supplier, including us |
| **R-08** | **Third-party integration dependency.** Payment and carrier integrations depend on software maintained by Paymob and Bosta. Version conflicts and behavioural changes in third-party software are a known and normal risk. We mitigate by version-pinning and regression-testing on staging before updates | Shared | Maintain a support arrangement after warranty so updates are tested rather than applied blindly |
| **R-09** | **Cash-on-delivery refusal and returns.** Published market figures put refusal at roughly 12 to 18% and return-to-origin at 15% or higher for unmanaged operations. A business risk affecting cash flow, not a software defect | **Client** | Decide whether COD verification (PAY-10) should be added to scope. Consider order confirmation contact before dispatch |
| **R-10** | **Cash flow timing.** COD funds arrive several days after delivery; card settlement is next business day. Your working capital requirement is larger than daily revenue suggests | **Client** | Plan working capital accordingly |
| **R-11** | **Content and data quality.** The store's quality is bounded by the quality of the data you supply. The import tool moves data faithfully; it does not improve it | **Client** | Review your Amazon data quality before migration |
| **R-12** | **Security after handover.** Ongoing security depends on timely software updates. Once warranty ends, an unmaintained site becomes progressively more exposed | **Client** | Arrange a support and maintenance agreement covering the period after warranty |
| **R-13** [attn] | **Framework licensing.** The commerce engine is WooCommerce. Corex is the Developer's pre-existing reusable framework and application layer around it (§2.4), maintained independently of this project and subject to its own published licence. Project-specific Mizzey code, configuration, content and data are delivered under Part Seven | Shared | **The licence and intellectual-property wording is set in the Services Agreement and should be reviewed by your legal adviser against the published Corex licence.** This register does not state licence terms |
| **R-14** | **Third-party software licences.** Certain commercial components require annual licences held in your name. These are your ongoing operating costs, listed in the Hosting & Technology specification | **Client** | Budget for annual renewals |
| **R-15** | **Logo and brand rights.** The store name and logo must be cleared for use. Where a logo is produced by an external designer, full rights should be assigned to you in writing | **Client** | Obtain written assignment from your designer. Take advice on trademark clearance |
| **R-16** | **Loyalty points are a financial liability.** Points issued are a future obligation against your revenue. Uncapped or non expiring points accumulate indefinitely. **Recorded for the future: loyalty is not in this contract** (Section W) | **Client** | Agree the accounting treatment with your accountant before any earning rate is set, if the programme is ever commissioned (OD-33) |
| **R-17** | **Referral programmes attract fraud.** Self referral through duplicate accounts is the most common form. **Recorded for the future: referrals are not in this contract** (Section W) | Shared | The fraud controls are specified (LOY-25) and would be built with the programme, not bolted on afterwards |

---

# PART FIVE: YOUR RESPONSIBILITIES

Delivery assumes these are met. **Any delay in the items below delays delivery of everything that depends on them.**

These are things you **provide**. They are separate from the things you **decide** (Part Six) and from
the things we **recommend** (Part Two). A recommendation of ours does not become your responsibility
until you approve it: CR-01 and CR-03 are written provider-neutral for that reason.

| ID | Responsibility | Needed | If not provided |
|---|---|---|---|
| **CR-01** [attn] | **Payment merchant account approved**: commercial registration and tax card | **Start first: longest lead item** | **Launch blocked** |
| CR-02 | Written approval of brand direction | Before design work | All design work stops |
| CR-03 | Carrier account, API key and rate card by governorate | Before shipping build | Checkout cannot be completed |
| CR-04 | **Legal and policy text**: shipping, returns, privacy, terms | Before launch | Launch blocked |
| CR-05 | Product photographs meeting the agreed standard | Before catalogue load | Launch blocked |
| **CR-06** [attn] | **Amazon sample export file**: confirmed available; please supply it | **Before build begins** | **Import specification cannot be finalised** |
| CR-07 | Confirmation of Amazon account type and report availability | Before build begins | Import scope uncertain |
| **CR-08** [attn] | **Written confirmation of image and content rights** | **Before build begins** | Images will not be imported |
| CR-09 | Full catalogue export | Before catalogue load | Launch blocked |
| CR-10 [attn] | **Written instruction to host on Cloudways (outside Egypt)**, following the legal advice at R-03 | **Before infrastructure setup** | Infrastructure cannot be provisioned |
| CR-12 | Domain, sender email, support numbers, legal entity details | Before launch | Launch blocked |
| CR-13 | Feedback on any deliverable | Within 5 business days | Deliverable deemed approved |
| CR-14 | UAT completed within the agreed window | At UAT | Build deemed accepted |
| **CR-15** [attn] | **Storefront content in both languages**: product names, descriptions, specifications, category and brand names, policy text and campaign copy, in English (the primary language) and Arabic | Before catalogue load | **Store cannot go live multilingual** |

**Also yours throughout:** product names, descriptions, specifications, prices, categories and attributes; all photography; all legal and policy text; home page and campaign copy; and category descriptions, **in both English and Arabic**. **We build the mechanisms, the content is yours.**

> [attn] **CR-15 deserves particular attention.** A multilingual store needs two versions of everything a customer reads. The framework, switcher, right-to-left layout and locale formatting are ours to build; the words are yours to supply, in both languages. **A multilingual store with content in only one language is not a multilingual store**, and this is the single most common reason such launches slip. Please plan the content and translation capacity alongside the build rather than at the end of it. This responsibility is unchanged by the English-primary clarification: it was always both languages.

---

# PART SIX: DECISIONS WE NEED FROM YOU

Business decisions, not features. **We will not assume values on your behalf.**

Every decision below carries one of four statuses. The distinction matters: a decision we have
recommended is not a decision you have taken.

| Status | Meaning |
|---|---|
| **Confirmed by you** | Settled by you. Recorded so it is not reopened |
| **Recommended by us, pending your approval** | Our proposal. It becomes agreed scope when you sign this register, not before |
| **Still open** | Needs an answer from you, with the work it blocks named |
| **Not needed for this phase** | Recorded, but nothing in the current engagement depends on it |

## Confirmed by you

| ID | Decision | Position recorded |
|---|---|---|
| **FIX-04** | Store language | **Multilingual English and Arabic storefront in the first release.** A fixed decision in your SRS §2, not an open question |
| **FIX-04a** | Primary storefront language | **English is the primary and default storefront language; Arabic is fully supported at launch** with complete right-to-left delivery. Your clarification, recorded as a client business decision. The English-first design direction follows from it (§A4) |
| **OD-02** | Launch market and currency | **Egypt only, Egyptian Pound**, in the first release |
| **OD-22** | Blog / content hub | **Not in scope.** Recorded as EX-13 |
| **OD-24** | Amazon sample export available? | **Yes.** Please supply the file: see CR-06 |
| **OD-30** | Returns and refunds | **Full workflow in the first release** |
| **OD-31** | Guest checkout | **In the first release.** A fixed decision in your SRS §2 and §6.7, not an open question |
| **OD-32** | Self-service storefront and product management, to the SRS baseline | **In the first release.** Your SRS §10.2 and §10.3 require product, category, collection and CMS management |

> **Please check this table before you sign.** These are recorded from our discussions with you. If
> any row does not reflect your position, correct it at signature: it is far easier to change here
> than to unpick later.

## Recommended by us, pending your approval

Our proposals, not your decisions. Each becomes part of the agreed scope when you sign.

| ID | Decision | Our recommendation | Type |
|---|---|---|---|
| **OD-06** [rec] | Payment provider | **Paymob**, with the reasoning and alternatives at §2.1. Merchant approval remains the longest lead item in the project | Developer recommendation |
| **OD-07** [rec] | Shipping carrier | **Bosta**, with the reasoning, alternatives and caveats at §2.2, behind a carrier abstraction so a second can be added | Developer recommendation |
| **OD-11** [rec] | Communication channels at launch, and the provider for each | **Transactional email in Phase 1**, with manual WhatsApp click-to-chat where applicable. WhatsApp Business API and SMS automation proposed for a later, separately contracted phase (INT-03, INT-07), because of business verification, provider setup, lead time, per-message cost and added complexity. **Note that your SRS §16.2 lists email plus WhatsApp and SMS in the Phase 1 set, subject to the providers available at launch**, so this is a proposed reduction and is recorded as one in §1.2 | Developer recommendation |
| **OD-10** [rec] | Same-day or next-day delivery at launch | **Not at launch.** Standard carrier delivery. Scheduled and express delivery recorded as P2 (SHIP-06, SHIP-07) | Developer recommendation |
| **OD-17** [rec] | Source of product imports | **Amazon export files, plus CSV and XLSX.** The import tool reads both (Section U). Google Sheets is not proposed as a supported source | Developer recommendation |
| **ADM-159** [dev] | Admin interface language | **English only**, with the administrator guide and training delivered in Arabic. The reasoning is at Section G10. Your SRS fixes the *storefront* as multilingual and is silent on the admin | Developer technical decision |
| **Stack** [dev] | Technology stack | **WordPress with WooCommerce**, per §2.3, in answer to your SRS §19.1 | Developer technical decision |

## Added by us, and contractual once you sign

These were **not** requested in your SRS and were **not** decided by you. We added them. They are in
the first release and become binding when you sign, but their origin is ours and this register does
not pretend otherwise.

| ID | Added capability | Scope / Stage | Type |
|---|---|---|---|
| **AUTH-10** [add] | Sign in and register with a Google account | P1 / S1 | Added contract commitment |
| **CART-13, CART-14** [add] | Guest cart survives registration and sign-in, merged without duplicates or loss | P1 / S1 | Added contract commitment |
| **Section G11** [add] | Self-service storefront and product control **beyond the SRS baseline at OD-32**: home page section control, campaign pages, per-category imagery and the preview-before-publish flow | P1 / S1 | Added contract commitment |
| **Section G10** [add] | The Operations Console | P1 / S1 | Added contract commitment |
| **Section U** [add] | Catalogue migration tooling beyond the CSV and XLSX requirement | P1 / S1 | Added contract commitment |
| **PAY-05, SHIP-16, BR-009, BR-010** [add] | Duplicate order and charge protection, cash-on-delivery reconciliation, and the added business rules | P1 / S1 | Added contract commitment |

> **Signing accepts them as scope. It does not rewrite their history.** Nothing in this table becomes
> a client decision because you sign the register: it becomes a contractual obligation of ours.

## Still open

| ID | Decision required | Blocks | Needed | Status |
|---|---|---|---|---|
| **OD-27** [attn] | **Hosting outside Egypt confirmed?** Cloudways is recommended and sits outside Egypt. We need your written instruction to proceed, having taken the legal advice at R-03 | All infrastructure | **Before infrastructure setup** | [note] |
| **OD-15** [attn] | **Expected product count, order volume and traffic** | Infrastructure sizing | **Before infrastructure setup** | [note] See note below |
| OD-01 | Brand name, logo, visual identity | All design | Before design work | [urgent] |
| OD-03 | Welcome discount: value, cap, expiry, eligibility | BR-001 | Before promotions build | [urgent] |
| OD-04 | Free shipping: does "two items" mean two units or two distinct products? Exclusions? | BR-002 | Before promotions build | [urgent] |
| OD-05 | Is COD offered? Value limits, zones, fees? | PAY-09 | Before checkout build | [urgent] |
| OD-08 | Return window and any product-type exceptions | RET-03, CMS-07 | Before returns build | [urgent] |
| OD-09 | VAT and invoicing requirements for your legal entity | Checkout, invoicing | Before checkout build | [urgent] |
| OD-12 | Product cost basis: purchase price only or landed cost? Who enters it? | ADM-27, RPT-11 | Before catalogue load | [urgent] |
| OD-13 | Authenticity and warranty policy, and supporting documents | CMS-02, PDP-16 | Before content build | [urgent] |
| OD-19 | Which payment methods in the first release: cards only, or cards and wallets? | Checkout | Before checkout build | [urgent] |
| OD-20 | Carrier COD collection and remittance cycle | SHIP-16, reporting | Before shipping build | [urgent] |
| OD-21 | Which ad platforms in the first release, Meta only, or Meta + TikTok + Google Shopping? | MKT-03→07 | Before marketing build | [urgent] |
| OD-26 [attn] | **Image and content rights confirmed in writing?** | Image import | **Before build begins** | [urgent] |
| OD-29 | Add COD verification (PAY-10) to scope? (R-09) | Checkout | Before checkout build | [urgent] |
| OD-14 | **Monthly operating budget for hosting and services.** Cloudways plus email, SMS and monitoring all carry a recurring cost that is yours, not ours | Infrastructure sizing and provider choice | Before infrastructure setup | [urgent] |
| OD-18 | **Domain, sending email address, support phone numbers, and the legal entity details** that appear on invoices and policy pages | Launch | Before launch | [urgent] |

> ### On OD-15: sizing without a firm number
>
> You have indicated that expected product count, order volume and traffic are not yet known. That is normal for a first launch and does not need to block anything.
>
> **We will proceed on a stated assumption:** the store is built and provisioned to handle up to **5,000 products** and **300 orders per day** comfortably, with headroom for short campaign peaks. This is recorded as an assumption rather than a guess, so that both sides know what was sized for.
>
> If actual volumes exceed this materially, resizing the infrastructure is normally handled as a change to the hosting plan rather than a re-implementation: the architecture (NFR-13) is built with that in mind. **Please confirm this assumption is reasonable, or correct it.**

> ### On OD-16: your ERP
>
> You have confirmed an ERP system is in development and close to complete. **ERP integration is not in this contract** and no date or commitment is given for it.
>
> **What this register does commit to is reducing the effort of that future integration, not eliminating it.** The current architecture provides clean integration boundaries and exportable data: every external connection is isolated behind an adapter (INT-16), orders and products are stored with clean, exportable structure, and a full catalogue export is available (MIG-25).
>
> **The eventual integration may still require implementation or architectural changes**, depending on the ERP's API, its data model, its authentication, the workflows and synchronisation required, the infrastructure involved and the business requirements at the time. When your ERP is ready, integration is scoped and quoted as a separate phase.
>
> **One request:** when the ERP's data structure is settled, share it with us. Knowing the shape of the system the store will eventually talk to costs nothing now and can save significant rework later.

---

# PART SEVEN: DELIVERABLES

## Pre-development (§19.1)

| ID | Deliverable | § | Scope | Stage |
|---|---|---|---|---|
| PRE-01 | Technical design and architecture document | 19.1 | DLV | S1 |
| PRE-02 | Technology stack proposal with reasoning and expected operating cost | 19.1 | DLV | S1 |
| PRE-03 | Sitemap, user flows, wireframes and UI design, approved before full implementation. **The canonical design source is English (FIX-04a); the Arabic layouts are the right-to-left localisation of that approved design, not a separate design** | 19.1 | DLV | S1 |
| PRE-04 | Project broken into milestones and deliverables | 19.1 | DLV | S1 |
| PRE-05 | External integrations identified, with accounts and costs required from you | 19.1 | DLV | S1 |
| PRE-06 | Requirements needing change or carrying technical risk identified, **this document** | 19.1 | DLV | S1 |
| **PRE-07** [key] | **Store operations walkthrough**: a live demonstration of the actual admin environment, with the Section G10 list reviewed together and confirmed, before development begins | - | **DLV** | S1 |

> **PRE-07 is strongly recommended.** Rather than describing how the store will be managed, we will show you a working environment and let you perform the tasks you do every day. If something you need is missing, we would far rather find out before development begins than after the build is complete.

## Handover (§19.2)

| ID | Deliverable | § | Scope | Stage |
|---|---|---|---|---|
| HND-01 | Source code in a repository you own, with clear development history | 19.2 | DLV | S1 |
| HND-02 | Design source files, design system and assets | 19.2 | DLV | S1 |
| HND-03 | Documentation: setup, deployment, **environment variables**, integrations, and an **Arabic administrator guide covering the English interface** | 19.2 | DLV | S1 |
| HND-04 | Domain, DNS, hosting and cloud access in your name | 19.2 | DLV | S1 |
| HND-05 | Database schema, migrations, and **backup and restore instructions** | 19.2 | DLV | S1 |
| HND-06 | Payment, shipping, email, SMS and analytics accounts under your ownership | 19.2 | DLV | S1 |
| HND-07 | Staging environment plus test cases and acceptance results | 19.2 | DLV | S1 |
| HND-08 | Production deployment and a **documented rollback procedure** | 19.2 | DLV | S1 |
| HND-09 | Admin training session, credentials handover, and a **known-issues list** | 19.2 | DLV | S1 |
| HND-10 | Warranty period for defect correction, **separate from new features** | 19.2 | DLV | S1 |
| HND-11 | **Third-Party Access Protocol** (MKT-26) | - | DLV | S1 |
| HND-12 | **Event Tracking Plan** (EVT-20) | 15 | DLV | S1 |

---

# PART EIGHT: CHANGE MANAGEMENT (§19.3)

| ID | Rule | § |
|---|---|---|
| CHG-01 | Every feature in your SRS is recorded within this register | 19.3 |
| CHG-02 | Every feature is assigned a phase, as your §19.3 requires. **This document is that assignment** | 19.3 |
| CHG-03 | Any request not in this document is recorded as a Change Request, with cost and schedule impact stated in writing **before** work begins | 19.3 |
| CHG-04 | Approving this register does not prevent change. It ensures change is visible, priced and agreed, rather than absorbed silently at the expense of the agreed scope | - |

---

# APPROVAL

By signing below, you confirm that:

1. You have read this document in full, **including Part One: what is not live at launch**.
2. You accept the scope classification (P1 / P1-L / P2 / P3 / OUT) and delivery staging (S1 / S2) recorded here.
3. You specifically acknowledge:
 - **ADM-159**: the storefront is multilingual, English primary with Arabic fully supported; the **administrative interface is English only**, with the administrator guide and training delivered in Arabic
 - **CR-15**: **storefront content in both languages is supplied by you.** We build the multilingual mechanism, not the words
 - **EX-02**: Phase 1 is single-vendor, built with reasonable extensibility, and **no guarantee is given that it can later be converted into a marketplace without substantial redesign**
 - **EX-13**: no blog or content-hub section, now or in any later phase
 - **EX-27**: the admin is not a reproduction of any third-party platform; Sections G10 and G11 are the complete operational commitment
 - **EX-32**: electronic tax receipt integration is not included; tax reporting is handled through your own accounting or ERP system
 - **R-03 / CR-10**: hosting on Cloudways places customer data outside Egypt, and you have taken your own legal advice on that
 - **OD-15**: infrastructure is sized on the stated assumption of up to 5,000 products and 300 orders per day
 - **Section W**: the **referral programme is specified at P2, while the loyalty points programme and VIP tiers are specified at P3**, matching their placement in your own SRS §16.3, §16.4 and §16.5. **None of these three is included in the current contract**, and each may be separately scoped and quoted if you commission it
4. You have reviewed the Risk & Compliance Register at Part Four, and understand that items requiring legal or tax advice are yours to resolve with qualified advisers.
5. You accept the responsibilities and deadlines at Part Five.
6. This register becomes Annex A to the Services Agreement and is the definitive scope of work.

| | Client | Developer |
|---|---|---|
| Name | | Mustafa Shaaban |
| Position | | |
| Signature | | |
| Date | | |

---

*Mizzey.com Feature Register, MS-ANX-2026-001, version 1.0, 30 August 2026*
*Questions on any item are welcome and encouraged before signature rather than after.*

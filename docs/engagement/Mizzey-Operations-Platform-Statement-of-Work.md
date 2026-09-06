# Mizzey Operations Platform
## Statement of Work and Commercial Terms

**Document:** MS-SOW-2026-009
**Version:** 1.0
**Date:** 5 September 2026
**Prepared for:** Mizzey.com
**Prepared by:** Mustafa Shaaban
**Contractual scope:** Feature Register & Scope Definition, MS-ANX-2026-001 (Contract Annex A)

---

## 1. Purpose of this document

This document sets out the commercial terms for building the **Mizzey Operations Platform**: what is being built, what it costs, when each payment falls due, what is included in the fee, and what is not.

It is the commercial half of the engagement. The other half is the scope, and the scope is defined by two documents that already exist:

| Document | Id | What it is |
|---|---|---|
| Feature Register & Scope Definition | MS-ANX-2026-001 | The definitive, requirement by requirement scope. Contract Annex A |
| What We Are Building | MS-DOC-2026-002 | The same scope in plain language |

**Where this document and the Feature Register appear to disagree about scope, the Feature Register governs.** Where they appear to disagree about money or dates, this document governs.

> **Commercial and legal review**
>
> This document contains commercial terms. It is prepared with care and matches the proposal issued to you, but it is not legal or tax advice and has not been reviewed by a lawyer. Both parties are free to take their own advice before signing.

---

## 2. The engagement

Mizzey.com is an independent e-commerce store, built so that Mizzey sells directly to its own customers rather than only through a marketplace.

The Operations Platform is the most complete of the three packages proposed. It is the full store, plus the operational system the Mizzey team uses to run the business once orders arrive faster than they can be handled by hand.

### 2.1 What is being built

**The store.** A custom storefront designed for Mizzey, in Arabic and English with right to left support, with products, categories, search and filtering, a curated Arabic synonym and correction list, cart, checkout, guest checkout, customer accounts, wishlist, reviews, Paymob, cash on delivery and Bosta shipping.

**The operational system.** One screen for daily operations showing orders needing action, stock problems, the returns queue and the revenue of the day. A returns and refunds workflow running from the request by the customer through review, approval and refund. Amazon migration tooling that can be re-run safely. Bulk actions on orders and products. Staff roles and permissions. An activity log of every sensitive change, and who made it. Advanced discount rules with conditions, limits, scheduling and priority. Business reports built for how Mizzey actually operates.

**The written product.** Written requirements and user stories, technical design, data model, acceptance criteria, testing, an Arabic administrator manual, deployment and recovery procedures, and a full technical handover.

Every one of these is defined precisely, and its limits recorded, in the Feature Register.

### 2.2 Delivery model

The work is delivered by Mustafa Shaaban as an individual professional, working directly with Mizzey. There is no agency layer, and the business analysis and project management that an agency quotes as separate roles are carried within the fee.

---

## 3. The fee

| Item | Amount |
|---|---:|
| Mizzey Operations Platform, professional fee | 290,000 EGP |
| Interface design, user interface and user experience, per section 9 | 20,000 EGP |
| **Total professional fee** | **310,000 EGP** |

**310,000 EGP is the total professional fee.** No value added tax and no other tax is added to it, and no tax is deducted from it. If either party is later informed by a competent authority that a tax applies to this engagement, the parties will agree in writing how it is handled before any further payment is made.

The fee is quoted and payable in Egyptian pounds.

This is a special project rate for the Mizzey engagement and the scope currently defined. It is not a published rate card.

---

## 4. Delivery stages and payments

The work is delivered in six stages. **The largest single payment is 20 percent**, and every stage is tied to something that can be opened, used and checked rather than to a date alone.

| Stage | What is complete | Week | % | Amount |
|---|---|---:|---:|---:|
| 1. Start and specification | Full written specification, work list and acceptance model agreed | 2 | 20 | 58,000 EGP |
| 2. Foundation and products | Platform, product model, catalogue and core buying built | 5 | 20 | 58,000 EGP |
| 3. Shop front complete | Full storefront from the approved design, accounts, cart and checkout | 8 | 20 | 58,000 EGP |
| 4. Operations system | Staff screen, roles, activity log, returns workflow, Paymob and Bosta | 11 | 15 | 43,500 EGP |
| 5. Migration and reports | Amazon migration tooling, business reports, product feeds, admin refinements | 13 | 15 | 43,500 EGP |
| 6. Live and handed over | Acceptance testing passed, live, full documentation and technical handover | 14 | 10 | 29,000 EGP |
| **Platform subtotal** | | | **100** | **290,000 EGP** |

Interface design sits outside the stage schedule, because it is finished before the storefront it is used to build. It is paid once, on your approval of the design.

| Item | When | Amount |
|---|---|---:|
| Interface design, user interface and user experience | On your written approval of the design, expected week 4 | 20,000 EGP |
| **Total, platform and interface design** | | **310,000 EGP** |

### 4.1 How a stage is completed

A stage is complete when its deliverable is available on the staging environment or, for written deliverables, issued in writing, and it meets the acceptance criteria for that stage set out in the Acceptance and UAT Plan, MS-UAT-2026-013.

Each stage is presented for review. **Feedback is due within 5 working days.** If no written response is received within that period, the stage is treated as accepted and its payment falls due. This rule exists to keep the project moving, not to shorten review: if more time is needed, ask for it and it will be given.

### 4.2 Stage 1 is payable at the start

Stage 1 covers the specification work that begins the project. Its payment is invoiced on signature and is what starts the engagement.

---

## 5. Timeline

| Item | Value |
|---|---|
| **Estimated duration to launch** | **14 to 16 weeks** |

The stage weeks in section 4 are the plan. The 14 to 16 week band is the honest range around it.

**The clock starts when the inputs listed in What You Need to Provide, MS-DEP-2026-012, are available**, not on the date of signature. The two that matter most are an approved brand identity, which is required before implementation begins, and an approved payment merchant account, without which the store cannot take payment.

Delay in client inputs, approvals or third-party account approvals extends the timeline by the period of the delay. The Project Plan and Delivery Schedule, MS-PLN-2026-011, sets out precisely what blocks what.

---

## 6. What is included in the fee

* Requirements analysis, written specification, user stories and acceptance criteria
* Technical design, data model, architecture and analytics event plan
* **Interface design for the storefront and the operations screens**, per section 9
* Development of everything classified **P1**, **P1-L** or **DLV** in the Feature Register
* The operational tooling layer described in section 2.1
* Amazon catalogue migration tooling, built to be re-run safely
* Paymob and Bosta integration, and cash on delivery if selected
* Arabic and English implementation, including right to left layout
* Marketing measurement setup: analytics, advertising tracking and product feeds within the agreed scope
* Testing, a staging environment, and support of your acceptance testing
* Production deployment, launch and a rollback procedure
* Business analysis and project management, carried within the fee
* Full documentation set and technical handover
* Training and credentials handover
* Delivery of all Mizzey-specific source code and project assets to a repository owned by Mizzey
* Second release items listed in Feature Register Part One section 1.1, delivered after go-live and already paid for within this fee
* **60 calendar days of warranty** from Production Go-Live, per the Support and Maintenance Terms, MS-SUP-2026-015

---

## 7. What is not included

Nothing in this section is a surprise. Each item is either recorded in the Feature Register as **P2**, **P3** or **OUT**, or is a third-party cost rather than professional work.

### 7.1 Not included as professional work

* **Brand identity creation.** An approved brand identity is required before implementation begins. This is a different thing from the interface design in section 9, and it is not included. See section 9.1
* Product photography
* Product copywriting, in either language
* Translation of product or content text
* Drafting of legal policy text: terms, privacy, shipping and returns wording
* Advertising, media buying and search engine optimisation campaign execution, as distinct from the technical setup that is included
* Any requirement classified **P2**, **P3** or **OUT** in the Feature Register
* Ongoing support after the warranty period, which is optional and priced separately

### 7.2 Not included as cost

The items in section 8, which are paid by Mizzey directly to the supplier, in the name of Mizzey.

---

## 8. Costs you pay in your own name

These are paid by Mizzey directly to each supplier, in the name of Mizzey, so that Mizzey owns every account. They are close estimates rather than quotations. **Each is confirmed at its actual price before anything is bought, and nothing is added to your accounts without your approval first.**

| Item | Rhythm | Estimate |
|---|---|---:|
| Hosting. A larger server, because the operational tooling, the migration jobs and the reports run alongside the shop | Monthly | $28 to $88 |
| Backups | Monthly | $1 to $3 |
| Arabic and English licence | Yearly | about $107 |
| Optional plugins. Fewer than the Launch Platform, because the custom system replaces most of them, including search | Yearly | $0 to $215 |
| Domain | Yearly | Variable |
| **Everything above, as a monthly average** | **Equivalent** | **$39 to $117, about 2,000 to 6,000 EGP** |

Taken from your sales rather than paid as a subscription:

| Item | Basis | Rate |
|---|---|---:|
| Commerce platform fee | Per order | None |
| Paymob processing | Per order | 2.75% + 3 EGP |
| Courier charges | Per parcel | Carrier rate card |

There is no platform fee and no revenue share on this option. WooCommerce is open source and charges neither.

---

## 9. Interface design

**An approved interface design is required for the Operations Platform**, and Mizzey has confirmed that the Developer provides it. The operations screens are designed as well as the shop front, which is why this is priced above the Launch Platform equivalent.

| Item | Fee |
|---|---:|
| Interface design for the Operations Platform, produced by the Developer | 20,000 EGP |

### What this covers

* The full screen set for the storefront, in both English and Arabic, including right to left layouts
* The operations screens your staff use
* A design system: tokens, components, states and the rules for using them
* Mobile and desktop states for every screen
* Handoff files the implementation is built from

The requirements the design must meet are set out in the Design Brief and Request for Proposal, MS-RFP-2026-003. That document was written to brief a third-party designer, and it now serves as the specification the Developer designs against, so the standard does not drop because the work is being done in-house.

### 9.1 What this is not

**This is interface design, not brand identity.** Brand identity is the logo, the colour system, the typography and the rules for using them. The interface is designed from it.

An approved brand identity is still required before implementation begins, and it is **not** included in the 20,000 EGP. If Mizzey does not have one, say so before signature and it will be quoted separately rather than assumed either way.

---

## 10. Invoicing and payment

* An invoice is issued at the completion of each stage, and for Stage 1 on signature
* Invoices are payable within **7 working days** of issue
* Payment is in Egyptian pounds, by bank transfer or an agreed local transfer method
* Bank or transfer charges are borne by the party whose bank levies them
* If an invoice remains unpaid for more than 14 working days, work may be suspended after written notice, and the timeline extends by the period of suspension

---

## 11. Assumptions

The fee and timeline in this document assume the following. If one of these turns out to be materially different, the effect is handled through the Change Control Procedure, MS-CHG-2026-014, rather than absorbed silently.

* The scope is as recorded in the Feature Register MS-ANX-2026-001 at the date of signature
* An approved brand identity is available before implementation begins
* The interface design produced under section 9 is approved by Mizzey before storefront implementation begins
* Paymob is approved as the payment provider and Bosta as the carrier, or an equivalent decision is made early enough not to delay integration work
* The payment merchant account is approved and live before checkout work completes
* Product content is supplied in both English and Arabic by Mizzey
* Mizzey confirms in writing that it holds the rights to the product images and descriptions being migrated
* Reviews and approvals are returned within 5 working days
* Hosting is provisioned on a specification suitable for the scope, at the instruction of Mizzey

---

## 12. Validity and acceptance

This Statement of Work is valid for **30 days** from its date. After that, the fee and timeline are reconfirmed before signature.

On signature this document becomes Annex B to the Services Agreement, MS-AGR-2026-010, and is read together with it.

---

## 13. Signature

By signing, both parties accept the commercial terms above, and confirm that the Feature Register MS-ANX-2026-001 is the definitive definition of scope.

| | Client, Mizzey | Developer |
|---|---|---|
| Name | | Mustafa Mohamed Shaaban |
| Title | | Software Engineering Lead |
| Signature | | |
| Date | | |

---

*Questions on anything here are welcome, and much better raised now than later.*

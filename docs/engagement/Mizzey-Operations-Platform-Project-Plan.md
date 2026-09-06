# Mizzey Operations Platform
## Project Plan and Delivery Schedule

**Document:** MS-PLN-2026-011
**Version:** 1.0
**Date:** 5 September 2026
**Prepared for:** Mizzey.com
**Prepared by:** Mustafa Shaaban
**Companion documents:** Statement of Work MS-SOW-2026-009, What You Need to Provide MS-DEP-2026-012

---

## 1. How to read this plan

This document turns the six delivery stages into a week by week plan, and says openly what has to be true for each week to happen on time.

Three things are worth understanding before reading the schedule.

**The week numbers are the plan. The 14 to 16 week band is the range.** A project of this size that finishes exactly on its planned week has usually had nothing go wrong. The two week band is the honest allowance for the things that do.

**Week 1 is not the week of signature.** The clock starts when the inputs in MS-DEP-2026-012 are available. The most common reason a project of this kind runs late is not development speed, it is waiting.

**Launch is not the end.** A set of contracted items is delivered in a second release shortly after go-live. They are already paid for within the fee. Section 7 lists them.

---

## 2. The six stages at a glance

| Stage | Weeks | What is delivered | Payment |
|---|---|---|---:|
| 1. Start and specification | 1 to 2 | Full written specification, work list and acceptance model | 58,000 EGP |
| 2. Foundation and products | 3 to 5 | Platform, product model, catalogue and core buying | 58,000 EGP |
| 3. Shop front complete | 6 to 8 | Full storefront, accounts, cart and checkout | 58,000 EGP |
| 4. Operations system | 9 to 11 | Staff screen, roles, activity log, returns, Paymob, Bosta | 43,500 EGP |
| 5. Migration and reports | 12 to 13 | Amazon migration tooling, reports, feeds, admin refinements | 43,500 EGP |
| 6. Live and handed over | 14 | UAT passed, live, documentation and technical handover | 29,000 EGP |

The two week band in the estimate sits across stages 3 to 6, where the volume of work and the number of external dependencies are highest.

---

## 3. Stage by stage

### 3.1 Stage 1. Start and specification. Weeks 1 to 2

**Work performed.** Scope baseline confirmed against the Feature Register. Technical specification written. Data model and architecture designed. Backlog and work list built. Acceptance model agreed. Environments planned. Analytics event plan drafted.

**Delivered.** Written technical specification, data model, work list, and the acceptance model that governs every later stage.

**Needed from you before it starts.** Signature. Confirmation of the business decisions listed in MS-DEP-2026-012 section 5, or a date by which each will be made. Access to your requirements documents and any existing assets.

**Gate at the end.** You review and approve the specification and the acceptance model. Approval of this stage is what makes every later acceptance objective rather than a matter of opinion.

**Payment released.** 58,000 EGP.

### 3.2 Stage 2. Foundation and products. Weeks 3 to 5

**Work performed.** Platform foundation, product model, categories, brands and collections, catalogue structure, stock model, core buying path, base admin. The interface design is produced in parallel during this stage and presented for your approval in week 4.

**Delivered.** A working foundation on staging, with the product model exercised against your real catalogue structure.

**Needed from you before it starts.** Approved brand identity. Your Amazon sample export file. Written instruction on hosting location, so servers can be prepared.

**Gate at the end.** You review the product model and catalogue structure on staging. This is the last cheap moment to change how products are structured.

**Payment released.** 58,000 EGP.

### 3.3 Stage 3. Shop front complete. Weeks 6 to 8

**Work performed.** Full storefront built from the approved interface design. Home page, listing and category pages, product pages, search and filtering with the curated Arabic synonym and correction list, cart, checkout, guest checkout, customer accounts, wishlist and reviews. Arabic and English throughout, with right to left layout.

**Delivered.** The complete customer-facing store on staging, in both languages.

**Needed from you before it starts.** **Your written approval of the interface design.** The design itself is produced by the developer, so it is not something you have to source, but storefront work cannot begin until you have approved it. Your storefront content in both languages, and your product photography, are needed during this stage rather than at the end of it.

**Gate at the end.** You walk the full customer journey on staging, in both languages, on a phone and on a desktop.

**Payment released.** 58,000 EGP.

### 3.4 Stage 4. Operations system. Weeks 9 to 11

**Work performed.** The daily operations screen. Staff roles and permissions. Activity log of sensitive changes. Returns and refunds workflow from customer request through review, approval and refund. Bulk actions on orders and products. Advanced discount rules. Paymob and Bosta integration. Cash on delivery if selected. Shipping rate zones by governorate.

**Delivered.** The operational system on staging, with payment and shipping integrated in test mode.

**Needed from you before it starts.** **An approved payment merchant account**, with commercial registration and tax card completed with the provider. Carrier account, API key and your rate card by governorate. Your decisions on cash on delivery, launch payment methods and the return window.

**Gate at the end.** Your team, not the developer, uses the operations screen to process test orders and a test return end to end.

**Payment released.** 43,500 EGP.

### 3.5 Stage 5. Migration and reports. Weeks 12 to 13

**Work performed.** Amazon migration tooling with column mapping, saved settings, safe repeat imports, product variants, Arabic text handling and error reports. Business reports. Product feeds. Analytics and advertising tracking. Admin refinements from your feedback in stage 4.

**Delivered.** Your catalogue loaded through the migration tool, reports running on real data, tracking verified.

**Needed from you before it starts.** Your full catalogue export. Written confirmation that you hold the rights to the product images and descriptions. Your legal policy text: shipping, returns, privacy and terms. Your decision on which advertising platforms are live at launch.

**Gate at the end.** You re-run the migration tool yourself on a changed file and confirm it updates rather than duplicates.

**Payment released.** 43,500 EGP.

### 3.6 Stage 6. Live and handed over. Week 14

**Work performed.** Full user acceptance testing against the script in MS-UAT-2026-013. Defect correction. Production deployment. Launch verification. Documentation completion. Training session. Credentials handover. Rollback procedure documented and tested.

**Delivered.** The store live, and the complete handover pack.

**Needed from you before it starts.** Your availability to run acceptance testing. Nothing can substitute for this: acceptance testing is performed by you, with support from the developer, not instead of you.

**Gate at the end.** Acceptance sign-off, then Production Go-Live. The 60 day warranty period starts here.

**Payment released.** 29,000 EGP.

---

## 4. What has to be ready, and by when

This is the dependency view of MS-DEP-2026-012. Each row is something the project waits for if it is not there.

| What | Needed by | What stops without it |
|---|---|---|
| **Payment merchant account approved** | **Start immediately, before anything else** | The store cannot take payment. Approval takes longer than any other item on this list |
| Approved brand identity | Before stage 2 | Implementation cannot begin |
| Hosting location instruction | Before stage 2 | Servers cannot be prepared |
| Amazon sample export file | Before stage 2 | The migration tool cannot be designed |
| **Your approval of the interface design** | **Before stage 3** | The storefront cannot be built. The design is produced by the developer and presented to you in week 4 |
| Storefront content in both languages | During stage 3 | The store cannot launch multilingual |
| Product photography | During stage 3 | Products cannot be loaded |
| Carrier account, API key and rate card | Before stage 4 | Checkout cannot be completed |
| Business decisions on cash on delivery, payment methods, return window | Before stage 4 | Checkout and returns cannot be finished |
| Full catalogue export | Before stage 5 | Products cannot be loaded |
| Written confirmation of image rights | Before stage 5 | Images will not be migrated |
| Legal policy text | Before stage 6 | Launch is blocked |
| Your availability for acceptance testing | Stage 6 | Launch is blocked |
| Feedback on anything submitted | Within 5 working days, every stage | Each late response moves everything behind it |

---

## 5. What stops the clock

The schedule assumes work is never waiting on a decision.

**Reviews are due within 5 working days.** If no written response is received within that period, the item is treated as approved and the project continues. If you need longer, say so before the period ends and you will be given it.

**A late input moves the timeline by the length of the delay, not by less.** Development work cannot always be resequenced around a missing dependency. If the interface design arrives two weeks late, the project finishes at least two weeks later.

**Third-party approvals are outside the control of both parties.** Payment merchant approval in particular depends on the provider and on your commercial documents. It is first on the list for exactly that reason.

**A change to scope moves the timeline by the amount stated in its Change Request**, agreed in writing before the work starts, per MS-CHG-2026-014.

---

## 6. How the project is run

**Weekly.** A short written progress note: what was completed, what is next, what is waiting on you. Nothing waits a week to be raised if it is urgent.

**Every stage.** A working demonstration on staging, not a document describing one. You see the real thing and use it yourself.

**Staging environment.** Available to you throughout, so you can look at any time rather than only at gates.

**Issues.** Raised in writing, tracked, and resolved with a recorded outcome. Nothing important is settled only in conversation.

---

## 7. The second release

These items are contracted, included in the fee, and delivered shortly after Production Go-Live rather than on launch day. The table shows what is live at launch in their place.

| Id | Item | At launch instead |
|---|---|---|
| JRN-11 / ACCT-09 | Reorder from order history | Past orders viewable |
| IA-23 / ACCT-11 | Notification preferences page | Unsubscribe links in emails |
| ACCT-13 | Account deletion from the account page | Handled manually on request |
| PLP-11 | Filter by rating | All other filters live |
| RPT-03 | Product performance report | Basic sales data available |
| RPT-07 | Search terms report | Search works, reporting follows |
| ADM-05 | Dashboard: new and returning customers | Revenue, orders and units live |
| ADM-07 | Dashboard: conversion rate | Available in Google Analytics |
| ADM-28 | Scheduled price changes | Prices changed manually or in bulk |
| ADM-71 | Reserved stock tracking | On-hand and available stock live |
| ADM-101 | Customer lifetime metrics | Profiles and order history live |
| ADM-105 | Customer data export under privacy controls | Handled manually on request |
| NOTF-14 | Admin-editable notification templates | Wording changed for you on request |
| ENT-04 | Inventory transaction records | Stock levels and adjustments live |
| NFR-12a | Automated data export and deletion handling | Handled manually on request |

The second release is scheduled with you after launch, once the store has been running long enough for its priorities to be obvious.

---

## 8. Risks, and what is done about them

| Risk | Effect | What reduces it |
|---|---|---|
| Payment merchant approval is slow | Checkout cannot be completed, launch slips | Started first, before any development work |
| Interface design approval is slow | Stage 3 cannot start | The design is produced by the developer and presented in week 4, which removes the risk of sourcing it. What remains is your review time |
| Bilingual content is not ready | Multilingual launch blocked | Content requested from stage 3, not from stage 6. This is the most common cause of delay in multilingual launches |
| Amazon export quality is poorer than expected | Migration takes longer | Sample file requested in stage 2, months before the full import |
| Image rights cannot be confirmed | Images cannot be migrated | Raised in writing early, so an alternative can be planned rather than discovered at launch |
| Scope grows during the build | Timeline and fee move | Change control, with a written impact assessment before any work starts |
| Reviews take longer than 5 working days | Everything behind them moves | Named reviewer on your side, agreed at kickoff |

---

## 9. Before anything is built

Before development begins you receive the technical design, the full site structure and user flows, the interface designs for approval, and a live demonstration of the admin environment.

Nothing gets built until you have seen and approved how it will look and work.

---

*This plan is a working document. It is updated as stages complete, and any change to the dates in it is recorded in writing.*

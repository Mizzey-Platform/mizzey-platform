# Mizzey Operations Platform
## Functional Specification: User Stories and Acceptance Criteria

**Document:** MS-SPC-2026-018
**Version:** 1.0
**Date:** 6 September 2026
**Prepared for:** Mizzey.com
**Prepared by:** Mustafa Shaaban
**Contract role:** Stage 1 deliverable under the Statement of Work, MS-SOW-2026-009
**Companion documents:** Feature Register MS-ANX-2026-001 (Annex A), Acceptance and UAT Plan MS-UAT-2026-013 (Annex C)

---

# PART ONE: HOW THIS DOCUMENT WORKS

## 1. What this document is

This is the written specification promised as the Stage 1 deliverable in the Statement of Work, section 4: requirements analysis, written specification, user stories and acceptance criteria. It is the document the Project Plan MS-PLN-2026-011 puts behind the Stage 1 gate, and approving it is what makes every later acceptance objective rather than a matter of opinion.

The Feature Register, MS-ANX-2026-001, says **what** is being delivered and at what contractual scope. It is a register: one line per requirement, permanent reference numbers, a scope classification against each. That is exactly what a contract needs, and it is deliberately not written as a build instruction.

This document says **how each of those requirements behaves when it is finished**. It takes the contracted rows of Annex A and turns them into user stories, each with acceptance criteria written as testable statements, grouped into the journeys a real person actually performs.

| This document provides | Where it comes from |
|---|---|
| Personas and role definitions | Annex A Section B |
| End to end user journeys, customer side and staff side | Annex A Section A5, Section G10, Section G11 |
| Epics and user stories | Every P1, P1-L and DLV row in Annex A |
| Acceptance criteria per story, in given, when, then form | Written here, governed by the definition of done in Annex A Section M |
| The cross cutting acceptance scenarios | Annex A Section N, reproduced and expanded into story level criteria |
| A traceability matrix | Every contracted requirement id mapped to the story that delivers it |
| The open decisions that block specific stories | Annex A Part Six |

## 2. What this document is not

**It is not a change to scope.** Not one requirement is added, removed, widened or narrowed here. Where this document and Annex A appear to differ, **Annex A governs**, and the difference is a defect in this document to be corrected, not a change to what is being built.

**It is not a technical design.** The data model, architecture, infrastructure and analytics event plan are separate Stage 1 deliverables. This document stays on behaviour a person can observe.

**It is not a replacement for the acceptance plan.** Annex C sets out how work is accepted, the review periods, defect severity and the go live checklist. This document supplies the criteria that plan tests against.

**It is not a design specification.** Screen layout, component behaviour and visual treatment are settled by the Interface Design produced under Statement of Work section 9 and approved separately. Where a story says a customer can see something, it does not dictate where on the page it sits.

## 3. How to read a user story

Every story has the same anatomy.

| Element | Meaning |
|---|---|
| **Story id** | US-nn-nn. The first pair is the epic, the second is the story within it. Permanent: it is used in the backlog, in commit messages, in test cases and in defect reports for the life of the project |
| **Title** | What the story delivers, in the fewest words that are still unambiguous |
| **Narrative** | As a role, I want a capability, so that an outcome follows. The outcome is not decoration: a story whose outcome cannot be stated is usually not a story |
| **Traceability** | Every Annex A requirement id the story delivers, plus any Annex A acceptance scenario it satisfies |
| **Scope** | The contractual classification carried over from Annex A, unchanged |
| **Stage** | S1 for the first release, S2 for the second release inside the Phase 1 contract |
| **Acceptance criteria** | Numbered, each written so that it either passes or fails. No criterion is satisfied by opinion |

A story inherits the **scope classification of the strictest requirement it contains**. A story delivering both a P1 and a P1-L requirement is tested against the P1-L boundary for the reduced part, exactly as Annex C section 2.2 requires.

## 4. How acceptance criteria are written

Criteria are written in **given, when, then** form wherever a state precedes an action, and as a plain statement of required behaviour where no prior state is involved.

Three rules were applied throughout, and they are the reason some criteria look pedantic.

**The unhappy path is specified, not implied.** Annex A DOD-02 requires business logic and exception cases, not only the successful path. A story that only describes success is incomplete, so every story that can fail says what failure looks like.

**Both languages are a criterion, not a caveat.** Annex A FIX-04 and NFR-04 place a fully supported Arabic storefront in the first release, and DOD-03 requires validation and error messages correct in both languages. Rather than repeat that on two hundred stories, it is stated once as a global criterion in section 6 and repeated explicitly only where behaviour differs by language.

**Permissions are a criterion.** Annex A DOD-06 requires staff permissions tested. Every admin story states which roles may perform the action and what happens to a role that may not.

## 5. Priority within the contract

Scope classification is contractual and comes from Annex A. Priority is a build ordering aid and is set here.

| Priority | Meaning |
|---|---|
| **Must** | The store cannot go live without it. Every P1 row on the critical purchase path |
| **Should** | Live at launch, but the store would function commercially for a short period without it |
| **Later** | Contracted, delivered in the second release. All S2 rows |

Priority never overrides scope. A Should story that is P1 is still contractually owed at launch.

## 6. Criteria that apply to every story

These are not repeated on each story. They apply to all of them, and a story is not done until they hold.

| Id | Global criterion | Source |
|---|---|---|
| GC-01 | The feature works in English, the primary and default language, and in Arabic with a correct right to left layout and no layout break | FIX-04, NFR-04, AC-12 |
| GC-02 | No text a customer can read is hard coded. Every string is translatable | NFR-04a |
| GC-03 | Currency, number and date formatting follow the active locale | NFR-04b |
| GC-04 | The feature is usable on a phone, a tablet and a desktop, and was tested on mobile first | FIX-05, NFR-01, PLT-06 |
| GC-05 | Validation and error messages are clear, specific and correct in both languages | DOD-03 |
| GC-06 | Exception cases are implemented, not only the successful path | DOD-02 |
| GC-07 | Where the story is an admin action, the role permitted to perform it is enforced, and a role without permission cannot reach the action | DOD-06, ROLE-09 |
| GC-08 | Where the story changes money, price, stock, permissions or a refund, the action is written to the audit log with actor, action, entity, before and after values, and timestamp | ROLE-10, ADM-132, ENT-17 |
| GC-09 | Any analytics event listed against the story fires with the correct parameters | DOD-05, EVT-20 |
| GC-10 | No known error affecting usage remains, and the settings the feature needs are documented | DOD-07, DOD-08 |

> **On GC-01.** English is the canonical design and the default language a visitor lands on. Arabic is the second supported language and is delivered in full at launch, with equivalent core functionality. Neither position is new: FIX-04 is a fixed decision from the client requirements document, and FIX-04a is the client clarification recorded in Annex A. Arabic is never a reduced version, and a story that works in English and breaks in Arabic is not done.

## 7. What approval of this document means

Approving this document means three things, and it is worth being precise about each.

1. **You agree these criteria are what you will test against.** At user acceptance testing, a feature that satisfies its criteria here is accepted. That is the protection this document gives you, and it is also the protection it gives us.
2. **You agree the journeys describe how the store should work.** If a step is missing from a journey, now is when it costs nothing to add.
3. **You have not changed the contract.** Scope remains what Annex A says. A capability you want that is not in Annex A does not become contracted because it appears in a story here, and no such capability has been introduced.

Approval carries the 5 working day review period set by Annex C section 2.1.

---

# PART TWO: PERSONAS AND ROLES

## 8. Why personas, and how they are used

A user story addressed to the user is a story nobody has thought about carefully. Every story in Part Four names one of the actors below, and the actor determines what the story may assume: what the person already knows, what they already have on screen, and what they are trying to finish.

The customer personas are drawn from the target audience recorded in Annex A VIS-04 to VIS-06. The staff roles are the permission roles defined in Annex A Section B, and they are not personas at all: they are contractual permission boundaries.

## 9. Customer personas

| Persona | Who they are | What they need from the store | Primary journeys |
|---|---|---|---|
| **PER-01. First time visitor** | Arrived from a Meta or TikTok advertisement, or from search. Class A or upper middle, willing to pay for quality. Has never heard of Mizzey and is deciding in under a minute whether it is legitimate | Immediate signals of authenticity and quality. A product page that answers the question the advertisement raised. A way to buy without committing to an account | J1, J2 |
| **PER-02. Cautious buyer** | Wants the product, does not want to create an account, and has been disappointed by an online purchase before | Guest checkout that genuinely works. A visible return policy. A delivery estimate before payment, not after | J1, J3 |
| **PER-03. Returning customer** | Has bought before, has an account, knows what they want | A cart that is still there. Saved addresses. Order history and tracking without contacting anybody | J2, J4, J6 |
| **PER-04. Arabic first shopper** | Reads and buys in Arabic by preference, on a phone | A complete Arabic store, not a translated shell. Correct right to left layout. Arabic search that finds what they typed, including Franco Arabic spellings | All journeys, in Arabic |

> **PER-04 is not a minority case in this market and is not treated as one.** Every journey in Part Three is walked in both languages at acceptance, per AC-12. The register does not treat Arabic as a variant of the store, and neither does this document.

## 10. Staff roles

These are the roles from Annex A Section B, reproduced with the operational shape each one has in this specification. The permission boundaries are contractual and are not restated loosely here: Annex A ROLE-01 to ROLE-10 governs.

| Role | Id | What this role does daily | Cannot |
|---|---|---|---|
| **Visitor** | ROLE-01 | Browse, search, filter, view products, add to cart, hold a temporary wishlist, register, check out as a guest | Reach anything requiring an account |
| **Customer** | ROLE-02 | Everything a visitor can do, plus account, addresses, orders, tracking, saved wishlist, reviews, coupons, returns | See any other customer data |
| **Customer Service** | ROLE-03 | View customers and orders, add notes, update service statuses | Refund, edit prices, change roles |
| **Warehouse** | ROLE-04 | View orders to prepare, pick, pack, ship, update tracking and stock | Refund, edit prices, read financial reports |
| **Marketing** | ROLE-05 | Banners, collections, coupons, home content, marketing reports | Install software, edit templates, edit files, reach customer personal data beyond campaign needs, read sensitive financial data |
| **Accountant** | ROLE-06 | Payment records and transaction references, refunds, order financial detail, invoices, product cost, the sales report | Reach advanced profitability and lifetime value reporting, which is P2 and arrives with those reports |
| **Admin** | ROLE-07 | Products, orders, customers, offers, integrations, users | Change roles and sensitive settings, clear the audit log |
| **Owner / Super Admin** | ROLE-08 | Everything, including roles, sensitive settings and audit logs | Delete the audit trail from the interface |

Two rules cut across all of them, and every admin story in Part Four is written against them.

| Id | Rule |
|---|---|
| ROLE-09 | Role based access is enforced. No staff member receives full admin by default |
| ROLE-10 | Sensitive operations are logged: refunds, price changes, stock changes, permission changes |

> **The Marketing role carries the Third Party Access Protocol.** Where an agency or media buyer is given access, they receive the Marketing role and nothing more, tracking is deployed through Google Tag Manager rather than by editing site code, and every action they take is in the audit log. The stories for that behaviour are in Epic E25, and the protocol itself is a deliverable, MKT-26.

---

# PART THREE: USER JOURNEYS

## 11. How the journeys are used

Annex A Section A5 records the customer journey as twelve numbered stages. That is the contractual statement of it. What follows expands those stages, and the operational equivalents on the staff side, into walkable sequences.

Each journey is a table of steps. Every step names the actor, what the person does, what the system must do in response, and the stories that deliver it. **The journeys are the acceptance walkthrough**: at the end of Stage 3 and again at user acceptance testing, these are the sequences that get walked, in both languages, on a phone and on a desktop.

Nine journeys are specified. Four are customer facing, five are operational.

| Journey | Name | Actor | Where it is exercised |
|---|---|---|---|
| **J1** | Discovery to delivered order, as a guest | PER-01, PER-02 | Stage 3 gate, UAT |
| **J2** | Discovery to delivered order, as a registered customer | PER-03, PER-04 | Stage 3 gate, UAT |
| **J3** | Identity and cart continuity | PER-01 to PER-04 | Stage 3 gate, UAT |
| **J4** | After sales self service | PER-03 | Stage 3 gate, UAT |
| **J5** | Order fulfilment | Warehouse, Admin | Stage 4 gate, UAT |
| **J6** | Return and refund, end to end | Customer, Customer Service, Accountant | Stage 4 gate, UAT |
| **J7** | The daily operations round | Admin, Warehouse | Stage 4 gate, UAT |
| **J8** | Catalogue migration from Amazon | Admin | Stage 5 gate, UAT |
| **J9** | Changing the storefront without a developer | Admin, Marketing | Stage 4 gate, UAT |

---

## 12. J1. Discovery to delivered order, as a guest

**Actor:** PER-01 first time visitor, or PER-02 cautious buyer. No account, arriving cold.

**Why this journey matters:** it is the journey with the highest volume and the lowest tolerance. The visitor has no relationship with the store and every friction point is a reason to leave.

| # | Actor does | System must | Stories | Annex A |
|---|---|---|---|---|
| 1 | Arrives at the home page from an advertisement | Show the hero, trending, new arrivals, collections, category shortcuts, brand highlights and trust signals, in the default language, with the language switcher visible | US-02-01 to US-02-04 | HOME-01 to HOME-09, JRN-01 |
| 2 | Searches, or opens a category | Return results with suggestions as they type, the full filter set, a result count, and a usable empty state | US-03-01 to US-03-09 | SRCH-01 to SRCH-05, PLP-01 to PLP-22, JRN-02 |
| 3 | Filters and sorts | Apply filters without losing the result set, show active filters, allow each to be removed individually | US-03-04, US-03-05 | PLP-06 to PLP-18 |
| 4 | Opens a product | Show gallery with zoom, video where present, price and any saving, variants with stock per variant, delivery estimate, returns summary, authenticity statement, reviews and related products | US-04-01 to US-04-10 | PDP-01 to PDP-24, JRN-03 |
| 5 | Adds to cart | Add the selected variant, update the header count, show the mini cart | US-05-01, US-05-02 | PDP-08, CART-12, NAV-06, JRN-04 |
| 6 | Opens the cart | Show line items, totals broken down, the free shipping progress prompt, cross sell suggestions, and re validate stock | US-05-03 to US-05-07 | CART-01 to CART-11, JRN-05 |
| 7 | Enters a coupon | Apply it, or explain in plain language why it does not apply, leaving the total unchanged | US-05-06 | CART-06, AC-04 |
| 8 | Proceeds to checkout as a guest | Allow checkout with no account, no forced registration, no dark pattern | US-06-06 | AUTH-07, FIX-09, BR-008, AC-13, JRN-06 |
| 9 | Enters an Egyptian address | Capture governorate, city, district, street and landmark, validate each field with a clear message | US-07-01 | CHK-01, CHK-11 |
| 10 | Chooses shipping and payment | Show the shipping cost for the zone, and the payment methods actually enabled | US-07-02, US-07-03 | CHK-03, CHK-04, SHIP-15 |
| 11 | Reviews and places the order | Show the full total breakdown, require terms acceptance, re validate stock at the moment of placement, and create exactly one order however many times the button is pressed | US-07-04 to US-07-07 | CHK-05, CHK-08, CHK-09, CHK-12, PAY-05, AC-05, AC-06 |
| 12 | Pays | Hand off to the provider hosted flow, store no card data, verify the callback signature, and never mark the order paid on a failed payment | US-08-01 to US-08-05 | PAY-02 to PAY-04, PAY-14, PAY-16, AC-07, AC-08 |
| 13 | Sees the confirmation | Show order number, summary, estimated delivery, and offer one step account creation requiring only a password | US-07-08, US-06-07 | JRN-08, AUTH-11, BR-008 |
| 14 | Receives the confirmation email | Send order confirmation with number and summary | US-12-02 | NOTF-02 |
| 15 | Receives shipping and delivery updates | Send carrier and tracking on dispatch, and a delivery confirmation with a review invitation | US-12-03, US-12-04 | NOTF-04, NOTF-06 |

> **Where this journey is allowed to end differently.** If stock runs out between step 6 and step 11, the order is not confirmed for the unavailable quantity and the customer is told before payment, not after (AC-05, BR-003). If payment fails, the order is left in Payment Failed and is never shown as Confirmed (AC-08, ORD-02). Both endings are acceptance criteria, not error handling to be decided during the build.

---

## 13. J2. Discovery to delivered order, as a registered customer

**Actor:** PER-03 returning customer, or PER-04 Arabic first shopper with an account.

Steps 1 to 7 are J1. This journey records where a registered customer diverges.

| # | Actor does | System must | Stories | Annex A |
|---|---|---|---|---|
| 1 | Signs in, or signs in with Google | Authenticate, and resolve a Google identity and a manual registration on the same email to one account, never two | US-06-02, US-06-03 | AUTH-03, AUTH-10, AUTH-13, AC-19 |
| 2 | Finds the cart from their last visit | Restore the saved cart across devices and sessions | US-05-09 | CART-09, CART-16 |
| 3 | Adds to the wishlist, or moves a wishlist item to the cart | Persist the wishlist to the account | US-11-04, US-11-05 | WISH-01, WISH-02, WISH-04 |
| 4 | Checks out | Offer saved addresses rather than re entry, and pre fill known details | US-07-01 | CHK-02, ACCT-04 |
| 5 | Applies the welcome discount, if newly registered | Apply it only where the configured conditions are met, and say why when they are not | US-14-02 | AUTH-06, BR-001, AC-03 |
| 6 | Places the order | Snapshot price and discount at the moment of purchase, so a later price change never alters this order | US-07-06 | BR-005, ENT-08, AC-09 |
| 7 | Tracks the order | Show status through Confirmed, Preparing, Shipped, Out for Delivery, Delivered, with carrier tracking | US-10-01, US-10-02 | ORD-03 to ORD-07, ACCT-07, JRN-09 |
| 8 | Downloads the invoice | Provide the invoice from the order record | US-10-03 | ACCT-12, ADM-92 |
| 9 | Leaves a review after delivery | Accept a rating and text, hold it for moderation, and publish only when approved | US-11-01, US-11-02 | REV-01 to REV-05 |

---

## 14. J3. Identity and cart continuity

**Actor:** every persona. This journey exists because it is where stores quietly lose completed carts, and because Annex A carries four added contract commitments about it.

**Why it is separated:** the failure it prevents is invisible. Nobody reports a lost cart as a defect; they simply do not come back. Annex A CART-13, CART-14, CART-15 and AUTH-13 are added commitments, not client requirements, and AC-18 and AC-19 test them.

| # | Situation | Required outcome | Stories | Annex A |
|---|---|---|---|---|
| 1 | Guest fills a cart, then registers | Every item carries into the new account. Nothing is lost | US-05-10 | CART-13, AC-18 |
| 2 | Guest fills a cart, then signs in to an account that already has a saved cart | The two carts merge. No item is lost and no duplicate line appears | US-05-11 | CART-14, CART-18, AC-18 |
| 3 | Guest fills a cart, then signs in with Google | Identical behaviour to step 2, through the Google flow | US-05-12 | CART-15, AC-18 |
| 4 | Guest closes the browser and returns within the defined window | The cart is still there | US-05-13 | CART-16 |
| 5 | Signed in customer adds to the cart on a phone, then opens a laptop | The cart is the same on both | US-05-09 | CART-09 |
| 6 | Merge produces a quantity above available stock | Quantities are combined up to the stock limit, prices are re validated, and the customer is told what changed | US-05-11 | CART-18, BR-003 |
| 7 | Guest with a temporary wishlist registers or signs in | The wishlist transfers to the account | US-11-06 | CART-17, WISH-03 |
| 8 | Customer registers manually with an email already used for Google sign in | One account, never two. The customer is signed in to the existing account | US-06-04 | AUTH-13, AC-19 |
| 9 | Guest completes an order, then creates an account from the confirmation page | The account is created by setting a password only, and the order is linked to it securely | US-06-07 | AUTH-08, AUTH-11, BR-008, AC-13 |

---

## 15. J4. After sales self service

**Actor:** PER-03 returning customer. The test of this journey is whether the customer ever needs to contact anyone.

| # | Actor does | System must | Stories | Annex A |
|---|---|---|---|---|
| 1 | Opens the account dashboard | Show an overview, profile, addresses, orders, wishlist and returns | US-10-01 | ACCT-01 to ACCT-08 |
| 2 | Opens an order | Show the full breakdown as charged, including discounts and shipping | US-10-02 | ACCT-06, ENT-08 |
| 3 | Tracks a shipment | Show the current carrier status against the order | US-10-02 | ACCT-07, SHIP-13, SHIP-14 |
| 4 | Downloads an invoice | Provide it as a document | US-10-03 | ACCT-12 |
| 5 | Requests a return | Accept item, quantity, reason, description and optional photographs, and check eligibility against the configured return window | US-13-01, US-13-02 | RET-01, RET-02, RET-03, ACCT-10 |
| 6 | Follows the return | Show the return status at every stage | US-13-06 | RET-10, AC-14 |
| 7 | Manages addresses | Add, edit, delete and set a default address | US-10-04 | ACCT-04 |
| 8 | Changes password or profile | Update them, with the change confirmed | US-10-05 | ACCT-02, ACCT-03 |
| 9 | Reorders a previous order | Rebuild the cart from a past order. **Second release** | US-10-06 | ACCT-09, JRN-11 |
| 10 | Sets notification preferences | Record channel preferences. **Second release**. At launch, unsubscribe links in emails | US-12-07 | ACCT-11, IA-23 |

---

## 16. J5. Order fulfilment

**Actor:** Warehouse and Admin. This is the journey the store runs every working day.

| # | Actor does | System must | Stories | Annex A |
|---|---|---|---|---|
| 1 | Opens the Operations Console | Show orders requiring action as a work queue, in an order that can be worked top to bottom | US-15-01, US-15-02 | ADM-140, ADM-141 |
| 2 | Opens a new order | Show items, prices, discounts, customer, address, payment, shipment, notes and a timeline | US-18-02 | ADM-86 |
| 3 | Picks and packs | Allow the status to move to Preparing, with the transition validated | US-18-03 | ORD-04, ADM-87 |
| 4 | Prints packing slips in bulk | Produce packing slips for a selection in one action | US-15-09 | ADM-156, ADM-93 |
| 5 | Dispatches to the carrier in bulk | Push the selected orders to the carrier and return tracking codes automatically | US-15-08 | SHIP-09, SHIP-10, ADM-155 |
| 6 | Prints airway bills in bulk | Produce all labels for the dispatched selection together | US-15-08 | SHIP-11 |
| 7 | Watches carrier status | Retrieve carrier status and map it to the order status through an explicit, documented mapping visible in admin | US-09-04, US-09-05 | SHIP-13, SHIP-14 |
| 8 | Handles a parcel returned to origin | Record it as Delivery Failed or Returned to Origin, never as Delivered, keeping stock and payment state correct | US-09-06 | ORD-13, SHIP-17, AC-16 |
| 9 | Handles cash on delivery | Record collected separately from remitted, so the dashboard never overstates cash | US-09-07 | ORD-14, SHIP-16 |
| 10 | Finds an order by customer phone | Return the order from a phone number search within seconds | US-18-01 | ADM-157, ADM-85 |
| 11 | Cancels an order | Record a reason and release the reserved stock | US-18-05 | ORD-08, ADM-90, BR-007 |

> **Step 8 is a named defect to design around, not an accident.** The published carrier integration has reported cases of a returned parcel showing as delivered. SHIP-14 and SHIP-17 exist so that an unpaid returned parcel is never recorded as a completed sale, and AC-16 tests it.

---

## 17. J6. Return and refund, end to end

**Actor:** Customer, then Customer Service, then whoever holds refund permission.

**Scope note:** the full workflow is live at launch. **Return shipping and collection remain a manual arrangement with the carrier** (RET-05, P2). The system records and tracks the return; arranging the pickup is done by the team directly.

| # | Actor | Step | System must | Stories | Annex A |
|---|---|---|---|---|---|
| 1 | Customer | Requests a return from the account | Capture order item, quantity, reason, description and optional photographs | US-13-01 | RET-01, RET-02 |
| 2 | System | Checks eligibility | Check automatically against the configured return window and refuse politely and clearly outside it | US-13-02 | RET-03 |
| 3 | Customer Service | Sees the case in the queue | Place new cases in a queue of return cases | US-20-01 | ADM-120 |
| 4 | Customer Service | Reviews it | Show the evidence, the reason and the order history together | US-20-02 | ADM-121 |
| 5 | Customer Service | Decides | Approve, reject or partially approve, with the reason recorded | US-20-03 | RET-04, ADM-122 |
| 6 | Customer | Sees the decision | Update the return status visibly, and send the return update email | US-13-06, US-12-05 | RET-10, NOTF-07 |
| 7 | Warehouse | Receives the item | Record receipt and the condition of the returned item | US-20-04 | RET-06, ADM-123 |
| 8 | Accountant or Admin | Refunds | Issue full or partial refund to the original payment method, with the payment reference recorded, never exceeding the amount actually paid after discount | US-08-06, US-20-05 | RET-08, PAY-07, PAY-08, ADM-124, BR-006, AC-10 |
| 9 | Customer | Is notified | Send the refund completed email with amount and reference | US-12-06 | NOTF-08 |
| 10 | Owner | Audits | Keep a full audit trail of the case and the refund | US-21-03 | ADM-125, ROLE-10 |

---

## 18. J7. The daily operations round

**Actor:** Admin, first thing in the morning. This journey is the acceptance test for OBJ-05, and Annex A states plainly that if the client must contact a developer to change a price, add a product or run an offer, the admin panel has failed regardless of the feature list.

| # | Actor does | System must | Stories | Annex A |
|---|---|---|---|---|
| 1 | Opens the Operations Console | Show the day in one view: orders needing action, stock needing attention, returns waiting, and today figures | US-15-01 to US-15-04 | ADM-140 to ADM-143 |
| 2 | Works the order queue | Present new, unpaid, ready to dispatch and failed delivery as a queue worked top to bottom | US-15-02 | ADM-141 |
| 3 | Works the stock queue | Show low, out of stock, missing an image and unpublished together | US-15-03 | ADM-142 |
| 4 | Adds a product | Allow a simple product, being title, price, stock, image and category, to be added in under three minutes | US-16-01 | ADM-146 |
| 5 | Changes prices in bulk | Change price by amount or percentage across a selection, and stock across a selection | US-16-06, US-16-07 | ADM-148, ADM-149 |
| 6 | Edits many products inline | Change price and stock across many products on one screen without opening each | US-16-05 | ADM-147 |
| 7 | Finds a product | Find any product within seconds by SKU, title or barcode | US-16-09 | ADM-152 |
| 8 | Saves a view | Save any filtered list and return to it in one click | US-15-05 | ADM-144 |
| 9 | Dispatches and prints | Send many orders to the carrier and print all labels together | US-15-08 | ADM-155, SHIP-10, SHIP-11 |
| 10 | Exports orders | Export orders to a spreadsheet with selectable columns | US-18-07 | ADM-158 |
| 11 | Checks the day figures | Show revenue, orders, units and pending dispatch for today | US-15-04 | ADM-143, ADM-01 to ADM-03 |

> **The commitment in Annex A Section G10 is exhaustive and deliberately so.** The operational capability is the list in that section. Anything not listed there is a Change Request, and Annex A EX-27 records that this is not a reproduction of any third party platform. If a task the team performs daily is missing, approval of this document is the moment to say so.

---

## 19. J8. Catalogue migration from Amazon

**Actor:** Admin, running the tool themselves, as often as needed.

**Why it is a journey and not a one off task:** the tool is run repeatedly, on changed files, by the client and not by the developer. Annex A MIG-07 makes safe re runs a key requirement and AC-17 tests it.

| # | Actor does | System must | Stories | Annex A |
|---|---|---|---|---|
| 1 | Exports from Amazon | Nothing. The export is performed by the client using Amazon reporting tools. The developer never holds Seller Central credentials | Not applicable | R-07, CR-06 |
| 2 | Uploads the file | Accept CSV, tab delimited and Excel, and detect the character encoding, verified against Arabic content | US-23-01, US-23-02 | MIG-01, MIG-02 |
| 3 | Maps the columns | Offer a column mapping interface matching file columns to store fields, and save the mapping for reuse | US-23-03, US-23-04 | MIG-04, MIG-05 |
| 4 | Previews | Show the first rows before anything is written | US-23-05 | MIG-03 |
| 5 | Runs a dry run | Produce a full validation report of what will be created, what will be updated, and what will fail and why, before anything changes | US-23-06 | MIG-06 |
| 6 | Imports | Create and update products in the background without failing on large files, handling variations as proper variable products | US-23-07, US-23-08 | MIG-09, MIG-20 |
| 7 | Re runs the same file | Update by SKU and create no duplicates | US-23-09 | MIG-07, AC-17 |
| 8 | Reads the error report | Provide a downloadable row level error report that can be corrected and re uploaded | US-23-10 | MIG-19 |
| 9 | Reviews history | Record who ran which file, when, with row counts and outcome | US-23-11 | MIG-18, ENT-19 |
| 10 | Exports the catalogue | Export in the same mapped format, so the catalogue stays portable | US-23-12 | MIG-25 |

> **What the client must supply before this journey can be finalised.** A real, unmodified Amazon sample export containing at least one simple product, one product with variations, one product with Arabic text and one product with several images (CR-06), confirmation of the Amazon account type and whether the Category Listings Report is activated (CR-07), and written confirmation of image and content rights (CR-08). Until CR-06 arrives, the import specification cannot be finalised. Amazon order history and customer data are excluded (EX-23, MIG-24).

---

## 20. J9. Changing the storefront without a developer

**Actor:** Admin or Marketing. This is the second half of OBJ-05 and the subject of AC-20.

| # | Actor does | System must | Stories | Annex A |
|---|---|---|---|---|
| 1 | Edits a home page section | Allow content, images, calls to action and section order to be changed | US-22-01, US-22-02 | SSC-02, ADM-59, ADM-61 |
| 2 | Hides a section | Show or hide any home page section without deleting its content | US-22-03 | SSC-03 |
| 3 | Edits a category or brand page | Change banner, description and hero imagery, per category and per brand | US-22-04 | SSC-04, SSC-05 |
| 4 | Reorders a collection | Change collection content and product ordering | US-22-05 | SSC-06, MER-03 |
| 5 | Edits the menu or footer | Change header navigation, mega menu structure, and footer content, links and columns | US-22-06 | SSC-07, SSC-08 |
| 6 | Edits a policy page or the FAQ | Change static and policy pages without code | US-22-07 | SSC-10, ADM-63, CMS-09 |
| 7 | Builds a campaign page | Create a campaign or offer landing page without a developer | US-22-08 | SSC-11, MKT-20 |
| 8 | Maintains both languages | Allow all of the above to be edited independently in English and Arabic | US-22-09 | SSC-12 |
| 9 | Previews | Show any change as it will appear before it goes live | US-22-10 | SSC-13 |
| 10 | Reverts | Return a content change to its previous version | US-22-11 | SSC-14 |
| 11 | Edits a product | Allow every field on a product to be edited individually, in both languages, with the change live on save | US-16-02, US-16-03 | SSC-20, SSC-21, SSC-29 |

> **Where a developer is still required, and this is the honest boundary.** Creating an entirely new type of page layout or storefront component that does not already exist in the design system is a Change Request. Changing the content, imagery, wording, ordering and visibility of everything already built is self service. AC-20 tests the second, not the first.

---

# PART FOUR: EPICS AND USER STORIES

## 21. The epic map

Twenty seven epics cover every contracted row in Annex A. The epic number is the first pair in every story id and never changes.

| Epic | Name | Delivery stage | Annex A sections |
|---|---|---|---|
| **E01** | Foundation, localisation and navigation | 2, 3 | A4, C1, C12, J |
| **E02** | Home page | 3 | C2 |
| **E03** | Browsing, filtering and search | 3 | C3, C4 |
| **E04** | Product detail | 3 | C5 |
| **E05** | Cart and continuity | 3 | C6 |
| **E06** | Identity and accounts | 3 | C7 |
| **E07** | Checkout and order placement | 3 | C8, E1 |
| **E08** | Payments and refunds | 4 | E2 |
| **E09** | Shipping and fulfilment | 4 | E3 |
| **E10** | My account and after sales | 3 | C9 |
| **E11** | Reviews, wishlist and recently viewed | 3 | C10, C11 |
| **E12** | Notifications | 3, 4 | F |
| **E13** | Returns and refunds, customer side | 4 | E4 |
| **E14** | Promotions and business rules | 4 | D1, D2, D3 |
| **E15** | The Operations Console | 4 | G10 |
| **E16** | Product and catalogue management | 2, 4 | G2, G3 |
| **E17** | Inventory | 2, 4 | G4 |
| **E18** | Order management | 4 | G5 |
| **E19** | Customers | 4 | G6 |
| **E20** | Returns and refunds administration | 4 | G8 |
| **E21** | Staff users, roles and audit | 4 | B, G9 |
| **E22** | Self service storefront control | 4 | G11, C12 |
| **E23** | Catalogue migration and import | 5 | U |
| **E24** | Dashboard and reports | 5 | G1, H |
| **E25** | Marketing enablement, SEO and third party access | 5 | V, I |
| **E26** | Analytics events | 5 | L |
| **E27** | Non functional requirements and deliverables | 1 to 6 | J, Part Seven |

---

# EPIC E01: FOUNDATION, LOCALISATION AND NAVIGATION

**What this epic delivers.** The multilingual framework, the header and navigation, and the trust and support pages. Everything in every other epic depends on it, which is why it is built in Stage 2 rather than alongside the storefront.

### US-01-01 [key] Bilingual storefront with full right to left support

*As an Arabic first shopper, I want the entire store in Arabic with a correct right to left layout, so that I can buy without switching to a language I read less comfortably.*

**Traceability:** FIX-04, NFR-04, AC-12. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | English is the default language a visitor lands on, and is the canonical design |
| 2 | Given the store is in Arabic, every core page renders right to left with correct alignment, Arabic typography and spacing, and mirrored directional patterns where direction carries meaning |
| 3 | Given the store is in Arabic, no page breaks layout at any tested breakpoint on phone, tablet or desktop |
| 4 | Every core storefront function available in English is available in Arabic, with equivalent behaviour |
| 5 | Icons whose meaning depends on direction, such as back, forward and progress arrows, are mirrored in Arabic |
| 6 | Where content exists in only one language, the page renders without error and falls back predictably rather than showing an empty region |

### US-01-02 Language switcher

*As any visitor, I want to change language from anywhere, so that I do not have to find my way back to the home page to do it.*

**Traceability:** NAV-02, FIX-04. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The switcher is present in the header on every page, including mobile |
| 2 | Given a visitor is on any page, when they switch language, then they arrive at the same page in the other language, not at the home page |
| 3 | The chosen language persists for the session and for a returning visitor on the same device |
| 4 | Given items are in the cart, when the language is switched, then the cart is unchanged |

### US-01-03 No hard coded storefront text

*As the store owner, I want every word a customer reads to be translatable, so that neither language depends on a developer to correct a word.*

**Traceability:** NFR-04a, SSC-01. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | No string a customer can read is embedded in code |
| 2 | Every interface string, including validation and error messages, buttons, empty states and confirmation text, exists in both languages |
| 3 | A missing translation is visible in a report rather than silently rendering the other language |

### US-01-04 Locale aware formatting

*As a shopper in either language, I want prices, numbers and dates to read naturally, so that the store does not look imported.*

**Traceability:** NFR-04b, OD-02. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Prices display in Egyptian Pounds throughout, with consistent decimal and thousands treatment |
| 2 | Dates and numbers follow the conventions of the active locale |
| 3 | The same order shows the same amount in both languages |

### US-01-05 Header and navigation

*As a visitor, I want the header to give me search, my account, my wishlist and my cart wherever I am, so that I never have to hunt for them.*

**Traceability:** NAV-01, NAV-03, NAV-04, NAV-05, NAV-06, NAV-07. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The header carries the logo, a prominent search bar, an account entry point, a wishlist entry point and a cart icon |
| 2 | The cart icon shows a live item count that updates when the cart changes, without a page reload |
| 3 | The mega menu and category navigation are driven by admin managed structure, not by code |
| 4 | Given the menu structure is changed in admin, when the storefront is reloaded, then the new structure appears |

### US-01-06 Mobile header and navigation

*As a shopper on a phone, I want a header that does not consume the screen, so that I can see products.*

**Traceability:** NAV-08, NAV-09, NAV-10, NFR-01. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The mobile header is a simplified layout with quick search and a hamburger menu |
| 2 | The hamburger menu exposes the same category structure as the desktop mega menu |
| 3 | The mobile header behaves correctly in right to left |

### US-01-07 Trust and support pages

*As a cautious buyer, I want to read the policies before I pay, so that I know what happens if the product is wrong.*

**Traceability:** CMS-01 to CMS-08, CMS-10, IA-24 to IA-31. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The store carries About, Contact with a working form, Help and FAQ, Authenticity Guarantee, Shipping Policy, Return and Refund Policy, Privacy Policy, and Terms and Conditions |
| 2 | Each page exists in both languages and is reachable from the footer |
| 3 | The contact form delivers to the configured address and confirms submission to the sender |
| 4 | A WhatsApp click to chat link is present and opens a conversation with the configured number |
| 5 | The Return and Refund policy page states the return window configured in the system, so that policy and behaviour cannot drift apart |

> **The pages are built by the developer. The legal and policy text within them is written and supplied by the client** (CR-04), and should be reviewed by an Egyptian lawyer before launch (R-05). The authenticity and warranty policy is an open decision, OD-13.

### US-01-08 Premium visual treatment

*As the store owner, I want discounting to look considered rather than cheap, so that the store reads as premium.*

**Traceability:** MER-06, MER-07, FIX-03, OBJ-04. **Scope:** P1. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | Imagery, typography and spacing follow the approved Interface Design and its design system |
| 2 | Sale prices, savings and badges use the treatment defined in the design system, not a default theme treatment |
| 3 | The implemented pages match the approved design across all mobile and desktop states |

---

# EPIC E02: HOME PAGE

**What this epic delivers.** The first page most visitors see, and the page the client changes most often. Every section is admin managed, which is why this epic is written against Annex A Section G11 as much as Section C2.

### US-02-01 Home page sections

*As a first time visitor, I want the home page to show me what is worth buying, so that I do not have to know what to search for.*

**Traceability:** HOME-01 to HOME-07, JRN-01. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The home page carries a hero banner or slider, trending products, new arrivals, featured collections, category shortcuts, brand highlights and promotional banners |
| 2 | Each section draws its products from the rule or curation set in admin, not from code |
| 3 | An empty section, for example no products currently flagged trending, renders without a broken region |
| 4 | Product cards link correctly to the product, and the `select_item` event fires |

### US-02-02 Trust signals and signup prompt

*As a first time visitor, I want to see immediately that the store is genuine, so that I am willing to enter a card.*

**Traceability:** HOME-08, HOME-09, OBJ-04. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Trust signals covering authenticity, delivery and returns appear on the home page |
| 2 | A newsletter or account signup prompt is present, and marketing consent is captured separately from the signup itself |
| 3 | The trust signal text is editable in admin in both languages |

### US-02-03 Home page content is editable

*As the store owner, I want to change the home page myself, so that a campaign does not wait for a developer.*

**Traceability:** HOME-10, ADM-61, SSC-02. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Section content, images, links and calls to action are editable from admin without code |
| 2 | Given a section is edited and saved, when the storefront is reloaded, then the change is live |
| 3 | Each section is maintained independently in English and Arabic |

### US-02-04 Home page section order

*As the store owner, I want to change what comes first, so that the page reflects the season rather than the build order.*

**Traceability:** HOME-11, ADM-59, ADM-60, SSC-03. **Scope:** P1-L. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | The order of existing home page sections can be changed from admin |
| 2 | Any section can be hidden and shown again without losing its content |
| 3 | The reduced boundary is explicit: section order and visibility are self service; **free form drag and drop composition with arbitrary new section types is not included**, per Annex A section 1.2 |

> **Section scheduling is not in the first release.** HOME-12 and ADM-62 are P2. A campaign section is switched on and off manually at launch.

---

# EPIC E03: BROWSING, FILTERING AND SEARCH

**What this epic delivers.** How a visitor finds a product. It carries one of the two named reductions in the storefront, being Arabic search handling, and that boundary is stated in the story rather than left to discovery.

### US-03-01 Product listing grid

*As a visitor browsing a category, I want a grid that tells me price, availability and what is special about each product, so that I can compare without opening ten tabs.*

**Traceability:** PLP-01 to PLP-05, PLP-17, PLP-20, PLP-21. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Each card shows image, name, price and any badge |
| 2 | A sale price is shown alongside the original price |
| 3 | Stock status is indicated on the card |
| 4 | Quick add to cart and quick add to wishlist work from the card without leaving the page |
| 5 | The result count is displayed, and the category banner, description and breadcrumb are shown |
| 6 | The `view_item_list` event fires with the list context |

### US-03-02 Filtering

*As a visitor, I want to narrow a long list, so that I can reach a decision.*

**Traceability:** PLP-06 to PLP-10, PLP-18, PLP-22. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Filters exist for category, brand, price range, attribute such as size or colour, and availability |
| 2 | Active filters are visible and each can be removed individually |
| 3 | Given filters are applied, when the visitor navigates to a product and returns, then the filters are still applied |
| 4 | On a phone, filters open in a dedicated drawer rather than pushing the results off screen |
| 5 | Filter labels and values are correct in both languages |

### US-03-03 Filter by rating

*As a visitor, I want to see only well reviewed products, so that I can shortcut the decision.*

**Traceability:** PLP-11. **Scope:** P1-L. **Stage:** S2. **Priority:** Later

| # | Acceptance criterion |
|---|---|
| 1 | Delivered in the second release. All other filters are live at launch |
| 2 | Given ratings exist, when the rating filter is applied, then only products at or above the selected rating are returned |

### US-03-04 Sorting

*As a visitor, I want to order the list the way I am shopping, so that the first screen is the useful one.*

**Traceability:** PLP-12 to PLP-16. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Sorting is available by newest, price ascending, price descending, best selling and relevance |
| 2 | The chosen sort persists while filters are changed |
| 3 | Results are paginated or infinitely scrolled consistently, and the position is retained when returning from a product |

### US-03-05 Empty results

*As a visitor whose filters returned nothing, I want a way forward, so that I do not leave.*

**Traceability:** PLP-19, SRCH-05. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | An empty result set shows an explanatory state, not a blank page |
| 2 | Alternative suggestions are offered, and the filters that produced no result can be cleared in one action |
| 3 | A zero result search records the term for later analysis and fires the zero result event |

### US-03-06 Search

*As a visitor who knows what they want, I want to type it and find it, so that I do not have to learn the category tree.*

**Traceability:** SRCH-01 to SRCH-04, SRCH-06a. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Search covers product name, description, SKU, brand and category |
| 2 | Suggestions appear as the visitor types |
| 3 | The results page carries the full filter set |
| 4 | Search operates correctly in both English and Arabic |
| 5 | The `search` event fires with the term and the result count |

### US-03-07 Arabic search handling

*As an Arabic first shopper, I want my spelling to still find the product, so that I do not conclude the store does not stock it.*

**Traceability:** SRCH-06, SRCH-07. **Scope:** P1-L [attn]. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A curated synonym and correction list covers the main categories and brands, including common Franco Arabic spellings |
| 2 | Given a term on the curated list, when it is searched in any of its listed forms, then the expected products are returned |
| 3 | The synonym list is managed from admin without a developer |
| 4 | The reduced boundary is explicit: **full automatic typo tolerance across the whole catalogue is not included**, per Annex A section 1.2. Coverage is what the curated list contains |

> **This is one of the twelve reductions listed in Annex A section 1.2, and it is tested against the reduced specification, not the full one.** The practical consequence is that the quality of Arabic search is a function of how complete the curated list is. Terms can be added at any time from admin, and adding them is not a change request.

### US-03-08 Search term logging

*As the store owner, I want to know what people searched for, so that I can stock what they wanted.*

**Traceability:** SRCH-08. **Scope:** P1. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | Every search term is recorded with its result count |
| 2 | Zero result terms are distinguishable from terms that returned results |
| 3 | The reporting layer over this data is the search terms report, which is second release |

### US-03-09 Collection and brand pages

*As a visitor following a campaign, I want a page that holds exactly the products it promised, so that the advertisement and the page agree.*

**Traceability:** IA-04, IA-05, IA-35, MER-04, ADM-57. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Curated collection pages and brand pages exist and are reachable from navigation and search |
| 2 | Products in a collection appear in the order set in admin |
| 3 | Each collection and brand page carries its own banner, description and imagery, maintained in both languages |

---

# EPIC E04: PRODUCT DETAIL

**What this epic delivers.** The page where the decision is made. Annex A JRN-03 lists what it must answer, and every item on that list is a criterion here.

### US-04-01 Product media

*As a visitor, I want to see the product properly, so that I am not guessing.*

**Traceability:** PDP-01, PDP-02. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The gallery supports multiple images with zoom |
| 2 | Video is supported where supplied |
| 3 | Media order follows the order set in admin |
| 4 | Images carry alt text, and a missing alt text is reported rather than silently absent |

### US-04-02 Product identity and price

*As a visitor, I want the price and any saving stated plainly, so that I am not calculating.*

**Traceability:** PDP-03, PDP-04. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Product name and brand are shown |
| 2 | Price is shown, and where a sale price applies both the sale price and the original are shown with the saving |
| 3 | Price presentation follows the premium treatment in the design system, per MER-07 |

### US-04-03 Variants and stock

*As a visitor buying a specific size or colour, I want to know that exact option is available, so that I am not disappointed at checkout.*

**Traceability:** PDP-05, PDP-06, PDP-07. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Variant selection is available for the product attributes defined in admin |
| 2 | Stock status is shown per variant, not only per product |
| 3 | Given a variant is out of stock, when it is selected, then Add to Cart is unavailable for it and the reason is stated |
| 4 | The quantity selector cannot exceed the available stock of the selected variant |

### US-04-04 Add to cart, buy now and wishlist

*As a visitor who has decided, I want to act immediately, so that the decision is not lost.*

**Traceability:** PDP-08, PDP-09, PDP-10, JRN-04. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Add to Cart adds the selected variant and quantity and confirms visibly |
| 2 | Buy Now takes the visitor to checkout with that item in the cart |
| 3 | Add to Wishlist works for a signed in customer and for a guest, per US-11-04 |
| 4 | The `add_to_cart` and `add_to_wishlist` events fire with the correct item parameters |

### US-04-05 Product information

*As a visitor, I want the description and specifications, so that I can confirm this is the right product.*

**Traceability:** PDP-11, PDP-12, PDP-13. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A short description or key features block appears above the fold on desktop |
| 2 | The full description is available on the page |
| 3 | A specifications table renders where specifications exist, and is absent rather than empty where they do not |
| 4 | All three are maintained independently in English and Arabic |

### US-04-06 Delivery, returns and authenticity

*As a cautious buyer, I want to know when it arrives, what happens if I return it, and that it is genuine, before I add it to the cart.*

**Traceability:** PDP-14, PDP-15, PDP-16, CMS-02. **Scope:** P1-L for the delivery estimate, otherwise P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A delivery estimate is shown where available for the customer location or the default zone |
| 2 | A returns policy summary is shown, consistent with the configured return window |
| 3 | An authenticity guarantee statement is shown and links to the authenticity page |
| 4 | The delivery estimate is presented as an estimate, and is not stated as a guaranteed date |

### US-04-07 Reviews on the product page

*As a visitor, I want to read what other buyers said, so that I trust the listing.*

**Traceability:** PDP-17, REV-04. **Scope:** P1-L. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | Approved reviews are displayed with the average rating |
| 2 | Only moderated and approved reviews appear |
| 3 | A product with no reviews shows a clean state, not an empty block |
| 4 | The reduced boundary is explicit: photo and video reviews, verified purchase badges, helpfulness voting and questions and answers are not included, per Annex A section 1.2 |

### US-04-08 Related and recently viewed

*As a visitor, I want to see near alternatives, so that a product that is not quite right does not end the visit.*

**Traceability:** PDP-18, PDP-19, MER-05, WISH-07. **Scope:** P1. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | Related products are shown from the assignment made in admin |
| 2 | Recently viewed products are shown for the current visitor |
| 3 | Recently viewed persists for a signed in customer across sessions |

### US-04-09 Out of stock handling

*As a visitor, I want to be told clearly that something is unavailable, so that I do not attempt to buy it.*

**Traceability:** PDP-22, AC-15, BR-003. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | An out of stock product states so clearly on the product page |
| 2 | The product page, the cart and checkout are consistent about availability, with no route that results in an unintended sale |
| 3 | Given a product goes out of stock while it is in a cart, when the cart is viewed, then the customer is told before checkout |
| 4 | Back in stock notification signup is not included at launch, being P2 |

### US-04-10 Sharing and structured data

*As the store owner, I want product pages to be shareable and legible to search engines, so that marketing has something to work with.*

**Traceability:** PDP-21, PDP-24, MKT-16. **Scope:** P1. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | Share buttons are present and produce a correct link and preview |
| 2 | Product structured data is emitted and validates against the search engine testing tools |
| 3 | Structured data reflects the active language and the correct price and availability |

---

# EPIC E05: CART AND CONTINUITY

**What this epic delivers.** The cart, and the four added contract commitments about never losing it. Annex A records CART-13, CART-14, CART-15 and CART-18 as commitments the developer added rather than requirements the client asked for, and AC-18 tests them.

### US-05-01 Cart contents

*As a shopper, I want to see exactly what I am buying, so that I can correct it before I pay.*

**Traceability:** CART-01, CART-02, CART-03, CART-04. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Each line shows image, name, selected variant, unit price and quantity |
| 2 | Quantity can be changed and the totals update |
| 3 | An item can be removed, and removal fires `remove_from_cart` |
| 4 | An item can be moved to the wishlist rather than deleted |

### US-05-02 Mini cart

*As a shopper adding several items, I want to confirm each addition without leaving the page, so that browsing is not interrupted.*

**Traceability:** CART-12, NAV-06. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Adding an item updates the header count and shows the mini cart |
| 2 | The mini cart lists current items and offers a route to the cart and to checkout |
| 3 | The mini cart renders correctly in right to left |

### US-05-03 Totals breakdown

*As a shopper, I want every charge itemised, so that the final number is never a surprise.*

**Traceability:** CART-05, CHK-08, BR-010. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Subtotal, discount, shipping and total are shown separately |
| 2 | The total equals the sum of its parts, in both languages |
| 3 | Discounts never produce a negative order total |

### US-05-04 Free shipping progress

*As a shopper one item away from free shipping, I want to be told, so that I add the item rather than abandon.*

**Traceability:** CART-07, JRN-05, BR-002, AC-01, AC-02. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Given one qualifying item is in the cart, when the cart is viewed, then it states that adding one more unlocks free shipping |
| 2 | Given two qualifying items are in the cart, when the cart is viewed, then shipping becomes free per the active promotion, with no code change required |
| 3 | The qualifying condition is read from the promotion configuration, not from code |
| 4 | The prompt disappears once the threshold is met |

> **The definition of two items is an open decision, OD-04:** two units of anything, or two distinct products, and whether any product types are excluded. The rule is configurable either way; the value is the client decision and it blocks the promotions build.

### US-05-05 Cross sell in the cart

*As the store owner, I want a relevant suggestion at the cart, so that average order value rises.*

**Traceability:** CART-08, OBJ-02. **Scope:** P1-L. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | Cart level cross sell suggestions are shown from the related product assignment |
| 2 | Adding a suggestion adds it to the same cart without losing the existing lines |
| 3 | The reduced boundary is explicit: automatic frequently bought together recommendation is P2 |

### US-05-06 Coupon entry in the cart

*As a shopper with a code, I want to know immediately whether it worked, so that I am not guessing at checkout.*

**Traceability:** CART-06, AC-04. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A valid coupon applies and the discount appears in the breakdown |
| 2 | Given an invalid, expired, exhausted or ineligible coupon, when it is entered, then a clear reason is shown and the total is unchanged |
| 3 | The reason distinguishes expired from ineligible from not found, in both languages |
| 4 | The `apply_coupon` event fires on a successful application |

### US-05-07 Stock re validation in the cart

*As a shopper, I want the cart to tell me the truth about availability, so that checkout does not fail.*

**Traceability:** CART-10, BR-003, AC-15. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Stock is re validated every time the cart is viewed |
| 2 | Given the available quantity has fallen below the cart quantity, when the cart is viewed, then the customer is told which line is affected and what quantity is available |
| 3 | The cart never permits progression to checkout with a quantity above available stock |

### US-05-08 Empty cart

*As a shopper with an empty cart, I want somewhere to go, so that the visit is not over.*

**Traceability:** CART-11. **Scope:** P1. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | The empty cart shows an explanatory state with suggestions |
| 2 | The suggestions link to live, in stock products |

### US-05-09 [key] Cart persists for signed in customers

*As a returning customer, I want my cart to be where I left it, on whatever device I pick up, so that I do not rebuild it.*

**Traceability:** CART-09. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Given a signed in customer adds items on one device, when they sign in on another device, then the same cart is present |
| 2 | The cart survives sign out and sign in |
| 3 | Prices are re validated when the cart is restored, and a changed price is shown as changed rather than applied silently |

### US-05-10 [key] Guest cart survives registration

*As a guest who filled a cart and then decided to register, I want to keep everything I chose, so that registering does not cost me my basket.*

**Traceability:** CART-13, AC-18. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Given a guest cart with items, when the guest completes registration, then every item is present in the new account cart |
| 2 | No item is lost and no quantity is changed |
| 3 | The behaviour holds whether registration is started from the cart, the header or the checkout |

### US-05-11 [key] Guest cart merges on sign in

*As a guest who filled a cart and then signed in, I want both carts combined sensibly, so that I lose nothing and see no duplicates.*

**Traceability:** CART-14, CART-18, AC-18. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Given a guest cart and a saved account cart both containing items, when the guest signs in, then the two merge into one cart |
| 2 | No item from either cart is lost |
| 3 | The same product and variant appearing in both carts produces one line, not two |
| 4 | Given merging would exceed available stock for a line, when the merge occurs, then the quantity is capped at available stock and the customer is told what changed |
| 5 | Prices are re validated on merge |

### US-05-12 [key] Guest cart survives Google sign in

*As a guest signing in with Google, I want identical cart behaviour, so that the faster route is not the lossy one.*

**Traceability:** CART-15, AUTH-10, AC-18, AC-19. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The merge behaviour in US-05-11 holds identically through the Google flow |
| 2 | Given the Google account email matches an existing account, when sign in completes, then the cart merges into that existing account and no second account is created |

### US-05-13 Guest cart survives a browser close

*As a guest who came back tomorrow, I want my cart still there, so that I can finish what I started.*

**Traceability:** CART-16. **Scope:** P1. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | A guest cart survives closing and reopening the browser within the defined retention window |
| 2 | The retention window is a documented setting, not a hard coded value |
| 3 | Stock and prices are re validated when the cart is restored |

---

# EPIC E06: IDENTITY AND ACCOUNTS

**What this epic delivers.** Every way into the store, and the rule that they all resolve to one customer. Annex A records Google sign in and one step account creation as added contract commitments.

### US-06-01 Register with email and password

*As a new customer, I want to create an account quickly, so that I am not filling a form before I have bought anything.*

**Traceability:** AUTH-01, AUTH-05, AUTH-14, AUTH-09. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Registration requires no more than email, password and consent. No unnecessary mandatory fields |
| 2 | Email verification is sent and the account state reflects whether the email is verified |
| 3 | Marketing consent is captured separately from account creation, and is not implied by registering |
| 4 | The `sign_up` event fires |
| 5 | Phone number registration is available at the reduced scope recorded at AUTH-02 |

### US-06-02 Sign in and password reset

*As a returning customer, I want to get back in without friction, so that I buy rather than give up.*

**Traceability:** AUTH-03, AUTH-04, AUTH-12, AUTH-16. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Sign in with email and password succeeds and fires the `login` event |
| 2 | A failed sign in states that the credentials are wrong without revealing whether the email exists |
| 3 | Password reset sends a link that is single use and time limited, and a used or expired link says so plainly |
| 4 | A passwordless email sign in link is available as an alternative, at the scope recorded at AUTH-12 |

### US-06-03 [key] Sign in and register with Google

*As a visitor who does not want to invent another password, I want to use my Google account, so that I can buy in one tap.*

**Traceability:** AUTH-10, AC-19. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A visitor can both register and sign in with a Google account |
| 2 | Given no account exists for the Google email, when sign in completes, then an account is created and the customer is signed in |
| 3 | Given an account already exists for that email, when sign in completes, then the customer is signed in to it and no second account is created |
| 4 | The cart merge in US-05-12 applies |
| 5 | Social login beyond Google is not in the first release, being P2 |

### US-06-04 [key] One account per email

*As the store owner, I want one customer to be one record, so that order history, returns and marketing are not split across duplicates.*

**Traceability:** AUTH-13, AC-19. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Given an account created by Google sign in, when the same email registers manually, then the two resolve to one account rather than creating a second |
| 2 | Given an account created manually, when that email signs in with Google, then the existing account is used |
| 3 | The resolution is explained to the customer rather than failing silently |
| 4 | No route through registration, guest checkout linking or Google sign in produces two accounts for one email address |

### US-06-05 Account security

*As the store owner, I want accounts to be difficult to attack, so that a breach is not the first thing customers hear about us.*

**Traceability:** AUTH-15, AUTH-16, NFR-05. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Sessions are handled securely and expire per the documented policy |
| 2 | Login attempts are rate limited, and the limit is a documented setting |
| 3 | Automated account creation is protected against |
| 4 | Passwords are stored using current secure practice, and are never recoverable in readable form |

### US-06-06 Guest checkout

*As a cautious buyer, I want to buy without an account, so that a purchase does not become a commitment.*

**Traceability:** AUTH-07, FIX-09, BR-008, AC-13, JRN-06. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A purchase completes end to end with no account created and no forced registration step |
| 2 | Registration is encouraged, not required, and the encouragement does not obstruct the guest route |
| 3 | The guest receives the same order confirmation and the same notifications as a registered customer |

### US-06-07 [key] One step account creation after ordering

*As a guest who has just ordered, I want to become a customer by choosing a password, so that I do not retype everything I just entered.*

**Traceability:** AUTH-11, AUTH-08, BR-008, AC-13. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The order confirmation offers account creation requiring a password only |
| 2 | Given the guest sets a password, when the account is created, then name, email, phone and address are carried across with no re entry |
| 3 | The order just placed is linked to the new account and appears in order history |
| 4 | The linking is secure: a guest order cannot be claimed by an account that does not own the ordering email |
| 5 | Declining the offer leaves the order intact and reachable through the confirmation email |

---

# EPIC E07: CHECKOUT AND ORDER PLACEMENT

**What this epic delivers.** The point at which the store either takes money correctly or does something expensive. Five of the twenty acceptance scenarios in Annex A Section N land in this epic.

### US-07-01 Address entry and saved addresses

*As a customer, I want to give my address in the form Egyptian couriers actually use, so that the parcel arrives.*

**Traceability:** CHK-01, CHK-02, ACCT-04, CHK-11. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The address form captures governorate, city, district, street and landmark |
| 2 | Governorate is selected from the list the shipping zones are configured against, not typed freely |
| 3 | A returning customer selects from saved addresses rather than retyping |
| 4 | Validation errors name the field and the problem, in both languages |
| 5 | Address autocomplete via maps is not in the first release, being P2 |

### US-07-02 Shipping method and cost

*As a customer, I want to know the delivery cost before I commit, so that the total is not a surprise.*

**Traceability:** CHK-03, SHIP-01, SHIP-15, SHIP-04, SHIP-05. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The shipping cost is calculated from the manual rate zone matching the selected governorate |
| 2 | A delivery estimate is shown where available |
| 3 | Given a free shipping promotion applies, when the total is calculated, then the shipping charge is overridden to zero |
| 4 | Given no rate zone matches the address, when the customer reaches this step, then the checkout states the problem rather than charging zero silently |

> **Live carrier rate lookup at checkout is not included** (SHIP-02, P2). Rates are manual governorate zones matching the client rate card, which the client supplies under CR-03.

### US-07-03 Payment method selection

*As a customer, I want to pay the way I prefer, so that I do not abandon at the last step.*

**Traceability:** CHK-04, PAY-09, PAY-15, PAY-14. **Scope:** P1, with method availability conditional on OD-05 and OD-19. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The methods offered are exactly those enabled in configuration and approved for the merchant account |
| 2 | Card payment with 3-D Secure authentication is available |
| 3 | Cash on delivery appears only if it is selected and configured, including any value limit, zone restriction or fee |
| 4 | Mobile wallet appears only if selected and approved by the provider |
| 5 | Disabling a method in configuration removes it from checkout without a code change |

> **Which methods are live at launch is not settled by this document.** It depends on the client selection under OD-19, on whether cash on delivery is offered at all under OD-05, and on what the provider approves for the merchant account. The capability is built and tested either way.

### US-07-04 Order review and terms

*As a customer, I want one last look before I pay, so that I catch my own mistake.*

**Traceability:** CHK-05, CHK-06, CHK-08, CHK-09. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A review step shows items, address, shipping method, payment method and the full total breakdown before confirmation |
| 2 | An order notes field is available |
| 3 | Terms acceptance is required and is recorded against the order |
| 4 | Every charge in the breakdown is itemised, with no unexplained difference between the review total and the amount charged |

### US-07-05 Coupon at checkout

*As a customer who found a code late, I want to apply it at checkout, so that I do not have to go back to the cart.*

**Traceability:** CHK-07, AC-04. **Scope:** P1. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | A coupon can be entered at checkout with the same validation and messaging as the cart |
| 2 | An invalid coupon leaves the total unchanged and states the reason |
| 3 | Applying a coupon recalculates shipping where the coupon affects it |

### US-07-06 [key] Stock validation at placement

*As the store owner, I want an order never to be confirmed for stock we do not have, so that we do not sell what we cannot ship.*

**Traceability:** CHK-12, BR-003, AC-05, AC-15. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Stock is re validated at the moment Place Order is pressed, not only when the cart was viewed |
| 2 | Given stock ran out between the cart and placement, when the order is placed, then the unavailable quantity is not confirmed and the customer is informed before payment is taken |
| 3 | The customer is told exactly which line is affected and what quantity is available |
| 4 | No order is created in a state that reserves more stock than exists |

### US-07-07 [key] Duplicate order and duplicate charge protection

*As the store owner, I want a double click to be harmless, so that we never charge twice or ship twice.*

**Traceability:** PAY-05, NFR-07, AC-06, AC-07. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Given Place Order is pressed twice in rapid succession, when the requests are processed, then exactly one order is created and exactly one charge is made |
| 2 | Given the payment provider sends the same confirmation callback twice, when both are received, then the second has no additional effect on the order, the stock or the payment record |
| 3 | The protection holds across a page refresh and a back button during payment |
| 4 | The duplicate attempt is recorded so it can be diagnosed, rather than silently discarded |

### US-07-08 Order confirmation

*As a customer who has just paid, I want proof and a next step, so that I know it worked.*

**Traceability:** JRN-08, ORD-01, ORD-03. **Scope:** P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The confirmation shows the order number, an item summary, the amount charged and an estimated delivery |
| 2 | The confirmation is reachable again from the confirmation email |
| 3 | The `purchase` event fires with items, tax, shipping and discount values |
| 4 | For a guest, the one step account creation offer in US-06-07 is presented here |

### US-07-09 Mobile checkout

*As a shopper on a phone, I want checkout to be short, so that I finish it standing up.*

**Traceability:** CHK-10, NFR-01. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Every checkout step is usable on a phone without horizontal scrolling |
| 2 | Input types match the field, so that numeric and telephone keyboards appear where appropriate |
| 3 | The mobile checkout is correct in right to left |

### US-07-10 Order status model

*As the store owner, I want order state to be explicit and complete, so that the system never has to guess what happened.*

**Traceability:** ORD-01 to ORD-14. **Scope:** P1, with ORD-14 at P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The status set is Pending Payment, Payment Failed, Confirmed, Preparing, Shipped, Out for Delivery, Delivered, Cancelled, Delivery Failed or Returned to Origin, COD Collected Awaiting Remittance, Return Requested, Return Approved or Rejected, Returned, and Refund Pending, Refunded or Partial |
| 2 | Delivery Failed or Returned to Origin is a distinct state and is never recorded as Delivered |
| 3 | Invalid transitions are rejected with a clear message rather than silently accepted |
| 4 | Every status change records who made it and when |

---

# EPIC E08: PAYMENTS AND REFUNDS

**What this epic delivers.** Taking money safely, and giving it back correctly. The provider recommendation is Paymob (OD-06); the abstraction layer exists so that the recommendation can change without rebuilding checkout.

### US-08-01 [key] Payment abstraction layer

*As the store owner, I want the payment provider to be replaceable, so that a commercial decision does not become a rebuild.*

**Traceability:** PAY-01, PAY-13, INT-16. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Checkout talks to an internal payment interface, not directly to the provider |
| 2 | The provider integration sits behind an adapter with retries, logging and webhook handling |
| 3 | Changing provider requires a new adapter, not changes to the checkout flow |
| 4 | Integration failures are logged with enough detail to diagnose them |

### US-08-02 No card data on Mizzey servers

*As the store owner, I want card data never to touch our systems, so that our compliance burden stays small.*

**Traceability:** PAY-02, PAY-03, NFR-06. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Payment uses the provider hosted and tokenised flow |
| 2 | No card number, expiry or security code is stored, logged or transmitted by the store |
| 3 | The stored payment record holds a transaction reference and status, never card data |

### US-08-03 [key] Callback verification

*As the store owner, I want a payment confirmation to be provably genuine, so that nobody can mark an order paid by forging a request.*

**Traceability:** PAY-04, PAY-16. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Every payment callback is cryptographically verified before it is acted upon |
| 2 | Given a callback fails verification, when it is received, then it is rejected, logged, and has no effect on the order |
| 3 | Verification failures are visible to the team rather than only in a log file |

### US-08-04 Payment failure handling

*As the store owner, I want a failed payment to leave the order unmistakably unpaid, so that we never ship against a failure.*

**Traceability:** ORD-02, AC-08, NOTF-03. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Given a payment fails, when the result is processed, then the order is set to Payment Failed and is never shown as Paid or Confirmed |
| 2 | Reserved stock is released per the reservation policy |
| 3 | The customer receives a payment failed notification with a retry route |
| 4 | A retry that succeeds moves the same order forward rather than creating a second one |

### US-08-05 Payment records

*As the accountant, I want every payment traceable to its provider transaction, so that reconciliation is possible.*

**Traceability:** PAY-06, PAY-19, ENT-09, ROLE-06. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Transaction reference, status, amount, currency and timestamps are stored against the order |
| 2 | The distinction between paid and funds received is recorded and documented |
| 3 | The Accountant role can read payment records and transaction references |
| 4 | A role without financial permission cannot reach them |

### US-08-06 [key] Refunds

*As the accountant, I want to refund from the order screen, correctly and only once, so that a refund is never larger than the payment.*

**Traceability:** PAY-07, PAY-08, PAY-17, ADM-91, BR-006, AC-10, AC-11. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A full refund can be issued from the order screen through the provider |
| 2 | A partial refund can be issued for a specified amount or specified items |
| 3 | Given discounts were applied, when a refund is attempted, then it cannot exceed the amount actually paid for that item or order after discount |
| 4 | Given a staff member without refund permission, when they open the order, then the refund action is not available to them |
| 5 | Every refund records amount, status, payment reference, actor and timestamp, and is written to the audit log |
| 6 | The refund event fires for analytics |

### US-08-07 Cash on delivery capability

*As the store owner, I want cash on delivery built and tested, so that turning it on is a decision rather than a project.*

**Traceability:** PAY-09, ORD-14, SHIP-16. **Scope:** P1 capability, conditional on OD-05. **Stage:** S1 if selected. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Cash on delivery is implemented and tested whether or not it is enabled at launch |
| 2 | Enabling or disabling it is a configuration change, including any value limit, zone restriction or fee |
| 3 | A cash on delivery order moves to COD Collected Awaiting Remittance on collection, distinct from funds received |
| 4 | Cash on delivery verification by one time password is not in the first release, being P2, and is an open decision at OD-29 |

---

# EPIC E09: SHIPPING AND FULFILMENT

**What this epic delivers.** Getting the parcel out and tracking what happens to it. The carrier recommendation is Bosta (OD-07), and this epic includes an explicit defence against a known defect in that integration.

### US-09-01 Shipping zones and rates

*As the store owner, I want to set delivery prices by governorate myself, so that a rate card change is not a developer task.*

**Traceability:** SHIP-01, SHIP-15. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Shipping zones and their rates are managed from admin |
| 2 | Zones are configured by governorate, matching the client carrier rate card |
| 3 | Changing a rate takes effect on the next checkout calculation without a code change |
| 4 | Every governorate the store ships to has a rate, and a gap is reported rather than defaulting to zero |

### US-09-02 Carrier integration

*As the warehouse, I want orders to reach the carrier from the order screen, so that we are not retyping addresses into another system.*

**Traceability:** SHIP-08, SHIP-09, SHIP-03, INT-02, INT-16. **Scope:** P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | An order is pushed to the carrier and a tracking code returns automatically and is linked to the order |
| 2 | The integration sits behind an adapter with retries, logging and webhook handling |
| 3 | Given the carrier is unreachable, when a dispatch is attempted, then the failure is reported to the operator and the order is not falsely marked shipped |

### US-09-03 Bulk dispatch, labels and pickups

*As the warehouse, I want to send the day in one action, so that dispatch is minutes rather than hours.*

**Traceability:** SHIP-10, SHIP-11, SHIP-12, ADM-155. **Scope:** P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Many orders can be sent to the carrier in one action |
| 2 | Airway bills for the dispatched selection print together |
| 3 | Pickup requests are created and their state is tracked |
| 4 | Given some orders in a bulk dispatch fail, when the action completes, then the successes are recorded, the failures are listed individually with reasons, and the operation is safe to retry for the failures only |

### US-09-04 Carrier status retrieval

*As the store owner, I want the order to show where the parcel is, so that customers do not have to ask.*

**Traceability:** SHIP-13, ACCT-07. **Scope:** P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Carrier status is retrieved and reflected on the order |
| 2 | The customer sees the current status in their account and through the tracking link |
| 3 | A stale or missing carrier status is shown as unknown rather than as a false state |

### US-09-05 [key] Explicit carrier status mapping

*As the store owner, I want to know exactly how a carrier status becomes an order status, so that a carrier quirk does not corrupt our records.*

**Traceability:** SHIP-14. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The mapping from every carrier status to an order status is explicit, documented, and visible in admin |
| 2 | An unrecognised carrier status does not change the order status, and is raised for a human decision |
| 3 | The mapping is verified against real carrier responses during Stage 4, not assumed from documentation |

### US-09-06 [key] Returned to origin is never Delivered

*As the store owner, I want an undelivered parcel recorded as undelivered, so that an unpaid return is never counted as a completed sale.*

**Traceability:** SHIP-17, ORD-13, AC-16. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Given a parcel is returned to origin, when the carrier reports it, then the order is recorded as Delivery Failed or Returned to Origin |
| 2 | The order is never recorded as Delivered in this case, under any carrier status mapping |
| 3 | Stock state and payment state remain correct: nothing is treated as sold and no payment state is advanced |
| 4 | The case appears in the operations queue for a decision |

> **This is a known defect in the published carrier integration, designed around rather than inherited.** Annex A section 2.2 records reported cases of returned parcels showing as delivered. AC-16 tests this specifically.

### US-09-07 [key] Cash on delivery reconciliation

*As the store owner, I want collected money and received money kept apart, so that the dashboard never overstates my cash position.*

**Traceability:** SHIP-16, ORD-14, PAY-19. **Scope:** P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Collected and remitted are recorded separately per order |
| 2 | An order delivered against cash on delivery shows as collected but not remitted until remittance is recorded |
| 3 | The difference between collected and remitted is visible as a figure, not only as two lists |
| 4 | The remittance cycle used is the one the client confirms under OD-20 |

---

# EPIC E10: MY ACCOUNT AND AFTER SALES

**What this epic delivers.** Everything a customer can do without contacting anybody. The measure of this epic is the volume of support messages the store does not receive.

### US-10-01 Account dashboard

*As a returning customer, I want one place that holds my orders, addresses and returns, so that I am not hunting.*

**Traceability:** ACCT-01, ACCT-08, IA-17. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The dashboard shows an overview with routes to profile, addresses, orders, wishlist and returns |
| 2 | Recent orders are shown with their current status |
| 3 | A customer sees only their own data, verified by attempting access to another customer record |

### US-10-02 Order history, detail and tracking

*As a customer, I want to see exactly what I was charged and where the parcel is, so that I do not have to ask.*

**Traceability:** ACCT-05, ACCT-06, ACCT-07, ENT-08. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Order history lists all orders with number, date, total and status |
| 2 | Order detail shows the full breakdown as charged, including items, discounts, shipping and payment method |
| 3 | Given a price changed after the order, when the order is viewed, then it still shows the price originally charged |
| 4 | Tracking shows the current carrier status where a shipment exists |

### US-10-03 Invoice download

*As a customer, I want a document I can file, so that I can claim or account for the purchase.*

**Traceability:** ACCT-12, ADM-92. **Scope:** P1-L. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | An invoice can be downloaded from the order |
| 2 | The invoice carries the legal entity details supplied under CR-12 and the tax treatment settled under OD-09 |
| 3 | The invoice reflects the amounts actually charged, including discounts |

### US-10-04 Address book

*As a customer, I want my addresses saved, so that ordering again is fast.*

**Traceability:** ACCT-04, CHK-02, ENT-05. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Addresses can be added, edited, deleted and set as default |
| 2 | The default address is pre selected at checkout |
| 3 | Deleting an address does not alter the address recorded on past orders |

### US-10-05 Profile and password

*As a customer, I want to correct my own details, so that a typo is not permanent.*

**Traceability:** ACCT-02, ACCT-03. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Profile details are editable and the change is confirmed |
| 2 | Password change requires the current password and confirms success |
| 3 | Changing the email requires verification of the new address |

### US-10-06 Reorder

*As a returning customer, I want to repeat a previous order, so that a routine purchase takes one action.*

**Traceability:** ACCT-09, JRN-11. **Scope:** P1-L. **Stage:** S2. **Priority:** Later

| # | Acceptance criterion |
|---|---|
| 1 | Delivered in the second release. At launch, customers can view past orders and add items manually |
| 2 | Given a past order, when reorder is used, then a cart is built from its items subject to current availability and current prices |
| 3 | Items no longer available are reported rather than silently omitted |

### US-10-07 Account deletion request

*As a customer, I want to ask for my account to be removed, so that I am not stuck in a system I have left.*

**Traceability:** ACCT-13, NFR-12a. **Scope:** P1-L. **Stage:** S2. **Priority:** Later

| # | Acceptance criterion |
|---|---|
| 1 | Delivered in the second release. At launch, deletion requests are handled manually on request |
| 2 | A request is recorded with a timestamp and is visible to the team |
| 3 | Handling respects the record retention the client is obliged to keep for completed orders |

---

# EPIC E11: REVIEWS, WISHLIST AND RECENTLY VIEWED

**What this epic delivers.** The social proof and the saving behaviours, both at their recorded reduced scope.

### US-11-01 Submit a review

*As a customer who received a product, I want to say what I thought, so that other buyers are better informed.*

**Traceability:** REV-01, REV-02, ENT-14. **Scope:** P1. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | A star rating and written text can be submitted |
| 2 | The submission is acknowledged and its pending state is explained |
| 3 | The reduced boundary is explicit: photo and video reviews, verified purchase badges, helpfulness voting and questions and answers are not included |

### US-11-02 Review moderation

*As the store owner, I want to approve reviews before they publish, so that the store is not a channel for abuse.*

**Traceability:** REV-03, REV-04. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | No review is publicly visible before approval |
| 2 | A moderator can approve or reject, and rejection is recorded |
| 3 | The average rating shown on the product page and listing counts approved reviews only |

### US-11-03 Review invitation

*As the store owner, I want to ask for reviews after delivery, so that the catalogue accumulates proof.*

**Traceability:** REV-05, NOTF-06. **Scope:** P1. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | A review invitation is sent after delivery is recorded |
| 2 | The invitation is subject to the notification consent rules |
| 3 | The invitation links directly to the review form for the delivered product |

### US-11-04 Wishlist

*As a shopper who is not ready to buy, I want to save the product, so that I can find it again.*

**Traceability:** WISH-01, WISH-02, WISH-03, ENT-07, IA-11. **Scope:** P1. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | Items can be added to and removed from the wishlist from the product page and the listing card |
| 2 | The wishlist persists for a signed in customer |
| 3 | A guest gets a temporary wishlist for the session |
| 4 | The reduced boundary is explicit: sharing, multiple lists and price drop alerts are not included |

### US-11-05 Move wishlist to cart

*As a shopper who has decided, I want to move a saved item into the cart, so that the save was useful.*

**Traceability:** WISH-04. **Scope:** P1. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | An item moves from wishlist to cart in one action |
| 2 | An out of stock wishlist item cannot be moved to the cart, and the reason is stated |

### US-11-06 Guest wishlist transfers on sign in

*As a guest who saved items and then signed in, I want to keep them, so that the save was not wasted.*

**Traceability:** CART-17, WISH-03. **Scope:** P1-L. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | Given a guest wishlist, when the guest registers or signs in, then the items transfer to the account wishlist |
| 2 | An item already saved to the account does not duplicate |

### US-11-07 Recently viewed

*As a shopper comparing options, I want to get back to what I just looked at, so that I do not lose the shortlist.*

**Traceability:** WISH-07, PDP-19. **Scope:** P1. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | Recently viewed products are shown to the visitor |
| 2 | The list persists across sessions for a signed in customer |
| 3 | Product comparison is not included, being P2 |

---

# EPIC E12: NOTIFICATIONS

**What this epic delivers.** Transactional email, being the launch communication channel. Annex A records this as a recommended reduction against the client requirements document, and records it openly as one.

> **The channel position, stated plainly.** The client requirements document lists email plus WhatsApp and SMS in its first phase set. The recommendation recorded at OD-11 and section 1.2 of Annex A is **transactional email at launch**, with manual WhatsApp click to chat where applicable, because WhatsApp Business API and SMS automation require business verification, provider setup, lead time and a per message cost. WhatsApp and SMS automation are P2 (INT-03, INT-07). This is a reduction and is treated as one.

### US-12-01 Account created notification

*As a new customer, I want a welcome that confirms my account and my discount, so that I know registration worked.*

**Traceability:** NOTF-01, AUTH-05, AUTH-06. **Scope:** P1 email. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A welcome email is sent containing verification and, where the welcome discount applies, its details and conditions |
| 2 | The email renders correctly in both languages and in common email clients |
| 3 | The language of the email matches the language the customer used |

### US-12-02 Order confirmation notification

*As a customer, I want written confirmation of what I ordered, so that I have a record.*

**Traceability:** NOTF-02. **Scope:** P1 email. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | An order confirmation email is sent containing the order number and a summary |
| 2 | It is sent for guest orders as well as account orders |
| 3 | It carries a route back to the order confirmation page |

### US-12-03 Payment failed notification

*As a customer whose payment failed, I want to know and be able to retry, so that the order is not simply lost.*

**Traceability:** NOTF-03, ORD-02. **Scope:** P1 email. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A payment failed email is sent with a retry route |
| 2 | It does not state or imply that the order is confirmed |

### US-12-04 Shipping and delivery notifications

*As a customer, I want to know when it ships and when it arrives, so that I can be there.*

**Traceability:** NOTF-04, NOTF-06. **Scope:** P1 email. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A shipped email is sent with the carrier and the tracking number |
| 2 | A delivered email is sent with a review invitation |
| 3 | An out for delivery update is not in the first release, being P2 |

### US-12-05 Return update notifications

*As a customer who requested a return, I want to be told each time it moves, so that I am not chasing it.*

**Traceability:** NOTF-07, RET-10. **Scope:** P1 email. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | An email is sent when a return is approved, rejected or received |
| 2 | A rejection states the recorded reason |

### US-12-06 Refund notification

*As a customer, I want confirmation of my refund and its reference, so that I can match it to my statement.*

**Traceability:** NOTF-08, RET-08. **Scope:** P1 email. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A refund completed email is sent with the amount and the payment reference |
| 2 | A partial refund states the amount refunded and what it covers |

### US-12-07 Consent, frequency and compliance

*As the store owner, I want sending governed by consent, so that marketing activity stays inside the law.*

**Traceability:** NOTF-11, NOTF-12, NOTF-13, AUTH-09, NFR-12, R-04. **Scope:** P1, with NOTF-12 at P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Transactional email is separated from marketing email, and marketing sending requires recorded consent |
| 2 | Frequency limits are respected |
| 3 | Sending complies with the email provider policies, and unsubscribe is present on marketing email |
| 4 | Consent state is visible against the customer record |
| 5 | A notification preferences page is second release; unsubscribe links serve at launch |

### US-12-08 Notification templates

*As the store owner, I want to change the wording of emails myself, so that tone is not a developer task.*

**Traceability:** NOTF-14, ENT-15. **Scope:** P1-L. **Stage:** S2. **Priority:** Later

| # | Acceptance criterion |
|---|---|
| 1 | Delivered in the second release. At launch, the developer changes email wording on request |
| 2 | Templates are editable in both languages |
| 3 | A template edit cannot break the variables the email depends on |

---

# EPIC E13: RETURNS AND REFUNDS, CUSTOMER SIDE

**What this epic delivers.** The customer half of journey J6. The full workflow is live at launch; return collection logistics are not.

### US-13-01 [key] Request a return

*As a customer with a product that is wrong, I want to start the return myself, so that I do not have to negotiate by message.*

**Traceability:** RET-01, RET-02, ACCT-10, ENT-12, IA-22. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A return is requested from the account, against a specific order |
| 2 | The request captures the item, the quantity, a reason from a defined list, and a free text description |
| 3 | Photographs can be attached optionally |
| 4 | The request is confirmed on screen and by email |
| 5 | The `return_request` event fires |

### US-13-02 Return eligibility

*As the store owner, I want the return window enforced automatically, so that the policy is applied consistently.*

**Traceability:** RET-03, CMS-07. **Scope:** P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Eligibility is checked automatically against the configured return window |
| 2 | Given an order outside the window, when a return is attempted, then it is refused with the reason and the window stated |
| 3 | The window is a configuration value, and the policy page states the same value |
| 4 | The reduced boundary is explicit: a complex per product or per category eligibility rule engine is not included |

> **The return window and any product type exceptions are an open decision, OD-08, and they block the returns build.**

### US-13-03 Return status visibility

*As a customer, I want to see where my return is, so that I am not left wondering.*

**Traceability:** RET-10, AC-14. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The customer sees the return status at every stage from their account |
| 2 | The stages shown match the states the team works through |
| 3 | A rejected return shows the recorded reason |

### US-13-04 Refund visibility

*As a customer, I want to see that the refund was issued and to what, so that I know where to look for the money.*

**Traceability:** RET-08, ORD-12, PAY-19. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The refund state is visible on the order and the return |
| 2 | The amount and the destination, being the original payment method, are stated |
| 3 | Where settlement takes time, the timing is explained rather than left ambiguous |

> **Return shipping remains a manual arrangement at launch.** The system records and tracks the return; arranging collection or drop off is handled by the client team with the carrier directly. Automated return logistics, disposition handling and return reason analytics are P2 (RET-05, RET-07, RET-09).

---

# EPIC E14: PROMOTIONS AND BUSINESS RULES

**What this epic delivers.** The promotion engine, and the ten launch business rules. Annex A FIX-08 requires that critical rules are not hard coded, and PROMO-23 makes configurability itself a key requirement.

### US-14-01 [key] Promotion engine

*As the store owner, I want to build an offer myself, so that a campaign does not need a release.*

**Traceability:** PROMO-01 to PROMO-19, PROMO-23, FIX-08, ADM-110, ADM-111, ADM-113, ADM-115, ENT-11. **Scope:** P1, with stacking, priority and customer group at P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Reward types available are percentage discount, fixed amount discount and free shipping |
| 2 | Conditions available are minimum cart value, minimum item count, specific products, specific categories, specific brands, first order only, and customer group |
| 3 | A promotion can be automatic with no code, or require a coupon code |
| 4 | Usage limits can be set in total and per customer |
| 5 | Start and end dates can be scheduled, and a promotion can be paused or deactivated without deleting its usage history |
| 6 | Product and category exclusions and a maximum discount cap can be set |
| 7 | Every one of the above is configured from admin with no code change |
| 8 | Buy X get Y, tiered discounts and bundle pricing are not included, being P2 |

### US-14-02 Welcome discount

*As the store owner, I want a welcome discount that applies only where it should, so that it drives registration without leaking margin.*

**Traceability:** BR-001, AUTH-06, FIX-06, AC-03. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Value, cap, expiry and eligibility are all configurable from admin |
| 2 | Given a new customer meets the configured conditions, when they check out, then the discount applies |
| 3 | Given the conditions are not met, when they check out, then the discount does not apply and the reason is available |
| 4 | The discount cannot be used more times than its configured limit |

> **The welcome discount value, cap, expiry and eligibility are an open decision, OD-03, and they block the promotions build.**

### US-14-03 Free shipping rule

*As the store owner, I want free shipping to follow a rule I set, so that I can change the threshold without a developer.*

**Traceability:** BR-002, BR-009, FIX-07, SHIP-05, AC-01, AC-02. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The qualifying condition is admin configurable |
| 2 | Given the condition is met, when totals are calculated, then the free shipping promotion overrides the calculated shipping rate |
| 3 | Changing the threshold takes effect without a code change |

### US-14-04 [key] Promotion stacking is explicit

*As the store owner, I want to control which offers combine, so that a customer never accidentally receives three discounts at once.*

**Traceability:** BR-004, PROMO-16, PROMO-17, ADM-111. **Scope:** P1 for the rule, P1-L for the configuration depth. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Whether the welcome discount, a coupon and free shipping can combine is a setting, not a hidden behaviour |
| 2 | Where two promotions compete, the configured priority decides which applies |
| 3 | The applied promotions are itemised in the totals breakdown so the customer and the team can see what was applied |
| 4 | Given stacking is disallowed, when a second promotion is attempted, then the customer is told which one applied and why the other did not |

### US-14-05 [key] Price snapshot

*As the store owner, I want a past order to keep its own prices forever, so that changing a price today never rewrites history.*

**Traceability:** BR-005, ENT-08, AC-09. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Every order item stores the price charged at the moment of order, together with the discount applied |
| 2 | Given a product price is changed, when a prior order is viewed, then it shows the original price |
| 3 | Reports, invoices and refunds all use the snapshot, not the current price |

### US-14-06 [key] Refund integrity

*As the store owner, I want the system to make an over refund impossible, so that a mistake cannot cost more than the sale.*

**Traceability:** BR-006, AC-10. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A refund cannot exceed the amount actually paid for that item or order after discounts |
| 2 | Cumulative partial refunds cannot exceed the same limit |
| 3 | An attempt to exceed it is refused with a clear message, and the attempt is logged |

### US-14-07 [key] Stock returns on cancellation

*As the store owner, I want cancelled orders to release their stock, so that the catalogue does not show as sold out when it is not.*

**Traceability:** BR-007, ORD-08, ADM-90. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A cancelled order releases its stock back under the agreed reservation policy |
| 2 | A failed payment releases reserved stock under the same policy |
| 3 | The reservation policy is documented, including how long stock is held for an unpaid order |

### US-14-08 No negative totals

*As the store owner, I want discounts never to produce a negative total, so that no combination of offers can pay a customer to order.*

**Traceability:** BR-010. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Given any combination of promotions, when totals are calculated, then the order total is never below zero |
| 2 | Where a discount would exceed the order value, it is capped at the order value |

### US-14-09 Merchandising controls

*As the store owner, I want to promote specific products without editing code, so that merchandising is a daily activity.*

**Traceability:** MER-01 to MER-05, ADM-42, ADM-43, SSC-22, SSC-23. **Scope:** P1. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | Badges of New, Trending, Limited and Best Seller can be assigned per product |
| 2 | A product can be flagged featured, and the flag affects home page and collection prominence |
| 3 | Products can be ordered manually within categories and collections |
| 4 | Related products can be assigned per product |

---

# EPIC E15: THE OPERATIONS CONSOLE

**What this epic delivers.** The single screen the team opens every morning. Annex A Section G10 is an added contract commitment and is written as an exhaustive list: the operational capability is what that section contains, and anything not in it is a Change Request (EX-27).

> **This epic is the acceptance test for OBJ-05.** If the client must contact a developer to change a price, add a product or run an offer, the admin panel has failed regardless of what the feature list says.

### US-15-01 [key] The Operations Console

*As the store owner, I want one page that tells me what needs doing today, so that nothing is missed because it lived on a different screen.*

**Traceability:** ADM-140, ADM-145. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A dedicated admin landing page combines the outstanding work of the day in one view |
| 2 | The page is organised around jobs to be done, not around database entities |
| 3 | Quick actions can be performed from the console without navigating away |
| 4 | The console respects the role of the person viewing it, showing only what that role may act on |

### US-15-02 [key] Orders requiring action queue

*As the warehouse, I want a work queue I can clear top to bottom, so that I know when the day is done.*

**Traceability:** ADM-141, ADM-12. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The queue contains new, unpaid, ready to dispatch and failed delivery orders |
| 2 | The queue is ordered so it can be worked top to bottom |
| 3 | Acting on an order removes it from the queue without a manual refresh |
| 4 | A returned to origin order appears here for a decision, per US-09-06 |

### US-15-03 Stock requiring attention

*As the store owner, I want to see catalogue problems before customers do, so that we are not selling what we cannot ship or hiding what we can.*

**Traceability:** ADM-142, ADM-10, ADM-11, ADM-75. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The panel shows low stock, out of stock, products missing an image, and unpublished products |
| 2 | Each entry links directly to the product for correction |
| 3 | The low stock threshold used is the one configured per SKU or globally |

### US-15-04 Today summary

*As the store owner, I want the day in four numbers, so that I know without running a report.*

**Traceability:** ADM-143, ADM-01, ADM-02, ADM-03. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Today orders, revenue, units and pending dispatch are shown |
| 2 | Revenue shown is consistent with the sales report for the same period |
| 3 | Where cash on delivery is in use, collected is not presented as received, per US-09-07 |

### US-15-05 Saved views

*As the store owner, I want to save a filtered list I use daily, so that I am not rebuilding the same filter every morning.*

**Traceability:** ADM-144. **Scope:** P1-L. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | Any filtered list can be saved and reopened in one click |
| 2 | Saved views belong to the user who created them |
| 3 | A saved view whose filter is no longer valid explains itself rather than erroring |

### US-15-06 Add a product in under three minutes

*As the store owner, I want adding a simple product to be genuinely quick, so that new stock goes live the day it arrives.*

**Traceability:** ADM-146, ADM-153. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A simple product with title, price, stock, image and category can be created and published in under three minutes by a trained operator |
| 2 | The three minute figure is measured during the Stage 4 gate by the client team, not by the developer |
| 3 | An existing product can be duplicated as a starting point |
| 4 | Required fields are the minimum needed to publish, and optional fields do not block saving |

### US-15-07 [key] Inline edit grid

*As the store owner, I want to change price and stock across many products on one screen, so that a price update is one task rather than fifty.*

**Traceability:** ADM-147. **Scope:** P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Price and stock are editable across many products on one screen, without opening each product |
| 2 | Changes are saved together and reported as a set, with any failures listed individually |
| 3 | Every change is written to the price and stock change history with user and timestamp |
| 4 | The reduced boundary is explicit: **full spreadsheet style editing of every field is not included**, per Annex A section 1.2. The grid covers price and stock |

### US-15-08 Bulk order operations

*As the warehouse, I want to act on many orders at once, so that dispatch scales with volume.*

**Traceability:** ADM-154, ADM-155, ADM-156, SHIP-10, SHIP-11. **Scope:** P1, with carrier dispatch and label printing at P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Order status can be changed in bulk, with invalid transitions rejected per order rather than failing the whole action |
| 2 | Many orders can be dispatched to the carrier at once, with airway bills printed together |
| 3 | Packing slips can be printed in bulk |
| 4 | A partial failure reports exactly which orders failed and why, and is safe to retry for those only |

### US-15-09 Bulk product operations

*As the store owner, I want to change many products at once, so that a seasonal change is not a week of clicking.*

**Traceability:** ADM-148, ADM-149, ADM-150, ADM-151, ADM-49. **Scope:** P1, with category assignment and combined bulk update at P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Price can be changed by amount or percentage across a selection |
| 2 | Stock can be set or adjusted across a selection |
| 3 | Products can be published, unpublished or archived in bulk |
| 4 | Products can be assigned to a category in bulk |
| 5 | Every bulk change is written to the audit log as one action with its affected set |

### US-15-10 Find anything quickly

*As the team, I want to find a product or an order in seconds, so that a customer on the phone is not waiting.*

**Traceability:** ADM-152, ADM-157, ADM-85. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Any product is found by SKU, title or barcode |
| 2 | Any order is found by customer phone number |
| 3 | Orders are also searchable by order number, customer name, email, status, payment state, date and carrier |
| 4 | Search returns results in a time the client team accepts during the Stage 4 gate |

### US-15-11 Admin language and mobile use

*As the team, I want a consistent admin in one language and usable on a phone for quick checks, so that training is simple and the shop floor is not tied to a desk.*

**Traceability:** ADM-159, ADM-160, HND-03. **Scope:** P1, with mobile admin at P1-L. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | The administrative interface is in English, consistently across standard screens and the custom Operations Console |
| 2 | The admin is usable on a mobile device for order status checks and stock updates |
| 3 | The administrator guide and the training session are delivered in Arabic, covering the English interface |
| 4 | An Arabic administrative interface is not included, being P2 |

---

# EPIC E16: PRODUCT AND CATALOGUE MANAGEMENT

**What this epic delivers.** Everything about a product, and the classification structures it sits in. Combined with E22, it is the second half of OBJ-05.

### US-16-01 Product lifecycle

*As the store owner, I want to create, change, clone and retire products, so that the catalogue reflects what we actually sell.*

**Traceability:** ADM-20, ADM-21, ADM-153, SSC-26. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Products can be created, edited, archived and cloned |
| 2 | Draft, published and scheduled states are supported |
| 3 | An archived product disappears from the storefront but remains on past orders |
| 4 | A scheduled product publishes at its scheduled time without intervention |

### US-16-02 [key] Every product field is editable

*As the store owner, I want to change any part of a product myself, so that I never wait for a developer to fix a description.*

**Traceability:** SSC-20, ADM-22 to ADM-27, ADM-29 to ADM-45, SSC-29. **Scope:** P1, with compare at price, weight and dimensions, tax class and shipping flags at P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Name, description, specifications, images, price, stock, category, brand, attributes and SEO fields are each editable individually |
| 2 | Images and video can be added, reordered and given alt text |
| 3 | Regular price, sale price, compare at price and product cost are all recordable |
| 4 | Badges, featured flag, collection assignment and related products are set per product |
| 5 | Given a product is saved, when the storefront is reloaded, then the change is live immediately |

### US-16-03 [key] Product content in both languages

*As the store owner, I want to maintain the Arabic and English versions of a product separately, so that neither is a machine translation of the other.*

**Traceability:** SSC-21, FIX-04, CR-15. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Product name, description and specifications are maintained independently in English and Arabic |
| 2 | The storefront shows the content for the active language |
| 3 | A product missing content in one language is reportable, so gaps can be found before launch |

### US-16-04 Product variants

*As the store owner, I want each size and colour to have its own code, price and stock, so that the warehouse and the storefront agree.*

**Traceability:** ADM-30 to ADM-35, ENT-02, PDP-05. **Scope:** P1, with weight and dimensions at P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Variant attributes are definable per product |
| 2 | SKU, barcode, price and stock are held per variant |
| 3 | Weight and dimensions are recordable per variant |
| 4 | A variant with no stock behaves per US-04-03 on the storefront |

### US-16-05 [key] Product cost captured from day one

*As the store owner, I want cost recorded from the first day, so that margin reporting can later be built on real history rather than estimates.*

**Traceability:** ADM-27, RPT-11. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A cost field exists on every product and variant and is populated during catalogue load |
| 2 | Cost is visible only to roles with financial permission |
| 3 | Cost is imported by the migration tool where the source file carries it |
| 4 | Profitability and margin reporting itself is P2 and is not delivered here. The data it needs is |

> **The cost basis is an open decision, OD-12:** purchase price only or landed cost, and who enters it. It blocks catalogue load.

### US-16-06 SEO fields per product

*As the marketing team, I want to control how a product appears in search, so that the catalogue can be optimised without a developer.*

**Traceability:** ADM-38 to ADM-41, SSC-27, MKT-12. **Scope:** P1. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | Slug, meta title, meta description, canonical and robots override are editable per product |
| 2 | The same fields are editable per category and per page |
| 3 | Changing a slug offers to create a redirect from the old one, per US-25-05 |

### US-16-07 Bulk import and export

*As the store owner, I want to move the catalogue in and out in bulk, so that I am never locked in.*

**Traceability:** ADM-47, ADM-48, ADM-161, MIG-25. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Products import in bulk through the tooling in Epic E23 |
| 2 | Products export in bulk in the same mapped format |
| 3 | An export can be re imported without loss |

### US-16-08 Price and stock change history

*As the store owner, I want to know who changed a price and when, so that a mistake can be found rather than argued about.*

**Traceability:** ADM-50, ROLE-10, ADM-132. **Scope:** P1-L. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | Every price and stock change records the user, the previous value, the new value and the timestamp |
| 2 | The history is visible from the product |
| 3 | Bulk changes are recorded with the same detail per product |

### US-16-09 Categories, brands and collections

*As the store owner, I want to organise the catalogue the way customers shop, so that browsing works.*

**Traceability:** ADM-55, ADM-56, ADM-57, ENT-03, IA-03 to IA-05. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Categories and subcategories are managed with ordering, images and SEO fields |
| 2 | Brand pages carry a logo, description and SEO fields |
| 3 | Manually curated collections are created and ordered from admin |
| 4 | All three are maintained in both languages |
| 5 | Rules based automatic collections are not included, being P2 |

---

# EPIC E17: INVENTORY

**What this epic delivers.** Knowing what is in stock, and being told when it is not.

### US-17-01 Stock levels

*As the warehouse, I want on hand and available to be distinct numbers, so that I am not shipping stock that is already promised.*

**Traceability:** ADM-70, ADM-72, ADM-71. **Scope:** P1, with reserved stock at P1-L, S2. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Stock on hand and stock available are both shown |
| 2 | Available stock reflects the reservation policy in force |
| 3 | Reserved stock tracking as a separate figure is second release. On hand and available are live at launch |

### US-17-02 Stock adjustments

*As the warehouse, I want to correct stock with a reason attached, so that a discrepancy can be investigated later.*

**Traceability:** ADM-73, ENT-04. **Scope:** P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Stock can be adjusted with a reason recorded |
| 2 | The user and timestamp are recorded with every adjustment |
| 3 | The adjustment is written to the audit log |
| 4 | A full inventory transaction record per movement is second release, per ENT-04 |

### US-17-03 Low stock thresholds and alerts

*As the store owner, I want to be told before something sells out, so that I can reorder in time.*

**Traceability:** ADM-74, ADM-75, ADM-10, ADM-11. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A low stock threshold can be set globally and overridden per SKU |
| 2 | Low and out of stock products appear in the Operations Console stock panel |
| 3 | The alert clears automatically when stock is replenished |

### US-17-04 Bulk stock import

*As the store owner, I want to update stock from a file, so that a stock take is one upload.*

**Traceability:** ADM-77, MIG-14. **Scope:** P1. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | Stock quantities can be imported in bulk by SKU |
| 2 | The dry run in US-23-06 applies: the operator sees what will change before it changes |
| 3 | An unmatched SKU is reported rather than silently creating a product |

> **Not included in this epic:** suppliers and purchase orders (ADM-79, P2), multi warehouse (ADM-78, P3), and demand forecasting (ADM-80, OUT).

---

# EPIC E18: ORDER MANAGEMENT

**What this epic delivers.** The admin side of an order, from arrival to closure.

### US-18-01 Order search and filtering

*As customer service, I want to find any order from whatever the customer tells me, so that the call is short.*

**Traceability:** ADM-85, ADM-157. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Orders are searchable and filterable by order number, customer, phone, email, status, payment state, date and carrier |
| 2 | Phone number search returns the order directly |
| 3 | Filters combine, and the resulting list can be saved as a view per US-15-05 |

### US-18-02 Order detail

*As customer service, I want everything about an order on one screen, so that I do not have to piece it together.*

**Traceability:** ADM-86, ENT-08, ADM-88. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The screen shows items, prices as charged, discounts, customer, address, payment, shipment, notes and a timeline |
| 2 | The timeline records every status change with actor and timestamp |
| 3 | Internal notes can be added and are never visible to the customer |
| 4 | Financial detail is visible only to roles with financial permission |

### US-18-03 Status updates

*As the warehouse, I want to move an order forward, so that the customer sees progress.*

**Traceability:** ADM-87, ORD-01 to ORD-14. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Status can be updated, with the transition validated against the status model |
| 2 | An invalid transition is refused with a reason |
| 3 | A status change that triggers a notification sends exactly one |

### US-18-04 Shipments from the order

*As the warehouse, I want to create the shipment from the order, so that tracking is attached where it belongs.*

**Traceability:** ADM-89, SHIP-03, SHIP-09. **Scope:** P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A shipment can be created and attached to the order with its tracking number |
| 2 | The tracking number is visible to the customer |
| 3 | A shipment can be corrected where the carrier returns a wrong code |

### US-18-05 Cancel an order

*As customer service, I want to cancel with a reason, so that the record explains itself later.*

**Traceability:** ADM-90, ORD-08, BR-007. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | An order can be cancelled with a reason recorded |
| 2 | Cancellation releases reserved stock |
| 3 | Cancellation of a paid order does not by itself issue a refund: the refund is a separate, permissioned action |

### US-18-06 Invoices and packing slips

*As the team, I want to print the paperwork, so that the parcel and the accounts are both correct.*

**Traceability:** ADM-92, ADM-93. **Scope:** P1-L. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | An invoice can be printed or downloaded from the order |
| 2 | A packing slip can be printed or downloaded |
| 3 | Both carry the legal entity details supplied under CR-12 |

### US-18-07 Order export

*As the store owner, I want orders in a spreadsheet, so that I can work outside the system when I need to.*

**Traceability:** ADM-158. **Scope:** P1. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | Orders export to a spreadsheet with selectable columns |
| 2 | The export honours the current filter |
| 3 | Exporting is permission controlled and is written to the audit log |

> **Manual order creation from admin, for telephone orders, is not included, being P2** (ADM-94).

---

# EPIC E19: CUSTOMERS

**What this epic delivers.** The customer record the team works from.

### US-19-01 Customer profile

*As customer service, I want the customer history in front of me, so that I can help without asking them to repeat themselves.*

**Traceability:** ADM-100, ENT-05. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The profile shows contact details, addresses and order history |
| 2 | Returns and refunds against the customer are visible from the profile |
| 3 | Access is permission controlled, and the Marketing role cannot reach personal data beyond campaign needs |

### US-19-02 Internal notes on a customer

*As customer service, I want to record context, so that the next person is not starting from nothing.*

**Traceability:** ADM-103. **Scope:** P1-L. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | Notes can be added to a customer record |
| 2 | Notes are never visible to the customer |
| 3 | Each note records its author and timestamp |

### US-19-03 Disable or suspend an account

*As the store owner, I want to stop an abusive account, so that the store is protected.*

**Traceability:** ADM-104. **Scope:** P1-L. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | An account can be disabled or suspended with a reason recorded |
| 2 | A disabled account cannot sign in and is told to contact support |
| 3 | The action is permission controlled and written to the audit log |

### US-19-04 Customer metrics and export

*As the store owner, I want spend and behaviour figures per customer, so that I can see who matters.*

**Traceability:** ADM-101, ADM-105, NFR-12a. **Scope:** P1-L. **Stage:** S2. **Priority:** Later

| # | Acceptance criterion |
|---|---|
| 1 | Delivered in the second release. Profiles and order history are live at launch |
| 2 | Total spend, average order value, returns and coupon usage are shown per customer |
| 3 | Customer data export under privacy controls is delivered with it; at launch, requests are handled manually |

> **Customer segments, being new, returning, VIP and dormant, are not included, being P2** (ADM-102).

---

# EPIC E20: RETURNS AND REFUNDS ADMINISTRATION

**What this epic delivers.** The staff half of journey J6.

### US-20-01 [key] Return case queue

*As customer service, I want new return cases in a queue, so that none is forgotten.*

**Traceability:** ADM-120, ADM-13. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | New return cases appear in a queue |
| 2 | The queue shows how long each case has been waiting |
| 3 | Pending returns also appear on the Operations Console |

### US-20-02 Review a return case

*As customer service, I want the evidence and the order together, so that I can decide once.*

**Traceability:** ADM-121, RET-01, RET-02. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The case shows the reason, the description, the attached photographs and the order history together |
| 2 | The eligibility check result is shown, including the window used |

### US-20-03 [key] Decide a return

*As customer service, I want to approve, reject or partially approve with a recorded reason, so that the decision is defensible.*

**Traceability:** RET-04, ADM-122, NOTF-07. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A case can be approved, rejected or partially approved |
| 2 | A reason is recorded in every case, including approvals |
| 3 | The decision updates the return status visible to the customer and sends the notification |
| 4 | The decision is permission controlled and written to the audit log |

### US-20-04 Record receipt and condition

*As the warehouse, I want to record what came back and in what state, so that the refund decision is informed.*

**Traceability:** RET-06, ADM-123. **Scope:** P1, with condition recording at P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Receipt of the returned item is recorded against the case |
| 2 | The condition of the returned item is recorded |
| 3 | Disposition handling, being return to stock, damaged or discard, is not included, being P2 |

### US-20-05 [key] Refund from the return case

*As the accountant, I want to refund from the case with the reference captured, so that the money and the record match.*

**Traceability:** RET-08, ADM-124, PAY-07, PAY-08, BR-006. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A full or partial refund is initiated from the return case |
| 2 | The refund goes to the original payment method and the payment reference is recorded |
| 3 | The refund cannot exceed the amount actually paid after discount |
| 4 | The customer is notified with the amount and the reference |

### US-20-06 Return audit trail

*As the owner, I want the whole case history preserved, so that a dispute has a record.*

**Traceability:** ADM-125, ENT-12, ENT-13. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Every action on the case is recorded with actor and timestamp |
| 2 | The trail includes the request, the decision, the receipt, the condition and the refund |
| 3 | The trail cannot be edited |

---

# EPIC E21: STAFF USERS, ROLES AND AUDIT

**What this epic delivers.** Who can do what, and the record of what they did. Annex A ROLE-09 and ROLE-10 apply to every admin story in this specification.

### US-21-01 Staff users and roles

*As the owner, I want to give each person exactly the access their job needs, so that a mistake has a small blast radius.*

**Traceability:** ADM-130, ROLE-01 to ROLE-09, ENT-16. **Scope:** P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Staff users can be created and assigned one of the defined roles |
| 2 | Permissions are granular within the role definitions in Annex A Section B |
| 3 | No staff member receives full admin by default |
| 4 | Given a staff member without permission, when they attempt a refund, a price edit or a role change, then the action is refused and the attempt is recorded, per AC-11 |
| 5 | The Accountant role has access to the Phase 1 financial operations at launch |

### US-21-02 Two factor authentication for admin

*As the owner, I want a second factor on admin accounts, so that a stolen password is not enough.*

**Traceability:** ADM-131, NFR-05. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Two factor authentication is available for admin accounts |
| 2 | It can be required for the roles the owner chooses |
| 3 | Recovery from a lost second factor is documented and controlled |

### US-21-03 Audit log

*As the owner, I want a searchable record of sensitive actions, so that a question about what happened has an answer.*

**Traceability:** ADM-132, ADM-133, ADM-134, ROLE-10, ENT-17, MKT-25. **Scope:** P1-L, with the deletion protection at P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Refunds, price changes, stock changes and permission changes are logged with actor, action, entity, before and after values, and timestamp |
| 2 | Logins and significant security events are recorded |
| 3 | The log is searchable by actor, entity, action and date |
| 4 | The audit trail cannot be deleted from the interface by ordinary users |
| 5 | Third party administrative actions appear in the same log |
| 6 | The reduced boundary is explicit: forensic reporting and log analytics are not included |

---

# EPIC E22: SELF SERVICE STOREFRONT CONTROL

**What this epic delivers.** The second half of OBJ-05, and the subject of AC-20. Annex A Section G11 is an added contract commitment beyond the client requirements baseline recorded at OD-32.

### US-22-01 [key] Nothing a customer reads is locked in code

*As the store owner, I want every storefront text, image and link editable from admin, so that the store is mine to change.*

**Traceability:** SSC-01, NFR-04a, AC-20. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Every text, image and link a customer can see is editable from admin |
| 2 | No customer facing string requires a code change to correct |
| 3 | The client, unaided, edits a product and a storefront section, and both changes appear correctly on the English and the Arabic storefront, per AC-20 |

### US-22-02 Home page sections

*As the store owner, I want to run the home page, so that the front of the store follows the season.*

**Traceability:** SSC-02, ADM-59, ADM-61, HOME-10. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Section content, images, calls to action and links are editable |
| 2 | The order of sections is changeable |
| 3 | Changes are independent per language |

### US-22-03 Show and hide sections

*As the store owner, I want to switch a section off without losing it, so that a seasonal block can return next year.*

**Traceability:** SSC-03, ADM-60. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Any home page section can be hidden and shown again |
| 2 | Hiding a section does not delete its content |
| 3 | A hidden section leaves no gap on the storefront |

### US-22-04 Category and brand page content

*As the marketing team, I want each category and brand to have its own presentation, so that the store does not look generic.*

**Traceability:** SSC-04, SSC-05, ADM-55, ADM-56, PLP-20. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Banner, description and hero imagery are editable per category |
| 2 | Brand page content is editable per brand |
| 3 | Both are maintained in both languages |

### US-22-05 Collection content and ordering

*As the merchandiser, I want to control what is in a collection and in what order, so that the best product is first.*

**Traceability:** SSC-06, ADM-57, MER-03, MER-04. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Products can be added to and removed from a collection |
| 2 | Product order within the collection is set manually and is respected on the storefront |
| 3 | Collection page content is editable in both languages |

### US-22-06 Navigation and footer

*As the store owner, I want to change the menu and the footer, so that a new category is reachable the day it exists.*

**Traceability:** SSC-07, SSC-08, NAV-07. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Header navigation and mega menu structure are managed from admin |
| 2 | Footer content, links and columns are managed from admin |
| 3 | Both are maintained in both languages, and a link missing in one language is reported |

### US-22-07 Static and policy pages

*As the store owner, I want to change policy text myself, so that a legal update is not a development task.*

**Traceability:** SSC-10, ADM-63, CMS-09. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Static pages, policy pages and the FAQ are editable without code |
| 2 | Edits are independent per language |
| 3 | The return policy page and the configured return window stay consistent, per US-13-02 |

### US-22-08 Promotional banners and campaign pages

*As the marketing team, I want to build an offer page, so that a campaign does not need a release.*

**Traceability:** SSC-09, SSC-11, MKT-20, IA-32. **Scope:** P1-L. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | Promotional banners can be created, scheduled, targeted to a page and removed |
| 2 | A campaign or offer landing page can be created from the existing components without a developer |
| 3 | Campaign pages exist in both languages |
| 4 | The boundary is explicit: creating an entirely new type of page layout or component that does not exist in the design system is a Change Request |

### US-22-09 [key] Content maintained independently in both languages

*As the store owner, I want the Arabic store to be genuinely Arabic, so that it does not read as a translation of an English page.*

**Traceability:** SSC-12, FIX-04, CR-15. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Every editable content area is maintained separately in English and Arabic |
| 2 | Editing one language never overwrites the other |
| 3 | Missing content in one language is reportable before launch |

> **The mechanism is the developer responsibility. The content is the client responsibility** (CR-15). A multilingual store with content in only one language is not a multilingual store, and this is the most common reason such launches slip.

### US-22-10 Preview before publish

*As the store owner, I want to see a change before customers do, so that a mistake is caught privately.*

**Traceability:** SSC-13. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Any content change can be previewed as it will appear |
| 2 | Preview covers both languages |
| 3 | A previewed change is not visible to customers until it is published |

### US-22-11 Revert a content change

*As the store owner, I want to undo a content edit, so that a bad change is recoverable.*

**Traceability:** SSC-14. **Scope:** P1-L. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | A content change can be reverted to its previous version |
| 2 | The revert is recorded with actor and timestamp |

---

# EPIC E23: CATALOGUE MIGRATION AND IMPORT

**What this epic delivers.** Journey J8. Annex A Section U is an added contract commitment: the client requirements document asked for CSV and XLSX import, and everything beyond that in this epic was added deliberately because moving a live catalogue with a plain spreadsheet import is how stores end up with duplicated products and corrupted Arabic.

### US-23-01 File formats accepted

*As the store owner, I want the tool to read what Amazon actually gives me, so that I am not reformatting files first.*

**Traceability:** MIG-01. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | CSV, tab delimited and Excel files are accepted |
| 2 | An unsupported file is refused with a clear explanation, not a stack trace |
| 3 | A very large file is accepted and processed per US-23-08 |

### US-23-02 [key] Encoding detection verified against Arabic

*As the store owner, I want Arabic to survive the import, so that the catalogue is not corrupted on arrival.*

**Traceability:** MIG-02. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Character encoding is detected automatically |
| 2 | Given a file containing Arabic product text in the encoding Amazon commonly produces, when it is imported, then the Arabic text is stored and displayed correctly |
| 3 | Given encoding cannot be determined confidently, when the file is uploaded, then the operator is asked rather than a guess being applied silently |

### US-23-03 [key] Column mapping

*As the store owner, I want to tell the tool what each column means, so that it works with any file, not one report format.*

**Traceability:** MIG-04, MIG-10, MIG-11, MIG-12. **Scope:** P1, with brand mapping at P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Each column in the source file is mapped to a store field through an interface |
| 2 | Source attributes are mappable to store attributes |
| 3 | A category mapping table can be built and is reusable |
| 4 | An unmapped required field blocks the import with a clear message |

### US-23-04 Saved mapping profiles

*As the store owner, I want to map once and reuse it, so that the second import is faster than the first.*

**Traceability:** MIG-05. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A mapping can be saved with a name and reused on a later import |
| 2 | A saved mapping applied to a file with different columns reports the mismatch rather than importing wrongly |

### US-23-05 Preview

*As the store owner, I want to see the first rows interpreted, so that I catch a mapping error before it matters.*

**Traceability:** MIG-03. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The first rows are shown as they will be interpreted, before anything is written |
| 2 | The preview shows the mapped field names, not the raw column headers |

### US-23-06 [key] Dry run

*As the store owner, I want a full report of what would happen, so that I never find out by breaking the catalogue.*

**Traceability:** MIG-06, MIG-08. **Scope:** P1, with per field overwrite control at P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A dry run produces a validation report listing what will be created, what will be updated, and what will fail and why |
| 2 | Nothing is written to the catalogue during a dry run |
| 3 | The operator can control which fields an update is permitted to overwrite |
| 4 | The report is downloadable |

### US-23-07 [key] Product variations

*As the store owner, I want parent and child listings to become proper variable products, so that the storefront shows sizes rather than duplicates.*

**Traceability:** MIG-09. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Parent and child listings in the source file are converted into variable products with variants |
| 2 | Variant attributes, SKU, price and stock are populated from the source |
| 3 | A child row whose parent is missing is reported as an error rather than imported as a standalone product |

### US-23-08 Background processing

*As the store owner, I want a big import to finish, so that the catalogue load is not a series of failures.*

**Traceability:** MIG-20. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Imports run in the background and do not fail on large files |
| 2 | Progress is visible while the import runs |
| 3 | An interrupted import can be resumed or safely re run per US-23-09 |

### US-23-09 [key] Safe re runs

*As the store owner, I want to run the same file again safely, so that keeping the catalogue current is routine.*

**Traceability:** MIG-07, AC-17. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Given a file that has already been imported, when it is imported again, then existing products are updated by SKU and no duplicates are created |
| 2 | The behaviour holds for variable products as well as simple products |
| 3 | The client re runs the tool on a changed file at the Stage 5 gate and confirms it updates rather than duplicates |

### US-23-10 Error report

*As the store owner, I want to fix the rows that failed, so that a partial import is not a dead end.*

**Traceability:** MIG-19. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A row level error report is downloadable after every import |
| 2 | Each error names the row, the field and the reason |
| 3 | The corrected file can be re uploaded and imported |

### US-23-11 Import history

*As the store owner, I want to know what was imported and by whom, so that an unexpected change can be traced.*

**Traceability:** MIG-18, ENT-19. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Every import run records the file, the mapping used, row counts, the outcome, the actor and the timestamp |
| 2 | The history is visible in admin |
| 3 | Import undo and scheduled recurring imports are not included, being P2 |

### US-23-12 Fields imported and export

*As the store owner, I want the data that matters to come across, and to be able to take it out again.*

**Traceability:** MIG-13, MIG-14, MIG-15, MIG-16, MIG-17, MIG-25. **Scope:** P1, with SEO fields, images and generated alt text at P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Price, sale price and cost are imported |
| 2 | Stock quantity is imported |
| 3 | SEO fields are imported where present |
| 4 | Images are downloaded and stored on the store server, subject to the rights confirmation at CR-08 |
| 5 | Alt text is generated from the product title where none is supplied |
| 6 | The catalogue exports in the same mapped format |

> **Excluded from this epic, and recorded as excluded:** live Amazon API synchronisation (MIG-23, EX-10) and Amazon order history and customer data import (MIG-24, EX-23). The second is excluded for a reason worth repeating: Amazon does not release complete buyer contact information to sellers, and importing marketplace customer records raises consent obligations under Egyptian data protection law.

---

# EPIC E24: DASHBOARD AND REPORTS

**What this epic delivers.** The numbers. Several reports are second release, and each says so in its own story rather than being discovered missing.

### US-24-01 Dashboard figures

*As the store owner, I want the headline numbers on opening the admin, so that I know how the business is doing.*

**Traceability:** ADM-01 to ADM-04, ADM-08, ADM-09, ADM-15. **Scope:** P1, with average order value and charts at P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Revenue, orders, units sold and average order value are shown |
| 2 | Charts are available by day, week, month and a custom range |
| 3 | Top products are listed |
| 4 | Quick actions for adding a product, creating a coupon and viewing pending orders are present |
| 5 | Figures reconcile with the sales report for the same period |

### US-24-02 Operational panels on the dashboard

*As the store owner, I want problems surfaced rather than found, so that the dashboard is useful rather than decorative.*

**Traceability:** ADM-10 to ADM-13, ADM-06. **Scope:** P1, with the refunds and returns panel at P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Low stock, out of stock, orders requiring action and pending returns and refunds are all visible |
| 2 | Each links to the screen where the work is done |

### US-24-03 Sales report

*As the accountant, I want the sales figures broken down, so that I can reconcile them.*

**Traceability:** RPT-01, ROLE-06. **Scope:** P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Revenue, net sales, orders, units, average order value, discounts and shipping are reported |
| 2 | The report is filterable by date range |
| 3 | The report is exportable |
| 4 | Access is limited to roles with financial permission |

### US-24-04 Inventory report

*As the store owner, I want stock health in one report, so that purchasing decisions have a basis.*

**Traceability:** RPT-10. **Scope:** P1-L. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | Low stock, out of stock, dead stock and adjustments are reported |
| 2 | The report is filterable and exportable |

### US-24-05 Funnel and traffic reporting

*As the marketing team, I want the funnel and the traffic sources, so that spend can be judged.*

**Traceability:** RPT-08, RPT-09, ADM-07, INT-09. **Scope:** P1-L, delivered through the analytics platform. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | Session to product to cart to checkout to purchase is measurable in the analytics platform |
| 2 | Traffic by channel, source and campaign is measurable |
| 3 | Conversion rate is available through the analytics platform rather than as an admin figure at launch |
| 4 | The event set in Epic E26 is what makes this true, and is verified with it |

### US-24-06 Second release reports

*As the store owner, I want to know exactly which reports arrive after launch, so that their absence is not a surprise.*

**Traceability:** RPT-03, RPT-07, ADM-05, ADM-07, ADM-101. **Scope:** P1-L. **Stage:** S2. **Priority:** Later

| # | Acceptance criterion |
|---|---|
| 1 | The product performance report, the search terms report, the dashboard customer figures and customer lifetime metrics are delivered in the second release |
| 2 | The data those reports need is captured from launch, so that they are built on real history |

> **Not included, and recorded as not included:** profitability and margin reporting (RPT-02, P2), customer lifetime value (RPT-04, P2), promotion reporting (RPT-05, P2) and returns analytics (RPT-06, P2). **The cost field is captured from day one** (ADM-27, RPT-11) so that margin reporting can later be built against accurate history rather than estimates.

---

# EPIC E25: MARKETING ENABLEMENT, SEO AND THIRD PARTY ACCESS

**What this epic delivers.** Everything a media buyer or agency needs, and the controls that stop them breaking the store. Running campaigns is excluded (EX-18); giving them the tools is contracted.

### US-25-01 Measurement and advertising tags

*As the marketing team, I want the tracking in place on day one, so that launch traffic is measurable.*

**Traceability:** MKT-01, MKT-02, MKT-03, INT-09, INT-10. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Google Analytics 4 and Tag Manager are installed with the full ecommerce event set |
| 2 | The Meta pixel is installed |
| 3 | The TikTok pixel is installed with standard ecommerce events |
| 4 | Events fire correctly in both languages and are verified before launch |
| 5 | Which advertising platforms are live at launch follows the client decision at OD-21 |

### US-25-02 [key] Product feeds

*As the media buyer, I want product feeds, so that dynamic retargeting and shopping listings are possible at all.*

**Traceability:** MKT-05, MKT-06, INT-12. **Scope:** P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A Meta product catalogue feed is produced and validates against the platform requirements |
| 2 | A Google Merchant Center feed is produced and validates |
| 3 | Feeds refresh on a schedule and reflect current price and availability |
| 4 | A product excluded from advertising can be excluded from the feed |

### US-25-03 [key] Campaign attribution through to the order

*As the store owner, I want revenue attributable to a campaign, so that advertising spend can be judged on sales rather than clicks.*

**Traceability:** MKT-09. **Scope:** P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Campaign tracking parameters are preserved from landing through to the order record |
| 2 | The order record carries the attribution data |
| 3 | Attribution survives a guest checkout and a sign in during checkout |

### US-25-04 Consent management

*As the store owner, I want tracking to respect consent, so that measurement does not create a legal problem.*

**Traceability:** MKT-10, NFR-12, R-04. **Scope:** P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A cookie and consent banner is present and configurable |
| 2 | Tracking is consent aware: tags respect the recorded consent state |
| 3 | Consent choices are recorded |

### US-25-05 [key] Redirect manager

*As the marketing team, I want to create redirects myself, so that a changed URL does not lose its traffic.*

**Traceability:** MKT-13, MKT-14. **Scope:** P1, with the missing page log at P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Permanent redirects are created from admin without a developer |
| 2 | A redirect loop or a redirect to a missing page is refused with an explanation |
| 3 | Missing page errors are logged and visible in admin |

### US-25-06 Technical SEO foundations

*As the marketing team, I want the technical basics correct, so that the store can rank.*

**Traceability:** MKT-12, MKT-15, MKT-16, MKT-17, MKT-18, NFR-03, ADM-38 to ADM-41. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Clean URLs, meta titles and descriptions, canonical tags, breadcrumbs and alt text are in place |
| 2 | An XML sitemap and a robots file are produced and controllable from admin |
| 3 | Structured data for product, organisation and breadcrumbs is emitted and validates |
| 4 | Search Console and Bing Webmaster verification are in place |
| 5 | hreflang tags target the English and Arabic versions of each page correctly |

### US-25-07 [key] Third party access controls

*As the store owner, I want an agency to be unable to break the store, so that marketing help does not become a maintenance problem.*

**Traceability:** MKT-21, MKT-22, MKT-23, MKT-24, MKT-25, ROLE-05. **Scope:** P1, with the personal data limit, staging access and audit visibility at P1-L. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The Marketing role cannot install software, edit templates or edit files |
| 2 | The Marketing role cannot reach customer personal data beyond campaign needs |
| 3 | Tracking scripts are deployed through Google Tag Manager, and no third party edits site code directly |
| 4 | Third parties receive staging access for testing before any production change |
| 5 | Every third party administrative action appears in the audit log |

### US-25-08 Third Party Access Protocol

*As the store owner, I want a document I can hand to an agency, so that the rules are agreed before access is given.*

**Traceability:** MKT-26. **Scope:** DLV. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | A short written protocol is delivered, stating the role granted, the Tag Manager rule, the change request rule, the notification duty and the chargeability of repairing third party damage |
| 2 | It is issued in a form the client can forward to an agency directly |

> **A blog or content hub is expressly excluded** (MKT-19, EX-13), recorded from the client position at OD-22. Publishing articles would cost nothing extra in platform terms; designing article, archive and category templates to the premium standard would be a Change Request.

---

# EPIC E26: ANALYTICS EVENTS

**What this epic delivers.** The measurement layer that Epic E24 and Epic E25 both depend on. The event plan itself is a contractual deliverable.

### US-26-01 Standard ecommerce event set

*As the marketing team, I want the whole funnel instrumented, so that every step can be measured.*

**Traceability:** EVT-01 to EVT-09, EVT-11 to EVT-14, EVT-16. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Events fire for home page viewed, item list viewed, search, product viewed, product card clicked, add to wishlist, add to cart, remove from cart and cart viewed |
| 2 | Events fire for begin checkout, add shipping info, add payment info and purchase |
| 3 | The purchase event carries items, tax, shipping and discount values |
| 4 | Registration and login events fire |
| 5 | Every event fires once per occurrence, with no duplicate on a page refresh |

### US-26-02 Secondary events

*As the marketing team, I want coupon, refund, return and zero result events, so that the less obvious behaviour is also measurable.*

**Traceability:** EVT-10, EVT-15, EVT-18, EVT-19. **Scope:** P1-L. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | Coupon applied, refund issued, zero result search and return requested events fire |
| 2 | Promotion viewed and promotion clicked events are not included, being P2 |

### US-26-03 Event tracking plan

*As the store owner, I want a document defining every event, so that a number in a report can be traced to its source.*

**Traceability:** EVT-20, DOD-05. **Scope:** DLV. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The plan defines every event, its trigger, its parameters, and the source of truth for each business metric |
| 2 | Every event in the plan is verified as firing correctly before launch |
| 3 | The plan is delivered in writing as part of the Stage 1 technical design |
| 4 | The reduced boundary is explicit: a bespoke custom event plan beyond the standard set is not included |

---

# EPIC E27: NON FUNCTIONAL REQUIREMENTS AND DELIVERABLES

**What this epic delivers.** The requirements that are not features and the documents that are not software. They are stories because they are testable and because leaving them implicit is how they get skipped.

### US-27-01 Mobile first and responsive

*As a shopper on a phone, I want the store designed for my screen, so that mobile is not an afterthought.*

**Traceability:** NFR-01, FIX-05, PLT-06, DOD-01. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Every screen is responsive across phone, tablet and desktop |
| 2 | Testing begins on mobile rather than being a later phase |
| 3 | Every screen is verified in both languages at every breakpoint |

### US-27-02 Performance

*As a shopper, I want pages to load quickly, so that I do not leave before I have seen anything.*

**Traceability:** NFR-02. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Images are optimised and lazy loaded |
| 2 | Caching and a content delivery network are in place |
| 3 | Performance is measured on the staging environment before launch and the results are recorded |
| 4 | No performance, conversion, traffic or ranking outcome is warranted, per EX-31 |

### US-27-03 Security

*As the store owner, I want current security practice applied throughout, so that the store is not an easy target.*

**Traceability:** NFR-05, NFR-06, AUTH-15, ADM-131. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | HTTPS throughout, secure password storage, role based access control, admin protection, input validation, rate limiting, secret management and dependency updates are all in place |
| 2 | No card data is stored, and the provider secure integration pattern is followed |
| 3 | The security controls are listed in the handover documentation |
| 4 | No guarantee of immunity from all future vulnerabilities is given or implied |

### US-27-04 [key] Data integrity

*As the store owner, I want money, stock and orders to stay consistent, so that a concurrent action never corrupts a record.*

**Traceability:** NFR-07, PAY-05, BR-003, BR-005, BR-006, BR-007. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Payment, order and inventory operations are transactionally safe |
| 2 | Duplicate protection holds across payments, orders and inventory |
| 3 | Concurrent purchases of the last unit of stock result in one sale, not two |
| 4 | Automated tests cover pricing, promotions, refunds, stock and duplicate order protection, per the reduced scope at DOD-04 |

### US-27-05 Backups and environments

*As the store owner, I want a tested way back, so that a bad change is recoverable.*

**Traceability:** NFR-08, NFR-09, HND-05, HND-08. **Scope:** P1. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Database and media backups run to the agreed retention |
| 2 | The restore procedure is documented and has been performed at least once, not only written |
| 3 | Development, staging and production environments exist, and risky changes are never tested on the live site |
| 4 | A rollback procedure is documented and tested before go live |

### US-27-06 Observability

*As the store owner, I want to know when something is failing, so that a problem is found before a customer reports it.*

**Traceability:** NFR-10, INT-16. **Scope:** P1-L. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | Application logs, error monitoring, integration logs and admin audit logs are in place |
| 2 | Integration failures against the payment and carrier providers are visible without reading a server log |

### US-27-07 Accessibility

*As any visitor, I want the store to be usable without a mouse and readable at normal contrast, so that it does not exclude people unnecessarily.*

**Traceability:** NFR-11. **Scope:** P1-L. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | Keyboard usability, form labels, colour contrast, image alt text and semantic structure are implemented |
| 2 | The core purchase path is completable using a keyboard alone |
| 3 | The reduced boundary is explicit: this is the accessibility scope recorded at NFR-11, not certification against a named standard |

### US-27-08 Privacy

*As the store owner, I want consent and data handling correct, so that the store is defensible under Egyptian data protection law.*

**Traceability:** NFR-12, NFR-12a, AUTH-09, R-03, R-04. **Scope:** P1, with automated request handling at P1-L, S2. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Consent is captured, the privacy policy is published, and data minimisation is applied |
| 2 | Marketing consent is separate from account creation |
| 3 | Automated data export and deletion request handling is second release. At launch, requests are handled manually |
| 4 | The hosting location decision and any authorisation it requires are the client responsibility, per R-03 and OD-27 |

### US-27-09 Browser support and scalability

*As the store owner, I want the store to work on what customers use and to cope with growth, so that success is not a failure mode.*

**Traceability:** NFR-13, NFR-14, FIX-10, OD-15. **Scope:** P1-L. **Stage:** S1. **Priority:** Should

| # | Acceptance criterion |
|---|---|
| 1 | Current Chrome, Safari, Edge and Firefox, plus mobile browsers, are supported and tested |
| 2 | The platform is provisioned against the stated assumption of up to 5,000 products and 300 orders per day, with headroom for short campaign peaks |
| 3 | Growth beyond that is absorbed by resizing infrastructure rather than re implementing the core, bounded by FIX-10 |
| 4 | No guarantee is given that a future P2 or P3 capability can be added without redesign |

### US-27-10 Pre development deliverables

*As the store owner, I want the design and the plan in writing before code is written, so that I am approving something I can see.*

**Traceability:** PRE-01 to PRE-07. **Scope:** DLV. **Stage:** S1. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | The technical design and architecture document, the technology stack proposal with reasoning and expected operating cost, the sitemap, user flows, wireframes and interface design, the milestone breakdown, and the integrations list with the accounts and costs required from the client are all delivered |
| 2 | This specification and the Feature Register together satisfy PRE-06 |
| 3 | The store operations walkthrough is performed on the actual admin environment, with the Annex A Section G10 list reviewed together and confirmed, before development begins |
| 4 | The canonical design source is English, and the Arabic layouts are the right to left localisation of that approved design, not a separate design |

### US-27-11 Handover deliverables

*As the store owner, I want to own everything at the end, so that another team could take this over.*

**Traceability:** HND-01, HND-02, HND-03, HND-04, HND-05, HND-06, HND-07, HND-08, HND-09, HND-10, HND-11, HND-12. **Scope:** DLV. **Stage:** S1 through S6. **Priority:** Must

| # | Acceptance criterion |
|---|---|
| 1 | Source code is in a repository the client owns, with a clear development history |
| 2 | Design source files, the design system and assets are delivered |
| 3 | Documentation covers setup, deployment, environment variables and integrations, and includes an Arabic administrator guide covering the English interface |
| 4 | Domain, DNS, hosting and cloud access are in the client name |
| 5 | Database schema, migrations, and backup and restore instructions are delivered |
| 6 | Payment, shipping, email and analytics accounts are under client ownership |
| 7 | The staging environment, test cases and acceptance results are delivered |
| 8 | Production deployment and a documented rollback procedure are delivered |
| 9 | The admin training session, the credentials handover and a known issues list are delivered. Training is the written Arabic administrator manual plus a live handover session, at the reduced scope recorded at HND-09 |
| 10 | The warranty period for defect correction runs from Production Go-Live, separate from new features, on the terms in the Services Agreement |
| 11 | The Third Party Access Protocol is delivered, per US-25-08 |
| 12 | The Event Tracking Plan is delivered, per US-26-03 |

---

# PART FIVE: CROSS CUTTING ACCEPTANCE

## 22. The twenty acceptance scenarios

Annex A Section N records twenty acceptance scenarios. Fifteen are the client own critical test cases from the requirements document; five were added by the developer covering returned parcels, catalogue re import, cart persistence, Google sign in and self service content management.

**These become the user acceptance testing script verbatim.** The table below is the same twenty scenarios with the story that carries each one, so that a failure in acceptance testing points immediately at the story whose criteria were not met.

| Id | Scenario | Required result | Story |
|---|---|---|---|
| AC-01 | Customer adds one item | Cart shows that adding one more unlocks free shipping | US-05-04 |
| AC-02 | Customer adds two items | Shipping becomes free per the active promotion, without a code change | US-05-04, US-14-03 |
| AC-03 | New customer registers | Welcome discount applies only if conditions are met | US-14-02 |
| AC-04 | Invalid coupon | Clear reason shown, total unchanged | US-05-06, US-07-05 |
| AC-05 | Stock runs out during checkout | Unavailable quantity not confirmed, customer informed | US-07-06 |
| AC-06 | Place Order clicked twice | Exactly one order created, no duplicate charge | US-07-07 |
| AC-07 | Duplicate payment confirmation | Handled safely with no duplicate effect | US-07-07, US-08-03 |
| AC-08 | Payment fails | Order does not incorrectly become Paid or Confirmed | US-08-04 |
| AC-09 | Price changed after a prior purchase | The earlier order retains its original price | US-14-05 |
| AC-10 | Partial refund | Cannot exceed the amount actually paid after discount | US-08-06, US-14-06 |
| AC-11 | Staff without permission | Cannot refund, or edit prices or roles | US-21-01 |
| AC-12 | English and Arabic | All core pages render correctly in both languages without layout break | US-01-01 |
| AC-13 | Guest checkout | Purchase succeeds without an account, order can later be linked securely | US-06-06, US-06-07 |
| AC-14 | Customer return request | Customer sees status, admin sees workflow and history | US-13-03, US-20-06 |
| AC-15 | Out of stock | Product page, cart and checkout consistent, no unintended sale | US-04-09, US-05-07 |
| AC-16 | Parcel returned to origin | Recorded as Returned, not Delivered, stock and payment state remain correct | US-09-06 |
| AC-17 | Catalogue import re run | Re running the same file updates existing products and creates no duplicates | US-23-09 |
| AC-18 | Guest fills a cart, then registers or signs in | The cart carries over intact, no items lost, no duplicates | US-05-10, US-05-11, US-05-12 |
| AC-19 | Customer signs in with Google | Account created or matched correctly, no duplicate account for the same email | US-06-03, US-06-04 |
| AC-20 | Client edits a product and a storefront section unaided | Changes appear correctly on both the English and Arabic storefront with no developer involvement | US-22-01 |

> **AC-20 is performed by the client, not by the developer.** So is the three minute product creation in US-15-06, and the migration re run in US-23-09. A capability the client cannot exercise unaided has not been delivered, whatever the code does.

## 23. The ten business rules as testable statements

Annex A Section D2 records ten launch business rules. BR-001 to BR-008 carry the same numbers as the client requirements document so the two can be read side by side. BR-009 and BR-010 are developer additions.

| Id | Rule | The test that proves it | Story |
|---|---|---|---|
| BR-001 | Welcome discount on account creation, with value, cap, expiry and eligibility all admin configurable | Change each of the four values in admin and observe the change at checkout, with no code change | US-14-02 |
| BR-002 | Free shipping on two or more items, condition admin configurable | Change the condition in admin and observe the change in the cart | US-14-03 |
| BR-003 | An order is never confirmed for a quantity above the stock actually available | Reduce stock below the cart quantity between cart and placement, then place the order | US-07-06 |
| BR-004 | Promotion stacking is explicit and controlled by a setting | Turn stacking off, then attempt to combine the welcome discount, a coupon and free shipping | US-14-04 |
| BR-005 | Every order item stores the price charged at the moment of order | Change a product price, then reopen an order placed before the change | US-14-05 |
| BR-006 | A refund never exceeds the amount actually paid after discounts | Attempt a refund above the discounted amount paid, and attempt cumulative partial refunds that would exceed it | US-14-06 |
| BR-007 | A cancelled or failed order releases its stock | Cancel a confirmed order and observe available stock | US-14-07 |
| BR-008 | A guest can complete an order and afterwards create an account with that order linked | Complete a guest order, then create the account from the confirmation | US-06-06, US-06-07 |
| BR-009 | A free shipping promotion overrides the calculated shipping rate | Trigger free shipping on an address whose zone carries a rate | US-14-03 |
| BR-010 | Discounts never produce a negative order total | Apply a discount larger than the order value | US-14-08 |

## 24. The definition of done

Annex A Section M adopts nine criteria from the client requirements document. They apply to every feature in every stage, and they are reproduced here because a story is not finished until they hold as well as its own criteria.

| Id | Criterion | Where it is enforced in this document |
|---|---|---|
| DOD-01 | Interface implemented as approved, across all mobile and desktop states | GC-04, US-27-01 |
| DOD-02 | Business logic and exception cases implemented, not only the successful path | GC-06, and the unhappy path criteria in every story |
| DOD-03 | Validation and error messages clear and correct in both English and Arabic | GC-05 |
| DOD-04 | Tests written and executed per system layer, plus manual testing on staging | US-27-04, at the reduced scope recorded at DOD-04 |
| DOD-05 | Analytics events verified where required | GC-09, Epic E26 |
| DOD-06 | Staff permissions tested | GC-07, US-21-01 |
| DOD-07 | No known errors affecting usage | GC-10 |
| DOD-08 | Settings needed for operation documented | GC-10, US-27-11 |
| DOD-09 | Client approval of acceptance criteria before a feature moves to production | Approval of this document, per section 7 |

**DOD-04 is delivered at its recorded reduced scope:** tests covering critical business logic, being pricing, promotions, refunds, stock and duplicate order protection, rather than full automated coverage of every feature (EX-16).

## 25. Stage gates and the stories behind them

The Project Plan MS-PLN-2026-011 sets a gate at the end of each stage. This table says which stories must satisfy their criteria before each gate can be passed.

| Stage | Gate | Stories that must pass |
|---|---|---|
| **1. Start and specification** | The client approves the specification and the acceptance model | This document, US-27-10 |
| **2. Foundation and products** | The client reviews the product model and catalogue structure on staging | E01, US-16-01 to US-16-05, US-16-09, E17 |
| **3. Shop front complete** | The client walks the full customer journey on staging, in both languages, on a phone and a desktop | J1, J2, J3, J4, and epics E02 to E07, E10, E11 |
| **4. Operations system** | The client team, not the developer, processes test orders and a test return end to end | J5, J6, J7, J9, and epics E08, E09, E12, E13, E14, E15, E18 to E22 |
| **5. Migration and reports** | The client re runs the migration tool on a changed file and confirms it updates rather than duplicates | J8, and epics E23, E24, E25, E26 |
| **6. Live and handed over** | Acceptance sign off against the twenty scenarios in section 22, then go live | All twenty AC scenarios, US-27-05, US-27-11 |

---

# PART SIX: TRACEABILITY

## 26. How to read the matrix

Every requirement in Annex A classified **P1**, **P1-L** or **DLV** appears below, mapped to the story or stories that deliver it. Consecutive requirements delivered by the same story are grouped.

**A requirement that appears here and nowhere in Part Four is a defect in this document.** A requirement that appears in Part Four and not in Annex A would be worse, being scope introduced without a contract, and none has been.

P2, P3 and OUT rows are not listed. They create no delivery obligation, they are named in the stories where their absence changes what is delivered, and Annex A Part One is the complete record of them.

## 27. Matrix: foundation

| Annex A | Requirement group | Story |
|---|---|---|
| VIS-01, VIS-02, VIS-03, VIS-04, VIS-05, VIS-06 | Vision, focus categories, ease of discovery, target audience | Realised across E02, E03, E04, and E15 for the operational half |
| OBJ-01, OBJ-04 | Conversion, premium trust experience | E01 to E07, US-01-08 |
| OBJ-02 | Average order value through thresholds and cross sell | US-05-04, US-05-05, US-14-03 |
| OBJ-03 | Registered customer base for remarketing | E06, US-12-07 |
| OBJ-05 | Run the store without daily developer dependence | E15, E16, E22 |
| OBJ-06, OBJ-07 | Data on sales and behaviour, readiness for integrations | E24, E26, US-08-01, US-09-02, INT-16 |
| PLT-01, PLT-02, PLT-03 | Single vendor, modular and extensible engineering | US-27-09 |
| PLT-06 | Responsive mobile first website | US-27-01 |
| FIX-01, FIX-02, FIX-03 | Products only, single vendor, premium identity | US-01-08 |
| FIX-04, FIX-04a | Multilingual storefront, English primary | US-01-01, US-01-02 |
| FIX-05 | Mobile first responsive | US-27-01 |
| FIX-06 | Welcome discount configurable | US-14-02 |
| FIX-07 | Free shipping on two or more items | US-14-03 |
| FIX-08 | Promotion engine manageable from admin | US-14-01 |
| FIX-09 | Guest checkout | US-06-06 |
| FIX-10 | Modular and extensible architecture, no capability guarantee | US-27-09 |
| JRN-01, JRN-02, JRN-03, JRN-04, JRN-05, JRN-06, JRN-07, JRN-08, JRN-09, JRN-10 | The customer journey stages | J1 and J2 in full, story by story in the journey tables |
| IA-01, IA-02, IA-03, IA-04, IA-05, IA-06, IA-07, IA-08, IA-09, IA-10, IA-11, IA-14, IA-15, IA-16, IA-17, IA-18, IA-19, IA-20, IA-21, IA-22, IA-24, IA-25, IA-26, IA-27, IA-28, IA-29, IA-30, IA-31, IA-32, IA-33, IA-35 | Every page in the information architecture | E01 to E13, E22. Each page is delivered by the epic that owns it |
| ROLE-01 to ROLE-10 | Roles, permissions and logging of sensitive operations | Section 10, US-21-01, US-21-03 |

## 28. Matrix: storefront

| Annex A | Requirement group | Story |
|---|---|---|
| NAV-01, NAV-03 to NAV-07 | Header, search bar, account, wishlist, cart count, mega menu | US-01-05 |
| NAV-02 | Language switcher | US-01-02 |
| NAV-08, NAV-09, NAV-10 | Mobile header, quick search, hamburger | US-01-06 |
| HOME-01, HOME-02, HOME-03, HOME-04, HOME-05, HOME-06, HOME-07 | Hero, trending, new arrivals, collections, category shortcuts, brands, offers | US-02-01 |
| HOME-08, HOME-09 | Trust signals, signup prompt | US-02-02 |
| HOME-10 | Section content editable | US-02-03, US-22-02 |
| HOME-11 | Section order changeable | US-02-04, US-22-02 |
| PLP-01, PLP-02, PLP-03, PLP-04, PLP-05 | Grid, sale price, stock status, quick add to cart and wishlist | US-03-01 |
| PLP-06, PLP-07, PLP-08, PLP-09, PLP-10, PLP-18, PLP-22 | Filters, active filter removal, mobile filter drawer | US-03-02 |
| PLP-11 | Filter by rating, second release | US-03-03 |
| PLP-12, PLP-13, PLP-14, PLP-15, PLP-16 | Sorting and pagination | US-03-04 |
| PLP-17, PLP-20, PLP-21 | Result count, category banner and description, breadcrumb | US-03-01 |
| PLP-19 | Empty state with suggestions | US-03-05 |
| SRCH-01, SRCH-02, SRCH-03, SRCH-04, SRCH-06a | Full text search, fields covered, autocomplete, results page, both languages | US-03-06 |
| SRCH-05 | Zero result page | US-03-05 |
| SRCH-06, SRCH-07 | Arabic synonym and correction list, admin managed | US-03-07 |
| SRCH-08 | Search term logging | US-03-08 |
| PDP-01, PDP-02 | Gallery with zoom, video | US-04-01 |
| PDP-03, PDP-04 | Name, brand, price and saving | US-04-02 |
| PDP-05, PDP-06, PDP-07 | Variants, stock per variant, quantity | US-04-03 |
| PDP-08, PDP-09, PDP-10 | Add to cart, buy now, wishlist | US-04-04 |
| PDP-11, PDP-12, PDP-13 | Short description, full description, specifications | US-04-05 |
| PDP-14, PDP-15, PDP-16 | Delivery estimate, returns summary, authenticity | US-04-06 |
| PDP-17 | Reviews on the product page | US-04-07 |
| PDP-18, PDP-19 | Related products, recently viewed | US-04-08, US-11-07 |
| PDP-21, PDP-24 | Share buttons, structured data | US-04-10 |
| PDP-22 | Out of stock handling | US-04-09 |
| CART-01 to CART-04 | Line items, quantity, removal, move to wishlist | US-05-01 |
| CART-05 | Totals breakdown | US-05-03 |
| CART-06 | Coupon entry and messaging | US-05-06 |
| CART-07 | Free shipping progress | US-05-04 |
| CART-08 | Cross sell in cart | US-05-05 |
| CART-09 | Cart persists across devices | US-05-09 |
| CART-10 | Stock re validation | US-05-07 |
| CART-11 | Empty cart state | US-05-08 |
| CART-12 | Mini cart | US-05-02 |
| CART-13 | Guest cart survives registration | US-05-10 |
| CART-14, CART-18 | Guest cart merges on sign in, conflicts resolved predictably | US-05-11 |
| CART-15 | Guest cart survives Google sign in | US-05-12 |
| CART-16 | Guest cart survives browser close | US-05-13 |
| CART-17 | Guest wishlist transfers | US-11-06 |
| AUTH-01, AUTH-02, AUTH-05, AUTH-09, AUTH-14 | Registration, phone registration, verification, consent, minimum fields | US-06-01 |
| AUTH-03, AUTH-04, AUTH-12, AUTH-16 | Sign in, password reset, passwordless link, single use link | US-06-02 |
| AUTH-06 | Welcome discount on registration | US-14-02 |
| AUTH-07 | Guest checkout | US-06-06 |
| AUTH-08, AUTH-11 | Guest order linked to an account, one step creation | US-06-07 |
| AUTH-10 | Google sign in and registration | US-06-03 |
| AUTH-13 | One account per email address | US-06-04 |
| AUTH-15 | Session security, rate limiting, bot protection | US-06-05 |
| CHK-01, CHK-02, CHK-11 | Egyptian address structure, saved addresses, validation | US-07-01 |
| CHK-03 | Shipping method and cost | US-07-02 |
| CHK-04 | Payment method selection | US-07-03 |
| CHK-05, CHK-06, CHK-08, CHK-09 | Review step, notes, breakdown, terms | US-07-04 |
| CHK-07 | Coupon at checkout | US-07-05 |
| CHK-10 | Mobile checkout | US-07-09 |
| CHK-12 | Stock re validation at placement | US-07-06 |
| ACCT-01, ACCT-08 | Account dashboard, saved wishlist | US-10-01 |
| ACCT-02, ACCT-03 | Profile, password | US-10-05 |
| ACCT-04 | Address book | US-10-04 |
| ACCT-05, ACCT-06, ACCT-07 | Order history, detail, tracking | US-10-02 |
| ACCT-09 | Reorder, second release | US-10-06 |
| ACCT-10 | Returns from the account | US-13-01 |
| ACCT-11 | Notification preferences, second release | US-12-07 |
| ACCT-12 | Invoice download | US-10-03 |
| ACCT-13 | Account deletion request, second release | US-10-07 |
| WISH-01, WISH-02, WISH-03 | Wishlist add and remove, persistence, guest wishlist | US-11-04 |
| WISH-04 | Move wishlist to cart | US-11-05 |
| WISH-07 | Recently viewed | US-11-07 |
| REV-01, REV-02 | Rating and written review | US-11-01 |
| REV-03, REV-04 | Moderation, average rating | US-11-02 |
| REV-05 | Post delivery review invitation | US-11-03 |
| CMS-01, CMS-02, CMS-03, CMS-04, CMS-05, CMS-06, CMS-07, CMS-08, CMS-10 | About, authenticity, shipping, contact, FAQ, privacy, returns, terms, WhatsApp link | US-01-07 |
| CMS-09 | All the above editable without a developer | US-22-07 |

## 29. Matrix: business rules, orders, payments, shipping and returns

| Annex A | Requirement group | Story |
|---|---|---|
| PROMO-01, PROMO-02, PROMO-03, PROMO-04, PROMO-05, PROMO-06, PROMO-07, PROMO-08, PROMO-09, PROMO-10, PROMO-11, PROMO-12, PROMO-13, PROMO-14, PROMO-15, PROMO-16, PROMO-17, PROMO-18, PROMO-19, PROMO-23 | The promotion engine, its rewards, conditions, limits, scheduling, exclusions and caps | US-14-01 |
| BR-001 | Welcome discount | US-14-02 |
| BR-002, BR-009 | Free shipping rule and rate override | US-14-03 |
| BR-003 | Stock validation | US-07-06 |
| BR-004 | Explicit stacking | US-14-04 |
| BR-005 | Price snapshot | US-14-05 |
| BR-006 | Refund integrity | US-14-06 |
| BR-007 | Stock released on cancellation | US-14-07 |
| BR-008 | Guest checkout with a route back | US-06-06, US-06-07 |
| BR-010 | No negative totals | US-14-08 |
| MER-01, MER-02, MER-03, MER-04, MER-05 | Badges, featured flag, manual ordering, collections, related products | US-14-09 |
| MER-06, MER-07 | Premium visual and discount treatment | US-01-08 |
| ORD-01, ORD-02, ORD-03, ORD-04, ORD-05, ORD-06, ORD-07, ORD-08, ORD-09, ORD-10, ORD-11, ORD-12, ORD-13, ORD-14 | The complete order status model | US-07-10, with ORD-13 at US-09-06 and ORD-14 at US-09-07 |
| PAY-01, PAY-13 | Payment abstraction and the provider integration | US-08-01 |
| PAY-02, PAY-03 | No card data, hosted tokenised flow | US-08-02 |
| PAY-04, PAY-16 | Webhook handling and cryptographic verification | US-08-03 |
| PAY-05 | Duplicate protection | US-07-07 |
| PAY-06, PAY-19 | Transaction records, settlement timing | US-08-05 |
| PAY-07, PAY-08, PAY-17 | Full and partial refunds from the order screen | US-08-06 |
| PAY-09 | Cash on delivery capability | US-08-07 |
| PAY-14, PAY-15 | Card payments with 3-D Secure, wallet capability | US-07-03 |
| SHIP-01, SHIP-15 | Zones and manual governorate rates | US-09-01 |
| SHIP-03, SHIP-08, SHIP-09 | Shipment creation, carrier integration, automatic tracking | US-09-02 |
| SHIP-04 | Delivery estimate | US-04-06, US-07-02 |
| SHIP-05 | Free shipping overrides the rate | US-14-03 |
| SHIP-10, SHIP-11, SHIP-12 | Bulk dispatch, bulk labels, pickups | US-09-03 |
| SHIP-13 | Carrier status retrieval | US-09-04 |
| SHIP-14 | Explicit status mapping | US-09-05 |
| SHIP-16 | Cash on delivery reconciliation | US-09-07 |
| SHIP-17 | Returned to origin as a distinct state | US-09-06 |
| RET-01, RET-02 | Return request with reason and photographs | US-13-01 |
| RET-03 | Eligibility against the return window | US-13-02 |
| RET-04 | Approve or reject with a recorded reason | US-20-03 |
| RET-06 | Receipt and condition | US-20-04 |
| RET-08 | Refund with reference and notification | US-20-05, US-13-04 |
| RET-10 | Return status visible to the customer | US-13-03 |
| NOTF-01 | Account created email | US-12-01 |
| NOTF-02 | Order confirmed email | US-12-02 |
| NOTF-03 | Payment failed email | US-12-03 |
| NOTF-04, NOTF-06 | Shipped and delivered emails | US-12-04 |
| NOTF-07 | Return update emails | US-12-05 |
| NOTF-08 | Refund completed email | US-12-06 |
| NOTF-11, NOTF-12, NOTF-13 | Consent governed sending, frequency limits, provider compliance | US-12-07 |
| NOTF-14 | Admin editable templates, second release | US-12-08 |

## 30. Matrix: administration

| Annex A | Requirement group | Story |
|---|---|---|
| ADM-01 to ADM-04, ADM-08, ADM-09, ADM-15 | Dashboard figures, charts, top products, quick actions | US-24-01 |
| ADM-05, ADM-07 | Customer figures and conversion rate, second release | US-24-06 |
| ADM-06, ADM-10 to ADM-13 | Refunds and returns panel, low and out of stock, orders requiring action, pending returns | US-24-02, US-15-03 |
| ADM-20, ADM-21 | Product lifecycle and states | US-16-01 |
| ADM-22, ADM-23, ADM-24, ADM-25, ADM-26, ADM-27, ADM-29 | Media, alt text, prices, cost, compare at price | US-16-02, US-16-05 |
| ADM-30, ADM-31, ADM-32, ADM-33, ADM-34, ADM-35, ADM-36, ADM-37 | Variants, SKU, barcode, price, stock, weight, tax class, shipping flags | US-16-04, US-16-02 |
| ADM-38, ADM-39, ADM-40, ADM-41 | SEO slug, meta title, meta description, canonical and robots | US-16-06 |
| ADM-42, ADM-43, ADM-44, ADM-45 | Badges, featured, collection assignment, related products | US-14-09, US-16-02 |
| ADM-47, ADM-48, ADM-161 | Bulk import, bulk export, import and export tooling | US-16-07, E23 |
| ADM-49 | Bulk update of price, stock, status, category | US-15-09 |
| ADM-50 | Price and stock change history | US-16-08 |
| ADM-55, ADM-56, ADM-57 | Categories, brands, curated collections | US-16-09 |
| ADM-59, ADM-60, ADM-61 | Home page section management, reordering, images and calls to action | US-22-02, US-22-03, US-02-03 |
| ADM-63 | Static pages, policies and FAQ editable | US-22-07 |
| ADM-70, ADM-72 | Stock on hand and available | US-17-01 |
| ADM-71 | Reserved stock, second release | US-17-01 |
| ADM-73 | Stock adjustments with reason | US-17-02 |
| ADM-74, ADM-75 | Low stock threshold and alerts | US-17-03 |
| ADM-77 | Bulk stock import | US-17-04 |
| ADM-85 | Order search and filtering | US-18-01 |
| ADM-86, ADM-88 | Order detail and internal notes | US-18-02 |
| ADM-87 | Status update with validation | US-18-03 |
| ADM-89 | Shipment and tracking from the order | US-18-04 |
| ADM-90 | Cancel order | US-18-05 |
| ADM-91 | Refund subject to permissions | US-08-06 |
| ADM-92, ADM-93 | Invoice and packing slip | US-18-06, US-10-03 |
| ADM-100 | Customer profile | US-19-01 |
| ADM-101, ADM-105 | Customer metrics and privacy controlled export, second release | US-19-04 |
| ADM-103 | Internal notes on a customer | US-19-02 |
| ADM-104 | Disable or suspend an account | US-19-03 |
| ADM-110, ADM-111, ADM-113, ADM-115 | Promotion creation, conditions and limits, scheduling, pausing | US-14-01, US-14-04 |
| ADM-120, ADM-121 | Return case queue and review | US-20-01, US-20-02 |
| ADM-122 | Approve, reject, partially approve | US-20-03 |
| ADM-123 | Record returned condition | US-20-04 |
| ADM-124 | Initiate and record refund | US-20-05 |
| ADM-125 | Full audit trail on returns | US-20-06 |
| ADM-130 | Staff users, roles and granular permissions | US-21-01 |
| ADM-131 | Two factor authentication for admin | US-21-02 |
| ADM-132, ADM-133, ADM-134 | Audit log, deletion protection, security events | US-21-03 |
| ADM-140, ADM-145 | The Operations Console and its quick actions | US-15-01 |
| ADM-141 | Orders requiring action queue | US-15-02 |
| ADM-142 | Stock requiring attention | US-15-03 |
| ADM-143 | Today summary | US-15-04 |
| ADM-144 | Saved views | US-15-05 |
| ADM-146, ADM-153 | Add a product in under three minutes, duplicate a product | US-15-06, US-16-01 |
| ADM-147 | Inline edit grid | US-15-07 |
| ADM-148 to ADM-151 | Bulk price, stock, publish state and category | US-15-09 |
| ADM-152, ADM-157 | Find a product in seconds, order search by phone | US-15-10 |
| ADM-154, ADM-155, ADM-156 | Bulk order status, bulk dispatch and labels, bulk packing slips | US-15-08 |
| ADM-158 | Order export with selectable columns | US-18-07 |
| ADM-159, ADM-160 | English admin interface, mobile admin use | US-15-11 |
| SSC-01 | Nothing a customer reads is locked in code | US-22-01 |
| SSC-02, SSC-03 | Home page sections, show and hide | US-22-02, US-22-03 |
| SSC-04, SSC-05 | Category and brand page content | US-22-04 |
| SSC-06 | Collection content and ordering | US-22-05 |
| SSC-07, SSC-08 | Navigation, mega menu, footer | US-22-06 |
| SSC-09, SSC-11 | Promotional banners, campaign pages | US-22-08 |
| SSC-10 | Static and policy pages | US-22-07 |
| SSC-12 | Independent English and Arabic content | US-22-09 |
| SSC-13 | Preview before publish | US-22-10 |
| SSC-14 | Revert a content change | US-22-11 |
| SSC-20, SSC-29 | Every product field editable, live on save | US-16-02 |
| SSC-21 | Product content in both languages | US-16-03 |
| SSC-22, SSC-23, SSC-24, SSC-25 | Badges, featured flag, placement, related products per product | US-14-09 |
| SSC-26 | Per product visibility and scheduling | US-16-01 |
| SSC-27 | Per product SEO override | US-16-06 |
| SSC-28 | Per product shipping and tax treatment | US-16-02 |

## 31. Matrix: migration, reports, marketing, analytics and non functional

| Annex A | Requirement group | Story |
|---|---|---|
| MIG-01 | File formats accepted | US-23-01 |
| MIG-02 | Encoding detection verified against Arabic | US-23-02 |
| MIG-03 | Preview | US-23-05 |
| MIG-04, MIG-10, MIG-11, MIG-12 | Column, attribute, category and brand mapping | US-23-03 |
| MIG-05 | Saved mapping profiles | US-23-04 |
| MIG-06, MIG-08 | Dry run and overwrite control | US-23-06 |
| MIG-07 | Safe re runs | US-23-09 |
| MIG-09 | Product variations | US-23-07 |
| MIG-13, MIG-14, MIG-15, MIG-16, MIG-17 | Prices and cost, stock, SEO fields, images, generated alt text | US-23-12 |
| MIG-18 | Import history | US-23-11 |
| MIG-19 | Row level error report | US-23-10 |
| MIG-20 | Background processing | US-23-08 |
| MIG-25 | Export in the mapped format | US-23-12, US-16-07 |
| RPT-01 | Sales report | US-24-03 |
| RPT-03, RPT-07 | Product performance and search terms reports, second release | US-24-06 |
| RPT-08, RPT-09 | Funnel and traffic reporting | US-24-05 |
| RPT-10 | Inventory report | US-24-04 |
| RPT-11 | Product cost captured from day one | US-16-05 |
| INT-01, INT-02 | Payment gateway and shipping carrier integrations | US-08-01, US-09-02 |
| INT-05 | Transactional email | E12 |
| INT-09, INT-10 | Analytics and Tag Manager, Meta pixel | US-25-01 |
| INT-12 | Google Merchant Center feed | US-25-02 |
| INT-16 | Every integration behind an adapter with retries, logging and webhooks | US-08-01, US-09-02, US-27-06 |
| MKT-01, MKT-02, MKT-03 | Analytics, Meta pixel, TikTok pixel | US-25-01 |
| MKT-05, MKT-06 | Meta and Google product feeds | US-25-02 |
| MKT-09 | Campaign attribution to the order | US-25-03 |
| MKT-10 | Consent management | US-25-04 |
| MKT-12, MKT-15 to MKT-18 | SEO fields, sitemap and robots, structured data, verification, hreflang | US-25-06, US-16-06 |
| MKT-13, MKT-14 | Redirect manager, missing page log | US-25-05 |
| MKT-20 | Campaign landing pages | US-22-08 |
| MKT-21 to MKT-25 | Marketing role limits, Tag Manager rule, staging access, audit visibility | US-25-07 |
| MKT-26 | Third Party Access Protocol | US-25-08 |
| EVT-01, EVT-02, EVT-03, EVT-04, EVT-05, EVT-06, EVT-07, EVT-08, EVT-09, EVT-11, EVT-12, EVT-13, EVT-14, EVT-16 | The standard ecommerce event set | US-26-01 |
| EVT-10, EVT-15, EVT-18, EVT-19 | Coupon, refund, zero result, return requested events | US-26-02 |
| EVT-20 | Event tracking plan | US-26-03 |
| NFR-01 | Mobile first | US-27-01 |
| NFR-02 | Performance | US-27-02 |
| NFR-03 | SEO foundations | US-25-06 |
| NFR-04, NFR-04a, NFR-04b | Localisation, no hard coded text, locale formatting | US-01-01, US-01-03, US-01-04 |
| NFR-05, NFR-06 | Security, payment security | US-27-03 |
| NFR-07 | Data integrity | US-27-04 |
| NFR-08, NFR-09 | Backups and environments | US-27-05 |
| NFR-10 | Observability | US-27-06 |
| NFR-11 | Accessibility | US-27-07 |
| NFR-12, NFR-12a | Privacy, automated request handling | US-27-08 |
| NFR-13, NFR-14 | Scalability and browser support | US-27-09 |
| ENT-01, ENT-02, ENT-03 | Product, variant, classification entities | US-16-01, US-16-04, US-16-09 |
| ENT-05, ENT-06, ENT-07 | Customer and address, cart, wishlist | US-10-04, E05, US-11-04 |
| ENT-08 | Order and order item with price snapshot | US-14-05 |
| ENT-09, ENT-13 | Payment and refund records | US-08-05, US-08-06 |
| ENT-10 | Shipment | US-09-02 |
| ENT-11 | Promotion and coupon | US-14-01 |
| ENT-12 | Return request and item | US-13-01, US-20-06 |
| ENT-14 | Review | US-11-01 |
| ENT-15 | Notification | US-12-08 |
| ENT-16 | Admin user, role, permission | US-21-01 |
| ENT-17 | Audit log | US-21-03 |
| ENT-19 | Import run | US-23-11 |
| ENT-04 | Inventory transaction, second release | US-17-02 |
| DOD-01 to DOD-09 | The definition of done | Section 24 |
| AC-01 to AC-20 | The acceptance scenarios | Section 22 |
| PRE-01, PRE-02, PRE-03, PRE-04, PRE-05, PRE-06, PRE-07 | Pre development deliverables | US-27-10 |
| HND-01, HND-02, HND-03, HND-04, HND-05, HND-06, HND-07, HND-08, HND-10, HND-11, HND-12 | Handover deliverables | US-27-11 |

---

# PART SEVEN: WHAT BLOCKS WHAT

## 32. Open decisions, and the stories each one holds up

Annex A Part Six records the business decisions still open. This section says exactly which stories cannot be finished until each is answered, so that the cost of a late decision is visible rather than discovered.

**None of these is a technical question, and none will be answered on the client behalf.**

| Decision | What is needed | Stories blocked | Needed by |
|---|---|---|---|
| **OD-27** | Written instruction to host outside Egypt, following legal advice on data protection | All infrastructure work, US-27-05, US-27-08 | Before infrastructure setup |
| **OD-15** | Expected product count, order volume and traffic, or confirmation of the stated assumption | US-27-09 | Before infrastructure setup |
| **OD-14** | Monthly operating budget for hosting and services | Infrastructure sizing and provider choice | Before infrastructure setup |
| **OD-01** | Brand name, logo and visual identity | All design work, and therefore E01 to E07 | Before design work |
| **OD-03** | Welcome discount value, cap, expiry and eligibility | US-14-02 | Before the promotions build |
| **OD-04** | Whether two items means two units or two distinct products, and any exclusions | US-05-04, US-14-03 | Before the promotions build |
| **OD-05** | Whether cash on delivery is offered, and any value limit, zone or fee | US-07-03, US-08-07 | Before the checkout build |
| **OD-19** | Which payment methods are live in the first release | US-07-03 | Before the checkout build |
| **OD-29** | Whether cash on delivery verification is added to scope | US-08-07 | Before the checkout build |
| **OD-09** | VAT and invoicing requirements for the legal entity | US-07-04, US-10-03, US-18-06 | Before the checkout build |
| **OD-08** | Return window and any product type exceptions | US-13-02, US-01-07 | Before the returns build |
| **OD-20** | Carrier cash collection and remittance cycle | US-09-07 | Before the shipping build |
| **OD-12** | Product cost basis, and who enters it | US-16-05 | Before catalogue load |
| **OD-13** | Authenticity and warranty policy and its supporting documents | US-01-07, US-04-06 | Before the content build |
| **OD-21** | Which advertising platforms are live in the first release | US-25-01, US-25-02 | Before the marketing build |
| **OD-26** | Image and content rights confirmed in writing | US-23-12 | Before build begins |
| **OD-18** | Domain, sending email address, support numbers, legal entity details | US-12-01 to US-12-06, US-10-03 | Before launch |

## 33. Client inputs, and the stories each one holds up

Annex A Part Five records what the client provides. These are not decisions, they are deliveries, and the same principle applies: what is late delays what depends on it.

| Input | Stories blocked | Needed by |
|---|---|---|
| **CR-01** Payment merchant account approved | US-07-03, US-08-01 to US-08-07. **Launch blocked** | Start first, longest lead item |
| **CR-02** Written approval of brand direction | All design work | Before design work |
| **CR-03** Carrier account, API key and rate card by governorate | US-09-01, US-09-02, US-09-03 | Before the shipping build |
| **CR-04** Legal and policy text | US-01-07, US-22-07. **Launch blocked** | Before launch |
| **CR-05** Product photographs | US-04-01, catalogue load | Before catalogue load |
| **CR-06** Amazon sample export file | Epic E23 in full. The import specification cannot be finalised without it | Before build begins |
| **CR-07** Amazon account type and report availability | US-23-03, US-23-07, US-23-12 | Before build begins |
| **CR-08** Written confirmation of image and content rights | US-23-12 criterion 4. Images will not be imported without it | Before build begins |
| **CR-09** Full catalogue export | US-23-09, catalogue load | Before catalogue load |
| **CR-10** Written hosting instruction | US-27-05, US-27-08 | Before infrastructure setup |
| **CR-12** Domain, sender email, support numbers, legal entity details | US-10-03, US-18-06, E12 | Before launch |
| **CR-13** Feedback on any deliverable | Every stage gate. Silence for 5 working days means the deliverable is deemed approved | Within 5 working days |
| **CR-14** User acceptance testing completed within the agreed window | Stage 6 | At UAT |
| **CR-15** Storefront content in both languages | US-16-03, US-22-09. **The store cannot go live multilingual without it** | Before catalogue load |

> **CR-15 deserves separate attention, and it is the input most often underestimated.** A multilingual store needs two versions of everything a customer reads: product names, descriptions, specifications, category and brand names, policy text and campaign copy, in English and Arabic. The framework, the switcher, the right to left layout and locale formatting are the developer responsibility. The words are the client responsibility. **A multilingual store with content in only one language is not a multilingual store**, and this is the single most common reason such launches slip.

## 34. Assumptions this specification is written on

Recorded so that both sides know what was assumed rather than agreed.

| Id | Assumption | Consequence if wrong |
|---|---|---|
| ASM-01 | The platform is sized for up to 5,000 products and 300 orders per day, with headroom for short campaign peaks (OD-15) | Materially higher volumes are handled by resizing the hosting plan, not by re implementing the core (NFR-13), and are a change to the hosting arrangement |
| ASM-02 | The launch market is Egypt and the currency is the Egyptian Pound (OD-02) | Multi currency and multi country are expressly excluded (EX-15) and would be a separate project |
| ASM-03 | Transactional email is the launch notification channel (OD-11) | WhatsApp and SMS automation are P2 and would be separately contracted |
| ASM-04 | The payment provider is Paymob and the carrier is Bosta, subject to client approval (OD-06, OD-07) | Both sit behind abstraction layers. A substitution notified before the relevant build begins is accommodated without a change fee where the integration requirements are materially equivalent |
| ASM-05 | The administrative interface is English, with the guide and training in Arabic (ADM-159) | An Arabic administrative interface is P2 |
| ASM-06 | The interface design is produced by the developer and approved by the client before storefront implementation begins | Storefront work cannot begin without that approval. Brand identity is a separate thing and is not included |
| ASM-07 | Amazon order history and customer personal data are not migrated (EX-23) | The customer base is built through the store own registration and consent flow |
| ASM-08 | Arabic search quality is bounded by the curated synonym list (SRCH-06) | Terms can be added from admin at any time, and adding them is not a change request |

## 35. What is deliberately absent from this document

A specification is judged as much by what it does not claim. The following are named here so that their absence is a decision on the record rather than an oversight.

| Absent | Why | Where it is recorded |
|---|---|---|
| Stories for P2 and P3 requirements | They are not contracted. Writing stories for them would blur the line the contract depends on | Annex A Part One, sections 1.3 and 1.5 |
| Screen layouts and component behaviour | Settled by the Interface Design, approved separately | Statement of Work section 9 |
| Data model, architecture and infrastructure design | A separate Stage 1 deliverable | PRE-01, PRE-02 |
| Loyalty, VIP tiers and referrals | Not in this contract. Fully specified in Annex A Section W so they can be quoted without re analysis | Annex A Section W |
| Any performance, conversion, traffic or ranking target | No such outcome is warranted | EX-31 |
| A marketplace conversion path | Phase 1 is single vendor. Reasonable extensibility is committed; a conversion guarantee is not | EX-02 |
| A blog or content hub | Recorded from the client position | EX-13, OD-22 |

---

# PART EIGHT: APPROVAL

## 36. What happens next

1. **You review this document within 5 working days**, per Annex C section 2.1. If you need longer, ask before the period ends and it will be given.
2. **You mark anything that does not match your expectation.** A missing step in a journey, a criterion that is wrong, an operational task the Operations Console does not cover. This is the cheapest moment in the project to change any of them.
3. **The store operations walkthrough (PRE-07) is performed** on the actual admin environment, with the Annex A Section G10 list reviewed together, before development begins.
4. **You approve, and Stage 1 closes.** Every later acceptance is then measured against these criteria rather than against recollection.

## 37. Approval

By approving this document you confirm that:

1. You have read it in full, including **Part Seven, which states what your open decisions and inputs are holding up**.
2. You agree that the acceptance criteria recorded here are what the work will be tested against at each stage gate and at user acceptance testing.
3. You accept that this document elaborates Annex A and **does not change it**. Where the two differ, Annex A governs.
4. You specifically acknowledge the reduced scope items identified in the stories that carry them, being Arabic search handling, reviews, wishlist, the audit log, analytics events, automated testing, training, home page management, notification channels, shipping rates, return eligibility and the inline edit grid.
5. You accept that the items marked second release in this document arrive after go live and are already included in the fee.

| | The Client, Mizzey | The Developer |
|---|---|---|
| Name | | Mustafa Mohamed Shaaban |
| Title | | Software Engineering Lead |
| Signature | | |
| Date | | |

---

*Mizzey Operations Platform. Functional Specification: User Stories and Acceptance Criteria. MS-SPC-2026-018, version 1.0, 6 September 2026. Stage 1 deliverable under the Statement of Work MS-SOW-2026-009. Read with the Feature Register MS-ANX-2026-001, which is the definitive scope, and the Acceptance and UAT Plan MS-UAT-2026-013, which governs how work is accepted.*

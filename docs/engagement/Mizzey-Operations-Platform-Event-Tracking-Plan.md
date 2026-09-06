# Mizzey Operations Platform
## Analytics Event Tracking Plan

**Document:** MS-EVT-2026-021
**Version:** 1.0
**Date:** 6 September 2026
**Prepared for:** Mizzey.com
**Prepared by:** Mustafa Shaaban
**Contract role:** Stage 1 deliverable under the Statement of Work, MS-SOW-2026-009, satisfying EVT-20 and HND-12
**Companion documents:** Feature Register MS-ANX-2026-001, Functional Specification MS-SPC-2026-018

---

# PART ONE: HOW MEASUREMENT WORKS HERE

## 1. What this document is

Annex A EVT-20 is a contractual deliverable, and it is specific about what it must contain: **every event,
its trigger, its parameters, and the source of truth per business metric**. This document is that, and the
last clause is the one that matters most.

A store that measures the same number in three systems will eventually report three different numbers, and
the argument that follows is unwinnable without a document that says in advance which one is right. Section
9 is that ruling.

## 2. Who this is for

**Your marketing team, and whoever they hire.** Annex A EX-18 excludes running campaigns and Section V
contracts the tools those campaigns need. This document is one of those tools: an agency arriving in month
four should be able to read it and know what is already measured without asking for a call.

## 3. What is instrumented, and what is not

| | |
|---|---|
| **In the first release** | The standard ecommerce event set across the full funnel, plus coupon, refund, return and zero result events |
| **Not included** | A bespoke custom event plan beyond the standard set. This is the recorded P1-L boundary at EVT-01 to EVT-19 |
| **Not included** | Promotion viewed and promotion clicked, being EVT-17, which is P2 |
| **Not included** | Server side tagging, being MKT-11, which is P3, and the Conversions API, being MKT-08, which is P2 |

## 4. Where events go

| Destination | What it receives | Requirement |
|---|---|---|
| **Google Analytics 4** | Every event in section 7 | MKT-01, INT-09 |
| **Google Tag Manager** | The only route any tag reaches the site by | MKT-23 |
| **Meta pixel** | The standard commerce subset | MKT-02, INT-10 |
| **TikTok pixel** | The standard commerce subset | MKT-03 |

**No third party writes tracking into the site code.** Every tag is deployed through Tag Manager, which is
a contractual control and not a preference: MKT-21 forbids the Marketing role from editing templates or
files, and MKT-23 makes Tag Manager the sanctioned route. An agency that asks for code access is asking for
a Change Request.

## 5. Conventions

| Rule | Value |
|---|---|
| Naming | Google Analytics 4 recommended ecommerce names, unchanged. A custom name is used only where no recommended one exists |
| Currency | `EGP` on every event carrying a value. The launch market is Egypt only, per OD-02 |
| Values | Excluding shipping and tax unless the parameter says otherwise. `purchase` carries all three separately |
| Language | Every event carries the active locale, so English and Arabic funnels can be separated |
| Identity | No personal data in any parameter. No email, no phone, no name, no address. Ever |
| Firing | Once per occurrence. A page refresh, a back navigation or a repeated callback does not double count |
| Layer | Pushed to the data layer by the site, read by Tag Manager. Tags are never hard coded |

> **On the identity rule.** Annex A NFR-12 requires data minimisation and R-04 records that electronic
> marketing is subject to consent obligations under Egyptian data protection law. Putting a customer email
> into an analytics parameter would breach both. The order id is sufficient to join back to the order
> inside your own systems, where the personal data already lives lawfully.

## 6. The item payload

Every event that carries products uses the same item shape. Defining it once is what keeps the funnel
joinable end to end.

| Parameter | Source | Note |
|---|---|---|
| `item_id` | Product or variation SKU | The variation SKU where a variant was chosen |
| `item_name` | Product name in the active language | |
| `item_brand` | Brand taxonomy | Empty where the product has no brand |
| `item_category` | Primary category | Sub categories as `item_category2` and onward |
| `item_variant` | The selected variant attributes | Empty for a simple product |
| `price` | Unit price actually charged or shown | The sale price where one applies |
| `discount` | Per unit discount | Zero where none |
| `quantity` | Units | |
| `index` | Position in the list | On list events only |
| `item_list_name` | The list the click came from | On list events only |

---

# PART TWO: THE EVENTS

## 7. Event reference

Every row is a contracted requirement from Annex A Section L.

| Id | Event | Trigger | Parameters beyond items | Scope |
|---|---|---|---|---|
| **EVT-01** | `view_home` | The home page renders | `language` | P1 |
| **EVT-02** | `view_item_list` | A category, collection or search results page renders | `item_list_name`, `item_list_id`, `items`, `language` | P1 |
| **EVT-03** | `search` | A search is executed | `search_term`, `results_count`, `language` | P1 |
| **EVT-04** | `view_item` | A product page renders | `items` with one item, `value`, `currency` | P1 |
| **EVT-05** | `select_item` | A product card is clicked | `item_list_name`, `items` with one item | P1 |
| **EVT-06** | `add_to_wishlist` | An item is added to the wishlist | `items`, `value`, `currency` | P1 |
| **EVT-07** | `add_to_cart` | An item is added to the cart, from anywhere | `items`, `value`, `currency` | P1 |
| **EVT-08** | `remove_from_cart` | A cart line is removed | `items`, `value`, `currency` | P1 |
| **EVT-09** | `view_cart` | The cart page renders | `items`, `value`, `currency` | P1 |
| **EVT-10** | `apply_coupon` | A coupon is accepted. **Not fired on a rejected coupon** | `coupon`, `discount_value`, `currency` | P1-L |
| **EVT-11** | `begin_checkout` | Checkout is entered | `items`, `value`, `currency`, `coupon` | P1 |
| **EVT-12** | `add_shipping_info` | A shipping method is chosen | `items`, `value`, `currency`, `shipping_tier` | P1 |
| **EVT-13** | `add_payment_info` | A payment method is chosen | `items`, `value`, `currency`, `payment_type` | P1 |
| **EVT-14** | `purchase` | **The order reaches Confirmed.** Not when Place Order is pressed | `transaction_id`, `value`, `tax`, `shipping`, `currency`, `coupon`, `items` | P1 |
| **EVT-15** | `refund` | A refund completes against the gateway | `transaction_id`, `value`, `currency`, `items` on a partial refund | P1-L |
| **EVT-16** | `sign_up`, `login` | An account is created, or a session begins | `method`, being `email`, `google` or `link` | P1 |
| **EVT-18** | `zero_result_search` | A search returns nothing | `search_term`, `language` | P1-L |
| **EVT-19** | `return_request` | A return request is submitted | `transaction_id`, `return_reason`, `items` | P1-L |

> ### Two triggers that are deliberately not the obvious ones
>
> **`purchase` fires when the order reaches Confirmed, not when Place Order is pressed.** A pressed button
> is an intention; a confirmed order is a sale. Firing on the button would count failed payments as
> revenue, and would double count the retry. This is the same boundary AC-06 and AC-08 enforce in the order
> logic, applied to measurement.
>
> **`refund` fires on gateway completion, not on the approval decision.** An approved return whose refund
> has not yet been issued is not money returned.

## 8. What fires where

Not every destination receives every event. Sending everything everywhere is how tag containers become
unmaintainable.

| Event | Analytics 4 | Meta | TikTok |
|---|---|---|---|
| `view_item_list`, `select_item`, `view_home` | Yes | | |
| `search`, `zero_result_search` | Yes | | |
| `view_item` | Yes | Yes | Yes |
| `add_to_wishlist` | Yes | Yes | |
| `add_to_cart`, `remove_from_cart`, `view_cart` | Yes | Yes | Yes |
| `begin_checkout` | Yes | Yes | Yes |
| `add_shipping_info`, `add_payment_info`, `apply_coupon` | Yes | | |
| `purchase` | Yes | Yes | Yes |
| `sign_up`, `login` | Yes | | |
| `refund`, `return_request` | Yes | | |

**Which advertising platforms are live at launch is an open decision, OD-21.** The instrumentation is built
for all three either way; enabling a platform is a Tag Manager change, not a development task.

---

# PART THREE: THE RULING

## 9. Source of truth per business metric

**This is the clause EVT-20 exists for.** For each metric, one system is authoritative and the others are
indicative. When they disagree, the authority wins and the difference is explained rather than argued.

| Metric | Source of truth | Why, and what the others are for |
|---|---|---|
| **Revenue** | **The store admin sales report** | Analytics loses events to consent refusal, ad blockers and network failure, and always undercounts. The order table cannot |
| **Orders and units** | **The store admin** | The same reason |
| **Average order value** | **The store admin** | Derived from the two above, so it inherits their authority |
| **Refunds and returns** | **The store admin** | The refund record carries the gateway reference. Nothing else does |
| **Cash actually received** | **The payment provider settlement report**, reconciled against the store | Paid is not received. PAY-19 records the distinction, and for cash on delivery SHIP-16 tracks collected separately from remitted |
| **Cash on delivery collected versus remitted** | **The store, from the carrier remittance** | The dashboard would otherwise overstate your cash position |
| **Sessions, users, traffic sources, campaigns** | **Analytics 4** | The store cannot see anonymous traffic at all |
| **Conversion rate** | **Analytics 4** | It needs a session denominator, which only Analytics has |
| **Funnel drop off** | **Analytics 4** | Indicative rather than exact, for the reasons above |
| **Campaign attributed revenue** | **The store order record**, using the attribution captured at MKT-09 | Platform reported revenue is each platform claiming credit for the same sale |
| **Advertising cost and return on spend** | **The advertising platform**, with revenue taken from the store | Cost is only known to the platform. Revenue is only known to the store |
| **Search terms and zero result terms** | **The store search log**, at SRCH-08 | Analytics site search is a subset. The store log has all of it |
| **Stock** | **The store** | There is no other candidate |

> **Expect Analytics to report less revenue than your admin, permanently.** A gap of roughly ten to thirty
> percent is normal and is not a defect. Consent refusal, ad blockers, browser privacy restrictions and
> dropped requests all remove events, and none of them removes an order from your database. **Judge the
> business on the admin figures and judge the marketing on Analytics**, and never reconcile one to the
> other.

## 10. Consent

| Rule | Behaviour |
|---|---|
| Before a choice is made | No advertising tag fires. No marketing identifier is set |
| Consent refused | Advertising tags stay off. Nothing that identifies the visitor is stored |
| Consent given | Tags fire normally |
| Recording | The choice is recorded and is changeable by the visitor |

This follows MKT-10 and NFR-12, and R-04 is the reason it is not optional. **Consent handling reduces
measured volume by design.** That is the trade, and section 9 already accounts for it.

## 11. How the plan is verified before launch

DOD-05 requires analytics events verified where required, and EVT-20 is not delivered by writing this
document. It is delivered by proving it.

| # | Verification |
|---|---|
| 1 | Every event in section 7 is fired by hand on staging and observed arriving, in English and in Arabic |
| 2 | The purchase event value reconciles to the order total on the same order, to the piaster |
| 3 | A double submitted order produces exactly one purchase event, matching AC-06 |
| 4 | A failed payment produces no purchase event, matching AC-08 |
| 5 | A refund produces one refund event with the correct value |
| 6 | Consent refused produces no advertising tag firing, checked in the network log rather than assumed |
| 7 | The item payload is identical in shape across `view_item`, `add_to_cart` and `purchase`, so the funnel joins |
| 8 | The results are recorded and handed over with the documentation, per HND-07 |

## 12. Handover

At handover this plan is delivered alongside the Tag Manager container, the Analytics property and the
pixel configurations, **all in accounts owned by you** (HND-06). Nothing in the measurement stack is held
in a developer account.

---

*Mizzey Operations Platform. Analytics Event Tracking Plan. MS-EVT-2026-021, version 1.0, 6 September 2026.
Stage 1 deliverable under the Statement of Work MS-SOW-2026-009, satisfying EVT-20 and HND-12. Scope is
defined by the Feature Register MS-ANX-2026-001.*

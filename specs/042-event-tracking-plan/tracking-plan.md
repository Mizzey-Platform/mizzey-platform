# Event Tracking Plan

**Register rows:** EVT-20 (this plan), handed over as HND-12. **Scope:** the Feature Register, MS-ANX-2026-006 v1.5,
section L (EVT-01 to EVT-20) and section V (MKT-01 to MKT-03, MKT-09, MKT-10, MKT-23). **Date:** 2 November 2026.

EVT-20 asks for every event, its trigger, its parameters, and the source of truth for each business metric. This
plan is that, for the standard e-commerce event set across the full funnel (Part One 1.2: not a bespoke plan). The
last part, the source of truth, is the one that settles arguments later.

## 1. What is measured

| | |
|---|---|
| **In the first release** | The standard e-commerce events across the funnel (EVT-01 to EVT-16, EVT-18), with coupon, refund and zero-result search |
| **Not included** | Promotion viewed and clicked (EVT-17, P2); return requested (EVT-19, DEF, with the returns workflow) |
| **Not included** | The Meta Conversions API (MKT-08, P2) and server-side tag management (MKT-11, P3) |
| **Not included** | Running campaigns (EX-18). The tools those campaigns need are included |

## 2. Where events go

| Destination | Receives | Rows |
|---|---|---|
| Google Analytics 4 | Every event in section 5 | MKT-01, INT-09 |
| Google Tag Manager | The only route any tag reaches the site by | MKT-23 |
| Meta pixel | The commerce subset in section 6 | MKT-02, INT-10 |
| TikTok pixel | The commerce subset in section 6 | MKT-03 |

The site pushes each event to the data layer (`window.dataLayer`); Tag Manager reads it and sends it on. No tag is
written into the site's code, and the Marketing role cannot edit templates or files (MKT-21, MKT-23). Which
advertising platforms are switched on at launch is OD-21: all three are built, and switching one on is a Tag Manager
change, not development.

## 3. Conventions

| Rule | Value |
|---|---|
| Names | Google Analytics 4's recommended e-commerce names, unchanged. A custom name only where none exists (`view_home`, `zero_result_search`) |
| Currency | `EGP` on every event that carries a value (the launch market is Egypt) |
| Values | Item prices as charged, after any sale price. `purchase` carries tax, shipping and the discount separately |
| Language | Every event carries `language` (`en` or `ar`), so the two storefronts' funnels can be read apart or together |
| Personal data | None in any parameter: no email, phone, name or address. The order number joins an event to the order inside the store, where the personal data already lives (NFR-12) |
| Firing | Once per occurrence. A refresh, a back navigation or a repeated callback does not count twice |

## 4. One item, two languages

Each product exists as two records, one per language (WPML). In events the item is identified by its **SKU**, which
both records share (the Arabic record copies it), so `item_id` is the same whichever storefront the visitor used, and
GA4 counts one item. A variation is identified by its own SKU. `item_name` is the name in the language shown; reports
that need one name group by `item_id`. A product with no SKU falls back to the source-language record's id.

| Parameter | Source | Note |
|---|---|---|
| `item_id` | SKU (the variation's SKU when a variation was chosen) | Same on both language records |
| `item_name` | Product name in the language shown | |
| `item_brand` | Brand | Empty when none |
| `item_category` | Primary category, in the language shown | Reports group by `item_id` across languages |
| `item_variant` | The chosen attributes | Empty for a simple product |
| `price` | Unit price charged | The sale price where one applies |
| `discount` | Discount per unit | 0 when none |
| `quantity` | Units | |
| `index`, `item_list_name`, `item_list_id` | The list position and the list | List events only |

## 5. The events

| Id | Event | Trigger | Parameters besides `items` and `language` | Scope |
|---|---|---|---|---|
| EVT-01 | `view_home` | The home page is shown | none | P1 |
| EVT-02 | `view_item_list` | A category, collection, brand or search results page is shown | `item_list_id`, `item_list_name` | P1 |
| EVT-03 | `search` | A search is run | `search_term`, `results_count` | P1 |
| EVT-04 | `view_item` | A product page is shown | `value`, `currency` | P1 |
| EVT-05 | `select_item` | A product card in a list is clicked | `item_list_id`, `item_list_name` | P1 |
| EVT-06 | `add_to_wishlist` | An item is saved to the wishlist | `value`, `currency` | P1 |
| EVT-07 | `add_to_cart` | An item is added to the cart, from any page | `value`, `currency` | P1 |
| EVT-08 | `remove_from_cart` | A cart line is removed or reduced | `value`, `currency` | P1 |
| EVT-09 | `view_cart` | The cart is shown | `value`, `currency` | P1 |
| EVT-10 | `apply_coupon` | A coupon is accepted (not when refused) | `coupon`, `discount`, `currency` | P1-L |
| EVT-11 | `begin_checkout` | Checkout is opened | `value`, `currency`, `coupon` | P1 |
| EVT-12 | `add_shipping_info` | A shipping method is chosen | `value`, `currency`, `shipping_tier` | P1 |
| EVT-13 | `add_payment_info` | A payment method is chosen | `value`, `currency`, `payment_type` | P1 |
| EVT-14 | `purchase` | The order is confirmed: payment confirmed by the gateway, or a cash-on-delivery order accepted. Not when Place Order is pressed | `transaction_id`, `value`, `tax`, `shipping`, `currency`, `coupon` | P1 |
| EVT-15 | `refund` | A refund completes, in full or in part | `transaction_id`, `value`, `currency`; `items` on a partial refund | P1-L |
| EVT-16 | `sign_up`, `login` | An account is created; a customer signs in (not staff) | `method`: `email` or `google` for sign-up; `password`, `google` or `link` for sign-in | P1 |
| EVT-18 | `zero_result_search` | A search returns nothing | `search_term` | P1-L |

**Two triggers that are not the obvious ones.** `purchase` fires on confirmation, because a pressed button is an
intention and a failed payment is not a sale; firing on the button would count failures and retries as revenue. It
fires once per order: the order records that it was sent. `refund` fires when the refund is made, not when it is
approved, and is sent by the store to Google Analytics directly (its Measurement Protocol), because a refund happens
in the administration, where no customer's browser is open.

A purchase confirmed after the customer has left the page (a gateway's late confirmation) cannot be sent from the
customer's browser and is not sent; the store's own records, which are the source of truth for revenue (section 7),
still have it.

## 6. What goes where

| Event | Analytics 4 | Meta | TikTok |
|---|---|---|---|
| `view_home`, `view_item_list`, `select_item` | Yes | | |
| `search`, `zero_result_search` | Yes | Yes (`search` only) | Yes (`search` only) |
| `view_item` | Yes | Yes | Yes |
| `add_to_wishlist` | Yes | Yes | Yes |
| `add_to_cart`, `view_cart` | Yes | Yes | Yes |
| `remove_from_cart` | Yes | | |
| `begin_checkout`, `add_payment_info` | Yes | Yes | Yes |
| `add_shipping_info`, `apply_coupon` | Yes | | |
| `purchase` | Yes | Yes | Yes |
| `sign_up` | Yes | Yes | Yes |
| `login`, `refund` | Yes | | |

## 7. Source of truth per business metric

For each metric one system is authoritative and the others are indicative. When they disagree, the authority wins
and the difference is explained.

| Metric | Source of truth | Why, and what the others are for |
|---|---|---|
| Revenue, orders, units, average order value | The store's sales report (RPT-01) | Analytics loses events to refused consent, ad blockers and dropped requests, and always counts less. The order table does not |
| Refunds | The store | The refund record carries the payment and refund references (#292, #293) |
| Money received from the payment provider | The provider's settlement report, reconciled against the store | Paid is not received (PAY-19) |
| Cash on delivery collected and remitted | The store, from the carrier's remittance (SHIP-16) | Delivered cash is not cash in hand |
| Sessions, users, traffic by channel, source and campaign | Analytics 4 (RPT-09) | The store cannot see anonymous visits |
| Conversion rate, funnel drop-off | Analytics 4 (RPT-08) | They need a visit count, which only Analytics has. Indicative, for the reasons above |
| Revenue by campaign | The store's order record, from the campaign captured on the order (MKT-09) | Each platform claims the same sale; the order records one |
| Advertising cost and return on spend | The advertising platform for the cost, the store for the revenue | Only the platform knows the cost; only the store knows the revenue |
| Search terms and zero-result terms | The store's search log (SRCH-08) | Analytics sees a subset |
| Stock | The ERP, through the store (ERP-07) | There is no other candidate |

**Expect Analytics to show less revenue than the store, permanently.** A gap of ten to thirty percent is normal and
is not a defect. Judge the business on the store's figures and the marketing on Analytics, and do not reconcile one
to the other.

## 8. Consent

| Situation | Behaviour |
|---|---|
| Before the visitor chooses | Google's consent mode defaults to denied; no advertising tag fires and no marketing identifier is set |
| Consent refused | Advertising tags stay off; analytics runs without identifiers (consent mode) |
| Consent given | Tags fire normally |
| The choice | Recorded, and the visitor can change it at any time (MKT-10, NFR-12) |

Consent reduces measured volume by design. Section 7 already allows for it.

## 9. How the plan is verified

DOD-05 asks for the events to be verified. Before launch, on staging:

| # | Check |
|---|---|
| 1 | Every event in section 5 is fired by hand and seen in the data layer and in Analytics' debug view, in English and in Arabic |
| 2 | `purchase` carries the order's total, tax and shipping to the piaster |
| 3 | An order submitted twice produces one `purchase`; a failed payment produces none |
| 4 | A refund produces one `refund` with the refunded value |
| 5 | With consent refused, no advertising request leaves the browser (checked in the network log) |
| 6 | `item_id` is the same for the English and the Arabic record of one product |

## 10. Handover

The plan is handed over (HND-12) with the Tag Manager container, the Analytics property and the pixels, all in
accounts the client owns (HND-06). Nothing in the measurement stack is held in a developer account.

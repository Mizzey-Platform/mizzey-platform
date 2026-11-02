# Feature Specification: measurement and advertising tools

**Feature Branch**: `043-measurement-tools`

**Created**: 2026-11-02

**Status**: Draft

**Work type**: requirement

**Input**: Issue #288 ("Measurement and advertising tools ready for a marketing team: analytics, pixels, product
feeds, attribution, consent"). It follows the event tracking plan (#274, specs/042). The owner's instruction of 2
November 2026 puts it in the work built with stand-ins: the Google, Meta and TikTok accounts are the client's (I-09,
HND-06), so the site is built to load whatever container is named, and loads nothing until one is.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| MKT-01 | P1 | S1 | Google Analytics 4 and Tag Manager with the full e-commerce event set |
| MKT-02 | P1 | S1 | Meta (Facebook / Instagram) Pixel |
| MKT-03 | P1 | S1 | TikTok Pixel with standard e-commerce events |
| MKT-05 | P1-L | S1 | Meta product catalogue feed: required for dynamic retargeting |
| MKT-06 | P1-L | S1 | Google Merchant Center product feed: required for Google Shopping |
| MKT-09 | P1-L | S1 | Campaign tracking preserved through to the order record, so revenue can be attributed to specific campaigns |
| MKT-10 | P1-L | S1 | Consent management and cookie banner, configured for consent-aware tracking |
| INT-09 | P1 | S1 | Google Analytics 4 and Tag Manager |
| INT-10 | P1 | S1 | Meta Pixel |
| INT-12 | P1-L | S1 | Google Merchant Center product feed |

MKT-03, MKT-05, MKT-06 and MKT-09 are marked [key] in the register. INT-09, INT-10 and INT-12 are #284's rows and the same integrations (its notes say so); they are traced here, and #284's email row (INT-05) is specs/045.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| MKT-21, MKT-23 | P1 | Tag Manager is the only route for tracking scripts; the Marketing role edits no files |
| EVT-01 to EVT-18 | P1, P1-L | The events the container receives; built in #312 |
| NFR-12 | P1 | Consent capture and data minimisation |
| MKT-04, MKT-07, MKT-08 | P2 | Snapchat, the TikTok catalogue feed, Meta's Conversions API: not built |

## Open contract items

- **I-09, HND-06.** The Google, Meta and TikTok accounts, opened by the client. The container id is entered when
  the client's Tag Manager account exists; the pixels are configured inside it.
- **OD-21.** Which trackers are switched on at launch. All three are built either way.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | Google Tag Manager loads on every storefront page, in both languages, after Google's consent defaults, from a container named in admin; the Google Analytics, Meta and TikTok tags run inside it | MKT-01, MKT-02, MKT-03, INT-09, INT-10 | provisional |
| AC-2 | A consent banner, in the visitor's language, records the visitor's choice, which governs every tracking tag and the campaign capture | MKT-10 | provisional |
| AC-3 | The campaign that brought the visitor is kept on the order | MKT-09 | provisional |
| AC-4 | A product feed per language serves Google Merchant Center and the Meta catalogue | MKT-05, MKT-06, INT-12 | provisional |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The media buyer arrives (Priority: High)

1. **Given** the client's Tag Manager container named in Settings, Marketing tags, **When** a visitor accepts
   cookies, **Then** the container's tags run with the visitor's consent; **When** the visitor refuses, **Then** no
   advertising tag sets anything.
2. **Given** the feed address, **When** Merchant Center or the Meta catalogue reads it, **Then** every visible
   product is listed with its SKU, price, availability, images and address in that language.

### Edge Cases

- No container named: nothing loads but the consent defaults and the data layer.
- A malformed container id: refused.
- A hidden, draft or unpriced product: not in the feed.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Campaign on the order | WooCommerce's order attribution (source, medium, campaign on the order, shown in the orders list) | Native, enabled; its cookies wait for consent here |
| Analytics, pixels | Google Tag Manager, in the client's account | Native to Tag Manager; the site only loads the container |
| Consent mode | Google's consent mode | Native to Google's tags; the site sets the defaults and the updates |
| Banner, feeds, container loader | None (no plugin, D-24) | Gap: `ConsentBanner`, `ProductFeed`, `TagManager` |

## Requirements

### Functional Requirements

- **FR-001**: The storefront MUST set Google's consent defaults to denied before any tag loads, unless the visitor
  has accepted (AC-1, AC-2).
- **FR-002**: The storefront MUST load the Tag Manager container named in Settings, Marketing tags
  (`manage_woocommerce`), and nothing when none is named; the id MUST match `GTM-` and letters or digits (AC-1).
- **FR-003**: The data layer MUST name the page's language (AC-1).
- **FR-004**: The banner MUST offer "Accept all" and "Essential only" in the visitor's language, keep the choice
  180 days, send it to the consent mode at once, and reopen from any `data-mizzey-consent-open` link (AC-2).
- **FR-005**: WooCommerce's order attribution MUST run only once the visitor accepts (AC-2, AC-3).
- **FR-006**: Each language MUST have a product feed in Google's product format, listing published, visible, priced
  products by SKU (variations by their own, grouped by the product's), with title, description, address, images,
  availability, price and sale price in EGP, brand, barcode and category; rebuilt at most hourly and after a product
  is saved (AC-4).

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- Snapchat (MKT-04), the TikTok catalogue feed (MKT-07), Meta's Conversions API (MKT-08), server-side tagging (MKT-11).

## Success Criteria

- **SC-001**: On staging, with a test container, the container loads after consent defaults in both languages; the
  banner records a choice; each feed validates in Merchant Center's feed preview.

## Delivery stage

Contractual stage: S1. Engineering timing: November 2026 (D-22).

## Assumptions

- The banner's look is neutral until the interface design (PRE-03b); its function is final.
- The Meta catalogue reads Google's product RSS format, so one feed per language serves both.

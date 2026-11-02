# Feature Specification: product management

**Feature Branch**: `036-product-management`

**Created**: 2026-11-02

**Status**: Draft

**Work type**: requirement

**Input**: Slice E-ADM-2, issue #276 ("Product management: content, media, pricing, variants, SEO fields, flags, bulk
import and export"). No blocking predecessor; the stock rows read the ERP through the seam that exists (#245, #461).
The owner's instruction of 2 November 2026 puts it in the admin set that needs no client input.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| ADM-20 | P1 | S1 | Create, edit, archive and clone products |
| ADM-21 | P1 | S1 | Draft, published and scheduled states |
| ADM-22 | P1 | S1 | Images and video |
| ADM-23 | P1 | S1 | Media ordering |
| ADM-24 | P1 | S1 | Alt text |
| ADM-25 | P1 | S1 | Regular price |
| ADM-26 | P1 | S1 | Sale price |
| ADM-29 | P1-L | S1 | Compare-at price |
| ADM-30 | P1 | S1 | Variant attributes |
| ADM-31 | P1 | S1 | SKU per variant |
| ADM-32 | P1 | S1 | Barcode per variant |
| ADM-33 | P1 | S1 | Price per variant |
| ADM-34 | P1 | S1 | Stock per variant, read from your ERP by variant SKU |
| ADM-35 | P1-L | S1 | Weight and dimensions per variant |
| ADM-36 | P1-L | S1 | Tax class |
| ADM-37 | P1-L | S1 | Shipping flags |
| ADM-38 | P1 | S1 | SEO slug |
| ADM-39 | P1 | S1 | SEO meta title |
| ADM-40 | P1 | S1 | SEO meta description |
| ADM-41 | P1 | S1 | Canonical and robots override |
| ADM-42 | P1 | S1 | Badges |
| ADM-43 | P1 | S1 | Featured flag |
| ADM-44 | P1 | S1 | Collection assignment |
| ADM-45 | P1 | S1 | Related products |
| ADM-47 | P1 | S1 | Bulk import: see Section U |
| ADM-48 | P1 | S1 | Bulk export |
| ADM-49 | P1-L | S1 | Bulk update: price, stock, status, category. The stock part is P1-E (Part One 1.7) |
| ADM-50 | P1-L | S1 | Price and stock change history with user and timestamp. The stock part is P1-E (Part One 1.7) |

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| ADM-27 | P1 | Product cost (#252, specs/001) |
| ADM-28, ADM-46 | P2 | Scheduled price changes, frequently bought together: not built (see Open contract items for ADM-28) |
| MER-01 | P1 | The same manual badges, from #282's side (D-19) |
| ERP-07, ADM-70, ADM-72 | P1-E | Local stock writers closed and the stock source's figure shown (specs/026) |
| SSC-27, MKT-12 | P1 | Per-product SEO overrides from the self-service and marketing sides: this is the one store (ADR-0003) |

## Open contract items

- **PRE-09.** ADM-34, the stock part of ADM-49 and of ADM-50 are P1-E; their criteria wait for the ERP specification.
- **CX-03** (D-12): the price part of ADM-50 writes a narrow history, with user and time.
- **ADM-28.** The register row is P2, while Part One's second-release table lists it: a contradiction for the owner
  (M-2), not built here.
- **CR-15.** Product content in both languages is the client's; placeholders until supplied.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | Staff create, edit, clone, archive and schedule simple and variable products with images in order with alt text, regular and sale prices, variant attributes, SKU, barcode and price per variant, weight and dimensions, tax class, shipping class, slug, featured flag and collection | ADM-20, ADM-21, ADM-22, ADM-23, ADM-24, ADM-25, ADM-26, ADM-30, ADM-31, ADM-32, ADM-33, ADM-35, ADM-36, ADM-37, ADM-38, ADM-43, ADM-44 | provisional |
| AC-2 | A product has a video, and an SEO title, meta description, canonical and robots override per language, shown on its page | ADM-22, ADM-39, ADM-40, ADM-41 | provisional |
| AC-3 | Staff set the manual badges New, Trending, Limited and Best Seller on a product | ADM-42 | provisional |
| AC-4 | Staff choose the related products shown on a product | ADM-45 | provisional |
| AC-5 | A sale price shows as a saving against the regular (compare-at) price only when it is lower | ADM-29 | provisional |
| AC-6 | Every price change is kept with the user and time | ADM-50 | provisional |
| AC-7 | Products are imported and exported in bulk, and price, status and category are updated in bulk, both language records following | ADM-47, ADM-48, ADM-49 | provisional |
| AC-8 | Stock per variant is read from the ERP by variant SKU; its bulk update and change history follow the ERP Integration Specification | ADM-34, ADM-49, ADM-50 | pending PRE-09 |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A new product (Priority: High)

1. **Given** a new serum, **When** staff create it with images, a video, prices, SEO fields and the New badge, and
   choose its related products, **Then** the product page carries the SEO fields and the storefront data the badge
   and the video.

### User Story 2 - A price change (Priority: High)

1. **Given** a product, **When** its price changes on the product screen, by the REST API or by import, **Then** each
   change is in its history with who and when, and the Arabic record shows the same price.

### Edge Cases

- A sale price above the regular price: not shown as a saving.
- A video or canonical address that is not a web address: not kept.
- The Arabic record's SEO fields: its own, never copied from the English, and shown on the Arabic page only.
- A price, a badge or another copied field changed by code (the REST API, WP-CLI, an integration) rather than on a
  screen: the Arabic record follows, as it does after the product screen, and an Arabic order is charged the new price.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Lifecycle, states, images, ordering, alt text, prices, variants, SKU, barcode (GTIN), weight, dimensions, tax and shipping class, slug, featured | WooCommerce's product screen | Native, verified |
| Clone | WooCommerce's Duplicate | Native |
| Collection | Mizzey's collection taxonomy (specs/003) | Exists |
| Import, export, bulk edit | WooCommerce's CSV importer and exporter, bulk edit; the migration is Section U (#247) | Native |
| Video | None | Gap: one address per product |
| SEO title, description, canonical, robots | None (no SEO plugin, D-24) | Gap: four fields, emitted through WordPress's own filters |
| Badges | None | Gap: four manual flags (D-19, D-20 C2) |
| Related products | Upsells (staff) and an automatic rail | Upsells relabelled; the automatic rail emptied (D-19 item 3, D-20 C3) |
| Compare-at | Sale and regular price, WooCommerce's validation | Native, verified (D-20 C6) |
| Price history | None | Gap: a narrow history per item (CX-03) |
| The Arabic record following a change made by code | WPML's copy runs from save_post, which a CRUD save after a field-only change skips (t20: REST and CRUD left a stale Arabic price) | Partial: `TranslationSync` runs WPML's own copy after such a save |

## Requirements

### Functional Requirements

- **FR-001**: The product screen MUST hold the video, the four SEO fields and the badges, saved only with the form's
  nonce by staff who may edit the product (AC-2, AC-3).
- **FR-002**: The product page MUST emit the SEO title, description, canonical and robots overrides of its own
  language record (AC-2).
- **FR-003**: The video and the badges MUST be the item's (copied to the Arabic record); the SEO fields each record's
  own (AC-2, AC-3).
- **FR-004**: The Store API MUST give the video and the badges in D-19's order (AC-2, AC-3).
- **FR-005**: The related products MUST be the Upsells, labelled "Related products"; the automatic rail MUST be
  empty (AC-4).
- **FR-006**: Every regular or sale price change of a product or variation MUST be recorded once per item with user
  and time, by any channel (AC-6).
- **FR-007**: A copied field (price, sale price and its dates, badges, video, cost, SKU, weight, dimensions) changed on
  the English record by any channel MUST reach the Arabic record (AC-6, AC-7).

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- Scheduled price changes (ADM-28, P2); frequently bought together (ADM-46, P2); the stock part of history and bulk
  update until PRE-09.

## Success Criteria

- **SC-001**: On staging, a product is created with every field and its page carries the SEO fields in both
  languages; a price change shows in its history.

## Delivery stage

Contractual stage: S1. Engineering timing: November 2026 (D-22, D-27).

## Assumptions

- "Archive" is WordPress's Private status (out of the shop, restorable) or the bin.
- A product video is one address: an uploaded file or a YouTube or Vimeo page; how it plays is the design's.

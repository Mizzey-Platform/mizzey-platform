# Feature Specification: product reviews

**Feature Branch**: `020-reviews`

**Created**: 2026-10-31

**Status**: Draft

**Work type**: requirement

**Input**: User description: "continue until you finish all waves" (the owner, 31 October 2026), wave 3 of the Stage 3
build plan under D-22: "E-SF-11 reviews: D-17 eligibility on every submission route, moderation, average rating data,
the invitation trigger, and up to 5 images per review, a recorded gift (D-23, not REV-06)". #309. The function layer;
the designed review form and display wait for PRE-03b. This part delivers REV-01 to REV-05; the images (D-23, a gift)
are a second part of #309.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| REV-01 | P1 | S1 | Star rating submission |
| REV-02 | P1 | S1 | Written review submission |
| REV-03 | P1 | S1 | Moderation before publication |
| REV-04 | P1 | S1 | Average rating on product page and listing |
| REV-05 | P1 | S1 | Post-delivery review invitation email |

Stories: Functional Specification MS-SPC-2026-032 v1.4, US-11-01 to US-11-05, the criteria that trace to the rows
above, with D-17's clarification of who may review.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| REV-06, REV-07 | P2 | Photo or video reviews and the verified-purchase badge are not contracted; D-23's images are a recorded gift, not REV-06, and no badge is shown |
| NOTF-06 | P1 | The same invitation, owned by #285's emails; the trigger and content are here |

## Open contract items

- D-17 is an owner working decision; acceptance of the US-11-01 and US-11-03 eligibility criteria waits for the
  client's signed Stage 1 Approval Record.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | A customer whose order for the product is delivered submits a star rating and a written review; the rating is required | REV-01, REV-02 | provisional |
| AC-2 | Every review waits for moderation; nothing is published before it is approved | REV-03 | provisional |
| AC-3 | The product page and the listing show the average of approved reviews | REV-04 | provisional |
| AC-4 | A delivered order sends one review invitation, in the order's language | REV-05 | provisional |

Provisional: verified by automated tests; acceptance follows manual testing on staging and the client's approval (and,
for who may review, the client's signed Stage 1 Approval Record under D-17). The designed form and display are
PRE-03b's.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Review what was delivered (Priority: High)

1. **Given** a customer whose order for a product is delivered, **When** they review it, **Then** the review, with its
   required rating, waits for moderation.
2. **Given** a customer without a delivered order for the product, or a visitor, **When** they open the product page,
   **Then** they see why they cannot review it instead of the form; a submission by any route is refused.
3. **Given** a customer who has reviewed a product, **When** they try again, **Then** they are told they already have.

### User Story 2 - Only approved reviews count (Priority: High)

1. **Given** a held review, **When** staff approve it, **Then** the product's average includes it, on the product page
   and the listing.

### User Story 3 - Invited after delivery (Priority: Medium)

1. **Given** an order whose delivery is confirmed, **When** it is delivered, **Then** one invitation goes to its billing
   email, in the order's language, linking each product's reviews.

### Edge Cases

- A fully refunded line, or an order not yet delivered, makes no one eligible.
- A product's variations and its record in the other language count as one product, for eligibility and for one
  review.
- An invitation reaches a guest purchaser too; it makes no one eligible.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Rating and text, average, display | WooCommerce's product reviews (`_wc_average_rating` over approved reviews) | Native, verified |
| Who may review | WooCommerce's "verified owners" setting counts any paid order, not a delivered one | Gap: `ReviewEligibility` (D-17) |
| Moderation | WordPress approves a returning commenter at once unless moderation is on | Gap: `ReviewModeration` holds every review |
| Invitation | WooCommerce 11's "Customer review request" is a beta feature, off by default, with its own review page; D-17 keeps it off (that page is another submission route) | Gap: `ReviewInvitation`, a WooCommerce email |

## Requirements

### Functional Requirements

- **FR-001**: A review MUST be accepted only from a signed-in customer with a delivered order for the product (any
  variation, either language), on a line not fully refunded, and only one active review per customer and product; on
  the form post, XML-RPC and the REST comments endpoint; staff who moderate are not checked (AC-1).
- **FR-002**: The product page MUST show the form only to someone who may review, and a short explanation otherwise.
- **FR-003**: Every review from someone who does not moderate MUST be held for moderation (AC-2); the rating MUST be
  required (AC-1).
- **FR-004**: The invitation MUST be a WooCommerce email staff can switch off and edit, sent once per order when it
  first enters a Delivered status, in the order's language (AC-4).
- **FR-005**: Every customer-facing message MUST be translated (FIX-04).

### Key Entities

- **Invitation sent**: when, on the order.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- Photo and video reviews as REV-06, the verified-purchase badge (REV-07), voting and questions (P2).

## Success Criteria

- **SC-001**: No review is published before approval, and none comes from someone without a delivered purchase.
- **SC-002**: No order sends a second invitation.

## Delivery stage

Contractual stage: S1, delivered with Stage 3 (D-22). Engineering timing: wave 3 of the Stage 3 build plan, from 31
October 2026.

## Assumptions

- The invitation is sent at the moment of delivery; a delay before sending is not asked for.
- D-23's images (up to 5 per review, moderated with it) are the second part of #309, with their own review of how an
  image stays private until its review is approved.

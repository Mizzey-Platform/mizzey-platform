# Feature Specification: the WhatsApp click-to-chat link

**Feature Branch**: `059-whatsapp-contact`

**Created**: 2026-11-04

**Status**: Draft

**Work type**: requirement

**Input**: #310 (trust and support pages, editable without a developer, in both languages), its function layer under
D-27. The pages exist in both languages (specs/003, IA-24 to IA-31) and are the client's to edit as WordPress pages;
the contact form is CoreX Forms' (a flow built in its admin, with its notification to the store and its confirmation to
the sender as two email bindings). What was missing is the WhatsApp link's number and greeting, set by the client. The
designed footer and contact page are PRE-03b's.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| CMS-09 | P1 | S1 | All the above editable without a developer |
| CMS-10 | P1 | S1 | WhatsApp click-to-chat contact link |

Story US-01-07 (Functional Specification MS-SPC-2026-032 v1.4): "A WhatsApp click to chat link is present and opens a
conversation with the configured number". Owned by #310.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| CMS-01 to CMS-08 | P1 | The pages: specs/003 made them in both languages; their words are the client's (CR-04) |
| CMS-04 | P1 | The contact form: CoreX Forms, configured in its builder |
| OD-08 | Client decision | The return window the return policy page states |

## Open contract items

- None.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | With a number set, the WhatsApp link opens a chat with that number and the greeting in the page's language; a number written with spaces, a plus or 00 is read as digits | CMS-10 | final |
| AC-2 | With no number, or an unusable one, there is no link | CMS-10 | final |
| AC-3 | The link reaches the storefront: a REST route in the request's language, and a shortcode any page or block can carry | CMS-10 | final |
| AC-4 | The number and the greetings are set in the admin, without a developer | CMS-09, CMS-10 | final |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A question on WhatsApp (Priority: High)

1. **Given** the number 201001234567 and an Arabic greeting, **When** an Arabic page's WhatsApp link is opened,
   **Then** WhatsApp opens a chat with that number, the Arabic greeting typed in.

### Edge Cases

- An empty Arabic greeting uses the English one.

## Native coverage

| Need | Native | Gap and how it is closed |
|---|---|---|
| Pages and their editing | WordPress pages, in each language | Used as they are (specs/003) |
| Contact form | CoreX Forms: builder, notification, sender confirmation | Used as it is, configured in its admin |
| WhatsApp link | None | The number and greetings in WooCommerce > Settings > General; `wa.me` address |

## Requirements

### Functional Requirements

- **FR-001**: The settings MUST be in WooCommerce > Settings > General, "Store contact" (AC-4).
- **FR-002**: `GET mizzey/v1/contact` and `[mizzey_whatsapp]` MUST give the link as AC-1 and AC-2 (AC-3).

### Key Entities

- **Store contact**: the WhatsApp number, a greeting in each language.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- None.

## Success Criteria

- **SC-001**: The client changes the WhatsApp number without a developer.

## Delivery stage

Contractual stage: S1, with #310. Engineering timing: 4 November 2026 (D-27).

## Assumptions

- The number is the client's (a content input).

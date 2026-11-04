# Feature Specification: the Egyptian address in full

**Feature Branch**: `050-egyptian-address`

**Created**: 2026-11-03

**Status**: Draft

**Work type**: requirement

**Input**: The owner, 3 November 2026 (translated from Arabic): "I would like these details added to the checkout, so
it is easy and clear. I do not know why there is billing and shipping; nobody here chooses or distinguishes between the
two, and I think Amazon and Noon do not have that, it is one thing. Study how names and addresses are handled in Egypt:
sometimes there is no street name but a compound name, for example Building 5, Dar Misr Al Andalus 70, fourth floor,
apartment 18, in the Third Settlement, New Cairo; someone else may have a street name inside a hara. It will make
delivering the order easier." He showed another Egyptian store's form (type of place, street, building, floor,
apartment, notes for the driver). It refines CHK-01, built in `019-checkout`, and the address book of `022-my-account`.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| CHK-01 | P1 | S1 | Address entry with Egyptian structure: governorate, city, district, street, landmark |
| CHK-02 | P1 | S1 | Saved address selection for returning customers |
| CHK-11 | P1 | S1 | Validation with clear error messages in both languages |
| ACCT-04 | P1 | S1 | Address book: add, edit, delete, set default |

Stories: Functional Specification MS-SPC-2026-032 v1.4, the checkout and account stories that trace to these rows.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| CHK-13 | P2 | Address autocomplete via maps: not built (the other store's map pin is this row) |
| SHIP-08, SHIP-09 | P1-L | Bosta through its official plugin (`041-bosta`): it sends the address's first and second lines and refuses a first line under 10 characters |
| CHK-06 | P1 | Order notes: the customer's note for the driver, unchanged |

## Open contract items

- None. Every part added is a part of the Egyptian address CHK-01 names ("Egyptian structure"); the map pin is CHK-13
  (P2) and is not built. One address is WooCommerce's own setting; no register row asks for two.

## What was studied

Sources and findings are in the private study (`pm/2026-11-03-egyptian-address-study.md`); the conclusions that shape
this slice:

- An Egyptian address reads from the largest place to the smallest: governorate, city or new city, district,
  neighbourhood or compound, street, building, floor, apartment. A compound often stands where a street would (Bosta's
  own district list names compounds, among them Dar Misr Al Andalus), and new cities number their districts and
  neighbourhoods.
- Couriers and delivery apps ask for the building, floor and apartment as separate parts, and a landmark or directions
  for the driver; drivers call before delivering. Postcodes are not used for delivery.
- Bosta's API has fields for the building, floor and apartment, but its official plugin sends only two address lines.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | For an Egyptian address the form reads largest place first: governorate, city or area, district or neighbourhood, the kind of place (apartment, villa or house, office), street or compound, building or villa number, floor, apartment or office number, nearest landmark; the mobile number is required | CHK-01, CHK-11 | provisional |
| AC-2 | A floor and an apartment number are required for an apartment or an office and not asked for a villa; a missing part is refused with a message naming it, in the checkout's language | CHK-01, CHK-11 | provisional |
| AC-3 | The checkout asks for one address, and the order is delivered to it | CHK-01 | provisional |
| AC-4 | The building, floor and apartment reach every reader of the address, as its second line in the order's language: the order screens and emails, the courier (Bosta), the packing slip and the invoice; a change staff make on the order is carried; the packing slip shows the landmark | CHK-01 | provisional |
| AC-5 | The address book keeps the same parts, and a default address prefills the checkout with all of them | ACCT-04, CHK-02 | provisional |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The driver finds the door (Priority: High)

1. **Given** a customer in a flat in a compound, **When** she checks out, **Then** she gives the governorate, the city,
   the district, "apartment", the compound's name as the street, the building, the floor and the apartment, and a
   landmark if she wishes; the order and the courier's address carry "Building 5, floor 4, apartment 18".
2. **Given** a villa, **When** "villa" is chosen, **Then** no floor or apartment is asked, and the line reads "Villa 12".
3. **Given** an apartment with no floor, **When** the order is placed, **Then** it is refused, naming the floor.

### User Story 2 - One address (Priority: High)

1. **Given** any checkout, **When** the customer fills it, **Then** there is one address and no billing or shipping
   choice.

### User Story 3 - Saved addresses (Priority: Medium)

1. **Given** an address saved in the account, **When** it is the default, **Then** the checkout is prefilled with every
   part.

### Edge Cases

- An address saved or ordered before this change has no kind of place or building: it keeps its old second line, and
  the customer is asked for the new parts the next time it is used at checkout.
- An address outside Egypt keeps WooCommerce's own form; none of these fields is shown.
- An Arabic order's line is in Arabic ("عمارة 5، الدور 4، شقة 18"), whoever saves the order.

## Native coverage

| Need | Native | Gap and how it is closed |
|---|---|---|
| One address | WooCommerce's "Force shipping to the customer billing address" | Set by code (`pre_option`), so it cannot drift |
| Address parts | Core address fields; additional checkout fields with conditional rules | The kind of place, building, floor and apartment as additional address fields; floor and apartment conditional on the kind |
| The parts on every reader | Every reader shows the second address line | The parts written as the second line on every order save, in the order's language |

## Requirements

### Functional Requirements

- **FR-001**: For an Egyptian address, the form MUST take governorate, city or area, district or neighbourhood, kind of
  place, street or compound, building or villa number, floor, apartment or office number and a landmark, in that
  order, with the mobile number required (AC-1).
- **FR-002**: The floor and apartment MUST be required for an apartment or an office and hidden for a villa (AC-2).
- **FR-003**: The checkout MUST ask for one address (AC-3).
- **FR-004**: The building, floor and apartment MUST be written as the address's second line, in the order's language,
  on every save of an Egyptian order; the packing slip MUST show the landmark (AC-4).
- **FR-005**: The address book MUST keep the same parts, with the same requirements, and copy them to the checkout's
  prefill (AC-5).

### Key Entities

- **Address**: governorate, city, district, kind of place, street or compound, building, floor, apartment, landmark,
  phone; on the order, the customer's account and each address book entry.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- The map pin and address autocomplete (CHK-13, P2).
- A district picker from Bosta's own district list (3,580 districts in Arabic and English, compounds among them), so
  the courier receives its district code: it needs a searchable picker that depends on the governorate, which is
  presentation (PRE-03b), and Bosta's account (CR-03). Recorded for the owner.
- A second phone number for the courier (Bosta accepts one): no register row names it.

## Success Criteria

- **SC-001**: Every Egyptian order placed through the checkout carries its building, and its floor and apartment
  unless it is a villa.
- **SC-002**: The courier's address for every such order carries those parts.

## Delivery stage

Contractual stage: S1, with the checkout (#255). Engineering timing: 3 November 2026, the function layer; the designed
form is PRE-03b's (D-14, D-27).

## Assumptions

- The kind of place, building, floor and apartment are free text: Egyptian buildings and floors are named as often as
  numbered ("Ground", "B12").
- Bosta's plugin refuses a first line under 10 characters; a short street name ("Street 9") is refused at the push, as
  before this change, and staff complete it on the order.

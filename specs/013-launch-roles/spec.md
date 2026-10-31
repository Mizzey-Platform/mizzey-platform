# Feature Specification: launch staff roles, the part Stage 3 needs

**Feature Branch**: `013-launch-roles`

**Created**: 2026-10-31

**Status**: Draft

**Work type**: requirement

**Input**: User description: "continue until you finish all waves" (the owner, 31 October 2026), wave 1 of the Stage 3
build plan under D-22: "E-ROLE-1 roles, the part Stage 3 needs: the capabilities the Stage 3 admin screens are checked
against (promotions, orders, refunds kept separate, D-10). CX-01 stays pending; no deferred role is pulled in." #251.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| ROLE-01 | P1 | S1 | Visitor: browse, search, filter, view products, add to cart, temporary wishlist, register, guest checkout |
| ROLE-02 | P1 | S1 | Customer: visitor rights plus account, addresses, orders, tracking, saved wishlist, reviews, coupons |
| ROLE-07 | P1 | S1 | Admin: products, orders, customers, offers, integrations, users |
| ROLE-08 | P1 | S1 | Owner / Super Admin: full access including roles, sensitive settings, audit logs |
| ROLE-09 | P1-L | S1 | Role-based access applied using the standard commerce role set: no staff account receives owner-level access by default |

Story: Functional Specification MS-SPC-2026-032 v1.4, US-21-01 (Staff users and roles), the criteria that trace to
the rows above.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| ROLE-03 to ROLE-05, ROLE-10 | DEF | Not built |
| ROLE-06 | DEF in the signed register | The Accountant role: CX-01, resolved by the owner (D-10, D-11 item 6), not acknowledged by the client. Not built in this slice; every criterion that would trace it is `pending CX-01` |
| ROLE-06a | P2 | Not built |
| ADM-130, ENT-16 | P1-L, P1 | Staff users and granular permissions; this slice adds the first granular capability, the rest are WooCommerce's |
| ADM-131 | P1 | Two-factor sign-in for administrators and shop managers, already in force (specs/008) |
| PAY-07, PAY-08 | P1 | Refunds; the refund flow is #283's and Stage 4's. This slice only keeps the permission to refund separate |

## Open contract items

- CX-01 (ROLE-06): pending the client's acknowledgement of MS-CLR-2026-037. Nothing here depends on it.

## Contractual acceptance criteria [checked]

| AC | Criterion | Register | Status |
|---|---|---|---|
| AC-1 | The Owner / Super Admin role holds every capability, including roles and users, sensitive settings, the logs, and refunds | ROLE-08 | provisional |
| AC-2 | The Admin role manages products, orders, customers, offers (promotions) and the store's settings and integrations, and may refund | ROLE-07 | provisional |
| AC-3 | No account receives owner-level access by default: a new account's role is not a staff role, and the Admin role holds no owner-level capability and can edit customers only | ROLE-09 | provisional |
| AC-4 | Refunding is a capability of its own: without it a refund is refused on the order screen and over the REST API, even to staff who edit orders; refunds made by the store's own code are unaffected | ROLE-07, ROLE-08 | provisional |
| AC-5 | Visitors and customers hold no staff capability | ROLE-01, ROLE-02 | provisional |

Provisional: verified by automated tests; acceptance follows manual testing on staging and the client's approval.
What visitors and customers can do on the storefront is delivered and accepted by the storefront slices (#305 to
#309, #255); AC-5 checks only that they hold no staff capability.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Staff get their job's access and no more (Priority: High)

**Independent Test**: read each role's capabilities; act as a shop manager and as an administrator.

**Acceptance Scenarios**:

1. **Given** an administrator, **When** they manage users and roles, settings or refunds, **Then** they can.
2. **Given** a shop manager, **When** they open products, orders, customers, coupons or WooCommerce settings, **Then**
   they can; **When** they try plugins, themes, site options or giving a user a staff role, **Then** they cannot.
3. **Given** a new account, **When** it is created, **Then** it is not a staff account.

### User Story 2 - Refunds stay a separate permission (Priority: High)

**Independent Test**: a shop manager with the refund capability removed for them alone.

**Acceptance Scenarios**:

1. **Given** staff who edit orders without the refund capability, **When** they refund on the order screen or through
   the REST API, **Then** the refund is refused and the order is unchanged.
2. **Given** the same staff with the capability, **When** they refund, **Then** the refund is made.
3. **Given** the store's own code (a payment provider's callback, a cancellation), **When** it refunds with nobody
   signed in, **Then** the refund is made.

### Edge Cases

- A refund deleted on the order screen is a refund action too: it needs the same capability.
- The capability is added to the two roles once and kept; the owner can remove it from Admin without rebuilding the
  role (D-11 item 7: "implemented through granular capabilities so that it can be adjusted without rebuilding roles").

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Owner / Super Admin | WordPress's Administrator role | Native, used as is |
| Admin | WooCommerce's Shop manager role: products, orders, coupons, customers (its editable roles are customers only), WooCommerce settings and reports; no site options, plugins or themes | Native, used as is |
| Visitor, customer | No account; WooCommerce's Customer role (read only) | Native, used as is |
| New accounts | WordPress's default role (subscriber); WooCommerce makes customers | Native, checked |
| Refund permission | WooCommerce checks `edit_shop_orders` for refunds (`WC_AJAX::refund_line_items`, `delete_refund`; the REST refunds controller), the same capability as editing orders | Gap: D-10 and D-11 keep refunds separate; one Mizzey capability checked before WooCommerce's handlers |

## Requirements

### Functional Requirements

- **FR-001**: The capability `mizzey_refund_orders` MUST exist on the Administrator and Shop manager roles, added
  once and kept, never re-added after the owner removes it from a role (AC-1, AC-2, AC-4).
- **FR-002**: A refund made or deleted on the order screen, or created or deleted through the REST API, by a signed-in
  user without the capability MUST be refused before WooCommerce acts (AC-4).
- **FR-003**: Refunds made by the store's own code with nobody signed in MUST NOT be affected (AC-4).
- **FR-004**: No role MUST be changed otherwise; the standard role set stands (AC-1 to AC-3, AC-5).

### Key Entities

- **Refund capability**: `mizzey_refund_orders`, the permission to make or delete a refund.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- The Accountant role (ROLE-06, pending CX-01), Customer Service, Warehouse and Marketing roles (DEF), sensitive
  operation audit logging (ROLE-10, DEF).

## Success Criteria

- **SC-001**: No account can refund without the refund capability, by any staff route.
- **SC-002**: No account other than an administrator holds an owner-level capability by default.

## Delivery stage

Contractual stage: S1, delivered with Stage 3 (D-22). Engineering timing: wave 1 of the Stage 3 build plan, from 31
October 2026.

## Assumptions

- "Admin" (ROLE-07) is WooCommerce's Shop manager role and "Owner / Super Admin" (ROLE-08) WordPress's Administrator,
  the standard commerce role set ROLE-09 names. Admin may refund (D-11 item 7: "refunds where the launch refund flow
  permits").
- "Audit logs" (ROLE-08) are the standard platform logs (ROLE-10 is DEF): WooCommerce's logs, which both roles read.

# Feature Specification: payments through Paymob

**Feature Branch**: `028-payments`

**Created**: 2026-11-01

**Status**: Draft

**Work type**: requirement

**Input**: Slice E-ORD-2, issue #283: "Payments through Paymob: verified callbacks, no duplicate charge, refunds from
the order screen". The owner, 1 November 2026, asked for a sandbox payment cycle with test cards so the checkout
scenarios can be run before the merchant account is approved. PAY-13 contracts Paymob "via its official WooCommerce
integration, wrapped in an abstraction layer", so the official plugin is the provider integration and Mizzey's own
code is the layer around it (the contract comes before D-24's preference for our own code). Built now against the
Paymob test mode, as D-10 allows when a sandbox can stand in for the approved account (CR-01).

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| PAY-01 | P1 | S1 | Payment abstraction layer: provider changeable without rebuilding checkout |
| PAY-02 | P1 | S1 | No card data stored on Mizzey servers |
| PAY-03 | P1 | S1 | Secure hosted and tokenised payment flow |
| PAY-04 | P1 | S1 | Webhook handling with signature verification |
| PAY-05 | P1 | S1 | Duplicate protection: a repeated callback or double-click on Place Order never creates a duplicate charge or order |
| PAY-06 | P1 | S1 | Transaction reference, status, amount, currency and timestamps stored against the order |
| PAY-07 | P1 | S1 | Full refund from the order screen |
| PAY-08 | P1 | S1 | Partial refund |
| PAY-09 | P1 | S1 | Cash on Delivery capability. Built and tested. Enabled at launch only if you select it |
| PAY-13 | P1 | S1 | Paymob via its official WooCommerce integration, wrapped in an abstraction layer |
| PAY-14 | P1 | S1 | Card payments with embedded 3-D Secure authentication |
| PAY-15 | P1 | S1 | Mobile-wallet payment capability |
| PAY-16 | P1 | S1 | Cryptographic verification of every payment callback |
| PAY-17 | P1-L | S1 | Refunds initiated from the order screen through the payment provider where the selected Paymob integration supports it; otherwise made through Paymob's own process and recorded on the order |
| PAY-19 | P1 | S1 | Settlement timing documented: paid status is distinct from funds received |
| INT-01 | P1 | S1 | Payment gateway, Paymob |

PAY-05 and PAY-16 are marked [key] in the register. PAY-09 is enabled at launch only if selected under OD-05, and
PAY-15 only if selected under OD-19 and approved by the provider.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| CHK-04 | P1 | Payment method selection is the checkout's row (specs/019); this slice supplies the methods it offers |
| BR-006 | P1 | A refund never exceeds what was paid for an item after discount (`RefundIntegrity`, specs/016); a provider refund passes the same check |
| ORD statuses | P1 | Pending Payment, Payment Failed and Confirmed (#248, specs/021) are the states a callback moves an order through; `TransitionGuard` decides which moves are allowed |
| CHK-12 | P1-E | Stock is reserved at placement (specs/019); a failed or abandoned payment releases it through the existing order paths |
| ROLE-06, AC-11 | | Who may refund: staff without the permission cannot (specs/021's roles) |

## Open contract items

- **CR-01, the merchant account.** Launch needs Paymob's approval of the client's account (commercial registration
  and tax card). The sandbox stands in until then; nothing here is accepted on the sandbox alone.
- **OD-05, cash on delivery.** Built and tested; the launch switch is the client's. No value limit or fee is built,
  as no register row asks for one.
- **OD-19 and the wallet approval.** Wallet payment is built behind its own switch, off until the client selects it
  and Paymob approves it for the account.
- **PAY-17.** The official plugin declares refund support (version 4.1.15, `process_refund`). Paymob's test mode
  refunds through its API: on 1 November 2026 a 50 EGP test card payment (3-D Secure) was partly refunded by 20 EGP,
  and a further refund above the 30 EGP left was refused by Paymob. That the plugin's refund from the order screen
  does the same is confirmed at build; if it does not, the fallback the row names (refund in Paymob's dashboard,
  recorded on the order) is what is built.
- **Design.** The payment-failed and pay-again states on the order-received page follow the low-fidelity checkout
  wireframes (Batch 05); the high-fidelity design is not a predecessor for the function layer.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | Exactly one order and at most one charge when Place Order is pressed twice or the payment page is opened twice | PAY-05 | provisional |
| AC-2 | A repeated or late payment confirmation has no second effect on the order, its stock or its emails | PAY-05 | provisional |
| AC-3 | A failed, declined or abandoned payment never makes the order Paid or Confirmed | PAY-14 | provisional |
| AC-4 | A callback that is unsigned, wrongly signed, for another amount or currency, or for an unknown order is refused and recorded, and changes nothing | PAY-04, PAY-16 | provisional |
| AC-5 | A card payment runs on Paymob's hosted page with 3-D Secure; no card number, expiry or security code reaches Mizzey's database, logs or emails | PAY-02, PAY-03, PAY-14 | provisional |
| AC-6 | The order stores the provider's transaction reference, status, amount, currency and the time of each payment event | PAY-06 | provisional |
| AC-7 | Staff with the permission refund in full or in part from the order screen; a partial refund cannot exceed what was paid after discount | PAY-07, PAY-08, PAY-17 | provisional |
| AC-8 | The checkout talks to a payment provider only through Mizzey's provider layer; a second provider can be added without changing the checkout | PAY-01, PAY-13, INT-01 | provisional |
| AC-9 | Cash on delivery and wallet payment each work end to end when switched on, and are not offered when switched off | PAY-09, PAY-15 | pending OD-05 |
| AC-10 | A settlement note says when a paid order's funds reach the client's account, and the admin never shows Paid as funds received | PAY-19 | provisional |

AC-1, AC-2, AC-3 and AC-7 carry the Acceptance and UAT Plan rows AC-06, AC-07, AC-08, AC-10 and AC-11. AC-9's wallet half also waits for OD-19 and Paymob's approval. All are tested automatically against a stand-in provider and run by hand against the Paymob test mode on staging;
acceptance waits for the approved account (CR-01), staging and the client's approval (DOD-04, DOD-09).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Pay by card (Priority: High)

1. **Given** a cart at checkout, **When** the customer chooses card and places the order, **Then** one order is
   created in Pending Payment, the customer is taken to Paymob's hosted page, completes 3-D Secure, and comes back to
   the order-received page with the order Confirmed.
2. **Given** the customer presses Place Order twice, or goes back and presses it again, **When** the orders are
   counted, **Then** there is one order and one payment attempt that can be charged.

### User Story 2 - A payment that does not go through (Priority: High)

1. **Given** a declined card, a failed 3-D Secure check or a closed payment page, **When** the customer returns or
   the callback arrives, **Then** the order is Payment Failed or stays Pending Payment, never Confirmed, and the
   customer can try again on the same order in their language.
2. **Given** no payment arrives within the hold time, **When** WooCommerce's unpaid-order clean-up runs, **Then** the
   order is cancelled and its reserved stock released.

### User Story 3 - Only Paymob's word counts (Priority: High)

1. **Given** a callback with no signature, a wrong signature, a changed amount or currency, or an order it does not
   belong to, **When** it reaches the site, **Then** it is refused, a record is kept without card data, and the order
   is unchanged.
2. **Given** the same verified success callback arrives twice, or after the redirect already confirmed the order,
   **When** it is handled, **Then** nothing happens a second time.

### User Story 4 - Refund from the order screen (Priority: High)

1. **Given** a paid card order, **When** permitted staff refund it in full or a line in part, **Then** Paymob refunds
   that amount, the order records the refund with the provider's reference, and the customer is told.
2. **Given** a refund above what was paid for a line after discount, or staff without the permission, **When** it is
   attempted, **Then** it is refused before anything is sent to Paymob.

### User Story 5 - Cash on delivery and wallet (Priority: Medium)

1. **Given** cash on delivery is switched on, **When** the customer chooses it, **Then** the order is placed without
   a payment page and follows the cash statuses of #248.
2. **Given** wallet payment is switched on and approved, **When** the customer chooses it, **Then** it runs on
   Paymob's hosted flow with the same verification and duplicate rules as cards.

### Edge Cases

- The callback arrives before the customer is redirected back, or the redirect arrives and the callback never does:
  the order follows the verified server callback; the redirect alone confirms nothing it has not verified.
- A verified payment arrives for an order already cancelled (stock released): the money is recorded, the order is not
  silently reopened, and staff are shown that it needs a refund or a manual decision.
- A refund made in Paymob's own dashboard: recorded on the order when Paymob reports it, as PAY-17's fallback asks.
  Paymob reports a refund by sending the payment again, marked refunded, with the total refunded so far (staging
  sandbox, 1 November 2026); the order's refunds are reconciled with that total, never counted from a refund's own
  notice.
- A partial refund the provider refuses: WooCommerce's refund is not kept, and staff see the provider's reason.
- Arabic and English: every customer and staff message has both (FIX-04), including the payment-failed state.
- A signed transaction is tied to its order by what Paymob signs: a transaction of an integration the store does not use is refused; a failure of another amount cannot fail an order awaiting payment; once an order is paid, a transaction of another Paymob order is handled as a second payment (refunded, D-25) and never shows that order's confirmation page (audit, 2 November 2026).
- A verified card payment for an order that now pays another way (the customer went back and chose cash on delivery, then paid in the old tab) is recorded on the order once and flagged for staff to refund or take; the gateway does not act on it.
- A success of the wrong amount returned again and again is flagged once.
- FR-011: staff who manage the store see a notice while the stored Paymob keys are test keys on the live store, or live keys on any other site.
- NFR-10: a callback refused because no secret is stored, its signature does not verify, or its amount does not match is logged as an error, which the monitor raises; other refusals are warnings.
- PAY-05: Place Order pressed twice at the same moment makes one order with one reservation per line (t60, over HTTP).

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Hosted card page, 3-D Secure, wallets | The official Paymob plugin (PAY-13), on WooCommerce's block checkout | Native (the plugin), configured |
| Callback signature check | The official plugin checks Paymob's HMAC | Native, to be proven by forged-callback tests, with Mizzey's amount, currency and order check added |
| Refunds through the provider | The plugin's `process_refund`, through WooCommerce's refund screen | Native (the plugin), with BR-006's check before it |
| Order statuses after payment | WooCommerce's `payment_complete` and failed paths, mapped by `TransitionGuard` | Native, mapped (#248) |
| Provider changeable without a checkout rebuild | None | Gap: Mizzey's provider layer |
| One order and one charge per attempt | WooCommerce's block checkout reuses the draft order; the plugin's own repeat handling is not documented | Gap: Mizzey's duplicate guard on placement and on callbacks |
| Payment record (reference, status, amount, currency, times) | Partly: WooCommerce stores a transaction id and paid date | Gap: the rest of PAY-06 on the order |
| No card data in logs | Not guaranteed for plugin debug logs | Gap: logging off on production, and a scrub check in the tests |
| Settlement note | None | Gap: a written note (PAY-19) |

## Requirements

### Functional Requirements

- **FR-001**: The checkout MUST reach a payment provider only through Mizzey's provider layer, which names the
  provider's methods, starts a payment, reads a verified result and asks for a refund. Paymob, through its official
  plugin, MUST be the first provider (AC-8).
- **FR-002**: Placing an order MUST create at most one order per checkout and at most one chargeable payment attempt
  per order, whatever the number of presses, reloads or returns to the payment page. A second verified payment of an
  order already paid MUST be refunded in full through the provider at once, and only once; when the provider does not
  make the refund, staff MUST be flagged to make it (AC-1; D-25).
- **FR-003**: Every payment callback and redirect MUST be accepted only when its signature verifies, and its amount,
  currency and order match the order as placed; anything else MUST be refused, recorded without card data, and change
  nothing (AC-4).
- **FR-004**: A verified result MUST change the order at most once; a repeat MUST have no second effect on status,
  stock, emails or the payment record (AC-2).
- **FR-005**: Only a verified success MUST move an order to Confirmed; a failure MUST move it to Payment Failed or leave
  it in Pending Payment, and the customer MUST be able to try again on the same order (AC-3).
- **FR-006**: No card number, expiry or security code MUST be stored, logged or emailed by the site; the payment page
  MUST be Paymob's (AC-5).
- **FR-007**: Each payment event MUST store against the order the provider's reference, status, amount, currency and
  time (AC-6).
- **FR-008**: Staff with the refund permission MUST be able to refund in full or in part from the order screen through
  the provider, under BR-006's limit; a refused provider refund MUST leave no refund on the order. If the sandbox shows
  the plugin cannot refund, refunds MUST instead be recorded on the order after being made in Paymob's dashboard (AC-7).
- **FR-009**: Cash on delivery and wallet payment MUST each have a switch, off by default on production, and MUST be
  offered only when on (AC-9).
- **FR-010**: A settlement note MUST state when funds from a paid order reach the client, and staff screens MUST say
  Paid, not received (AC-10).
- **FR-011**: Provider keys MUST live outside the repository and the database export, and test keys MUST never reach
  production.
- **FR-012**: Every message MUST be translated (FIX-04).
- **FR-013**: Only the parts of the provider's plugin the store uses MUST be reachable: entry points of features the
  store does not run MUST be refused to everyone, and the plugin's settings MUST need staff who manage the store
  (PAY-02, PAY-03; D-21).

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- Instalments and buy now pay later (PAY-20), kiosk payment (PAY-21), Instapay (PAY-22), COD verification by OTP or
  WhatsApp (PAY-10).
- A second provider (Kashier or another); the layer allows it, nothing is built for it.

## Success Criteria

- **SC-001**: In a sandbox run of every scenario above, no order is confirmed without a verified payment and no order
  or charge is ever doubled.
- **SC-002**: A search of the database, the logs and the emails after the run finds no card number.

## Sandbox prerequisites

- Paymob test mode on the account under review: the test secret, public and API keys, the test card integration and
  the HMAC secret, taken from Paymob's dashboard by the account holder and placed on staging only.
- Paymob's server callback must reach staging. Staging sits behind Cloudflare Access, so the callback path alone needs
  an Access bypass (a change to the owner's Cloudflare settings, made only on his approval).
- Paymob's published test cards, for success, decline and 3-D Secure.

## Delivery stage

Contractual stage: S1. Engineering timing: November 2026, the function layer on the sandbox ahead of CR-01.

## Assumptions

- Mizzey's layer verifies every callback's signature itself, and checks its amount, currency and order, before the
  official plugin acts on it; the plugin's own checks are not relied on.
- Paymob's test mode works on the account still under review: confirmed on 1 November 2026 with its test keys, a test
  card integration and Paymob's published test card.

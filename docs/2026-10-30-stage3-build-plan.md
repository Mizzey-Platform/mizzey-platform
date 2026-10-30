# Stage 3 build plan: buying works end to end

**Status: proposed for owner approval with D-22. Internal: not a client confirmation, adds no scope, changes no
signed document.** Written 30 October 2026, on the owner's ruling of the same day: build Stage 3 now, while the brand
identity is awaited, and deliver it in contract order (Stage 2 first, then Stage 3).

## 1. What Stage 3 is

The Statement of Work: "Cart, checkout, guest checkout, accounts, and a complete order placed successfully", 46,250
EGP. The Project Plan (section 3.3) adds the wishlist, reviews, discount codes, the launch business rules, stock
handling behind an adapter, and the initial catalogue migration. Gate: the client places a test order from end to
end on staging.

Payment (Paymob), cash on delivery as a live payment method, Bosta, tracking and the ERP integration are Stage 4.
The Stage 3 test order is placed with WooCommerce's native cash-on-delivery method switched on in staging only; the
launch switch stays with the client (OD-05).

## 2. The rule that lets this start now

D-14 froze functional work until the design was approved, and D-20 kept that freeze for the storefront (D8) and
kept the stage order (D2). D-22 replaces D-20 D2 and narrows D8 for the slices below:

- **Build now, the function layer:** server-side behaviour, data, validation, admin settings, integration seams,
  emails' triggers and content, and their tests. Customer screens run on WooCommerce's native blocks with neutral
  tokens, which the backlog already allows ("Structural work proceeds on neutral tokens").
- **After PRE-03b, the presentation layer:** the designed cart, checkout, account, wishlist and review screens,
  the order status labels customers see, and the shared email layout. Built from the approved design as a restyle
  of working screens, not a rebuild.
- **Delivery in contract order:** Stage 3 is not demonstrated, offered for acceptance or invoiced before Stage 2 is
  accepted. Staging carries the work as it merges; no Stage 3 walkthrough is given until then.

Nothing here changes the scope, a register row, an acceptance criterion or the stage boundaries.

## 3. The slices, in build order

Each slice is one requirement pull request with its own spec under `specs/`, Codex cross-review, the full gates and
staging evidence, as for 008 and 009. Ids are the backlog's (`docs/scope/backlog-ownership.json`).

| Wave | Slice | Issue | Build now | Waits for the design | Stands in for a missing input |
|---|---|---|---|---|---|
| 1 | E-ORD-1 order status model | #248 | Status model, validated transitions, queryable status history, the order's language (D6), the Delivered set D-17 needs | Status labels customers see | Bosta status legend: ADR-0002 minimum, mapped in Stage 4 |
| 1 | E-RULES-1 promotion engine | #281 | The PROMO rows WooCommerce lacks (item-count condition, start date, cap, automatic application, brand condition if measured), on native coupons | None (admin only) | None |
| 1 | E-ERP-1 ERP adapter seam | #245 | One stock interface, both language records resolving to one stock identity, contract tests against a mock | None | PRE-09: mock and contract only (M-3) |
| 2 | E-RULES-2 launch business rules | #290 | BR-001, BR-002, BR-004, BR-006, BR-008 to BR-010; BR-003 and BR-007 through the seam | None | OD-03 and OD-04: the recorded example defaults, admin-configurable (C7) |
| 2 | E-ADM-7 promotions in admin | #289 | Create, schedule, pause, reactivate, usage kept | None | None |
| 2 | E-SF-7 accounts (rest of #305) | #305 | Registration, one account per email across manual and Google sign-in, guest checkout and guest conversion (AUTH-07, AUTH-11), consent kept separate (AUTH-09); AUTH-15 is done | Sign-in and registration screens | Google OAuth: a developer test client in staging until the client-owned one exists |
| 2 | E-SF-6 cart | #306 | Cart merge on register, sign-in and Google (P-001), guest retention as a setting (CART-16), stock re-validation through the seam, free-shipping prompt data | Cart and mini-cart screens | Guest retention default: proposed 30 days, owner to confirm |
| 2 | E-SF-10 wishlist | #307 | B17 minimal wishlist keyed to the canonical product, guest lifetime, idempotent merge on sign-in (CART-17), recently viewed | Wishlist screens | None |
| 3 | E-SF-8 checkout | #255 | Order placement in both languages, BR-005 price snapshot, ENT-08 order snapshot, CHK validation messages in both languages (CHK-11) | Checkout screens | OD-09 VAT: placeholder fields until the client's answer |
| 3 | E-SF-9 My Account | #308 | Address book, order detail with status history, profile and password on WooCommerce's account details (B9) | Account screens | Invoice download (ACCT-12) follows ADM-92 in #280 |
| 3 | E-SF-11 reviews | #309 | D-17 eligibility on every submission route, moderation, average rating data, the invitation trigger | Review form and display | None |
| 3 | E-NOTF-1 emails, Stage 3 part | #285 | Account and order emails, in the order's language, with triggers and content | Shared email layout and header | OD-01 logo: placeholder; OD-18 sender: staging capture |
| M | Migration | #247, #295, #296, #297 | The run on the #249 validation pass, sources first then translations (A11), terms in both languages, background run, standard export | None | CR-06 and CR-09: fixtures in the documented export format; CR-08: fixture images, no client image fetched before the rights confirmation |

The migration track runs beside waves 1 to 3. It is accepted only on the client's real export (CR-06, CR-09) and
loads images only after the written rights confirmation (CR-08).

## 4. What the client still owes for Stage 3

All were requested in the signed pack (Annex F). None blocks development, because a default, fixture or mock stands
in (D-10). Each blocks final acceptance:

- the decisions on cash on delivery, the welcome discount and free shipping (OD-05, OD-03, OD-04);
- the full catalogue export with SKUs matching the ERP, and a real sample export (CR-06, CR-09);
- written confirmation of image rights (CR-08);
- a Google OAuth client in a client-owned account, for Google sign-in.

## 5. Checks every slice carries

The definition of done (DOD rows), the D-21 quality standard, the full integration suite on a clean baseline, both
languages on staging through real requests, mutation checks on the critical logic (pricing, promotions, refunds,
stock, duplicate orders: DOD-04), and the security check. Every customer-facing string translated (no hard-coded
storefront text, FIX-04). Every public form a slice touches (registration, reviews) carries D-21's spam protection:
a honeypot always, and the challenge service through CoreX Captcha once the owner chooses the provider (D-21 item 7)
and the keys exist in the client's own account.

## 6. Risks

- **Restyle cost.** If the approved design changes a flow, not only its look, the function layer is reworked.
  Mitigation: the flows follow the closed low-fidelity wireframe phase (148 surfaces reviewed, 62 frozen).
- **WooCommerce block extension points.** Some checkout and cart behaviour must hook the Store API, not the
  templates. Each slice's spec names the hook and proves it over HTTP, as 009 did.
- **Timeline.** Stage 3 is weeks 4 to 5 in the plan, about two weeks of work. The brand identity is expected about
  15 working days from 29 October, so waves 1 and 2 and the migration track fit before it; wave 3 may overlap
  Stage 2.

## 7. Not in this plan

Paymob, live cash on delivery, Bosta, tracking, SEO tooling and the ERP integration (Stage 4). Second-release rows
(S2). Any P2, DEF or OUT row.

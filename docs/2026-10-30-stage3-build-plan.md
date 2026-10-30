# Stage 3 build plan: buying works end to end

**Status: proposed for owner approval with D-22. Internal: not a client confirmation, adds no scope, changes no
signed document.** Written 30 October 2026, on the owner's ruling of the same day: build Stage 3 now, while the brand
identity is awaited, and deliver it in contract order (Stage 2 first, then Stage 3).

## 1. What Stage 3 is

The Statement of Work: "Cart, checkout, guest checkout, accounts, and a complete order placed successfully", 46,250
EGP. The Project Plan (section 3.3) adds the wishlist, reviews, discount codes, the launch business rules, stock
handling behind an adapter, and the initial catalogue migration. Gate: the client places a test order from end to
end on staging.

Paymob, Bosta, tracking and the ERP integration are Stage 4. The Stage 3 test order is placed with WooCommerce's
native cash-on-delivery method switched on in staging only, as a fixture. PAY-09 stays owned by #283, and the launch
switch stays with the client (OD-05).

## 2. The rule that lets this start now

D-14 froze functional work until the design was approved, and D-20 kept that freeze for the storefront (D8) and
kept the stage order (D2). D-22 replaces D-20 D2 and narrows D8 for the slices below:

- **Build now, the function layer:** server-side behaviour, data, validation, admin settings, integration seams,
  email triggers and content, and their tests. Customer screens run on WooCommerce's native blocks with neutral
  tokens, which the backlog already allows ("Structural work proceeds on neutral tokens").
- **After PRE-03b, the presentation layer:** the designed cart, checkout, account, wishlist and review screens, the
  status labels customers see and the shared email layout. Some of it needs block-checkout and cart extensions in
  JavaScript (cart to wishlist, the free-shipping prompt, address selection, checkout review and errors), so it is
  integration work with its own budget, not a stylesheet pass. The D-21 performance and accessibility gates apply
  to every customer screen a slice ships, native ones included, from the first slice.
- **Postponed dependencies.** For the function layer only, D-22 stops treating the design system (#250), the header
  and navigation (#253) and the product page (#254) as blocking predecessors. They stay blocking for the
  presentation layer, and every other recorded dependency stands.
- **Delivery in contract order:** Stage 3 is not demonstrated, offered for acceptance or invoiced before Stage 2 is
  accepted. Staging carries the work as it merges; no Stage 3 walkthrough is given until then.

Nothing here changes the scope, a register row, an acceptance criterion or the stage boundaries.

## 3. The slices, in build order

Each slice is one requirement pull request with its own spec under `specs/`, the full gates and staging evidence, as
for 008 and 009, reviewed by Codex as a second opinion. Ids are the backlog's (`docs/scope/backlog-ownership.json`).
Each slice's spec names the WooCommerce extension points it uses (Store API, block extension data, server hooks) and
proves them over HTTP and in a browser, in both languages.

| Wave | Slice | Issue | Build now | Waits for the design | Stands in for a missing input |
|---|---|---|---|---|---|
| 1 | E-ORD-1 order status model | #248 | Status model, validated transitions, queryable status history, the order's language (D6), the Delivered set D-17 needs | Status labels customers see | Bosta status legend: ADR-0002 minimum, mapped in Stage 4 |
| 1 | E-RULES-1 promotion engine | #281 | The PROMO rows WooCommerce lacks (item-count condition, start date, cap, automatic application, brand condition if measured), on native coupons | None (admin only) | None |
| 1 | E-ERP-1 ERP adapter seam | #245 | One stock interface, both language records resolving to one stock identity, contract tests against a mock | None | PRE-09: mock and contract only (M-3) |
| 1 | E-ROLE-1 roles, the part Stage 3 needs | #251 | The capabilities the Stage 3 admin screens are checked against (promotions, orders, refunds kept separate, D-10) | None | CX-01 stays pending; no deferred role is pulled in |
| 2 | E-RULES-2 launch business rules | #290 | BR-001, BR-002, BR-004, BR-006, BR-008 to BR-010; BR-003 and BR-007 through the seam | None | OD-03 and OD-04: the recorded example defaults, admin-configurable (C7) |
| 2 | E-ADM-7 promotions in admin | #289 | Create, schedule, pause, reactivate, usage kept | None | None |
| 2 | E-SF-7 accounts, rest of #305 | #305 | Registration, one account per email across manual and Google sign-in, guest checkout and guest conversion (AUTH-07, AUTH-11), consent kept separate (AUTH-09). AUTH-15 is verified locally and its baseline was applied on staging on 30 October; the staging verification in specs/009 (T017) is not yet recorded, and client acceptance is pending | Sign-in and registration screens | Google OAuth: a developer test client in staging until the client-owned one exists |
| 2 | E-SF-6 cart, after #305 | #306 | Cart merge on register, sign-in and Google (P-001), guest retention as a setting (CART-16), stock re-validation through the seam, free-shipping prompt data, related products from the Upsells field (D-19) | Cart and mini-cart screens | Guest retention default: proposed 30 days, owner to confirm |
| 2 | E-SF-10 wishlist | #307 | B17 minimal wishlist keyed to the canonical product, guest lifetime, idempotent merge on sign-in (CART-17), recently viewed | Wishlist screens | None |
| 3 | E-SF-8 checkout | #255 | Order placement in both languages, BR-005 price snapshot, ENT-08 order snapshot, CHK validation messages in both languages (CHK-11), and the stock safeguard below | Checkout screens | OD-09 VAT: placeholder fields until the client's answer |
| 3 | E-ADM-5 invoice document, ADM-92 only | #280 | The invoice ACCT-12 downloads, from an approved plugin before custom code (M-4) | Invoice layout | OD-09 and OD-18: placeholder entity details |
| 3 | E-SF-9 My Account | #308 | Address book, order detail with status history, profile and password on WooCommerce's account details (B9), invoice download after ADM-92 | Account screens | None |
| 3 | E-SF-11 reviews | #309 | D-17 eligibility on every submission route, moderation, average rating data, the invitation trigger | Review form and display | None |
| 3 | E-NOTF-1 emails, Stage 3 part | #285 | Account and order emails, in the order's language, with triggers and content | Shared email layout and header | OD-01 logo: placeholder; OD-18 sender: staging capture |

**The stock safeguard (D-19 item 5).** Only the ERP is authoritative for stock. Native checkout holds, order stock
reduction and restoration are among the unguarded local writers D-19 lists, and switching on cash on delivery in
staging triggers them. Checkout and cart therefore ship with mock-backed tests proving that every stock question and
every stock change goes through the adapter, that validation fails closed when the adapter cannot answer, and that no
WooCommerce path changes stock on its own. This does not choose the PRE-09 mechanism.

**The migration track,** beside waves 1 to 3, in this order:

1. #249: the import specification and validation pass are merged (#423); what remains is the final run on the
   client's real sample export.
2. #247: the migration run, sources first and translations second (A11), with translation groups intact.
3. #295 (terms in both languages, SEO fields) and #296 (images: fixture images until the written rights
   confirmation, no client image fetched before it), each after #249.
4. #297: the background run and the standard export, after #247.

Fixtures in the documented export format verify the code. Contractual migration acceptance needs the client's real
files, as the inputs below record.

## 4. What the client still owes for Stage 3

All were requested in the signed pack (Annex F). None blocks development, because a default, fixture or mock stands
in (D-10). Each keeps the class the backlog records:

| Input | Class | What it gates |
|---|---|---|
| OD-03 welcome discount, OD-04 free shipping | Configurable working default | The client's real values replace the examples |
| OD-05 cash on delivery | Configurable working default | The launch switch only |
| CR-06 real sample export, CR-07 report availability | Blocks final acceptance | The final #249 run and the mapping #295 can do |
| CX-04 import runs | Blocks final acceptance (resolved by the owner in D-12, not client-confirmed) | How runs are recorded, until the client acknowledges it |
| CR-09 full catalogue export | Blocks production or launch only | The full load |
| CR-08 image rights | Content or client input | Whether images are imported at all |
| Google OAuth client in a client-owned account | Vendor or account input | Google sign-in outside staging |

## 5. Checks every slice carries

The definition of done (DOD rows), the D-21 quality standard, the full integration suite on a clean baseline, both
languages on staging through real requests, mutation checks on the critical logic (pricing, promotions, refunds,
stock, duplicate orders: DOD-04), and the security check. Every customer-facing string translated (no hard-coded
storefront text, FIX-04). Every public form a slice touches (registration, reviews) carries D-21's spam protection:
a honeypot always, and the challenge service through CoreX Captcha once the owner chooses the provider (D-21 item 7)
and the keys exist in the client's own account.

## 6. Risks

- **Presentation rework.** If the approved design changes a flow, not only its look, the function layer is
  reworked. The flows follow the closed low-fidelity wireframe phase (148 surfaces reviewed, 62 frozen), which
  limits it; the presentation layer has its own budget (section 2).
- **Block extension points.** Some cart and checkout behaviour must use the Store API and block extensions, not
  classic template hooks. Each slice proves its extension points before it merges.
- **Timeline, an assumption.** The Project Plan gives Stage 3 weeks 4 to 5. The brand identity is assumed about 15
  working days after 29 October (the owner's note of 30 October, not a client commitment). No slice is estimated
  yet; each spec carries its estimate, and this plan is re-checked after wave 1 against that date.

## 7. Not in this plan

Paymob and PAY-09, live cash on delivery, Bosta, tracking and events (#312), SEO tooling and the ERP integration
(Stage 4). The full journey (#322) and the client's acceptance scenarios (#323) close later. Second-release rows (S2).
Any P2, DEF or OUT row.

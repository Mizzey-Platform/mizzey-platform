# Mizzey Operations Platform
## Technical Design, Architecture and Data Model

**Document:** MS-TDD-2026-019
**Version:** 1.0
**Date:** 6 September 2026
**Prepared for:** Mizzey.com
**Prepared by:** Mustafa Shaaban
**Contract role:** Stage 1 deliverable under the Statement of Work, MS-SOW-2026-009, satisfying PRE-01 and PRE-02
**Companion documents:** Feature Register MS-ANX-2026-001, Functional Specification MS-SPC-2026-018, Delivery Backlog MS-BLG-2026-020

---

# PART ONE: THE STACK

## 1. What this document is

Two of the pre development deliverables in Annex A Part Seven are answered here. **PRE-01**, the technical
design and architecture document, and **PRE-02**, the technology stack proposal with its reasoning and its
expected operating cost.

It also answers a question the Feature Register raised and did not settle in engineering terms: your
requirements document section 19.1 required the developer to propose a stack and justify it. Annex A
section 2.3 states the choice. This document states what that choice actually gives you, what it does not,
and what has to be built because of the difference.

**It changes no scope.** Where this document and Annex A differ, Annex A governs.

## 2. The layers

```
WordPress            the content platform, users, roles, media, i18n
  WooCommerce        the commerce engine: products, stock, cart, orders, coupons, refunds
    CoreX            the framework layer: container, config, routing, middleware,
                     admin shell, forms, blocks, mail, media, CLI
      Mizzey         the store: theme, commerce rules, Operations Console,
                     storefront controls, integrations, migration tooling
```

Each layer may depend on the layer below it and never on the layer above. The Mizzey layer is the only one
written for this client. **CoreX is pre existing and maintained independently of this project**, under its
own licence, as Annex A section 2.4 records.

## 3. Why WordPress and WooCommerce

*Developer technical decision, in answer to your requirements document section 19.1. Your document names no
technology, and this choice is the developer's rather than yours.*

The reasoning in Annex A section 2.3 stands. Restated in engineering terms:

**The expensive, dangerous parts are already written and already proven.** Order state machines, stock
decrement under concurrency, tax and discount arithmetic, partial refunds against a gateway, coupon
eligibility. These are where bespoke commerce projects lose their budget, and where a defect costs real
money rather than embarrassment. Section 6 sets out exactly how much of the register they cover.

**Both recommended providers publish maintained integrations for it.** Paymob and Bosta each ship a
WooCommerce plugin. Integrating against raw APIs instead would add cost and risk for no gain (PAY-13,
SHIP-08).

**It does not lock you in.** The data model is standard and exportable, hosting is yours, and the code is
delivered to a repository you own (HND-01, MIG-25).

## 4. Why CoreX, and what state it is actually in

*Developer technical decision. Not referred to in your requirements document.*

CoreX is the developer's pre existing framework layer. **This section states its real condition rather than
its intended one**, because the honest version is the one that can be planned against.

| CoreX capability | State | What Mizzey uses it for |
|---|---|---|
| Container, layered configuration, routing, middleware, security, cache, jobs, notifications | **Stable** | The spine of every Mizzey module |
| Admin product: settings, data models, submissions, access, operations, insights | **Stable** | The Operations Console and the storefront controls are built on this |
| Forms: schema, validation, flow builder, submission pipeline, file uploads | **Stable** | Return requests, contact, import wizard |
| Server rendered blocks | **Stable** | Storefront and campaign page composition |
| Brand tokens and visual foundation | **Stable** | The design system the interface design is implemented into |
| Header, mobile navigation, mega menu, footer system | **Stable** | NAV-01 to NAV-10, SSC-07, SSC-08 |
| Admin experience: shell, data explorer, settings | **Stable** | Every custom admin screen |
| Mail: templates, routes, queue, attempt log | **Stable** | Every notification in Epic E12 |
| Media: WebP pipeline behind an activation gate | **Stable** | NFR-02 image optimisation |
| Client site delivery: `make:site`, `sites/` layout, dist builder, deployment | **Stable** | How Mizzey is generated, built and deployed |
| CLI generators | **Stable** | Module scaffolding |
| **WooCommerce kit** | **Not built** | **Nothing. See below** |

> ### The one that matters: there is no CoreX commerce kit
>
> `corex-kit-woo` is four files, being a blueprint, a gate and a service provider, and its own
> documentation describes it as reserving the seam rather than being a store kit. It is milestone M9 on the
> CoreX roadmap and it is not started.
>
> **The practical consequence for this project.** Every storefront template, every commerce screen, every
> commerce rule in this build is Mizzey specific work. CoreX contributes the framework beneath them, not
> the store itself. Nothing in Annex A promised otherwise: section 2.4 lists what CoreX provides as
> architectural structure, custom application modules, the Operations Console, the storefront management
> controls, the integration adapters and the migration tooling, and every one of those is described there
> as something built for Mizzey on CoreX.
>
> **This is recorded here so that the estimate can be read honestly.** The Delivery Backlog MS-BLG-2026-020
> prices the custom epics at up to 1.7 times the configuration epics for exactly this reason.

**What CoreX does remove** is the six to eight weeks a project of this shape normally spends building
plumbing: dependency injection, environment layered configuration, an admin shell that looks like one
product, a forms pipeline, a mail queue with an attempt log, an audit trail, a WebP pipeline, a release
and deployment path. Those are present, tested and in use.

---

# PART TWO: WHAT IS BOUGHT AND WHAT IS BUILT

## 5. How to read the next two sections

Annex A contains 598 contracted requirements. The single most useful thing this document can do is say,
for each area, whether the behaviour arrives with WooCommerce or has to be written.

Four classifications are used, and they are the same ones the Delivery Backlog estimates against.

| Classification | Meaning |
|---|---|
| **Native** | WooCommerce implements the behaviour. The work is configuration, the theme layer, and proving it |
| **Extend** | Woo provides the mechanism and a hook. The required behaviour is a filter or an action on top |
| **Plugin** | A maintained third party component does it. Named, with its licence cost where it has one |
| **Build** | Nothing provides it. A Mizzey module, written on CoreX |

## 6. Where WooCommerce carries the contract

These are the areas where the platform choice pays for itself. **They are also the areas with the highest
cost of failure**, which is the point.

| Area | Requirements | How | Note |
|---|---|---|---|
| Products, variants, SKU, barcode, stock, pricing | ADM-20 to ADM-45, ENT-01, ENT-02 | **Native** | Variable products cover every variant requirement |
| Categories, brands, attributes, collections | ADM-55 to ADM-57, ENT-03 | **Native** | Brands and collections as taxonomies |
| Cart and checkout mechanics | CART-01 to CART-05, CHK-01 to CHK-12 | **Native** | Address fields need Egyptian structure, section 7 |
| Order lifecycle and statuses | ORD-01 to ORD-12 | **Native** | Two custom statuses added, section 7 |
| **Price snapshot on the order** | **BR-005, ENT-08, AC-09** | **Native** | Order line items store the price charged. **This was the highest risk business rule in the register and the platform already guarantees it** |
| Payments, tokenised hosted flow, no card storage | PAY-02, PAY-03, PAY-14, NFR-06 | **Native** plus provider plugin | Paymob is PCI DSS Level 1 and hosted |
| Full and partial refunds against the gateway | PAY-07, PAY-08, PAY-17, ADM-91 | **Native** | Woo refund API enforces the paid amount ceiling, which is BR-006 |
| Stock validation and decrement under concurrency | BR-003, CHK-12, AC-05 | **Native** | Reduce stock at payment, restore on cancel, which is BR-007 |
| Coupons: percentage, fixed, product and category limits, usage limits, expiry | PROMO-01 to PROMO-15, PROMO-18, PROMO-19 | **Native** | Stacking control is not native, section 7 |
| Guest checkout and account creation | AUTH-07, FIX-09, BR-008 | **Native** | Linking a guest order to a later account is Extend |
| Customer accounts, addresses, order history | ACCT-01 to ACCT-08, ENT-05 | **Native** | My Account endpoints |
| Reviews with moderation | REV-01 to REV-04, ENT-14 | **Native** | WordPress comment moderation |
| Shipping zones and flat rates | SHIP-01, SHIP-15 | **Native** | Zones by governorate as shipping zones |
| Tax classes | ADM-36 | **Native** | Configured once OD-09 is answered |
| REST API and webhooks | INT-16 | **Native** | The adapter layer wraps them |

## 7. Where WooCommerce stops, and what gets written

**This is the real content of the build.** Each row is a place where the register requires behaviour the
platform does not provide. None of them is a blocker. All of them are known quantities, and each is named
so that no one discovers it in week nine.

| # | Requirement | What Woo does | What gets built | Class | Size |
|---|---|---|---|---|---|
| 1 | **Guest cart survives sign in and merges without duplicates** (CART-13, CART-14, CART-15, CART-18, AC-18) | On login, Woo **replaces** the session cart with the persistent cart, or keeps the session cart and discards the saved one, depending on path. It does not merge | A merge on `woocommerce_cart_loaded_from_session`: union by product and variation, quantities combined, capped at available stock, prices re validated, and the customer told what changed | **Build** | Medium, and the most delicate logic in the storefront |
| 2 | **Free shipping on two or more items** (BR-002, FIX-07, AC-01, AC-02) | Free shipping supports a minimum order **amount** or a coupon. It has no item count condition | A shipping method condition on `woocommerce_package_rates`, reading a configured rule, plus the cart progress prompt (CART-07) | **Extend** | Small |
| 3 | **Promotion stacking and priority** (BR-004, PROMO-16, PROMO-17, ADM-111) | Coupons combine freely. There is no priority and no stacking policy | A promotion resolver that evaluates eligible promotions, applies the configured priority, enforces the stacking policy, and itemises what was applied | **Build** | Medium |
| 4 | **Welcome discount on registration** (BR-001, AUTH-06, FIX-06, AC-03) | Nothing automatic | Rule evaluation at registration and at checkout, with value, cap, expiry and eligibility all configured | **Build** | Small |
| 5 | **Returns and refunds workflow** (RET-01 to RET-10, ADM-120 to ADM-125, E13, E20) | Woo has refunds. It has **no returns workflow at all**: no request, no review, no decision, no receipt, no condition, no status the customer can see | A full RMA module: request capture with evidence, eligibility against the configured window, a review queue, approve, reject or partially approve with a recorded reason, receipt and condition, refund against the original payment, and customer visible status at every stage | **Build** | **Large** |
| 6 | **Catalogue migration tooling** (MIG-01 to MIG-25, Section U) | Woo ships a basic CSV importer with fixed column handling, no dry run, no saved mappings, and no safe re run semantics | A mapping driven import engine: encoding detection verified against Arabic, a column mapping interface with saved profiles, preview, a dry run that writes nothing, safe re runs that update by SKU, parent and child rows converted into variable products, background processing, a row level error report, and an import history | **Build** | **Largest single item in the project** |
| 7 | **The Operations Console** (ADM-140 to ADM-161, Section G10) | Woo has separate list screens and a basic status report | A single work queue page: orders requiring action, stock requiring attention, today figures, saved views, quick actions, an inline price and stock grid, bulk operations, and search by customer phone | **Build** | **Large** |
| 8 | **Self service storefront control** (SSC-01 to SSC-29, Section G11) | Woo controls products. It does not control the storefront around them | Content control for home sections, category and brand pages, collections, navigation, footer, campaign pages, all per language, with preview before publish and revert | **Build** | **Large** |
| 9 | **Arabic search that finds what was typed** (SRCH-06, SRCH-07) | WordPress search is a `LIKE` query. It has no synonyms, no correction and no relevance | A curated synonym and correction list, admin managed, applied at query time across the main categories and brands including Franco Arabic spellings | **Build** | Medium. Quality is bounded by the list, which is why the register records it P1-L |
| 10 | **Returned to origin is never Delivered** (ORD-13, SHIP-17, AC-16) | Woo has no such status, and the carrier integration has reported cases of mapping a returned parcel to delivered | Two registered order statuses, plus an explicit carrier status map that is documented and visible in admin, and an unrecognised carrier status that changes nothing and raises for a human | **Extend** | Small, and it prevents an expensive error |
| 11 | **Cash on delivery reconciliation** (SHIP-16, ORD-14) | Woo marks a COD order paid on completion. Collected and remitted are the same thing to it | Collected and remitted tracked separately per order, with the difference reportable | **Build** | Small |
| 12 | **Duplicate order and duplicate charge protection** (PAY-05, NFR-07, AC-06, AC-07) | Some protection exists, and gateway callback idempotency is the integrator responsibility | An idempotency key on order placement, and a processed callback record so a repeated provider callback is a no operation | **Extend** | Small, and non negotiable |
| 13 | **Wishlist** (WISH-01 to WISH-04, WISH-07, ENT-07) | Nothing | A wishlist for accounts and a session wishlist for guests that transfers on sign in, plus recently viewed | **Build** | Small |
| 14 | **Audit log** (ADM-132 to ADM-134, ROLE-10, ENT-17) | Nothing | Actor, action, entity, before and after values, timestamp, searchable, and not deletable from the interface | **Build** on the CoreX audit seam | Medium |
| 15 | **Staff roles beyond the Woo defaults** (ROLE-03 to ROLE-09, ADM-130) | Woo ships Shop Manager and Customer only | Six role definitions with granular capabilities, and the negative tests that prove a role cannot reach what it must not | **Build** | Medium |
| 16 | **Google sign in** (AUTH-10, AUTH-13, AC-19) | Nothing | OAuth sign in, and identity resolution so one email is always one account | **Plugin** or **Build** | Small |
| 17 | **Multilingual product and content** (FIX-04, NFR-04, SSC-12, SSC-21) | Nothing. WordPress is single language | A translation plugin, plus the right to left layout system and locale formatting | **Plugin**, and it has a licence cost. Section 11 | Medium, and it touches everything |
| 18 | **Product feeds and campaign attribution** (MKT-05, MKT-06, MKT-09) | Nothing | Meta and Google Merchant feeds, and campaign parameters preserved to the order record | **Plugin** plus **Extend** | Small |
| 19 | **Redirect manager and missing page log** (MKT-13, MKT-14) | Nothing | Admin managed permanent redirects with loop detection | **Plugin** | Small |
| 20 | **One step account creation after ordering** (AUTH-11) | Woo offers account creation at checkout, not after it from the confirmation with no re entry | Password only account creation from the order confirmation, with the order linked securely | **Build** | Small |

> **The answer to the question this section exists for.** Yes, the specification is implementable on
> WooCommerce, and the platform carries the requirements whose failure would cost money. **Roughly a third
> of the effort is in five Build areas that Woo contributes nothing to**, being returns, the Operations
> Console, the storefront controls, the migration tooling and the search layer. That proportion is normal
> for a store with an operational contract of this depth, and it is what the estimate is built on.

---

# PART THREE: THE BUILD

## 8. Module inventory

Every module is a CoreX service provider inside the Mizzey site plugin, generated with `wp corex make:site
Mizzey`. Each owns its data, exposes its own interfaces, and depends on the platform rather than on its
siblings.

| Module | Owns | Epics |
|---|---|---|
| `Mizzey\Storefront` | Templates, blocks, navigation, home sections, campaign pages | E01, E02, E03, E04, E22 |
| `Mizzey\Cart` | Cart continuity, merge, free shipping progress, cross sell | E05 |
| `Mizzey\Identity` | Google sign in, account resolution, one step creation, session policy | E06 |
| `Mizzey\Checkout` | Egyptian address structure, order placement, idempotency | E07 |
| `Mizzey\Payments` | The payment abstraction and the Paymob adapter | E08 |
| `Mizzey\Shipping` | The carrier abstraction, the Bosta adapter, status mapping, COD reconciliation | E09 |
| `Mizzey\Promotions` | The promotion resolver, stacking, priority, welcome discount, business rules | E14 |
| `Mizzey\Returns` | The RMA workflow, customer side and staff side | E13, E20 |
| `Mizzey\Operations` | The Operations Console, work queues, bulk actions, saved views | E15, E18 |
| `Mizzey\Catalogue` | Product controls, per language content, cost, inventory | E16, E17 |
| `Mizzey\Migration` | The mapping driven import and export engine | E23 |
| `Mizzey\Access` | Roles, capabilities, audit log | E19, E21 |
| `Mizzey\Insight` | Dashboard, reports, analytics events, feeds | E24, E25, E26 |
| `Mizzey\Notify` | Notification routing on the CoreX mail queue | E12 |

## 9. Data model

Annex A Section K names nineteen entities. Fourteen exist in WooCommerce already. **Five are new tables**,
and one is a new field on an existing entity.

| Entity | Where it lives | Note |
|---|---|---|
| ENT-01 Product, ENT-02 Product Variant | Woo products and variations | Native |
| ENT-03 Category, Brand, Collection | Woo taxonomies | Brand and collection as custom taxonomies |
| ENT-05 Customer, Address | WordPress users plus Woo customer data | Egyptian address fields added |
| ENT-06 Cart, Cart Item | Woo session and persistent cart | The merge logic is Mizzey, the storage is Woo |
| ENT-08 Order, Order Item | Woo orders, HPOS tables | **Price snapshot native.** Attribution fields added for MKT-09 |
| ENT-09 Payment | Woo order meta plus a payment record | Transaction reference, status, amount, timestamps |
| ENT-10 Shipment | **New table** | Carrier, tracking, rate, status, and the collected versus remitted pair for SHIP-16 |
| ENT-11 Promotion, Coupon | Woo coupons plus a promotion rules table | Stacking, priority and conditions the coupon schema cannot hold |
| ENT-12 Return Request, Return Item | **New table** | Reason, status, attachments, decision, actor, timestamps |
| ENT-13 Refund | Woo refunds, linked to the return | Native amount ceiling |
| ENT-14 Review | WordPress comments | Native moderation |
| ENT-15 Notification | CoreX mail attempt log | Channel, template, status, provider reference |
| ENT-16 Admin User, Role, Permission | WordPress roles and capabilities | Six role definitions |
| ENT-17 Audit Log | **New table** | Append only from the interface, per ADM-133 |
| ENT-19 Import Run | **New table** | File, mapping, row counts, outcome, actor, timestamp |
| ENT-07 Wishlist | **New table** | Account and guest |
| ENT-04 Inventory Transaction | Second release | Stock levels are native and live at launch |
| ADM-27 Product cost | **New field** on product and variation | Captured from day one so margin reporting can be built later on real history |

**Two design rules apply across all of it.**

**Nothing that WooCommerce owns is duplicated.** Where Woo holds the truth, Mizzey reads it rather than
keeping a copy that can drift.

**Every new table carries actor and timestamp.** The audit requirement is not a feature bolted on at the
end; it is a column pair on everything that changes.

## 10. Integrations

Annex A INT-16 requires every external integration behind an adapter with retries, logging and webhook
handling. That is a single CoreX pattern applied five times.

| Integration | Adapter | Failure behaviour |
|---|---|---|
| Paymob | `Payments\Gateway` interface, Paymob adapter over its Woo plugin | Callback signature verified before anything is acted on. A failed verification is rejected, logged and raised. A repeated callback is a no operation |
| Bosta | `Shipping\Carrier` interface, Bosta adapter | A dispatch failure is reported per order and is safe to retry for the failures only. An unrecognised carrier status changes nothing |
| Transactional email | CoreX mail with a queue and an attempt log | Fail closed by default. A send that cannot be made is visible, not silent |
| Analytics and Tag Manager | A data layer emitter, tags deployed through Tag Manager only | Consent aware. No third party edits site code, per MKT-23 |
| Product feeds | Scheduled generation | A feed that fails to validate is reported rather than published |

**The abstraction is why a provider change is not a rebuild.** Annex A section 2.1 commits to accommodating
a different payment provider without a change fee where the integration requirements are materially
equivalent, and this is the mechanism that makes that commitment safe to give.

## 11. Third party components and their annual cost

**PRE-02 requires the expected operating cost, and this is the part of it the developer controls.** Hosting
and provider fees are separate and are yours (Annex A CR-14, R-14).

| Component | Purpose | Licence | Approximate annual cost |
|---|---|---|---|
| **Translation plugin** | Multilingual products, categories and content, per FIX-04 and SSC-21 | Commercial | **Roughly 100 USD or EUR per year** |
| Google sign in | AUTH-10 | Free tier available | Nil, or low |
| Redirect manager | MKT-13 | Free | Nil |
| Two factor authentication for admin | ADM-131 | Free | Nil |
| Product feeds | MKT-05, MKT-06 | Free options available | Nil |
| WordPress, WooCommerce, CoreX | The platform | Open source, and the Corex licence | Nil |

> ### The multilingual licence, stated plainly
>
> **A fully multilingual WooCommerce store requires a commercial translation plugin.** The free options do
> not translate product data reliably, and FIX-04 places a fully supported Arabic storefront in the first
> release as a fixed decision, not an option.
>
> This is not new scope and it is not a surprise charge from the developer: Annex A R-14 already records
> that certain commercial components require annual licences held in your name, and that these are your
> ongoing operating costs. **It is named here with a figure so that it reaches your budget now rather than
> at launch**, and so that the licence is bought in your name and stays yours.
>
> It is the only component in this build with a meaningful recurring licence cost.

## 12. Environments, deployment and recovery

| Environment | Purpose | Per Annex A |
|---|---|---|
| Local | Development on WAMP | |
| Staging | Every stage gate is reviewed here. Risky changes are never tested on the live site | NFR-09 |
| Production | Provisioned once the hosting instruction and any required authorisation are in place | CR-10, R-03 |

**Deployment** is the CoreX dist builder and verifier, producing a shared host deployable artefact, driven
from the repository. **Rollback is documented and tested before go live**, not written at go live (HND-08).

**Backups** cover the database and media, to the agreed retention, and **the restore procedure is performed
at least once rather than only written** (NFR-08, HND-05). A restore procedure that has never been run is a
document, not a recovery plan.

## 13. Security posture

The controls in NFR-05, NFR-06, AUTH-15, AUTH-16 and ADM-131 are implemented as follows, and none of them
is optional.

| Control | How |
|---|---|
| Card data | Never touches the server. Provider hosted, tokenised (PAY-02, PAY-03) |
| Payment callbacks | Cryptographically verified before being acted on (PAY-16) |
| Passwords | WordPress secure storage, never recoverable in readable form |
| Sessions and login | Rate limited, documented expiry, automated account creation protected against |
| Password reset | Single use and time limited (AUTH-16) |
| Admin | Two factor available and requirable per role (ADM-131) |
| Authorisation | Capability checks on every admin action, with negative tests, per AC-11 |
| Transport | HTTPS throughout |
| Input | Validated and escaped at the boundary |
| Secrets | Environment configuration, never in the repository |
| Dependencies | Version pinned, updated on a schedule, regression tested on staging before production (R-08) |
| Audit | Sensitive operations logged and not deletable from the interface (ADM-133) |

**No guarantee of immunity from all future vulnerabilities is given**, and none can be. The Services
Agreement says the same.

## 14. Performance

| Measure | Approach |
|---|---|
| Images | WebP through the CoreX pipeline, served only when the conversion passes its gate, lazy loaded |
| Caching | Page and object caching, with the cart, checkout and account correctly excluded |
| Delivery | Content delivery network in front of static assets |
| Queries | Product listing and search queries reviewed against the real catalogue size, not against test data |
| Measurement | Measured on staging before launch and recorded |

Sizing follows the stated assumption at OD-15: **up to 5,000 products and 300 orders per day**, with
headroom for short campaign peaks. Growth beyond that is absorbed by resizing infrastructure rather than
re implementing the core (NFR-13), bounded by FIX-10.

**No performance, conversion, traffic or ranking outcome is warranted** (EX-31).

---

# PART FOUR: RISKS THIS DESIGN CARRIES

## 15. Technical risks arising from these choices

Annex A Part Four holds the commercial and compliance risks. These are the engineering ones, and they are
the developer's to manage unless the owner column says otherwise.

| Id | Risk | Owner | Mitigation |
|---|---|---|---|
| **TR-01** | **The cart merge is subtle and its failures are silent.** A customer who loses a cart does not report a defect, they leave | Developer | Automated tests over every path in AC-18, and the merge treated as a key requirement rather than a convenience |
| **TR-02** | **The carrier integration has a known status mapping defect**, with reported cases of a returned parcel showing as delivered | Developer | An explicit documented map, an unrecognised status that changes nothing, and AC-16 tested against real carrier responses in Stage 4 rather than against documentation |
| **TR-03** | **The migration tool is the largest single build and its inputs are outside the developer control.** Amazon report formats change, and the complete listing report is not enabled by default | Shared | The real sample file is required before the build begins (CR-06). The engine is mapping driven rather than format specific, so a changed format is a new mapping and not a new tool |
| **TR-04** | **The translation plugin becomes a dependency of everything a customer reads.** A major version change can affect the whole storefront | Developer | Version pinned, upgraded only on staging with a full bilingual regression pass |
| **TR-05** | **Multilingual content quality is bounded by the client content, not by the system** | **Client** | CR-15. The mechanism is delivered whether or not the words arrive, and a missing translation is reportable before launch rather than discovered by a customer |
| **TR-06** | **Concurrency on the last unit of stock** | Developer | Woo handles the decrement; the tests prove it under concurrent purchase, per US-27-04 |
| **TR-07** | **Third party plugin surface increases the update and security burden after warranty** | **Client** after handover | The plugin count is kept deliberately low, section 11. R-12 applies: an unmaintained site becomes progressively more exposed |
| **TR-08** | **CoreX has no commerce kit, so there is no reference implementation to follow** for the storefront and commerce screens | Developer | Accepted and priced. The Delivery Backlog carries the custom leverage factor for exactly this |

---

*Mizzey Operations Platform. Technical Design, Architecture and Data Model. MS-TDD-2026-019, version 1.0,
6 September 2026. Stage 1 deliverable under the Statement of Work MS-SOW-2026-009, satisfying PRE-01 and
PRE-02. Scope is defined by the Feature Register MS-ANX-2026-001 and by nothing else, including this
document.*

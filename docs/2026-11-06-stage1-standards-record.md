# Stage 1 standards record

Written 6 November 2026. The traceability and evidence record for four Stage 1 PBIs whose own work is a record, not
a build: #269 (the non-functional standards), #271 (the fixed core decisions), #272 (vision, objectives and platform)
and #268 (the definition of done). For each row it names the PBI and the evidence that carries it, or what it waits
for. Every row here is technical evidence; none is client acceptance (constitution M-7), and DOD-09, the client's
approval, is open for every PBI.

Scenario numbers are integration scenarios in `mizzey-site/tests/integration/`. "On staging" means the scenario
passed on the staging server, as recorded in `docs/2026-10-31-stage3-staging-verification.md`,
`docs/2026-11-02-staging-verification.md` or the spec's own `evidence/` folder.

## 1. Non-functional standards (#269)

NFR-06, NFR-07 and NFR-12 are recorded from built work. Their scenarios were run again on 6 November 2026 on main fabda7e (WordPress 7.1.4, WooCommerce 11.1.0, WPML 5.3.0, WooCommerce Multilingual 5.8.0) and all pass: t68, t69, t60, t59, t53, t58, t83, t90 and t22. The concurrency harness ran on staging v0.1.25 the same day (#549).

| Row | What the register asks | Evidence | State |
|---|---|---|---|
| NFR-01 | All screens responsive; testing begins on mobile | Waits for the designed storefront (PRE-03b, #250, brand identity OD-01) | Waits for design |
| NFR-02 | Image optimisation, lazy loading, caching and CDN | Images and lazy loading: specs/032, t72. Caching and CDN wait for the production host (OD-27, OD-14) | Part recorded |
| NFR-04b | Currency, number and date formatting per locale | specs/032, t72 | Recorded |
| NFR-05 | HTTPS, passwords, roles, admin protection, input validation, rate limiting, secrets, updates | specs/007 to 009, 031, 039; t39 to t50, t71, t79; `wp mizzey security check` passes on staging (v0.1.25) | Recorded |
| NFR-06 | No card data stored; the provider's secure pattern followed | Card payments run on the provider's hosted page with 3-D Secure; the store has no card field. t68 sends a full card number inside the provider's payloads and finds it in no order meta, order note, option or payment log (specs/028 AC-5; PAY-02, PAY-03). Mizzey has still to choose its gateway (I-06): a provider is added only through the same payment layer (specs/028 AC-8) and gets the same check | Recorded |
| NFR-07 | Transaction safety and duplicate protection across payments, orders and inventory | Payments: a second payment of a paid order is refunded once by its own reference, a repeated confirmation has no second effect, and one order's transaction cannot pay another (specs/028 AC-1, AC-2; t68). Orders: each line is reserved with the stock source once, a placement retried after a lost answer or a crash takes nothing twice, and a cancellation releases each line once (specs/019 AC-7; t60). Stock: a change is applied once per operation key, from either language record (specs/012; t53); a merged cart has no duplicate line (CART-14; t59). Simultaneous orders: on staging, 12 buyers ordered the last 3 units at the same instant, four times, two of them split between the English and Arabic records; each time 3 orders were confirmed and none oversold (#549). In October, before the checkout reserved through the stock source, the same test oversold on every run. The ERP's own side is settled by PRE-09 and tested in #321 | Recorded, for the store's side |
| NFR-11 | Keyboard use, labels, contrast, alt text, semantic structure | Waits for the designed storefront (#250; the D-21 accessibility rules apply to it). Alt text on imported images: specs/046, t86 | Waits for design |
| NFR-12 | Consent capture, privacy policy, data minimisation | Consent: Google's consent defaults are set to denied before any tag; a banner in the visitor's language records the choice, which governs every tag and the order's campaign record (specs/043 AC-1, AC-2; t83). Data minimisation: registration asks for no more than email, password and an unticked marketing-consent box, recorded apart from the account (specs/017 AC-1, AC-9; t58); the checkout takes one address, used for both delivery and billing (specs/050; t90). Privacy policy: the page and its address exist in both languages (IA-30, specs/003; t22); its text is Mizzey's legal text, needed before launch (I-13), on the trust pages (#310). Data export and deletion requests are NFR-12a, stage S2 | Recorded, except the policy text |
| NFR-13 | Growth absorbed by resizing and extending, not re-implementing | The structure is the FIX-10 row below; the load test waits for the production host | Waits for the host |

## 2. Fixed core decisions (#271)

| Row | Decision | Delivered by | Evidence | State |
|---|---|---|---|---|
| FIX-01 | Products only, not services | #276, #295 | The catalogue loader creates only simple and variable products (specs/046, t86), and every order is shipped (#291, t81). No service, booking or subscription product type is built or installed | Holds |
| FIX-02 | Single-vendor at launch | #251 | The plugin set in `stack.lock.json` has no vendor or marketplace plugin, and the launch roles (#251, t54) have no seller role | Holds |
| FIX-03 | Premium, curated, trend-driven: not a discount store | #250, #282 | Merchandising built: badges (specs/036, t76), manual ordering in categories and collections (specs/061, t101), trending, new-arrival and featured-collection home sections (specs/056, t96). The identity itself is the approved design | Waits for design |
| FIX-04 | English and Arabic with full LTR and RTL | #241 | specs/002, t21, on staging | Holds |
| FIX-05 | Mobile-first, with desktop and tablet | #250, #269 | NFR-01 above | Waits for design |
| FIX-06 | Welcome discount on account creation, admin-configurable | #290 | specs/016, t57, on staging | Holds |
| FIX-07 | Two or more items grants free shipping, admin-configurable | #290 | specs/016, t57, on staging (a custom rule: WooCommerce's free shipping has no item-count condition, P-011) | Holds |
| FIX-08 | Promotion engine managed from admin; critical rules not hard-coded | #281, #289 | specs/011, t52; specs/014, t55; on staging | Holds |
| FIX-09 | Guest checkout, with encouragement to register | #305 | specs/017, t58 (AUTH-07 purchase without an account, AUTH-11 the offer on the order confirmation), on staging | Holds |
| FIX-10 | Modular, extensible engineering; no guarantee for a future P2 or P3 | #245, #283 | The stock source sits behind one adapter (specs/012, t53); payments sit behind one provider layer, so a second provider is added without changing the checkout (specs/028 AC-8, t68); decisions are recorded in `docs/adr/` | Holds, as a principle |

## 3. Vision, objectives and platform (#272)

| Row | What the register says | Evidence | State |
|---|---|---|---|
| VIS-01 | Premium store, large curated selection across categories | Categories and collections managed from admin (specs/061, t101); the selection is Mizzey's product sheet (I-11) | Waits for the catalogue and design |
| VIS-02 | Focus categories | Mizzey's category list (29 October 2026) is the category structure; categories and collections are managed from admin (specs/061, t101) | Waits for the catalogue |
| VIS-03 | Easy discovery and purchase: search, filters, product pages, cart and account | Search specs/058, t98; filters and sorting specs/057, t97; product page specs/055, t95; cart t59; checkout t60; account t63. The designed pages wait for #250 | Functions built; waits for design |
| VIS-04 | Audience: Class A, upper-middle | A positioning row, met by the design and the content | Waits for design |
| VIS-05 | Authenticity, delivery speed, easy returns, an organised experience | Delivery through Bosta with its status mapping (#291, t81); refunds from the order screen (#292, t69); the returns and trust pages (#310) wait for Mizzey's policy text (I-13) | Part built |
| VIS-06 | New, trending, imported products found quickly | Trending, new-arrival and featured-collection home sections (specs/056, t96); badges (specs/036, t76); manual ordering in categories and collections (specs/061, t101) | Functions built; waits for design |
| OBJ-01 | Increase conversion | No figure is guaranteed. Measurement is in place: the funnel events (specs/044, t84) and Tag Manager after consent (specs/043, t83) | Measurement built |
| OBJ-02 | Raise order value: free-shipping threshold, bundles, cross-sell | Free shipping on two items (t57); the promotion engine (t52); cart suggestions from related products (specs/018 AC-5, t59) | Built |
| OBJ-03 | A registered customer base for remarketing | Accounts and guest conversion (specs/017, t58); marketing consent recorded apart (AUTH-09, t58); welcome discount (t57) | Built |
| OBJ-04 | A premium experience that builds trust | The approved design (#250) and the trust pages (#310) | Waits for design and content |
| OBJ-05 | Products, inventory, orders and offers managed without daily dependence on the developer | Product management (#276, t76), everyday admin tasks (#278, t77), orders (#280, t70), customers (#279, t73), promotions (#281, #289; t52, t55) and the dashboard (#294, t75), all on staging; storefront self-service (#311, t100). The register's test, Mizzey changing a price, adding a product and running an offer unaided, is the walkthrough (#267) | Built; the unaided test waits for Mizzey |
| PLT-01 | Version 1 is single-vendor | FIX-02 above | Holds |
| PLT-02 | No seller accounts or commissions at launch | FIX-02 above | Holds |
| PLT-03 | Modular, avoiding needless coupling; no conversion guarantee | FIX-10 above | Holds, as a principle |
| PLT-06 | Responsive, mobile-first website | NFR-01 above | Waits for design |

## 4. Definition of done (#268)

How each criterion is applied to every Stage 1 PBI:

| Row | Criterion | How it is applied | State |
|---|---|---|---|
| DOD-01 | Interface as approved, mobile and desktop | Storefront PBIs wait for the approved design (PRE-03b, #250); admin PBIs use WooCommerce's standard screens | Open for storefront PBIs |
| DOD-02 | Business logic and exception cases, not only the success path | Each spec lists its edge cases, and each requirement PR runs mutations against its scenario (`specs/*/evidence/mutations.*`) | Applied |
| DOD-03 | Validation and errors clear in English and Arabic | Scenarios check the Arabic message wherever one is shown (for example t60, t90, t102) | Applied |
| DOD-04 | Tests per layer, plus manual testing on staging | The full integration suite locally (101 scenarios, 6 November 2026), the tooling unit tests, and each scenario on staging through the request a browser makes (AGENTS.md). Mizzey's manual testing on staging is its acceptance | Applied; manual testing open |
| DOD-05 | Analytics events verified where required | #274 and #312: specs/042 and 044, t82 and t84 | Applied where required |
| DOD-06 | Staff permissions tested | Launch roles (#251, t54), the order screens (t70), agency access (#298, t104), two-factor sign-in (t43, t71) | Applied |
| DOD-07 | No known errors affecting usage | Known third-party defects are recorded, not hidden: Bosta's Create Pickup page fails when Bosta's API cannot be reached (#541) | Applied; third-party defects recorded |
| DOD-08 | Settings needed for operation documented | `docs/security-baseline.md`, `docs/staging-environment.md`, `docs/release-pipeline.md`, `docs/payments-settlement.md`; the handover pack (#324) collects them | Applied; collected at handover |
| DOD-09 | Client approval of acceptance criteria before production | The Stage 1 documents were approved on 3 November 2026. Feature acceptance on staging has not started | Open for every PBI |

Where each Stage 1 PBI's evidence is, generated on 6 November 2026 from `docs/scope/backlog-ownership.json`, the
specs and the scenario headers. PBIs with nothing built yet are left out.

| PBI | Status | Specs | Scenarios | DOD-01 interface |
|---|---|---|---|---|
| #241 | Verified | 002, 004, 005 | t21 | no storefront design of its own |
| #242 | Verified | 003, 005, 038 | t28, t78 | no storefront design of its own |
| #244 | In progress | 005 | - | no storefront design of its own |
| #246 | Verified | 004, 005, 035 | t75 | no storefront design of its own |
| #247 | Verified | 046 | t86 | no storefront design of its own |
| #249 | Verified | 006, 046 | t86 | no storefront design of its own |
| #245 | Verified | 012, 019, 036 | t53, t60, t76 | no storefront design of its own |
| #248 | Verified | 010, 041 | t51, t81 | waits for the approved design (#250) |
| #250 | Ready | 005, 009 | - | waits for the approved design (#250) |
| #251 | Verified | 013 | t54, t70 | no storefront design of its own |
| #252 | Verified | 001 | t105 | no storefront design of its own |
| #253 | Ready | 009, 052, 054 | t92, t94 | waits for the approved design (#250) |
| #254 | Ready | 055 | t95 | waits for the approved design (#250) |
| #255 | Verified | 019 | t60 | waits for the approved design (#250) |
| #266 | Verified | 005 | - | no storefront design of its own |
| #267 | Ready | 037 | t77 | no storefront design of its own |
| #269 | In progress | 007, 017, 028, 032, 043, 050 | t22, t39, t50, t53, t58, t59, t60, t68, t71, t72, t79, t83, t90 | no storefront design of its own |
| #270 | Verified | 039 | t79 | no storefront design of its own |
| #273 | Verified | 040 | t80 | no storefront design of its own |
| #274 | Verified | 042, 043 | t82, t83, t84 | no storefront design of its own |
| #275 | Verified | 007, 008, 031 | t71, t79 | no storefront design of its own |
| #276 | Verified | 036 | t76 | no storefront design of its own |
| #277 | Ready | 061 | t101 | waits for the approved design (#250) |
| #278 | Verified | 037 | t77 | no storefront design of its own |
| #279 | Verified | 033 | t73 | no storefront design of its own |
| #280 | Verified | 023, 030 | t64, t70 | no storefront design of its own |
| #281 | Verified | 011 | t52 | no storefront design of its own |
| #282 | Ready | 061 | t101 | waits for the approved design (#250) |
| #283 | Verified | 028, 029 | t68, t69, t70 | waits for the approved design (#250) |
| #284 | Verified | 045 | t85 | no storefront design of its own |
| #285 | Verified | 021 | t62 | waits for the approved design (#250) |
| #286 | Verified | 034 | t74 | no storefront design of its own |
| #287 | Verified | 038 | t78 | no storefront design of its own |
| #288 | Verified | 042, 043, 045 | t82, t83, t84, t85 | waits for the approved design (#250) |
| #289 | Verified | 014 | t55 | no storefront design of its own |
| #290 | Verified | 016 | t57 | no storefront design of its own |
| #291 | Verified | 041 | t81 | no storefront design of its own |
| #292 | Verified | 029 | t69, t70 | no storefront design of its own |
| #293 | Verified | 029 | t69, t70 | no storefront design of its own |
| #294 | Verified | 004, 035 | t75 | no storefront design of its own |
| #295 | Verified | 046 | t86 | no storefront design of its own |
| #296 | Verified | 046 | t86 | no storefront design of its own |
| #297 | Verified | 046 | t86 | no storefront design of its own |
| #298 | In progress | 064 | t104 | no storefront design of its own |
| #302 | Ready | 056 | t96 | waits for the approved design (#250) |
| #303 | Ready | 057 | t97 | waits for the approved design (#250) |
| #304 | Blocked | 058 | t98 | waits for the approved design (#250) |
| #305 | Verified | 007, 009, 017, 018, 024 | t58, t59, t65 | waits for the approved design (#250) |
| #306 | Verified | 011, 018 | t52, t59 | waits for the approved design (#250) |
| #307 | Verified | 015 | t56 | waits for the approved design (#250) |
| #308 | Verified | 022, 023 | t63, t64 | waits for the approved design (#250) |
| #309 | Verified | 020, 025 | t61, t66 | waits for the approved design (#250) |
| #310 | Ready | 059 | t99 | waits for the approved design (#250) |
| #311 | Ready | 060 | t100 | waits for the approved design (#250) |
| #312 | Blocked | 042, 044 | t82, t84 | no storefront design of its own |
| #313 | Blocked | 042 | t82, t84 | no storefront design of its own |
| #314 | In progress | 062 | t102 | no storefront design of its own |
| #316 | In progress | 026, 049 | t67, t88 | no storefront design of its own |
| #317 | Blocked | 026 | t67 | no storefront design of its own |

#268 closes when the last Stage 1 PBI is accepted. Until then this table is the record, regenerated as PBIs move.

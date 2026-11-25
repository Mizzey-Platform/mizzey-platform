# Feature Specification: the Stage 2 shop front, built from the approved design

**Feature Branch**: `066-stage-2-shop-front`

**Created**: 2026-11-24

**Status**: Draft

**Work type**: requirement

**Input**: Project Plan section 3.2, "Stage 2. Shop front built": "The approved design built: home page, category and
collection pages, product pages, search and filtering with the curated Arabic synonym and correction list. Arabic
and English throughout, with right to left layout, on mobile and desktop." The owner's rulings of 24 November 2026:
the client approved the design direction (Concept 1, Clay); Stage 2 is closed completely
first, its screens drawn in full before they are built; the screens of later stages are drawn just before their
stage is built.

This spec is the **presentation layer** of the shop front. The behaviour under it is already built, merged and on
staging (D-27, specs/050 to specs/064 and the earlier specs they name): this spec rebuilds none of it. It replaces
the neutral native screens with the designed ones, in `mizzey-theme/` and the section library's blocks in
`mizzey-site/`. The Statement of Work, section 9.1, delivers the interface design "as working HTML and CSS screens",
so the screens these slices build are also the form in which the Stage 2 part of the design reaches the client.

It is one spec with twelve slices. Each slice is one pull request, built test first, and compared with its design
board before it merges.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| NAV-01 | P1 | S1 | Logo |
| NAV-02 | P1 | S1 | Language switcher (AR / EN) |
| NAV-03 | P1 | S1 | Clear, prominent search bar |
| NAV-04 | P1 | S1 | Account entry point |
| NAV-05 | P1 | S1 | Wishlist entry point |
| NAV-06 | P1 | S1 | Cart icon with live item count |
| NAV-07 | P1 | S1 | Mega menu / category navigation, admin-manageable |
| NAV-08 | P1 | S1 | Mobile: simplified header |
| NAV-09 | P1 | S1 | Mobile: quick search |
| NAV-10 | P1 | S1 | Mobile: hamburger menu |
| HOME-01 | P1 | S1 | Hero banner / slider, admin-managed |
| HOME-02 | P1 | S1 | Trending products section |
| HOME-03 | P1 | S1 | New arrivals section |
| HOME-04 | P1 | S1 | Featured collections section |
| HOME-05 | P1 | S1 | Category shortcuts |
| HOME-06 | P1 | S1 | Brand highlights |
| HOME-07 | P1 | S1 | Promotional / offer banners |
| HOME-08 | P1 | S1 | Trust signals: authenticity, delivery, returns |
| HOME-09 | P1 | S1 | Newsletter / account signup prompt |
| HOME-10 | P1 | S1 | Section content editable without a developer |
| HOME-11 | P1-L | S1 | Section order changeable without a developer |
| PLP-01 | P1 | S1 | Product grid with image, name, price, badge |
| PLP-02 | P1 | S1 | Sale price displayed alongside original |
| PLP-03 | P1 | S1 | Stock status indication, from your ERP (ERP-02) |
| PLP-04 | P1 | S1 | Quick add to cart |
| PLP-05 | P1 | S1 | Quick add to wishlist |
| PLP-06 | P1 | S1 | Filter by category |
| PLP-07 | P1 | S1 | Filter by brand |
| PLP-08 | P1 | S1 | Filter by price range |
| PLP-09 | P1 | S1 | Filter by attribute (size, colour, etc.) |
| PLP-10 | P1 | S1 | Filter by availability |
| PLP-12 | P1 | S1 | Sort by newest |
| PLP-13 | P1 | S1 | Sort by price ascending / descending |
| PLP-14 | P1 | S1 | Sort by best selling |
| PLP-15 | P1 | S1 | Sort by relevance |
| PLP-16 | P1 | S1 | Pagination or infinite scroll |
| PLP-17 | P1 | S1 | Result count displayed |
| PLP-18 | P1 | S1 | Active filters visible and individually removable |
| PLP-19 | P1 | S1 | Empty-state handling with suggestions |
| PLP-20 | P1 | S1 | Category banner and description |
| PLP-21 | P1 | S1 | Breadcrumb navigation |
| PLP-22 | P1 | S1 | Mobile-optimised filter drawer |
| SRCH-03 | P1 | S1 | Autocomplete / suggestions as you type |
| SRCH-04 | P1 | S1 | Search results page with the full filter set |
| SRCH-05 | P1 | S1 | Zero-result page with alternative suggestions |
| SRCH-06 | P1-L | S1 | Arabic search handling: synonym and correction list covering main categories and brands, including common Franco-Arabic spellings |
| SRCH-06a | P1 | S1 | Search operates correctly in both English and Arabic |
| PDP-01 | P1 | S1 | Image gallery with zoom |
| PDP-02 | P1 | S1 | Video support |
| PDP-03 | P1 | S1 | Product name and brand |
| PDP-04 | P1 | S1 | Price, sale price, saving displayed |
| PDP-05 | P1 | S1 | Variant selection (size, colour, etc.) |
| PDP-06 | P1 | S1 | Stock status per variant, read from your ERP (ERP-02) |
| PDP-07 | P1 | S1 | Quantity selector |
| PDP-08 | P1 | S1 | Add to Cart |
| PDP-09 | P1 | S1 | Buy Now |
| PDP-10 | P1 | S1 | Add to Wishlist |
| PDP-11 | P1 | S1 | Short description / key features |
| PDP-12 | P1 | S1 | Full description tab |
| PDP-13 | P1 | S1 | Specifications table |
| PDP-14 | P1-L | S1 | Delivery estimate and shipping information |
| PDP-15 | P1 | S1 | Returns policy summary |
| PDP-16 | P1 | S1 | Authenticity guarantee statement |
| PDP-17 | P1-L | S1 | Customer reviews display |
| PDP-18 | P1 | S1 | Related products |
| PDP-19 | P1 | S1 | Recently viewed |
| PDP-21 | P1 | S1 | Share buttons |
| PDP-22 | P1 | S1 | Out-of-stock handling with clear messaging, on the figure your ERP reports |
| IA-04 | P1 | S1 | Collections |
| IA-05 | P1 | S1 | Brands |
| SSC-01 | P1 | S1 | Every storefront text, image and link is editable from the admin, nothing a customer reads is locked in code |
| SSC-02 | P1 | S1 | Home page sections: content, images, calls to action, and order |
| SSC-03 | P1 | S1 | Show or hide any home page section without deleting its content |
| SSC-04 | P1 | S1 | Category page banners, descriptions and hero imagery, per category |
| SSC-05 | P1 | S1 | Brand page content, per brand |
| SSC-06 | P1 | S1 | Collection page content and product ordering, per collection |
| SSC-07 | P1 | S1 | Header navigation and mega menu structure |
| SSC-08 | P1 | S1 | Footer content, links and columns |
| SSC-09 | P1-L | S1 | Promotional banners: create, schedule, target to a page, and remove |
| SSC-12 | P1 | S1 | All of the above editable independently in English and Arabic |
| SSC-13 | P1 | S1 | Preview any change before it goes live |
| MER-01 | P1 | S1 | Product badges, New, Trending, Limited, Best Seller |
| MER-06 | P1 | S1 | Premium visual treatment: imagery, typography, spacing consistent with brand |
| MER-07 | P1 | S1 | Discount presentation consistent with premium positioning, not discount-store styling |
| REV-04 | P1 | S1 | Average rating on product page and listing |
| CART-12 | P1 | S1 | Mini-cart in header |
| CMS-10 | P1 | S1 | WhatsApp click-to-chat contact link |
| MKT-10 | P1-L | S1 | Consent management and cookie banner, configured for consent-aware tracking |
| FIX-04a | P1 | S1 | English is the primary and default storefront language. Arabic is the second supported language, fully delivered at launch. Client clarification, not a requirement of the SRS |
| NFR-04 | P1 | S1 | English left-to-right as the default locale, Arabic right-to-left fully supported |
| NFR-04a | P1 | S1 | No hard-coded text anywhere in the storefront interface |
| NFR-11 | P1-L | S1 | Keyboard usability, labels, contrast, alt text, semantic structure |

Accepting PBIs, unchanged (`docs/scope/backlog-ownership.json`): NAV rows #253; HOME rows #302; PLP rows #303; SRCH
rows #304; PDP rows #254; SSC rows #311; MER rows #282; IA-04 and IA-05 #242; REV-04 #309; CART-12 #306; CMS-10 #310;
MKT-10 #288; FIX-04a, NFR-04 and NFR-04a #241; NFR-11 #269. Each slice names the PBIs it serves. The Stage column is
the register's release value (S1, the first release); the Project Plan's Stage 2 is where the work is delivered.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| PRE-03b | DLV | The interface design, owned by #250. The slices are built from its Stage 2 boards; its acceptance is #250's, not this spec's |
| SRCH-01, SRCH-02, SRCH-07, SRCH-08 | P1 | What the search finds, the staff's synonym list and the term log: specs/058, unchanged |
| PDP-24 | P1 | Structured data: specs/038, unchanged; the designed page keeps it |
| PDP-20, PDP-23 | P2 | Frequently bought together, back-in-stock signup: not built |
| PLP-11 | P1-L (S2) | Filter by rating: the second release |
| IA-33 | P1-L (S2) | Brand landing pages: the second release. The brand page here is the brand's product listing with its content (IA-05, SSC-05) |
| IA-12, WISH-08 | P2 | Comparison: not built, and not owed because Quick Look was given (D-26) |
| REV-06 | P2 | Photo and video reviews: not delivered by D-23's gift |
| HOME-12, HOME-13 | P2, P3 | Section scheduling and a personalised home page: not built |
| SSC-10, SSC-11, SSC-14 | P1, P1-L | Static pages, campaign pages and revisions: the library built here is what they use; their templates come with the content pages, a later stage |
| SSC-20 to SSC-29 | P1, P1-E, P1-L | Product control in the admin: specs/036, specs/060 and WooCommerce, unchanged |
| ERP-02, ERP-03 | P1-E | Where the stock figure comes from: specs/012, specs/026, specs/049 and specs/062; pending the ERP Integration Specification |
| CART-01 to CART-11 | P1, P1-L | The cart page: Stage 3. Only the header's mini cart (CART-12) is here |
| WISH-01 to WISH-04, WISH-07 | P1 | The wishlist and recently viewed behaviour: specs/015, unchanged; the wishlist page is a later stage |
| NFR-01, NFR-02, NFR-14 | P1 | Mobile first, performance and browsers: #269 and #241; measured on these screens by the D-21 gates |
| ADM-159 | P1 | The administration is English only; the editor screens of the library are English |

## Open contract items

- No open contradiction touches these rows.
- The ERP Integration Specification is not approved, so the criterion on the stock status shown (AC-6) is
  `provisional` (M-3).
- The client's approval of the design direction was given on 24 November 2026 and is recorded by the owner. Its
  written confirmation is collected before Stage 2 is submitted for acceptance. It blocks final acceptance, not this
  build (M-10), and no criterion here claims the client's acceptance.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | Every page carries the designed header: the logo, a prominent search bar, the language switcher, the account, wishlist and cart entry points, the cart showing a live item count, and the category menu driven by the structure staff manage. On a phone: the simplified header, quick search and a hamburger menu with the same structure | NAV-01, NAV-02, NAV-03, NAV-04, NAV-05, NAV-06, NAV-07, NAV-08, NAV-09, NAV-10, SSC-07 | final |
| AC-2 | The footer's content, links and columns are edited by staff in each language. A WhatsApp link opens a conversation with the configured number. A consent banner, in the visitor's language, records the visitor's choice | SSC-08, CMS-10, MKT-10 | final |
| AC-3 | The home page shows the hero, trending products, new arrivals, featured collections, category shortcuts, brand highlights, offer banners, trust signals and the signup prompt, each fed from the admin. A section with nothing to show leaves no broken region | HOME-01, HOME-02, HOME-03, HOME-04, HOME-05, HOME-06, HOME-07, HOME-08, HOME-09 | final |
| AC-4 | Staff edit each home section's text, images, links and calls to action, show or hide a section without deleting its content, and change the order of sections, without a developer, from the delivered section library; independently in English and Arabic; previewed before it goes live | HOME-10, HOME-11, SSC-01, SSC-02, SSC-03, SSC-12, SSC-13 | final |
| AC-5 | A product card shows the image, name, price and badge, the sale price beside the original, and the average rating, with quick add to cart and quick add to wishlist | PLP-01, PLP-02, PLP-04, PLP-05, MER-01, REV-04 | final |
| AC-6 | The card and the product page show the stock status the store's stock source reports, per variant on the product page, with a clear message when a product is out of stock | PLP-03, PDP-06, PDP-22 | provisional |
| AC-7 | A category page shows its banner and description, the breadcrumb, the result count, filters by category, brand, price range, attribute and availability, the active filters each removable on its own, the sorts (newest, price, best selling, relevance) and paging. On a phone the filters open in a drawer. A list with no products shows the empty state with suggestions | PLP-06, PLP-07, PLP-08, PLP-09, PLP-10, PLP-12, PLP-13, PLP-14, PLP-15, PLP-16, PLP-17, PLP-18, PLP-19, PLP-20, PLP-21, PLP-22, SSC-04 | final |
| AC-8 | A collection page and a brand page each show their own content and their products; a collection's products are in the order staff set | IA-04, IA-05, SSC-05, SSC-06 | final |
| AC-9 | A promotional banner staff created appears on the page it targets during its schedule, and not otherwise | SSC-09 | final |
| AC-10 | Search suggests as the shopper types, opens a results page with the full filter set, and shows a zero-result page with alternatives, in English and in Arabic. A spelling on the staff's synonym and correction list, Franco-Arabic included, finds its products | SRCH-03, SRCH-04, SRCH-05, SRCH-06, SRCH-06a | final |
| AC-11 | The product page shows the gallery with zoom and video, the name and brand, the price, sale price and saving, the option selection, the quantity selector, Add to Cart, Buy Now, Add to Wishlist, the key features, the full description, the specifications table, the delivery estimate, the returns summary, the authenticity statement and the share buttons | PDP-01, PDP-02, PDP-03, PDP-04, PDP-05, PDP-07, PDP-08, PDP-09, PDP-10, PDP-11, PDP-12, PDP-13, PDP-14, PDP-15, PDP-16, PDP-21 | final |
| AC-12 | The product page shows the approved reviews with the average rating, the related products and the recently viewed products | PDP-17, PDP-18, PDP-19, REV-04 | final |
| AC-13 | The header's cart control opens the mini cart, which shows the items in the cart | CART-12 | final |
| AC-14 | Every screen here works in English, left to right, as the default, and in Arabic, right to left, with no text hard-coded; it is usable by keyboard, with labels, contrast, alternative text and semantic structure | FIX-04a, NFR-04, NFR-04a, NFR-11 | final |
| AC-15 | Imagery, typography and spacing follow the brand on every screen here, and a discount is presented as the boards draw it, not in discount-store styling | MER-06, MER-07 | final |

## What is already built, and is not rebuilt

| Behaviour | Where it is | What Stage 2 adds |
|---|---|---|
| Both languages, right to left, translated addresses | specs/002, specs/003 | The designed screens in both directions |
| Language kept for the device, and on the account (D-31, a gift) | specs/052 | The switcher's designed look |
| Header data: menu structure, live cart count, entry points | specs/054 | The designed header, mega menu and phone menu |
| Home sections' data: trending, new arrivals, collections, categories, brands | specs/056 | The section blocks and their designed look |
| Filters, sorts, paging, count, breadcrumb | specs/057 (WooCommerce), specs/053 for attributes | The designed listing, toolbar and phone drawer |
| Search, suggestions, synonym list, zero-result alternatives, term log | specs/058 | The designed search box, panel and results page |
| Product data: saving, Buy Now, delivery estimate, returns, authenticity, share | specs/055 | The designed product page |
| Badges, related products, video, product SEO | specs/036, specs/038 | Their designed presentation |
| Term banners and content, promotional banners, schedule and target | specs/060 | Their designed presentation |
| Manual order in a category or collection | specs/061 | Nothing: the listing reads it |
| WhatsApp number and greeting | specs/059 | The designed link |
| Consent choice and consent-aware tags | specs/043 | The designed banner |
| Wishlist, recently viewed | specs/015 | The heart on the card and product page, the rail |
| Reviews, eligibility, moderation, photos (D-23, a gift) | specs/020, specs/025 | The designed reviews display |
| Stock source, add to cart against it | specs/012, specs/026, specs/049, specs/062 | The designed stock states |
| Cart and mini cart behaviour | specs/018 | The designed mini cart only |

A slice that finds a behaviour missing or wrong under its screen stops and reports it. The repair is its own pull
request against the spec that owns the behaviour, not part of the slice.

## The section library (Feature Register, section G11)

The register delivers storefront editing "through the WordPress block editor as a curated library of complete
sections from the approved Interface Design, not as a free-form page builder". Slice 1 lays the pattern and every
later slice follows it:

- **A section is one complete block.** Its structure, grid, spacing and responsive behaviour are fixed in code.
  Staff choose a section and edit only the content and settings it exposes: text, images, alternative text, links,
  calls to action, and the product source where it has one.
- **Eligible pages take sections only.** On the home page staff add, remove, duplicate, show, hide and reorder
  sections of the delivered types. Raw layout blocks (columns, groups, spacers) are not offered there.
- **A variant exists only where a board draws it.** Ruled by the owner on 24 November 2026: the hero is built as a
  single banner and as a slider, and the announcement strip as a fixed strip and as a slider. Any other variant
  waits for a board. A new section type, or a variant no board draws, is a Change Request (MS-CHG-2026-028).
- **Each language has its own content**, through WPML; one section's structure serves both directions. A variant
  that places an image at one side is stored as inline-start or inline-end, never left or right.
- **In the editor a section is recognisable** and close to its storefront look, and is previewed before publishing.
  The editor is not pixel-identical for every interactive state, as the register says.
- **Fixed templates, edited content.** Category, collection, brand, search and product pages are templates, not
  section pages: staff change their content in the admin screens already built, not their layout.
- **Global brand controls**: the site logo and the approved colour tokens, in the theme's global settings.

The section list and the fields each exposes are confirmed by the boards, slice by slice. The working map is
`docs/2026-10-29-storefront-composition-and-ownership.md`, sections 3 to 5.

## The design this builds

The Stage 2 design was closed on 25 November 2026, after two correction rounds, each checked rendered against the
approved first version and the owner's rulings. The build follows the Concept 1 workspace at version
**1795564351-dde6**. A later change to a board replaces that board and is checked the same way before a slice uses
it. The owner's design rulings that change what the slices build:

- **Header and footer styles.** C1-Header-Footer-Styles draws three headers and two footers (Indigo or White, logo
  at the start or centred), in English and Arabic. These are the only header and footer variants (G11): a theme
  setting chooses one, and slices 2 and 3 build exactly these.
- **Drawers.** A side drawer comes out of the screen edge: full height, square on the edge it comes from, radius 28
  on the two corners facing the page; from the opposite side in Arabic. The desktop mini cart, the phone menu and
  the filters drawer follow it. Bottom sheets round their two top corners only. Dialogs that float (Quick Look,
  cookie preferences) round all four.
- **Product card labels, one per zone.** At most one merchandising tag in the image's top start corner (Limited,
  New, Best Seller, Trending, in that order of precedence: the owner's batch-02 ruling of 19 October 2026, which
  stands where a board lists another order), on the light Indigo tint, flush with the card's corner.
  The sale lives in the price line (price, struck old price, the orange "-20%" tag; on cards under 260 wide the tag
  drops under the struck price, clear of the add button). Stock lives on the card's second line ("Only 3 left",
  "Out of stock"). An out-of-stock card shows no sale and no add button.
- **Price filter.** The price group is a bar chart of how many products sit at each price, above a two-handle
  slider with a price bubble while dragging; the range line opens two fields for typing. Arabic puts the low end
  on the right.
- **The ZZ mark** appears in three places only: the footer's end corner, behind the tick on the order confirmed page
  (a later stage), and breathing in loading image wells and the hero skeleton while content loads. Empty states use
  a line icon in a disc. The page-not-found boards are approved as drawn.
- **Motion never claims success early.** A count, a total or a confirmation changes only after the server answers;
  the waiting state shows the i-dot loader. Every motion has its reduced-motion form, as C1-Motion-Playground draws.

Two rules settle drawing slips found in the final check (C1-Category-Phone, frame 2): the filters drawer's "Show N
products" button is always pinned to the bottom of the drawer above the safe area, with the content scrolling under
it; and the drawer shows one count, the server's answer for every chosen filter together, on that button only.

The cart, checkout, order confirmed, email and error-page boards are drawn too. They are built in their own stage
(D-36) and are not part of these slices.

## Slices

### The checks every slice passes

| # | Check |
|---|---|
| K-1 | The built screen is compared with its board at 390 and 1440 wide, in English: the differences in order, spacing, type, colour and states are measured and listed, and each is fixed or recorded as accepted by the owner |
| K-2 | The same screen in Arabic, right to left: against the Arabic board where one is drawn, otherwise as the mirror of the English board (PRE-03b). Long product names (up to 200 characters), long Arabic words and images of any shape do not clip, overlap or shift the layout |
| K-3 | Every colour, size, space, radius and font comes from a token. No literal value in the theme's styles |
| K-4 | Every word a visitor reads is translatable and translated; none is hard-coded |
| K-5 | Accessibility (D-21): no serious or critical axe-core finding on the screen and its open states; reachable and usable by keyboard, with a visible focus, labels and alternative text; motion respects the reduced-motion setting |
| K-6 | Performance (D-21) on the slice's templates, mobile profile: Largest Contentful Paint 2.0 s or less, Cumulative Layout Shift 0.05 or less, Total Blocking Time 150 ms or less, and inside the byte budget slice 1 sets. A block loads only its own assets |
| K-7 | The tests of the specs under the screen pass unchanged, and the full suite passes |
| K-8 | The screen is opened in a real browser on staging, in both languages, at phone and desktop widths, through the requests a browser makes |

"Waits for design" means the board is not drawn yet. The slice is not dropped: it is built when its board exists,
and Stage 2 does not close without it.

### The slices, in order

| # | Slice | Boards | Design | PBIs |
|---|---|---|---|---|
| 1 | Foundation: tokens, type, base components, notices, the section library pattern | C1-System, C1-Components (every section except the card, 05 to 07, and Quick Look, 15), C1-Notices, C1-Motion-Playground | Drawn | #250, #241, #269, #311 |
| 2 | Header and menus | C1-Menus-Phone, C1-Menus-Desktop, C1-Header-Footer-Styles (headers), C1-Global | Drawn | #253, #311 |
| 3 | Footer, announcement strip, WhatsApp link, consent banner | C1-Header-Footer-Styles (footers), C1-Global (footer, strip, cookie consent), C1-Strip-Slider, C1-Options | Drawn | #311, #310, #288 |
| 4 | Product card and product rail | C1-Components (05 to 07), C1-Options (card labels, chosen), C1-Category-Phone (quick add with options) | Drawn | #303, #282, #309 |
| 5 | Home page sections | C1-Home-Phone, C1-Home-Phone-Arabic, C1-Home-Desktop, C1-Hero-Banners, C1-Slider-Options, C1-Options (single banner) | Drawn | #302, #311 |
| 6 | Category listing | C1-Category-Phone, C1-Category-Desktop, C1-Options (price filter, chosen) | Drawn | #303, #311 |
| 7 | Collection and brand pages, promotional banner | C1-Collection-Phone, C1-Collection-Desktop, C1-Brand-Phone, C1-Brand-Desktop | Drawn | #242, #303, #311 |
| 8 | Search | C1-Search-Phone, C1-Search-Desktop, the search panel on C1-Menus-Phone and C1-Menus-Desktop | Drawn | #304 |
| 9 | Product page: gallery and buying area | C1-Product-Phone, C1-Product-Desktop, C1-Arabic-Stage-2 | Drawn | #254 |
| 10 | Product page: details, reviews, related and recently viewed | C1-Product-Phone, C1-Product-Desktop, C1-Photo-Reviews | Drawn | #254, #309 |
| 11 | Quick Look (D-26, a gift) | C1-Category-Desktop, C1-Components (15) | Drawn | none: a gift |
| 12 | Mini cart | C1-Mini-Cart | Drawn | #306 |

Slices 2 to 12 each need slice 1. Slices 5 to 8, 10 and 11 need slice 4 (the card). Slice 7 needs slice 6 (the
listing). Slice 11 needs slice 9 (the buying area it reuses).

### Slice 1: foundation

- **Rows**: FIX-04a, NFR-04, NFR-04a, NFR-11, SSC-01, MER-06.
- **Builds**: the tokens of `design/tokens/tokens.json` as the theme's global settings and `--mizzey-*` custom
  properties; Inter and Tajawal self-hosted, per script; the type roles, spacing, radius, focus ring and motion;
  the icon set the boards draw, as one set of inline SVG icons that take their colour from a token;
  logical properties throughout, so one stylesheet serves both directions; the base components (buttons, fields,
  choices, chips and badges, notices, loader and skeletons, empty states, pagination, drawers and modals, steps,
  tooltips, tabs); the section library pattern above, with one section built end to end as the proof; the global
  brand controls; the byte budgets per template (D-21).
- **Before it starts**: the brand rulings of 18 November 2026 (D-34: the interface orange, Ink, body weight, the
  logo on light grounds, colour proportions) are on `main` with the token values that follow from them; `AGENTS.md`
  and `design/README.md` record that the storefront build has started.
- **Acceptance**: K-1 to K-8 against the boards; every colour role pair on the boards meets WCAG 2.2 AA; on an
  eligible page the editor offers the library's sections and no raw layout block; the proof section is added,
  edited in both languages, hidden, reordered, previewed and published by an editor account.
- **Test first**: a check that fails until every token in `tokens.json` reaches the theme and no style holds a
  literal value or a physical left or right; a browser test that fails until the editor's inserter on the home page
  lists only library sections.

### Slice 2: header and menus

- **Rows**: NAV-01 to NAV-10, SSC-07. D-31's gift shows in the switcher's signed-in state.
- **Builds**: the desktop header, the mega menu, the phone header, the phone menu with its levels and Categories
  tab, the language switcher, the search field's place (its panel is slice 8), the account, wishlist and cart entry
  points with the live count; as template parts over the data of specs/054 and specs/052.
- **Acceptance**: K-1 to K-8; AC-1; a menu changed in the admin appears after a reload in the mega menu and the
  phone menu alike; switching language keeps the page and the cart; menus open, trap and return focus by keyboard.
- **Test first**: a browser test of the header in both languages at both widths, failing on the native header: the
  entry points, the count after an add without a reload, the same menu structure in the mega menu and phone menu.

### Slice 3: footer, announcement strip, WhatsApp link, consent banner

- **Rows**: SSC-08, CMS-10, MKT-10.
- **Builds**: the footer with its columns, links and logo; the announcement strip in its two variants, fixed and
  slider; the WhatsApp link; the consent banner's designed look over specs/043.
- **Acceptance**: K-1 to K-8; AC-2; the strip's slider pauses on focus and hover and stops under reduced motion;
  the consent banner blocks no content and is reachable first by keyboard.
- **Test first**: a browser test that fails until the footer's columns come from the admin in each language, the
  WhatsApp link carries the configured number, and the strip's variant follows its setting.
- **Footer styles**: the two footers of C1-Header-Footer-Styles, chosen by a theme setting.

### Slice 4: product card and product rail

- **Rows**: PLP-01 to PLP-05, MER-01, MER-07, REV-04. D-29's gift shows as the stock state from whichever source
  the product is set to.
- **Builds**: the card with its image (any shape, shown whole), name, brand, price and sale price, the labels one per zone, rating,
  stock state, wishlist heart, quick add, and quick add with options on a phone; its loading skeleton; the rail
  that carries cards on the home page and the product page.
- **Acceptance**: K-1 to K-8; AC-5, AC-6; a card with a 200 character name, no image, no rating, a sale and a
  sold-out state each render as the board's states; an add confirms in place with the notice and the count.
- **Test first**: a browser test over a fixture of the edge products above, failing until each state renders and
  quick add reaches the cart through the stock source.

### Slice 5: home page sections

- **Rows**: HOME-01 to HOME-11, SSC-02, SSC-03, SSC-12, SSC-13.
- **Builds**: the sections on the boards as library blocks: the hero in its two variants (single banner, slider),
  category shortcuts, trending, new arrivals, featured collections, offer and deal banners, brand highlights, trust
  signals, the signup prompt; the home template that accepts sections only. The signup prompt is built as the boards
  draw it, with its email field and optional box for news and offers, on one small sign-up and consent record with a
  welcome email and a leave link, no customer account and no campaign engine (DECISIONS A10, ruled by the owner on
  25 November 2026), and a read-only list for staff.
- **Acceptance**: K-1 to K-8; AC-3, AC-4; each product section reads the rule or curation of specs/056, and an
  empty one renders nothing broken; the Arabic home page is compared with C1-Home-Phone-Arabic.
- **Test first**: a browser test as an editor account: add, edit, hide, reorder and preview sections in each
  language, failing until the storefront shows the same order and content, and the hidden section's content is kept.
- **The signup prompt** (HOME-09, AC-3), checked by t119 in Chromium and WebKit, at 390 and 1440, in both languages:
  - the field, the optional box and the button as drawn; an address that is not one is refused with words that say
    how to fix it; the button shows the i-dot loader until the server answers; success replaces the form at the same
    height with the focus on the message; the same states come back without JavaScript;
  - the record: one per address, in lower case, with the language, the date, the source "home", the status and a
    random leave token; consent only when the box is ticked, with the version of its words; no WordPress account;
    the same answer for a new and a known address; a record that cannot be written is answered as a failure;
  - the welcome: one email in the sign-up's language, through the store's customer email, with the leave link; tried
    again while it is owed;
  - the leave page: in the sign-up's language, one press leaves, a mail program's one-click request leaves, an
    unknown link answers 404; it loads no tracking;
  - the list: read-only, searchable and counted, for the owner, administrators and shop managers, with no export;
  - the limits: a timed single-use form token, a trap field, per network address and per email, and an hourly
    ceiling on welcomes (docs/security-baseline.md).

### Slice 6: category listing

- **Rows**: PLP-06 to PLP-10, PLP-12 to PLP-22, SSC-04.
- **Builds**: the category template: banner and description, breadcrumb, the filter panel on desktop and the
  filter drawer on a phone, the toolbar (sort, count, active filters), the grid of cards, numbered paging, the
  loading state and the no-products state; over WooCommerce's product query (specs/057).
- **Acceptance**: K-1 to K-8; AC-7; the filters and sort stay in the address and survive going to a product and
  back; the drawer traps and returns focus; changing a filter does not shift the layout.
- **Test first**: a browser test at both widths and in both languages, failing on the native listing: each filter,
  each sort, removing one active filter, paging, and the empty state.

### Slice 7: collection and brand pages, promotional banner

- **Rows**: IA-04, IA-05, SSC-05, SSC-06, SSC-09.
- **Builds**: the collection page and the brand page on the listing of slice 6, each with its own content
  (specs/060) and, for a collection, the staff's order (specs/061); the brands index; the promotional banner's
  designed place on its target pages.
- **Acceptance**: K-1 to K-8; AC-8, AC-9.
- **Test first**: a browser test that fails until a collection lists its products in the staff's order with its
  content in each language, and a scheduled banner shows on its target only inside its dates.

### Slice 8: search

- **Rows**: SRCH-03, SRCH-04, SRCH-05, SRCH-06, SRCH-06a, and PLP-15 for the relevance order.
- **Builds**: the search field's suggestion panel on desktop and phone, the results page on the listing of slice 6
  with the full filter set, the zero-result page with its alternatives; over specs/058.
- **Acceptance**: K-1 to K-8; AC-10; the panel is a labelled combobox usable by keyboard; an exact SKU leads; a
  term on the staff's list (an Arabic variant and a Franco-Arabic spelling from the fixture) reaches its products.
- **Test first**: a browser test in both languages, failing on the native search form: suggestions while typing,
  the results with a filter applied, the zero-result alternatives, and the listed spellings.

### Slice 9: product page, gallery and buying area

- **Rows**: PDP-01 to PDP-11, PDP-14 to PDP-16, PDP-21, PDP-22.
- **Builds**: the gallery with zoom and video, name and brand, rating summary, price, sale price and saving, the
  option selection (text options and swatches as drawn), stock state per variant, quantity, Add to Cart, Buy Now,
  the wishlist heart, key features, the delivery estimate, returns summary and authenticity statement, share, the
  sold-out state, and the phone's buy bar in its states; over specs/055 and WooCommerce's product blocks.
- **Acceptance**: K-1 to K-8; AC-6, AC-11 for these rows; options are generic, never assumed to be size and
  colour; choosing a variant updates price, stock and image without a layout shift; the buy bar never covers the
  consent banner or a notice.
- **Test first**: a browser test over a simple, a variable, a sold-out and a no-video product in both languages,
  failing on the native product page.

### Slice 10: product page, details, reviews, related and recently viewed

- **Rows**: PDP-12, PDP-13, PDP-17, PDP-18, PDP-19, REV-04. D-23's gift shows as the photos in a review.
- **Builds**: the full description and specifications (absent when empty), the reviews display with the average
  and the approved reviews and their photos, the related products rail and the recently viewed rail.
- **Acceptance**: K-1 to K-8; AC-12; only approved reviews and their photos are public; a product with no reviews,
  no specifications or no related products shows the board's state, not an empty frame.
- **Test first**: a browser test that fails until the reviews, their photos, and both rails render from the
  fixture, and the empty cases render as drawn.

### Slice 11: Quick Look (D-26, a recorded gift)

- **Rows**: none of its own. It uses the contracted behaviour of PDP-01, PDP-03 to PDP-11, PLP-04 and PLP-05.
- **Builds**: on desktop and larger screens, the Quick Look control on a card and its preview: images, name and
  brand, price and saving, stock, key features, options, Add to Cart, the wishlist heart and a link to the product
  page. On a phone the quick add of slice 4 stays the fast path.
- **Acceptance**: K-1 to K-8; focus moves into the preview, stays in it and returns to the card; the preview's
  data loads only when it is opened; no layout shift.
- **Test first**: a browser test that fails until the preview opens from a card by mouse and by keyboard, adds a
  chosen variant to the cart, and returns focus on close.

### Slice 12: mini cart

- **Rows**: CART-12.
- **Builds**: the mini cart as drawn: a drawer out of the screen edge on desktop, a bottom sheet on a phone, with its
  free-delivery progress, loading and empty states; over
  WooCommerce's mini-cart block and specs/018. The cart page itself stays Stage 3.
- **Acceptance**: K-1 to K-8; AC-13; the mini cart shows the same lines and totals the cart page computes; it
  opens from the cart control, traps and returns focus.
- **Test first**: a browser test that fails until the drawer opens from the header, lists an added item with its
  option and price in each language, and its total equals the cart's.

### Closing Stage 2

After slice 12, and with no slice still waiting for design: one tagged release on staging; the D-21 measurements
on the home page, a category, a simple and a variable product and the search results, in English and Arabic; the
browser check of K-8 across the whole shop front on a phone and a desktop; then the client's browse, the gate the
Project Plan names. Three states are reported apart: workflow complete, technically verified, contractually accepted.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Finding and choosing a product (Priority: High)

1. **Given** the home page in Arabic on a phone, **When** the shopper opens the menu, picks a category, filters by
   brand and price and sorts by price, **Then** each screen is the designed one, right to left, and the filtered
   list keeps its state when they return from a product (AC-1, AC-3, AC-7, AC-14).
2. **Given** the search field, **When** the shopper types a Franco-Arabic spelling on the staff's list, **Then**
   suggestions appear, and the results page lists the products with the full filter set (AC-10).
3. **Given** a variable product, **When** the shopper picks an option that is sold out, **Then** the page says so
   clearly and offers no purchase, and another option can be added to the cart, confirmed in the header (AC-6,
   AC-11, AC-13).

### User Story 2 - The team changes the home page (Priority: High)

1. **Given** an editor account, **When** they add a hero from the library, set its banner in each language, hide
   the deals section and move new arrivals up, then preview and publish, **Then** the storefront shows exactly that
   in each language, and the hidden section's content is still there to show again (AC-4).

### Edge Cases

- A product name of 200 characters, an image far from square, a product with no image, rating or reviews.
- A section whose source has no products; a category with no products; a search with no result.
- An option set that is not size and colour; a product with one variant left.

## Native coverage

| Need | Native | Gap and how it is closed |
|---|---|---|
| Templates, template parts, global styles | WordPress block theme, `theme.json` | None: `mizzey-theme` carries the tokens and the designed styles |
| Header menu, footer menus | Core navigation and `wp_navigation`, one per language | The mega menu's panel layout: a block style and the data of specs/054 |
| Listing, filters, sort, paging, breadcrumb | WooCommerce product collection and filter blocks | Styled as drawn; the phone drawer where the native overlay does not match the board |
| Product page parts, mini cart | WooCommerce product and mini-cart blocks | Styled as drawn; Buy Now, saving, delivery and statements from specs/055 |
| Home sections | No native block gives a complete, locked section | The library's blocks in `mizzey-site`, made with the CoreX generators |
| Sign-up record (HOME-09) | WooCommerce keeps no sign-up list outside customer accounts; corex-newsletter is not enabled (D-18, item 5; A4) | A small Mizzey table, its welcome as a WooCommerce email, and a read-only list (A10) |
| Search panel and results | Core search form | The panel over specs/058's suggestions |
| Quick Look | None | The gift's own block (D-26) |

A native block is replaced only where the slice records, with evidence, that styling cannot reach the board (M-4).

## Requirements

### Functional Requirements

- **FR-001**: The theme MUST take every design value from `design/tokens/tokens.json` (AC-14, AC-15).
- **FR-002**: Every screen MUST be built once and serve both directions through logical properties (AC-14).
- **FR-003**: The home page MUST accept only library sections, with add, remove, duplicate, show, hide, reorder,
  per-language content and preview (AC-4).
- **FR-004**: Each slice's screens MUST read the data and behaviour of the specs named for it and MUST NOT
  reimplement them (AC-1 to AC-13).
- **FR-005**: Every word the storefront shows MUST be translatable; none is hard-coded (AC-14).
- **FR-006**: A block MUST load only its own assets, and interactive parts MUST work by keyboard (AC-14).

### Key Entities

- **Section**: a library block with its variant and its per-language content.
- **Token**: a named design value in `tokens.json`, emitted to the theme.

## Recorded gifts on these screens (not owed)

| Gift | Where it shows | Record |
|---|---|---|
| Quick Look on product cards | Slice 11 | D-26 |
| Photos in reviews | Slice 10 | D-23 |
| The language follows the customer's profile | Slice 2 | D-31 |
| The store's own stock and the per-product switch | The stock state in slices 4 and 9 | D-29 |

Each is built in its slice and labelled a gift. None adds a register row or is read as owed, and the client is
told in writing, when the shop front is presented, that each is included at no charge beyond the contracted scope.

## Optional safeguards (not owed)

| Id | Safeguard | Why | Custom code? |
|---|---|---|---|
| S-1 | A style check in CI that refuses a literal colour, size or a physical left or right in the theme | Keeps K-3 and both directions true after Stage 2 | A small checker in `tools/` |
| S-2 | Stored screenshots of each screen at both widths and in both languages, compared on later changes | Catches a later slice breaking an earlier screen | Test infrastructure only |

## Future or Option C items (not built)

- The card waves idea (board C1-Card-Waves): set aside by the owner on 24 November 2026.
- Brand landing pages (IA-33) and the filter by rating (PLP-11): the second release.
- Comparison, frequently bought together, back-in-stock signup, section scheduling: P2.
- Any section type or variant no board draws: a Change Request.

## Success Criteria

- **SC-001**: Every Stage 2 screen matches its board at 390 and 1440 wide, in English and Arabic, with the
  differences measured and closed.
- **SC-002**: The D-21 levels hold on the home page, a category, both product types and the search results.
- **SC-003**: An editor changes the home page in both languages without a developer, from the library only.
- **SC-004**: No test of specs/050 to specs/064 changed to make a slice pass.

## Delivery stage

Contractual stage: every traced row is S1 in the register (the first release); none moves. Delivery: Stage 2 of
the Project Plan, section 3.2, whose gate is the client browsing the shop front on staging, in both languages, on
a phone and on a desktop. Engineering timing: the function layer was built on 4 to 6 November 2026 (D-27); these
slices follow from 24 November 2026.

## Assumptions

- The boards are the design's working source and are named here by their board names. They are not copied into
  this repository; a slice's evidence records what it was compared with.
- The Arabic screens are the right to left localisation of the approved English design (PRE-03b), so one Arabic
  board per surface is not required.
- The cart page, checkout, account and content pages keep their present native screens, on the new tokens, until
  their stage.
- The graphics the design itself needs (the icon set, and the banner and section artwork the boards show) are
  produced with the design, as on the boards. Banner and section artwork ships as sample content the team replaces
  in the editor: it carries no brand name, logo, label or face, and is never presented as the client's own
  photography. Product photos and the store's own campaign images stay the client's content.
- The initial entries of the synonym and correction list, the home page's words and images, and the WhatsApp
  number are the client's content; fixtures stand in on staging (M-10).

## Open points for the owner

1. Settled 25 November 2026: every Stage 2 board is drawn (see "The design this builds").
2. Board C1-Options also draws "without free delivery" and the fields and select boxes: each is ruled before the
   slice it belongs to (slices 3 and 1). The order confirmed email, the price filter and the card labels are ruled.
3. Settled: D-34 and D-35 are on `main` (#554), and D-36 records the rulings of 24 November 2026 (#555).

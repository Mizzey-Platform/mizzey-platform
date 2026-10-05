# Sitemap and Principal User Flows

**Mizzey Launch Platform**

| Item | Detail |
|---|---|
| **Document** | Companion to the Functional Specification MS-SPC-2026-032 version 1.2, for register row PRE-03a |
| **Version** | 1.0 |
| **Date** | 5 October 2026 |
| **Document id** | Not assigned. One is assigned if this companion is issued |
| **Scope authority** | Feature Register MS-ANX-2026-006 version 1.5, which governs |
| **Status** | Prepared. Not yet delivered to the client. Not approved. It adds no scope: every page and every step cites the register row that contracts it, and the Feature Register governs wherever this document differs. |

---

## 1. What this document is

Register row PRE-03a reads: "**Sitemap and user flows.** Delivered in the Functional Specification (PRE-08),
which carries the information architecture and the end to end customer and staff journeys".

The Functional Specification holds that material as user stories traced to register rows. It has no sitemap and
no flow that can be read at a glance. This companion makes the same contracted material visible in two forms:

- **A sitemap**: every page row of register section A6 that creates an obligation, with its address in English
  and in Arabic.
- **The principal user flows**: one customer flow for each journey row of register section A5, and the staff
  flows for which contracted rows exist.

It does not replace, amend or reissue the Feature Register or the Functional Specification. It designs nothing:
page layout and visual design belong to the Interface Design (PRE-03b). It writes no content. Where a source is
silent, the point is left out or listed in section 7, and it is not filled in here.

## 2. How to read it

### Scope and stage values

The values are the register's own, checked for every id cited here against the machine-readable mirror of the
register (`docs/scope/register-ids.json`, generated from version 1.5).

| Value | Meaning in the register | Creates a delivery obligation |
|---|---|---|
| P1 | Phase 1 contract, delivered in full | Yes |
| P1-L | Phase 1 contract, delivered to a defined reduced specification | Yes |
| P1-E | Phase 1 contract, ERP-dependent. How it works is set in the ERP Integration Specification (PRE-09) | Yes |
| DLV | A contractual deliverable rather than a software feature | Yes |
| DEF | Deferred to the Operations Platform | No |
| P2 | Growth phase, not contracted | No |
| P3 | Long-term vision | No |
| OUT | Expressly excluded | No |
| DEC | A business decision required from the client | No |

| Stage | Meaning |
|---|---|
| S1 | First release: live when the store launches |
| S2 | Second release: delivered after the store is live, within the Phase 1 contract |
| - | Not in Phase 1, or set by PRE-09 where the row says so |

### What "route verified" means

The sitemap marks a page "route verified" when its address exists in both languages on the development runtime
and passed the technical verification of engineering feature 003 (information architecture and URLs, PBI #242,
criteria AC-242-01 and AC-242-02 of that feature). It means no more than that:

- The address answers and serves its own page in the right language. Page bodies are placeholders.
- The design and behaviour of each page are separate work, owned by the PBI named beside it, and most are not
  built yet. At the time feature 003 was measured the theme held a front page template and an index template
  only.
- It is technical verification, not contractual acceptance. Manual testing on staging (DOD-04) and client
  approval of acceptance criteria (DOD-09) are both outstanding, and acceptance happens only through the
  Acceptance and UAT Plan MS-UAT-2026-027.

### Conventions

- **Register ids** (for example IA-08, CHK-01) are quoted from the register. Ids beginning OD are client decisions
  from register Part Six, and ids beginning CR are client responsibilities from Part Five. They are cited as
  inputs, not as delivery rows.
- **Story ids** (US-nn-nn) are those of the Functional Specification version 1.2.
- **PBI numbers** (#nnn) are the owning backlog items recorded in `docs/scope/backlog-ownership.json`.
  A second-release slice has a key and no number, because it is recorded and not yet created.
- **"Per PRE-09"** marks a step whose behaviour depends on the client's ERP. The outcome is contracted. No
  mechanism is described, because no P1-E behaviour is final before the ERP Integration Specification is
  approved.
- **Owner decision** marks a working decision of the developer recorded in `DECISIONS.md`. It is not a
  client confirmation.
- Ids beginning CX are contradictions inside the contract documents, recorded in
  `docs/scope/open-items.json`. Criteria written AC-242-nn belong to engineering feature 003 and are not
  the register's acceptance scenarios AC-01 to AC-25.
- Labels beginning "Flow" and "OP" are this document's own. They are not register ids.
- Addresses are paths. No domain is shown, because the production domain and hosting are open (OD-27, OD-18).

---

## 3. The sitemap

### 3.1 The site at a glance

The register gives each page a group and no hierarchy. The nesting below shows only how the addresses sit under
one another and how a customer reaches one page from another in the flows of section 5. It is not a navigation
design: the menu structure is admin-managed (NAV-07) and its presentation belongs to the Interface Design.

Every page exists twice: in English at the address shown, and in Arabic at the same address under `/ar/`,
except where section 3.2 shows a different Arabic ending.

- **Home** (IA-01) `/`
  - **Catalogue**
    - Products (IA-02) `/shop/`
    - Categories (IA-03) `/product-category/{term}/`
    - Collections (IA-04) and curated collection pages (IA-35) `/collection/{term}/`
    - Brands (IA-05) `/brand/{term}/`
    - Product Details (IA-07) `/product/{slug}/`
  - **Search**
    - Search Results (IA-06) `/?s={query}`
  - **Shopping**
    - Cart (IA-08) `/cart/`
      - Checkout (IA-09) `/checkout/`
        - Order Confirmation (IA-10) `/checkout/order-received/{id}/`
    - Wishlist (IA-11) `/wishlist/`
  - **Account**
    - My Account (IA-17) `/my-account/`, which shows Login (IA-14) and Register (IA-15) to a signed-out visitor
      - Forgot Password (IA-16) `/my-account/lost-password/`
      - Addresses (IA-18) `/my-account/edit-address/`
      - Orders (IA-19) `/my-account/orders/`
        - Order Details (IA-20) `/my-account/view-order/{id}/`
    - Tracking (IA-21) `/track-your-order/`
  - **Trust and support**
    - About (IA-24) `/about/`
    - Contact Us (IA-25) `/contact-us/`
    - Help / FAQ (IA-26) `/help/`
    - Authenticity Guarantee (IA-27) `/authenticity-guarantee/`
    - Shipping Policy (IA-28) `/shipping-policy/`
    - Return and Refund Policy (IA-29) `/returns-and-refunds/`
    - Privacy Policy (IA-30) `/privacy-policy/`
    - Terms and Conditions (IA-31) `/terms-and-conditions/`
  - **Campaign**
    - Offer / campaign pages (IA-32) `/offers/`
  - **Second release, not in the first release**
    - Notification Preferences (IA-23), no address yet
    - Brand landing pages (IA-33), no address yet

### 3.2 Every contracted page

Thirty-one page rows create an obligation: twenty-nine at S1 and two at S2. "Row owner" is the PBI that owns the
IA row itself. "Page content and behaviour" names the rows that contract what the page does, and their owners.

**Home**

| ID | Page | Scope | Stage | English | Arabic | Route state | Row owner | Page content and behaviour |
|---|---|---|---|---|---|---|---|---|
| IA-01 | Home | P1 | S1 | `/` | `/ar/` | Route verified. A static front page | #242 | HOME-01 to HOME-11: #302. Editing: SSC-02, SSC-03: #311 |

**Catalogue**

| ID | Page | Scope | Stage | English | Arabic | Route state | Row owner | Page content and behaviour |
|---|---|---|---|---|---|---|---|---|
| IA-02 | Products | P1 | S1 | `/shop/` | `/ar/shop/` | Route verified | #242 | PLP rows at S1: #303 |
| IA-03 | Categories | P1 | S1 | `/product-category/{term}/` | `/ar/product-category/{term}/` | Route verified. One archive per category | #242 | PLP rows at S1: #303. ADM-55: #277. SSC-04: #311 |
| IA-04 | Collections | P1 | S1 | `/collection/{term}/` | `/ar/collection/{term}/` | Route verified, on test terms only | #242 | PLP rows at S1: #303. MER-04: #282. ADM-57: #277. SSC-06: #311 |
| IA-35 | Curated collection pages | P1 | S1 | `/collection/{term}/` | `/ar/collection/{term}/` | Route verified. The same route as IA-04 | #242 | As IA-04 |
| IA-05 | Brands | P1 | S1 | `/brand/{term}/` | `/ar/brand/{term}/` | Route verified. One archive per brand | #242 | ADM-56: #277. SSC-05: #311 |
| IA-07 | Product Details | P1 | S1 | `/product/{slug}/` | `/ar/product/{slug}/` | Route verified. The product template is not built | #242 | PDP rows: #254 |

**Search**

| ID | Page | Scope | Stage | English | Arabic | Route state | Row owner | Page content and behaviour |
|---|---|---|---|---|---|---|---|---|
| IA-06 | Search Results | P1 | S1 | `/?s={query}` | `/ar/?s={query}` | Route verified | #242 | SRCH rows: #304 |

**Shopping: cart, checkout and wishlist**

| ID | Page | Scope | Stage | English | Arabic | Route state | Row owner | Page content and behaviour |
|---|---|---|---|---|---|---|---|---|
| IA-08 | Cart | P1 | S1 | `/cart/` | `/ar/cart/` | Route verified | #242 | CART rows: #306 |
| IA-09 | Checkout | P1 | S1 | `/checkout/` | `/ar/checkout/` | Route verified. With an empty cart it redirects to the cart in the same language | #242 | CHK rows: #255 |
| IA-10 | Order Confirmation | P1 | S1 | `/checkout/order-received/{id}/` | `/ar/checkout/order-received-ar/{id}/` | Route verified. Reached only after checkout, not a listed page | #242 | CHK rows: #255. AUTH-11: #305 |
| IA-11 | Wishlist | P1-L | S1 | `/wishlist/` | `/ar/wishlist/` | Route verified. Address only | #242 | WISH rows: #307 |

**Account**

| ID | Page | Scope | Stage | English | Arabic | Route state | Row owner | Page content and behaviour |
|---|---|---|---|---|---|---|---|---|
| IA-14 | Login | P1 | S1 | `/my-account/` | `/ar/my-account/` | Route verified. The My Account page when signed out | #242 | AUTH rows: #305 |
| IA-15 | Register | P1 | S1 | `/my-account/` | `/ar/my-account/` | Route verified. The same page, registration form when enabled | #242 | AUTH rows: #305 |
| IA-16 | Forgot Password | P1 | S1 | `/my-account/lost-password/` | `/ar/my-account/lost-password-ar/` | Route verified | #242 | AUTH-04, AUTH-16: #305 |
| IA-17 | My Account | P1 | S1 | `/my-account/` | `/ar/my-account/` | Route verified | #242 | ACCT rows at S1: #308 |
| IA-18 | Addresses | P1 | S1 | `/my-account/edit-address/` | `/ar/my-account/edit-address-ar/` | Route verified | #242 | ACCT-04: #308 |
| IA-19 | Orders | P1 | S1 | `/my-account/orders/` | `/ar/my-account/orders-ar/` | Route verified | #242 | ACCT-05: #308 |
| IA-20 | Order Details | P1 | S1 | `/my-account/view-order/{id}/` | `/ar/my-account/view-order-ar/{id}/` | Route verified. Signed-in customers only | #242 | ACCT-06, ACCT-12: #308 |
| IA-21 | Tracking | P1 | S1 | `/track-your-order/` | `/ar/track-your-order/` | Route verified. A page with an order lookup by order number and email | #242 | ACCT-07: #308. SHIP-13: #291 |

**Trust and support**

| ID | Page | Scope | Stage | English | Arabic | Route state | Row owner | Page content and behaviour |
|---|---|---|---|---|---|---|---|---|
| IA-24 | About | P1 | S1 | `/about/` | `/ar/about/` | Route verified | #242 | CMS-01: #310 |
| IA-25 | Contact Us | P1 | S1 | `/contact-us/` | `/ar/contact-us/` | Route verified | #242 | CMS-04, CMS-10: #310 |
| IA-26 | Help / FAQ | P1 | S1 | `/help/` | `/ar/help/` | Route verified | #242 | CMS-05: #310 |
| IA-27 | Authenticity Guarantee | P1 | S1 | `/authenticity-guarantee/` | `/ar/authenticity-guarantee/` | Route verified | #242 | CMS-02: #310 |
| IA-28 | Shipping Policy | P1 | S1 | `/shipping-policy/` | `/ar/shipping-policy/` | Route verified | #242 | CMS-03: #310 |
| IA-29 | Return & Refund Policy | P1 | S1 | `/returns-and-refunds/` | `/ar/returns-and-refunds/` | Route verified | #242 | CMS-07: #310 |
| IA-30 | Privacy Policy | P1 | S1 | `/privacy-policy/` | `/ar/privacy-policy/` | Route verified | #242 | CMS-06: #310 |
| IA-31 | Terms & Conditions | P1 | S1 | `/terms-and-conditions/` | `/ar/terms-and-conditions/` | Route verified | #242 | CMS-08: #310 |

All eight are editable without a developer (CMS-09: #310; SSC-10: #311; ADM-63: #277). The pages are built by the
developer. The legal and policy text within them is supplied by the client (CR-04).

**Campaign**

| ID | Page | Scope | Stage | English | Arabic | Route state | Row owner | Page content and behaviour |
|---|---|---|---|---|---|---|---|---|
| IA-32 | Offer / campaign pages | P1-L | S1 | `/offers/` | `/ar/offers/` | Route verified. Address only | #242 | SSC-11: #311. MKT-20: #287 |

**Contracted for the second release**

| ID | Page | Scope | Stage | English | Arabic | Route state | Row owner | In the first release |
|---|---|---|---|---|---|---|---|---|
| IA-23 | Notification Preferences | P1-L | S2 | None yet | None yet | Not built. Second release | Slice E-FND-2b, not yet created | The register provides "Unsubscribe links in emails" (Part One 1.1) |
| IA-33 | Brand landing pages | P1-L | S2 | None yet | None yet | Not built. Second release | Slice E-FND-2b, not yet created | Not stated in the register. See OP-13 |

**Elements carried on every page**

| Element | Register rows | Owner | State |
|---|---|---|---|
| Header: logo, language switcher, search bar, account, wishlist and cart entry points, category navigation | NAV-01 to NAV-07 | #253 | Not built |
| Mobile header: simplified header, quick search, hamburger menu | NAV-08 to NAV-10 | #253 | Not built |
| Footer content, links and columns | SSC-08 | #311 | Not built |

**Search-engine outputs that are addresses but not pages**

| Output | Address | Register row | State |
|---|---|---|---|
| XML sitemap, covering both languages | `/wp-sitemap.xml` | NFR-03 | Technically verified (AC-242-10 of feature 003) |
| Robots file | `/robots.txt` | NFR-03 | Technically verified (AC-242-11 of feature 003) |

### 3.3 Page rows that are not launch pages

These rows are in register section A6 and create no obligation. They are listed so that nobody takes them for
pages of the launch release. None is built, and none has an address.

| ID | Page | Scope | Stage | What the register says |
|---|---|---|---|---|
| IA-12 | Compare | P2 | - | Not contracted. Product comparison is listed in Part One 1.3 |
| IA-13 | Gift Cards | P2 | - | Not contracted. Part One 1.3 places gift cards in Phase 2 |
| IA-22 | Returns, in the customer account | DEF | - | Deferred to the Operations Platform. The Launch Platform provides instead: "Returns raised through your support channels" (Part One 1.6) |
| IA-34 | Category SEO pages | P2 | - | Not contracted. "Category pages themselves are included" (Part One 1.3) |
| IA-36 | Referral and loyalty pages | P2 | - | Not contracted |

No IA row carries the scope P3, OUT or DEC. Among the journey rows, JRN-12 is P3 and is treated in section 5.

---

## 4. English and Arabic: addresses and flow considerations

Everything in this section is taken from engineering features 002 (bilingual platform baseline, PBI #241) and
003 (information architecture and URLs, PBI #242), and from the register rows they trace.

### 4.1 How language is carried

| Point | What holds | Source |
|---|---|---|
| Default language | English is the primary and default storefront language and is served at the root | FIX-04a; feature 002 |
| Arabic | Arabic is an active second language, served under the `/ar/` prefix | FIX-04, FIX-04a; feature 002 |
| How the language is chosen | From the address. A request with no language in it is English. No stored or session state makes a request default to Arabic | Feature 002 |
| Direction | The Arabic document carries `lang="ar"` and `dir="rtl"`. The English document carries `lang="en-US"` and no direction attribute | NFR-04; feature 002 |
| Templates | The same templates render both languages. Direction and locale come from the active language | FIX-04, NFR-04; feature 002 |
| Text | No hard-coded storefront text. Every string this engagement writes is translatable | NFR-04a; feature 002 |
| Wrong-language guard | An Arabic address serves the Arabic record or rendering of that page, never the English counterpart | Feature 003, AC-242-02 |
| One address per screen | No contracted page has two managed routes. An engineering guardrail, not contracted acceptance | Feature 003, guardrail G-1 |
| Language tags for search engines | Language targeting tags are emitted for `ar`, `en` and `x-default` | MKT-18; feature 003 |
| Admin | The administrative interface stays English when the storefront language changes | ADM-159; feature 002 |
| Design | The canonical design source is English. The Arabic layouts are its right to left localisation | PRE-03b, AC-12 |

### 4.2 The counterpart address, by page type

Each page has exactly one address per language. This is what a language switch has to resolve to.

| Page type | Rows | English form | Arabic form | How the pair is tied |
|---|---|---|---|---|
| Page with its own record | IA-01, IA-02, IA-08, IA-09, IA-11, IA-17, IA-21, IA-24 to IA-32 | `/{slug}/` | `/ar/{slug}/` | An English record and an Arabic record in one translation group, English as source. The slug is the same in both languages in the URL map |
| Catalogue archive | IA-03, IA-04, IA-05, IA-35 | `/{base}/{term}/` | `/ar/{base}/{term}/` | The three bases (`product-category`, `collection`, `brand`) are the same in both languages and are deliberately distinct from one another. Each taxonomy is translatable |
| Product | IA-07 | `/product/{slug}/` | `/ar/product/{slug}/` | Slug translation is switched on for products |
| Search results | IA-06 | `/?s={query}` | `/ar/?s={query}` | A stable address carrying the query, in both languages |
| Sign-in and registration | IA-14, IA-15 | `/my-account/` | `/ar/my-account/` | The My Account page shown to a signed-out visitor. Not a separate address |
| Account screen | IA-16, IA-18, IA-19, IA-20 | `/my-account/{endpoint}/` | `/ar/my-account/{endpoint}-ar/` | An endpoint under the account page, not a page record. The Arabic ending is a translated string |
| Order confirmation | IA-10 | `/checkout/order-received/{id}/` | `/ar/checkout/order-received-ar/{id}/` | An endpoint under checkout. Reached only after an order is placed |

### 4.3 What switching language does

The language switcher is contracted as NAV-02 and is owned by #253. **It is not built.** The behaviour below is
the contracted story for it (US-01-02), set against what the implemented addresses provide.

| Point | Contracted in the Functional Specification | State |
|---|---|---|
| Where the switcher is | In the header on every page, including mobile | Not built (#253) |
| Where a switch lands | On the same page in the other language, not on the home page | The counterpart address exists for every page type in section 4.2. The switcher itself is not built |
| The cart | Unchanged by a switch | Not built, not verified |
| Checkout with an empty cart | Not stated in the story | Measured in feature 003: `/checkout/` redirects to `/cart/` and `/ar/checkout/` to `/ar/cart/`, so the redirect stays in its own language |
| Persistence of the chosen language | "The chosen language persists for the session and for a returning visitor on the same device" | Differs from feature 002. See OP-14 |

No source states what a switch does to a search query in progress, to a part-completed checkout form, or on the
order confirmation. These are listed in OP-15 and are not answered here.

### 4.4 Right to left

- Arabic is not a separate design or a separate template set. It is the same page with direction taken from the
  language (feature 002).
- The contracted acceptance scenario is AC-12: all core pages render correctly in both languages without layout
  break, and Arabic passes right to left layout validation.
- Rendering in real browsers, in both directions, is **not yet verified**. It needs the staging environment
  (NFR-14; feature 002 criterion AC-9; #244).
- Directional detail of each page, such as mirrored arrows and the mobile header, belongs to the page's own PBI
  and to the Interface Design, and is not specified here.

### 4.5 What is still a content input

None of these blocks the routes. Each is content for a page whose address exists.

| Input | Rows | State |
|---|---|---|
| Final Arabic wording of the account and confirmation endings | IA-10, IA-16, IA-18, IA-19, IA-20 | Placeholders today, suffixed `-ar`. The final Arabic wording is a client content input |
| Page copy, and its Arabic translation | IA-24 to IA-32 | Placeholder bodies. Legal and policy text is the client's (CR-04) |
| Storefront content in both languages | All storefront pages | The client's to supply (CR-15). The mechanism is the developer's, the content is the client's |
| Collection names, copy and imagery | IA-04, IA-35 | Client content. The routes were verified on test terms |
| The brand list | IA-05 | Client content |
| Presentation detail of the tracking screen | IA-21 | A design and content input |
| Confirmation that Home is a static page | IA-01 | Configured that way. Awaiting confirmation |
| Arabic catalogue content | IA-07 | Arrives with the catalogue migration (SSC-21, Section U) |

---

## 5. Principal customer flows

One flow for each journey row of register section A5. The ten S1 rows are owned together by #322, an end to end
record accepted on the evidence of the PBIs that build each step. Each flow runs identically in English and in
Arabic: the pages are those of section 3 at their address in the customer's language.

A visitor (ROLE-01) may browse, search, filter, view products, add to cart, keep a temporary wishlist, register
and check out as a guest. A customer (ROLE-02) has in addition an account, addresses, orders, tracking, a saved
wishlist, reviews and coupons.

### Flow C1. Discovery (JRN-01, P1, S1)

Register wording: "Arrive at Home → see Trending / New / Collections".

| Step | The customer | Page | The system | Register rows | Stories |
|---|---|---|---|---|---|
| 1 | Opens the store with no language chosen | Home (IA-01) | Serves English at the root. Arabic is served under `/ar/`, right to left | JRN-01, IA-01, FIX-04, FIX-04a, NFR-04 | US-01-01 |
| 2 | Reads the home page | Home (IA-01) | Shows the hero banner or slider, trending products, new arrivals, featured collections, category shortcuts, brand highlights and offer banners, each maintained in the admin | HOME-01 to HOME-07, HOME-10 | US-02-01, US-02-03 |
| 3 | Reads the trust signals and the signup prompt | Home (IA-01) | Shows authenticity, delivery and returns signals, and a newsletter or account signup prompt | HOME-08, HOME-09 | US-02-02 |
| 4 | Uses the header | Any page | Offers the logo, language switcher, search bar, account, wishlist and cart entry points and the category navigation. Simplified on mobile | NAV-01 to NAV-10 | US-01-02, US-01-05, US-01-06 |
| 5 | Selects a product, a category, a collection or a brand | Product Details (IA-07), Categories (IA-03), Collections (IA-04, IA-35), Brands (IA-05) | Opens that page in the same language | IA-03, IA-04, IA-05, IA-07, IA-35 | US-02-01, US-03-09 |

Continues in Flow C2 or Flow C3.

### Flow C2. Find (JRN-02, P1, S1)

Register wording: "Search, category, filters or recommendations".

| Step | The customer | Page | The system | Register rows | Stories |
|---|---|---|---|---|---|
| 1 | Types in the search bar | Any page, in the header | Suggests as the customer types. Searches name, description, SKU, brand and category, in English and in Arabic | NAV-03, SRCH-01, SRCH-02, SRCH-03, SRCH-06a | US-03-06 |
| 2 | Submits the search | Search Results (IA-06) | Shows results with the full filter set. Applies the curated Arabic synonym and correction list. Logs the search term | IA-06, SRCH-04, SRCH-06, SRCH-07, SRCH-08 | US-03-06, US-03-07, US-03-08 |
| 3 | Gets no result | Search Results (IA-06) | Shows a zero-result page with alternative suggestions | SRCH-05, PLP-19 | US-03-05 |
| 4 | Or browses by category | Products (IA-02), Categories (IA-03) | Shows the product grid with image, name, price and badge, the sale price beside the original, the category banner and description, the breadcrumb and the result count | NAV-07, IA-02, IA-03, PLP-01, PLP-02, PLP-17, PLP-20, PLP-21 | US-01-05, US-03-01 |
| 5 | Reads the stock status on a product card | Products (IA-02), Categories (IA-03) | Shows stock status from the ERP. **Per PRE-09** | PLP-03, ERP-02 | US-03-01 |
| 6 | Narrows the list | Products (IA-02), Categories (IA-03), Search Results (IA-06) | Filters by category, brand, price range, attribute and availability. Active filters are visible and removable one at a time. A filter drawer on mobile. Filter by rating is second release (PLP-11, S2) | PLP-06 to PLP-10, PLP-18, PLP-22 | US-03-02, US-03-03 |
| 7 | Sorts and pages through the list | As step 6 | Sorts by newest, price, best selling and relevance. Paginates or scrolls | PLP-12 to PLP-16 | US-03-04 |
| 8 | Follows a curated recommendation | Collections (IA-04, IA-35), Brands (IA-05), Home (IA-01), Product Details (IA-07) | Shows curated collections, brand pages, the trending and new arrival sections, and related products. See OP-12 | MER-04, HOME-02, HOME-03, HOME-04, PDP-18 | US-03-09, US-04-08 |
| 9 | Adds straight from a card, or opens the product | As step 6 | Quick add to cart and quick add to wishlist from the card | PLP-04, PLP-05 | US-03-01 |

Continues in Flow C3 or Flow C4.

### Flow C3. Evaluate (JRN-03, P1, S1)

Register wording: "Product page: media, price, authenticity, stock, delivery, reviews, returns, related".

| Step | The customer | Page | The system | Register rows | Stories |
|---|---|---|---|---|---|
| 1 | Opens the product page | Product Details (IA-07) | Shows the product name and brand, the price, and the sale price and saving where one applies | JRN-03, IA-07, PDP-03, PDP-04 | US-04-02 |
| 2 | Looks at the media | Product Details (IA-07) | Image gallery with zoom, and video where supplied | PDP-01, PDP-02 | US-04-01 |
| 3 | Chooses a variant and a quantity | Product Details (IA-07) | Offers variant selection and a quantity selector | PDP-05, PDP-07 | US-04-03 |
| 4 | Reads the stock status of that variant | Product Details (IA-07) | Shows stock status per variant, and clear out-of-stock messaging, on the figure the ERP reports. **Per PRE-09** | PDP-06, PDP-22, ERP-02 | US-04-03, US-04-09 |
| 5 | Reads the product information | Product Details (IA-07) | Short description or key features, full description, specifications table | PDP-11, PDP-12, PDP-13 | US-04-05 |
| 6 | Reads delivery, returns and authenticity | Product Details (IA-07), Authenticity Guarantee (IA-27) | Delivery estimate and shipping information, at reduced scope and where available. Returns policy summary. Authenticity guarantee statement | PDP-14, PDP-15, PDP-16, SHIP-04 | US-04-06 |
| 7 | Reads the reviews | Product Details (IA-07) | Shows moderated reviews and the average rating, at reduced scope | PDP-17, REV-04 | US-04-07 |
| 8 | Looks at related and recently viewed products | Product Details (IA-07) | Shows related products and recently viewed products | PDP-18, PDP-19, MER-05, WISH-07 | US-04-08 |
| 9 | Shares the product | Product Details (IA-07) | Share buttons. Structured data is emitted for search engines | PDP-21, PDP-24 | US-04-10 |

Continues in Flow C4.

### Flow C4. Intent (JRN-04, P1, S1)

Register wording: "Add to Cart, Buy Now or Wishlist".

| Step | The customer | Page | The system | Register rows | Stories |
|---|---|---|---|---|---|
| 1a | Presses Add to Cart | Product Details (IA-07) | Adds the selected variant and quantity. Updates the cart count in the header and shows the mini-cart | JRN-04, PDP-08, CART-12, NAV-06 | US-04-04, US-05-02 |
| 1b | (system step) | Product Details (IA-07) | Adding to the cart takes account of ERP stock. **Per PRE-09** | ERP-03 | US-04-03, US-05-07 |
| 2 | Or presses Buy Now | Product Details (IA-07), then Checkout (IA-09) | Goes to checkout with that item in the cart | PDP-09 | US-04-04 |
| 3 | Or presses Add to Wishlist | Product Details (IA-07), then Wishlist (IA-11) | Saves the product. A guest has a temporary wishlist. A signed-in customer's wishlist persists | PDP-10, WISH-01, WISH-02, WISH-03, IA-11 | US-04-04, US-11-04 |
| 4 | Later moves a saved product to the cart | Wishlist (IA-11) | Moves the item from the wishlist to the cart | WISH-04 | US-11-05 |
| 5 | Or adds from a listing | Products (IA-02), Categories (IA-03) | Quick add to cart and quick add to wishlist | PLP-04, PLP-05 | US-03-01 |

The wishlist is contracted at reduced scope: save, view, move to cart. Sharing, multiple lists and price-drop
alerts are not included (Part One 1.2). Continues in Flow C5.

### Flow C5. Upsell (JRN-05, P1-L, S1)

Register wording: "Smart prompt: add one more item for free shipping".

| Step | The customer | Page | The system | Register rows | Stories |
|---|---|---|---|---|---|
| 1 | Opens the cart | Cart (IA-08) | Shows each line with image, name, variant, price and quantity, and the subtotal, discount, shipping and total | IA-08, CART-01, CART-05 | US-05-01, US-05-03 |
| 2 | (system step) | Cart (IA-08) | Stock is re-validated when the cart is viewed. **Per PRE-09** | CART-10, ERP-03 | US-05-07 |
| 3 | Sees the free-shipping prompt | Cart (IA-08) | Shows the free-shipping progress indicator. The qualifying condition is admin-configurable | JRN-05, CART-07, BR-002, FIX-07, AC-01 | US-05-04 |
| 4 | Adds one more item, from the prompt, the store or a cart suggestion | Cart (IA-08) | Shows cross-sell suggestions in the cart, at reduced scope | CART-08 | US-05-05 |
| 5 | Sees shipping become free | Cart (IA-08) | Applies free shipping per the active promotion, without a code change. The free-shipping promotion overrides the calculated shipping rate | BR-002, BR-009, SHIP-05, AC-02 | US-05-04, US-14-03 |
| 6 | Adjusts the cart | Cart (IA-08) | Updates a quantity, removes an item, moves an item to the wishlist | CART-02, CART-03, CART-04 | US-05-01 |
| 7 | Enters a coupon | Cart (IA-08) | Applies a valid coupon. Gives a clear reason for an invalid one and leaves the total unchanged | CART-06, AC-04 | US-05-06 |
| 8 | Finds the cart empty | Cart (IA-08) | Shows an empty cart state with suggestions | CART-11 | US-05-08 |

What "two items" means, two units or two distinct products, is an open client decision (OD-04). An owner
working default is recorded: two or more units in the cart, any mix of products. It is not client-confirmed.
Continues in Flow C6.

### Flow C6. Identity (JRN-06, P1, key row, S1)

Register wording: "Guest checkout, Google sign-in, or register; welcome discount per rules; **cart carries
through every path**".

| Step | The customer | Page | The system | Register rows | Stories |
|---|---|---|---|---|---|
| 1 | With items in the cart, goes to checkout or to the account entry point | Cart (IA-08), header | Offers four paths: continue as a guest, register, sign in, or use Google | JRN-06, NAV-04, FIX-09 | US-06-06 |
| 2 | **Guest path.** Continues without an account | Checkout (IA-09) | Lets the purchase proceed with no account and no forced registration. The cart is unchanged | AUTH-07, BR-008, AC-13 | US-06-06 |
| 3 | **Register path.** Gives email, password and consent | Register (IA-15) | Creates the account with no more than email, password and consent. Captures marketing consent separately. Sends the verification and welcome email. Registration by phone number is at reduced scope | AUTH-01, AUTH-02, AUTH-05, AUTH-09, AUTH-14, NOTF-01 | US-06-01, US-12-01 |
| 4 | (register path, system step) | Register (IA-15) | Applies the welcome discount per the configured rules, only where its conditions are met | AUTH-06, BR-001, FIX-06, AC-03 | US-14-02 |
| 5 | (register path, system step) | Register (IA-15) | Keeps every item of the guest cart in the new account | CART-13, AC-18 | US-05-10 |
| 6 | **Sign-in path.** Signs in with email and password, or with a passwordless email link at reduced scope | Login (IA-14) | Signs the customer in. Rate-limits attempts | AUTH-03, AUTH-12, AUTH-15 | US-06-02, US-06-05 |
| 7 | (sign-in path, system step) | Login (IA-14) | Merges the guest cart with any saved cart: no item lost, no duplicate line, quantities combined, prices re-validated. Stock limits in the merge are **per PRE-09** | CART-09, CART-14, CART-18, AC-18 | US-05-09, US-05-11 |
| 8 | **Google path.** Chooses Google | Login (IA-14), Register (IA-15) | Creates the account or matches the existing one. One email is one account, never two. The cart behaves as in steps 5 and 7 | AUTH-10, AUTH-13, CART-15, AC-19 | US-06-03, US-06-04, US-05-12 |
| 9 | Has forgotten the password | Forgot Password (IA-16) | Sends a reset link that is single-use and time-limited | AUTH-04, AUTH-16 | US-06-02 |
| 10 | Comes back later without having signed in | Cart (IA-08) | The guest cart survives a browser close and return within a defined window | CART-16 | US-05-13 |
| 11 | Had saved items as a guest | Wishlist (IA-11) | The guest wishlist transfers to the account on registration or sign-in, at reduced scope | CART-17 | US-11-06 |

The welcome discount's value, cap, expiry and eligibility are an open client decision (OD-03). They are
admin-configurable under BR-001 and are not stated here. Social sign-in beyond Google is not contracted
(AUTH-10a, P2). Continues in Flow C7.

### Flow C7. Checkout (JRN-07, P1, S1)

Register wording: "Address → shipping → payment → review → place order".

The guest variant and the signed-in variant differ only at steps 2 and 3. Every other step is the same.

| Step | The customer | Page | The system | Register rows | Stories |
|---|---|---|---|---|---|
| 1 | Goes to checkout from the cart, the mini-cart or Buy Now | Checkout (IA-09) | Opens checkout in the customer's language. With an empty cart it returns to the cart in the same language | JRN-07, IA-09, CHK-10 | US-07-09 |
| 2 | **Guest:** has no saved details. **Signed in:** selects a saved address | Checkout (IA-09) | Offers saved address selection to a returning customer | CHK-02, AUTH-07 | US-07-01, US-06-06 |
| 3 | **Address.** Enters or confirms the address | Checkout (IA-09) | Takes the address in the Egyptian structure: governorate, city, district, street, landmark. Validates with clear messages in both languages | CHK-01, CHK-11 | US-07-01 |
| 4 | **Shipping.** Chooses a shipping method | Checkout (IA-09) | Shows the method and its cost from the manual rate zones by governorate. Shows a delivery estimate where available. A free-shipping promotion overrides the rate | CHK-03, SHIP-01, SHIP-15, SHIP-04, SHIP-05 | US-07-02 |
| 5 | Enters a coupon or an order note, if wanted | Checkout (IA-09) | Coupon entry at checkout. Order notes field | CHK-06, CHK-07 | US-07-04, US-07-05 |
| 6 | **Payment.** Chooses a payment method | Checkout (IA-09) | Offers the methods enabled for launch. See the payment table below | CHK-04, PAY-13 | US-07-03 |
| 7 | **Review.** Checks the order and accepts the terms | Checkout (IA-09) | Shows an order review step with a clear total breakdown including all charges. Requires terms acceptance | CHK-05, CHK-08, CHK-09 | US-07-04, US-05-03 |
| 8 | **Place order.** Presses Place Order | Checkout (IA-09) | Re-validates stock at the point of order placement. No sale is confirmed against stock that cannot be validated as available in the ERP, including when the ERP cannot be reached. What the customer sees in that case is **per PRE-09** | CHK-12, BR-003, ERP-04, ERP-05, AC-05 | US-07-06 |
| 9 | (system step) | Checkout (IA-09) | Creates exactly one order, however many times Place Order is pressed. Stores the price charged on each order item. The order is Pending Payment | PAY-05, BR-005, ORD-01, AC-06 | US-07-07, US-14-05 |
| 10 | Completes payment, by the chosen method | Payment step | Runs the secure hosted and tokenised payment flow for card and wallet. The outcomes are in the table below | PAY-03, PAY-13 | US-08-02 |

**Payment methods at step 6**

| Method | Register rows | Offered at launch |
|---|---|---|
| Card, with embedded 3-D Secure authentication | PAY-14, PAY-13 | Yes, through Paymob. No card data is stored on Mizzey servers (PAY-02, PAY-03) |
| Mobile wallet | PAY-15 | Only if selected under OD-19 and approved for the merchant account by the payment provider. Owner working default: cards and wallets are both launch-capable. Not client-confirmed |
| Cash on delivery | PAY-09 | The capability is built and tested. It is enabled at launch only if the client selects it (OD-05, open). See OP-03 |

Instalments, kiosk payment and cash-on-delivery verification are not contracted (PAY-20, PAY-21, PAY-10, all P2).

**Payment outcomes at step 10**

| Outcome | The customer | The system | Register rows | Stories |
|---|---|---|---|---|
| Card or wallet, success | Completes the hosted payment step | Verifies the payment callback cryptographically before acting on it. Stores the transaction reference, status, amount, currency and timestamps against the order. The order becomes Confirmed. A repeated callback has no duplicate effect | PAY-04, PAY-16, PAY-06, PAY-05, ORD-03, AC-07 | US-08-03, US-08-05, US-07-07 |
| Card or wallet, failure | Is told the payment failed | The order becomes Payment Failed and is not confirmed. A payment failed email is sent with a retry prompt. Stock is restored in the ERP **per PRE-09** | ORD-02, NOTF-03, AC-08, BR-007, ERP-06 | US-08-04, US-12-03 |
| Cash on delivery, where enabled | Pays nothing online | The method is accepted and the order becomes Confirmed. Collection and remittance are recorded later (Flow S2, step 11) | PAY-09, ORD-03 | US-08-07 |
| A sale reduces stock | (system step) | A sale on the website reduces the corresponding stock in the ERP. **Per PRE-09** | ERP-04 | US-07-06 |

Continues in Flow C8.

### Flow C8. Confirmation (JRN-08, P1-L, S1)

Register wording: "Order number, summary, estimated delivery, confirmation message".

| Step | The customer | Page | The system | Register rows | Stories |
|---|---|---|---|---|---|
| 1 | Arrives at the confirmation | Order Confirmation (IA-10) | Shows the order number, a summary, the estimated delivery where available and a confirmation message | JRN-08, IA-10, SHIP-04 | US-07-08 |
| 2 | Receives the confirmation email | Email | Sends the order confirmed email with the order number and summary, to guests as well as account holders. Notifications at launch are email only | NOTF-02 | US-12-02 |
| 3 | **Guest:** is offered an account | Order Confirmation (IA-10) | Offers one-step account creation by setting a password only, with no re-entry of name, email, phone or address | AUTH-11, BR-008 | US-06-07 |
| 4 | **Guest:** sets a password | Order Confirmation (IA-10) | Creates the account and links the order to it securely | AUTH-11, AUTH-08, AC-13 | US-06-07 |
| 5 | **Guest:** declines | Order Confirmation (IA-10) | Leaves the order intact. The guest route stays open | AUTH-07, BR-008 | US-06-07 |

The confirmation page is reached only after an order is placed and is not a listed page (feature 003,
AC-242-06). The register does not state what the reduced specification of JRN-08 is. See OP-13. Continues in
Flow C9.

### Flow C9. Fulfilment and order tracking (JRN-09, P1, S1)

Register wording: "Confirmed → Preparing → Shipped → Out for Delivery → Delivered".

The statuses are moved by the staff and by the carrier integration in Flow S2. This flow is what the customer
sees.

| Step | The customer | Page | The system | Register rows | Stories |
|---|---|---|---|---|---|
| 1 | Has a confirmed order | Order Confirmation (IA-10) | The order is Confirmed: payment or method accepted, ready to prepare | JRN-09, ORD-03 | US-07-10 |
| 2 | **Signed in:** opens the order history | Orders (IA-19) | Lists the customer's orders | ACCT-05 | US-10-02 |
| 3 | **Signed in:** opens an order | Order Details (IA-20) | Shows the order with its full breakdown, at the prices originally charged, and its current status | ACCT-06, BR-005 | US-10-02, US-14-05 |
| 4 | Waits while the order is prepared | Order Details (IA-20) | The order is Preparing | ORD-04 | US-07-10 |
| 5 | Receives the shipped email | Email | The order is Shipped: handed to the carrier, tracking available. The email carries the carrier and the tracking number | ORD-05, SHIP-03, SHIP-09, NOTF-04 | US-12-04 |
| 6 | **Signed in:** follows the order from the account | Order Details (IA-20) | Shows order tracking. The carrier status is retrieved and reflected on the order | ACCT-07, SHIP-13 | US-09-04, US-10-02 |
| 7 | **Guest, or not signed in:** looks the order up | Tracking (IA-21) | The tracking page looks an order up by order number and email, so it serves a customer who is not signed in | IA-21, SHIP-13 | US-09-04 |
| 8 | Sees the order out for delivery | Order Details (IA-20), Tracking (IA-21) | The order is Out for Delivery. No email is sent for this step at launch: the out for delivery update is NOTF-05, P2 | ORD-06 | US-07-10, US-12-04 |
| 9 | Receives the delivered email | Email | The order is Delivered. The email confirms delivery and invites a review | ORD-07, NOTF-06, REV-05 | US-12-04, US-11-03 |
| 10 | Downloads the invoice | Order Details (IA-20) | Invoice download, at reduced scope. Needs the client's invoicing inputs (OD-09, CR-12) | ACCT-12 | US-10-03 |

**Outcomes other than delivery**

| Outcome | What the customer sees | Register rows | Stories |
|---|---|---|---|
| The parcel is not delivered and returns to origin | A distinct state, Delivery Failed / Returned to Origin, never recorded as Delivered | ORD-13, SHIP-17, AC-16 | US-09-06 |
| The order is cancelled | Cancelled, with a recorded reason. Stock returns to the ERP **per PRE-09** | ORD-08, BR-007, ERP-06 | US-18-05 |
| Cash on delivery, where enabled | The courier collects the cash. The store records COD Collected, Awaiting Remittance, at reduced scope | ORD-14 | US-08-07 |

Continues in Flow C10.

### Flow C10. After-sales (JRN-10, P1, S1)

Register wording: "Review / Return / Refund / Support". Four branches.

| Step | The customer | Page | The system | Register rows | Stories |
|---|---|---|---|---|---|
| R1 | **Review.** Follows the review invitation, or opens the product | Product Details (IA-07) | Takes a star rating and a written review | JRN-10, REV-01, REV-02, REV-05 | US-11-01, US-11-03 |
| R2 | Waits for the review to appear | Product Details (IA-07) | Holds the review for moderation before publication. Staff approve or reject it. Approved reviews count in the average rating on the product page and the listing | REV-03, REV-04 | US-11-02 |
| T1 | **Return.** Reads the policy | Return and Refund Policy (IA-29) | Shows the return and refund policy page | CMS-07 | US-13-02 |
| T2 | Contacts the store about a return | Contact Us (IA-25), Help / FAQ (IA-26) | There is no returns area in the account and no return request form at launch. The register provides instead: "Returns agreed through your published support channels" | AC-14, CMS-04, CMS-05, CMS-10 | US-13-03 |
| T3 | Is kept informed | Support channel | The register provides instead: "The customer is updated by your team through the support channel" | AC-14 | US-13-03 |
| F1 | **Refund.** Receives the refund | Email, Order Details (IA-20) | The staff record the refund from the order screen (Flow S3). The standard refund email is sent. The order shows the standard refunded state | RET-08, NOTF-08, ORD-12 | US-12-06, US-13-04 |
| S1 | **Support.** Asks a question | Contact Us (IA-25), Help / FAQ (IA-26) | Contact page with a form, FAQ and help centre, and a WhatsApp click-to-chat link | CMS-04, CMS-05, CMS-10 | US-01-07 |

Deferred and not part of this flow: the customer return request, automatic eligibility checking, return status
in the account and the return update email (RET-01, RET-03, RET-10, ACCT-10, IA-22, NOTF-07, all DEF). Reviews
are at reduced scope: photo and video reviews, verified-purchase badges, voting and questions and answers are not
included (Part One 1.2).

### Flow C11. Reorder (JRN-11, P1-L, S2): second release

Register wording: "**Reorder**: repeat a previous order from history". **This flow is not in the first
release.** It is contracted for the second release, together with ACCT-09, and is owned by the second-release
slices E-FND-3b and E-SF-9b, which are recorded and not yet created.

| Step | The customer | Page | The system | Register rows | Stories |
|---|---|---|---|---|---|
| First release | Views past orders and adds items again by hand | Orders (IA-19), Order Details (IA-20) | The register provides in the first release: "Customers can view past orders; one-click reorder follows" (Part One 1.1) | ACCT-05, ACCT-06 | US-10-02 |
| Second release, 1 | Opens a past order and chooses to reorder | Orders (IA-19), Order Details (IA-20) | Repeats the previous order from history | JRN-11, ACCT-09 | US-10-06 |
| Second release, 2 | Continues to the cart | Cart (IA-08) | Continues as Flow C5 onward | JRN-11 | US-10-06 |

### Flow C12. Personalised recommendation after purchase (JRN-12, P3, no stage)

JRN-12 is long-term vision. It creates no obligation, has no price, no date and no stage, and **no flow is
written for it**. It is listed here only so that the journey table is accounted for in full.

### Flow C13. Using the account (supports JRN-06, JRN-09 and JRN-10)

Not a journey row of its own. It gathers the contracted account rows the journey flows pass through.

| Step | The customer | Page | The system | Register rows | Stories |
|---|---|---|---|---|---|
| 1 | Opens the account | My Account (IA-17) | Shows the dashboard overview. A signed-out visitor sees sign-in and registration instead | IA-17, ACCT-01, IA-14, IA-15 | US-10-01 |
| 2 | Edits profile details or changes the password | My Account (IA-17) | Profile details are editable. Password change. See OP-19 for the address of these screens | ACCT-02, ACCT-03 | US-10-05 |
| 3 | Manages addresses | Addresses (IA-18) | Address book: add, edit, delete, set default. Saved addresses are offered at checkout | IA-18, ACCT-04, CHK-02 | US-10-04 |
| 4 | Reviews orders | Orders (IA-19), Order Details (IA-20) | Order history list and order detail with full breakdown | IA-19, IA-20, ACCT-05, ACCT-06 | US-10-02 |
| 5 | Tracks an order | Order Details (IA-20), Tracking (IA-21) | Order tracking | ACCT-07 | US-10-02, US-09-04 |
| 6 | Opens the saved wishlist | Wishlist (IA-11) | The saved wishlist | ACCT-08, WISH-02 | US-10-01, US-11-04 |
| 7 | Downloads an invoice | Order Details (IA-20) | Invoice download, at reduced scope | ACCT-12 | US-10-03 |

**Account features that are second release, and what the first release provides (Part One 1.1)**

| Feature | Rows | Stage | In the first release, in the register's words |
|---|---|---|---|
| Reorder from order history | JRN-11, ACCT-09 | S2 | "Customers can view past orders; one-click reorder follows" |
| Notification preferences page | IA-23, ACCT-11 | S2 | "Unsubscribe links in emails" |
| Account deletion request from the account page | ACCT-13 | S2 | "Handled manually on request" |

---

## 6. Principal staff flows

Staff flows are written only where contracted rows exist.

### 6.1 Who performs them at launch

| Point | What the register contracts | Rows |
|---|---|---|
| Where staff work | The standard commerce administration. No bespoke administration interface is designed | Section G10, Section G11, ADM-159 |
| Admin language | English, with the administrator guide and training delivered in Arabic | ADM-159, HND-03 |
| Roles at launch | Admin, and Owner / Super Admin | ROLE-07, ROLE-08 |
| Other staff accounts | The standard commerce role set, applied so that no staff account receives owner-level access by default | ROLE-09, ADM-130 |
| Sign-in protection | Two-factor authentication for admin accounts. Logins and significant security events recorded | ADM-131, ADM-134 |

In the tables below, "Staff" means an Admin (ROLE-07), an Owner (ROLE-08), or another staff account holding the
needed permission under the standard commerce role set (ROLE-09). The dedicated Customer Service, Warehouse and
Marketing role profiles are deferred (ROLE-03, ROLE-04, ROLE-05, DEF). In their place the register provides: "The
standard commerce role set, applied so that no staff account receives owner-level access by default (ROLE-09)".
The Accountant is treated in OP-08.

### Flow S1. Adding and editing a product

Contracted by register sections G2, G10 (product operations) and G11 (individual product control). Owners:
#276, #278 and #311, with SSC-21 under #247 and ADM-27 under #252.

| Step | Role | What the role does | Where | The system | Register rows | Stories |
|---|---|---|---|---|---|---|
| 1 | Staff | Finds an existing product, or starts a new one, or duplicates one as a starting point | Product list | Finds any product by SKU, title or barcode | ADM-152, ADM-153, ADM-20 | US-15-10, US-16-01 |
| 2 | Staff | Enters the title, description, specifications, category, brand and attributes | Product screen | Every field is editable individually | SSC-20, ADM-20 | US-16-02 |
| 3 | Staff | Adds images and video, orders them, and writes alt text | Product screen | Stores media, media order and alt text | ADM-22, ADM-23, ADM-24 | US-16-02 |
| 4 | Staff | Sets the regular price, sale price and compare-at price, and the product cost | Product screen | Stores the prices. Captures cost from day one. The cost basis is an open client decision (OD-12) | ADM-25, ADM-26, ADM-29, ADM-27 | US-16-02, US-16-05 |
| 5 | Staff | Sets up variants: attributes, SKU, barcode, price, and weight and dimensions at reduced scope | Product screen | Stores each variant | ADM-30, ADM-31, ADM-32, ADM-33, ADM-35 | US-16-04 |
| 6 | Staff | Stock | Product screen | The ERP is the source of truth for stock. How stock appears in, and is changed through, the admin is **per PRE-09** | ADM-34, ERP-01, ERP-07 | US-16-02, US-17-01 |
| 7 | Staff | Assigns collections, badges, the featured flag and related products | Product screen | Controls placement and prominence on the storefront | ADM-42, ADM-43, ADM-44, ADM-45, SSC-22, SSC-23, SSC-24, SSC-25 | US-14-09, US-16-02 |
| 8 | Staff | Sets the SEO slug, title, description, and canonical and robots override | Product screen | Stores the per-product SEO fields | ADM-38, ADM-39, ADM-40, ADM-41, SSC-27 | US-16-06 |
| 9 | Staff | Sets tax class and shipping flags, at reduced scope | Product screen | Stores shipping and tax treatment | ADM-36, ADM-37, SSC-28 | US-16-02 |
| 10 | Staff | Writes the Arabic content of the product | Product screen | Product content is maintained separately in English and Arabic | SSC-21 | US-16-03 |
| 11 | Staff | Chooses draft, published, hidden or scheduled | Product screen | Supports draft, published and scheduled states, and per-product visibility | ADM-21, SSC-26 | US-16-01 |
| 12 | Staff | Saves | Product screen | The change takes effect on the storefront immediately on save | SSC-29 | US-16-02 |
| 13 | Staff | Changes many products at once | Product list | Bulk price change, bulk publish, unpublish or archive, and bulk category assignment, using the standard commerce bulk-edit capability, at reduced scope | ADM-148, ADM-150, ADM-151 | US-15-09 |

A simple product (title, price, stock, image, category) is contracted to be addable in under three minutes
(ADM-146, US-15-06). Deferred: the inline edit grid (ADM-147, DEF). The register provides instead: "Standard bulk
price editing across a selection (ADM-148), and bulk stock change as settled for ADM-149". Bulk stock change
(ADM-149) is P1-E and is per PRE-09.

### Flow S2. Processing an order from confirmation to delivery

Contracted by register sections E1, E3, G5 and G10 (order operations). Owners: #248, #280, #291, #278, #294.

| Step | Role | What the role does | Where | The system | Register rows | Stories |
|---|---|---|---|---|---|---|
| 1 | Staff | Sees what needs attention | Admin dashboard | Shows orders requiring action. See OP-06 | ADM-12 | US-15-02, US-24-02 |
| 2 | Staff | Finds the order | Order list | Search and filter by order number, customer, phone, email, status, payment, date and carrier. Order search by customer phone number | ADM-85, ADM-157 | US-18-01, US-15-10 |
| 3 | Staff | Opens the order | Order screen | Shows items, prices, discounts, customer, address, payment, shipment, notes and timeline | ADM-86 | US-18-02 |
| 4 | Staff | Adds an internal note where needed | Order screen | Stores internal notes | ADM-88 | US-18-02 |
| 5 | Staff | Moves the order from Confirmed to Preparing | Order screen | Updates the status with validation | ADM-87, ORD-03, ORD-04 | US-18-03 |
| 6 | Staff | Prints the packing slip and the invoice, per order | Order screen | Print or download packing slip and invoice, at reduced scope | ADM-93, ADM-92 | US-18-06 |
| 7 | Staff | Dispatches the order to the carrier, one order at a time | Order screen | Pushes the order to Bosta. A tracking code is returned automatically and linked to the order. Creates and attaches the shipment | ADM-89, SHIP-08, SHIP-09, SHIP-03 | US-18-04, US-09-02 |
| 8 | Staff | Requests a courier pickup | Carrier integration | Pickup requests created and tracked, at reduced scope | SHIP-12 | US-09-03 |
| 9 | (system) | None | None | The order is Shipped. The shipped email is sent to the customer with the carrier and tracking number | ORD-05, NOTF-04 | US-12-04 |
| 10 | (system) | None | None | Retrieves the carrier status and reflects it on the order, through an explicit carrier-status-to-order-status mapping that is documented and visible in the admin. The order moves to Out for Delivery, then Delivered. The delivered email is sent | SHIP-13, SHIP-14, ORD-06, ORD-07, NOTF-06 | US-09-04, US-09-05 |
| 11 | Not stated in a source | Cash on delivery, where enabled: collection and remittance are recorded | Order screen | COD Collected, Awaiting Remittance. Collected versus remitted recorded per order, at reduced scope. The remittance cycle is an open client decision (OD-20) | ORD-14, SHIP-16, PAY-19 | US-09-07 |
| 12 | Staff | Changes the status of many orders at once | Order list | Bulk order status change, using the standard commerce bulk-action capability, at reduced scope | ADM-154 | US-15-08 |
| 13 | Staff | Exports orders | Order list | Order export to spreadsheet using the standard supported commerce export, at reduced scope | ADM-158 | US-18-07 |

**Exceptions**

| Case | Role | What happens | Register rows | Stories |
|---|---|---|---|---|
| The parcel returns to origin | (system), then Staff | Recorded as Delivery Failed / Returned to Origin, never as Delivered. Returned parcels are received in the ERP **per PRE-09** | ORD-13, SHIP-17, AC-16, ERP-06 | US-09-06 |
| The order is cancelled | Staff | Cancels the order with a recorded reason. Stock is released back to the ERP **per PRE-09** | ADM-90, ORD-08, BR-007, ERP-06 | US-18-05 |
| The payment failed | (system) | The order is Payment Failed and never becomes Confirmed | ORD-02, AC-08 | US-08-04 |

**Where this flow touches deferred rows, and what the register provides instead (Part One 1.6)**

| Deferred | Rows | The Launch Platform provides instead, in the register's words |
|---|---|---|
| Operations Console landing page | ADM-140 | "The standard commerce administration, with product, order, customer, stock and content management" |
| Orders-requiring-action queue | ADM-141 | "The standard order list, filterable and sortable by status" |
| Stock-requiring-attention queue | ADM-142 | "The out-of-stock and unmatched SKU reporting in RPT-10, from your ERP figures" |
| Today's summary | ADM-143 | "The standard admin dashboard and sales reporting (RPT-01)" |
| Saved views | ADM-144 | "Standard filtering, applied per session" |
| Quick actions from the console | ADM-145 | "Actions performed from the standard order and product screens" |
| Bulk carrier dispatch and bulk airway-bill printing | ADM-155, SHIP-10, SHIP-11 | "Orders dispatched individually through the Bosta integration, with tracking codes and status updates (SHIP-08, SHIP-09, SHIP-13)" |
| Bulk packing-slip printing | ADM-156 | "Packing slips printed per order" |

### Flow S3. Handling a return and recording a refund

Contracted by PAY-07, PAY-08, PAY-17, RET-08, ADM-91 and ADM-124. Owners: #283, #292, #293, #280. The register
records the returns position as confirmed by the client: standard WooCommerce, returns handled by the client's
team through its support channels, refunds recorded from the order screen (OD-30).

| Step | Role | What the role does | Where | The system | Register rows | Stories |
|---|---|---|---|---|---|---|
| 1 | Customer | Contacts the store about a return (Flow C10, step T2) | Support channels | None. There is no return request form at launch | AC-14 | US-13-03 |
| 2 | Staff | Judges the request against the published policy and records the decision | Order screen | Stores the decision as an order note | ADM-88, CMS-07 | US-18-02 |
| 3 | Staff | Records the condition of a returned item, where one comes back | Order screen | Stores it as an order note | ADM-88 | US-18-02 |
| 4 | Staff with refund permission | Opens the order and records a full or a partial refund, with its payment reference | Order screen | Refund, full or partial, from the standard order screen, subject to permissions | PAY-07, PAY-08, ADM-91, ADM-124, RET-08 | US-08-06, US-20-05 |
| 5 | (system) | None | Order screen | A refund never exceeds the amount actually paid for that item or order after discounts. Staff without permission cannot refund | BR-006, AC-10, AC-11 | US-14-06, US-08-06 |
| 6 | (system), or Staff | None | Order screen, or Paymob | The refund is initiated through the payment provider where the selected Paymob integration supports it. Otherwise it is made through Paymob's own process and recorded on the order | PAY-17 | US-08-06 |
| 7 | (system) | None | None | The order takes the standard refunded state, fully or partially. The standard refund email is sent to the customer | ORD-12, NOTF-08 | US-12-06, US-13-04 |
| 8 | Staff | Checks pending returns and refunds | Admin dashboard | Shows pending returns and refunds, and refunds and returns at reduced scope. See OP-06 | ADM-13, ADM-06 | US-20-01, US-24-02 |

**Where this flow touches deferred rows, and what the register provides instead (Part One 1.6)**

| Deferred | Rows | The Launch Platform provides instead, in the register's words |
|---|---|---|
| Customer return request, with optional photographs | RET-01, RET-02 | "Returns agreed through your published support channels". "Photographs supplied by the customer through the support channel" |
| Automatic eligibility check against a return window | RET-03 | "Eligibility judged by your team against your published policy" |
| Approve or reject queue with a recorded reason | RET-04 | "Decisions recorded as order notes" |
| Receipt of the returned item and its condition | RET-06 | "Condition recorded as an order note" |
| Return status visible in the customer account | RET-10 | "The customer is updated by your team through the support channel" |
| Returns area in the customer account | ACCT-10, IA-22 | "Returns raised through your support channels" |
| Return-specific order statuses | ORD-09, ORD-10, ORD-11 | "Returns recorded as order notes; refunds use the standard refunded states (ORD-12)" |
| Return update email | NOTF-07 | "Your team updates the customer; the standard refund email is sent on refund (NOTF-08)" |
| Custom return case queue and its review, decision and condition screens | ADM-120, ADM-121, ADM-122, ADM-123 | "Customers contact you about a return through the support channels published on the store. Your team reviews and handles it." (section E4) |
| Sensitive-operation audit logging, including refunds | ROLE-10 | "Standard platform and hosting logs. No custom operational audit trail" |

Who holds refund permission among staff is governed by ADM-91 ("subject to permissions") and AC-11. The
capability policy is an owner decision recorded in D-11 and is not client-confirmed. See OP-08.

### Flow S4. Editing storefront content

Contracted by register section G11, rows SSC-01 to SSC-14. Owner: #311. The storefront is edited in the standard
WordPress block editor, from a curated library of complete storefront sections derived from the approved
Interface Design. The sections do not exist until that design is approved (PRE-03b, #250).

| Step | Role | What the role does | Where | The system | Register rows | Stories |
|---|---|---|---|---|---|---|
| 1 | Staff | Opens the page or section to change | Block editor | Each delivered section is recognisable in the editor and closely reflects its storefront appearance | SSC-01 | US-22-01 |
| 2 | Staff | Edits the text, images, links and calls to action of a section | Block editor | Every storefront text, image and link is editable from the admin | SSC-01, SSC-02 | US-22-01, US-22-02 |
| 3 | Staff | Adds, removes, duplicates, reorders, shows or hides sections, where the approved section design supports it | Block editor | Section order is changeable. A hidden section keeps its content | SSC-02, SSC-03, HOME-11, ADM-59, ADM-60 | US-02-04, US-22-03 |
| 4 | Staff | Edits the Arabic version | Block editor | English and Arabic are editable independently | SSC-12 | US-22-09 |
| 5 | Staff | Previews the change | Block editor | Preview any change before it goes live | SSC-13 | US-22-10 |
| 6 | Staff | Publishes | Block editor | The change appears on the English and the Arabic storefront with no developer involvement | SSC-01, AC-20 | US-22-01 |
| 7 | Staff | Reverts a change, if needed | Block editor | Reverts a content change to its previous version, at reduced scope | SSC-14 | US-22-11 |

**What can be edited, by page**

| Content | Sitemap pages | Register rows | Stories |
|---|---|---|---|
| Home page sections: content, images, calls to action, order, show and hide | Home (IA-01) | SSC-02, SSC-03, HOME-10, HOME-11, ADM-59, ADM-60, ADM-61 | US-02-03, US-02-04, US-22-02, US-22-03 |
| Category banners, descriptions and hero imagery | Categories (IA-03) | SSC-04, ADM-55 | US-22-04 |
| Brand page content | Brands (IA-05) | SSC-05, ADM-56 | US-22-04 |
| Collection page content and product ordering | Collections (IA-04, IA-35) | SSC-06, ADM-57, MER-03 | US-22-05 |
| Header navigation and mega menu structure | Every page | SSC-07, NAV-07 | US-22-06 |
| Footer content, links and columns | Every page | SSC-08 | US-22-06 |
| Promotional banners: create, schedule, target to a page, remove, at reduced scope | Pages the banner is targeted to | SSC-09 | US-22-08 |
| Static and policy pages, including the FAQ | IA-24 to IA-31 | SSC-10, ADM-63, CMS-09 | US-22-07 |
| Campaign and offer landing pages, from the approved templates and the delivered section library, at reduced scope | Offer / campaign pages (IA-32) | SSC-11, MKT-20 | US-22-08 |

Not included, in the register's words: "creating new section types; arbitrary page layout construction;
unrestricted nesting of columns or containers; designing new components". A new section type, or a variant not
in the approved Interface Design, is a Change Request.

### Flow S5. The initial catalogue migration, performed by the Developer

Contracted by register Section U, rows MIG-01 to MIG-25 at the scopes shown. Owners: #249, #295, #247, #296,
#297, #320. **This is one migration performed once by the Developer as a project task. It is not a self-service
import tool that the client operates.**

| Step | Role | What the role does | The system or the output | Register rows | Stories |
|---|---|---|---|---|---|
| 1 | Client | Supplies a real, unmodified sample export, confirms the account type and report availability, and confirms image and content rights in writing | The inputs the import specification needs (CR-06, CR-07, CR-08) | MIG-01 | US-23-01 |
| 2 | Client | Supplies the full catalogue export | The one agreed export (CR-09) | MIG-01 | US-23-01 |
| 3 | Developer | Accepts the one agreed export in CSV, tab-delimited or Excel format | The source file for the migration | MIG-01 | US-23-01 |
| 4 | Developer | Verifies and, where necessary, normalises the character encoding | Arabic content is not corrupted | MIG-02 | US-23-02 |
| 5 | Developer | Previews and validates the source file | Nothing is written to the store before this | MIG-03 | US-23-05 |
| 6 | Developer | Maps the source columns to store fields, and maps attributes, categories and brands | The mapping for this migration | MIG-04, MIG-10, MIG-11, MIG-12 | US-23-03 |
| 7 | Developer | Runs a validation pass before the live load | A report of what will be created, updated or rejected | MIG-06 | US-23-06 |
| 8 | Developer, then Client | Reviews the validation issues and gives the client an actionable issue list where client correction is required | The issue list | MIG-19 | US-23-10 |
| 9 | Developer | Runs the live load | Performed as a background operation that does not interrupt the store | MIG-20 | US-23-08 |
| 10 | (within the load) | None | Variations become variable products. Price, sale price and cost are migrated where present. SEO fields are migrated where present | MIG-09, MIG-13, MIG-15 | US-23-07, US-23-12 |
| 11 | (within the load) | None | Images are downloaded from the source references and stored on the Mizzey server, subject to the rights confirmation (CR-08). Alt text is generated from the product title where none is supplied | MIG-16, MIG-17 | US-23-12 |
| 12 | (within the load) | None | Stock quantity at the initial load, and the matching of products and variants to the ERP, are **per PRE-09** | MIG-14, ERP-08 | US-23-12 |
| 13 | Client and Developer | Check the result | The agreed export is migrated once, with no duplicate products, and every problem in the source is reported | AC-17 | US-23-09 |
| 14 | Staff | Afterwards, exports the catalogue when needed | The catalogue remains exportable using the standard supported commerce export format | MIG-25, ADM-48 | US-16-07, US-23-12 |

**Where this flow touches deferred rows, and what the register provides instead (Part One 1.6)**

| Deferred | Rows | The Launch Platform provides instead, in the register's words |
|---|---|---|
| Saved mapping profiles | MIG-05 | "Mapping performed once by the Developer for the initial migration (MIG-04)" |
| Safe re-runs that update by SKU | MIG-07 | "One initial Developer-performed migration (Section U)" |
| Control over which fields an update may overwrite | MIG-08 | "Not applicable to a one-time migration" |
| Import history | MIG-18 | "The Developer's migration report for the initial load (MIG-19)" |

Not contracted at all: undo of an import run and scheduled recurring imports (MIG-21, MIG-22, P2), live Amazon
synchronisation (MIG-23, OUT) and import of Amazon order history and customer data (MIG-24, OUT). Data cleansing,
enrichment and translation are not included (Section U.5).

---

## 7. Open points

Recorded, not resolved. Each is a place where the sources disagree, or where a step waits for a client input or
an owner decision. The Feature Register governs wherever a document differs from it. Corrections to the
Functional Specification are made at the Stage 1 review, not here.

### 7.1 The Feature Register and the Functional Specification

| Id | Open point | Rows and stories |
|---|---|---|
| OP-01 | **Trace gaps.** No story in the Functional Specification traces five of the S1 journey rows or sixteen of the contracted page rows, although stories describing most of them exist | JRN-02, JRN-03, JRN-07, JRN-09, JRN-10. IA-01, IA-02, IA-06 to IA-10, IA-14 to IA-16, IA-18 to IA-21, IA-23, IA-33 |
| OP-02 | **The deliverables story.** US-27-10 lists "the sitemap, user flows, wireframes and interface design" as delivered and traces neither PRE-03a nor PRE-03b. PRE-03b says "no separate wireframe stage is produced" | US-27-10, PRE-03a, PRE-03b |
| OP-03 | **Cash on delivery limits and fees.** Two stories promise "any value limit, zone restriction or fee". No PAY, CHK, ADM or BR row contracts a value limit or a fee. Owner decision (D-11): the capability is the native one, switched on or off, with no value ceiling and no fee. Not client-confirmed. Whether cash on delivery is offered at all is the client's open decision | US-07-03, US-08-07, PAY-09, OD-05 |
| OP-04 | **A configured return window.** Three stories refer to a return window configured in the system. The automatic check against a configured window is deferred, and the window itself is an open client decision | US-01-07, US-04-06, US-22-07, RET-03 (DEF), OD-08 |
| OP-05 | **Returns in the account dashboard.** A story gives the dashboard a route to returns. The returns area in the account is deferred | US-10-01, ACCT-10 (DEF), IA-22 (DEF) |
| OP-06 | **Dashboard queues.** Two contracted dashboard rows name queues whose workflow is deferred, and one story sends a returned parcel to "the operations queue". Owner reading recorded in the open items file (CX-05): the two rows are counts and links to the standard filtered views. Not client-confirmed | ADM-12, ADM-13, ADM-120 (DEF), ADM-141 (DEF), US-09-06, US-15-02, US-20-01 |
| OP-07 | **Saved views.** A contracted story says a filtered order list "can be saved as a view per US-15-05". Saved views are deferred and US-15-05 is not in the Launch Platform | US-18-01, ADM-144 (DEF) |
| OP-08 | **The Accountant.** The register narrative says the role exists at launch, and the ROLE-06 row reads DEF. Four stories are written "As the accountant". Owner decision (D-10, D-11, CX-01): the role exists at launch on least privilege, and refund permission is a separate capability it does not hold by default. A Scope Clarification, MS-CLR-2026-037, is prepared and not acknowledged by the client. Not client-confirmed | ROLE-06 (DEF), US-08-05, US-08-06, US-20-05, US-24-03 |
| OP-09 | **Staff personas whose role profiles are deferred.** Stories are written for "the warehouse", "customer service" and "the marketing team", and two contracted rows (MKT-21, MKT-22) restrict "the Marketing role", while the three role profiles are deferred. Owner reading for the Marketing case recorded in the open items file (CX-02). Not client-confirmed | ROLE-03, ROLE-04, ROLE-05 (all DEF), ROLE-09, US-19-01 |
| OP-10 | **Three further contradictions with owner readings.** An audit trail contracted by three rows while the audit system is deferred (CX-03). An Import Run entity contracted while import history is deferred (CX-04). Banner scheduling contracted while section scheduling is a later phase (CX-06). Each has an owner reading in the open items file. None is client-confirmed | ROLE-10 (DEF), ADM-50, ENT-17, MKT-25. ENT-19, MIG-18 (DEF). SSC-09, HOME-12 (P2), ADM-62 (P2) |
| OP-11 | **Self-service import.** ADM-47 and ADM-161 are P1 rows that read "see Section U", and Section U contracts a Developer-performed migration and no self-service tool. One story speaks of moving the catalogue "in and out in bulk", one of a "migration tool", and one says the catalogue "exports in the same mapped format" while MIG-25 excludes export profiles matching the import schema | ADM-47, ADM-161, MIG-25, US-16-05, US-16-07, US-23-12 |
| OP-12 | **"Recommendations" in JRN-02.** The row names recommendations and cites no row for them. Flow C2 lists only the curated, non-personalised rows that are contracted. Personalised recommendation is long-term vision | JRN-02, JRN-12 (P3), HOME-13 (P3) |
| OP-13 | **Reductions and stand-ins the register does not state.** JRN-05 and JRN-08 are P1-L, and neither appears by id in the list of reduced specifications in Part One 1.2. IA-33 is staged S2 and does not appear in the second-release table in Part One 1.1, so what the first release provides in its place is not stated | JRN-05, JRN-08, IA-33 |

### 7.2 The Functional Specification and the implemented information architecture

| Id | Open point | Rows and stories |
|---|---|---|
| OP-14 | **Whether a chosen language persists.** The story for the language switcher says the chosen language persists for the session and for a returning visitor on the same device. Feature 002 resolves the language from the address and never from stored or session state, and a request with no language in it is English | US-01-02, NAV-02, FIX-04a |
| OP-15 | **What a language switch does in three places.** No source states what a switch does to a search in progress, to a part-completed checkout, or on the order confirmation. The switcher is not built | NAV-02, IA-06, IA-09, IA-10 |
| OP-16 | **A missing translation.** One story says a missing translation is visible in a report rather than silently rendering the other language. Feature 002 records that a string with no Arabic translation shows its English source, as correct fallback. Feature 002 does not include such a report | US-01-03, NFR-04a |

### 7.3 The Feature Register and the implemented information architecture

| Id | Open point | Rows |
|---|---|---|
| OP-17 | **Two page rows, one address.** Login and Register are two rows and one address, the My Account page shown to a signed-out visitor. Collections and Curated collection pages are two rows and one route | IA-14, IA-15. IA-04, IA-35 |
| OP-18 | **No index pages.** Categories, Collections and Brands are each implemented as one archive per term. The URL map has no page that lists all categories, all collections or all brands. No source says whether one is intended | IA-03, IA-04, IA-05 |
| OP-19 | **Account screens with no page row.** Editable profile details and change of password are contracted, have no IA row of their own, and have no entry in the URL map | ACCT-02, ACCT-03 |
| OP-20 | **Tracking sits outside the account.** The register groups Tracking under Account. It is implemented as a separate page with an order lookup, serving customers who are not signed in, while tracking for signed-in customers is on the order in the account. The presentation of the tracking screen is a design and content input | IA-21, ACCT-07 |
| OP-21 | **"Route verified" is not "page delivered".** The routes are technically verified and not contractually accepted. Staging tests and client approval are outstanding. Browser rendering in both directions is not yet verified. Most page designs and behaviours are not built | IA-01 to IA-35 at S1, DOD-04, DOD-09, NFR-14 |
| OP-22 | **Author pages in the XML sitemap.** Feature 003 measured that the XML sitemap index lists an author section, and recorded it as a configuration decision rather than changing it | NFR-03 |
| OP-23 | **A stale ownership note.** The specification of feature 002 still describes PRE-03a and PRE-03b as the staging and production environments owned by #244. The register words them as the sitemap and user flows, and the interface design. Ownership was corrected by owner decision D-11: PRE-03a to #266, PRE-03b to #250 | PRE-03a, PRE-03b |

### 7.4 Steps that wait for the ERP Integration Specification

| Id | Open point | Rows |
|---|---|---|
| OP-24 | **Every step marked "per PRE-09".** The outcome is contracted and the behaviour is not final until the ERP Integration Specification is approved by the client. It is not yet written | P1-E: ERP-03, ERP-06, ERP-07, ERP-08, MIG-14, ADM-149. P1 rows that rest on the ERP: ERP-02, ERP-04, ERP-05, PLP-03, PDP-06, PDP-22, CART-10, CHK-12, BR-003, BR-007 |
| OP-25 | **CHK-12 names a method.** The row reads "by reserving it in your ERP", while register section I2.2 lists reservation among the matters pending in the specification and not decided | CHK-12, ERP-04 |
| OP-26 | **The provisional ERP acceptance scenarios.** Five scenarios are provisional, with final wording set in PRE-09 | AC-21 to AC-25 |

### 7.5 Steps that wait for a client input or decision

| Id | Open point | Affects |
|---|---|---|
| OP-27 | **Payment methods at launch.** Whether cash on delivery is offered (OD-05), which methods are in the first release (OD-19), and the merchant account approval (CR-01). Owner working default for OD-19: cards and wallets. Not client-confirmed | Flow C7, steps 6 and 10 |
| OP-28 | **Promotion rules.** The welcome discount's value, cap, expiry and eligibility (OD-03), and what "two items" means for free shipping (OD-04). Owner working defaults are recorded for both. Neither is client-confirmed | Flow C5, Flow C6 |
| OP-29 | **Return window, authenticity policy and legal text.** The return window (OD-08), the authenticity and warranty policy (OD-13), and the legal and policy text (CR-04) | IA-27, IA-29, Flow C3, Flow C10 |
| OP-30 | **Invoicing.** VAT and invoicing requirements (OD-09) and the legal entity details that appear on invoices and policy pages (OD-18, CR-12) | Flow C9 step 10, Flow S2 step 6 |
| OP-31 | **Shipping.** The carrier account and rate card by governorate (CR-03) and the cash-on-delivery remittance cycle (OD-20) | Flow C7 step 4, Flow S2 |
| OP-32 | **Content and design.** Storefront content in both languages (CR-15), the content inputs of section 4.5, and the brand name, logo and visual identity that the Interface Design waits for (OD-01) | Every page. Flow S4 |
| OP-33 | **Migration inputs.** The sample export (CR-06), the account type (CR-07), the rights confirmation (CR-08, OD-26), the full export (CR-09), SKUs that match the ERP (CR-18), and the product cost basis (OD-12) | Flow S1 step 4, Flow S5 |
| OP-34 | **Hosting and domain.** No production address is shown, because hosting and the domain are open (OD-27, OD-18) | Every address in section 3 |
| OP-35 | **The store operations walkthrough.** PRE-07 reads "before development begins". It has not been held, and the Section G10 list has not been confirmed with the client (D-11) | Flows S1 to S5 |

### 7.6 About the sources themselves

| Id | Open point |
|---|---|
| OP-36 | The owner readings of CX-02 to CX-06 are recorded in `DECISIONS.md` D-12 and in the open items file. They are owner decisions, not client confirmations, and the flows above cite them as such. The signed register is unchanged | Client confirmation of each reading |
| OP-37 | The nesting in section 3.1 is not in any source. The register gives a group for each page and no hierarchy. The nesting shows address structure and the order of the flows, and is not a navigation design |
| OP-38 | No source states which status changes in Flow S2 are made by staff and which by the carrier integration, beyond ADM-87 (staff update status with validation) and SHIP-13 with SHIP-14 (carrier status retrieved, reflected and mapped). No source states who records cash-on-delivery collection and remittance under SHIP-16 |

---

## 8. Coverage

Every IA and JRN id of register sections A5 and A6, and where it appears here. Thirty-six page rows and twelve
journey rows. Nothing is added: no page or flow in this document lacks a row in this table.

### 8.1 Page rows

| ID | Page | Scope | Stage | Obligation | Sitemap | Flows |
|---|---|---|---|---|---|---|
| IA-01 | Home | P1 | S1 | Yes | 3.1, 3.2 | C1, C2, S4 |
| IA-02 | Products | P1 | S1 | Yes | 3.1, 3.2 | C2, C4 |
| IA-03 | Categories | P1 | S1 | Yes | 3.1, 3.2 | C1, C2, C4, S4 |
| IA-04 | Collections | P1 | S1 | Yes | 3.1, 3.2 | C1, C2, S4 |
| IA-05 | Brands | P1 | S1 | Yes | 3.1, 3.2 | C1, C2, S4 |
| IA-06 | Search Results | P1 | S1 | Yes | 3.1, 3.2 | C2 |
| IA-07 | Product Details | P1 | S1 | Yes | 3.1, 3.2 | C1, C2, C3, C4, C10 |
| IA-08 | Cart | P1 | S1 | Yes | 3.1, 3.2 | C5, C6, C11 |
| IA-09 | Checkout | P1 | S1 | Yes | 3.1, 3.2 | C4, C6, C7 |
| IA-10 | Order Confirmation | P1 | S1 | Yes | 3.1, 3.2 | C8, C9 |
| IA-11 | Wishlist | P1-L | S1 | Yes | 3.1, 3.2 | C4, C6, C13 |
| IA-12 | Compare | P2 | - | No | 3.3 | None |
| IA-13 | Gift Cards | P2 | - | No | 3.3 | None |
| IA-14 | Login | P1 | S1 | Yes | 3.1, 3.2 | C6, C13 |
| IA-15 | Register | P1 | S1 | Yes | 3.1, 3.2 | C6, C13 |
| IA-16 | Forgot Password | P1 | S1 | Yes | 3.1, 3.2 | C6 |
| IA-17 | My Account | P1 | S1 | Yes | 3.1, 3.2 | C13 |
| IA-18 | Addresses | P1 | S1 | Yes | 3.1, 3.2 | C13 |
| IA-19 | Orders | P1 | S1 | Yes | 3.1, 3.2 | C9, C11, C13 |
| IA-20 | Order Details | P1 | S1 | Yes | 3.1, 3.2 | C9, C10, C11, C13 |
| IA-21 | Tracking | P1 | S1 | Yes | 3.1, 3.2 | C9, C13 |
| IA-22 | Returns | DEF | - | No | 3.3 | Named as deferred in C10 and S3 |
| IA-23 | Notification Preferences | P1-L | S2 | Yes, second release | 3.1, 3.2 | C13, as second release |
| IA-24 | About | P1 | S1 | Yes | 3.1, 3.2 | S4 |
| IA-25 | Contact Us | P1 | S1 | Yes | 3.1, 3.2 | C10, S4 |
| IA-26 | Help / FAQ | P1 | S1 | Yes | 3.1, 3.2 | C10, S4 |
| IA-27 | Authenticity Guarantee | P1 | S1 | Yes | 3.1, 3.2 | C3, S4 |
| IA-28 | Shipping Policy | P1 | S1 | Yes | 3.1, 3.2 | S4 |
| IA-29 | Return & Refund Policy | P1 | S1 | Yes | 3.1, 3.2 | C10, S4 |
| IA-30 | Privacy Policy | P1 | S1 | Yes | 3.1, 3.2 | S4 |
| IA-31 | Terms & Conditions | P1 | S1 | Yes | 3.1, 3.2 | S4 |
| IA-32 | Offer / campaign pages | P1-L | S1 | Yes | 3.1, 3.2 | S4 |
| IA-33 | Brand landing pages | P1-L | S2 | Yes, second release | 3.1, 3.2 | None. See OP-13 |
| IA-34 | Category SEO pages | P2 | - | No | 3.3 | None |
| IA-35 | Curated collection pages | P1 | S1 | Yes | 3.1, 3.2 | C1, C2, S4 |
| IA-36 | Referral and loyalty pages | P2 | - | No | 3.3 | None |

### 8.2 Journey rows

| ID | Journey step | Scope | Stage | Obligation | Flow | Owner |
|---|---|---|---|---|---|---|
| JRN-01 | Discovery | P1 | S1 | Yes | C1 | #322 |
| JRN-02 | Find | P1 | S1 | Yes | C2 | #322 |
| JRN-03 | Evaluate | P1 | S1 | Yes | C3 | #322 |
| JRN-04 | Intent | P1 | S1 | Yes | C4 | #322 |
| JRN-05 | Upsell | P1-L | S1 | Yes | C5 | #322 |
| JRN-06 | Identity | P1 | S1 | Yes | C6 | #322 |
| JRN-07 | Checkout | P1 | S1 | Yes | C7 | #322 |
| JRN-08 | Confirmation | P1-L | S1 | Yes | C8 | #322 |
| JRN-09 | Fulfilment | P1 | S1 | Yes | C9, with staff Flow S2 | #322 |
| JRN-10 | After-sales | P1 | S1 | Yes | C10, with staff Flow S3 | #322 |
| JRN-11 | After-sales: reorder | P1-L | S2 | Yes, second release | C11, marked second release | Slice E-FND-3b, not yet created |
| JRN-12 | After-sales: personalised recommendation | P3 | - | No | C12. No flow written | None |

### 8.3 Counts

| Measure | Count |
|---|---|
| Page rows in register section A6 | 36 |
| Page rows that create an obligation, shown in the sitemap | 31: 29 at S1 and 2 at S2 |
| Page rows that create no obligation, shown in section 3.3 | 5: 4 at P2 and 1 at DEF |
| Journey rows in register section A5 | 12 |
| Journey rows with a flow | 11: 10 at S1 and 1 at S2 |
| Journey rows with no flow, by design | 1: JRN-12, P3 |
| Supporting customer flow with no journey row of its own | 1: Flow C13 |
| Staff flows | 5: Flows S1 to S5 |
| Open points | 38 |

---

*Mizzey Launch Platform. Sitemap and Principal User Flows, version 1.0, 5 October 2026. Companion to the
Functional Specification MS-SPC-2026-032 version 1.2, for register row PRE-03a. Prepared, not delivered, not
approved. Scope is defined by the Feature Register MS-ANX-2026-006 version 1.5 and by nothing else, including
this document.*

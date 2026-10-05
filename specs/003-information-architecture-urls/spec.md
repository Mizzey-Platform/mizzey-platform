# Feature Specification: Information architecture and URL structure, in both languages

**Feature Branch**: `003-information-architecture-urls`

**Created**: 4 October 2026

**Status**: Draft

**Work type**: requirement

**Input**: PBI #242, the second implementation feature of Option B. It establishes that every contracted page
exists, sits where it belongs and is reachable in both languages, and that the SEO fundamentals NFR-03 names
follow from that. It designs nothing and writes no content.

## Register trace [checked]

Thirty rows. Twenty-nine page rows from register section A6 (SRS §5) plus NFR-03 (SRS §13).

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| IA-01 | P1 | S1 | Home, Storefront |
| IA-02 | P1 | S1 | Products, Storefront |
| IA-03 | P1 | S1 | Categories, Storefront |
| IA-04 | P1 | S1 | Collections, Storefront |
| IA-05 | P1 | S1 | Brands, Storefront |
| IA-06 | P1 | S1 | Search Results, Storefront |
| IA-07 | P1 | S1 | Product Details, Storefront |
| IA-08 | P1 | S1 | Cart, Shopping |
| IA-09 | P1 | S1 | Checkout, Shopping |
| IA-10 | P1 | S1 | Order Confirmation, Shopping |
| IA-11 | P1-L | S1 | Wishlist, Shopping |
| IA-14 | P1 | S1 | Login, Account |
| IA-15 | P1 | S1 | Register, Account |
| IA-16 | P1 | S1 | Forgot Password, Account |
| IA-17 | P1 | S1 | My Account, Account |
| IA-18 | P1 | S1 | Addresses, Account |
| IA-19 | P1 | S1 | Orders, Account |
| IA-20 | P1 | S1 | Order Details, Account |
| IA-21 | P1 | S1 | Tracking, Account |
| IA-24 | P1 | S1 | About, Trust and Support |
| IA-25 | P1 | S1 | Contact Us, Trust and Support |
| IA-26 | P1 | S1 | Help / FAQ, Trust and Support |
| IA-27 | P1 | S1 | Authenticity Guarantee, Trust and Support |
| IA-28 | P1 | S1 | Shipping Policy, Trust and Support |
| IA-29 | P1 | S1 | Return and Refund Policy, Trust and Support |
| IA-30 | P1 | S1 | Privacy Policy, Trust and Support |
| IA-31 | P1 | S1 | Terms and Conditions, Trust and Support |
| IA-32 | P1-L | S1 | Offer / campaign pages, Campaign and SEO |
| IA-35 | P1 | S1 | Curated collection pages, Campaign and SEO |
| NFR-03 | P1 | S1 | SEO: "Clean URLs, meta, sitemap, robots, canonical, breadcrumbs, structured data, alt text" |

**Scope class and Stage, computed mechanically from these thirty ids, not inferred from the PBI's purpose:**

| | Computed | Working |
|---|---|---|
| **Scope class** | **`mixed`** | 28 are P1, two are P1-L (IA-11, IA-32). More than one distinct delivery scope, so `mixed` |
| **Stage** | **`S1`** | All thirty are S1 in the register. One distinct value, so S1 |

Both match what the live board holds. No row here is a range, and no non-delivery row is cited.

## Context rows (no obligation here)

| ID | Scope | Why it matters, and who owns it |
|---|---|---|
| IA-12, IA-13, IA-34, IA-36 | **P2** | Compare, Gift Cards, Category SEO pages, Referral and loyalty pages. Not contracted. Named so nobody builds them while building their neighbours |
| IA-22 | **DEF** | Returns page. The dedicated returns workflow is deferred to the Operations Platform |
| IA-23, IA-33 | P1-L, register stage **S2** | Notification Preferences and Brand landing pages. **Contracted, and not removed from scope**: a Stage 1 PBI must not claim a second-release row, so they are owned by the future **E-FND-2b** S2 slice in this epic |
| MKT-12, MKT-15, MKT-16, MKT-18 | P1 and P1-L, owned by **E-MKT-2** | The **editable admin controls** over SEO output: title and description fields, canonical field, sitemap and robots control, hreflang. NFR-03 owes the output; these own the controls |
| ADM-41 | P1, owned by **E-ADM-2** | Canonical and robots override in the product admin |
| SSC-27 | P1, owned by **E-ADM-10** | Per-product SEO override: title, description, slug, canonical |
| ADM-159 | P1, owned by E-ADM-11 | The admin is English only. No Arabic admin interface here |
| NAV rows | P1, owned by **#253** | Header, navigation and footer. #242 settles where pages sit; #253 builds the navigation that exposes them |
| PRE-07, PRE-08 | DLV, owned by **#250** | The design system and interface design. **#242 has `Design dependency = none` and must keep it** |

## Open contract items

- **No `docs/scope/open-items.json` entry touches any of the thirty ids.** CX-01 and PRE-09 do not reach this
  feature.
- **Four client or content decisions bear on specific rows**, listed under "Decisions this feature needs" below.
  None of them blocks the feature as a whole, and none is resolved here: contradictions and open items are
  escalated, never settled in a spec (M-2).

## Contractual acceptance criteria [checked]

The twenty-nine page rows share one obligation, stated once and applied per row rather than repeated
twenty-nine times, with the rows that need their own criterion given one.

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-242-01 | Every contracted page exists as a reachable URL in **English**, returning 200, and serves the page it names | IA-01, IA-02, IA-03, IA-04, IA-05, IA-06, IA-07, IA-08, IA-09, IA-10, IA-11, IA-14, IA-15, IA-16, IA-17, IA-18, IA-19, IA-20, IA-21, IA-24, IA-25, IA-26, IA-27, IA-28, IA-29, IA-30, IA-31, IA-32, IA-35 | final |
| AC-242-02 | Every contracted page is reachable in **Arabic** under the Arabic language prefix, returning 200, and serves the Arabic record or the Arabic rendering of that page, never the English counterpart | IA-01, IA-02, IA-03, IA-04, IA-05, IA-06, IA-07, IA-08, IA-09, IA-10, IA-11, IA-14, IA-15, IA-16, IA-17, IA-18, IA-19, IA-20, IA-21, IA-24, IA-25, IA-26, IA-27, IA-28, IA-29, IA-30, IA-31, IA-32, IA-35 | final |
| AC-242-03 | Each page's English and Arabic records form **one translation group** with the English record as source, so a later slice resolves the pair by translation identity | IA-01, IA-24, IA-25, IA-26, IA-27, IA-28, IA-29, IA-30, IA-31, IA-32 | final |
| AC-242-04 | **The account URLs are endpoints under the account page, not separate pages**, and each resolves in both languages. No second route to the same screen exists | IA-14, IA-15, IA-16, IA-17, IA-18, IA-19, IA-20 | final |
| AC-242-05 | The storefront's **product, category, collection and brand URLs** follow one documented pattern per language, and a URL that names a term serves that term's archive | IA-02, IA-03, IA-04, IA-05, IA-07, IA-35 | final |
| AC-242-06 | **Order confirmation** is reachable only as the post-checkout endpoint and is not a publicly listed page | IA-10 | final |
| AC-242-07 | **Search results** have a stable URL carrying the query, in both languages | IA-06 | final |
| AC-242-08 | **Clean URLs**: every contracted URL is human readable, carries no query string for its identity, and no contracted page is reachable only by `?page_id=` | NFR-03 | final |
| AC-242-09 | **A canonical link is emitted on every contracted URL**, pointing at that URL's own canonical form, including archive URLs where WordPress core emits none | NFR-03 | final |
| AC-242-10 | **The sitemap includes both languages**: every contracted page appears for English and for Arabic | NFR-03 | final |
| AC-242-11 | **`robots.txt` is served** and does not disallow any contracted page | NFR-03 | final |
| AC-242-12 | A **breadcrumb mechanism** is present and not suppressed, so a storefront template can emit breadcrumbs for a page's position in the hierarchy | NFR-03 | final |
| AC-242-13 | A **structured-data mechanism** is present and correctly wired for product and breadcrumb data, so a storefront template emits it without further work here | NFR-03 | final |
| AC-242-14 | **A meta description is emitted** on every contracted page, and a page without one does not fall back to repeating another page's | NFR-03 | final |
| AC-242-15 | **Alt text is supported and used** on the images this feature introduces, and the mechanism is available for later content | NFR-03 | final |


**The labels are `AC-242-nn`, not `AC-nn`, deliberately.** The register's Section N holds contracted acceptance
scenarios **AC-01 to AC-25**, owned by E-ACC-1, and a criterion labelled `AC-12` in this document would be
textually identical to register row AC-12, which this PBI does not own. The pilot and #241 used `AC-1` to `AC-9`
and never crossed the collision, so this is the first spec where it bites. Found by this feature's own
cross-artifact analysis, because `tools/scope_trace.py` inspects the criterion text and not the label column.

**Fifteen contracted criteria, every one traced to a row in the Register trace.** AC-242-01 to AC-242-07
carry the page rows; AC-242-08 to AC-242-15 are NFR-03's eight obligations, one criterion each.

**AC-242-12 and AC-242-13 assert the mechanism, not the emission, and that is an ownership boundary rather
than a convenience.** Measured: `WC_Structured_Data::generate_product_data` is hooked on
`woocommerce_single_product_summary` at priority 60 and `generate_breadcrumblist_data` on
`woocommerce_breadcrumb`, so the stack is wired correctly. Neither hook fires yet, because the theme has no
product template: it holds `front-page.html` and `index.html` only. **The product template is PDP-01 to PDP-24,
owned by #254**, and **MKT-16 contracts "Structured data: product, organisation, breadcrumbs" and is owned by
E-MKT-2**. Building a product template here would take #254's rows and breach the design boundary this PBI holds
at `Design dependency = none`.

So #242 verifies that nothing it does suppresses the mechanism, and records that emission arrives with the
storefront templates. An earlier draft of these two criteria claimed the output, which over-claimed against both
#254 and MKT-16.

### Derived architecture invariant, not contracted acceptance

**G-1, one managed route per screen.** No contracted page is reachable at two independently managed routes: no
sluggified duplicate, no second page record serving a screen the platform already routes, and a trailing-slash
variant resolves to one canonical form rather than serving the same content twice.

**This is an engineering guardrail, and it is reported separately from the contracted criteria.** NFR-03 says
"Clean URLs" and does **not** say "no duplicate URLs", so presenting G-1 as client-contract acceptance would
overstate the register. It is kept because the row-by-row account analysis showed exactly how duplicates would
arise here, and because a duplicate route is invisible until a crawler finds it. Alternative technical URLs the
platform naturally produces are handled through normal canonical behaviour, not by inventing duplicated managed
content.

**Nothing here is a "URL structure" criterion in the abstract.** Each criterion names the behaviour it asserts.

## Clarifications

### C-1. Does "information architecture" oblige a URL taxonomy, a redirect map or an SEO audit?

**No.** SRS §5, which all twenty-nine page rows cite, is headed "the general structure of the pages" and its body
is a table of group against page name. It contains no slug pattern, no canonical rule, no redirect policy and no
trailing-slash decision. The register mirrors it with the columns `ID | Page | Group | § | Scope | Stage`.

**SRS section 5 does not prescribe URL taxonomy.** #242 owns only the runtime IA, URL and SEO-output obligations assigned to its registered rows. **Separately registered SEO and admin-control requirements retain their own ownership** and are not absorbed here.

So the page rows oblige **existence, position and reachability in both languages**, and the URL and SEO output this feature owes is the output assigned to **NFR-03**. A documented URL map
is the obvious way to evidence AC-242-05 and AC-242-08; it is not itself a contracted deliverable, and it is produced as
evidence rather than claimed as a row.

### C-2. Eight of the rows are endpoints, not pages. Does that change what is owed?

**It changes how, not whether, and the mechanism differs per row.** Grouping them all as "My Account
endpoints" would be wrong. Measured, row by row:

| Row | Native mechanism | A true endpoint? |
|---|---|---|
| IA-10 Order Confirmation | The **checkout** `order-received` endpoint | Yes |
| IA-14 Login | **No dedicated native endpoint.** Login is presented through the My Account authentication experience on the base page | No |
| IA-15 Register | **No dedicated native endpoint.** Registration is presented through My Account when enabled | No |
| IA-16 Forgot Password | The `lost-password` account endpoint | Yes |
| IA-17 My Account | **The base My Account page**, a real page record | No |
| IA-18 Addresses | The `edit-address` account endpoint | Yes |
| IA-19 Orders | The `orders` account endpoint | Yes |
| IA-20 Order Details | The `view-order/{id}` account endpoint | Yes |
| IA-21 Tracking | **Not a core My Account endpoint.** See C-6 | No |

All ten endpoint query vars exist at their defaults, and **WPML String Translation already registers all fifteen
endpoint slugs** under context `WP Endpoints`, so the Arabic URL for the five true endpoints is a
string-translation task rather than a page to create.

**No separate WordPress page is created merely to make a screen look like a "page" in the register**, which is
the error this row-by-row reading prevents.

So the Arabic URL for those rows is a **string-translation configuration task**, not a page to create. AC-242-04 states
this positively and asserts the negative that matters: **no second route to the same screen**. Creating pages for
them would satisfy a naive reading of "the page exists" and produce duplicate URLs, which G-1 forbids.

### C-3. NFR-03 overlaps six rows owned by other slices. What does #242 owe?

**The output, not the controls.** NFR-03 is a non-functional standard: the storefront emits clean URLs, canonical
links, a sitemap, robots, breadcrumbs, structured data, meta and alt text. The editable admin controls over those
outputs are separate contracted rows owned elsewhere:

| What #242 owes (NFR-03) | What #242 does not owe |
|---|---|
| A canonical link is emitted | An admin field to override it (ADM-41, SSC-27) |
| A sitemap is served, covering both languages | A sitemap and robots management screen (MKT-15) |
| A meta description is emitted | Editable title and description fields per product, category and page (MKT-12) |
| Structured data is emitted for product and organisation | A structured-data configuration surface (MKT-16) |
| - | hreflang, which **WPML already emits** and MKT-18 owns |

Building an admin control here would take another slice's row, which M-1 forbids even with a valid id in hand.

### C-4. Which NFR-03 obligations need staging, and which do not?

**One, partly.** The issue's phrasing, "the NFR-03 artefacts present and correct on staging", is not converted
wholesale into "blocked on #244". Measured separately:

| Obligation | Where it can be verified |
|---|---|
| Clean URLs, canonical, sitemap, robots, breadcrumbs, structured data, meta | **Locally, by served HTTP.** The runtime serves real requests, including `/ar/` |
| Alt text | **Locally.** Attachment meta is assertable |
| Search-engine behaviour: indexing, rich-result eligibility, crawl verification in a real search console | **Staging or production, and not by this feature.** No row obliges a search-console check, and nothing here claims one |

**#244 and staging are not required to START implementation of #242**, and every criterion below is
verifiable locally by served HTTP. **Final feature closure must still respect the project Definition of Done**,
including staging verification wherever that Definition applies:

| Row | Wording | Bears on closure |
|---|---|---|
| **DOD-04** | "Tests written and executed per system layer, **plus manual testing on staging**" | **Yes.** Staging manual testing is a Definition-of-Done step, and it cannot be performed until #244 delivers an environment |
| DOD-09 | "Client approval of acceptance criteria before a feature moves to production" | Yes, a separate closure gate and not an engineering one |

The Definition of Done is **not weakened**. Implementation and local verification complete here; the staging step
is named as the remaining closure dependency rather than declared unnecessary, and **no staging result is claimed
that did not happen**.

### C-5. What happens to rows whose page needs a name or content that nobody has agreed?

They are classified, not invented. Three kinds:

| Classification | Meaning | Rows |
|---|---|---|
| **Placeholder-capable** | The page's IA identity, slug and translation group can be established now with placeholder body content, and the copy arrives with the content slice | IA-24, IA-25, IA-26, IA-27, IA-28, IA-29, IA-30, IA-31 |
| **Client-input-required** | A name or structural decision the register does not fix, which must come from the client before the row can be completed | IA-04, IA-35 (what a collection is and what the collections are), IA-05 (the brand list), IA-21 (what tracking shows) |
| **Separate later slice** | The page's behaviour is another PBI's row; #242 establishes only its IA and URL identity | IA-11 (wishlist behaviour is E-SF-10), IA-32 (campaign behaviour is E-RULES and E-SF) |

No category name, collection name, Arabic copy or SEO text is written by this feature.

### C-6. IA-21 Tracking: the architecture follows the register, and is not blocked on a decision

**Verified against the register rather than deferred.** Order tracking is already established:

| Row | Wording | Scope |
|---|---|---|
| ORD-05 | "Shipped: handed to carrier, **tracking available**" | P1, S1 |
| SHIP-03 | "Shipment created and **tracking number linked to the order**" | P1, S1 |
| ENT-10, through ADR-0002 | The shipment record: carrier, tracking, rate, current mapped status | - |

So the customer-facing tracking experience reads the order's own shipment data, and the native mechanism is
WooCommerce's **order-tracking form**, which looks an order up by number and email. That is a **different
capability from IA-20's `view-order` endpoint**, which requires a signed-in customer: the tracking entry point
serves a guest holding an order number. Providing both is not a duplicate route under G-1; they answer different
requests, and no second tracking architecture is created.

**#242 owns IA-21's route only.** The shipment record, the carrier integration and the status mapping are SHIP-03,
SHIP-09, SHIP-13, SHIP-14 and ENT-10, owned by E-ORD-3, E-DATA and #248. Unresolved presentation detail is a
design and content input, not an architectural blocker.

### C-7. The collection model follows the register, so the capability is built now

**Verified against the register rather than deferred.** The model is not an open question:

| Row | Wording | Scope |
|---|---|---|
| **ADM-57** | "**Manually curated collections**" | **P1, S1** |
| ADM-58 | "Rules-based automatic collections" | **P2**, and Part One states "Manually curated collections are included" |
| ADM-44 | "Collection assignment" | P1, S1 |
| MER-03 | "Manual product ordering within categories and collections" | P1, S1 |
| MER-04 | "Curated collections" | P1, S1 |
| SSC-06 | "Collection page content and product ordering, per collection" | P1, S1 |
| SSC-24 | "Per-product placement: which collections and which position within them" | P1, S1 |
| HOME-04 | "Featured collections section" | P1, S1 |

A manually curated grouping of products, with per-collection page content and manual ordering, and rules-based
membership explicitly deferred to P2. **That is a taxonomy**, the same shape as `product_cat`, and no client
decision is needed to know it.

**So IA-04 and IA-35 are implemented now**: the taxonomy, its archive route, its bilingual behaviour and its
fixtures. Only the **final names, copy, imagery and merchandising choices** are client input, and the tests use
clearly non-production fixture terms.

**The ownership boundary.** #242 owns IA-04's and IA-35's **route and bilingual reachability**. The admin CRUD
(ADM-57), collection assignment (ADM-44), manual ordering (MER-03) and per-collection content (SSC-06, SSC-24)
belong to E-ADM-3, E-ADM-2, E-RULES-3 and E-ADM-10. Registering the taxonomy is the substrate the archive route
needs; it is not the admin surface those rows own.

## User Scenarios and Testing *(mandatory)*

### User Story 1 - Every contracted page is reachable, in both languages (Priority: High)

A customer, or a crawler, can reach every page the contract names, in English at the root and in Arabic under the
Arabic prefix, and gets that page rather than a 404 or the other language's version.

**Why this priority**: it is the whole of the twenty-nine page rows. Everything the storefront slices later build
assumes its page exists and has an address.

**Independent Test**: request every contracted URL in both languages over HTTP and assert the status, the language
of the served document, and that the served record is the one the URL names.

**Acceptance Scenarios**:

1. **Given** the contracted URL map, **When** each English URL is requested, **Then** each returns 200 and serves
   its own page (AC-242-01)
2. **Given** the same map, **When** each Arabic URL is requested, **Then** each returns 200, serves Arabic, and is
   not the English record (AC-242-02)
3. **Given** a page with both records, **When** the pair is read, **Then** they share one translation group with
   English as source (AC-242-03)

---

### User Story 2 - The account area has one route per screen (Priority: High)

A customer reaches their orders, addresses and password reset at predictable URLs under their account, in both
languages, and there is exactly one URL per screen.

**Why this priority**: the naive reading of eight of these rows creates pages that duplicate WooCommerce's own
endpoints. That produces two URLs for one screen, which is an SEO defect and a support problem, and it is far
cheaper to prevent than to unpick.

**Independent Test**: request each account endpoint in both languages; assert 200 and the expected screen. Assert
no page record exists whose slug duplicates an endpoint.

**Acceptance Scenarios**:

1. **Given** the account endpoints, **When** each is requested in both languages, **Then** each resolves to its
   screen (AC-242-04)
2. **Given** the page inventory, **When** it is searched for a page duplicating an endpoint, **Then** none exists
   (AC-242-04, G-1)

---

### User Story 3 - A crawler finds a coherent, non-duplicated site (Priority: High)

A crawler fetching the storefront finds clean URLs, a canonical link on every page including archives, a sitemap
covering both languages, a served `robots.txt`, breadcrumbs, and structured data for products and the
organisation.

**Why this priority**: NFR-03 is a single P1 row that decides whether the storefront is findable at all, and five
of its eight obligations were measured as native while two have real gaps.

**Independent Test**: fetch each artefact over HTTP and assert its content: canonical present and self-referential
on an archive URL, both languages present in the sitemap, `robots.txt` served and not disallowing a contracted
page, structured data parsing as valid JSON with the expected types.

**Acceptance Scenarios**:

1. **Given** an archive URL such as the shop, **When** it is fetched, **Then** a canonical link is present and
   points at that URL's canonical form (AC-242-09)
2. **Given** the sitemap, **When** it is fetched, **Then** both the English and the Arabic URL of each contracted
   page appear (AC-242-10)
3. **Given** a product, **When** its page is fetched, **Then** product and organisation structured data are
   emitted and parse (AC-242-13)
4. **Given** any contracted page, **When** it is fetched, **Then** a meta description is present and is not a
   copy of another page's (AC-242-14)

---

### Edge Cases

- **A trailing-slash variant of a contracted URL is requested.** It must resolve to one canonical form rather
  than serving the same content at two addresses (G-1).
- **An Arabic URL is requested for a page that has no Arabic record yet.** It must not silently serve the English
  record under the Arabic prefix, which would be a bilingual URL serving the wrong language record.
- **Two pages are given the same slug in different languages.** WPML permits this when the language is known at
  insert time; #241 measured that inserting first and assigning the language afterwards produces a suffixed slug
  and a 301. The same invariant applies to every page this feature creates.
- **An endpoint slug is translated to a value that collides with an existing page slug.** The collision must be
  detected rather than producing an ambiguous URL.
- **A contracted page is reachable only by `?page_id=`.** That is a clean-URL failure (AC-242-08). Two drafts are in
  that state today, `privacy-policy` and `refund_returns`.
- **The sitemap lists author pages.** Measured: `wp-sitemap-users-1.xml` is in the index. A configuration decision
  under NFR-03's sitemap obligation, recorded rather than silently changed.

## Native coverage

Measured on the disposable runtime on 4 October 2026. Full working in [research.md](research.md).

| Capability | Evidence | Status |
|---|---|---|
| Clean URLs: permalink structure | `permalink_structure` is `/%postname%/`, set by #241's T-01; `/shop/`, `/ar/shop/`, `/sample-page/` serve 200 | VERIFIED |
| Arabic URL for any page | WPML directory negotiation, `directory_for_default_language=0`. #241 verified `/ar/` and `/ar/shop/` serve `dir="rtl" lang="ar"` | VERIFIED |
| Cart, checkout, My Account pages, both languages | ids 5, 6, 7, 8 with Arabic 19, 20, 21, 22; one translation group each; WooCommerce page options assigned per language | VERIFIED |
| Account and confirmation URLs | **WooCommerce endpoints**, all ten query vars present at defaults | VERIFIED |
| Arabic endpoint slugs | **WPML String Translation already registers all 15** under context `WP Endpoints` | VERIFIED |
| Product, category and tag URL bases | WooCommerce `product`, `product-category`, `product-tag`; `posts_slug_translation` on for `product` | VERIFIED |
| `product_cat` translatable | yes, 2 terms | VERIFIED |
| `robots.txt` | Served 200 natively, already carrying WooCommerce's `Disallow` rules and the `Sitemap:` line | VERIFIED |
| Sitemap | Core serves `/wp-sitemap.xml` 200 with five sections | VERIFIED |
| Breadcrumbs | `woocommerce_breadcrumb()` exists; the `core/breadcrumbs` block is registered | VERIFIED |
| Structured data | `WC_Structured_Data` exists. Not yet observed in output: the runtime had no products at measurement time | PREVIOUSLY TESTED |
| Alt text | Core attachment meta `_wp_attachment_image_alt` | VERIFIED |
| hreflang | **WPML already emits** `hreflang` for `ar`, `en` and `x-default` on both `/shop/` and `/ar/shop/`. **MKT-18 owns it and it needs nothing** | VERIFIED |
| **Canonical on archive URLs** | **Gap.** `rel_canonical()` begins `if ( ! is_singular() ) { return; }`. Measured: `/sample-page/` emits a canonical; `/`, `/ar/` and `/shop/` emit **none** | VERIFIED as a gap |
| **Sitemap language coverage** | **Gap.** `wp-sitemap-posts-page-1.xml` lists only the English pages; the Arabic records 19 to 22 are absent | VERIFIED as a gap |
| **Meta description** | **Gap.** Core has no meta description field and no SEO plugin is active | VERIFIED as a gap |
| **Home page** | **Gap.** `show_on_front` is `posts` and `page_on_front` is `0`: there is no Home page record | VERIFIED as a gap |
| **`product_brand` translatable** | **Gap.** The taxonomy exists in WooCommerce 11.1.0 and is **not** registered as translatable in WPML. IA-05 is P1 and needs Arabic | VERIFIED as a gap |
| **A collection mechanism** | **Gap.** No `product_collection` taxonomy exists, and no convention is recorded. IA-04 and IA-35 need an architecture decision plus client naming | VERIFIED as a gap |
| **A tracking surface** | **Gap.** No native tracking endpoint. WooCommerce offers a shortcode; Bosta owns carrier status (SHIP rows, ADR-0002). IA-21 needs a decision | VERIFIED as a gap |
| Eight Trust and Support pages | **Do not exist**, except `privacy-policy` and `refund_returns` as **drafts** reachable only by `?page_id=` | VERIFIED as a gap |
| CoreX support for any of this | **None.** No template, routing, URL, canonical, SEO or sitemap layer; `Controllers/` and `Services/` are empty directories | VERIFIED |

**Native first, per M-4.** Most of NFR-03 and most of the URL mechanics are already provided. The gaps are
specific and small, and only three of them are code.

## Requirements

### Functional Requirements

- **FR-001**: Every contracted page MUST exist as a reachable URL in both languages, serving its own record. (AC-242-01, AC-242-02)
- **FR-002**: Each page created here MUST form one translation group with the English record as source, and MUST be created with the target language set **before** insert, so its slug is not suffixed. (AC-242-03)
- **FR-003**: The account and confirmation URLs MUST remain WooCommerce endpoints, with their Arabic slugs supplied through string translation, and no page record may duplicate an endpoint. (AC-242-04, G-1)
- **FR-004**: The storefront's product, category, collection and brand URL patterns MUST be documented per language, and a URL naming a term MUST serve that term's archive. (AC-242-05)
- **FR-005**: A canonical link MUST be emitted on every contracted URL, including archive URLs where core emits none. (AC-242-09)
- **FR-006**: The sitemap MUST include both languages for every contracted page. (AC-242-10)
- **FR-007**: A meta description MUST be emitted per contracted page, without one page's description leaking onto another. (AC-242-14)
- **FR-008**: No contracted page may be reachable only by a query-string identity, and no contracted page may have a duplicate URL. (AC-242-08, G-1)
- **FR-009**: `robots.txt` MUST remain served and MUST NOT disallow a contracted page. (AC-242-11)
- **FR-010**: Breadcrumbs and structured data MUST be available on the pages whose hierarchy and content they describe. (AC-242-12, AC-242-13)
- **FR-011**: No admin control, override field or management screen for any SEO output may be built here. (C-3)
- **FR-012**: No visual design, layout, navigation styling, component or design token may be introduced. (Design boundary)

### Metadata output must be extensible, not throwaway

#242 owns the runtime **output** of canonical, meta description and sitemap coverage. **MKT-12, MKT-15, MKT-16,
ADM-41 and SSC-27 own the admin controls over that output** and are not built here, so the output is built so
those rows can later override or configure it **without replacing this implementation and without creating a
parallel metadata store**:

- **FR-013**: every metadata value this feature emits MUST pass through a documented filter before output, so a
  later admin-owned control can override it per record without the emitting code changing.
- **FR-014**: this feature MUST NOT introduce its own storage for a metadata value. Each value is derived from
  the record WordPress already holds, so a later per-record override field becomes the source rather than a
  second store to reconcile.
- **FR-015**: no broad SEO plugin is added to satisfy #242. One would deliver the admin surfaces four other
  slices own, which M-1 forbids.

### Key Entities

- **Contracted page**: one of the twenty-nine named pages. Has an English record or route, an Arabic counterpart,
  a position in a group, and one URL per language.
- **Account endpoint**: a WooCommerce query var under the account or checkout page, whose slug is translatable as
  a string. Not a page.
- **URL map**: the documented pattern per page type and language. Evidence for AC-242-05 and AC-242-08, not a contracted
  deliverable.

## Optional safeguards (not owed)

| Id | Safeguard | Why | Custom code? |
|---|---|---|---|
| S-1 | A test that fails if any contracted page becomes reachable at two URLs, or at a `?page_id=` URL only | G-1 is the criterion most likely to regress silently as later slices add pages. A duplicate URL is invisible until a crawler finds it | No, test only |
| S-2 | A test that fails if an Arabic URL serves the English record | The specific bilingual failure that looks like success: a 200 in the right place with the wrong content | No, test only |

Both need Mustafa's approval and neither is presented to the client as a deliverable.

## Future or Option C items (not built)

| Item | Where it sits |
|---|---|
| Compare, Gift Cards, Category SEO pages, Referral and loyalty pages | IA-12, IA-13, IA-34, IA-36, all **P2** |
| Returns page and the dedicated returns workflow | IA-22, **DEF** |
| Notification Preferences, Brand landing pages | IA-23, IA-33, P1-L staged **S2**, owned by E-FND-2b |
| Editable SEO fields, canonical and robots override, sitemap control, hreflang | MKT-12, MKT-15, MKT-16, MKT-18, ADM-41, SSC-27, owned by E-MKT-2, E-ADM-2 and E-ADM-10 |
| Header, navigation, footer and the language switcher | NAV rows, owned by #253, behind OD-01 |
| Any page's visual design | PRE-07, PRE-08 and the storefront slices, owned by #250, behind OD-01 |
| Wishlist behaviour | E-SF-10. #242 establishes only IA-11's URL identity |
| Campaign and offer behaviour | E-RULES and E-SF. #242 establishes only IA-32's URL identity |

## Decisions this feature needs

Recorded, not resolved. None blocks the feature as a whole; each blocks its own row's completion.

| Id | Decision | Row | Classification |
|---|---|---|---|
| **D-242-1** | What the Home page is: a static page record, or the current posts index | IA-01 | Configuration, needs Mustafa's call. A static page is the ordinary answer for a storefront |
| **D-242-2** | The **final collection names, copy, imagery and merchandising choices**. The model is not an open question: see C-7 | IA-04, IA-35 | **Content input only.** It does not block the capability, the routing or the tests, which use fixture terms |
| **D-242-3** | The brand list, and whether brand pages are `product_brand` archives | IA-05 | WPML configuration plus **client content**. The taxonomy exists and is not translatable |
| **D-242-4** | The **presentation detail** of the tracking screen. The route and the data source follow the register: see C-6 | IA-21 | **Design and content input only.** It does not block the route |

## Success Criteria

- **SC-001**: Every contracted URL in both languages returns 200 and serves its own record, asserted by served
  HTTP from a scripted clean baseline.
- **SC-002**: No contracted page is reachable at two URLs, and none is reachable only by `?page_id=`, asserted
  negatively.
- **SC-003**: A canonical link is present on an archive URL, where core emits none today.
- **SC-004**: Both languages appear in the sitemap for every contracted page.
- **SC-005**: Structured data for product and organisation parses as valid JSON with the expected types.
- **SC-006**: The URL map is written down per page type and language, and matches what the runtime serves.
- **SC-007**: No acceptance criterion is reported verified from an implementation's existence rather than from a
  served observation.

## Delivery stage

Contractual stage: **S1**, for all thirty rows. Engineering timing: second, immediately after #241, which is
delivery order 2 on the board and changes no contractual stage.

## Assumptions

- The disposable local runtime is evidence for the recorded versions only (M-8).
- No production URL, domain or hosting arrangement is assumed. **OD-27** is open, and nothing here decides it.
- #241's capability is a satisfied dependency: English at the root, Arabic under `/ar/`, direction from the
  locale, one template set, translation identity. **It is not rebuilt here.** #241's own AC-242-09 remaining open does
  not block this feature.
- Arabic page copy, category names, collection names and SEO text are content, and arrive with their own slices.
- CoreX is not modified.

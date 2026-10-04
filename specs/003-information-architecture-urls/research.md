# Research: the 30-row audit, measured

Phase 0 for `003-information-architecture-urls`, PBI #242. Everything here was **measured on the disposable
runtime or read from an authoritative source on 4 October 2026**, not recalled.

Versions: WordPress 7.1.2, WooCommerce 11.1.0, WPML 4.9.7, WPML String Translation 3.5.4, WCML 5.5.7,
CoreX 0.42.0, PHP 8.3.6. Runtime at the #241 baseline: pretty permalinks, `.htaccess` present, Arabic enabled,
four Arabic system-page records.

## 1. What the rows actually say, from the register and the SRS

**The register's A6 table has the columns `ID | Page | Group | § | Scope | Stage`.** Every one of the 29 IA rows
cites SRS **§5**, and every value in the `Page` column is a page name, not a behaviour.

The SRS §5 heading is **"الهيكل العام للصفحات (Information Architecture)"**, "the general structure of the pages",
and its body is a two-column table of group against page list:

| SRS group | Pages, verbatim |
|---|---|
| Storefront | Home; Products; Categories; Collections; Brands; Search Results; Product Details |
| Shopping | Cart; Checkout; Order Confirmation; Wishlist; Compare (Phase 2); Gift Cards (Phase 2) |
| Account | Login; Register; Forgot Password; My Account; Addresses; Orders; Order Details; Tracking; Returns; Notifications/Preferences |
| Trust and Support | About; Contact Us; Help/FAQ; Authenticity Guarantee; Shipping Policy; Return and Refund Policy; Privacy Policy; Terms and Conditions |
| Campaign / SEO | Offer pages; Brand landing pages; Category SEO pages; Curated Collection pages; Referral/Loyalty pages (later) |

**SRS §5 says nothing whatever about URLs.** No slug pattern, no canonical rule, no redirect policy, no
trailing-slash decision. It is a page inventory, and the register mirrors it one row per page.

**So where does "URL structure" in this PBI's title come from?** From **NFR-03**, and from one of its eight words:

> NFR-03, SEO: "Clean URLs, meta, sitemap, robots, canonical, breadcrumbs, structured data, alt text" (§13)

The SRS §13 original is identical, with one extra detail the register abbreviates: it says
**"Product/Organization structured data"**, naming the two types.

**This matters for scope.** The 29 IA rows oblige that each named page **exists and is reachable in both
languages**. They do not oblige a URL taxonomy, a redirect map or an SEO audit. NFR-03 obliges clean URLs, and
seven other things. Nothing in the 30 rows obliges a documented "URL map" as a deliverable, though one is the
obvious way to show the work.

## 2. The architectural finding that reshapes eight rows

**IA-14 to IA-21 are not pages. They are WooCommerce My Account endpoints**, measured:

| Row | Delivered as | Mechanism |
|---|---|---|
| IA-14 Login | The My Account page when logged out | WooCommerce renders the login form on the account page |
| IA-15 Register | The same page, registration form | WooCommerce, subject to its registration setting |
| IA-16 Forgot Password | **endpoint** `lost-password` | `/my-account/lost-password/` |
| IA-17 My Account | The My Account page, dashboard | A real page record, exists in both languages |
| IA-18 Addresses | **endpoint** `edit-address` | `/my-account/edit-address/` |
| IA-19 Orders | **endpoint** `orders` | `/my-account/orders/` |
| IA-20 Order Details | **endpoint** `view-order` | `/my-account/view-order/{id}/` |
| IA-10 Order Confirmation | **endpoint** `order-received` | under the checkout page |

All ten endpoint query vars exist and are at their defaults. **And their slugs are already translatable**: WPML
String Translation holds **15 registered strings in context `WP Endpoints`**, including `lost-password`,
`edit-address`, `orders`, `view-order` and `order-received`.

**Consequence**: the Arabic URL for eight of the thirty rows is a **string-translation configuration task**, not a
page to create and not code to write. Creating pages for them would be wrong: it would produce two routes to the
same screen.

**IA-21 Tracking is the exception.** There is **no native tracking endpoint**. WooCommerce offers order tracking
through a shortcode on a page, and the Bosta integration owns carrier status (SHIP rows, ADR-0002). This row needs
a decision, recorded in the spec rather than guessed.

## 3. The page inventory as it stands

Measured. Eleven page records exist, of which four are the Arabic counterparts #241 created:

| Page | Record | Both languages |
|---|---|---|
| shop, cart, checkout, my-account | ids 5, 6, 7, 8 with Arabic 19, 20, 21, 22 | **Yes**, one translation group each |
| privacy-policy | id 3, **draft** | No |
| refund_returns | id 9, **draft** | No |
| sample-page | id 2, WordPress default | Not a contracted page |

WooCommerce page assignment is per language and correct: `shop en=5 ar=19`, `cart 6/20`, `checkout 7/21`,
`myaccount 8/22`. The `terms` assignment is **unset**, which IA-31 needs.

**There is no Home page record**: `show_on_front` is `posts` and `page_on_front` is `0`, so the front page is the
blog index. IA-01 needs a decision and a configuration change.

## 4. URL mechanics as configured

| Setting | Value | Bears on |
|---|---|---|
| `permalink_structure` | `/%postname%/` | Clean URLs, NFR-03. Set by #241's T-01 |
| WooCommerce `product_base` | `product` | IA-07 |
| WooCommerce `category_base` | `product-category` | IA-03 |
| WooCommerce `tag_base` | `product-tag` | not an IA row |
| `category_base`, `tag_base` (core) | default | blog taxonomies, not an IA row |
| WPML `language_negotiation_type` | `1`, directories | every row's Arabic URL |
| WPML `directory_for_default_language` | `0` | English at the root |
| WPML `posts_slug_translation` | **on, for `product`** | IA-07's Arabic slug base |
| rewrite rules stored | 371 | - |

**`posts_slug_translation` is on for `product` only.** Whether the `product-category` base should also be
translated is a decision, and it is WPML configuration either way, not code.

## 5. Taxonomies that carry IA identity

| Taxonomy | Exists | Public | WPML translatable | Terms | Bears on |
|---|---|---|---|---|---|
| `product_cat` | yes | yes | **yes** | 2 | IA-03 |
| `product_tag` | yes | yes | **yes** | 0 | not an IA row |
| `product_brand` | **yes** | yes | **NO** | 0 | **IA-05 Brands** |
| `product_collection` | **does not exist** | - | - | - | **IA-04, IA-35 Collections** |

Two real gaps:

- **IA-05**: `product_brand` exists in WooCommerce 11.1.0 but is **not registered as translatable** in WPML. A
  P1 row needs its Arabic counterpart, so this is a WPML configuration change.
- **IA-04 and IA-35**: no collection mechanism exists. "Collections" and "Curated collection pages" need a
  decision between a `product_cat` convention, a new taxonomy, or curated pages. That is an architecture decision
  plus client naming, and the register fixes neither.

## 6. NFR-03, obligation by obligation, measured

Eight obligations, measured individually against served HTTP rather than assumed.

| # | Obligation | Native today | Measured |
|---|---|---|---|
| 1 | **Clean URLs** | **Yes** | `permalink_structure` is `/%postname%/`; `/shop/`, `/ar/shop/`, `/sample-page/` all serve 200 |
| 2 | **meta** (title, description) | **Partly** | `<title>` is emitted. **Core has no meta description field and no SEO plugin is active.** The Arabic `/ar/shop/` title reads `Shop - Mizzey`, in English, because #241 deliberately copied the English title: Arabic copy is content work |
| 3 | **sitemap** | **Yes, with a gap** | Core serves `/wp-sitemap.xml` 200 with post, page, category, product_cat and **users** sections. **It lists only English URLs**: the Arabic pages 19 to 22 are absent |
| 4 | **robots** | **Yes** | `/robots.txt` serves 200 natively, already carrying WooCommerce's own `Disallow` rules and the `Sitemap:` line |
| 5 | **canonical** | **Partly, and this is the sharpest finding** | `rel_canonical` is on `wp_head`, and its source begins `if ( ! is_singular() ) { return; }`. Measured: `/sample-page/` emits a canonical; **`/`, `/ar/` and `/shop/` emit none** |
| 6 | **breadcrumbs** | **Yes** | `woocommerce_breadcrumb()` exists, and the `core/breadcrumbs` block is registered |
| 7 | **structured data** | **Yes, untested here** | `WC_Structured_Data` exists. The shop page carried no `application/ld+json` at measurement time, because the runtime had no products. To be measured with a product present |
| 8 | **alt text** | **Yes** | Core attachment meta `_wp_attachment_image_alt`. Per-image content, not a platform gap |

### Two NFR-03 facts worth stating plainly

**hreflang is already emitted, by WPML, and #242 does not own it.** Measured on both `/shop/` and `/ar/shop/`:

```
<link rel="alternate" hreflang="ar" href="http://mizzey.local/ar/shop/" />
<link rel="alternate" hreflang="en" href="http://mizzey.local/shop/" />
<link rel="alternate" hreflang="x-default" href="http://mizzey.local/shop/" />
```

**MKT-18** contracts hreflang and is owned by E-MKT-2, not by #242. It needs nothing built, and #242 must not
build it.

**The core sitemap exposes author pages.** `wp-sitemap-users-1.xml` is in the index. That is a configuration
decision under NFR-03's sitemap obligation, not a defect.

## 7. The ownership boundary NFR-03 runs into

NFR-03's eight words each reappear as a more specific row **owned by another slice**. Read from the committed
ownership map:

| Row | Wording | Accepting owner |
|---|---|---|
| **NFR-03** | The eight SEO fundamentals | **#242**, this PBI |
| MKT-12 | "Editable title, description and canonical tag on every product, category and page" | E-MKT-2, unseeded |
| MKT-15 | "XML sitemap and robots file control" | E-MKT-2, unseeded |
| MKT-16 | "Structured data: product, organisation, breadcrumbs" | E-MKT-2, unseeded |
| MKT-18 | "hreflang language targeting tags for English and Arabic" | E-MKT-2, unseeded |
| ADM-41 | "Canonical and robots override" | E-ADM-2, unseeded |
| SSC-27 | "Per-product SEO override: title, description, slug, canonical" | E-ADM-10, unseeded |

**The distinction this PBI has to hold.** NFR-03 is the **non-functional standard**: the storefront emits clean
URLs, canonical links, a sitemap, robots, breadcrumbs, structured data, meta and alt text. The MKT, ADM and SSC
rows are the **editable admin controls** over those outputs. #242 owes the standard. It does not owe an admin
field, an override screen, or a sitemap management UI, and building one would take another slice's row.

## 8. CoreX v0.42.0, inspected only where #242 could plausibly depend on it

| Looked for | Found |
|---|---|
| A template hierarchy or routing layer | **None.** No `template_include`, `locate_template` or `get_template_part`; rendering is deferred entirely to WordPress block templates (established in #241's research and unchanged) |
| A URL, permalink, rewrite or canonical helper | **None.** CoreX writes no front-end `<html>` or `<head>` output outside admin interstitials, and has no front-end `body_class` or `language_attributes` filter |
| An SEO, sitemap or structured-data service | **None** among its 5 actions and 22 filters |
| Anything IA-related | **None.** `plugins/corex-core/src/Controllers/` and `src/Services/` are empty directories |

**So #242 has no CoreX dependency.** CoreX stays read-only, and a missing Mizzey feature is not a reason to patch
it.

## 9. The theme as it stands

A block theme with **two templates** (`front-page.html`, `index.html`) and **two parts** (`header.html`,
`footer.html`). WordPress falls back to `index.html` for anything without a more specific template, so a page's
*identity* does not require a template per page, and nothing here forces template work.

**This is the design boundary.** #242 may establish that a page exists, where it sits and what its URL is. The
page's visual design, navigation styling and components are #250 and the storefront slices, behind OD-01. A page
can have an IA identity and no design.

## 10. What must not be built here, and why

| Not doing | Reason |
|---|---|
| hreflang | WPML already emits it, and MKT-18 owns it |
| An SEO admin screen, canonical override, sitemap control | MKT-12, MKT-15, ADM-41, SSC-27, all owned elsewhere |
| A language switcher | A navigation component, owned by #253, behind OD-01 |
| Any visual design, layout or component | #250 and the storefront slices, behind OD-01 |
| Arabic page copy, category names, collection names, SEO copy | Content and client decisions. The register fixes none of them |
| An Arabic admin interface | ADM-159 fixes the admin as English; ADM-159a is P2 |
| Metadata, stock or price synchronisation | Settled by the pilot, A11, A12, B1 and the price matrix |
| Anything for IA-12, IA-13, IA-22, IA-34, IA-36 | P2 or DEF |
| IA-23, IA-33 | Contracted P1-L rows the register stages **S2**, owned by the future E-FND-2b slice. Not removed from scope; simply not owned here |

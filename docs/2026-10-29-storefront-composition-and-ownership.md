# Storefront composition and ownership map

**Status: working draft, 29 October 2026, for the high-fidelity design and the build.** Derived from the closed
low-fidelity wireframes (`design/wireframes/manifest.json`, `stabilisation.phase_closure`), the design inventory
(`design/inventory/`), the signed Technical Design (MS-TDD-2026-033 v1.3, sections 2 to 4) and D-18 (approved by the
owner on 21 October 2026, carried by PR #395 and PR #396). It changes no scope, no signed document and no code.

**What it is not.** The Technical Design, section 4.1, says that no number of sections, blocks or variants is fixed
before the interface design is approved. The section list, variants and editable fields below are the proposals the
inventory already records, so that the interface design and the build start from one map; the approved interface
design confirms or changes them.

## 1. The rule

The owner's rule, 29 October 2026, consistent with D-18 and ADR-0003: **a framework capability that exists in CoreX is
used from CoreX; anything commerce-related stays with WooCommerce; native WordPress and WooCommerce come first.**
Mizzey writes only the contracted behaviour, thin adapters and the storefront presentation.

| Capability | Owner | Mechanism |
|---|---|---|
| Container, configuration, routing, service providers, the mail queue, forms, blocks API, media, security foundation, planned maintenance, framework administration | **CoreX** | The pinned CoreX framework; each Mizzey module is a CoreX service provider (Technical Design, section 4). Only the add-ons D-18 section 3 allows |
| Products, variations, prices, stock records, cart, checkout, orders and statuses, coupons, refunds, shipping zones, the customer account, transactional customer emails, Coming Soon | **WooCommerce** | Native blocks, templates, endpoints and settings, extended through supported hooks |
| Content, pages, menus (`wp_navigation`), templates and template parts, users, roles, media, the XML sitemap | **WordPress** | The block editor and Full Site Editing |
| English and Arabic content, strings, translated menus | **WPML** (licensed) | WPML, WooCommerce Multilingual, String Translation |
| Contracted rules and integrations: cart merge, free shipping rule, welcome discount, Egyptian address, idempotency, Paymob and Bosta adapters, COD reconciliation, search, SEO fields, review eligibility, wishlist, the ERP stock adapter, the section library's blocks | **mizzey-site** | The 13 modules of the Technical Design, section 4 (`Mizzey\Storefront` to `Mizzey\Notify`) |
| The storefront presentation: block theme, `theme.json` tokens, templates, template parts, styles of the native blocks | **mizzey-theme** | Never wp-admin (D-18 section 4) |
| Redirects and the missing-page log | **Redirection** (free) | Owner ruling R1, 21 October 2026; the adapter refuses loops and a target equal to its source |

## 2. Globals

| Global | Where it lives | Owner |
|---|---|---|
| Design tokens: type roles, spacing, grid, colour roles, radius, elevation, focus, motion, icons | `theme.json` global settings and styles; the roles are the `foundations` layer of `design/inventory/components.json`. Values arrive with the approved brand identity (OD-01) | mizzey-theme |
| Site logo, brand colours, typefaces | Theme global settings, managed centrally (Technical Design, section 4.1) | mizzey-theme, set by the client |
| Header, mobile header, footer | Block template parts, edited in the site editor; the footer has its own state in `ad-navigation-editor` | WordPress, presented by mizzey-theme |
| Navigation menus, mega menu | Core navigation block and `wp_navigation` records, one per language | WordPress and WPML |
| Language switcher | WPML switcher, placed in the header and mobile navigation | WPML |
| Mini-cart | WooCommerce mini-cart block (IX-02: the cart control opens it; an add confirms in place) | WooCommerce |
| Search field and suggestions | Core search with `Mizzey\Search` behind it (#304, custom search baseline) | mizzey-site |
| Promotional banner | A section of the library, placed globally | mizzey-site (`Mizzey\Storefront`) |
| Consent banner | Decision pending (Technical Design, row 22; #288) | open |
| Breadcrumbs | WooCommerce and core breadcrumbs | WooCommerce, WordPress |
| Transactional emails | WooCommerce email templates and settings. The Technical Design names the CoreX mail queue (`Mizzey\Notify`) for sending, but the ownership audit (PR #396) finds WooCommerce mail separate and no SMTP service in CoreX: the sending path, one send per message and one sender authority are proven in the build | WooCommerce for content; the transport is verified, not assumed |

## 3. Pages, parts and sections

Generated from `design/inventory/surfaces.json` and `components.json`. "Native starting point" is the inventory's
native baseline; "sections and components" are the structural components each surface uses (foundations and controls
omitted, since every surface uses them).

### Global parts

| Surface | Address (English) | Native starting point (inventory) | Sections and components |
|---|---|---|---|
| `gl-header` Header, desktop |  | Block theme header template part (Full Site Editing), with the WooCommerce mini-cart and customer account blocks. | `nv-header`, `nv-language-switcher`, `fb-badge` |
| `gl-header-mobile` Header, mobile |  | The same header template part, at the mobile breakpoint. | `nv-header` |
| `gl-mobile-nav` Mobile navigation menu |  | Core navigation block overlay. | `nv-mobile-nav`, `nv-language-switcher`, `fb-drawer` |
| `gl-mega-menu` Mega menu and category navigation |  | Core navigation block | `nv-mega-menu` |
| `gl-footer` Footer |  | Block theme footer template part, edited through Full Site Editing. | `nv-footer`, `cn-signup-prompt` |
| `gl-promo-banner` Promotional banner, on the storefront |  | A section of the curated library | `cn-promo-banner` |
| `gl-language-switcher` Language switcher |  | WPML language switcher | `nv-language-switcher` |
| `gl-cookie-consent` Consent and cookie banner |  | A consent management tool, selected and configured in #288 |  |
| `gl-breadcrumbs` Breadcrumbs |  | woocommerce_breadcrumb() and the core breadcrumbs block. | `nv-breadcrumbs` |
| `sf-mini-cart` Mini-cart in the header |  | WooCommerce mini-cart block. | `cm-cart-line`, `cm-free-shipping-progress`, `fb-drawer` |
| `sf-search-suggestions` Search suggestions as you type |  | Selected in #304 | `fb-skeleton` |

### Home and content pages

| Surface | Address (English) | Native starting point (inventory) | Sections and components |
|---|---|---|---|
| `sf-home` Home page | / | A static front page built from the curated section library in the block editor. | `cm-product-card`, `cn-hero`, `cn-promo-banner`, `cn-product-rail`, `cn-category-tiles`, `cn-collection-feature`, `cn-brand-highlights`, `cn-image-text`, `cn-trust-strip`, `cn-signup-prompt` |
| `ct-campaign-page` Offer and campaign page | /offers/ | Built by the client from the approved templates and the delivered section library. | `cn-hero`, `cn-promo-banner`, `cn-product-rail`, `cn-image-text` |
| `ct-static-page` Static and policy page layout |  | A page built from the curated section library in the block editor. | `cn-rich-text` |
| `ct-about` About | /about/ | A page built from the curated section library. | `cn-rich-text`, `cn-image-text` |
| `ct-authenticity` Authenticity guarantee | /authenticity-guarantee/ | A page built from the curated section library. | `cn-image-text` |
| `ct-help` Help and FAQ | /help/ | A page built from the curated section library. | `cn-accordion` |
| `ct-shipping-policy` Shipping policy | /shipping-policy/ | A page built from the curated section library. |  |
| `ct-returns-policy` Return and refund policy | /returns-and-refunds/ | A page built from the curated section library. |  |
| `ct-privacy` Privacy policy | /privacy-policy/ | A page built from the curated section library. | `cn-rich-text` |
| `ct-terms` Terms and conditions | /terms-and-conditions/ | A page built from the curated section library. | `cn-rich-text` |
| `ct-contact` Contact us | /contact-us/ | A page from the section library with a contact form. | `fb-inline-validation`, `fb-success-state`, `cn-contact-form` |

### Listing and search

| Surface | Address (English) | Native starting point (inventory) | Sections and components |
|---|---|---|---|
| `sf-shop` Products listing | /shop/ | WooCommerce product archive template (block theme). | `cm-product-card`, `fb-skeleton` |
| `sf-category` Category page | /product-category/{term}/ | WooCommerce product category archive. | `nv-breadcrumbs` |
| `sf-collection` Collection page | /collection/{term}/ | The site's collection taxonomy, served with the platform's product archive template. |  |
| `sf-brand` Brand page | /brand/{term}/ | WooCommerce brand taxonomy archive. |  |
| `sf-search-results` Search results | /?s={query} | WordPress search results template with the product listing. | `fb-skeleton` |

### Product page

| Surface | Address (English) | Native starting point (inventory) | Sections and components |
|---|---|---|---|
| `sf-pdp-simple` Product details page, simple product | /product/{slug}/ | WooCommerce single product template (block theme). | `nv-breadcrumbs`, `nv-tabs`, `cm-price`, `cm-add-to-cart`, `cm-product-badge`, `cm-share`, `cn-accordion`, `cn-trust-strip` |
| `sf-pdp-variable` Product details page, variable product | /product/{slug}/ | WooCommerce add to cart with options block. | `cm-variant-selector`, `cm-add-to-cart` |

### Cart and checkout

| Surface | Address (English) | Native starting point (inventory) | Sections and components |
|---|---|---|---|
| `sf-cart` Cart | /cart/ | WooCommerce Cart block. | `cm-price`, `cm-cart-line`, `cm-order-summary`, `cm-coupon`, `fb-notice`, `cn-product-rail` |
| `ck-checkout` Checkout | /checkout/ | WooCommerce Checkout block. | `cm-order-summary`, `fb-notice` |
| `ck-order-received` Order confirmation | /checkout/order-received/{id}/ | WooCommerce order confirmation block. | `cm-order-summary`, `fb-success-state` |

### Account

| Surface | Address (English) | Native starting point (inventory) | Sections and components |
|---|---|---|---|
| `au-sign-in` Sign in | /my-account/ | WooCommerce My Account sign-in form, shown to a signed-out visitor. | `nv-tabs` |
| `au-register` Registration | /my-account/ | WooCommerce My Account registration form. | `fb-inline-validation` |
| `au-lost-password` Lost password | /my-account/lost-password/ | WooCommerce lost-password endpoint. |  |
| `au-reset-password` Set a new password |  | WooCommerce reset-password form. |  |
| `ac-dashboard` Account dashboard | /my-account/ | The replaceable templates/myaccount/dashboard.php inside my-account.php, a paragraph of links by default | `fb-notice` |
| `ac-orders` Order history | /my-account/orders/ | The orders endpoint and its paging, shown by the replaceable templates/myaccount/orders.php as a table by default | `nv-pagination`, `cm-status-label`, `fb-empty-state` |
| `ac-order-detail` Order detail, with tracking and invoice | /my-account/view-order/{id}/ | The view-order endpoint, shown by the replaceable templates/myaccount/view-order.php | `cm-price`, `cm-order-summary`, `cm-address-card`, `cm-status-timeline`, `cm-status-label` |
| `ac-addresses` Address book | /my-account/edit-address/ | The edit-address endpoint (templates/myaccount/my-address.php and form-edit-address.php) holds one billing and one shipping address | `cm-address-card`, `fb-inline-validation`, `fb-empty-state`, `fb-modal` |
| `ac-account-details` Profile details |  | The edit-account endpoint (templates/myaccount/form-edit-account.php), saved by WC_Form_Handler::save_account_details: first name, last name, display name and email are required by default (filterable), the password change sits in the same form, and an email change is saved at once with no verification step (OP-19). |  |
| `ac-tracking` Track an order without signing in | /track-your-order/ | The order tracking form (the woocommerce_order_tracking shortcode, templates/order/form-tracking.php): lookup by order number and billing email, then the native order details | `cm-status-timeline` |
| `sf-wishlist` Wishlist | /wishlist/ | None in the standard store | `cm-product-card`, `cm-stock-state`, `fb-toast`, `fb-empty-state` |

### Emails

| Surface | Address (English) | Native starting point (inventory) | Sections and components |
|---|---|---|---|
| `em-shell` Email layout: header, footer, consent and sender details |  | WooCommerce email template. | `cn-email-block` |

### Section library

| Section | Variants (proposed) | The client edits | Fixed in code | Used on |
|---|---|---|---|---|
| `cn-hero` Hero | image at inline-start, image at inline-end, full bleed | heading, text, button label and link, image, alt text | height, type scale, spacing, overlay | sf-home, ct-campaign-page |
| `cn-promo-banner` Promotional banner | strip, card | text, link, image, start, optional end, page placement, enabled | position rules, size | gl-promo-banner, sf-home, ct-campaign-page |
| `cn-product-rail` Product rail | manual pick, by collection, by category, newest, by badge | heading, source, item count within a fixed range | card layout, columns per viewport | sf-home, sf-pdp-related, sf-pdp-recently-viewed, sf-recently-viewed, sf-cart, ct-campaign-page |
| `cn-category-tiles` Category tiles | four tiles, six tiles | category, image, label | grid, aspect ratio | sf-home |
| `cn-collection-feature` Featured collections | one | collection, image, label | grid | sf-home |
| `cn-brand-highlights` Brand highlights | one | brand, logo | grid | sf-home |
| `cn-rich-text` Rich text | one column, two columns | text, headings to a fixed depth, links | fonts, colours | ct-static-page, ct-about, ct-privacy, ct-terms |
| `cn-image-text` Image with text | image at inline-start, image at inline-end, stacked | image, alt text, heading, text, link | proportions | sf-home, ct-about, ct-authenticity, ct-campaign-page |
| `cn-accordion` Accordion | single open, multiple open | question, answer | styling | ct-help, sf-pdp-simple |
| `cn-trust-strip` Trust strip | three items, four items | icon from a fixed set, label, link | layout | sf-home, sf-pdp-simple |
| `cn-signup-prompt` Newsletter or account signup prompt | one | heading, text, button label | layout | sf-home, gl-footer |


## 4. Commerce components

| Component | Native mechanism | Mizzey addition |
|---|---|---|
| `cm-product-card` | WooCommerce product collection and product template blocks | Badge (`cm-product-badge`) and stock state presentation |
| `cm-price` | WooCommerce product price block | None |
| `cm-gallery` | WooCommerce product gallery block | None |
| `cm-variant-selector` | WooCommerce add to cart with options block | Text chips; native swatches only if accepted at #254 |
| `cm-stock-state` | WooCommerce stock status | Wording and timing per the ERP specification (PRE-09); only the ERP moves stock (D-19) |
| `cm-add-to-cart` | WooCommerce add to cart blocks | In-place confirmation (IX-10); wishlist action |
| `cm-cart-line`, `cm-order-summary`, `cm-coupon` | WooCommerce Cart and Checkout blocks | Cart merge notice (`Mizzey\Cart`) |
| `cm-free-shipping-progress` | None | `Mizzey\Cart`, from the configured free shipping rule (BR-002) |
| `cm-address-card` | WooCommerce addresses | Egyptian address structure (`Mizzey\Checkout`) |
| `cm-status-timeline`, `cm-status-label` | WooCommerce order statuses | The two added statuses; the carrier status map (`Mizzey\Shipping`) |
| `cm-rating`, `cm-review-item` | WooCommerce reviews | Review eligibility (D-17, `Mizzey\Catalogue`) |
| `cm-product-badge` | None | Manual badges (D-19), `Mizzey\Catalogue` |
| `cm-payment-method`, `cm-shipping-method` | WooCommerce payment and shipping methods | Paymob and Bosta adapters |
| `cm-share` | None | Share links, no tracking script |

## 5. Data a section stores

Each library section is one block with a fixed structure (Technical Design, section 4.1). Its stored data is the
"client edits" column of the section library table, saved as block attributes in the page content, per language
through WPML. Product data is never copied into a block: a rail or tile stores a source (a manual pick, a collection
or a category) and the products are read from WooCommerce at render time. Curated rails stay manual (D-19); the
"newest" and "by badge" rail sources in the inventory's proposal need the owner's confirmation against D-19 before the
interface design uses them.

## 6. Open points this map depends on

- D-18 sections 1 to 5 and 6 to 9 are approved but not on `main` until PR #395 and PR #396 merge.
- Consent banner mechanism (Technical Design, row 22; #288).
- The email sending path (`Mizzey\Notify` in the Technical Design against the ownership audit): verified in the build,
  with the mail service account the client approves (INT-05).
- Which CoreX add-ons are allowed (D-18 section 3): corex-blocks for the section library, corex-forms and corex-email
  for the contact page (CMS-04), corex-media for WebP (NFR-02).
- The CoreX target: PR #351 (v0.46.1) against D-18 section 6 (v0.47.0).
- The section list, variants and editable fields: confirmed by the approved interface design.

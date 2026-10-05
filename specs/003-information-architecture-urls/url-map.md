# URL map, both languages

Feature `003-information-architecture-urls`, PBI #242. **Evidence for AC-242-05 and AC-242-08, not a contracted
deliverable**: no register row obliges a URL map document. It is produced because it is the clearest way to show
what the runtime serves, and `t22-information-architecture.php` asserts the runtime against it.

English is served at the root and Arabic under the `/ar/` prefix, by WPML directory negotiation with
`directory_for_default_language = 0`. That configuration is #241's, not this feature's.

Measured on the disposable runtime, WordPress 7.1.2 with WooCommerce 11.1.0, WPML 4.9.7.

## Pages that have a record

Each has an English record and an Arabic counterpart in one translation group, with the English record as source.

| Row | Page | English | Arabic |
|---|---|---|---|
| IA-01 | Home | `/` | `/ar/` |
| IA-02 | Products | `/shop/` | `/ar/shop/` |
| IA-08 | Cart | `/cart/` | `/ar/cart/` |
| IA-09 | Checkout | `/checkout/` | `/ar/checkout/` |
| IA-11 | Wishlist | `/wishlist/` | `/ar/wishlist/` |
| IA-17 | My Account | `/my-account/` | `/ar/my-account/` |
| IA-21 | Tracking | `/track-your-order/` | `/ar/track-your-order/` |
| IA-24 | About | `/about/` | `/ar/about/` |
| IA-25 | Contact us | `/contact-us/` | `/ar/contact-us/` |
| IA-26 | Help and FAQ | `/help/` | `/ar/help/` |
| IA-27 | Authenticity guarantee | `/authenticity-guarantee/` | `/ar/authenticity-guarantee/` |
| IA-28 | Shipping policy | `/shipping-policy/` | `/ar/shipping-policy/` |
| IA-29 | Return and refund policy | `/returns-and-refunds/` | `/ar/returns-and-refunds/` |
| IA-30 | Privacy policy | `/privacy-policy/` | `/ar/privacy-policy/` |
| IA-31 | Terms and conditions | `/terms-and-conditions/` | `/ar/terms-and-conditions/` |
| IA-32 | Offers and campaigns | `/offers/` | `/ar/offers/` |

**IA-01 is a static front page.** `show_on_front` is `page` and `page_on_front` is the Home record, so the
storefront's root is a page rather than the blog index.

**IA-09 answers a redirect when the cart is empty**, in both languages: `/checkout/` goes to `/cart/` and
`/ar/checkout/` goes to `/ar/cart/`. That is WooCommerce's own behaviour, measured under #241, and the redirect
staying inside its own language is what t22 asserts.

**IA-30 reuses the record WordPress created.** The default privacy-policy draft was published rather than
duplicated, which is why its id is lower than its neighbours'.

## Archives, which need no page record

| Row | Page | English | Arabic | Base |
|---|---|---|---|---|
| IA-03 | Categories | `/product-category/{term}/` | `/ar/product-category/{term}/` | WooCommerce `category_base` |
| IA-05 | Brands | `/brand/{term}/` | `/ar/brand/{term}/` | WooCommerce `product_brand`, with the address word fixed by `MizzeySite\Catalogue\Brands` |
| IA-04, IA-35 | Collections | `/collection/{term}/` | `/ar/collection/{term}/` | `MizzeySite\Catalogue\Collections::SLUG` |
| IA-07 | Product details | `/product/{slug}/` | `/ar/product/{slug}/` | WooCommerce `product_base`, with WPML slug translation on for `product` |
| IA-06 | Search results | `/?s={query}` | `/ar/?s={query}` | WordPress search |

**The three taxonomy bases are deliberately distinct** (`product-category`, `brand`, `collection`) so a
collection archive can never collide with a category archive, which guardrail G-1 forbids.

**The address word of each taxonomy is the same in every language.** WooCommerce's default word for the brand
taxonomy is a translatable string, which gave an Arabic request links under an Arabic word that no routing rule
answered. The site now supplies `brand` as the default (repair of 5 October 2026, `research.md` section 11).
**Each of the three archives shows its term and the products assigned to it**, in both languages: the collection
archive is served with the platform's product archive template (`research.md` section 12).

**IA-05 needed one configuration change**: `product_brand` ships with WooCommerce and was not registered as
translatable in WPML, so its Arabic archive did not exist. **IA-04 and IA-35 needed the taxonomy**, which the
site plugin registers because the register settles the model at ADM-57, manually curated collections, P1 and S1.

## Account and confirmation routes, which are endpoints

**No page record exists for any of these**, and none may be created: a page would give one screen two managed
routes, which guardrail G-1 forbids. The Arabic slug comes from WPML String Translation, context `WP Endpoints`.

| Row | Screen | English | Arabic | Mechanism |
|---|---|---|---|---|
| IA-14 | Login | `/my-account/` | `/ar/my-account/` | The My Account base page when signed out. **Not an endpoint** |
| IA-15 | Register | `/my-account/` | `/ar/my-account/` | The same page, registration form when enabled. **Not an endpoint** |
| IA-16 | Forgot password | `/my-account/lost-password/` | `/ar/my-account/lost-password-ar/` | `lost-password` endpoint |
| IA-18 | Addresses | `/my-account/edit-address/` | `/ar/my-account/edit-address-ar/` | `edit-address` endpoint |
| IA-19 | Orders | `/my-account/orders/` | `/ar/my-account/orders-ar/` | `orders` endpoint |
| IA-20 | Order details | `/my-account/view-order/{id}/` | `/ar/my-account/view-order-ar/{id}/` | `view-order` endpoint |
| IA-10 | Order confirmation | `/checkout/order-received/{id}/` | `/ar/checkout/order-received-ar/{id}/` | `order-received` endpoint on checkout |

**The Arabic endpoint slugs are placeholders**, suffixed `-ar` rather than translated. The URLs must differ per
language and stay stable for the scenarios; the final Arabic wording is a client content input.

**IA-21 Tracking is not an endpoint** and is not a duplicate of IA-20. It is a page carrying WooCommerce's native
order-tracking form, which looks an order up by number and email and so serves a guest who is not signed in.
IA-20's `view-order` requires a signed-in customer. Two capabilities, two audiences, one route each.

## NFR-03 artefacts

| Obligation | URL or output | Source |
|---|---|---|
| Clean URLs | `permalink_structure` is `/%postname%/` | #241's T-01 |
| Sitemap | `/wp-sitemap.xml` and its sections, **covering both languages** | Core, extended by `MizzeySite\Seo\SitemapLanguages` |
| robots | `/robots.txt`, with WooCommerce's own rules and the `Sitemap:` line | Core and WooCommerce |
| canonical | `<link rel="canonical">` on every contracted route, **including archives** | Core for singular, `MizzeySite\Seo\ArchiveCanonical` for the rest |
| meta | `<meta name="description">` per page, never shared | `MizzeySite\Seo\MetaDescription` |
| hreflang | `hreflang` for `ar`, `en` and `x-default` | **WPML, already.** MKT-18 owns the row; nothing built |
| breadcrumbs | `woocommerce_breadcrumb()` and the `core/breadcrumbs` block | WooCommerce and core. Emission arrives with the storefront templates |
| structured data | `WC_Structured_Data`, wired to the product and breadcrumb template hooks | WooCommerce. Emission arrives with the product template, #254; the row is MKT-16 |

## What this map does not define

No redirect map and no trailing-slash policy, because neither SRS §5 nor NFR-03 obliges one. WordPress resolves
a trailing-slash variant to the canonical form, and guardrail G-1 asserts that no contracted page has two managed
routes. No production URL or domain: **OD-27** is open, and nothing here decides it.

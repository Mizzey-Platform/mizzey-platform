# Verification record: information architecture and URL structure

Feature `003-information-architecture-urls`, PBI #242. Thirty register rows: 28 P1 and two P1-L, all S1.

**Three states, reported separately, per constitution M-7:**

| State | Where this stands |
|---|---|
| **Workflow complete** | Yes. Spec, clarification, research, plan, checklist, tasks, analysis, implementation, verification |
| **Technically verified** | **AC-242-01 to AC-242-15 and guardrail G-1**, on the disposable runtime at the recorded versions |
| **Contractually accepted** | **No criterion.** Acceptance happens only through the Acceptance and UAT Plan MS-UAT-2026-027 |

**Closure still owes the project Definition of Done**, which this record does not weaken:

| Row | Wording | State |
|---|---|---|
| **DOD-04** | "Tests written and executed per system layer, **plus manual testing on staging**" | **Run on staging on 5 October 2026 as a scripted pass in a real browser, and it found a defect.** 51 of 52 checks without a finding. **Finding F-242-1**: on the Arabic brand archive the canonical link and all three language links point at addresses that answer 404, which is AC-242-09. A pass by a person is still owed. See "Staging pass" below |
| DOD-09 | "Client approval of acceptance criteria before a feature moves to production" | Outstanding, a client gate rather than an engineering one |

**The staging result is reported as it came out.** Nothing in this record reports a manual staging test that did
not happen: what ran is a scripted pass in a real browser by the agent, not a person looking.

## Staging pass, 5 October 2026

Run on the staging environment of #244, on the code of `main`, in the installed Chrome. Every page of the URL
map in both languages, a product, a category archive, a brand archive, search results and the lost-password
screen; the empty-cart checkout; an unknown address; `robots.txt`; the sitemap; and, signed in as the invented
demonstration customer, the order list, the addresses and one order.

| Result | Detail |
|---|---|
| 51 of 52 checks without a finding | Status, address, language, direction, canonical link, language links and whether each answers, on every page |
| **Finding F-242-1, a defect** | `/ar/brand/{term}/` answers 200 and is the documented address. Its canonical link and its `ar`, `en` and `x-default` language links carry a translated form of "brand" and **all four answer 404**. The English brand archive and both category archives are correct. **AC-242-09 does not hold on that page**, and the scenario `t23` did not catch it |
| The sitemap | 34 page addresses, 16 Arabic. AC-242-10 holds on staging |
| The lost-password screen's canonical is the account page | Correct: it is an endpoint, not a page |

**AC-242-09 is therefore technically verified except on the Arabic brand archive**, where it fails. The feature
needs a correction and a scenario that fetches the archive as a visitor does. Full record:
`docs/2026-10-05-staging-verification.md`; evidence: `specs/005-local-staging-environment/evidence/`.

Versions this is evidence for, and no others (M-8): WordPress 7.1.2, WooCommerce 11.1.0, WPML 4.9.7, WPML String
Translation 3.5.4, WCML 5.5.7, CoreX 0.42.0, PHP 8.3.6, MySQL 8.3.0.

## What the audit changed before anything was built

Two readings, both taken from the source documents rather than from the PBI's title.

**SRS §5 is a page inventory.** All twenty-nine page rows cite it; its heading is "the general structure of the
pages" and its body is a table of group against page name, with no slug pattern, canonical rule, redirect policy
or trailing-slash decision. So the page rows oblige existence, position and reachability in both languages.
**#242 owns the runtime IA, URL and SEO output assigned to its registered rows, and separately registered SEO and
admin-control rows keep their own ownership.**

**Eight rows are not pages.** Measured row by row rather than grouped: IA-10 is the checkout `order-received`
endpoint; IA-16, IA-18, IA-19 and IA-20 are the `lost-password`, `edit-address`, `orders` and `view-order`
account endpoints; **IA-14 Login and IA-15 Register are not endpoints at all** but the My Account authentication
experience on the base page; **IA-17 is the base page itself**; and **IA-21 is neither**. No page was created to
make a screen look like a register row.

## Implementation, row by row

### Satisfied natively, nothing built

| Rows | Mechanism |
|---|---|
| IA-02 | The WooCommerce shop page, which already existed in both languages |
| IA-03 | `product_cat` archives, already translatable |
| IA-06 | WordPress search, `/?s=` and `/ar/?s=` |
| IA-07 | WooCommerce product singular, with WPML slug translation already on for `product` |
| IA-08, IA-09, IA-17 | The cart, checkout and My Account pages, already in both languages |
| IA-14, IA-15 | The My Account authentication experience on the base page |
| NFR-03 clean URLs | `permalink_structure` `/%postname%/`, from #241 |
| NFR-03 robots | `/robots.txt`, with WooCommerce's rules and the `Sitemap:` line |
| NFR-03 hreflang | **WPML already emits** `ar`, `en` and `x-default`. **MKT-18 owns the row**; nothing built |
| NFR-03 alt text | Core attachment meta |

### Configuration

| Rows | Change |
|---|---|
| IA-01 | A Home page record in both languages, and `show_on_front` set to it. The storefront root was the blog index |
| IA-11, IA-21, IA-24 to IA-32 | Twelve page records in English with twelve Arabic counterparts, one translation group each, English as source. The two WordPress drafts (`privacy-policy`, `refund_returns`) were **published and reused, not duplicated** |
| IA-31 | WooCommerce's `terms` page assignment, which was unset |
| IA-05 | `product_brand` registered translatable in WPML. It ships with WooCommerce and was not translatable, so a P1 row had no Arabic archive |
| IA-10, IA-16, IA-18 to IA-20 | Arabic slugs for ten endpoints through WPML String Translation. **No pages**: a page would give one screen two managed routes |

### Production code, four small classes

| Class | Row | Why it exists |
|---|---|---|
| `Catalogue\Collections` | IA-04, IA-35 | Registers `product_collection`. The register settles the model: **ADM-57 makes manually curated collections P1 and S1** while ADM-58 defers rules-based membership to P2 |
| `Seo\ArchiveCanonical` | NFR-03 | Core's `rel_canonical()` opens `if ( ! is_singular() ) { return; }`, so archives had no canonical |
| `Seo\MetaDescription` | NFR-03 | Core has no meta description field and no SEO plugin is active |
| `Seo\SitemapLanguages` | NFR-03 | **Measured first**: WPML hooks none of the `wp_sitemaps_*` filters and ships no core-sitemap integration, so the sitemap carried only the default language. No configuration fixes it |

**All three SEO classes emit and nothing more**, which is the boundary that keeps them inside NFR-03:

- Every value passes through a documented filter, `mizzey_canonical_url` or `mizzey_meta_description`, so
  **MKT-12, MKT-15, ADM-41 and SSC-27 can override it later without this code changing** (FR-013, asserted).
- **No metadata storage is introduced** (FR-014). Each value is derived from the record WordPress already holds,
  so a later per-record override field becomes the source rather than a second store to reconcile.
- **No SEO plugin was added** (FR-015). One would deliver four other slices' admin surfaces.

## Contracted acceptance criteria

| # | Criterion | Result |
|---|---|---|
| AC-242-01 | Every contracted page reachable in English, 200, serving its own record | **PASS** |
| AC-242-02 | Every contracted page reachable in Arabic under the prefix, never the English counterpart | **PASS** |
| AC-242-03 | One translation group per pair, English as source | **PASS** |
| AC-242-04 | Account URLs are endpoints, resolving in both languages, with no second route | **PASS** |
| AC-242-05 | Product, category, collection and brand URLs follow one documented pattern per language | **PASS** |
| AC-242-06 | Order confirmation reachable only as the post-checkout endpoint, not a listed page | **PASS** |
| AC-242-07 | Search results have a stable URL carrying the query, both languages | **PASS** |
| AC-242-08 | Clean URLs; no contracted page reachable only by a query string | **PASS** |
| AC-242-09 | A canonical link on every contracted URL, **including archives** | **PASS** |
| AC-242-10 | The sitemap includes both languages for every contracted page | **PASS** |
| AC-242-11 | `robots.txt` served, disallowing no contracted page | **PASS** |
| AC-242-12 | A breadcrumb mechanism present and not suppressed | **PASS** |
| AC-242-13 | A structured-data mechanism present and correctly wired | **PASS** |
| AC-242-14 | A meta description per page, never shared | **PASS** |
| AC-242-15 | Alt text supported, mechanism available | **PASS** |

**AC-242-12 and AC-242-13 assert the mechanism, not the emission, and that is an ownership boundary.** Measured:
`WC_Structured_Data::generate_product_data` is wired to `woocommerce_single_product_summary` at priority 60 and
`generate_breadcrumblist_data` to `woocommerce_breadcrumb`, so the stack is correct. Neither fires yet because
the theme has no product template. **The product template is PDP-01 to PDP-24, owned by #254**, and **MKT-16
contracts the structured-data row, owned by E-MKT-2**. An earlier draft of these two criteria claimed the output,
which over-claimed against both. Building a template here would also breach `Design dependency = none`.

## Derived architecture guardrail, reported separately

| | Rule | Result |
|---|---|---|
| **G-1** | One managed route per screen: no sluggified duplicate, no second page record for a screen the platform already routes, and a trailing-slash variant resolving to one canonical form | **PASS** |

**G-1 is not contracted acceptance.** NFR-03 says "Clean URLs" and does not say "no duplicate URLs". It is kept
because the row-by-row account analysis showed exactly how duplicates would arise, and it is asserted negatively:
`t22` checks that no page record duplicates an endpoint slug in either language, and finds none.

## Evidence

| File | Contents |
|---|---|
| `evidence/clean-baseline.txt` | The reset preceding the run, its configuration summary, exit code, and what the runtime held afterwards |
| `evidence/final-suite.txt` | The complete suite output, every scenario's notes |
| `evidence/final-suite.json` | The same verdicts, machine readable |

**One run, on a genuinely clean baseline, serially: 19 scenarios, 0 failed.** Twelve contract scenarios pass,
t09 stays `FACT (pending CX-01)`, the four probes stay fact-finding, and t21, t22 and t23 pass. The runtime
afterwards holds 0 products, 0 orders, no collection terms, and the 34 baseline pages, which is the twelve this
feature adds plus their Arabic counterparts plus the WordPress and WooCommerce defaults and their Arabic
counterparts.

**Two of my own process errors are recorded, because both looked like code defects and neither was.** A stale
grep line led me to blame AC-8 for a failure that was elsewhere. And I ran two suites concurrently against the
one runtime, which produced page ids in the eighties and missing system-page translation groups: that looked
exactly like a regression in the #241 fixture and was an artifact of the concurrency. The figure above comes from
a single serial run with nothing else touching the runtime.

Two new scenarios: `t22-information-architecture.php` (AC-242-01 to AC-242-07 and G-1) and
`t23-seo-fundamentals.php` (AC-242-08 to AC-242-15). Both assert served HTTP, because a URL cannot be asserted
honestly any other way.

## Findings the implementation produced, each fixed at its cause

| Finding | Cause | Fix |
|---|---|---|
| Eleven of twelve new pages had **no WPML language at all**, `trid NULL lang NULL` | WPML attaches a post to a language on `save_post`, which a page inserted inside the fixture never reaches. Only the pre-existing draft had a group | Each English record's language details are set explicitly before the Arabic pass reads them |
| **No endpoint slug was translated** | WooCommerce registers the slugs under `WP Endpoints` only when it runs in an admin context, which happens after `setup.php`. Every lookup found no string id | The step moved to its own file, run after `admin-visit.php` |
| t22 failed IA-09 on a **302** | WooCommerce redirects an empty cart away from checkout, in both languages. Asserting 200 was wrong | The redirect target is asserted instead, **per language**: `/checkout/` goes to `/cart/` and `/ar/checkout/` to `/ar/cart/`, which is a stronger check of language-correct routing than a 200 |
| t22's Arabic search URL was the **English root** | `home_url()` under `switch_lang()` did not return the Arabic root | The URL comes from the language URL WPML itself reports |
| t23's FR-013 override probe could not work | The filters were added in the scenario's process and the page fetched over HTTP, a different process | Asserted in process, with the emitted markup recorded |
| **t21 regressed**, its AC-8 probe reading the English source string | `unload_textdomain( $domain )` defaults to `$reloadable = false`, which stops the domain loading again in the same request. #242 introduced an earlier just-in-time attempt by giving the collection taxonomy translatable labels, and the non-reloadable unload then cemented the failure. Measured in two fresh processes: the default returns English, `$reloadable = true` returns Arabic | t21 uses the reloadable form. A test fix: the production behaviour is ordinary and correct |
| The hard-coded-text check flagged `'<link rel="canonical" href="%s" />'` | Its heuristic saw letters and a space in a markup template | A literal that is only tags and format specifiers is exempt, with a case asserting that a sentence inside a wrapper still fails |

## Boundaries held

No SEO admin control, override field, sitemap management screen or hreflang output. No navigation, language
switcher, visual design, layout, component or design token: `Design dependency` stays `none`. No category name,
collection name, Arabic copy, SEO text or marketing content invented; page bodies are marked placeholders and the
Arabic endpoint slugs are suffixed placeholders. No stock, price, ERP or migration work. No metadata
synchronisation framework. SKU is never used as multilingual identity. **CoreX is unmodified**: it was inspected
only where #242 could plausibly depend on it, and provides no template, routing, URL, canonical, SEO or sitemap
layer.

Rows this feature deliberately does not touch: IA-12, IA-13, IA-34, IA-36 (P2), IA-22 (DEF), and **IA-23 and
IA-33**, contracted P1-L rows the register stages **S2**, owned by the future E-FND-2b slice and not removed from
scope.

## Remaining content inputs

Named rather than invented. None blocks the implementation; each is the final content for a row whose capability,
route and bilingual behaviour are delivered.

| Input | Rows | What is needed |
|---|---|---|
| **D-242-1** | IA-01 | Confirmation that the Home page is a static page, which is how it is configured |
| **D-242-2** | IA-04, IA-35 | The final collection names, copy and imagery. The model follows ADM-57; the tests use fixture terms |
| **D-242-3** | IA-05 | The brand list. The taxonomy is translatable and its archive serves in both languages |
| **D-242-4** | IA-21 | The tracking screen's presentation detail. The route and data source follow ORD-05, SHIP-03 and ADR-0002 |
| Page copy | IA-24 to IA-32 | Body text for the support and policy pages, and the Arabic translations |
| Arabic endpoint slugs | IA-10, IA-16, IA-18 to IA-20 | The final Arabic wording, currently suffixed placeholders |

## Guard Gate

| Guard | Scope | Outcome |
|---|---|---|
| **wp-guard** | The four new classes and the bootstrap | Clean. No variable output unescaped, output through `esc_url` and `esc_attr`, no request superglobals, no `$wpdb`, ABSPATH guard in all four, no unbounded query, literal text domain, public filters prefixed `mizzey_` |
| **woo-guard** | The taxonomy registration and the endpoint configuration | Clean. No order, product, cart, checkout or money code; no `get_post_meta` on an order; no direct meta write |
| **test-guard** | The two new scenarios and the fixture | Applied |
| **docs-guard** | The spec, research, plan, checklist, tasks, analysis, URL map and this record | Applied |
| **clean-code-guard** | The production code | Applied |

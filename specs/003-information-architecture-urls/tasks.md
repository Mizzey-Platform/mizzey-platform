# Tasks: information architecture and URL structure

Feature `003-information-architecture-urls`, PBI #242. Thirty register rows, 28 P1 and two P1-L, all S1.

Ordered so the decisions that gate rows surface first, configuration lands before the code that assumes it, and
evidence comes last. **Every task names its test (M-7).** Production code is three tasks of fourteen, and one of
those three may turn out to be configuration.

## Phase A: settle what can be settled, and surface what cannot

### T-01. Measure whether WPML already covers the sitemap gap

**Kind**: no file change. Measurement.

The sitemap was observed carrying only English URLs. The **cause has not been attributed**: WPML may provide a
sitemap integration that is off, or may not integrate with core sitemaps at all. Measure before writing anything,
because the answer decides whether T-09 is configuration or code.

**Test**: the measurement is the test. Its result is recorded in `research.md` and changes T-09's kind.

**Gate**: the cause is attributed, in writing, with the setting or the absence that explains it.

---

### T-02. Put the four decisions to Mustafa

**Kind**: no file change. Reporting.

**D-242-1** the Home page, **D-242-2** what a Collection is and what the collections are, **D-242-3** the brand
list and whether `product_brand` becomes translatable, **D-242-4** what Tracking shows and where it lives.

**Do not invent any of them.** No category name, collection name, page copy or marketing content is written by this feature; fixture content is used where a record is needed. **None of the four blocks implementation**: the collection model follows ADM-57 and its neighbours (spec C-7), and the tracking route follows ORD-05, SHIP-03 and ADR-0002 (spec C-6).

**Gate**: each input is recorded as supplied, or named as still required, with the row it affects.

---

## Phase B: configuration, which is most of the feature

### T-03. Create the eight Trust and Support page records, both languages

**Kind**: site-tests for the baseline fixture, plus the operational runbook recorded in the spec.

IA-24 About, IA-25 Contact Us, IA-26 Help/FAQ, IA-27 Authenticity Guarantee, IA-28 Shipping Policy, IA-29 Return
and Refund Policy, IA-30 Privacy Policy, IA-31 Terms and Conditions. Two exist as drafts reachable only by
`?page_id=`: `privacy-policy` and `refund_returns`. Publish and reuse those rather than creating duplicates.

**Placeholder body content only.** The copy is content work and arrives with its own slice. Assign the WooCommerce
`terms` page option, which is unset, for IA-31.

**Apply #241's invariant**: set the target language **before** insert, so the Arabic slug is not suffixed and the
URL does not answer 301. Create all English records first, then the Arabic ones.

**Test**: T-11 requests all sixteen URLs and asserts 200, the served language, one translation group per pair with
English as source, and that neither draft remains reachable only by `?page_id=`.

**Gate**: sixteen URLs serve 200, eight translation groups, no suffixed slug.

---

### T-04. Translate the ten endpoint slugs

**Kind**: site-tests for the baseline fixture.

IA-10 `order-received`, IA-16 `lost-password`, IA-18 `edit-address`, IA-19 `orders`, IA-20 `view-order`, and the
remaining account endpoints. All fifteen are already registered in WPML String Translation under context
`WP Endpoints`.

**Create no pages for these rows.** They are endpoints; a page would be a second route to the same screen.

**Test**: T-11 requests each endpoint in both languages and asserts the screen; and asserts no page record exists
whose slug duplicates an endpoint slug in either language.

**Gate**: each account URL resolves in both languages, and the duplicate-route assertion passes.

---

### T-05. Settle the Home page

**Kind**: site-tests for the baseline fixture. **Blocked by D-242-1.**

If a static page: create it in both languages and set `show_on_front` and `page_on_front`. If the posts index
stands, record that and assert the root serves it.

**Test**: T-11 asserts the root serves the decided Home in both languages.

**Gate**: `/` and the Arabic root serve the decided Home, and the decision is recorded.

---

### T-06. Register `product_brand` as translatable

**Kind**: site-tests for the baseline fixture. **Blocked by D-242-3** for the brand list; the translatability
setting is not blocked.

The taxonomy exists in WooCommerce 11.1.0 and is not registered as translatable in WPML. IA-05 is P1 and needs
Arabic.

**Test**: T-11 asserts the taxonomy is translatable and that a brand term's archive URL serves in both languages.
Without the brand list, the test uses a placeholder term created and cleaned up by the scenario.

**Gate**: `is_translated_taxonomy('product_brand')` is true, and a term archive serves in both languages.

---

### T-07. Implement the collection capability

**Kind**: site-code to register the taxonomy, plus site-tests for fixture terms. **Not blocked.**

The model follows the register, per spec C-7: **ADM-57 "Manually curated collections" is P1/S1** while ADM-58 rules-based is P2, so a manually curated taxonomy with per-collection content and manual ordering is the established shape. Register `product_collection` as a public, WPML-translatable product taxonomy, which gives IA-04 and IA-35 their archive route in both languages.

**Scope boundary**: the taxonomy registration only. The admin CRUD (ADM-57), assignment (ADM-44), ordering (MER-03) and per-collection content (SSC-06, SSC-24) belong to other slices and are not built. **Do not invent collection names**: the tests create and clean up clearly non-production fixture terms.

**Test**: T-11 asserts a fixture collection term's archive serves in both languages, and that the taxonomy is registered translatable. Guard: **wp-guard**, **woo-guard**.

**Gate**: a collection archive serves in both languages; the final names remain a recorded content input.

---

## Phase C: the three output items, and only output

### T-08. Emit a canonical link on archive URLs

**Kind**: site-code (`mizzey-site/src/Seo/ArchiveCanonical.php`, new).

Core's `rel_canonical()` begins `if ( ! is_singular() ) { return; }`, measured by reading its source, and `/`,
`/ar/` and `/shop/` emit no canonical. One filter adds a self-referential canonical derived from the resolved
query, never guessed from the request string.

**No admin field, no override, no setting.** ADM-41 and SSC-27 own the override; this emits output only.

**Test**: T-12 fetches archive URLs in both languages and asserts a canonical is present and self-referential,
and that a singular URL still has exactly one. Guard: **wp-guard**, **clean-code-guard**.

**Gate**: every contracted archive URL emits exactly one canonical, in both languages.

---

### T-09. Cover both languages in the sitemap

**Kind**: configuration or site-code, **decided by T-01**.

The gap is measured; the cause is not yet attributed. If WPML provides an integration, this is a setting and no
code is written.

**Test**: T-12 fetches the sitemap and asserts that both the English and the Arabic URL of every contracted page
appear. Guard: **wp-guard** if code.

**Gate**: both languages present for every contracted page.

---

### T-10. Emit a meta description per page

**Kind**: site-code (`mizzey-site/src/Seo/MetaDescription.php`, new).

Core has no meta description field and no SEO plugin is active. One filter emits a description from the page's own
excerpt, with **no cross-page fallback**: a page without one emits none rather than repeating a neighbour's, which
is what AC-242-14 asserts.

**No admin field.** MKT-12 owns the editable title and description.

**Test**: T-12 asserts a description on pages that have one, and asserts a page without one does not inherit
another's. Guard: **wp-guard**, **clean-code-guard**.

**Gate**: descriptions are per page and never shared.

---

## Phase D: evidence

### T-11. Write the information-architecture scenario

**Kind**: site-tests (`mizzey-site/tests/integration/t22-information-architecture.php`, new).

Real HTTP over the whole URL map, following #241's pattern, because a URL cannot be asserted honestly any other
way. Assertions, each naming its criterion:

| Assertion | Criterion |
|---|---|
| Every contracted English URL returns 200 and serves its own record | AC-242-01 |
| Every contracted Arabic URL returns 200, serves Arabic, and is **not** the English record | AC-242-02 |
| Each page pair is one translation group with English as source | AC-242-03 |
| Each account endpoint resolves in both languages | AC-242-04 |
| **No page record duplicates an endpoint slug**, in either language | AC-242-04, G-1 |
| A term URL serves that term's archive, per language | AC-242-05 |
| Order confirmation is reachable only post-checkout and is not publicly listed | AC-242-06 |
| Search results have a stable URL carrying the query, both languages | AC-242-07 |
| **No contracted page is reachable only by `?page_id=`** | AC-242-08 |
| **No contracted page is reachable at two URLs**, and a trailing-slash variant resolves to one form | G-1 |

**Fresh-state safe**: runs from a scripted clean baseline and leaves the runtime clean, cleaning up anything it
creates through `on_finish` rather than `track_post`, which #241 measured to be product-only.

**Test**: the scenario is the test. Guard: **test-guard**.

**Gate**: all assertions pass from a reset runtime, repeatably.

---

### T-12. Write the SEO fundamentals scenario

**Kind**: site-tests (`mizzey-site/tests/integration/t23-seo-fundamentals.php`, new).

NFR-03's eight obligations over served HTTP:

| Assertion | Criterion |
|---|---|
| Clean URLs: human readable, no query-string identity | AC-242-08 |
| Canonical present and self-referential, **including archives** | AC-242-09 |
| Sitemap carries both languages for every contracted page | AC-242-10 |
| `robots.txt` served, and disallows no contracted page | AC-242-11 |
| Breadcrumbs available on the storefront pages that have a hierarchy | AC-242-12 |
| Structured data for product and organisation, **parsing as valid JSON** with the expected types | AC-242-13 |
| A meta description per page, never shared | AC-242-14 |
| Alt text supported and used on the images this feature introduces | AC-242-15 |

**Record, do not assert**: that `wp-sitemap-users-1.xml` is in the sitemap index. A configuration decision under
NFR-03, surfaced rather than silently changed.

**Do not fake browser evidence.** No user-agent string, no header, no headless approximation. **No browser matrix
is invented for #242**: none of these thirty rows obliges browser rendering, unlike NFR-14 in #241.

**Test**: the scenario is the test. Guard: **test-guard**.

**Gate**: all eight obligations asserted from served HTTP, repeatably.

---

### T-13. Write the URL map

**Kind**: feature-spec (`specs/003-information-architecture-urls/url-map.md`).

One row per page type per language: the pattern, an example, and which register row it serves. Evidence for AC-242-05
and AC-242-08, asserted against the runtime by T-11.

**It is not a contracted deliverable** and is not presented as one.

**Test**: T-11 asserts the runtime matches it. Guard: **docs-guard**.

**Gate**: the map matches what the runtime serves, row by row.

---

### T-14. Write the verification record

**Kind**: feature-spec (`verification.md`), plus `evidence/`.

Record what was run and what passed, with the three states separate: workflow complete, technically verified,
contractually accepted. **No criterion of #242 reaches contractually accepted.**

State plainly which rows are **incomplete pending a decision** rather than reporting the feature as fully
verified.

**Test**: review. Guard: **docs-guard**.

**Gate**: every criterion's state is stated, nothing is overstated, and each undelivered row names its decision.

---

## What no task does

No task invents a page name, a category name, a collection name, Arabic copy or SEO text. No task builds an SEO
admin field, a canonical override, a sitemap control or hreflang. No task builds navigation, a language switcher,
a visual design, a layout, a component or a design token. No task touches stock, price, ERP or migration. No task
edits CoreX, `stack.lock.json`, `corex.lock`, another feature's spec, or anything under `app/wp/wp-content`. No
task reopens #241. No task creates an issue, a PBI or a Project change. No task invents a browser matrix.

## Dependency order

```text
T-01 ─> T-09
T-02 records the four content inputs and blocks nothing
T-05, T-06 and T-07 proceed with fixture content

T-03 ─┬─────────────> T-11 ─> T-13 ─> T-14
T-04 ─┘               ▲
T-08, T-09, T-10 ───> T-12 ┘
```

T-01 first, because it decides whether T-09 is configuration or code. T-02 records the content inputs and blocks nothing. Every other task is unblocked.

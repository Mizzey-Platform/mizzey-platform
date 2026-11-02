# Feature Specification: search engine controls

**Feature Branch**: `038-seo-controls`

**Created**: 2026-11-02

**Status**: Draft

**Work type**: requirement

**Input**: Slice E-MKT-2, issue #287 ("Search engine controls: meta and canonical, redirects, missing-page log,
sitemap"). Its predecessor #242 is verified (the SEO output, specs/003). The product SEO fields are specs/036, which
this builds on. Search Console is already verified on mizzey.com's domain. The owner's instruction of 2 November 2026
puts it in the admin set that needs no client input; D-24 rules our own code over a redirect plugin.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| MKT-12 | P1 | S1 | Editable title, description and canonical tag on every product, category and page |
| MKT-13 | P1 | S1 | Redirect manager: permanent redirects created from admin without a developer |
| MKT-14 | P1-L | S1 | Log of missing-page errors, visible in admin |
| MKT-15 | P1 | S1 | XML sitemap and robots file control |
| MKT-16 | P1 | S1 | Structured data: product, organisation, breadcrumbs |
| MKT-17 | P1 | S1 | Google Search Console and Bing Webmaster verification |
| MKT-18 | P1 | S1 | hreflang language targeting tags for English and Arabic |
| MKT-20 | P1-L | S1 | Campaign landing pages, created without a developer |

MKT-13 is marked [key] in the register.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| NFR-03 | P1 | The SEO output (specs/003): clean URLs, canonical, sitemap in both languages, robots, breadcrumbs, structured data, meta description |
| ADM-38 to ADM-41 | P1 | The product SEO fields (specs/036), the same store |
| SSC-11, SSC-27 | P1 | Landing pages from the section library and per-product overrides, from #311's side |
| PDP-24 | P1 | Product structured data, from #254's side |
| MKT-19, IA-34 | OUT, P2 | Blog and its templates: not built |

## Open contract items

- **OD-18.** Verification on the production domain needs it live; the codes are entered here when the client's
  accounts give them (Search Console is already verified by DNS for mizzey.com).
- **D-24, R1.** Redirection (approved on 21 October 2026, never installed) is replaced by our own redirect manager.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | Staff edit the title, description and canonical of any page and category, as of any product, and the page carries them | MKT-12 | provisional |
| AC-2 | Staff create permanent redirects in admin, in either language, and they are followed | MKT-13 | provisional |
| AC-3 | Missing-page errors are logged and visible in admin | MKT-14 | provisional |
| AC-4 | Staff choose what the sitemap lists and add robots file lines; an item kept out of search results is out of the sitemap | MKT-15 | provisional |
| AC-5 | Pages carry product, organisation and breadcrumb structured data and hreflang for English and Arabic; the verification codes are entered in admin | MKT-16, MKT-17, MKT-18 | provisional |
| AC-6 | A campaign landing page is created from blocks without a developer | MKT-20 | provisional |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A renamed page (Priority: High)

1. **Given** a page whose address changed, **When** marketing adds a redirect from the old path, **Then** the old path
   sends visitors and search engines permanently to the new one; **When** a visitor follows a broken link, **Then** the
   missing page appears in the log.

### User Story 2 - The category's search listing (Priority: High)

1. **Given** a category, **When** marketing edits its title and description, **Then** the category page carries them.

### Edge Cases

- A redirect to itself: refused.
- Probes for server files: not logged as missing pages.
- A robots line that is not a directive: not kept.
- A verification code with other characters: cleaned to letters, digits, hyphens and underscores.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Sitemap | WordPress's own sitemaps, both languages (specs/003) | Native; the choice of content added |
| robots.txt | WordPress's virtual robots.txt | Native; extra lines added |
| hreflang | WPML | Native, verified |
| Product and breadcrumb structured data | WooCommerce | Native, verified |
| Organisation structured data | None (specs/003 left it to MKT-16) | Gap: printed on the home page of each language |
| Landing pages | The block editor with WooCommerce's blocks | Native, verified (the section library is #311) |
| Page and category SEO fields | None (no SEO plugin, D-24) | Gap: the same four fields as products |
| Redirects, missing-page log | None (Redirection not installed, D-24) | Gap: our own |
| Verification codes | None | Gap: two settings |

## Requirements

### Functional Requirements

- **FR-001**: Pages (the Shop page included, which WooCommerce serves as the product archive), product categories,
  collections and brands MUST have the SEO title, description, canonical and
  robots fields, per language record, saved with a nonce by staff who may edit them (AC-1).
- **FR-002**: Redirects MUST be 301, matched on the normalised path before WordPress looks for a page, counted, and
  MUST refuse a redirect to the same page and a target that is not a full web address (a bare query or fragment); a
  target given as a path keeps its language prefix (AC-2).
- **FR-003**: Missing pages MUST be logged by path with count, last time and referrer, never the visitor's address,
  at most 500 paths (AC-3).
- **FR-004**: Staff MUST choose which content kinds the sitemap lists; an item marked "do not index" MUST be left out;
  extra robots lines MUST be directives only (AC-4).
- **FR-005**: The Google and Bing verification codes MUST be printed on the home page (AC-5).
- **FR-006**: The screen MUST need `manage_woocommerce` and its nonce for every change.
- **FR-007**: The home page of each language MUST carry the store's organisation structured data: name, address
  and, once the brand identity gives one, the logo (AC-5).
- **FR-008**: The title, canonical and robots values MUST be checked when printed, whatever route stored them: the
  title escaped, the canonical a full web address, the robots value one of the four choices (AC-1, AC-4).

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- The blog (MKT-19, OUT); site search indexing (IA-34, P2); regular-expression redirects.

## Success Criteria

- **SC-001**: On staging, a redirect is created and followed, a missing page is logged, and a category's edited title
  is on its page, in both languages.

## Delivery stage

Contractual stage: S1. Engineering timing: November 2026 (D-22, D-27).

## Assumptions

- Known limit: "do not index" is set per language record. If every English record of one content kind were hidden
  while its Arabic translations were not, WordPress would count no sitemap page for that kind and the index would not
  list it. Not expected in practice (the fields are set item by item); recorded from the review.

- Marketing work is done by staff who manage the store (the launch roles, specs/013); the Marketing profile is DEF.

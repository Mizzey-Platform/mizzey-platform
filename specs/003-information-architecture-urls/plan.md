# Implementation Plan: information architecture and URL structure

**Branch**: `003-information-architecture-urls` | **Date**: 4 October 2026 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/003-information-architecture-urls/spec.md`, PBI #242.

## Summary

Thirty rows: twenty-nine named pages that must exist and be reachable in both languages, and NFR-03's eight SEO
fundamentals. The audit in [research.md](research.md) establishes the shape, and it is not the shape the PBI title
suggests.

| Work | Kind | Rows |
|---|---|---|
| Eight Trust and Support page records, both languages | Platform configuration and placeholder content | IA-24 to IA-31 |
| A Home page record and the front-page setting | Configuration, after D-242-1 | IA-01 |
| Arabic slugs for ten endpoints | **String translation configuration.** No pages, no code | IA-10, IA-14 to IA-20 |
| `product_brand` registered translatable | WPML configuration, after D-242-3 | IA-05 |
| A canonical link on archive URLs | **Production code**, small. Core emits none | NFR-03 |
| Both languages in the sitemap | **Production code or configuration**, to be settled in Phase 1 | NFR-03 |
| A meta description mechanism | **Production code**, small | NFR-03 |
| The URL map, written down | Evidence | IA-02 to IA-07, NFR-03 |
| A served-request scenario over every contracted URL | Test | AC-242-01 to G-1 |

**All thirty rows are implementable now.** Four carry an open **content or presentation input**, not an architectural decision: IA-01 the Home choice, IA-04 and IA-35 the collection names and copy, IA-05 the brand list, IA-21 the tracking presentation. Each row's capability, route and bilingual behaviour is built with fixture content, and the inputs are named rather than invented.

**Nothing from #241 is rebuilt.** English at the root, Arabic under `/ar/`, direction from the locale, one
template set and translation identity are a satisfied dependency.

## Technical Context

**Language/Version**: PHP 8.3.6. Python 3.10 for any check, matching `tools/tests/`.

**Primary Dependencies**: WordPress 7.1.2, WooCommerce 11.1.0, WPML 4.9.7, WPML String Translation 3.5.4,
WCML 5.5.7, CoreX 0.42.0. As recorded in `stack.lock.json`, which this plan was verified against (M-8).

**Storage**: page records and term records that WordPress and WooCommerce already define. No new table, no new
post type. One new taxonomy only if D-242-2 decides a collection is a taxonomy.

**Testing**: the existing integration harness on a scripted clean baseline, extended with **real HTTP assertions
over the whole URL map**, which is the pattern #241 established and the only honest way to assert a URL.

**Target Platform**: WordPress on Apache. Production hosting undecided: **OD-27** is open and nothing here
assumes a host or a domain.

**Project Type**: WordPress client site. CoreX provides nothing for this feature.

**Performance Goals**: none stated by the thirty rows, and none invented. NFR-02 is a separate row owned by
E-NFR-1.

**Constraints**: CoreX unmodified. **No visual design.** No admin control over SEO output. No navigation. No
content or names invented.

**Scale/Scope**: 29 pages across two languages, 8 SEO obligations, 16 criteria, 4 recorded decisions.

## Constitution Check

*GATE: passed before Phase 0. Re-checked after Phase 1 design, below.*

- [x] **M-1 Scope**: all thirty ids are obligation rows, 28 P1 and two P1-L. Each criterion is supported by the
      wording of the row it cites, and the hardest test was NFR-03: its eight words were decomposed into AC-242-08 to
      G-1 rather than becoming one vague "URL structure" criterion, and the admin controls its neighbours own
      were explicitly excluded.
- [x] **M-2 Open items**: no `open-items.json` entry touches these ids. Four feature decisions are **recorded in
      the spec, not resolved**, and OD-27 is cited as bearing on nothing here.
- [x] **M-3 P1-E**: no P1-E row is in scope. Nothing is ERP dependent.
- [x] **M-4 Native first**: five of NFR-03's eight obligations and most URL mechanics are native and left alone.
      hreflang is native **and** owned elsewhere, so it is doubly not built. The three code items are each
      justified by a measured gap, two of them by reading WordPress core's own source.
- [x] **M-5 Three lists**: sixteen contractual criteria; two optional safeguards needing approval; eight future
      or Option C items recorded and not built.
- [x] **M-6 Stage**: S1 for all thirty, unchanged. Running second is delivery order.
- [x] **M-7 Tests**: every task below names its test. No money, stock, permission, refund, payment or
      shipment-state path is touched, so none needs that class of coverage.
- [x] **M-8 Versions**: the versions above, from `stack.lock.json`.
- [x] **CoreX**: nothing under `app/corex` is edited. It was inspected only where #242 could plausibly depend on
      it, and provides nothing.
- [x] **Guard Gate**: **wp-guard** on the production code, **woo-guard** on anything touching WooCommerce pages
      or endpoints, **test-guard** on the scenario, **docs-guard** on these records, **clean-code-guard** on the
      production code.

## Phase 0: what the audit settled

Full working in [research.md](research.md). The four findings that shaped this plan:

1. **The page rows oblige existence, not a URL taxonomy.** SRS section 5 is a page inventory with no URL content. #242 owns the runtime IA, URL and SEO **output** assigned to its registered rows; separately registered SEO and admin-control rows keep their own ownership.
2. **Eight rows are endpoints, and their Arabic slugs are already translatable.** Creating pages for them would
   produce duplicate routes, which G-1 forbids.
3. **NFR-03 is five-eighths native.** The gaps are canonical on archives (core returns early unless
   `is_singular()`), sitemap language coverage, and meta description.
4. **CoreX has no part in this.** No template, routing, URL, canonical, SEO or sitemap layer.

## Phase 1: design

### Where the three code items sit, and how small they are

All three are output, not controls, which is what keeps them inside NFR-03 and out of MKT-12, MKT-15 and ADM-41.

| Item | Approach | Why not configuration |
|---|---|---|
| **Canonical on archives** | One filter adding a self-referential canonical where core emits none, derived from the resolved query rather than guessed | Core's `rel_canonical` returns early unless `is_singular()`. No setting changes that |
| **Sitemap in both languages** | **To be settled by measurement in the first task**: WPML may already provide a sitemap integration, in which case this is configuration and no code is written. Only if it does not does a provider or filter get added | Measure before building. The gap is observed; the cause is not yet attributed |
| **Meta description** | One filter emitting a description per page, sourced from the page's own excerpt with no cross-page fallback | Core has no such field and no SEO plugin is active |

**The boundary that keeps these honest**: each emits output from data that already exists. **None adds an admin
field, an override, or a settings screen.** The moment one would, it has crossed into MKT-12, ADM-41 or SSC-27.

### Page creation follows #241's measured invariant

Every page record created here sets the target language **before** `wp_insert_post`. #241 measured the
alternative: inserting first and assigning the language afterwards gets the slug suffixed and the URL answers 301.
Sources first, translations second, as A11 established.

### What the URL map is, and is not

A document, per page type and per language, produced as evidence for AC-242-05 and AC-242-08 and asserted against the
runtime by the scenario. **It is not a contracted deliverable** and is not presented as one: no row obliges it.

### Structure Decision

Real paths, each inside a path class a `requirement` pull request may touch:

```text
specs/003-information-architecture-urls/        feature-spec
├── spec.md, research.md, plan.md
├── checklists/requirements.md
├── tasks.md, analysis.md
├── url-map.md                                  the documented map, as evidence
└── verification.md                             written at the verify step
└── evidence/                                   the committed suite output

mizzey-site/                                    site-code
└── src/Seo/                                    new: the three output filters, one small class each
    ├── ArchiveCanonical.php
    ├── MetaDescription.php
    └── SitemapLanguages.php                    only if measurement shows WPML does not cover it

mizzey-site/tests/integration/                  site-tests
├── t22-information-architecture.php            new: every contracted URL, both languages
├── t23-seo-fundamentals.php                    new: NFR-03's eight obligations over served HTTP
└── baseline/setup.php                          gains the page records and the endpoint translations
```

**Not touched**: `app/corex` and anything under it, `app/wp/wp-content`, `stack.lock.json`, `corex.lock`,
`docs/scope/`, the CI workflow, `mizzey-theme/` beyond nothing, and every other feature's spec.

**No new checker is planned.** The existing one-owner rule, scope trace, house rules, spec-consistency check,
path policy and guards cover this feature. S-1 and S-2 are test assertions inside the new scenarios, not new
machinery. A checker would be added only if #242 introduced a new class of factual claim that drifts across
artifacts, and it does not: its claims are URL behaviours, which the scenarios assert directly.

### Re-check of the Constitution gate after design

All ten still hold. The design added no register id, no criterion and no behaviour beyond the thirty rows; it
kept all three code items on the output side of the NFR-03 boundary; and it defers one of them pending a
measurement rather than assuming code is needed.

## Phase 2: tasks

In [tasks.md](tasks.md). Ordered so the decisions that gate rows are surfaced first, the configuration lands
before the code that assumes it, and the evidence comes last.

## Complexity Tracking

No constitution violation to justify. Four deliberate decisions not to build:

| Considered | Rejected because |
|---|---|
| Pages for the account rows, to satisfy "the page exists" literally | They are WooCommerce endpoints. Pages would create a second route to each screen, which G-1 forbids and which no row asks for |
| An SEO plugin, to cover meta, canonical and sitemap in one step | It would deliver MKT-12, MKT-15, MKT-16, ADM-41 and SSC-27's admin surfaces along with the output, taking four other slices' rows. A plugin may be the right answer **when those slices run**; it is not this slice's call |
| hreflang output | WPML already emits it and MKT-18 owns it. Nothing to build and not ours to build |
| A redirect map and trailing-slash policy as deliverables | Neither appears in SRS §5 or NFR-03. G-1 asserts no duplicate URL exists, which is the obligation; a policy document is not |

# Feature Specification: images and locale formatting

**Feature Branch**: `032-image-handling`

**Created**: 2026-11-02

**Status**: Draft

**Work type**: requirement

**Input**: Slice E-NFR-1, issue #269 ("Cross-cutting standards met"), the part that needs no design and no production
host: what happens to an image whatever staff upload (the notes on #269 of 24 October 2026), and NFR-04b's number,
price and date formatting. The real Amazon product images on mizzey.com (aspect 0.29 to 2.27, phone-sized files) make
the image part urgent. The other rows of #269 keep their own evidence and are not changed here.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| NFR-02 | P1 | S1 | Performance: Image optimisation, lazy loading, caching and CDN |
| NFR-04b | P1 | S1 | Localisation: Currency, number and date formatting per locale |

This slice covers NFR-02's image optimisation and lazy loading. Its caching and CDN depend on the production host
(OD-27, OD-14) and stay open; the performance measurement (D-21 section 1) is taken on staging with the designed
pages.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| NFR-05 | P1 | The upload allow-list and limits of D-21's security baseline (specs/007) |
| MIG rows | P1-L | The catalogue migration (#296) stores images through the same sideload, so the guard and WebP apply to it |
| D-20 A9 | | CoreX WebP copies are used only if their serving can be shown; otherwise their generation stays off |
| D-20 B2 | | Western digits 0 to 9 everywhere, with locale formatting |

## Open contract items

- **OD-27.** WebP needs the server's image library to write it: verified on the local runtime and staging; to be
  checked on the production host once it is chosen.
- **Server upload limit.** PHP's own limit must be at least the guard's 20 MB on staging and production: a hosting
  setting, recorded with the staging evidence.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | An image too heavy to serve (over 20 MB or 40 megapixels) is refused with a message saying why, on every upload route | NFR-02 | provisional |
| AC-2 | Every size made of an uploaded JPEG or PNG is an optimised WebP; the full size is scaled to 2,560 px and the original kept | NFR-02 | provisional |
| AC-3 | Product images are served as WebP and lazy loaded below the first images | NFR-02 | provisional |
| AC-4 | Prices, numbers and dates are formatted per locale, with Western digits on Arabic pages; the currency is written per language (English `EGP` before the amount, Arabic the amount, a space, then the Arabic symbol) | NFR-04b | provisional |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A phone photo as a product image (Priority: High)

1. **Given** a 12-megapixel phone photo, **When** staff add it to a product, **Then** the shop shows WebP sizes of it,
   lazy loaded, and the original is kept.
2. **Given** a 60 MB or 50-megapixel export, **When** staff upload it, **Then** it is refused with a message saying
   how big it may be.

### Edge Cases

- A transparent PNG: its WebP sizes keep the transparency.
- A server whose image library cannot write WebP: sizes stay JPEG or PNG; nothing breaks.
- A file that is not an image: left to WordPress's own type checks.
- The administration is English (ADM-159): staff see and save the stored currency position and symbol; only Arabic
  output is written the Arabic way.
- The Arabic symbol is the working default the reviewed Arabic wireframes draw; the localisation review may change
  the wording through the translation file, with no code change.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| WebP sizes | WordPress's `image_editor_output_format` | Native, configured |
| Scaling a big image, keeping the original | WordPress's big-image threshold (2,560 px) | Native, default kept |
| Lazy loading | WordPress's `loading="lazy"` | Native, verified |
| CoreX WebP copies | Served only through CoreX's picture helper, which WooCommerce's images do not use | Not used: generation stays off (D-20 A9) |
| Refusing heavy images with a reason | Only PHP's upload limit, with no useful message | Gap: an upload guard |
| Number and date formatting | WordPress locale formatting | Native, verified |
| Currency symbol and position per language | None: WooCommerce keeps one symbol and one position for every language, so the Arabic shop printed `EGP91.00` like the English one (audit of 2 November 2026). WooCommerce's own locale data for the pound in Arabic is the amount, a space, then the symbol (`i18n/locale-info.php`, EG) | Gap: `MizzeySite\I18n\CurrencyFormat` sets the position option and the symbol (a translation) on Arabic pages, emails and invoices |

## Requirements

### Functional Requirements

- **FR-001**: An image over 20 MB or 40 megapixels MUST be refused before it is stored, through the media library, a
  product, the REST API and a sideload, with a message giving the limit (AC-1).
- **FR-002**: Every size made of a JPEG or a PNG MUST be written as WebP where the server can write it (AC-2).
- **FR-003**: WordPress's big-image threshold MUST stay at 2,560 px and the original MUST be kept (AC-2).
- **FR-004**: One mechanism MUST produce WebP: WordPress's, with CoreX Media's generation off (AC-2; ADR-0003).
- **FR-005**: Prices, numbers and dates on Arabic pages MUST use Western digits with Arabic formatting (AC-4).
- **FR-006**: On Arabic pages, and in an Arabic order's email or invoice, a price MUST be written as the amount, a space,
  then the Arabic symbol of the pound; English keeps the store's setting. The cart and checkout blocks and the Store API
  MUST write it the same way as the page (AC-4).

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- AVIF output, a smaller set of registered sizes and per-template byte budgets: after the approved design (D-21).
- Caching and the CDN: with the production host (OD-27).

## Success Criteria

- **SC-001**: On staging, a large phone photo and a large PNG are uploaded and the stored sizes recorded with the
  NFR-02 evidence.

## Delivery stage

Contractual stage: S1. Engineering timing: November 2026 (D-22, D-27).

## Assumptions

- 20 MB and 40 megapixels are working defaults (D-10): above any phone or catalogue photo, below what the server's
  image library can decode in its memory.
- Keeping originals is the reversible choice; removing them can be decided later.

# Feature Specification: the initial catalogue load

**Feature Branch**: `046-catalogue-load`

**Created**: 2026-11-02

**Status**: Draft

**Work type**: requirement

**Input**: Issues #247 ("The catalogue migration does not corrupt Arabic product or variation content"), #295
(attributes, categories, brands and SEO fields), #296 (images with alt text) and #297 (the background run and the
export). They build on the import specification (#249, specs/006): its mapping, reader and validation pass decide
what is written; this writes it. The owner's instruction of 2 November 2026 puts the migration track in the work
built with a stand-in: the client's real export (CR-06) and the image rights (CR-08) come later, so the load is built
and tested on a fixture of the same shape, and on the 49 real products locally.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| MIG-02 | P1-L | S1 | Arabic content integrity: the Developer verifies and, where necessary, normalises character encoding for the agreed initial catalogue import so Arabic content is not corrupted |
| MIG-09 | P1-L | S1 | Product variations present in the agreed source data are converted correctly into variable products during the initial Developer-performed migration |
| MIG-10 | P1-L | S1 | Source attributes are mapped to store attributes during the initial migration |
| MIG-11 | P1-L | S1 | Source categories are mapped to the store category structure during the initial migration |
| MIG-12 | P1-L | S1 | Brands are mapped during the initial migration |
| MIG-13 | P1-L | S1 | Price, sale price and cost are migrated where present in the agreed source |
| MIG-15 | P1-L | S1 | SEO fields are migrated where present in the agreed source |
| MIG-16 | P1-L | S1 | Product images downloaded from the source references supplied and stored on the Mizzey server: subject to the rights confirmation at CR-08 |
| MIG-17 | P1-L | S1 | Alt text generated from the product title where none is supplied in the source |
| MIG-20 | P1-L | S1 | The initial migration is performed as a background operation and does not interrupt the store |
| MIG-25 | P1-L | S1 | The catalogue remains exportable using the standard supported commerce export format |
| SSC-21 | P1 | S1 | Product content maintained separately in English and Arabic |

SSC-21 is marked [key] in the register.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| MIG-01, MIG-03, MIG-04, MIG-06, MIG-19 | P1-L | The import specification this load executes (specs/006) |
| MIG-05, MIG-07, MIG-08, MIG-18 | DEF | Saved profiles, scheduled and repeat imports: not built |
| MIG-21, MIG-22 | P2 | Not built |
| ERP-07 | P1-E | Stock is the ERP's: the load writes no stock (PRE-09) |

## Open contract items

- **CR-06.** The client's real, unmodified Amazon export. The mapping's headers follow it when it arrives; the
  acceptance evidence is a run over that file.
- **CR-08.** The rights to the product images. Images are downloaded only when the run says the rights are confirmed.
- **CX-04** (D-12): each run is kept as an Import Run record (ENT-19, owned by #273, specs/040). The record is written;
  its acceptance stays with specs/040's AC-3.
- **CR-06, SEO fields in Arabic.** The mapping has no Arabic SEO fields: the SEO title and description are written to
  the English record only, and the Arabic record keeps its own (empty until staff fill them). Whether the agreed source
  carries Arabic SEO text is known only from the real export; if it does, the mapping gains the fields then.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | The load writes what the validation pass decided, in the background, without interrupting the store; rejected rows are not written | MIG-20 | provisional |
| AC-2 | Each product carries its name, description, price, sale price and cost, its category path and brand, and its SEO fields where the source has them | MIG-11, MIG-12, MIG-13, MIG-15 | provisional |
| AC-3 | Variations in the source become variable products with their attributes | MIG-09, MIG-10 | provisional |
| AC-4 | Each Arabic record is written as its own content, valid and normalised, in the product's translation group, and the Arabic variations keep their attributes; on every run, the first and an update, the Arabic record carries the item's price, sale price and other copied fields, and a variation added to a translated product gets its Arabic variation | MIG-02, MIG-09, MIG-13, SSC-21 | provisional |
| AC-5 | Images are downloaded and stored on the store's server once rights are confirmed, with the title as alt text where the source gives none | MIG-16, MIG-17 | provisional |
| AC-6 | The loaded catalogue exports in WooCommerce's standard CSV format | MIG-25 | provisional |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The live load (Priority: High)

1. **Given** the client's export validated with no blocking issue, **When** the developer runs
   `wp mizzey catalogue load <file> --out=<dir>`, **Then** the English records are written in the background, then
   the Arabic ones, then the images if `--images-rights-confirmed` was given; `wp mizzey catalogue status` shows where
   it stands and every row that failed, with the reason.

### Edge Cases

- A second load while one runs: refused.
- Arabic text that is not valid UTF-8: the row fails with the reason; nothing is guessed.
- A byte order mark at the start of a cell: dropped.
- The same image address on two products: stored once.
- A row with no Arabic text: no Arabic record.
- An update file that changes only a price (blank cells elsewhere): the English and the Arabic record both carry the
  new price, and an Arabic order is charged it.
- An update file that adds a size to a product already in both languages: the Arabic product gets the Arabic
  variation, at its price, and the new value in its attribute list, in the English wording until it is translated.
- A category or brand that has no Arabic name: the Arabic record shows none; the run lists a warning for it, for the
  translation to be added.
- An Arabic name with `&`: it is stored as WordPress stores titles and reads back as written.
- An image address that is not a public web address, and every address after the tenth: not downloaded, reported.
  The validation pass reports them first, as client issues.
- Cost in the mapping while WooCommerce's cost of goods is off: the load refuses to start, so no cost is lost.
- A run directory under WordPress, the uploads folder, the site's folder when WordPress sits in a subfolder of it (the
  site address ends in that subfolder, as `/wp` does), or the request's document root, or one that holds an earlier
  run's plan, run record copy or reports (a validation's included): refused, and nothing is created or overwritten
  there. Under WP-CLI the document root is WordPress's own folder, so a web root
  set higher than the site address shows is not known to the load: the runbook keeps run directories outside the web
  server's folders (a home or temporary folder).
- A web runner rather than WP-CLI: an images action takes at most five products and hands over once half the PHP time
  limit is spent; it queues a watchdog first, which resumes from the saved progress if the action dies at the limit,
  and a product tried twice without finishing is given up and reported. One worker per run works at a time, under a
  lock its database connection holds, so a watchdog that fires while a slow worker is alive does nothing but wait
  again. The load is run with `wp action-scheduler run` where the host allows it.
- A run that stopped moving for half an hour and is replaced by a new one: its Import Run record is finalised as
  superseded, with its finish time and what it had written.
- An update that renames a product and moves it to a translated category: the Arabic record gets the Arabic category.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Products, variations, attributes, categories, brands, cost | WooCommerce's product classes and taxonomies, its cost of goods | Native, used |
| The Arabic record | WooCommerce Multilingual's translation editor | Native, used in its own phase |
| The Arabic record following an update | WPML's copy of the copied fields and WooCommerce Multilingual's product synchronisation, which run only from save_post | Partial: a CRUD save skips save_post (t20, t18); `TranslationSync` runs WPML's and WCML's own synchronisation after it |
| Background work | Action Scheduler | Native, used |
| Media | WordPress's `media_sideload_image` | Native, used |
| Export | WooCommerce's CSV exporter | Native, verified |
| Sequencing the phases, encoding checks, the plan | None | Gap: `CatalogueLoad` |

## Requirements

### Functional Requirements

- **FR-001**: The load MUST plan only the rows the validation pass creates or updates, sources before variations, and
  write nothing before it runs (AC-1).
- **FR-002**: The English records MUST all be written before any Arabic record; the Arabic phase MUST create no
  product, and runs in later scheduled actions (#247, A11) (AC-4).
- **FR-003**: Text MUST be valid UTF-8, NFC, with no byte order mark; the stored Arabic title MUST read back as written
  (AC-4).
- **FR-004**: Categories MUST be created along their path where missing; the brand MUST be the brand taxonomy's term;
  attributes MUST be the variable product's, with every value its variations use (AC-2, AC-3).
- **FR-005**: Images MUST be downloaded only with the rights confirmed, each address once, the first as the product
  image and the rest its gallery, alt text the title where none is given (AC-5).
- **FR-006**: The run MUST work in batches of 20 in Action Scheduler (images five products an action) and record its
  state and every failed row (AC-1).
- **FR-007**: After an English record is written, its translations MUST carry its copied fields (price, sale price and
  its dates, cost, SKU, weight and dimensions), and the Arabic phase MUST run WooCommerce Multilingual's
  synchronisation for every product the run touched, the parent of a new variation included, so the Arabic
  variation exists and its value is in the Arabic attribute list (AC-4).
- **FR-008**: A category or brand assigned to a product with an Arabic record, and with no Arabic translation, MUST be
  listed as a warning in the run (AC-2).
- **FR-009**: An image address that is refused, or beyond the tenth, MUST be reported for its row (AC-5).
- **FR-010**: The run MUST write the validation report and the client issue list into its own run directory, and keep
  an Import Run record (file and its hash, mapping and rules version, actor, times, counts, outcome, problems and
  warnings) that a later run does not overwrite (AC-1, CX-04).

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- Saved import profiles, scheduled and repeat imports (DEF); stock from the file (P1-E, ERP).

## Success Criteria

- **SC-001**: The 49 real products load locally with no failed row, their images whole and named; on staging the
  fixture load completes with the store answering throughout.

## Delivery stage

Contractual stage: S1. Engineering timing: November 2026 (D-22).

## Assumptions

- Arabic attribute values are those of the source: WooCommerce Multilingual carries them to the Arabic variations.
- Attributes are product-level (local) attributes. Whether the migrated sizes and shades become global attributes, so
  the listing filters of PLP-09 (#303) can use them, is decided with #303; until then the filter cannot use them.
- The image alt text is the English title, on one attachment shared by both language records (MIG-17 does not say
  per language).

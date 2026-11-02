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

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | The load writes what the validation pass decided, in the background, without interrupting the store; rejected rows are not written | MIG-20 | pending CR-06 |
| AC-2 | Each product carries its name, description, price, sale price and cost, its category path and brand, and its SEO fields where the source has them | MIG-11, MIG-12, MIG-13, MIG-15 | pending CR-06 |
| AC-3 | Variations in the source become variable products with their attributes | MIG-09, MIG-10 | pending CR-06 |
| AC-4 | Each Arabic record is written as its own content, valid and normalised, in the product's translation group, and the Arabic variations keep their attributes | MIG-02, SSC-21 | pending CR-06 |
| AC-5 | Images are downloaded and stored on the store's server once rights are confirmed, with the title as alt text where the source gives none | MIG-16, MIG-17 | pending CR-08 |
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

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Products, variations, attributes, categories, brands, cost | WooCommerce's product classes and taxonomies, its cost of goods | Native, used |
| The Arabic record | WooCommerce Multilingual's translation editor | Native, used in its own phase |
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
- **FR-006**: The run MUST work in batches of 20 in Action Scheduler and record its state and every failed row (AC-1).

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

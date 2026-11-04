# Implementation Plan: the importer reads Mizzey's product sheet

**Branch**: `051-product-sheet-import` | **Date**: 2026-11-04 | **Spec**: [spec.md](spec.md)

## Summary

The plugin's mapping (`mizzey-site/import/catalogue-mapping.json`) becomes the product sheet's. A mapping may name
header prefixes: `option_` fills `attribute_`, so each option column of a file is added as a column of its own when
the file is read (`Mapping::forHeader()`). A prefixed column that cannot be an attribute stops the run (F4). The
illustrative fixtures keep their own mapping.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0 (`stack.lock.json`) |
| Code | `Catalogue/Import/Mapping.php`, `HeaderCheck.php`, `CatalogueLoad.php`, `Cli/CatalogueCommand.php`; `import/catalogue-mapping.json` |
| Testing | `t91-product-sheet` (new); t30 to t38 and t86 unchanged in what they assert, on the fixtures' mapping |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-1 | Every criterion is in the wording of MIG-01, MIG-04, MIG-09 or MIG-10; nothing reusable or self-service is added |
| M-4 | WooCommerce's custom attributes hold the options; the existing importer reads the file |
| M-7 | Each criterion has a t91 check; deliberate defects are caught (`evidence/mutations.txt`) |

## Design

| Piece | What it does |
|---|---|
| `Mapping::prefixes()`, `prefixedField()` | `option_pack_size` fills `attribute_pack-size`; a name outside a to z, digits, `_` and `-` fills nothing |
| `Mapping::forHeader()` | The mapping with each prefixed header of the file added, the first of two that name one option only |
| `HeaderCheck` F4 | A prefixed header left unmapped stops the run, named, with the correction |
| Callers | The preview, the validation and the load expand the mapping from the file's header before the header check |
| `CatalogueLoad::parentAttributes` | The option's name with spaces for `-` ("Pack size"); its key unchanged |
| Fixtures | `fixtures/catalogue/mapping.json` (the former plugin mapping) for t30 to t38; `product-sheet.csv`, the sample as sent |

## Criterion to test map

| AC | `t91-product-sheet` asserts |
|---|---|
| AC-1 | The header check is complete with no failure or warning; the CLI validation plans the eight rows, no rejection |
| AC-2 | The sample's size, colour and capacity from its option columns; scent and pack size added to a row, read without a mapping change |
| AC-3 | `option_`, `option_اللون`, `option_Size` beside `option_size`: F4, named; the CLI refuses such a sheet and names it |
| AC-4 | The load: the simple product's fields, category path, brand, SEO, ASIN; the shirt's three variations by size and colour with their prices and cost; the bottle by capacity only; the Arabic records |

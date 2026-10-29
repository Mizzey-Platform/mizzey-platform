# Catalogue import fixtures

Invented data for the catalogue import scenarios (t30 to t38, `specs/006-catalogue-import-spec`). Nothing here is the
client's.

The headers are **illustrative**: modelled on an Amazon seller listings report (tab-delimited, UTF-8 with a byte-order
mark), plus the columns a variation load needs (`parent-child`, `parent-sku`, the attribute columns) and Arabic text.
The real sample export (CR-06) replaces them; only `mizzey-site/import/catalogue-mapping.json` changes when it does.

| File | What it is for |
|---|---|
| `export.tsv` | The main fixture: one row per rule of `contracts/initial-load-rules.md`, across Beauty, Health and household, Sport and Vitamins. Several rows refer to store records that `catalogue_seed_export_store()` in `_catalogue.php` creates |
| `header-mismatch.tsv` | The `price` column missing (F1) |
| `duplicate-headers.tsv` | `item-name` twice (F2) |
| `extra-column.tsv` | A column the mapping does not list (W2) |
| `empty.tsv` | No header at all (F3) |
| `header-only.tsv` | A header and no rows |

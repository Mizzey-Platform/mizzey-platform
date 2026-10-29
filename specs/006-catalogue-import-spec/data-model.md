# Data model: catalogue import specification and validation pass

Nothing here is a new database table. The tool reads the catalogue and writes files.

## Import specification (`mizzey-site/import/catalogue-mapping.json`)

| Field | Meaning |
|---|---|
| `version` | Required mapping version; every run reports it. Mapping validation checks the supported format, the delimiter and encoding (or workbook-sheet configuration, once a workbook format is agreed), unique source headers, explicit targets or ignore reasons, row-type interpretation and a compatible rules version |
| `rules_version` | The version of `contracts/initial-load-rules.md` the mapping assumes |
| `source` | `format` (`tsv`, or `csv` where needed; another format only once agreed), `delimiter`, `encoding` (default `UTF-8`) |
| `columns` | One entry per source header: `to` (a store field key, as the native importer names it) or `ignore` with a `reason` |
| `row_type` | How the row type is read: the column and its values for simple, variable and variation rows |
| `language` | Which columns carry Arabic text and the store field each fills in the Arabic translation |

## Interpreted row (in memory)

`row_number`, `row_type`, `sku` (the source value, unchanged), the mapped store fields, the Arabic fields, `parent_sku`
for a variation.

## SKU resolution (in memory)

For one SKU: the matched ids, and for each `{id, kind: product|variation, language, is_original}`; `near_matches` for a
SKU that matches only after trimming or case folding.

## Validation report line (`validation-report.csv`)

| Column | Content |
|---|---|
| `row` | The data row number, the header being row 1 |
| `sku` | The source SKU as written |
| `type` | simple, variable or variation |
| `outcome` | create, update or reject |
| `rule` | The rule code from `initial-load-rules.md` |
| `reason` | The plain English reason |
| `resolved_id` | The product or variation id the SKU resolves to, or empty |
| `language_role` | `original`, `translation` or empty |
| `translations` | Ids of the original's translations, when it has any |
| `issues` | Issue codes on this row, whatever its outcome; near-match issues include candidate ids, stored SKU values and the comparison rule |

## Client issue (`client-issues.csv`)

`row`, `sku`, `field`, `problem`, `correction` (what to change), `issue_code`. Only issues whose owner is `client`.

## Run (`run.json`, the CX-04 payload)

`file` (source file reference), `mapping_used` (reference identifying the versioned mapping), `mapping_version`, `rules_version`, `counts` (rows, create, update, reject, client issues,
developer issues), `outcome` (`completed` or `file-level-failure` with its reason), `actor` (the WordPress user or the
system user running WP-CLI), `started_at`, `finished_at`, `reports` (the paths) or the error reference, `recorder: fixture-adapter` (a fixture record, not database persistence).

# Data model: catalogue import specification and validation pass

Nothing here is a new database table. The tool reads the catalogue and writes files.

## Import specification (`mizzey-site/import/catalogue-mapping.json`)

| Field | Meaning |
|---|---|
| `version` | Required mapping version; every run reports it. Mapping validation checks the supported format, the delimiter and encoding (or workbook-sheet configuration, once a workbook format is agreed), unique source headers, explicit targets or ignore reasons, row-type interpretation and a compatible rules version |
| `rules_version` | The version of `contracts/initial-load-rules.md` the mapping assumes |
| `source` | `format` (`tsv`, or `csv` where needed; another format only once agreed) and `encoding` (default `UTF-8`); the format sets the delimiter |
| `columns` | One entry per source header: `to` (a store field: `sku`, `name`, `description`, `regular_price`, `parent_sku`, `row_type`, `name_ar`, `description_ar`, `source_asin`, or `attribute_<slug>`) or `ignore` with the reason as its value. No field is filled twice |
| `row_type` | `values`: each source value of the `row_type` column and the row type it means (simple, variable, variation) |
| `note` | Optional: free text for the Developer |

Arabic text is carried by the columns mapped to `name_ar` and `description_ar`.

## Interpreted row (in memory)

`row`, `type` (or the unknown source value), `sku_source` and `parent_sku_source` (unchanged), their trimmed forms, the
mapped fields, the attribute values, the price, and the issues found while reading.

## SKU resolution (in memory)

For one SKU: a status (`none`, `original`, `ambiguous`, `missing_original`, `identity`), every candidate as `{id, stored_sku,
kind, comparison: exact|collation, language, role}`, and for an original its id, kind, product type and translations.

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
| `issues` | Issue codes on this row, whatever its outcome |
| `near_matches` | For each candidate matched other than byte for byte: `id=stored SKU (collation)` |

## Client issue (`client-issues.csv`)

`row`, `sku`, `field`, `problem`, `correction` (what to change), `issue_code`. Only issues whose owner is `client`.

## Run (`run.json`, the CX-04 payload)

`file` (`name`, `reference`), `mapping_used` (the mapping's path, relative to the plugin), `mapping_version`,
`rules_version`, `header` (the header check), `counts` (rows, create, update, reject, client issues, developer issues;
`null` when no row was interpreted), `outcome` (`completed`, `file-level-failure`, `guard-refused` or `output-failure`),
`reason`, `error_reference` (`null` when completed), `actor` (the WordPress user, or `wp-cli`), `started_at`,
`finished_at` (UTC), `reports` (the paths written), `recorder: fixture-adapter` and `persisted: false` (a fixture record,
not database persistence).

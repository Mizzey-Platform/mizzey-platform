# Research: catalogue import specification and validation pass

Evidence read on 29 October 2026 against WooCommerce 11.1.0 on the development runtime (`../app/wp`), WPML 4.9.7,
WCML 5.5.7, WordPress 7.1.4, CoreX v0.47.0. Line numbers are in
`wp-content/plugins/woocommerce/includes/import/class-wc-product-csv-importer.php` unless stated.

## R-1. The agreed format, and the fixture until CR-06

**Decision:** the mapping is header-driven, and the fixture is a tab-delimited text file in the layout of an Amazon
seller listings report, with invented rows. **Rationale:** Amazon's seller reports download as tab-delimited text by
default, and the register leaves the exact report to CR-07. Mapping by header name, never by column position, means the
real file changes only the mapping data, not the code. **Alternatives:** a CSV fixture (rejected: not Amazon's default
report format); hard-coding Amazon's column names (rejected: the real report and account type are unknown until CR-06
and CR-07). The fixture's headers are marked illustrative and are replaced when the real file arrives.

## R-2. The native importer cannot be the dry run

**Finding:** with `parse => true`, the importer writes while parsing, before `import()` is called:

- `parse_relative_field()` saves a placeholder product for an unknown parent (lines 228 to 251);
- `parse_id_field()` saves a placeholder product for an unknown id (lines 289 to 310);
- `parse_categories_field()` and `parse_tags_field()` insert missing terms (`wp_insert_term`, lines 455, 502, 533).

**Decision:** do not invoke the native parsed-data pipeline during preview or validation. With `parse => false`, the
inspected `read_file()` path reads and maps rows without invoking the placeholder or term-creation callbacks. Its
constructor still loads WooCommerce services and invokes filters (line 56), so this is not proof that the whole
WordPress process performs no writes. Mizzey's own reader is chosen for explicit format handling and bounded reading,
not because native raw reading creates catalogue records. Native file validation accepts CSV and TXT extensions by
default (`wc-conditional-functions.php`, line 522), so the fixture's `.tsv` extension needs its own handling. Every
interpretation in the preview and the pass is Mizzey's own read-only code, and a test proves the catalogue unchanged
(AC-5). **Rationale:**
this is the gap P-008 recorded ("no dry run"), now located precisely. **Alternative rejected:** running the native
parse inside a database transaction and rolling it back: WordPress caches, term counts and the CoreX Activity log are not
transactional, so a rollback is not proof of no write.

## R-3. Reading the agreed format

**Decision:** implement the tab-delimited fixture path now, with PHP's own CSV reader, the mapping's delimiter, a
byte-order mark stripped and the documented encoding (UTF-8 unless the mapping says otherwise); the same delimited
reader serves CSV where needed. An Excel (`.xlsx`) reader is deferred until the agreed export requires it; then an
existing suitable reader is assessed before committing to a handwritten workbook parser, because XML parsing alone does
not establish correct spreadsheet-cell interpretation. The availability of any PHP extension on each deployment target
is a prerequisite to verify at that point, not an assumed property of every runtime. Any other source format needs
agreement (CR-06).

## R-4. Resolving a SKU to a product and its language role

**Finding (A5, #249):** a WPML duplicate carries its original's SKU, and `wc_get_product_id_by_sku()` returns whichever
product the query reaches first (`class-wc-product-data-store-cpt.php`, line 1341).

**Decision:** use prepared, batched candidate queries joining WooCommerce's `wc_product_meta_lookup` (its `sku` column
is indexed, `class-wc-install.php`, line 1985) to posts, retaining every matching product and variation and excluding
trash. No `LIMIT 1`. The SKU column's collation is `utf8mb4_unicode_520_ci` (measured on the runtime): case, accents and
trailing spaces do not count, which is how WooCommerce's own lookup and uniqueness check compare. The source SKU is
trimmed for the query and kept as written in the report; each candidate is marked `exact` (byte for byte) or
`collation`, and a `collation` match carries W1 with its id and stored SKU. The outcome follows the match, as the
loader's would; a native collision is never predicted as an uncomplicated create.

For each candidate, `wpml_element_language_details` is called with its actual element type (`post_product` or
`post_product_variation`). A valid details object with a language and a translation-group id is required before
`source_language_code === null` is read as "original"; missing details are an identity issue, not an original. The
complete group is resolved by translation-group id and element type, including members whose SKU differs, so a missing
original is distinguished from an original that is merely absent from the SKU candidate set. **Rationale:** one indexed
read per batch, not one per row (the 5,000-product sizing check); near matches are reported, never used silently
(spec, edge cases).

## R-5. The initial-load rules

**Decision:** the rules that turn an interpreted row into create, update or reject are data in the import specification
(`contracts/initial-load-rules.md`), versioned with the mapping, and applied by one read-only rules component. They are
the rules the live load (#247) will apply, so the pass predicts that load rather than an imagined one. Summary:

| SKU resolves to | Outcome |
|---|---|
| nothing | create, if the row's required fields for its type are present |
| one source-language original, with or without its translations | update that original, if the row passes the applicable update checks; the translations are listed |
| a translation group with no usable original, or unresolvable identity details | reject: the store's original is missing or unresolvable |
| two or more unrelated originals | reject: ambiguous SKU |
| an unresolved repeated source SKU | reject the affected ambiguous rows; file order does not select a valid identity |

A variation needs an unambiguous usable variable parent, resolved from the store or a valid file row irrespective of row
order. Validation indexes the file's parent candidates before finalising dependent outcomes and performs no conversion or
repair writes.

## R-6. Recording the run

**Decision:** an `ImportRunRecorder` contract with the payload of the spec's CX-04 dependency; the default
implementation writes `run.json` beside the reports, marked as a fixture-adapter record. When #273 delivers ENT-19, a
second implementation stores the run; the pass does not change. **Rationale:** CX-04 and #273's ownership of ENT-19.

## R-7. Where the tool runs

**Decision:** a WP-CLI command in the site plugin, `wp mizzey catalogue preview` and `wp mizzey catalogue validate`,
registered only when WP-CLI is present. **Rationale:** the Developer operates it (MIG-01 to MIG-19 all say "the
Developer"); no administration screen is owed, and a command keeps it off every web request. CoreX has no CLI generator
for a site command (`wp help corex` lists none), so it is a plain WP-CLI command.

## R-8. Reports

**Decision:** three files per run in a directory the Developer names: `validation-report.csv` (one line per data row),
`client-issues.csv` (the reviewed issues for the Client) and `run.json`. The CSVs are UTF-8 with a byte-order mark so
Excel shows Arabic correctly. **Rationale:** MIG-19's list goes to the Client, who opens it in Excel; English headings
(ADM-159).

## R-9. The Developer's review step

**Decision:** the pass marks each issue `client` or `developer` by its rule (for example, a missing price is `client`;
a column the mapping does not yet cover is `developer`). `client-issues.csv` is written from the `client` issues, and
the Developer reviews it before sending (MIG-19 says "reviewed by the Developer"). The review is a step in
`quickstart.md`, not code.

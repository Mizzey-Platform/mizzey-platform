# Analysis: catalogue import specification and validation pass

The review record for T026. Read-only reviews by Codex (gpt-6.1-sol, effort high or medium), each finding checked
against the code or the sources before it was applied.

## Planning reviews

| Review | Verdict | Applied |
|---|---|---|
| Plan, research, data model, contracts, quickstart | FIX FIRST, 12 fixes | All: a query-level write guard with permitted housekeeping, the SKU query and WPML group resolution, create and update requirements, no first-row-wins, parent resolution by valid relationships, raw identifiers kept, near-match evidence, CLI and payload reconciled, a stronger test map, the Excel reader deferred, the missing schema reference, the operator not told to hide defects |
| Re-check of those fixes | READY | |
| Tasks against the spec and plan | FIX FIRST, 4 fixes | All: report tests named, failure status tested, the FR-003 and FR-011 boundary reviews, named manual checks |

## Code review (FIX FIRST, applied)

| Finding | Applied |
|---|---|
| The PHP comparison key only approximated the collation | Keys are the database's collation weights under the lookup column's actual collation; t35 adds `STRAßE` = `STRASSE` and a distinct `STRASE` |
| The write guard missed comment shapes and could refuse a value that named a table | A statement tokeniser: values and comments set aside, executable comments read; t34 covers six shapes and an option value |
| WordPress loads before the guard | The plugin installs the guard as it loads when a catalogue command is run; a refusal then halts with exit 3; t34 writes on `init` to prove it. Preview halts only after clean-up |
| Writes were not checked for completeness | Every report write and the run record's byte count are checked |
| A later failure could leave the run marked completed | Every outcome replaces the earlier one; counts kept; an output directory holding an earlier run is refused |
| A rejected parent's Developer defect reached the Client list | The child inherits the parent's owner; t36 proves neither reaches the list |
| A non-UTF-8 encoding was decoded after parsing | Converted before parsing, source bytes validated first; t30 adds UTF-16, a quoted first header after a BOM, a multi-line cell and an invalid surrogate |
| t34 could pass on a failed command and left a probe behind | Expected exits asserted, the probe tracked, the guard always removed; the snapshot fails closed |
| The SQL exemption in the shared text check was too broad | Replaced by an exemption for literals inside `$wpdb` query calls only, tested both ways |

Kept as designed, with the reason: leading and trailing spaces are trimmed before matching, because the loader trims
SKUs (WooCommerce's importer cleans them with `wc_clean()`); any match that is not byte for byte carries W1. Recorded in
`contracts/initial-load-rules.md`.

## Boundary reviews (FR-003, FR-011)

- **FR-003, one mapping, no profiles:** the tool reads one file, `mizzey-site/import/catalogue-mapping.json`, or another
  named with `--mapping` for a run. Nothing stores, lists or reuses mappings (`Mapping::load()` only reads).
- **FR-011, Developer-operated only:** the command is registered only under WP-CLI (`mizzey-site.php`); no admin page,
  REST route, hook on a web request or storefront output is added. The guard is installed only for these two commands.

## Sequence

The components were written before the scenarios, against T013. Four deliberate defects were each shown to fail the
scenario that guards them (`evidence/mutation.txt`): the guard switched off, first-row-wins duplicates, a key that
ignores the collation, and a rejected parent's owner forced to the Client.

## House rules

No em dash, no emoji, no heading below h3 in the changed files. Every new string is translatable
(`test_storefront_strings`); every page fetch reaches the storefront guard or states its exemption
(`test_storefront_guard`).

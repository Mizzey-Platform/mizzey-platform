# Initial-load rules, version 1

The rules the live load (#247) applies, and the validation pass predicts. Owned by this specification; a change bumps
the version, and the mapping names the version it assumes (`rules_version`). Encoding, variation conversion and price
or cost loading remain #247's; taxonomy and SEO migration #295's. These rules decide only the outcome and its reason.
`mizzey-site/src/Catalogue/Import/LoadRules.php` applies them.

## Order of evaluation

File-level checks run first and stop the run with no row outcomes. Then every row is interpreted, repeated SKUs are
found across the whole file, every SKU is resolved, non-variation rows are decided, and variation rows are decided last,
so a parent written below its variations counts. Every rejection condition is evaluated before create or update; every
applicable issue is kept on the row; the reported rule is the first rejection in this order: R8, R7, R6, R2, R3, R4,
R9, R1, R5. The same row always gets the same answer, whatever its position in the file.

## Matching a SKU

The source SKU is kept as written in every report. For matching, surrounding spaces, case and accents do not count:
that is how the store's SKU column compares (`utf8mb4_unicode_520_ci` on WooCommerce's lookup table), so it is how
WooCommerce's own SKU lookup and uniqueness check would treat the row at load time. A match that is not byte for byte
is kept, not hidden and not turned into a create, and it carries W1 with the stored SKU and the comparison.

## Required values

| Row type | To create | To update |
|---|---|---|
| simple | name, SKU, regular price | SKU |
| variable | name, SKU | SKU |
| variation | SKU, parent SKU, regular price, at least one attribute value | SKU, parent SKU |

On an update a blank cell means "no change", never "clear the value". A price is digits with a dot and at most two
decimal places; a comma, a currency sign or a thousands separator is R6, not a guess.

## Outcomes

| Code | When | Outcome | Issue owner |
|---|---|---|---|
| `C1` | No store record has the SKU, and the create values are present | create | |
| `U1` | The row passes the update checks and the SKU resolves to one source-language original (its translations are listed) | update | |
| `R1` | A required value for the row type and operation is empty | reject | client |
| `R2` | The SKU repeats another row's SKU, by the matching rule above | reject every affected row; their row numbers are named | client |
| `R3` | The SKU's translation group has no usable original (missing or in the trash), or a matched record has no language details | reject | developer |
| `R4` | The SKU belongs to two or more unrelated store records | reject; the records are named | developer |
| `R5` | A variation has no unambiguous usable variable parent: not in the file or the store, on more than one row, on a rejected or non-variable row, or a store record that is not a variable product | reject | client for the file; developer for the store record |
| `R6` | A value cannot be read: a price that is not a plain number, or text not valid in the declared encoding | reject | client |
| `R7` | The row-type value is not one the mapping lists | reject | client |
| `R8` | The row has more or fewer cells than the header has columns | reject | client |
| `R9` | The SKU belongs to a store record of another kind (a variation for a simple or variable row, or the reverse) | reject | developer |

## File-level failures

| Code | When |
|---|---|
| `F1` | A column the mapping fills is missing from the header |
| `F2` | A header appears more than once |
| `F3` | The file has no header row (an empty file) |

A file with a header and no rows completes with no outcomes.

## Warnings

| Code | When | Owner |
|---|---|---|
| `W1` | The SKU matched a store record that stores it differently (case, accents or spaces); the outcome follows the match | developer |
| `W2` | The file has a column the mapping does not list (file level); the mapping is not complete until it is mapped or recorded as not imported | developer |
| `W3` | On a create, an Arabic column the mapping fills is empty | client |
| `W4` | The SKU matched a translation; its original carries a different SKU | developer |

The `client` issues go to `client-issues.csv` for the Developer's review. The `developer` issues are corrected in the
mapping where the mapping is wrong, or routed to their owning work where the store data is wrong; rules are never changed
merely to suppress an issue.

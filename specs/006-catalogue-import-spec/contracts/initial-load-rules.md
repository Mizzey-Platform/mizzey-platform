# Initial-load rules, version 1

The rules the live load (#247) applies, and the validation pass predicts. Owned by this specification; a change bumps
the version, and the mapping names the version it assumes. Encoding, variation conversion and price or cost loading
remain #247's; taxonomy and SEO migration #295's. These rules decide only the outcome and its reason.

## Row types

| Type | Read from | Needs |
|---|---|---|
| simple | the mapping's `row_type` | name, SKU, regular price |
| variable | the mapping's `row_type` | name, SKU; the attributes its variations use |
| variation | the mapping's `row_type` | SKU, parent SKU, a value for each of the parent's variation attributes, regular price |

A field is required only by this table, never because the fixture happens to carry it.

## Outcomes

| Code | When | Outcome | Issue owner |
|---|---|---|---|
| `C1` | The SKU resolves to nothing and the required fields are present | create | |
| `U1` | The SKU resolves to one source-language original (its translations are listed) | update | |
| `R1` | A required field for the row type is empty | reject | client |
| `R2` | The SKU repeats an earlier row's SKU in the file | reject (the earlier row keeps its own outcome; this one is rejected) | client |
| `R3` | The SKU resolves only to a translation, with no original | reject | developer |
| `R4` | The SKU resolves to two or more unrelated originals | reject | developer |
| `R5` | A variation's parent SKU resolves to no parent row in the file and no existing variable product | reject | client |
| `R6` | A value cannot be read as its field's type (a price that is not a number) | reject | client |
| `F1` | A column the mapping maps is missing from the header | file-level failure, no row outcomes | developer |

## Warnings on a row, whatever its outcome

| Code | When | Owner |
|---|---|---|
| `W1` | The SKU matches a store SKU only after trimming or case folding (a near match); the exact rule was applied | developer |
| `W2` | The file has a header column the mapping does not list | developer |
| `W3` | Arabic text is expected in a column and the column is empty | client |

The `client` issues go to `client-issues.csv` after the Developer's review; the `developer` issues are resolved in the
mapping or the rules.

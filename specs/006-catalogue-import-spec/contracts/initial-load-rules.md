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

Required fields are specified separately for create and update operations and justified against the documented loader.
The table above describes fixture create cases, not universal requirements for existing-product updates. Regular price
and Arabic content are not universally mandatory merely because the fixture contains them. Omitted-field and blank-cell
behaviour are documented separately. File-level checks run first; row rejection conditions are evaluated before C1 or
U1. Each row receives one deterministic outcome while retaining all applicable issues.

## Outcomes

| Code | When | Outcome | Issue owner |
|---|---|---|---|
| `C1` | The SKU resolves to nothing and the required fields are present | create | |
| `U1` | The row passes the applicable update checks and resolves to one source-language original (its translations are listed) | update | |
| `R1` | A required field for the row type is empty | reject | client |
| `R2` | Repeated source SKUs cannot be resolved unambiguously under the documented initial-load rules | reject the affected ambiguous rows; report their related row numbers | client |
| `R3` | The complete WPML translation group has no usable original, or its identity details cannot be resolved | reject | developer |
| `R4` | The SKU resolves to two or more unrelated originals | reject | developer |
| `R5` | A variation has no unambiguous usable variable parent after resolving store identity and relevant file-row outcomes, including parents appearing later in the file; a rejected or non-variable file row alone is not a usable parent | reject | client for source-data correction; developer for store-identity or mapping problems |
| `R6` | A value cannot be read as its field's type (a price that is not a number) | reject | client |
| `F1` | A column the mapping maps is missing from the header | file-level failure, no row outcomes | developer |

## Warnings on a row, whatever its outcome

| Code | When | Owner |
|---|---|---|
| `W1` | The SKU matches a store SKU only after trimming or case folding (a near match); the exact rule was applied | developer |
| `W2` | The file has a header column the mapping does not list | developer |
| `W3` | Arabic text is expected in a column and the column is empty | client |

The `client` issues go to `client-issues.csv` after the Developer's review. The `developer` issues are corrected in the
mapping where the mapping is wrong, or routed to their owning work where the store data is wrong; rules are never changed
merely to suppress an issue.

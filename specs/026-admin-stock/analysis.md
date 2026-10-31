# Analysis: stock in the admin

Read-only reviews by Codex (gpt-6.1-sol, effort high). Each finding was checked against WordPress 7.1.4's,
WooCommerce 11.1.0's and WooCommerce Multilingual 5.5.7's code before it was applied, and each fix has a t67 check
that fails when the fix is removed (`evidence/mutation.txt`), unless the record says why it cannot have one.

| Round | Verdict | Findings, and what was done |
|---|---|---|
| 1 | FIX FIRST | Nine. The first design reset stock on the product object at save, which left every writer below the object open: the metadata API, WooCommerce's direct SQL stock update and WooCommerce Multilingual's raw synchronisation. Rewritten to refuse at the storage boundary (the metadata API's short-circuit filters and `$wpdb`'s `query` filter), with the REST API refused before dispatch and the admin fields locked for display only |
| 2 | FIX FIRST | Four High, three Medium. A refused save of 0 still moved the out-of-stock visibility term: the object takes the stored stock settings before WooCommerce derives anything from them. Writes by meta id only, a delete for every object, and keys in another case or with a trailing space escaped: all refused, keys compared as MySQL compares them. A bulk edit lost its price: only the stock settings are read back. Defaults were let through one layer and refused by the other: both let them through. Version 1's nested variations: read |
| 3 | FIX FIRST | One still open, two new, all Medium or above. The raw-SQL layer matched text: quoted meta ids escaped it, a value that reads like a stock key (a purchase note `_stock`) was refused, and WooCommerce's lookup rebuild, which only reads the post meta table, was blocked. The layer was rewritten: quoted text is blanked before the statement is read, the statement's target table is found, and an update or a delete is judged by the rows MySQL selects for its own condition |
| 4 | FIX FIRST | One Medium. WPML copies a duplicate's meta in one insert, so refusing its stock rows lost the price and SKU with them: the stock keys are left out of WPML's duplication while the permit is off. The mutation run also showed two layers whose loss t67 did not see: without the metadata-API layer the raw layer still refuses, but WordPress announces a write that did not happen, so t67 asserts no announcement; and the bulk edit's answer before its save is now asserted |
| 5 | | |

## Limits, recorded

- Code that already runs with full PHP privileges can unhook the guard; the guard stops WordPress's, WooCommerce's
  and WooCommerce Multilingual's own writers, and the admin, not a hostile plugin.
- An insert from a query or by SET is refused when it names a stock key at all; none of the native writers makes one.
- The ERP's own mechanism waits for PRE-09 (AC-1 and AC-2 stay pending); the adapter writes through
  `StockWriteGuard::projection()`.

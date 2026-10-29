# Command contract

Registered only when WP-CLI is present. Never available on a web request.

## `wp mizzey catalogue preview <file> [--rows=<n>]`

Prints the mapping version, the format and encoding read, the header check, and the first `n` data rows (default 10)
field by field as the store would interpret them. Writes nothing. Exit 0 when the header check passes, 1 on a
file-level failure.

## `wp mizzey catalogue validate <file> --out=<dir>`

Runs the whole-file validation pass and writes, in `<dir>`, `validation-report.csv`, `client-issues.csv` and `run.json`
(contracts in `../data-model.md`). Prints the totals per outcome and the issue counts. Writes nothing to the catalogue.
Exit 0 when the pass completed, whatever the outcomes; 1 on a file-level failure; 2 on a usage error or an unreadable
file.

## Both

Read only `<file>` and `mizzey-site/import/catalogue-mapping.json`. A catalogue write attempted during a run stops it
with exit 3 and the name of the write.

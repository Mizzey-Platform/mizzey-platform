# Command contract

Registered only when WP-CLI is present. Never available on a web request.

## `wp mizzey catalogue preview <file> [--rows=<n>]`

Prints the mapping version, the format and encoding read, the header check, and the first `n` data rows (default 10)
field by field as the store would interpret them. Writes no report files or catalogue data; documented read-side cache
housekeeping may occur. Exit 0 when the header check passes, 1 on a
file-level failure.

## `wp mizzey catalogue validate <file> --out=<dir>`

Runs the whole-file validation pass and writes, in `<dir>`, `validation-report.csv`, `client-issues.csv` and `run.json`
(contracts in `../data-model.md`). Prints the totals per outcome and the issue counts. Writes nothing to the catalogue.
Exit 0 when row validation completed, whatever the row outcomes; 1 on a file-level failure; 2 on a usage error or an
unreadable file. Where output is writable, a file-level failure records its reason in `run.json` and produces no
invented row outcomes. A guard or output-write failure never leaves a run marked completed.

## Both

Read the source file and the mapping from the filesystem, and read catalogue and WPML identity data from WordPress.
Catalogue mutations are prohibited; documented read-side cache housekeeping is permitted. A protected catalogue mutation
attempted during a run stops it with exit 3, before the mutation, and the name of the write.

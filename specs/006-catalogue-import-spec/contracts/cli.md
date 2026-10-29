# Command contract

Registered only when WP-CLI is present. Never available on a web request.

## `wp mizzey catalogue preview <file> [--rows=<n>] [--mapping=<path>]`

Prints the mapping version, the format and encoding read, the header check, and the first `n` data rows (default 10)
field by field as the store would interpret them. Writes no report files or catalogue data; documented read-side cache
housekeeping may occur. Exit 0 when the header check passes, 1 on a file-level failure, 2 on an unreadable file or an
unusable mapping, 3 when a catalogue write was refused.

## `wp mizzey catalogue validate <file> --out=<dir> [--mapping=<path>]`

Runs the whole-file validation pass and writes, in `<dir>`, `validation-report.csv`, `client-issues.csv` and `run.json`
(contracts in `../data-model.md`). Prints the totals per outcome, the issue counts and the peak memory. Writes nothing
to the catalogue.

- `<dir>` must not already hold any of the three files: a run never overwrites an earlier run's evidence.
- Exit 0 when row validation completed, whatever the row outcomes; 1 on a file-level failure; 2 on a usage error (an
  unreadable file, an unusable mapping, an output directory that cannot be written or already holds a run) or on
  output that was not written completely; 3 when a catalogue write was refused.
- Where output is writable, a file-level failure records its reason in `run.json` and produces no invented row
  outcomes. A guard or output failure is recorded as such and never leaves a run marked completed; the counts reached
  before it are kept.

## Both

`--mapping` names another import specification; the default is `mizzey-site/import/catalogue-mapping.json`. An unusable
mapping is refused with every problem named.

Read the source file and the mapping from the filesystem, and read catalogue and WPML identity data from WordPress.
Catalogue mutations are prohibited; documented read-side cache housekeeping is permitted.

The write guard is installed by the plugin as WordPress loads whenever one of these two commands is the one being run,
so the load itself is covered (WooCommerce's work on `init` included). A protected catalogue mutation stops the run
before it happens, with exit 3 and the statement named: during the command it is recorded in `run.json` as
`guard-refused`; during the load, before the command starts, the process stops with the same exit and reason and no run
is recorded.

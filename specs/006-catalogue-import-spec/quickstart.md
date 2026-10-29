# Quickstart: validating the client's export

On the development runtime or the staging server, never on production.

1. Put the file outside the web root.
2. `wp mizzey catalogue preview <file>`: read the header check. A missing mapped column (`F1`) means the mapping does not
   fit this file: edit `mizzey-site/import/catalogue-mapping.json`, bump its `version`, and repeat.
3. Read the first rows: the right columns in the right fields, Arabic text readable, prices as numbers.
4. `wp mizzey catalogue validate <file> --out=<dir>`, with a new directory for each run: a run never overwrites an
   earlier one. Exit 3 means a catalogue write was attempted and refused; find out what attempted it before going on.
5. Read `validation-report.csv`. Correct mapping errors, documenting and versioning genuine mapping or loader-rule
   changes. Route missing-original, ambiguous-identity and other store-data defects to their owning work; this tool
   performs no repair. Do not change rules merely to suppress an issue. Re-run after the relevant correction and retain
   unresolved findings explicitly.
6. Review `client-issues.csv` (MIG-19: "reviewed by the Developer"): every line is the Client's to correct, with the row,
   field and correction. Send it to the Client.
7. Keep `run.json` with the evidence of the run. Future runs use #273's persistence adapter when available. This
   fixture record does not prove database persistence, and automatic backfilling of earlier runs is not promised.
8. On the real sample export (CR-06), this sequence is AC-8.

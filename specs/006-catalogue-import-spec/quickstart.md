# Quickstart: validating the client's export

On the development runtime or the staging server, never on production.

1. Put the file outside the web root. Note its name, size and sha256.
2. `wp mizzey catalogue preview <file>`: read the header check. A missing mapped column (`F1`) means the mapping does not
   fit this file: edit `mizzey-site/import/catalogue-mapping.json`, bump its `version`, and repeat.
3. Read the first rows: the right columns in the right fields, Arabic text readable, prices as numbers.
4. `wp mizzey catalogue validate <file> --out=<dir>`.
5. Read `validation-report.csv`. Resolve every `developer` issue in the mapping or the rules (`R3`, `R4`, `W1`, `W2`),
   bump the version, and re-run until only `client` issues remain.
6. Review `client-issues.csv` (MIG-19: "reviewed by the Developer"): every line is the Client's to correct, with the row,
   field and correction. Send it to the Client.
7. Keep `run.json` with the evidence of the run. When #273 delivers the Import Run record, the same run is stored there.
8. On the real sample export (CR-06), this sequence is AC-8.

# Verification: catalogue import specification and validation pass

Three states, kept apart (constitution M-7): technically verified, verified on staging, accepted by the client. Only
the first is claimed here, and it is against the fixture, not the real export.

## 1. Technical verification, 29 October 2026, development runtime

Stack: WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, WCML 5.5.7, CoreX v0.47.0. The SKU lookup column's collation is
`utf8mb4_unicode_520_ci`, read from the database.

| Evidence | What it shows |
|---|---|
| `evidence/clean-baseline.txt` | The runtime reset to the clean baseline before the suite (exit 0) |
| `evidence/final-suite.txt`, `final-suite.json` | The whole suite, serially: 37 scenarios, 31 pass, 6 fact-finding, 0 failed |
| `evidence/mutation.txt` | Four deliberate defects, each failing the scenario that guards it, the code restored after each |

| AC | Scenario | Result |
|---|---|---|
| AC-1 | t30 | Pass: the tab-delimited fixture read row for row, identifiers and Arabic kept; UTF-16 converted before parsing; a quoted first header after a byte-order mark; a cell spanning lines; invalid bytes stop the read |
| AC-2 | t31 | Pass: every fixture header mapped or recorded as not imported, with a reason; F1, F2, F3 stop the run; W2 reported and the mapping not called complete; six unusable mappings refused |
| AC-3 | t32 | Pass: the preview names the mapping and rules versions, format and encoding, shows rows field by field with Arabic intact, stops at the row limit; a misfit exits 1; an unreadable file exits 2 |
| AC-4 | t33 | Pass: 23 rows, each on its intended rule for its intended reason; several issues kept on one row; parents written later count; the same rows reversed give the same outcomes; empty and header-only files |
| AC-5 | t34 | Pass: preview, validate and a file-level failure each leave every protected table's checksum unchanged; API, direct SQL and seven statement shapes refused; option writes and reads allowed; a write during a run exits 3 as `guard-refused`; a write while WordPress loads exits 3 and records no run |
| AC-6 | t35 | Pass: duplicate and separately translated pairs, no match, unrelated records, missing language details, a trashed original, a translation carrying the SKU, trash, case, collation equivalence (`STRAßE` and `STRASSE`), a distinct `STRASE`, leading and trailing padding, and a variation |
| AC-7 | t36 | Pass: every Client issue on the list with row, field, problem and correction, including one on a created row; no Developer issue, including one a child inherits from a parent rejected for a store-identity defect |
| CX-04 | t37 | Pass: the complete payload, marked fixture-adapter and not persisted; a file-level failure recorded with its reason and no row outcomes; an earlier run never overwritten; unwritable output a usage error |
| FR-012 | t38 | Fact: 5,000 rows validated whole as a completed run, about 4 s and 200 MB on this machine |

Unit and repository checks: `python -m unittest discover -s tools/tests -t .`, `repo_checks.py`, `discovery.check`,
`house_rules.py` and `scope_trace.py`, as reported in the pull request.

## 2. Staging

Not done. DOD-04 staging verification waits for the owner's deployment approval; the person's own review is separate
from any scripted check.

## 3. Client acceptance

Not done. AC-8 waits for the real sample export (CR-06) and the report type (CR-07). AC-3 stays `pending CX-04` until
the client confirms it. Client approval is through MS-UAT-2026-027 (DOD-09).

# Scope source record

`register-ids.json` is generated. Do not edit it by hand.

## Current source

| Field | Value |
|---|---|
| Document | Feature Register, MS-ANX-2026-006 |
| Version | 1.5, 22 September 2026 (contract pack MZ-02 REV4) |
| Source file (outside this repository) | `final docs/Client/Branded/Mizzey-Launch-Platform-Feature-Register.md` |
| Source SHA-256 | `6624a6dcbc351e5437bc8aedf7008de8cae5e332acf694f328380fb58322204f` |
| Signed-pack file | `03 SIGN - Feature Register (scope).pdf` in `Mizzey-Launch-Platform-Pack-MZ-02-REV4-FINAL.zip` |
| Pack zip SHA-256 | `ea9c4377789f0660ab04b7ec75cc9667d077f90b9e7a31617b99f8c7f95a8c13` |
| Ids extracted | 754 (P1 435, P1-L 117, P1-E 19, DLV 24, DEF 44, P2 74, P3 32, OUT 9) |

## How it was verified (22 September 2026)

1. Extraction reads only tables whose header has both a Scope and a Stage column, by column name. Any id whose
   scope or stage differs between two tables stops the extraction. None did.
2. 22 known rows were spot-checked against the register text. They included ADM-27, RPT-11, ROLE-06 (DEF),
   ERP-03 (P1-E), ERP-09 (OUT), MIG-13 (P1-L), SHIP-16 (P1-L key) and ADM-159a (P2). All matched.
3. Every id in the markdown that is absent from the output is of a non-requirement kind: CHG, CR (client
   responsibilities), EX (exclusions) and OD (open decisions). None of those kinds is a scope row.
4. Cross-check against the signed PDF: the text extracted from the REV4 register PDF contains all 754 ids, and no
   requirement-kind id appears in the PDF that the extraction missed. The PDF states Version 1.5 and
   MS-ANX-2026-006.

## Regenerating

When a new register version is issued through change control:

```bash
python tools/extract_register_ids.py --register "<path to the new register markdown>"
```

Then update this file (version, hashes, counts, verification) in a PR of its own, classified
`internal:governance`. `--check` confirms the committed file matches a given register without writing.

"""Generate docs/scope/register-ids.json from the Feature Register markdown.

The register is the only scope authority. This script reads its tables and records, for every row id, the scope
value, the stage and the key marker. It records no requirement text, so the repository carries traceability without
carrying a copy of the contract.

Usage:
    python tools/extract_register_ids.py --register "<path to Mizzey-Launch-Platform-Feature-Register.md>"
    python tools/extract_register_ids.py --register <path> --check   # fail if the committed file is stale

Only tables whose header has both a Scope and a Stage column are read. Where an id appears in more than one such
table, every occurrence must agree, or the script fails: a disagreement is a contract contradiction to raise, not
something to pick a winner for.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "docs" / "scope" / "register-ids.json"

SCOPES = ("P1-L", "P1-E", "P1", "DEF", "P2", "P3", "OUT", "DLV", "DEC")
OBLIGATION = {"P1", "P1-L", "P1-E", "DLV"}
ID_RE = re.compile(r"^\*{0,2}([A-Z]{2,5}-\d{1,3}[a-z]?)\*{0,2}")
STAGE_RE = re.compile(r"\b(S1|S2)\b")


def cells(line: str) -> list[str]:
    return [c.strip() for c in line.strip().strip("|").split("|")]


def parse_scope(cell: str) -> str | None:
    text = cell.replace("*", "").strip()
    for s in SCOPES:
        if re.match(rf"^{re.escape(s)}(?![-A-Z])", text):
            return s
    return None


def parse_stage(cell: str) -> str:
    m = STAGE_RE.search(cell.replace("*", ""))
    return m.group(1) if m else "-"


def stage_column(header: list[str]) -> int:
    """The index of the register's stage column.

    One table, the customer journey (A5), has two columns headed Stage: the first names the journey step, such
    as Discovery, and the last carries S1 or S2. The contractual stage is always the last one. Reading the first
    recorded every journey row as unstaged.
    """
    return len(header) - 1 - header[::-1].index("stage")


def extract(md: str) -> dict[str, dict]:
    rows: dict[str, dict] = {}
    conflicts: list[str] = []
    header: list[str] | None = None
    for n, line in enumerate(md.splitlines(), 1):
        if not line.startswith("|"):
            header = None
            continue
        c = cells(line)
        if header is None:
            header = [h.replace("*", "").strip().lower() for h in c]
            continue
        if set("".join(c)) <= set("-: "):
            continue
        if "scope" not in header or "stage" not in header or header[0] != "id":
            continue
        m = ID_RE.match(c[0])
        if not m:
            continue
        rid = m.group(1)
        scope = parse_scope(c[header.index("scope")])
        if scope is None:
            conflicts.append(f"line {n}: {rid} has no recognisable scope value: {c[header.index('scope')]!r}")
            continue
        rec = {
            "scope": scope,
            "stage": parse_stage(c[stage_column(header)]),
            "key": "[key]" in line,
            "obligation": scope in OBLIGATION,
        }
        if rid in rows:
            prev = rows[rid]
            if (prev["scope"], prev["stage"]) != (rec["scope"], rec["stage"]):
                conflicts.append(f"line {n}: {rid} is {rec['scope']}/{rec['stage']} here but "
                                 f"{prev['scope']}/{prev['stage']} at line {prev['line']}")
            prev["key"] = prev["key"] or rec["key"]
            continue
        rec["line"] = n
        rows[rid] = rec
    if conflicts:
        raise SystemExit("register extraction failed:\n  " + "\n  ".join(conflicts))
    return rows


def build(register: Path) -> dict:
    raw = register.read_bytes()
    md = raw.decode("utf-8")
    doc = re.search(r"\*\*Document:\*\*\s*(\S+)", md)
    ver = re.search(r"\*\*Version:\*\*\s*(\S+)", md)
    rows = extract(md)
    return {
        "generated_by": "tools/extract_register_ids.py",
        "source": {
            "document": doc.group(1) if doc else None,
            "version": ver.group(1) if ver else None,
            "file": register.name,
            "sha256": hashlib.sha256(raw).hexdigest(),
        },
        "obligation_scopes": sorted(OBLIGATION),
        "count": len(rows),
        "ids": {k: {kk: vv for kk, vv in v.items() if kk != "line"} for k, v in sorted(rows.items())},
    }


def main(argv: list[str] | None = None) -> int:
    ap = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    ap.add_argument("--register", required=True, type=Path)
    ap.add_argument("--out", type=Path, default=OUT)
    ap.add_argument("--check", action="store_true", help="fail if --out differs from a fresh extraction")
    a = ap.parse_args(argv)
    data = build(a.register)
    text = json.dumps(data, indent=1, ensure_ascii=False) + "\n"
    if a.check:
        current = a.out.read_text(encoding="utf-8") if a.out.exists() else ""
        if current != text:
            print(f"{a.out} is stale against {a.register}", file=sys.stderr)
            return 1
        print(f"{a.out} matches {a.register.name} ({data['count']} ids)")
        return 0
    a.out.parent.mkdir(parents=True, exist_ok=True)
    a.out.write_text(text, encoding="utf-8", newline="\n")
    print(f"wrote {a.out} ({data['count']} ids, {data['source']['document']} v{data['source']['version']})")
    return 0


if __name__ == "__main__":
    sys.exit(main())

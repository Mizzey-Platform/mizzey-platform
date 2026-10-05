"""Regenerate the generated parts of the two backlog records from their sources.

Two records carry generated tables, and a hand edit of either is how they drifted from the board before:

  docs/2026-10-04-option-b-backlog-structure.md   the slices by epic, and the ownership map
  docs/2026-10-04-github-project-proposal.md      the board snapshot, and its appendix of exact ids

Sources. The ownership map comes from docs/scope/backlog-ownership.json. The board snapshot comes from the live
Project, which the GitHub Project API serves only to an authenticated client, so it is read by hand and passed in:

    gh project item-list 1 --owner Mizzey-Platform --format json --limit 200 > board.json
    python tools/gen_backlog_docs.py --board board.json --date "5 October 2026"

Read the Project, write the document. Never the reverse.
"""

from __future__ import annotations

import argparse
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
OWNERSHIP = ROOT / "docs" / "scope" / "backlog-ownership.json"
STRUCTURE = ROOT / "docs" / "2026-10-04-option-b-backlog-structure.md"
BOARD_DOC = ROOT / "docs" / "2026-10-04-github-project-proposal.md"
EPICS = ("E-PRE", "E-FND", "E-ROLE", "E-SF", "E-RULES", "E-ORD", "E-NOTF", "E-ADM", "E-MIG", "E-ERP", "E-RPT",
         "E-INT", "E-NFR", "E-DATA", "E-EVT", "E-MKT", "E-DOD", "E-ACC", "E-HND")
ABBREVIATE_ABOVE = 5


def group_of(key: str) -> str:
    """The epic group a slice key belongs to: E-ADM-4b -> E-ADM, E-DOD -> E-DOD."""
    for epic in sorted(EPICS, key=len, reverse=True):
        if key == epic or key.startswith(epic + "-"):
            return epic
    raise ValueError(f"slice key {key!r} belongs to no epic")


def between(text: str, start: str, end: str, body: str) -> str:
    """Replace what lies between two markers, keeping both markers."""
    a = text.index(start) + len(start)
    b = text.index(end, a)
    return text[:a] + body + text[b:]


def slices_by_epic(slices: list[dict]) -> str:
    seeded = sum(1 for s in slices if s.get("issue"))
    out = [
        "\n\nGenerated from `docs/scope/backlog-ownership.json` by `tools/gen_backlog_docs.py`. Each slice states the "
        "**exact delivery ids it owns** in the ownership map at the end of this document, which is generated in the "
        "same pass and is what CI reads. `Scope class` and `Stage` are **computed over the ids the slice owns**.\n\n"
        f"**{seeded} of the {len(slices)} slices are PBIs on the live board.** The other {len(slices) - seeded} hold "
        "second-release rows: they are recorded here so that every delivery row has an owner, and they are not "
        "created until the second release is planned. The outcome, dependencies, inputs and notes of a slice live "
        "in its issue and in the ownership file, not in this table.\n\n"
        "A slice may own a row from another register section where the outcome is the same, which is why the "
        "per-epic counts here do not match the epic table above, which counts by register section. Both account "
        "for the same delivery rows.\n"
    ]
    for epic in EPICS:
        mine = [s for s in slices if group_of(s["key"]) == epic]
        if not mine:
            continue
        rows = sum(len(s["ids"]) for s in mine)
        out.append(f"\n### {epic} ({rows} delivery rows over {len(mine)} slice{'s' if len(mine) != 1 else ''})\n\n"
                   "| Slice | PBI | Rows | Scope class | Stage | Delivery order |\n|---|---|---|---|---|---|\n")
        for s in sorted(mine, key=lambda s: (s["delivery_order"] is None, s["delivery_order"] or 0, s["key"])):
            stage = "per PRE-09" if s["stage"] == "-" else s["stage"]
            pbi = f"#{s['issue']}" if s.get("issue") else "not created, second release"
            order = s["delivery_order"] if s["delivery_order"] else "-"
            out.append(f"| {s['key']} {s['title']} | {pbi} | {len(s['ids'])} | `{s['scope_class']}` | `{stage}` | {order} |\n")
    return "".join(out) + "\n"


def ownership_map(slices: list[dict]) -> str:
    out = []
    for epic in EPICS:
        mine = [s for s in slices if group_of(s["key"]) == epic]
        if not mine:
            continue
        out.append(f"**{epic}**\n\n")
        for s in sorted(mine, key=lambda s: s["key"]):
            pbi = f" (#{s['issue']})" if s.get("issue") else ""
            out.append(f"- **{s['key']} {s['title']}**{pbi} ({len(s['ids'])} ids): `{', '.join(s['ids'])}`\n")
        out.append("\n")
    return "".join(out)


def cell(value) -> str:
    text = " ".join(str(value or "-").split())
    return text.replace("|", "/") or "-"


def board_rows(items: list[dict]) -> tuple[str, str, int]:
    table, appendix, total = [], [], 0
    for item in sorted(items, key=lambda i: i["delivery order"]):
        n = item["content"]["number"]
        ids = [x.strip() for x in item["register ids"].split(",") if x.strip()]
        total += len(ids)
        ids_cell = ", ".join(ids) if len(ids) <= ABBREVIATE_ABOVE else f"{len(ids)} ids, listed in the issue"
        table.append(
            f"| {int(item['delivery order'])} | #{n} | {cell(item['title'])} | {item['epic']} | {ids_cell} | "
            f"**{item['scope class']}** | **{item['stage']}** | **{item['status']}** | **{item['eRP blocked']}** | "
            f"{item['design dependency']} | {cell(item.get('data-integrity dependency'))} | "
            f"{cell(item.get('client decision'))} | {cell(item.get('dependencies'))} |\n")
        appendix.append(f"- **#{n}** ({len(ids)} ids): `{', '.join(ids)}`\n")
    return "".join(table), "".join(appendix), total


def main(argv: list[str] | None = None) -> int:
    ap = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    ap.add_argument("--board", type=Path, help="`gh project item-list ... --format json` output; omit to leave the board record alone")
    ap.add_argument("--date", help="the date the board was read, as written in the record")
    a = ap.parse_args(argv)

    slices = json.loads(OWNERSHIP.read_text(encoding="utf-8"))["slices"]
    text = STRUCTURE.read_text(encoding="utf-8")
    text = between(text, "## The slices, by epic", "## What can proceed independently of the ERP, and what cannot",
                   slices_by_epic(slices))
    text = between(text, "Format: slice, live PBI where one exists, count, then the exact ids.\n\n",
                   "### One row, one accepting owner: how the three shared citations were resolved", ownership_map(slices))
    STRUCTURE.write_text(text, encoding="utf-8", newline="\n")

    if a.board:
        if not a.date:
            ap.error("--date is required with --board")
        items = json.loads(a.board.read_text(encoding="utf-8"))["items"]
        table, appendix, total = board_rows(items)
        doc = BOARD_DOC.read_text(encoding="utf-8")
        doc, n = re.subn(r"(?:^\| \d+ \| #\d+ \|.*\n)+", lambda _: table, doc, count=1, flags=re.M)
        assert n == 1, "the snapshot table was not found"
        doc, n = re.subn(r"(?:^- \*\*#\d+\*\* \(\d+ ids?\): `.*\n)+", lambda _: appendix, doc, count=1, flags=re.M)
        assert n == 1, "the appendix was not found"
        doc, n = re.subn(r"^\*\*\d+ exact delivery ids across the .* PBIs\.\*\*$",
                         f"**{total} exact delivery ids across the {len(items)} PBIs.**", doc, count=1, flags=re.M)
        assert n == 1, "the exact-id total line was not found"
        doc, n = re.subn(r"^## The .* PBIs, as the live board holds them$",
                         f"## The {len(items)} PBIs, as the live board holds them", doc, count=1, flags=re.M)
        assert n == 1, "the snapshot heading was not found"
        doc, n = re.subn(r"^Regenerated .*?(?=\n\n)", f"Regenerated {a.date}, by `tools/gen_backlog_docs.py` from the live Project.",
                         doc, count=1, flags=re.M | re.S)
        assert n == 1, "the regeneration line was not found"
        BOARD_DOC.write_text(doc, encoding="utf-8", newline="\n")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

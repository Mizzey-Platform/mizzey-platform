"""The Option B backlog document's row counts must match the machine-readable scope extraction.

The backlog document (docs/<date>-option-b-backlog-structure.md) carries two hand-written tables of counts: a
scope summary, one row per scope class, and an epic table, one row per register section plus a TOTAL row. Both can
drift from docs/scope/register-ids.json, which is generated. A drift of one row went unnoticed once already: the
pre-development deliverables are labelled PRE-01 to PRE-09, which reads as nine, but the register splits PRE-03
into PRE-03a and PRE-03b, so the series holds ten ids. The hand count of nine made the epic table total 594
against an authoritative 595.

check() recomputes the totals from register-ids.json and compares them with what the document says, so a reissued
register or an edited table fails here instead of being believed. It reads; it never writes.
"""

import json
import re
import shutil
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DOC = "docs/2026-10-04-option-b-backlog-structure.md"
DOC_GLOB = "docs/*-option-b-backlog-structure.md"
DELIVERY = ("P1", "P1-L", "P1-E", "DLV")

# "| E-FND | A. Foundation ... | 58 | 12 | 0 | 0 | 70 | 4 |", and the bold TOTAL row.
EPIC_ROW = re.compile(
    r"^\|\s*(?:\*\*)?(E-[A-Z]+|Total)(?:\*\*)?\s*\|[^|]*\|"
    r"\s*(?:\*\*)?(\d+)(?:\*\*)?\s*\|\s*(?:\*\*)?(\d+)(?:\*\*)?\s*\|"
    r"\s*(?:\*\*)?(\d+)(?:\*\*)?\s*\|\s*(?:\*\*)?(\d+)(?:\*\*)?\s*\|"
    r"\s*(?:\*\*)?(\d+)(?:\*\*)?\s*\|",
    re.IGNORECASE,
)
# "| P1 | 435 | Live at launch...", and the bold total row.
SCOPE_ROW = re.compile(r"^\|\s*(?:\*\*)?(P1|P1-L|P1-E|DLV|Total)(?:\*\*)?\s*\|\s*(?:\*\*)?(\d+)(?:\*\*)?\s*\|")


def authoritative(repo: Path) -> dict:
    """Delivery-row counts per scope class, from the generated extraction."""
    data = json.loads((repo / "docs" / "scope" / "register-ids.json").read_text(encoding="utf-8"))
    counts = {s: 0 for s in DELIVERY}
    for row in data["ids"].values():
        if row["scope"] in counts:
            counts[row["scope"]] += 1
    return counts


def check(repo: Path = ROOT) -> list:
    """Every disagreement between the backlog document's tables and the extraction, as readable lines."""
    errs = []
    want = authoritative(repo)
    want_total = sum(want.values())

    docs = sorted(repo.glob(DOC_GLOB))
    if not docs:
        return [f"no backlog document matching {DOC_GLOB}"]

    for doc in docs:
        rel = doc.relative_to(repo).as_posix()
        lines = doc.read_text(encoding="utf-8").splitlines()

        scope_said, scope_total = {}, None
        for line in lines:
            m = SCOPE_ROW.match(line)
            if not m:
                continue
            label, n = m.group(1), int(m.group(2))
            if label.lower() == "total":
                scope_total = n if scope_total is None else scope_total
            else:
                scope_said.setdefault(label, n)
        for scope in DELIVERY:
            if scope not in scope_said:
                errs.append(f"{rel}: the scope table has no row for {scope}")
            elif scope_said[scope] != want[scope]:
                errs.append(f"{rel}: scope table says {scope} {scope_said[scope]}, extraction says {want[scope]}")
        if scope_total is None:
            errs.append(f"{rel}: the scope table has no Total row")
        elif scope_total != want_total:
            errs.append(f"{rel}: scope table total {scope_total}, extraction says {want_total}")

        epics, stated_total = [], None
        for line in lines:
            m = EPIC_ROW.match(line)
            if not m:
                continue
            cols = [int(m.group(i)) for i in (2, 3, 4, 5)]
            row_total = int(m.group(6))
            if m.group(1).lower() == "total":
                stated_total = (cols, row_total)
            else:
                epics.append((m.group(1), cols, row_total))

        if not epics:
            errs.append(f"{rel}: no epic rows found; has the table format changed?")
            continue

        for label, cols, row_total in epics:
            if sum(cols) != row_total:
                errs.append(f"{rel}: epic {label} columns sum to {sum(cols)} but its Total column says {row_total}")

        summed = [sum(c[i] for _, c, _ in epics) for i in range(4)]
        for scope, got in zip(DELIVERY, summed):
            if got != want[scope]:
                errs.append(f"{rel}: epic rows sum to {got} for {scope}, extraction says {want[scope]}")
        if sum(summed) != want_total:
            errs.append(f"{rel}: epic rows sum to {sum(summed)} delivery rows, extraction says {want_total}")

        if stated_total is None:
            errs.append(f"{rel}: the epic table has no Total row")
        else:
            cols, row_total = stated_total
            for scope, said, got in zip(DELIVERY, cols, summed):
                if said != got:
                    errs.append(f"{rel}: epic table Total row says {scope} {said}, its own rows sum to {got}")
            if row_total != want_total:
                errs.append(f"{rel}: epic table Total row says {row_total}, extraction says {want_total}")

    return errs


def tree(dest: Path) -> Path:
    """A copy of the parts of the repository check() reads."""
    (dest / "docs" / "scope").mkdir(parents=True)
    shutil.copy(ROOT / "docs" / "scope" / "register-ids.json", dest / "docs" / "scope")
    shutil.copy(ROOT / DOC, dest / "docs")
    return dest


class BacklogTotals(unittest.TestCase):
    def test_current_tree_passes(self):
        self.assertEqual(check(ROOT), [])

    def test_authoritative_counts_come_from_the_extraction(self):
        ids = json.loads((ROOT / "docs" / "scope" / "register-ids.json").read_text(encoding="utf-8"))["ids"]
        self.assertEqual(authoritative(ROOT),
                         {s: sum(1 for r in ids.values() if r["scope"] == s) for s in DELIVERY})

    def test_the_pre_series_holds_ten_ids(self):
        # PRE-03 is split into PRE-03a and PRE-03b, which is what made an earlier hand count say nine.
        ids = json.loads((ROOT / "docs" / "scope" / "register-ids.json").read_text(encoding="utf-8"))["ids"]
        pre = sorted(k for k, v in ids.items() if k.startswith("PRE-") and v["scope"] == "DLV")
        self.assertEqual(len(pre), 10, pre)
        self.assertIn("PRE-03a", pre)
        self.assertIn("PRE-03b", pre)

    def test_a_wrong_epic_column_fails(self):
        with tempfile.TemporaryDirectory() as d:
            root = tree(Path(d))
            doc = root / DOC
            doc.write_text(doc.read_text(encoding="utf-8").replace(
                "| 0 | 0 | 0 | 10 | 10 | - |", "| 0 | 0 | 0 | 9 | 9 | - |"), encoding="utf-8")
            errs = check(root)
            self.assertTrue(any("DLV, extraction says 24" in e for e in errs), errs)
            self.assertTrue(any("594" in e for e in errs), errs)

    def test_a_row_whose_columns_do_not_sum_fails(self):
        with tempfile.TemporaryDirectory() as d:
            root = tree(Path(d))
            doc = root / DOC
            doc.write_text(doc.read_text(encoding="utf-8").replace(
                "| 0 | 0 | 0 | 10 | 10 | - |", "| 0 | 0 | 0 | 10 | 11 | - |"), encoding="utf-8")
            self.assertTrue(any("columns sum to 10 but its Total column says 11" in e for e in check(root)))

    def test_a_wrong_scope_summary_fails(self):
        with tempfile.TemporaryDirectory() as d:
            root = tree(Path(d))
            doc = root / DOC
            doc.write_text(doc.read_text(encoding="utf-8").replace("| P1 | 435 |", "| P1 | 430 |"), encoding="utf-8")
            self.assertTrue(any("scope table says P1 430" in e for e in check(root)))

    def test_a_reissued_register_fails_until_the_tables_follow(self):
        with tempfile.TemporaryDirectory() as d:
            root = tree(Path(d))
            p = root / "docs" / "scope" / "register-ids.json"
            data = json.loads(p.read_text(encoding="utf-8"))
            data["ids"]["ZZZ-01"] = {"scope": "P1", "stage": "S1", "key": False, "obligation": True}
            p.write_text(json.dumps(data), encoding="utf-8")
            self.assertTrue(any("extraction says 436" in e for e in check(root)))

    def test_a_missing_document_is_reported(self):
        with tempfile.TemporaryDirectory() as d:
            (Path(d) / "docs" / "scope").mkdir(parents=True)
            shutil.copy(ROOT / "docs" / "scope" / "register-ids.json", Path(d) / "docs" / "scope")
            self.assertTrue(any("no backlog document" in e for e in check(Path(d))))


if __name__ == "__main__":
    unittest.main()

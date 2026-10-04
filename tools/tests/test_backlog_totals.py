"""The Option B backlog document's row counts must match the machine-readable scope extraction.

The backlog document (docs/<date>-option-b-backlog-structure.md) carries two hand-written tables of counts: a
scope summary, one row per scope class, and an epic table, one row per register section plus a TOTAL row. Both can
drift from docs/scope/register-ids.json, which is generated. A drift of one row went unnoticed once already: the
pre-development deliverables are labelled PRE-01 to PRE-09, which reads as nine, but the register splits PRE-03
into PRE-03a and PRE-03b, so the series holds ten ids. The hand count of nine made the epic table total 594
against an authoritative 595.

It also checks the document's **ownership map**, which assigns every delivery row to the slice that would own it.
That map exists because range shorthand (`IA-01 to IA-36`) is not a scope citation: the IA section holds five
non-delivery rows and two rows the register stages S2, and citing the range swept all seven into a Stage 1 PBI.
The map is checked for four things the first fifteen PBIs got wrong: a cited id that is not a delivery row, a
delivery row left with no owner, a range used where an id belongs, and a seeded slice that disagrees with the live
board. Three rows are deliberately cited by two PBIs; a fourth fails.

check() recomputes everything from register-ids.json and the board document and compares it with what the backlog
document says, so a reissued register or an edited table fails here instead of being believed. It reads; it never
writes.
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

# "- **E-FND-1 Bilingual platform baseline** (#241) (5 ids): `FIX-04, FIX-04a, NFR-04, NFR-04a, NFR-14`"
OWNED = re.compile(r"^- \*\*([^*]+)\*\*(?:\s*\(#(\d+)\))?\s*\((\d+) ids?\):\s*`([^`]+)`")
# the board document's own appendix, the live record of what each seeded PBI cites
BOARD = re.compile(r"^- \*\*#(\d+)\*\*\s*\((\d+) ids?\):\s*`([^`]+)`")
BOARD_GLOB = "docs/*-github-project-proposal.md"
SINGLE_ID = re.compile(r"^[A-Z]{2,6}-\d{1,3}[a-z]?$")
# One row, one accepting owner, with no exception. FIX-04, NFR-04 and ERP-10 were each cited by two PBIs until
# 4 October 2026, when the second citation became a Dependencies entry on the live board. Nothing is exempt now,
# and the empty set is the point: a row with two owners leaves the board unable to say which acceptance closed it.
CITED_TWICE: set[str] = set()


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

        errs.extend(ownership(repo, doc))

    return errs


def board_appendix(repo: Path) -> dict:
    """issue number -> the exact ids the live board record says that PBI cites."""
    docs = sorted(repo.glob(BOARD_GLOB))
    if not docs:
        return {}
    out = {}
    for line in docs[0].read_text(encoding="utf-8").splitlines():
        m = BOARD.match(line)
        if m:
            out[int(m.group(1))] = [x.strip() for x in m.group(3).split(",") if x.strip()]
    return out


def ownership(repo: Path, doc: Path) -> list:
    """Every disagreement between the document's ownership map, the extraction and the live board record."""
    errs = []
    rel = doc.relative_to(repo).as_posix()
    ids = json.loads((repo / "docs" / "scope" / "register-ids.json").read_text(encoding="utf-8"))["ids"]
    delivery = {i for i, row in ids.items() if row["scope"] in DELIVERY}

    slices, owners = [], {}
    for line in doc.read_text(encoding="utf-8").splitlines():
        m = OWNED.match(line)
        if not m:
            continue
        name, issue, claimed, listed = m.group(1).strip(), m.group(2), int(m.group(3)), m.group(4)
        cited = [x.strip() for x in listed.split(",") if x.strip()]
        slices.append((name, int(issue) if issue else None, cited))
        if len(cited) != claimed:
            errs.append(f"{rel}: {name} says {claimed} ids and lists {len(cited)}")
        for i in cited:
            if not SINGLE_ID.match(i):
                errs.append(f"{rel}: {name} cites {i!r}, which is not a single register id. A range is not a "
                            "scope citation")
            elif i not in ids:
                errs.append(f"{rel}: {name} cites {i}, which is not a register id")
            elif i not in delivery:
                errs.append(f"{rel}: {name} cites {i}, which the register scopes {ids[i]['scope']}, "
                            "not a delivery row")
            owners.setdefault(i, []).append(name)

    if not slices:
        return [f"{rel}: no ownership map found; has its format changed?"]

    unowned = sorted(delivery - set(owners))
    if unowned:
        errs.append(f"{rel}: {len(unowned)} delivery rows have no owner, so removing them from one slice left "
                    f"them nowhere: {', '.join(unowned)}")

    for i, who in sorted(owners.items()):
        if len(who) > 1 and i not in CITED_TWICE:
            errs.append(f"{rel}: {i} is cited by {len(who)} slices ({', '.join(who)}). One row, one accepting "
                        "owner; a second slice names it as a dependency")

    board = board_appendix(repo)
    for name, issue, cited in slices:
        if issue is None:
            continue
        if issue not in board:
            errs.append(f"{rel}: {name} claims to be PBI #{issue}, which the board record does not list")
            continue
        if sorted(cited) != sorted(board[issue]):
            errs.append(f"{rel}: {name} cites {sorted(cited)} but the board record says #{issue} cites "
                        f"{sorted(board[issue])}. The board is the operational state")
    return errs


def tree(dest: Path) -> Path:
    """A copy of the parts of the repository check() reads."""
    (dest / "docs" / "scope").mkdir(parents=True)
    shutil.copy(ROOT / "docs" / "scope" / "register-ids.json", dest / "docs" / "scope")
    shutil.copy(ROOT / DOC, dest / "docs")
    for board in ROOT.glob(BOARD_GLOB):
        shutil.copy(board, dest / "docs")
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

    # ---- the ownership map ------------------------------------------------------------------------------
    def test_the_map_accounts_for_every_delivery_row(self):
        """The point of the map: 595 rows, every one of them with a named owner."""
        ids = json.loads((ROOT / "docs" / "scope" / "register-ids.json").read_text(encoding="utf-8"))["ids"]
        delivery = {i for i, row in ids.items() if row["scope"] in DELIVERY}
        owned = set()
        for line in (ROOT / DOC).read_text(encoding="utf-8").splitlines():
            m = OWNED.match(line)
            if m:
                owned.update(x.strip() for x in m.group(4).split(",") if x.strip())
        self.assertEqual(owned, delivery, "the ownership map and the register disagree about the delivery rows")
        self.assertEqual(len(owned), 595)

    def test_removing_a_row_from_a_slice_leaves_it_detectably_unowned(self):
        """A row taken out of one slice must be given to another, not dropped out of the backlog."""
        with tempfile.TemporaryDirectory() as d:
            root = tree(Path(d))
            doc = root / DOC
            doc.write_text(doc.read_text(encoding="utf-8").replace(
                "(2 ids): `IA-23, IA-33`", "(1 ids): `IA-23`"), encoding="utf-8")
            errs = check(root)
            self.assertTrue(any("IA-33" in e and "no owner" in e for e in errs), errs)

    def test_a_range_is_not_a_scope_citation(self):
        with tempfile.TemporaryDirectory() as d:
            root = tree(Path(d))
            doc = root / DOC
            doc.write_text(doc.read_text(encoding="utf-8").replace(
                "(10 ids): `NAV-01, NAV-02, NAV-03, NAV-04, NAV-05, NAV-06, NAV-07, NAV-08, NAV-09, NAV-10`",
                "(10 ids): `NAV-01 to NAV-10`"), encoding="utf-8")
            errs = check(root)
            self.assertTrue(any("not a single register id" in e for e in errs), errs)

    def test_a_non_delivery_row_cannot_be_owned(self):
        """IA-36 is P2. Citing the IA range swept it into a PBI once already."""
        with tempfile.TemporaryDirectory() as d:
            root = tree(Path(d))
            doc = root / DOC
            doc.write_text(doc.read_text(encoding="utf-8").replace(
                "(2 ids): `IA-23, IA-33`", "(3 ids): `IA-23, IA-33, IA-36`"), encoding="utf-8")
            errs = check(root)
            self.assertTrue(any("IA-36" in e and "P2" in e for e in errs), errs)

    def test_the_three_rows_cited_twice_are_still_exactly_three(self):
        """If the board is corrected, this fails and CITED_TWICE is narrowed. It must not drift silently."""
        owners = {}
        for line in (ROOT / DOC).read_text(encoding="utf-8").splitlines():
            m = OWNED.match(line)
            if m:
                for i in (x.strip() for x in m.group(4).split(",") if x.strip()):
                    owners.setdefault(i, 0)
                    owners[i] += 1
        self.assertEqual({i for i, n in owners.items() if n > 1}, CITED_TWICE)

    def test_a_fourth_row_cited_twice_fails(self):
        with tempfile.TemporaryDirectory() as d:
            root = tree(Path(d))
            doc = root / DOC
            doc.write_text(doc.read_text(encoding="utf-8").replace(
                "(1 ids): `RET-08`", "(2 ids): `RET-08, NAV-01`"), encoding="utf-8")
            errs = check(root)
            self.assertTrue(any("NAV-01 is cited by 2 slices" in e for e in errs), errs)

    def test_a_seeded_slice_must_match_the_live_board(self):
        """The board is the operational state. The document records it, never the reverse."""
        with tempfile.TemporaryDirectory() as d:
            root = tree(Path(d))
            doc = root / DOC
            doc.write_text(doc.read_text(encoding="utf-8").replace(
                "(#241) (5 ids): `FIX-04, FIX-04a, NFR-04, NFR-04a, NFR-14`",
                "(#241) (4 ids): `FIX-04, FIX-04a, NFR-04, NFR-04a`"), encoding="utf-8")
            errs = check(root)
            self.assertTrue(any("#241 cites" in e and "board record" in e for e in errs), errs)

    def test_a_slice_claiming_an_unknown_pbi_fails(self):
        with tempfile.TemporaryDirectory() as d:
            root = tree(Path(d))
            doc = root / DOC
            doc.write_text(doc.read_text(encoding="utf-8").replace("(#241) (5 ids):", "(#999) (5 ids):"),
                           encoding="utf-8")
            errs = check(root)
            self.assertTrue(any("#999" in e for e in errs), errs)

    def test_a_missing_document_is_reported(self):
        with tempfile.TemporaryDirectory() as d:
            (Path(d) / "docs" / "scope").mkdir(parents=True)
            shutil.copy(ROOT / "docs" / "scope" / "register-ids.json", Path(d) / "docs" / "scope")
            self.assertTrue(any("no backlog document" in e for e in check(Path(d))))


if __name__ == "__main__":
    unittest.main()

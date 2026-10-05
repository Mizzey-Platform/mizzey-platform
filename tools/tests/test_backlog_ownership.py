"""Every delivery row of the Feature Register has exactly one accepting owner, and Stage 1 has no gap.

Why this exists. On 5 October 2026 the backlog held fifteen PBIs, and three of them cited rows whose register
wording was not their outcome: #244, an infrastructure PBI, cited the technical design, the stack proposal, the
sitemap and the interface design; #250, the design PBI, cited the store operations walkthrough and the functional
specification; #249, the import specification, cited the integrations list and the register itself. Every one of
those ids was a real delivery row, so the existing checks passed. What was missing was a single machine-readable
statement of who owns each row, checked against the register itself.

`docs/scope/backlog-ownership.json` is that statement. This file checks it against
`docs/scope/register-ids.json`, the extraction of the signed register, and against the generated ownership map in
the backlog structure record. It cannot check that a row's wording matches a slice's outcome: that is a review
judgement, recorded in `docs/2026-10-05-stage1-ownership-audit.md`.
"""

import json
import re
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DELIVERY = {"P1", "P1-L", "P1-E", "DLV"}
SCOPE_ORDER = {"P1": 0, "P1-L": 1, "P1-E": 2, "DLV": 3}
CLASSES = {
    "Blocks development now", "Blocks final acceptance", "Blocks production or launch only",
    "Content or client input", "Vendor or account input", "ERP input", "Configurable working default",
}
# What a PBI with no undelivered predecessor may be, short of Verified.
STARTABLE = {"Ready", "In progress", "In review"}
SINGLE_ID = re.compile(r"^[A-Z]{2,6}-\d{1,3}[a-z]?$")
OWNED = re.compile(r"^- \*\*(\S+) [^*]*\*\*(?:\s*\(#(\d+)\))?\s*\((\d+) ids?\):\s*`([^`]+)`")


def load(name):
    return json.loads((ROOT / "docs" / "scope" / name).read_text(encoding="utf-8"))


def audit(slices: list, ids: dict) -> dict:
    """The ownership audit, as data. Every list is empty when the backlog is sound."""
    owners: dict[str, list[str]] = {}
    for s in slices:
        for i in s["ids"]:
            owners.setdefault(i, []).append(s["key"])
    delivery = sorted(i for i, v in ids.items() if v["scope"] in DELIVERY)
    s1 = [i for i in delivery if ids[i]["stage"] == "S1"]
    seeded = [s for s in slices if s.get("issue")]
    seeded_ids = {i for s in seeded for i in s["ids"]}

    def scope_of(rows):
        found = sorted({ids[i]["scope"] for i in rows if i in ids}, key=lambda x: SCOPE_ORDER.get(x, 9))
        return found[0] if len(found) == 1 else "mixed"

    def stage_of(rows):
        found = sorted({ids[i]["stage"] for i in rows if i in ids})
        return found[0] if len(found) == 1 else "mixed"

    return {
        "delivery_rows": len(delivery),
        "s1_rows": len(s1),
        "unknown_ids": sorted(i for i in owners if i not in ids),
        "not_single_ids": sorted(i for i in owners if not SINGLE_ID.match(i)),
        "non_delivery_owned": sorted(i for i in owners if i in ids and ids[i]["scope"] not in DELIVERY),
        "orphans": [i for i in delivery if i not in owners],
        "duplicates": {i: k for i, k in owners.items() if len(k) > 1},
        "s1_orphans": [i for i in s1 if i not in owners],
        "s1_without_a_pbi": [i for i in s1 if i not in seeded_ids],
        "s1_pbi_citing_another_stage": sorted(
            (s["key"], i) for s in slices if s["stage"] == "S1" for i in s["ids"] if i in ids and ids[i]["stage"] != "S1"),
        "second_release_rows_on_a_pbi": sorted(i for i in seeded_ids if i in ids and ids[i]["stage"] == "S2"),
        "p1e_without_a_pbi": sorted(i for i in delivery if ids[i]["scope"] == "P1-E" and i not in seeded_ids),
        "wrong_scope_class": sorted(s["key"] for s in slices if s["scope_class"] != scope_of(s["ids"])),
        "wrong_stage": sorted(s["key"] for s in slices if s["stage"] != stage_of(s["ids"])),
    }


class Ownership(unittest.TestCase):
    def setUp(self):
        self.data = load("backlog-ownership.json")
        self.slices = self.data["slices"]
        self.reg = load("register-ids.json")
        self.ids = self.reg["ids"]
        self.audit = audit(self.slices, self.ids)

    def test_the_file_is_checked_against_the_register_it_names(self):
        self.assertEqual(self.data["register"]["sha256"], self.reg["source"]["sha256"],
                         "the ownership file was built against a different register than the one extracted")

    def test_every_owned_id_is_a_real_delivery_row_named_exactly(self):
        self.assertEqual(self.audit["unknown_ids"], [])
        self.assertEqual(self.audit["not_single_ids"], [], "a range or a phrase is not a citation")
        self.assertEqual(self.audit["non_delivery_owned"], [], "DEF, P2, P3 and OUT rows create no obligation")

    def test_no_delivery_row_is_without_an_owner(self):
        self.assertEqual(self.audit["orphans"], [])

    def test_no_delivery_row_has_two_owners(self):
        self.assertEqual(self.audit["duplicates"], {})

    def test_every_stage_one_row_is_owned_by_a_pbi_that_exists(self):
        self.assertEqual(self.audit["s1_orphans"], [])
        self.assertEqual(self.audit["s1_without_a_pbi"], [],
                         "a Stage 1 row is owned by a slice with no issue, so development would meet a missing PBI")

    def test_a_stage_one_pbi_cites_only_stage_one_rows(self):
        self.assertEqual(self.audit["s1_pbi_citing_another_stage"], [])

    def test_no_second_release_row_sits_on_a_created_pbi(self):
        self.assertEqual(self.audit["second_release_rows_on_a_pbi"], [])

    def test_every_erp_dependent_row_has_an_owner_while_pre09_is_unapproved(self):
        self.assertEqual(self.audit["p1e_without_a_pbi"], [])
        gates = load("open-items.json")["gates"]
        self.assertIs(gates["PRE-09"]["approved"], False,
                      "PRE-09 is recorded as approved: P1-E ownership no longer needs to stand in for acceptance")
        for s in self.slices:
            if s.get("issue") and any(self.ids[i]["scope"] == "P1-E" for i in s["ids"]):
                with self.subTest(slice=s["key"]):
                    self.assertNotEqual(s["erp_blocked"], "no", "a PBI that owns a P1-E row depends on PRE-09")

    def test_scope_class_and_stage_are_computed_from_the_rows(self):
        self.assertEqual(self.audit["wrong_scope_class"], [])
        self.assertEqual(self.audit["wrong_stage"], [])

    def test_delivery_order_is_scheduling_only(self):
        created = [s for s in self.slices if s.get("issue")]
        orders = sorted(s["delivery_order"] for s in created)
        self.assertEqual(orders, list(range(1, len(created) + 1)), "delivery order is not one contiguous sequence")
        for s in self.slices:
            if not s.get("issue"):
                with self.subTest(slice=s["key"]):
                    self.assertIsNone(s["delivery_order"], "a slice that is not created is not scheduled")
        # Scheduling never restates a stage: an early order does not make a row Stage 1, and the stage of every
        # slice is asserted from the register above, independently of where it sits in the queue.

    def test_a_blocked_pbi_names_a_predecessor_and_nothing_else(self):
        delivered = {s["issue"] for s in self.slices if s.get("status") == "Verified"}
        for s in self.slices:
            if not s.get("issue") or s["status"] == "Verified":
                continue
            waiting = [d["ref"] for d in s.get("depends_on", [])
                       if d.get("blocking") and int(d["ref"].lstrip("#")) not in delivered]
            with self.subTest(slice=s["key"]):
                # A PBI whose predecessors are delivered is Ready, or has been started: In progress or In review
                # (D-12: #243 stays in progress awaiting ERP input, #244 stays open for production).
                if waiting:
                    self.assertEqual(s["status"], "Blocked",
                                     "a PBI with an undelivered predecessor is Blocked, whatever else is true")
                else:
                    self.assertIn(s["status"], STARTABLE,
                                  "Blocked means a predecessor PBI is not delivered, and only that (D-10)")

    def test_every_dependency_names_a_pbi_that_exists(self):
        numbers = {s["issue"] for s in self.slices if s.get("issue")}
        for s in self.slices:
            for d in s.get("depends_on", []):
                if s.get("issue"):
                    with self.subTest(slice=s["key"], ref=d["ref"]):
                        self.assertRegex(d["ref"], r"^#\d+$", "a created PBI depends on issues, not on slice keys")
                        self.assertIn(int(d["ref"][1:]), numbers)

    def test_every_input_carries_one_of_the_seven_classes(self):
        for s in self.slices:
            for i in s.get("inputs", []):
                with self.subTest(slice=s["key"], input=i["id"]):
                    self.assertIn(i["class"], CLASSES)

    def test_no_predecessor_cycle(self):
        graph = {s["issue"]: [int(d["ref"][1:]) for d in s.get("depends_on", []) if d.get("blocking")]
                 for s in self.slices if s.get("issue")}
        state: dict[int, int] = {}

        def visit(n):
            if state.get(n) == 1:
                return [n]
            if state.get(n) == 2:
                return None
            state[n] = 1
            for m in graph.get(n, []):
                loop = visit(m)
                if loop:
                    return [n] + loop
            state[n] = 2
            return None

        for n in graph:
            self.assertIsNone(visit(n), "predecessors form a cycle, so nothing in it could ever start")

    def test_the_generated_ownership_map_says_what_the_file_says(self):
        doc = next(iter(sorted(ROOT.glob("docs/*-option-b-backlog-structure.md")))).read_text(encoding="utf-8")
        in_doc = {}
        for line in doc.splitlines():
            m = OWNED.match(line)
            if m:
                in_doc[m.group(1)] = (int(m.group(2)) if m.group(2) else None,
                                      [x.strip() for x in m.group(4).split(",")])
        in_file = {s["key"]: (s.get("issue"), s["ids"]) for s in self.slices}
        self.assertEqual(in_doc, in_file, "regenerate the record with tools/gen_backlog_docs.py")


class AuditBites(unittest.TestCase):
    """Each fault the audit exists to catch, introduced on purpose."""

    def setUp(self):
        self.slices = json.loads(json.dumps(load("backlog-ownership.json")["slices"]))
        self.ids = load("register-ids.json")["ids"]

    def slice_holding(self, row):
        return next(s for s in self.slices if row in s["ids"])

    def test_a_removed_row_is_an_orphan(self):
        self.slice_holding("RPT-10")["ids"].remove("RPT-10")
        self.assertIn("RPT-10", audit(self.slices, self.ids)["orphans"])

    def test_a_row_cited_twice_is_a_duplicate(self):
        self.slice_holding("PRE-03b")["ids"].append("RPT-10")
        self.assertIn("RPT-10", audit(self.slices, self.ids)["duplicates"])

    def test_a_deferred_row_cannot_be_owned(self):
        self.assertEqual(self.ids["ROLE-06"]["scope"], "DEF")
        self.slice_holding("ROLE-09")["ids"].append("ROLE-06")
        self.assertIn("ROLE-06", audit(self.slices, self.ids)["non_delivery_owned"])

    def test_a_second_release_row_on_a_stage_one_pbi_is_caught(self):
        self.assertEqual(self.ids["JRN-11"]["stage"], "S2")
        self.slice_holding("JRN-01")["ids"].append("JRN-11")
        found = audit(self.slices, self.ids)
        self.assertIn("JRN-11", found["second_release_rows_on_a_pbi"])
        self.assertTrue(found["wrong_stage"])

    def test_a_stage_one_row_on_an_uncreated_slice_is_caught(self):
        self.slice_holding("RPT-10")["issue"] = None
        self.assertIn("RPT-10", audit(self.slices, self.ids)["s1_without_a_pbi"])

    def test_a_scope_class_chosen_by_hand_is_caught(self):
        s = self.slice_holding("RPT-10")
        s["scope_class"] = "P1"
        self.assertIn(s["key"], audit(self.slices, self.ids)["wrong_scope_class"])

    def test_a_range_is_not_an_id(self):
        self.slice_holding("RPT-10")["ids"].append("ADM-01 to ADM-09")
        self.assertTrue(audit(self.slices, self.ids)["not_single_ids"])


if __name__ == "__main__":
    unittest.main()

"""The design inventory holds to the signed scope, and the checker refuses what it must.

The first class runs the real inventory. The second builds small broken inventories and checks each is refused,
so a rule cannot be removed from the checker without a test noticing.
"""

from __future__ import annotations

import copy
import unittest
from pathlib import Path

from tools import design_inventory as di

ROOT = Path(__file__).resolve().parent.parent.parent


class TheCommittedInventory(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.data = di.load(ROOT)

    def test_it_passes_every_check(self):
        self.assertEqual(di.validate(self.data), [])

    def test_every_delivery_row_is_accounted_for(self):
        rows = di.classify_rows(self.data)
        self.assertEqual(len(rows), sum(1 for r in self.data["ids"].values() if r["obligation"]))
        self.assertEqual([r for r, c in rows.items() if c["how"] == "uncovered"], [])

    def test_the_coverage_report_is_current(self):
        report = (ROOT / "design" / "coverage.md").read_text(encoding="utf-8")
        self.assertEqual(report, di.render(self.data),
                         "design/coverage.md is stale; run python tools/design_inventory.py --write")

    def test_no_wireframe_and_no_brand_value_has_been_committed(self):
        """D-14: the first wireframe waits for the coverage review, and tokens stay neutral until OD-01."""
        for folder in ("wireframes", "briefs", "tokens"):
            found = sorted(p.name for p in (ROOT / "design" / folder).iterdir())
            self.assertEqual(found, ["README.md"], f"design/{folder}/ holds more than its README")
        self.assertTrue(all(c["brand"] == "neutral" for c in self.data["components"]))

    def test_the_signed_documents_are_referenced_and_not_copied(self):
        for src in self.data["sources"]:
            if src["authority_rank"] <= 4:
                self.assertTrue(src["source"].startswith("../../final docs/"), src["id"])
        committed = {p.suffix for p in (ROOT / "design").rglob("*") if p.is_file()}
        self.assertEqual(committed, {".md", ".json"})

    def test_option_c_is_the_lowest_authority_and_is_named_as_history(self):
        last = max(self.data["sources"], key=lambda s: s["authority_rank"])
        self.assertEqual(last["id"], "OPTION-C")
        self.assertIn("Never scope", last["role"])


def surface(**over) -> dict:
    s = {"id": "x-cart", "name": "Cart", "kind": "page", "scope_type": "contracted", "audience": ["customer"],
         "register_ids": ["CART-01"], "context_ids": [], "excluded_ids": [], "owner_pbi": 306, "stage": "S1",
         "route": None, "flow_steps": ["F1.1"], "acceptance": {"stories": [], "scenarios": [], "operations_checks": []},
         "states": [{"state": "default"}], "responsive": {"mobile": "a", "tablet": "b", "desktop": "c"},
         "rtl": {"applies": True, "mirrors": ["layout"], "does_not_mirror": ["numerals"]}, "native_baseline": "",
         "design_freedom": "free", "open_items": [], "erp_dependency": "none", "status": "inventoried"}
    s.update(over)
    return s


IDS = {
    "CART-01": {"scope": "P1", "stage": "S1", "obligation": True},
    "CART-02": {"scope": "P1", "stage": "S1", "obligation": True},
    "PLP-11": {"scope": "P1-L", "stage": "S2", "obligation": True},
    "NAV-01": {"scope": "P1", "stage": "S1", "obligation": True},
    "AC-01": {"scope": "P1", "stage": "S1", "obligation": True},
    "WISH-05": {"scope": "P2", "stage": "-", "obligation": False},
    "ACCT-10": {"scope": "DEF", "stage": "-", "obligation": False},
}
SLICES = [{"issue": 306, "key": "E-SF-6", "title": "Cart", "ids": ["CART-01", "CART-02"], "design_dependency": "needs design"},
          {"issue": 253, "key": "E-SF-1", "title": "Header", "ids": ["NAV-01"], "design_dependency": "none"},
          {"issue": None, "key": "E-SF-3b", "title": "Rating filter", "ids": ["PLP-11"], "design_dependency": "none"},
          {"issue": 323, "key": "E-ACC-1", "title": "Scenarios", "ids": ["AC-01"], "design_dependency": "none"}]


def inventory(*surfaces, no_surface=None, flows=None, components=None, placeholders=None) -> dict:
    return {"surfaces": list(surfaces),
            "no_surface": no_surface if no_surface is not None else
            {"behaviour": {"reason": "No screen.", "ids": ["CART-02", "PLP-11", "NAV-01", "AC-01"]}},
            "flows": flows if flows is not None else
            [{"id": "F1", "steps": [{"step": "F1.1", "surfaces": ["x-cart"], "register_ids": ["CART-01"]}]}],
            "components": components or [], "placeholders": placeholders or [], "sources": [],
            "ids": copy.deepcopy(IDS), "slices": SLICES}


class TheCheckerRefuses(unittest.TestCase):
    def errors(self, data, *, drop=di.EMAIL_ROWS):
        # The fixture register has no email rows: that rule is exercised on its own below.
        return [e for e in di.validate(data) if not any(e.startswith(r + " ") for r in drop)]

    def assertRefused(self, data, text):
        errs = self.errors(data)
        self.assertTrue(any(text in e for e in errs), f"expected {text!r} in {errs}")

    def test_a_sound_inventory_passes(self):
        self.assertEqual(self.errors(inventory(surface())), [])

    def test_a_contracted_surface_with_no_row(self):
        self.assertRefused(inventory(surface(register_ids=[], stage=None)), "backed by no delivery row")

    def test_a_deferred_row_as_a_launch_obligation(self):
        self.assertRefused(inventory(surface(register_ids=["CART-01", "ACCT-10"])), "creates no launch obligation")

    def test_a_later_phase_row_as_context(self):
        self.assertRefused(inventory(surface(context_ids=["WISH-05"])), "creates no launch obligation")

    def test_a_delivery_row_listed_as_excluded(self):
        self.assertRefused(inventory(surface(excluded_ids=["CART-02"])), "cannot be listed as excluded")

    def test_a_row_another_pbi_owns(self):
        self.assertRefused(inventory(surface(register_ids=["CART-01", "NAV-01"])), "is owned by 253, not by 306")

    def test_a_surface_outside_scope_that_cites_a_row(self):
        s = surface(scope_type="native_required", owner_pbi=None, scope_note="Every site has one.")
        self.assertRefused(inventory(s), "surfaces cite no register row")

    def test_a_surface_outside_scope_that_does_not_say_why_it_exists(self):
        s = surface(scope_type="internal_operational", register_ids=[], stage=None, owner_pbi=None)
        self.assertRefused(inventory(s, no_surface={"behaviour": {"reason": "No screen.",
                                                                  "ids": ["CART-01", "CART-02", "PLP-11", "NAV-01", "AC-01"]}}),
                           "must say why they exist")

    def test_a_provider_surface_needs_no_reading_direction_of_ours(self):
        s = surface(scope_type="provider_hosted", register_ids=[], stage=None, owner_pbi=None, scope_note="Theirs.",
                    rtl={"applies": False, "reason": "The provider's."})
        data = inventory(s, no_surface={"behaviour": {"reason": "No screen.",
                                                      "ids": ["CART-01", "CART-02", "PLP-11", "NAV-01", "AC-01"]}})
        self.assertEqual(self.errors(data), [])

    def test_a_customer_surface_that_ignores_arabic(self):
        self.assertRefused(inventory(surface(rtl={"applies": False, "reason": "later"})),
                           "English left to right and Arabic right to left")

    def test_a_stage_that_was_moved(self):
        self.assertRefused(inventory(surface(stage="S2")), "is not what its rows give")

    def test_a_flow_step_that_points_nowhere(self):
        flows = [{"id": "F1", "steps": [{"step": "F1.1", "surfaces": ["x-cart", "x-gone"], "register_ids": []}]}]
        self.assertRefused(inventory(surface(), flows=flows), "x-gone is not a surface")

    def test_flow_steps_that_drifted(self):
        self.assertRefused(inventory(surface(flow_steps=[])), "flow_steps differs from flows.json")

    def test_an_open_item_with_no_placeholder_rule(self):
        self.assertRefused(inventory(surface(open_items=["OD-01"])), "has no entry in placeholders.json")

    def test_a_placeholder_whose_surfaces_drifted(self):
        p = {"id": "OD-01", "affected_surfaces": [], "unknown": "a", "placeholder_allowed": "b", "must_not_assume": "c",
             "replaced_by": "d"}
        self.assertRefused(inventory(surface(open_items=["OD-01"]), placeholders=[p]), "affected_surfaces differs")

    def test_a_component_used_by_a_surface_that_does_not_exist(self):
        c = {"id": "c-x", "used_by": ["x-gone"], "register_ids": []}
        self.assertRefused(inventory(surface(), components=[c]), "used by x-gone")

    def test_a_delivery_row_nobody_accounted_for(self):
        data = inventory(surface(), no_surface={"behaviour": {"reason": "No screen.", "ids": ["CART-02", "NAV-01", "AC-01"]}})
        self.assertRefused(data, "PLP-11: on no surface and not marked no_surface")

    def test_a_row_marked_no_surface_and_drawn(self):
        data = inventory(surface(), no_surface={"behaviour": {"reason": "No screen.",
                                                              "ids": ["CART-01", "CART-02", "PLP-11", "NAV-01", "AC-01"]}})
        self.assertRefused(data, "CART-01: marked no_surface (behaviour) and also on x-cart")

    def test_a_no_surface_block_without_a_reason(self):
        data = inventory(surface(), no_surface={"behaviour": {"reason": "", "ids": ["CART-02", "PLP-11", "NAV-01", "AC-01"]}})
        self.assertRefused(data, "gives no reason")

    def test_an_email_row_no_email_surface_carries(self):
        errs = self.errors(inventory(surface()), drop=())
        self.assertTrue(any(e == "NOTF-02 names an email and no email surface carries it" for e in errs))

    def test_an_acceptance_scenario_counts_as_carried(self):
        data = inventory(surface(acceptance={"stories": [], "scenarios": ["AC-01"], "operations_checks": []}),
                         no_surface={"behaviour": {"reason": "No screen.", "ids": ["CART-02", "PLP-11", "NAV-01"]}})
        self.assertEqual(self.errors(data), [])
        self.assertEqual(di.classify_rows(data)["AC-01"]["how"], "via_surface")

    def test_a_missing_design_dependency_is_reported_and_not_an_error(self):
        s = surface(id="x-header", register_ids=["NAV-01"], owner_pbi=253, flow_steps=[])
        data = inventory(surface(), s, no_surface={"behaviour": {"reason": "No screen.", "ids": ["CART-02", "PLP-11", "AC-01"]}})
        self.assertEqual(self.errors(data), [])
        self.assertEqual([k for k, _, _ in di.dependency_findings(data)["missing"]], [253])


if __name__ == "__main__":
    unittest.main()

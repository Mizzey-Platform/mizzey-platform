"""The design inventory holds to the signed scope, and the checker refuses what it must.

The first class runs the real inventory. The second builds small broken inventories and checks each is refused,
so a rule cannot be removed from the checker without a test noticing.
"""

from __future__ import annotations

import copy
import json
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

    def test_four_design_questions_are_locked_owner_decisions(self):
        self.assertEqual([d["id"] for d in self.data["decisions"]], ["DQ-04", "DQ-05", "DQ-06", "DQ-07"])
        self.assertFalse([p["id"] for p in self.data["placeholders"] if p["id"].startswith("DQ-")])
        self.assertFalse([s["id"] for s in self.data["surfaces"] if any(i.startswith("DQ-") for i in s["open_items"])])
        for d in self.data["decisions"]:
            self.assertEqual((d["status"], d["decided_by"], d["client_confirmed"], d["adds_scope"]),
                             ("locked", "owner", False, False), d["id"])

    def test_numerals_list_continuation_and_checkout_are_provisional_and_select_nothing(self):
        """D-14: a working baseline for the first wireframes is not a requirement and not a final product decision."""
        by_id = {o["id"]: o for o in self.data["options"]}
        for oid, baseline in (("DQ-01", "Western digits 0 to 9 in both languages"), ("DQ-02", "Numbered pagination"),
                              ("DQ-03", "One page with clear sections")):
            o = by_id[oid]
            self.assertEqual((o["status"], o["working_baseline"], o["selected_pattern"]), ("provisional", baseline, None), oid)
            self.assertGreaterEqual(len(o["candidate_patterns"]), 3, oid)
        self.assertIn("Load More", by_id["DQ-02"]["candidate_patterns"])
        self.assertTrue(any("infinite" in c for c in by_id["DQ-02"]["candidate_patterns"]))
        self.assertTrue(any(c.startswith("Stepped checkout") for c in by_id["DQ-03"]["candidate_patterns"]))

    def test_no_interaction_choice_has_been_locked_before_any_wireframe_exists(self):
        self.assertEqual([o["id"] for o in self.data["options"] if o["status"] == "locked"], [])
        self.assertEqual([o["id"] for o in self.data["options"] if o["selected_pattern"] is not None], [])

    def test_no_surface_text_states_a_provisional_pattern_as_settled(self):
        text = json.dumps(self.data["surfaces"]) + json.dumps(self.data["components"])
        for phrase in ("No infinite scroll", "No multi-page wizard", "No wizard"):
            self.assertNotIn(phrase, text)

    def test_platform_surfaces_have_no_invented_build_pbi(self):
        by_id = {s["id"]: s for s in self.data["surfaces"]}
        for sid, origin in (("sy-404", "wordpress"), ("sy-maintenance", "wordpress"), ("sy-store-notices", "woocommerce"),
                            ("sy-sign-in-required", "woocommerce"), ("sy-coming-soon", "unknown")):
            self.assertIsNone(by_id[sid]["build_pbi"], sid)
            self.assertEqual(by_id[sid]["implementation_origin"], origin, sid)

    def test_the_five_customer_facing_builders_are_marked_as_needing_design(self):
        f = di.dependency_findings(self.data)
        self.assertEqual(f["missing"], [])
        marked = {(sl["issue"] or sl["key"]): sl["design_dependency"] for sl in self.data["slices"]}
        for pbi in (248, 283, 285, 288, 301):
            self.assertEqual(marked[pbi], "needs design", pbi)
        self.assertEqual(len(f["staff_only"]), 18)
        self.assertTrue(all(marked[k] == "none" for k, _, _ in f["staff_only"]))
        self.assertEqual(marked[277], "needs design")

    def test_second_release_surfaces_stay_inventoried_and_out_of_the_first_wireframe_pass(self):
        second = [s for s in self.data["surfaces"] if s["stage"] == "S2"]
        self.assertEqual(len(second), 8)
        for s in second:
            self.assertIn("DQ-04", s["decisions"], s["id"])
            self.assertEqual(s["status"], "inventoried", s["id"])

    def test_building_a_surface_elsewhere_leaves_its_rows_with_their_accepting_pbi(self):
        own = di.owners(self.data["slices"])
        differs = [s for s in self.data["surfaces"] if s["build_pbi"] not in (None, s["owner_pbi"])]
        self.assertTrue(differs)
        for s in differs:
            self.assertTrue(s["build_note"], s["id"])
            for r in s["register_ids"]:
                self.assertEqual(own[r], s["owner_pbi"], f"{s['id']}: {r}")
        received = next(s for s in self.data["surfaces"] if s["id"] == "ck-order-received")
        self.assertEqual((received["owner_pbi"], received["build_pbi"]), (242, 255))

    def test_option_c_is_the_lowest_authority_and_is_named_as_history(self):
        last = max(self.data["sources"], key=lambda s: s["authority_rank"])
        self.assertEqual(last["id"], "OPTION-C")
        self.assertIn("Never scope", last["role"])


def surface(**over) -> dict:
    s = {"id": "x-cart", "name": "Cart", "kind": "page", "scope_type": "contracted", "audience": ["customer"],
         "register_ids": ["CART-01"], "context_ids": [], "excluded_ids": [], "owner_pbi": 306, "build_pbi": 306,
         "stage": "S1",
         "route": None, "flow_steps": ["F1.1"], "acceptance": {"stories": [], "scenarios": [], "operations_checks": []},
         "states": [{"state": "default"}], "responsive": {"mobile": "a", "tablet": "b", "desktop": "c"},
         "rtl": {"applies": True, "mirrors": ["layout"], "does_not_mirror": ["numerals"]}, "native_baseline": "",
         "design_freedom": "free", "open_items": [], "decisions": [], "interaction_options": [],
         "erp_dependency": "none", "status": "inventoried"}
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


def decision(**over) -> dict:
    d = {"id": "DQ-05", "title": "Cash collected", "status": "locked", "question": "q", "decision": "d", "must_not": "n",
         "decided_by": "owner", "date": "2026-10-06", "record": "DECISIONS.md D-14", "client_confirmed": False,
         "adds_scope": False, "affected_surfaces": []}
    d.update(over)
    return d


def option(**over) -> dict:
    o = {"id": "IX-01", "name": "List continuation", "affected_surfaces": ["x-cart"], "requirement_outcome": "o",
         "candidate_patterns": ["Numbered pagination", "Load More"], "working_baseline": None, "status": "open",
         "decision_stage": "low_fidelity_review", "constraints": ["c"], "selection_criteria": ["usability"],
         "selected_pattern": None, "decision_source": None}
    o.update(over)
    return o


def inventory(*surfaces, no_surface=None, flows=None, components=None, placeholders=None, decisions=None,
              options=None) -> dict:
    return {"surfaces": list(surfaces),
            "no_surface": no_surface if no_surface is not None else
            {"behaviour": {"reason": "No screen.", "ids": ["CART-02", "PLP-11", "NAV-01", "AC-01"]}},
            "flows": flows if flows is not None else
            [{"id": "F1", "steps": [{"step": "F1.1", "surfaces": ["x-cart"], "register_ids": ["CART-01"]}]}],
            "components": components or [], "placeholders": placeholders or [], "decisions": decisions or [],
            "options": options or [], "sources": [],
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
        s = surface(scope_type="native_required", owner_pbi=None, build_pbi=None, scope_note="Every site has one.",
                    implementation_origin="wordpress")
        self.assertRefused(inventory(s), "surfaces cite no register row")

    def test_a_surface_outside_scope_that_does_not_say_why_it_exists(self):
        s = surface(scope_type="internal_operational", register_ids=[], stage=None, owner_pbi=None, build_pbi=None,
                    implementation_origin="unknown")
        self.assertRefused(inventory(s, no_surface={"behaviour": {"reason": "No screen.",
                                                                  "ids": ["CART-01", "CART-02", "PLP-11", "NAV-01", "AC-01"]}}),
                           "must say why they exist")

    def test_a_provider_surface_needs_no_reading_direction_of_ours(self):
        s = surface(scope_type="provider_hosted", register_ids=[], stage=None, owner_pbi=None, build_pbi=None,
                    scope_note="Theirs.", implementation_origin="provider",
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
        s = surface(id="x-header", register_ids=["NAV-01"], owner_pbi=253, build_pbi=253, flow_steps=[])
        data = inventory(surface(), s, no_surface={"behaviour": {"reason": "No screen.", "ids": ["CART-02", "PLP-11", "AC-01"]}})
        self.assertEqual(self.errors(data), [])
        self.assertEqual([c[0] for c in di.dependency_findings(data)["missing"]], [253])

    # Accepting ownership and build responsibility are two facts
    HEADER = {"behaviour": {"reason": "No screen.", "ids": ["CART-02", "PLP-11", "AC-01"]}}

    def test_a_surface_may_be_built_by_another_pbi_when_it_says_why(self):
        s = surface(id="x-header", register_ids=["NAV-01"], owner_pbi=253, build_pbi=306, flow_steps=[],
                    build_note="The header row is accepted by #253 and the screen is built with the cart.")
        data = inventory(surface(), s, no_surface=self.HEADER)
        self.assertEqual(self.errors(data), [])
        f = di.dependency_findings(data)
        self.assertEqual([x["id"] for x in f["differs"]], ["x-header"])
        self.assertEqual(f["missing"], [], "the design dependency is read on the PBI that builds, not the one that accepts")

    def test_building_elsewhere_moves_no_row(self):
        s = surface(id="x-header", register_ids=["NAV-01"], owner_pbi=306, build_pbi=306, flow_steps=[])
        self.assertRefused(inventory(surface(), s, no_surface=self.HEADER), "NAV-01 is owned by 253, not by 306")

    def test_a_different_build_pbi_without_a_reason(self):
        s = surface(id="x-header", register_ids=["NAV-01"], owner_pbi=253, build_pbi=306, flow_steps=[])
        self.assertRefused(inventory(surface(), s, no_surface=self.HEADER), "say why in build_note")

    def test_a_build_note_where_nothing_differs(self):
        self.assertRefused(inventory(surface(build_note="x")), "build_note is for a build PBI that differs")

    def test_a_build_pbi_that_is_not_a_pbi(self):
        self.assertRefused(inventory(surface(build_pbi=999, build_note="x")), "build_pbi 999 is not a PBI")

    def test_a_contracted_surface_nobody_builds(self):
        self.assertRefused(inventory(surface(build_pbi=None)), "no PBI builds it")

    def test_staff_additions_do_not_raise_a_design_dependency(self):
        s = surface(id="x-admin", register_ids=["NAV-01"], owner_pbi=253, build_pbi=253, flow_steps=[], audience=["staff"],
                    rtl={"applies": False, "reason": "English only."})
        f = di.dependency_findings(inventory(surface(), s, no_surface=self.HEADER))
        self.assertEqual(f["missing"], [])
        self.assertEqual([k for k, _, _ in f["staff_only"]], [253])

    # Owner design decisions
    def test_a_surface_may_cite_an_owner_design_decision(self):
        data = inventory(surface(decisions=["DQ-05"]), decisions=[decision(affected_surfaces=["x-cart"])])
        self.assertEqual(self.errors(data), [])

    def test_a_decision_nobody_recorded(self):
        self.assertRefused(inventory(surface(decisions=["DQ-05"])), "is not an owner design decision")

    def test_a_decided_question_left_open_on_a_surface(self):
        data = inventory(surface(open_items=["DQ-05"]), decisions=[decision()])
        self.assertRefused(data, "open item DQ-05 has no entry in placeholders.json")

    def test_a_decision_recorded_as_a_client_confirmation(self):
        data = inventory(surface(), decisions=[decision(client_confirmed=True)])
        self.assertRefused(data, "is not a client confirmation and adds no scope")

    def test_a_decision_recorded_as_added_scope(self):
        data = inventory(surface(), decisions=[decision(adds_scope=True)])
        self.assertRefused(data, "is not a client confirmation and adds no scope")

    def test_a_decision_that_is_also_an_open_placeholder(self):
        p = {"id": "DQ-05", "affected_surfaces": [], "unknown": "a", "placeholder_allowed": "b", "must_not_assume": "c",
             "replaced_by": "d"}
        self.assertRefused(inventory(surface(), placeholders=[p], decisions=[decision()]), "a decided question is not open")

    def test_an_owner_decision_is_locked_or_it_is_not_a_decision(self):
        data = inventory(surface(), decisions=[decision(status="provisional")])
        self.assertRefused(data, "belongs in interaction-options.json")

    # A provisional pattern is not a requirement and not a final decision
    PLATFORM = {"behaviour": {"reason": "No screen.", "ids": ["CART-01", "CART-02", "PLP-11", "NAV-01", "AC-01"]}}

    def with_option(self, **over):
        return inventory(surface(interaction_options=["IX-01"]), options=[option(**over)])

    def test_an_open_choice_passes(self):
        self.assertEqual(self.errors(self.with_option()), [])

    def test_a_provisional_baseline_passes_and_selects_nothing(self):
        data = self.with_option(status="provisional", working_baseline="Numbered pagination", decision_source="Owner baseline")
        self.assertEqual(self.errors(data), [])
        self.assertIsNone(data["options"][0]["selected_pattern"])

    def test_a_locked_choice_passes_when_it_says_what_was_selected_and_by_whom(self):
        data = self.with_option(status="locked", working_baseline="Numbered pagination", selected_pattern="Load More",
                                decision_source="Owner, low-fidelity review")
        self.assertEqual(self.errors(data), [])

    def test_a_baseline_is_not_a_selection(self):
        data = self.with_option(status="provisional", working_baseline="Numbered pagination", decision_source="Owner baseline",
                                selected_pattern="Numbered pagination")
        self.assertRefused(data, "a baseline is not a selection")

    def test_an_open_choice_with_a_baseline_is_provisional(self):
        self.assertRefused(self.with_option(working_baseline="Load More"), "that is provisional")

    def test_a_provisional_choice_without_a_baseline(self):
        self.assertRefused(self.with_option(status="provisional", decision_source="Owner baseline"), "names no working baseline")

    def test_a_provisional_choice_that_does_not_say_who_set_it(self):
        self.assertRefused(self.with_option(status="provisional", working_baseline="Load More"), "who set the baseline")

    def test_a_locked_choice_that_selected_nothing(self):
        self.assertRefused(self.with_option(status="locked"), "does not say which pattern was selected")

    def test_a_baseline_outside_the_candidates(self):
        data = self.with_option(status="provisional", working_baseline="Carousel", decision_source="Owner baseline")
        self.assertRefused(data, "working_baseline is not one of its candidate patterns")

    def test_a_choice_with_one_pattern_is_not_a_choice(self):
        self.assertRefused(self.with_option(candidate_patterns=["Numbered pagination"]), "at least two candidate patterns")

    def test_a_status_outside_the_three(self):
        self.assertRefused(self.with_option(status="decided"), "is not one of open, provisional, locked")

    def test_a_choice_that_affects_a_surface_that_does_not_exist(self):
        data = inventory(surface(interaction_options=["IX-01"]), options=[option(affected_surfaces=["x-cart", "x-gone"])])
        self.assertRefused(data, "affects x-gone")

    def test_a_surface_whose_options_drifted(self):
        self.assertRefused(inventory(surface(), options=[option()]), "interaction_options differs")

    def test_a_choice_cannot_also_be_a_locked_decision(self):
        data = inventory(surface(interaction_options=["DQ-05"]), options=[option(id="DQ-05")], decisions=[decision()])
        self.assertRefused(data, "it is one thing only")

    # A surface nothing here builds
    def test_no_pbi_is_invented_for_a_platform_surface(self):
        s = surface(scope_type="native_required", register_ids=[], stage=None, owner_pbi=None, build_pbi=None,
                    scope_note="Every site has one.", implementation_origin="woocommerce")
        self.assertEqual(self.errors(inventory(s, no_surface=self.PLATFORM)), [])
        self.assertEqual([x["id"] for x in di.dependency_findings(inventory(s, no_surface=self.PLATFORM))["unbuilt"]], ["x-cart"])

    def test_an_unbuilt_surface_that_does_not_say_where_it_comes_from(self):
        s = surface(scope_type="native_required", register_ids=[], stage=None, owner_pbi=None, build_pbi=None,
                    scope_note="Every site has one.")
        self.assertRefused(inventory(s, no_surface=self.PLATFORM), "implementation_origin must be one of")

    def test_an_origin_on_a_surface_a_pbi_builds(self):
        self.assertRefused(inventory(surface(implementation_origin="woocommerce")), "is for a surface no PBI builds")

    def test_provider_is_the_origin_of_provider_surfaces_only(self):
        s = surface(scope_type="native_required", register_ids=[], stage=None, owner_pbi=None, build_pbi=None,
                    scope_note="Every site has one.", implementation_origin="provider")
        self.assertRefused(inventory(s, no_surface=self.PLATFORM), "is for provider-hosted surfaces, and only for them")

    def test_a_decision_whose_surfaces_drifted(self):
        data = inventory(surface(decisions=["DQ-05"]), decisions=[decision()])
        self.assertRefused(data, "decision DQ-05: affected_surfaces differs")


if __name__ == "__main__":
    unittest.main()

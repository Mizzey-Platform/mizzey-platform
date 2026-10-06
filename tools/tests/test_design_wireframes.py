"""A wireframe stays a design prototype, and the production manifest stays true to the inventory.

The first class checks the committed state. The second gives the artefact checker files it must refuse: script,
server tags, outside resources, libraries, colour, web fonts. The third breaks the manifest in the ways it must
not be broken.
"""

from __future__ import annotations

import copy
import re
import tempfile
import unittest
from pathlib import Path

from tools import design_wireframes as dw

ROOT = Path(__file__).resolve().parent.parent.parent
SURFACES = {"sf-cart", "sf-mini-cart"}

GOOD = """<!doctype html>
<html lang="en" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="wf-surfaces" content="sf-cart sf-mini-cart">
  <meta name="wf-viewport" content="mobile desktop">
  <title>Cart</title>
  <link rel="stylesheet" href="../../_shared/wireframe.css">
</head>
<body>
  <main class="wf-frame" style="border: 1px solid #999; color: #222">
    <h1>Cart</h1>
    <a href="#summary">Summary</a>
    <a href="sf-mini-cart.en.html">Mini-cart</a>
    <form action="#"><button type="button">Checkout</button></form>
    <aside class="wf-note wf-motion" data-trigger="Add to Cart is pressed" data-effect="The mini-cart appears"
           data-role="essential" data-reduced-motion="It appears in place">Feedback on add.</aside>
  </main>
</body>
</html>
"""
CSS = ":root { --wf-ink: #222; --wf-line: #cfcfcf; } .wf-frame { border: 1px solid var(--wf-line); color: rgb(34 34 34); }\n"


class Sandbox(unittest.TestCase):
    """A throwaway repository root with the shared stylesheet in place."""

    def setUp(self):
        self._tmp = tempfile.TemporaryDirectory()
        self.root = Path(self._tmp.name)
        (self.root / "design/wireframes/_shared").mkdir(parents=True)
        (self.root / "design/wireframes/s1/batch-04").mkdir(parents=True)
        (self.root / "design/wireframes/_shared/wireframe.css").write_text(CSS, encoding="utf-8")
        self.rel = "design/wireframes/s1/batch-04/sf-cart.en.html"

    def tearDown(self):
        self._tmp.cleanup()

    def html(self, text: str, rel: str | None = None) -> list[str]:
        return dw.check_html(rel or self.rel, text, SURFACES, self.root)

    def assertRefused(self, problems: list[str], text: str):
        self.assertTrue(any(text in p for p in problems), f"expected {text!r} in {problems}")


class TheCommittedState(unittest.TestCase):
    def test_the_manifest_and_every_artefact_pass(self):
        self.assertEqual(dw.validate(ROOT), [])

    def test_only_design_artefact_file_types_are_under_wireframes(self):
        kinds = {p.suffix for p in (ROOT / "design/wireframes").rglob("*") if p.is_file()}
        self.assertLessEqual(kinds, {".md", ".json", ".html", ".css"})

    def test_no_calibration_frame_has_been_drawn_and_no_direction_is_selected(self):
        manifest = dw.load(ROOT)["manifest"]
        if list((ROOT / "design/wireframes").rglob("*.html")):
            self.skipTest("wireframes exist: the calibration has started")
        self.assertTrue(all(v == "not_started" for f in manifest["calibration"]["frames"] for v in f["status"].values()))
        self.assertIsNone(manifest["selection"]["selected_direction"])
        self.assertTrue(all(e["artifacts"] == [] for e in manifest["entries"]))

    def test_the_calibration_set_is_eight_frames_in_three_directions_on_existing_surfaces(self):
        data = dw.load(ROOT)
        frames = data["manifest"]["calibration"]["frames"]
        self.assertEqual([f["id"] for f in frames], [f"cal-0{n}" for n in range(1, 9)])
        self.assertEqual([d["id"] for d in data["manifest"]["directions"]], ["a", "b", "c"])
        known = {s["id"] for s in data["surfaces"]}
        used = {sid for f in frames for sid in f["surfaces"]}
        self.assertLessEqual(used, known)
        self.assertEqual({f["viewport"] for f in frames}, {"mobile", "desktop"})
        briefed = {e["surface"] for e in data["manifest"]["entries"] if e["brief"]}
        self.assertEqual(used, briefed, "the briefed surfaces are exactly those of the calibration set")

    def test_every_designable_surface_is_in_one_batch_and_the_rest_say_why(self):
        data = dw.load(ROOT)
        by_id = {s["id"]: s for s in data["surfaces"]}
        outside = [e["surface"] for e in data["manifest"]["entries"] if e["batch"] is None]
        self.assertEqual(sorted(s for s in outside if by_id[s]["scope_type"] != "provider_hosted"), ["fd-visual-treatment"])
        self.assertEqual(len([s for s in outside if by_id[s]["scope_type"] == "provider_hosted"]), 7)
        second = [e["surface"] for e in data["manifest"]["entries"] if e["batch"] == "batch-10"]
        self.assertEqual(sorted(second), sorted(s["id"] for s in data["surfaces"] if s["stage"] == "S2"))

    def test_the_workflow_document_counts_each_batch_as_the_manifest_does(self):
        manifest = dw.load(ROOT)["manifest"]
        text = (ROOT / "design/wireframes/workflow.md").read_text(encoding="utf-8")
        stated = {m.group(1): int(m.group(2)) for m in re.finditer(r"^\| `(batch-\d\d)` \|.*\| (\d+) \|$", text, re.M)}
        counted = {b["id"]: sum(1 for e in manifest["entries"] if e["batch"] == b["id"]) for b in manifest["batches"]}
        self.assertEqual(stated, counted)

    def test_the_manifest_restates_no_requirement(self):
        """Design-production status only: an entry carries no register id, and the rows stay in the inventory."""
        manifest = dw.load(ROOT)["manifest"]
        allowed = {"surface", "batch", "brief", "artifacts", "en_ltr", "ar_rtl", "mobile", "desktop", "states_covered",
                   "interaction_pending", "review_status", "not_in_low_fidelity"}
        for e in manifest["entries"]:
            self.assertLessEqual(set(e), allowed, e["surface"])


class AWireframeIsNotApplicationCode(Sandbox):
    def test_a_plain_static_wireframe_passes(self):
        self.assertEqual(self.html(GOOD), [])

    def test_an_arabic_wireframe_passes(self):
        text = GOOD.replace('lang="en" dir="ltr"', 'lang="ar" dir="rtl"')
        self.assertEqual(self.html(text, "design/wireframes/s1/batch-04/sf-cart.ar.html"), [])

    def test_script_in_any_form(self):
        self.assertRefused(self.html(GOOD.replace("</body>", "<script>var a = 1</script></body>")), "a <script> element")
        self.assertRefused(self.html(GOOD.replace("</body>", '<script src="app.js"></script></body>')), "a <script> element")
        self.assertRefused(self.html(GOOD.replace('<button type="button">', '<button type="button" onclick="go()">')),
                           "an inline event handler (onclick)")
        self.assertRefused(self.html(GOOD.replace('href="#summary"', 'href="javascript:void(0)"')), "a javascript: address")

    def test_a_server_or_template_tag(self):
        self.assertRefused(self.html(GOOD.replace("<h1>Cart</h1>", "<h1><?php echo $title; ?></h1>")), "a server or template tag")
        self.assertRefused(self.html(GOOD.replace("<h1>Cart</h1>", "<h1>{{ title }}</h1>")), "a server or template tag")

    def test_anything_fetched_from_outside(self):
        cdn = '<link rel="stylesheet" href="https://cdn.example.com/x.css">'
        self.assertRefused(self.html(GOOD.replace("</head>", cdn + "</head>")), "an address outside the wireframes")
        self.assertRefused(self.html(GOOD.replace('href="#summary"', 'href="//example.com/"')), "an address outside the wireframes")
        self.assertRefused(self.html(GOOD.replace("<h1>Cart</h1>", '<img src="logo.png" alt="">')), "a <img> element")
        self.assertRefused(self.html(GOOD.replace("<h1>Cart</h1>", '<iframe src="x.html"></iframe>')), "a <iframe> element")
        self.assertRefused(self.html(GOOD.replace('href="#summary"', 'href="data:text/html,x"')), "a data: address")
        self.assertRefused(self.html(GOOD.replace('action="#"', 'action="/checkout/"')), "a form that submits somewhere")

    def test_a_stylesheet_outside_the_wireframes_or_missing(self):
        self.assertRefused(self.html(GOOD.replace("../../_shared/wireframe.css", "../../../../mizzey-theme/style.css")),
                           "a stylesheet outside design/wireframes/")
        self.assertRefused(self.html(GOOD.replace("wireframe.css", "missing.css")), "a stylesheet that does not exist")
        self.assertRefused(self.html(GOOD.replace('<link rel="stylesheet" href="../../_shared/wireframe.css">', "")),
                           "no link to the shared wireframe stylesheet")

    def test_a_product_library(self):
        for name in ("tailwind", "bootstrap", "swiper", "gsap", "shadcn"):
            self.assertRefused(self.html(GOOD.replace('class="wf-frame"', f'class="wf-frame {name}-x"')),
                               f"a product library ({name})")
        self.assertRefused(dw.check_stylesheet("x.css", "/* bootstrap 5 */ .a { color: #333 }"), "a product library (bootstrap)")

    def test_any_colour_that_is_not_a_grey(self):
        for value in ("#d38a68", "#0af", "rgb(211, 138, 104)", "rgba(0 0 255 / 0.5)", "hsl(20 50% 60%)", "tomato", "navy",
                      "oklch(70% 0.1 40)"):
            self.assertRefused(dw.check_css(f".a {{ color: {value} }}"), "colour", )
            self.assertRefused(self.html(GOOD.replace("color: #222", f"color: {value}")), "colour")
        for value in ("#222", "#ccc", "#f5f5f5", "rgb(34, 34, 34)", "rgba(0 0 0 / 0.4)", "hsl(0 0% 60%)", "black", "white",
                      "gray", "transparent", "currentColor"):
            self.assertEqual(dw.check_css(f".a {{ color: {value}; border: 1px solid {value} }}"), [], value)

    def test_a_selector_is_not_read_as_a_colour(self):
        self.assertEqual(dw.check_css("#ace .decade { margin: 0 }"), [])

    def test_web_fonts_and_css_that_reaches_outside(self):
        self.assertRefused(dw.check_css("@font-face { font-family: X; src: url(x.woff2) }"), "@font-face")
        self.assertRefused(dw.check_css("@import 'other.css';"), "@import")
        self.assertRefused(dw.check_css(".a { background: url(photo.jpg) }"), "url()")
        self.assertEqual(dw.check_css(".a { font-family: system-ui, 'Segoe UI', Tahoma, sans-serif }"), [])

    def test_a_stylesheet_big_enough_to_be_a_library(self):
        big = ".a { margin: 0 }\n" * 4000
        self.assertRefused(dw.check_stylesheet("x.css", big), "not a library")

    def test_language_and_direction_must_agree_with_each_other_and_with_the_file_name(self):
        self.assertRefused(self.html(GOOD.replace('dir="ltr"', 'dir="rtl"')), "a wireframe is en with ltr, or ar with rtl")
        self.assertRefused(self.html(GOOD.replace(' lang="en" dir="ltr"', "")), "a wireframe is en with ltr, or ar with rtl")
        self.assertRefused(self.html(GOOD, "design/wireframes/s1/batch-04/sf-cart.ar.html"), "whose document language is 'en'")
        self.assertRefused(self.html(GOOD, "design/wireframes/s1/batch-04/sf-cart.html"), "does not end .en.html or .ar.html")

    def test_a_wireframe_names_the_canonical_surfaces_it_shows(self):
        self.assertRefused(self.html(GOOD.replace("sf-cart sf-mini-cart", "sf-cart sf-basket")), "sf-basket, which is not a surface")
        self.assertRefused(self.html(GOOD.replace('<meta name="wf-surfaces" content="sf-cart sf-mini-cart">', "")),
                           "naming the canonical surfaces it shows")
        self.assertRefused(self.html(GOOD.replace('content="mobile desktop"', 'content="watch"')), "wf-viewport")

    def test_a_calibration_frame_says_which_direction_it_is(self):
        rel = "design/wireframes/s1/calibration/b/cal-06.en.html"
        (self.root / "design/wireframes/s1/calibration/b").mkdir(parents=True)
        text = GOOD.replace("../../_shared/", "../../../_shared/")
        self.assertRefused(self.html(text, rel), "a calibration frame of direction b")
        ok = text.replace("<title>", '<meta name="wf-direction" content="b">\n  <title>')
        self.assertEqual(self.html(ok, rel), [])

    def test_motion_is_annotated_with_all_four_facts(self):
        self.assertRefused(self.html(GOOD.replace(' data-reduced-motion="It appears in place"', "")),
                           "a motion note without data-reduced-motion")
        self.assertRefused(self.html(GOOD.replace('data-role="essential"', 'data-role="nice"')), "essential or decorative")

    def test_nothing_but_design_artefacts_under_wireframes(self):
        (self.root / "design/wireframes/s1/app.js").write_text("alert(1)", encoding="utf-8")
        (self.root / "design/wireframes/s1/page.php").write_text("<?php", encoding="utf-8")
        errs = dw.check_artefacts(self.root, SURFACES)
        self.assertRefused(errs, "s1/app.js: only HTML, CSS, Markdown and JSON")
        self.assertRefused(errs, "s1/page.php: only HTML, CSS, Markdown and JSON")


class TheManifestHoldsToTheInventory(unittest.TestCase):
    def setUp(self):
        self.data = copy.deepcopy(dw.load(ROOT))
        self.entries = {e["surface"]: e for e in self.data["manifest"]["entries"]}
        self.surfaces = {s["id"]: s for s in self.data["surfaces"]}

    def errors(self):
        return dw.check_manifest(self.data, ROOT)

    def assertRefused(self, text):
        errs = self.errors()
        self.assertTrue(any(text in e for e in errs), f"expected {text!r} in {errs[:6]}")

    def test_a_surface_with_no_entry(self):
        self.data["manifest"]["entries"] = [e for e in self.data["manifest"]["entries"] if e["surface"] != "sf-cart"]
        self.assertRefused("surface sf-cart has no entry")

    def test_an_entry_for_a_surface_that_does_not_exist(self):
        self.data["manifest"]["entries"].append(dict(self.entries["sf-cart"], surface="sf-basket"))
        self.assertRefused("sf-basket is not a surface of the inventory")

    def test_pending_interaction_choices_are_derived_not_typed(self):
        self.entries["sf-pagination"]["interaction_pending"] = []
        self.assertRefused("manifest sf-pagination: interaction_pending differs from the inventory")

    def test_a_locked_choice_is_no_longer_pending(self):
        option = next(o for o in self.data["options"] if o["id"] == "DQ-02")
        option["status"] = "locked"
        self.assertRefused("manifest sf-pagination: interaction_pending differs from the inventory")

    def test_a_briefed_surface_needs_its_brief_and_a_purpose(self):
        self.entries["sf-wishlist"]["review_status"] = "briefed"
        self.surfaces["sf-wishlist"]["status"] = "briefed"
        self.assertRefused("manifest sf-wishlist: briefed, and has no brief")
        self.assertRefused("manifest sf-wishlist: briefed, and the inventory gives the surface no purpose")

    def test_the_manifest_and_the_inventory_status_cannot_disagree(self):
        self.entries["sf-cart"]["review_status"] = "reviewed"
        self.assertRefused("review status reviewed and the inventory status briefed disagree")

    def test_a_surface_is_not_reviewed_while_a_required_state_is_uncovered(self):
        self.entries["sf-cart"]["review_status"] = "reviewed"
        self.surfaces["sf-cart"]["status"] = "reviewed"
        self.entries["sf-cart"]["states_covered"] = ["default"]
        self.assertRefused("reviewed, and these required states are not covered")

    def test_a_state_the_surface_does_not_list(self):
        self.entries["sf-cart"]["states_covered"] = ["default", "gift_wrapped"]
        self.assertRefused("covers state gift_wrapped, which the surface does not list")

    def test_a_listed_artefact_that_does_not_exist(self):
        self.entries["sf-cart"]["artifacts"] = ["design/wireframes/s1/batch-04/sf-cart.en.html"]
        self.assertRefused("artefact design/wireframes/s1/batch-04/sf-cart.en.html does not exist")

    def test_an_artefact_filed_under_the_wrong_release_or_name(self):
        self.entries["sf-cart"]["artifacts"] = ["design/wireframes/s2/batch-10/sf-cart.en.html"]
        self.assertRefused("is not design/wireframes/s1/<batch>/sf-cart.en.html or .ar.html")

    def test_marked_drawn_with_nothing_to_show(self):
        self.entries["sf-cart"]["en_ltr"] = "drawn"
        self.assertRefused("manifest sf-cart: en_ltr marked drawn, and no artefact is listed")

    def test_the_administration_is_not_drawn_in_arabic(self):
        self.entries["ad-staff-log"]["ar_rtl"] = "not_started"
        self.assertRefused("Arabic does not apply to this surface")

    def test_a_customer_facing_surface_is_drawn_in_arabic_too(self):
        self.entries["sf-cart"]["ar_rtl"] = "not_applicable"
        self.assertRefused("a customer-facing surface is drawn in Arabic too")

    def test_a_provider_interface_is_never_put_in_a_batch(self):
        self.entries["pv-paymob-card"]["batch"] = "batch-05"
        self.assertRefused("a provider's interface is not wireframed")

    def test_a_surface_outside_every_batch_says_why(self):
        self.entries["sf-cart"]["batch"] = None
        self.assertRefused("in no review batch, and does not say why")

    def test_second_release_surfaces_stay_in_their_own_pass(self):
        self.entries["ac-reorder"]["batch"] = "batch-06"
        self.assertRefused("second-release surfaces are in the second-release batch")
        self.entries["ac-reorder"]["batch"] = "batch-10"
        self.entries["sf-cart"]["batch"] = "batch-10"
        self.assertRefused("manifest sf-cart: second-release surfaces are in the second-release batch")

    def test_a_calibration_frame_reuses_existing_first_release_customer_surfaces(self):
        frame = self.data["manifest"]["calibration"]["frames"][5]
        frame["surfaces"].append("sf-gift-finder")
        self.assertRefused("calibration cal-06: sf-gift-finder is not a surface of the inventory")
        frame["surfaces"][-1] = "ac-reorder"
        self.assertRefused("calibration cal-06: ac-reorder is not a first-release, contracted, customer-facing surface")
        frame["surfaces"][-1] = "ad-staff-log"
        self.assertRefused("calibration cal-06: ad-staff-log is not a first-release, contracted, customer-facing surface")

    def test_a_calibration_frame_shows_only_states_its_surfaces_list(self):
        self.data["manifest"]["calibration"]["frames"][5]["shows"].append("sf-cart:gift_wrapped")
        self.assertRefused("calibration cal-06: shows sf-cart:gift_wrapped")

    def test_a_calibration_frame_is_drawn_in_all_three_directions_and_always_in_english(self):
        frame = self.data["manifest"]["calibration"]["frames"][0]
        del frame["status"]["c"]
        self.assertRefused("calibration cal-01: one status for each of the three directions")
        frame["status"]["c"] = "not_started"
        frame["languages"] = ["ar-rtl"]
        self.assertRefused("calibration cal-01: English is the canonical design and is always drawn")

    def test_a_frame_marked_drawn_with_no_file(self):
        self.data["manifest"]["calibration"]["frames"][0]["status"]["a"] = "drawn"
        self.assertRefused("calibration cal-01: direction a is drawn, and design/wireframes/s1/calibration/a/cal-01.en.html is missing")

    def test_a_declared_calibration_file_may_exist_before_its_review_is_recorded(self):
        """The designer creates the files and leaves the manifest alone: the status follows the review."""
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            path = root / "design/wireframes/s1/calibration/a/cal-01.en.html"
            path.parent.mkdir(parents=True)
            path.write_text("<!doctype html>", encoding="utf-8")
            stray = root / "design/wireframes/s1/calibration/a/cal-99.en.html"
            stray.write_text("<!doctype html>", encoding="utf-8")
            errs = dw.check_manifest(self.data, root)
        self.assertFalse([e for e in errs if "cal-01" in e], errs[:6])
        self.assertTrue(any("cal-99.en.html: a wireframe the manifest does not list" in e for e in errs))

    def test_no_direction_is_selected_before_the_calibration_is_reviewed(self):
        self.data["manifest"]["selection"]["selected_direction"] = "b"
        self.data["manifest"]["selection"]["decision_source"] = "Owner"
        self.assertRefused("a direction is selected before every calibration frame has been reviewed")

    def test_there_are_three_directions_and_no_fourth(self):
        self.data["manifest"]["directions"].append({"id": "d", "label": "Extra", "approach": "x"})
        self.assertRefused("the three structural directions a, b and c")


if __name__ == "__main__":
    unittest.main()

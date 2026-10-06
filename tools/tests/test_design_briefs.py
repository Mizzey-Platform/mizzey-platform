"""A brief is generated from the inventory, stays current, and never carries the signed wording into the repository."""

from __future__ import annotations

import copy
import io
import contextlib
import tempfile
import unittest
from pathlib import Path

from tools import design_briefs as db

ROOT = Path(__file__).resolve().parent.parent.parent
SECTIONS = ("## Surface", "## Purpose", "## Scope", "## Structure", "## States", "## Responsive", "## Localisation",
            "## Design freedom", "## Native baseline", "## Open inputs", "## Interaction options", "## Design direction",
            "## Review status")


class TheCommittedBriefs(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.data = db.load(ROOT)

    def test_they_are_what_the_generator_writes(self):
        self.assertEqual(db.check(ROOT, self.data), [])

    def test_there_is_one_for_each_surface_the_manifest_says_is_briefed_and_no_other(self):
        listed = sorted(e["surface"] + ".md" for e in self.data["manifest"]["entries"] if e["brief"])
        on_disk = sorted(p.name for p in (ROOT / "design/briefs").glob("*.md") if p.name != "README.md")
        self.assertEqual(on_disk, listed)

    def test_a_brief_can_be_generated_for_every_surface(self):
        for s in self.data["surfaces"]:
            text = db.brief(s["id"], self.data)
            for heading in SECTIONS:
                self.assertIn("\n" + heading + "\n", text, f"{s['id']}: {heading}")
            self.assertTrue(text.startswith(f"# Brief: {s['name']} (`{s['id']}`)"))

    def test_generation_is_deterministic(self):
        again = db.load(ROOT)
        for sid in ("sf-cart", "ck-checkout", "ad-staff-log", "em-refund", "pv-paymob-card"):
            self.assertEqual(db.brief(sid, self.data), db.brief(sid, again))

    def test_a_brief_carries_what_the_inventory_holds(self):
        text = db.brief("sf-pdp-simple", self.data)
        s = next(x for x in self.data["surfaces"] if x["id"] == "sf-pdp-simple")
        for rid in s["register_ids"] + s["context_ids"] + s["excluded_ids"]:
            self.assertIn(f"`{rid}`", text)
        for state in s["states"]:
            self.assertIn(f"`{state['state']}`", text)
        for item in s["open_items"]:
            self.assertIn(f"`{item}`", text)
        self.assertIn("#254", text)
        self.assertIn(s["purpose"], text)
        self.assertIn(s["native_baseline"], text)

    def test_a_contract_fixed_pattern_is_marked_as_not_a_design_alternative(self):
        text = db.brief("sf-pdp-simple", self.data)
        self.assertIn("### IX-08:", text)
        self.assertIn("Fixed by the contract. Not a design alternative", text)
        self.assertIn("Status `open`", text)

    def test_a_provisional_baseline_is_shown_as_provisional_with_its_alternatives(self):
        text = db.brief("sf-pagination", self.data)
        self.assertIn("Status `provisional`. A working baseline, not a requirement and not a final owner decision.", text)
        for candidate in ("Numbered pagination", "Load More", "Controlled continuous or infinite loading"):
            self.assertIn(f"- {candidate}", text)

    def test_the_administration_is_briefed_in_english_only_and_without_the_storefront_direction(self):
        text = db.brief("ad-staff-log", self.data)
        self.assertIn("Arabic right to left does not apply.", text)
        self.assertIn("is written for the storefront", text)
        self.assertNotIn("**Motion is annotated, not built.**", text)

    def test_a_surface_outside_scope_is_never_briefed_as_contracted(self):
        text = db.brief("sy-404", self.data)
        self.assertIn("**This surface is drawn for no register row.**", text)
        self.assertIn("none. Origin: `wordpress`", text)

    def test_a_brief_quotes_the_direction_document_and_follows_it(self):
        changed = copy.deepcopy(self.data)
        changed["direction"] = changed["direction"].replace(
            "Mizzey should feel like a contemporary, polished, modern ecommerce product.",
            "Mizzey should feel like a marketplace stall.")
        self.assertIn("Mizzey should feel like a marketplace stall.", db.brief("sf-cart", changed))
        self.assertIn("not the brand identity", db.brief("sf-cart", self.data))

    def test_a_committed_brief_goes_stale_when_the_inventory_changes(self):
        changed = copy.deepcopy(self.data)
        next(s for s in changed["surfaces"] if s["id"] == "sf-cart")["states"].append({"state": "gift_wrapped"})
        self.assertTrue(any(e.startswith("design/briefs/sf-cart.md: stale") for e in db.check(ROOT, changed)))

    def test_no_committed_brief_carries_register_wording(self):
        for path in (ROOT / "design/briefs").glob("*.md"):
            text = path.read_text(encoding="utf-8")
            self.assertNotIn("Wording in the signed register", text, path.name)
            self.assertNotIn("Confidential", text, path.name)
            self.assertNotIn("EGP", text, path.name)


class WordingStaysOutsideTheRepository(unittest.TestCase):
    REGISTER = "| ID | Requirement | Scope | Stage |\n|---|---|---|---|\n| CART-01 | Line items with image and name | P1 | S1 |\n"

    def run_cli(self, *args: str) -> tuple[int, str]:
        out = io.StringIO()
        with contextlib.redirect_stdout(out):
            code = db.main(list(args))
        return code, out.getvalue()

    def test_working_copies_with_wording_are_refused_inside_the_repository(self):
        with tempfile.TemporaryDirectory() as tmp:
            register = Path(tmp) / "register.md"
            register.write_text(self.REGISTER, encoding="utf-8")
            for inside in (ROOT / "design" / "briefs", ROOT / "design" / "briefs" / "local", ROOT):
                code, out = self.run_cli("--register", str(register), "--out", str(inside))
                self.assertEqual(code, 1)
                self.assertIn("never written inside the repository", out)
            self.assertFalse((ROOT / "design" / "briefs" / "local").exists())

    def test_they_are_written_outside_it_with_the_wording_merged(self):
        with tempfile.TemporaryDirectory() as tmp:
            register = Path(tmp) / "register.md"
            register.write_text(self.REGISTER, encoding="utf-8")
            out_dir = Path(tmp) / "working"
            code, _ = self.run_cli("--register", str(register), "--out", str(out_dir))
            self.assertEqual(code, 0)
            text = (out_dir / "sf-cart.md").read_text(encoding="utf-8")
            self.assertIn("Line items with image and name", text)
            self.assertIn("Confidential: not for the repository", text)

    def test_the_register_option_needs_somewhere_outside_to_write(self):
        code, out = self.run_cli("--register", "anything.md")
        self.assertEqual(code, 1)
        self.assertIn("--register needs --out", out)

    def test_the_wording_reader_takes_the_first_table_row_of_each_id(self):
        with tempfile.TemporaryDirectory() as tmp:
            register = Path(tmp) / "register.md"
            register.write_text(self.REGISTER + "| **CART-01** [key] | A later mention | P1 | S1 |\nCART-02 in prose\n",
                                encoding="utf-8")
            self.assertEqual(db.register_wording(register), {"CART-01": "Line items with image and name"})


if __name__ == "__main__":
    unittest.main()

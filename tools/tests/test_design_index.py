"""The wireframe navigation page is generated from the manifest and inventory, links every wireframe, and stays current."""

from __future__ import annotations

import re
import shutil
import tempfile
import unittest
from pathlib import Path

from tools import design_index as di

ROOT = Path(__file__).resolve().parent.parent.parent


class ThePage(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.data = di.load(ROOT)
        cls.page = di.build(cls.data)

    def test_it_is_the_same_every_time_it_is_built(self):
        self.assertEqual(di.build(self.data), self.page)

    def test_it_links_every_wireframe_the_manifest_lists(self):
        hrefs = set(re.findall(r'href="([^"#]+)"', self.page))
        for e in self.data["manifest"]["entries"]:
            for path in e.get("artifacts", []):
                self.assertIn(di.rel(path), hrefs, path)

    def test_every_link_points_at_a_file_that_exists(self):
        for href in set(re.findall(r'href="([^"#]+)"', self.page)):
            self.assertTrue((ROOT / di.WIREFRAMES / href).is_file(), href)

    def test_every_flow_and_batch_has_its_own_section(self):
        for f in self.data["flows"]:
            self.assertIn(f'id="{f["id"]}"', self.page)
        for b in self.data["manifest"]["batches"]:
            self.assertIn(f'id="{b["id"]}"', self.page)

    def test_it_follows_the_wireframe_rules(self):
        self.assertNotIn("<script", self.page.lower())
        self.assertIsNone(re.search(r"\bon[a-z]+\s*=", self.page))
        self.assertIsNone(re.search(r"https?://", self.page))
        self.assertNotIn("—", self.page)
        for colour in re.findall(r"#([0-9a-fA-F]{6})\b", di.STYLE):
            r, g, b = (int(colour[i:i + 2], 16) for i in (0, 2, 4))
            self.assertTrue(r == g == b, f"#{colour} is not a grey")


class TheCheck(unittest.TestCase):
    def test_a_missing_page_is_not_an_error_and_a_stale_one_is(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            for part in ("design/wireframes/manifest.json", "design/inventory/surfaces.json",
                         "design/inventory/flows.json", "design/inventory/components.json"):
                (root / part).parent.mkdir(parents=True, exist_ok=True)
                shutil.copy(ROOT / part, root / part)
            self.assertEqual(di.check(root), [])
            (root / di.INDEX).write_text(di.build(di.load(root)), encoding="utf-8", newline="\n")
            self.assertEqual(di.check(root), [])
            (root / di.INDEX).write_text("<!doctype html>\n", encoding="utf-8")
            self.assertEqual(len(di.check(root)), 1)


if __name__ == "__main__":
    unittest.main()

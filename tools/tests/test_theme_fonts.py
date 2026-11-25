"""The theme's fonts exist, are self-hosted per script, and stay inside the byte budgets (spec 066, slice 1, K-6).

Ships with the theme (the slice pull request); `test_theme_fonts_tool.py` tests the tool on fixtures.

Why this exists. Inter and Tajawal are served from `mizzey-theme/assets/fonts/` as WOFF2, one file per weight and
script, made by `tools/theme_fonts.py`. Every face `theme.json` declares must be a file that exists, every face
swaps (`font-display: swap`) and owns only its script (`unicode-range`), the fallback faces carry the metric
overrides so the swap moves nothing, the open-font licences ship beside the files, and what a page loads stays in
the budget of the design foundations (A.7):

| Page | Budget |
|---|---|
| English | 48 KB |
| Arabic | 84 KB |
| Any page, hard ceiling | 100 KB transferred, 50 KB and 2 files preloaded |

KB here is 1024 bytes. The budgets are checked against the bytes of the files on disk, which is what is sent: WOFF2
is already compressed, so a server does not shrink it further.
"""

from __future__ import annotations

import hashlib
import json
import re
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
THEME = ROOT / "mizzey-theme"
FONTS = THEME / "assets" / "fonts"
MANIFEST = FONTS / "fonts.json"
KB = 1024
BUDGETS = {"en": 48 * KB, "ar": 84 * KB}
CEILING = 100 * KB
PRELOAD_CEILING = 50 * KB
PRELOAD_FILES = 2


def theme_faces() -> list[dict]:
    theme = json.loads((THEME / "theme.json").read_text(encoding="utf-8"))
    families = theme.get("settings", {}).get("typography", {}).get("fontFamilies", [])
    return [face for family in families for face in family.get("fontFace", [])]


def manifest() -> dict:
    return json.loads(MANIFEST.read_text(encoding="utf-8")) if MANIFEST.exists() else {}


class FontFilesExist(unittest.TestCase):
    def test_theme_json_declares_the_brand_faces(self) -> None:
        faces = theme_faces()
        families = {f["fontFamily"] for f in faces}
        self.assertTrue({"Inter", "Tajawal"} <= families, "theme.json declares no Inter or no Tajawal face")

    def test_every_face_theme_json_lists_is_a_file_that_exists(self) -> None:
        missing = []
        for face in theme_faces():
            for src in face["src"] if isinstance(face["src"], list) else [face["src"]]:
                self.assertTrue(src.startswith("file:./assets/fonts/") and src.endswith(".woff2"), src)
                if not (THEME / src.removeprefix("file:./")).is_file():
                    missing.append(src)
        self.assertEqual([], missing, "font files theme.json names that are not on disk")

    def test_every_face_swaps_and_owns_only_its_script(self) -> None:
        for face in theme_faces():
            self.assertEqual("swap", face.get("fontDisplay"), face["src"])
            self.assertTrue(face.get("unicodeRange"), face["src"])

    def test_the_manifest_lists_no_source_it_could_not_find(self) -> None:
        self.assertTrue(manifest(), "assets/fonts/fonts.json is missing: run python tools/theme_fonts.py")
        self.assertEqual([], manifest().get("missing_sources", []), "font weights whose source TTF was not found")

    def test_the_open_font_licences_ship_with_the_files(self) -> None:
        for name in ("Inter-OFL.txt", "Tajawal-OFL.txt"):
            text = (FONTS / name).read_text(encoding="utf-8") if (FONTS / name).exists() else ""
            self.assertIn("SIL OPEN FONT LICENSE", text.upper(), name)

    def test_the_fallback_faces_carry_the_metric_overrides(self) -> None:
        css = (THEME / "assets" / "css" / "fonts.css").read_text(encoding="utf-8")
        for family in ("Inter Fallback", "Inter Ar", "Tajawal Fallback"):
            self.assertIn(f'font-family: "{family}"', css)
        for descriptor in ("size-adjust", "ascent-override", "descent-override", "line-gap-override"):
            self.assertIn(descriptor, css)


class FontBudgets(unittest.TestCase):
    def page_bytes(self, page: str) -> int:
        files = manifest()["pages"][page]
        return sum((FONTS / name).stat().st_size for name in files)

    def test_each_page_stays_inside_its_script_budget(self) -> None:
        over = []
        for page, budget in BUDGETS.items():
            size = self.page_bytes(page)
            if size > budget:
                over.append(f"{page}: {size / KB:.1f} KB, budget {budget / KB:.0f} KB")
        self.assertEqual([], over, "font bytes a page loads, over the budget of FOUNDATIONS A.7")

    def test_no_page_goes_over_the_hard_ceiling(self) -> None:
        over = [f"{page}: {self.page_bytes(page) / KB:.1f} KB" for page in manifest()["pages"]
                if self.page_bytes(page) > CEILING]
        self.assertEqual([], over, "font bytes over the 100 KB ceiling")

    def test_preloads_stay_inside_their_ceiling(self) -> None:
        for language, files in manifest()["preload"].items():
            self.assertLessEqual(len(files), PRELOAD_FILES, language)
            size = sum((FONTS / name).stat().st_size for name in files)
            self.assertLessEqual(size, PRELOAD_CEILING, language)

    def test_the_preloads_are_the_ones_foundations_names(self) -> None:
        preload = manifest()["preload"]
        self.assertEqual(1, len(preload["en"]))
        self.assertTrue(preload["en"][0].startswith("inter-"), preload["en"])
        self.assertEqual(["tajawal-400-arabic", "tajawal-900-arabic"],
                         [re.sub(r"-[0-9a-f]{8}\.woff2$", "", f) for f in preload["ar"]])


class FontFileNames(unittest.TestCase):
    def test_every_file_name_carries_the_hash_of_its_bytes(self) -> None:
        for face in manifest()["faces"]:
            name = face["file"]
            digest = hashlib.sha256((FONTS / name).read_bytes()).hexdigest()[:8]
            self.assertTrue(name.endswith(f"-{digest}.woff2"), name)

    def test_no_file_from_an_earlier_build_is_left_behind(self) -> None:
        listed = {face["file"] for face in manifest()["faces"]}
        self.assertEqual(listed, {p.name for p in FONTS.glob("*.woff2")})


class FontLoadingInTheTheme(unittest.TestCase):
    def test_functions_php_prints_the_preload_from_the_manifest(self) -> None:
        source = (THEME / "functions.php").read_text(encoding="utf-8")
        self.assertIn("rel=\"preload\"", source)
        self.assertIn("crossorigin", source)
        self.assertRegex(source, re.compile(r"fonts\.json"))


if __name__ == "__main__":
    unittest.main()

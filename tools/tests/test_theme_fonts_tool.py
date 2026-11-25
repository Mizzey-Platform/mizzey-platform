"""`tools/theme_fonts.py` makes the self-hosted font files, tested on fixtures.

Ships with the tool (the governance pull request), so it reads no theme file and no real font: it builds a tiny
TrueType font with fontTools, lays it out as the workspace `design/brand` folder, and runs the tool into a
temporary theme. What it proves:

- with only the static Inter files, one WOFF2 per weight is made; with `InterVariable.ttf` present, it is preferred
  and made once, for the weight range 300 to 900;
- Inter keeps only Latin, Tajawal keeps Arabic plus the shared punctuation, without Latin letters or digits;
- every file name carries the first eight hex digits of its SHA-256, and files of an earlier build are removed;
- the pages and the preloads are those of FOUNDATIONS A.7 (English: the Inter file; Arabic: Tajawal 400 and 900);
- the fallback faces carry `size-adjust` and the three metric overrides, and "Inter Ar" points at the Inter files;
- the licences are copied, and a missing source is listed and makes the command fail, without stopping the rest.
"""

from __future__ import annotations

import hashlib
import io
import json
import re
import tempfile
import unittest
from pathlib import Path
from unittest import mock

try:
    from fontTools.fontBuilder import FontBuilder
    from fontTools.pens.ttGlyphPen import TTGlyphPen
    from fontTools.ttLib import TTFont

    from tools import theme_fonts as tf
except ImportError:  # CI has no fontTools: the tool runs where the fonts are built, the output checks run everywhere
    raise unittest.SkipTest("fontTools is not installed; tools/theme_fonts.py is tested where fonts are built")

CHARS = {"space": 0x20, "A": 0x41, "zero": 0x30, "comma": 0x2C, "alef": 0x627, "beh": 0x628}


def tiny_font() -> bytes:
    fb = FontBuilder(1000, isTTF=True)
    names = [".notdef", *CHARS]
    fb.setupGlyphOrder(names)
    fb.setupCharacterMap({code: name for name, code in CHARS.items()})
    glyphs = {}
    for name in names:
        pen = TTGlyphPen(None)
        if name != "space":
            pen.moveTo((50, 0)); pen.lineTo((50, 700)); pen.lineTo((450, 700)); pen.lineTo((450, 0)); pen.closePath()
        glyphs[name] = pen.glyph()
    fb.setupGlyf(glyphs)
    fb.setupHorizontalMetrics({name: (500, 50) for name in names})
    fb.setupHorizontalHeader(ascent=800, descent=-200)
    fb.setupNameTable({"familyName": "Fixture", "styleName": "Regular", "version": "Version 1.000"})
    fb.setupOS2(sTypoAscender=800, sTypoDescender=-200, usWinAscent=800, usWinDescent=200)
    fb.setupPost()
    buffer = io.BytesIO()
    fb.save(buffer)
    return buffer.getvalue()


FONT = tiny_font()


class Fixture(unittest.TestCase):
    variable = False

    def setUp(self) -> None:
        self.dir = tempfile.TemporaryDirectory()
        root = Path(self.dir.name)
        self.brand, self.theme, self.system = root / "brand", root / "theme", root / "system"
        inter, tajawal, pack = (self.brand / tf.INTER, self.brand / tf.TAJAWAL, self.brand / tf.PACK)
        for folder in (inter, tajawal, pack, self.system):
            folder.mkdir(parents=True)
        for style in ("Light", "Regular", "Medium", "Bold", "Black"):
            (inter / f"Inter-{style}.ttf").write_bytes(FONT)
        (tajawal / "Tajawal-Regular.ttf").write_bytes(FONT)
        for style in ("Light", "Medium", "Black"):
            (pack / f"Tajawal-{style}.ttf").write_bytes(FONT)
        (inter / "LICENSE.txt").write_text("SIL OPEN FONT LICENSE Version 1.1 (Inter)", encoding="utf-8")
        (tajawal / "OFL.txt").write_text("SIL OPEN FONT LICENSE Version 1.1 (Tajawal)", encoding="utf-8")
        for name in ("arial.ttf", "arialbd.ttf"):
            (self.system / name).write_bytes(FONT)
        if self.variable:
            (inter / "InterVariable.ttf").write_bytes(FONT)

    def tearDown(self) -> None:
        self.dir.cleanup()

    def build(self) -> dict:
        return tf.build(self.brand, self.theme, system_fonts=self.system)

    @property
    def fonts(self) -> Path:
        return self.theme / "assets" / "fonts"


class StaticInter(Fixture):
    def test_one_file_per_weight_with_a_content_hash(self) -> None:
        manifest = self.build()
        names = [face["file"] for face in manifest["faces"]]
        self.assertEqual(9, len(names))
        for name in names:
            self.assertRegex(name, r"^(inter-\d00-latin|tajawal-\d00-arabic)-[0-9a-f]{8}\.woff2$")
            digest = hashlib.sha256((self.fonts / name).read_bytes()).hexdigest()[:8]
            self.assertTrue(name.endswith(f"-{digest}.woff2"), name)
        self.assertEqual([], manifest["missing_sources"])

    def test_each_script_keeps_only_its_characters(self) -> None:
        manifest = self.build()
        cmaps = {face["file"].split("-")[0]: set(tf.open_font(self.fonts / face["file"]).getBestCmap())
                 for face in manifest["faces"] if face["fontWeight"] == "400"}
        self.assertTrue({0x41, 0x30} <= cmaps["inter"])
        self.assertFalse({0x627, 0x628} & cmaps["inter"])
        self.assertTrue({0x627, 0x628, 0x20, 0x2C} <= cmaps["tajawal"])
        self.assertFalse({0x41, 0x30} & cmaps["tajawal"], "Latin letters and digits in Arabic come from Inter")

    def test_every_face_swaps_and_names_its_range(self) -> None:
        for face in self.build()["faces"]:
            self.assertEqual("swap", face["fontDisplay"])
            self.assertEqual(tf.LATIN if face["fontFamily"] == "Inter" else tf.ARABIC, face["unicodeRange"])
            self.assertEqual([f"file:./assets/fonts/{face['file']}"], face["src"])

    def test_pages_and_preloads_follow_foundations(self) -> None:
        manifest = self.build()
        stem = lambda name: re.sub(r"-[0-9a-f]{8}\.woff2$", "", name)  # noqa: E731
        self.assertEqual(["inter-400-latin", "inter-500-latin", "inter-700-latin", "inter-900-latin"],
                         [stem(f) for f in manifest["pages"]["en"]])
        self.assertEqual(["tajawal-400-arabic", "tajawal-500-arabic", "tajawal-900-arabic"],
                         [stem(f) for f in manifest["pages"]["ar"]][:3])
        self.assertIn("tajawal-300-arabic", [stem(f) for f in manifest["pages"]["ar-lead"]])
        self.assertEqual({"en": ["inter-400-latin"], "ar": ["tajawal-400-arabic", "tajawal-900-arabic"]},
                         {k: [stem(f) for f in v] for k, v in manifest["preload"].items()})

    def test_files_of_an_earlier_build_are_removed(self) -> None:
        self.fonts.mkdir(parents=True)
        (self.fonts / "inter-400-latin-00000000.woff2").write_bytes(b"old")
        manifest = self.build()
        self.assertEqual({f["file"] for f in manifest["faces"]}, {p.name for p in self.fonts.glob("*.woff2")})

    def test_the_fallback_faces_and_inter_ar(self) -> None:
        self.build()
        css = (self.theme / "assets" / "css" / "fonts.css").read_text(encoding="utf-8")
        for family, local in (("Inter Fallback", "Arial"), ("Inter Fallback", "Arial Bold"),
                              ("Tajawal Fallback", "Tahoma"), ("Tajawal Fallback", "Tahoma Bold")):
            self.assertIn(f'font-family: "{family}";\n\tsrc: local("{local}");', css)
        self.assertIn("size-adjust: 100%;", css, "the fixture font measures the same as its own fallback")
        self.assertIn("ascent-override: 80%;\n\tdescent-override: 20%;\n\tline-gap-override: 0%;", css)
        self.assertEqual(5, css.count('font-family: "Inter Ar"'))
        self.assertIn("size-adjust: 94%;", css)

    def test_the_licences_travel_with_the_files(self) -> None:
        self.build()
        for name in ("Inter-OFL.txt", "Tajawal-OFL.txt"):
            self.assertIn("SIL OPEN FONT LICENSE", (self.fonts / name).read_text(encoding="utf-8"))

    def test_a_missing_source_is_listed_and_fails_the_command(self) -> None:
        (self.brand / tf.TAJAWAL / "Tajawal-Regular.ttf").unlink()
        manifest = self.build()
        self.assertEqual([f"{tf.TAJAWAL}/Tajawal-Regular.ttf"], manifest["missing_sources"])
        self.assertEqual(8, len(manifest["faces"]))
        self.assertEqual(1, tf.main(["--brand-dir", str(self.brand), "--theme", str(self.theme)]))


class VariableInter(Fixture):
    variable = True

    def test_the_variable_font_is_preferred_and_made_once(self) -> None:
        calls = []

        def fake_variable(source, script, keep_hinting):
            calls.append(Path(source).name)
            return tf.static_woff2(source, script, keep_hinting)

        with mock.patch.object(tf, "variable_woff2", side_effect=fake_variable), \
                mock.patch.object(tf, "metric_font", side_effect=lambda face, brand, weight: tf.open_font(brand / face.source)):
            manifest = self.build()
        self.assertEqual(["InterVariable.ttf"], calls)
        inter = [f for f in manifest["faces"] if f["fontFamily"] == "Inter"]
        self.assertEqual(1, len(inter))
        self.assertEqual("300 900", inter[0]["fontWeight"])
        self.assertRegex(inter[0]["file"], r"^inter-var-latin-[0-9a-f]{8}\.woff2$")
        self.assertEqual([inter[0]["file"]], manifest["pages"]["en"])
        self.assertEqual([inter[0]["file"]], manifest["preload"]["en"])
        self.assertEqual(tf.VARIABLE_LIMITS, {"wght": (300, 900), "opsz": 14})


if __name__ == "__main__":
    unittest.main()

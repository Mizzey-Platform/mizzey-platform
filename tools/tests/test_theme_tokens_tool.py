"""`tools/theme_tokens.py` turns design tokens into the theme's token layer, tested on fixtures.

Ships with the tool (the governance pull request), so it reads no theme file: every input is a small fixture in a
temporary folder. What it proves:

- every token becomes one `--mizzey-*` custom property, a reference becomes `var()`, and the value forms the tokens
  use (font stacks, cubic Bezier, layered shadows, gradients, numbers) are written as CSS;
- a fluid token becomes the `clamp()` of FOUNDATIONS A.5, for a font size, a line height and a dimension;
- the type roles take the Latin tokens by default and the Arabic ones on `:lang(ar)`, where an Arabic role exists;
- under reduced motion every duration takes the reduced token, except those marked `keepWhenReduced`;
- `theme.json` gets the palette, gradients, font families with their faces and fallbacks, font sizes, spacing,
  radius, shadows and layout widths, and keeps every key the tool does not own;
- `--check` reports stale output and writes nothing.
"""

from __future__ import annotations

import json
import tempfile
import unittest
from pathlib import Path

from tools import theme_tokens as tt


def tok(kind, value, **ext):
    node = {"$type": kind, "$value": value, "$extensions": {"mizzey": {"status": "decided", **ext}}}
    return node


FIXTURE = {
    "color": {
        "brand": {"indigo": tok("color", "#263B73"), "white": tok("color", "#FFFFFF")},
        "role": {"focus": tok("color", "{color.brand.indigo}"), "scrim": tok("color", "rgba(16,24,48,.48)")},
    },
    "gradient": {"deep": tok("gradient", [{"color": "{color.brand.indigo}", "position": 0},
                                          {"color": "#3A55A0", "position": 1}], angle="135deg")},
    "font": {"family": {"latin": tok("fontFamily", ["Inter", "system-ui", "Segoe UI", "sans-serif"])},
             "weight": {"black": tok("fontWeight", 900), "regular": tok("fontWeight", 400)}},
    "type": {
        "latin": {
            "h2": tok("typography", {"fontFamily": "{font.family.latin}", "fontWeight": "{font.weight.black}",
                                     "fontSize": "1.9375rem", "lineHeight": 1.26, "letterSpacing": "-0.015em"},
                      fluid={"fontSize": ["1.4375rem", "1.9375rem"], "lineHeight": ["1.8125rem", "2.4375rem"]}),
            "price": tok("typography", {"fontFamily": "{font.family.latin}", "fontWeight": "{font.weight.regular}",
                                        "fontSize": "1rem", "lineHeight": 1.5, "fontFeatureSettings": '"tnum" 1'}),
        },
        "arabic": {
            "h2": tok("typography", {"fontFamily": "{font.family.latin}", "fontWeight": "{font.weight.black}",
                                     "fontSize": "2.125rem", "lineHeight": 1.41}),
        },
    },
    "space": {"1": tok("dimension", "0.25rem"), "section": tok("dimension", "4.5rem", fluid=["2.5rem", "4.5rem"])},
    "radius": {"card": tok("dimension", "20px")},
    "elevation": {"1": tok("shadow", [{"color": "rgba(38,59,115,.06)", "offsetX": "0px", "offsetY": "1px",
                                       "blur": "2px", "spread": "0px"},
                                      {"color": "{color.brand.indigo}", "offsetX": "0px", "offsetY": "0px",
                                       "blur": "0px", "spread": "1px"}])},
    "focus": {"width": tok("dimension", "2px")},
    "motion": {
        "duration": {"1": tok("duration", "80ms", keepWhenReduced=True), "6": tok("duration", "320ms"),
                     "reduced": tok("duration", "0ms")},
        "easing": {"out": tok("cubicBezier", [0.22, 1, 0.36, 1])},
        "scale": {"press": tok("number", 0.97)},
    },
    "layout": {"content-width": tok("dimension", "1272px"), "reading-width": tok("dimension", "840px")},
}
FONTS = {
    "faces": [{"fontFamily": "Inter", "fontWeight": "300 900", "fontStyle": "normal", "fontDisplay": "swap",
               "src": ["file:./assets/fonts/inter-var-latin-0123abcd.woff2"], "unicodeRange": "U+0000-00FF",
               "file": "inter-var-latin-0123abcd.woff2", "bytes": 1}],
    "fallbacks": {"Inter": ["Inter Fallback"]},
}


class Fixture(unittest.TestCase):
    def setUp(self) -> None:
        self.dir = tempfile.TemporaryDirectory()
        root = Path(self.dir.name)
        self.tokens_path = root / "tokens.json"
        self.tokens_path.write_text(json.dumps(FIXTURE), encoding="utf-8")
        self.theme = root / "theme"
        (self.theme / "assets" / "fonts").mkdir(parents=True)
        (self.theme / "assets" / "fonts" / "fonts.json").write_text(json.dumps(FONTS), encoding="utf-8")
        (self.theme / "theme.json").write_text(json.dumps({"version": 3, "settings": {"appearanceTools": True},
                                                           "styles": {"keep": "me"}}), encoding="utf-8")
        self.tokens = tt.load_tokens(self.tokens_path)
        self.css = tt.render_css(self.tokens, FONTS)
        self.json = tt.render_theme_json(json.loads((self.theme / "theme.json").read_text()), self.tokens, FONTS)

    def tearDown(self) -> None:
        self.dir.cleanup()


class CustomProperties(Fixture):
    def test_every_token_gets_one_custom_property(self) -> None:
        for path in self.tokens:
            self.assertIn(tt.css_name(path) + ":", self.css, path)

    def test_value_forms(self) -> None:
        for line in (
            "--mizzey-color-role-focus: var(--mizzey-color-brand-indigo);",
            "--mizzey-color-role-scrim: rgba(16,24,48,.48);",
            '--mizzey-font-family-latin: "Inter", "Inter Fallback", system-ui, "Segoe UI", sans-serif;',
            "--mizzey-motion-easing-out: cubic-bezier(0.22, 1, 0.36, 1);",
            "--mizzey-motion-scale-press: 0.97;",
            "--mizzey-elevation-1: 0px 1px 2px 0px rgba(38,59,115,.06), 0px 0px 0px 1px var(--mizzey-color-brand-indigo);",
            "--mizzey-gradient-deep: linear-gradient(135deg, var(--mizzey-color-brand-indigo) 0%, #3A55A0 100%);",
            '--mizzey-type-latin-price-font-feature-settings: "tnum" 1;',
        ):
            self.assertIn(line, self.css)

    def test_fluid_values_are_the_foundations_clamps(self) -> None:
        self.assertEqual("clamp(1.4375rem, 1.2518rem + 0.7619vw, 1.9375rem)", tt.fluid_clamp("1.4375rem", "1.9375rem"))
        self.assertEqual("clamp(2.0625rem, 1.6911rem + 1.5238vw, 3.0625rem)", tt.fluid_clamp("2.0625rem", "3.0625rem"))
        self.assertIn("--mizzey-type-latin-h2-font-size: clamp(1.4375rem, 1.2518rem + 0.7619vw, 1.9375rem);", self.css)
        self.assertIn("--mizzey-type-latin-h2-line-height: clamp(1.8125rem, 1.5804rem + 0.9524vw, 2.4375rem);", self.css)
        self.assertIn("--mizzey-space-section: clamp(2.5rem, 1.7571rem + 3.0476vw, 4.5rem);", self.css)

    def test_roles_are_latin_by_default_and_arabic_where_drawn(self) -> None:
        root, arabic = self.css.split(":root:lang(ar) {")
        self.assertIn("--mizzey-type-h2-font-size: var(--mizzey-type-latin-h2-font-size);", root)
        self.assertIn("--mizzey-type-price: var(--mizzey-type-latin-price);", root)
        self.assertIn("--mizzey-type-h2: var(--mizzey-type-arabic-h2);", arabic)
        self.assertNotIn("--mizzey-type-price", arabic.split("}")[0])
        self.assertIn(".mizzey-type--h2 {\n\tfont: var(--mizzey-type-h2);\n\tletter-spacing: var(--mizzey-type-h2-letter-spacing);", self.css)

    def test_reduced_motion_keeps_only_the_marked_durations(self) -> None:
        block = self.css.split("@media (prefers-reduced-motion: reduce) {")[1]
        self.assertIn("--mizzey-motion-duration-6: var(--mizzey-motion-duration-reduced);", block)
        self.assertNotIn("--mizzey-motion-duration-1:", block)
        self.assertNotIn("--mizzey-motion-duration-reduced: var", block)

    def test_an_unknown_reference_fails(self) -> None:
        with self.assertRaises(ValueError):
            tt.resolve("{color.nope}", self.tokens)


class ThemeJson(Fixture):
    def test_settings_carry_every_class_of_token(self) -> None:
        s = self.json["settings"]
        self.assertEqual({"slug": "color-role-focus", "name": "Role Focus", "color": "#263B73"}, s["color"]["palette"][2])
        self.assertEqual("linear-gradient(135deg, #263B73 0%, #3A55A0 100%)", s["color"]["gradients"][0]["gradient"])
        family = s["typography"]["fontFamilies"][0]
        self.assertEqual('"Inter", "Inter Fallback", system-ui, "Segoe UI", sans-serif', family["fontFamily"])
        self.assertEqual(["file:./assets/fonts/inter-var-latin-0123abcd.woff2"], family["fontFace"][0]["src"])
        self.assertNotIn("bytes", family["fontFace"][0])
        self.assertEqual(["type-h2", "type-price"], [f["slug"] for f in s["typography"]["fontSizes"]])
        self.assertEqual("var(--mizzey-type-h2-font-size)", s["typography"]["fontSizes"][0]["size"])
        self.assertEqual("clamp(2.5rem, 1.7571rem + 3.0476vw, 4.5rem)", s["spacing"]["spacingSizes"][1]["size"])
        self.assertEqual("20px", s["border"]["radiusSizes"][0]["size"])
        self.assertIn("0px 0px 0px 1px #263B73", s["shadow"]["presets"][0]["shadow"])
        self.assertEqual({"contentSize": "840px", "wideSize": "1272px"}, s["layout"])
        for key in ("defaultPalette", "defaultGradients"):
            self.assertIs(False, s["color"][key])

    def test_keys_the_tool_does_not_own_are_kept(self) -> None:
        self.assertEqual({"keep": "me"}, self.json["styles"])
        self.assertIs(True, self.json["settings"]["appearanceTools"])
        again = tt.render_theme_json(self.json, self.tokens, FONTS)
        self.assertEqual(self.json, again, "rendering its own output again changes nothing")


class Command(Fixture):
    def run_tool(self, *extra: str) -> int:
        return tt.main(["--tokens", str(self.tokens_path), "--theme", str(self.theme), *extra])

    def test_check_reports_stale_output_and_writes_nothing(self) -> None:
        self.assertEqual(1, self.run_tool("--check"))
        self.assertFalse((self.theme / "assets" / "css" / "tokens.css").exists())

    def test_a_written_layer_passes_the_check(self) -> None:
        self.assertEqual(0, self.run_tool())
        self.assertEqual(0, self.run_tool("--check"))
        self.assertIn(".mizzey-type--price {", (self.theme / "assets" / "css" / "tokens.css").read_text(encoding="utf-8"))


if __name__ == "__main__":
    unittest.main()

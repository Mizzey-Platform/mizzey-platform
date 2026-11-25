"""The design tokens reach the theme, and the theme's styles hold no value of their own (spec 066, slice 1, K-3).

Ships with the theme (the slice pull request). The generator itself is tested on fixtures in
`test_theme_tokens_tool.py`. This check reads the theme as checked in and fails when:

- a token in `design/tokens/tokens.json` has no `--mizzey-*` custom property, or its class of token is missing
  from `theme.json`;
- the checked-in output is not what `tools/theme_tokens.py` makes from the current tokens (a token changed and the
  tool was not re-run);
- a stylesheet in `mizzey-theme/` outside the generated layer holds a literal colour, size, time, easing or font
  name (K-3);
- any style, block attribute or class in the theme uses a physical left or right instead of a logical property, so
  one stylesheet serves both reading directions (AC-14, FIX-04a).
"""

from __future__ import annotations

import json
import re
import unittest
from pathlib import Path

from tools import theme_tokens

ROOT = Path(__file__).resolve().parents[2]
THEME = ROOT / "mizzey-theme"
TOKENS = ROOT / "design" / "tokens" / "tokens.json"
GENERATED_CSS = THEME / "assets" / "css" / "tokens.css"
GENERATED = {GENERATED_CSS, THEME / "assets" / "css" / "fonts.css"}


# The rules ---------------------------------------------------------------------------------------------------------

def _blank_comments(text: str) -> str:
    """CSS comments replaced by spaces, newlines kept, so line numbers still match."""
    return re.sub(r"/\*.*?\*/", lambda m: re.sub(r"[^\n]", " ", m.group(0)), text, flags=re.S)


def _line(text: str, index: int) -> int:
    return text.count("\n", 0, index) + 1


NAMED_COLOURS = ("white", "black", "red", "green", "blue", "orange", "grey", "gray", "navy", "indigo", "cream",
                 "yellow", "purple", "pink", "brown", "silver", "gold")
LITERALS = (
    ("hex colour", re.compile(r"#[0-9a-fA-F]{3,8}\b")),
    ("colour function", re.compile(r"\b(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch|color)\(", re.I)),
    ("named colour", re.compile(r"(?<![-\w])(?:" + "|".join(NAMED_COLOURS) + r")(?![-\w])", re.I)),
    ("size", re.compile(r"(?<![-\w.])(?:\d*\.)?\d+(?:px|rem|em|vw|vh|vmin|vmax|pt|ch|ex|svh|dvh|lvh)\b")),
    ("time", re.compile(r"(?<![-\w.])(?:\d*\.)?\d+m?s\b")),
    ("easing", re.compile(r"\bcubic-bezier\(|\blinear\(")),
)
DECLARATION = re.compile(r"(?<![-\w])([a-z-]+)\s*:\s*([^;{}]+)")


def literal_values(css: str) -> list[tuple[int, str]]:
    """(line, finding) for each literal colour, size, time, easing or font name in a stylesheet's declarations."""
    text = _blank_comments(css)
    found = []
    for block in re.finditer(r"\{([^{}]*)\}", text):
        for decl in DECLARATION.finditer(block.group(1)):
            prop, value = decl.group(1), decl.group(2)
            at = block.start(1) + decl.start()
            if prop.startswith("--"):
                continue
            for name, pattern in LITERALS:
                for hit in pattern.finditer(value):
                    if name == "size" and re.fullmatch(r"0+(?:\.0+)?[a-z]+", hit.group(0)):
                        continue
                    found.append((_line(text, at), f"{prop}: {name} {hit.group(0)}"))
            if prop in ("font-family", "font") and not re.match(r"\s*(?:var\(|inherit|initial|unset)", value):
                found.append((_line(text, at), f"{prop}: font name {value.strip()}"))
    return found


PHYSICAL = (
    re.compile(r"\b(?:margin|padding|border|scroll-margin|scroll-padding|inset)-(?:left|right)\b"),
    re.compile(r"\bborder-(?:top|bottom)-(?:left|right)-radius\b"),
    re.compile(r"(?<![-\w])\"?(?:left|right)\"?\s*:"),
    re.compile(r"\b(?:text-align|float|clear|justify-content|justify-self|justify-items)\s*:\s*(?:left|right)\b"),
    re.compile(r"\"(?:textAlign|align|justifyContent)\"\s*:\s*\"(?:left|right)\""),
    re.compile(r"\bhas-text-align-(?:left|right)\b|\bis-content-justification-(?:left|right)\b"),
    re.compile(r"\b(?:padding|margin)(?:Left|Right)\b"),
)


def physical_sides(text: str) -> list[tuple[int, str]]:
    """(line, finding) for each physical left or right in a style, a block attribute or a class."""
    clean = _blank_comments(text)
    return sorted((_line(clean, hit.start()), hit.group(0).strip())
                  for pattern in PHYSICAL for hit in pattern.finditer(clean))


def theme_stylesheets() -> list[Path]:
    """The shipped stylesheets: not the generated layer, and not the test fixtures under mizzey-theme/tests/."""
    return sorted(p for p in THEME.rglob("*.css") if p not in GENERATED and "tests" not in p.relative_to(THEME).parts)


# The checks --------------------------------------------------------------------------------------------------------

class EveryTokenReachesTheTheme(unittest.TestCase):
    def setUp(self) -> None:
        self.tokens = theme_tokens.load_tokens(TOKENS)
        self.css = GENERATED_CSS.read_text(encoding="utf-8") if GENERATED_CSS.exists() else ""
        self.theme = json.loads((THEME / "theme.json").read_text(encoding="utf-8"))

    def test_every_token_has_a_mizzey_custom_property(self) -> None:
        self.assertTrue(self.tokens, "no tokens were read, so nothing was checked")
        defined = set(re.findall(r"(--mizzey-[a-z0-9-]+)\s*:", self.css))
        missing = [path for path in self.tokens if theme_tokens.css_name(path) not in defined]
        self.assertEqual([], missing, "tokens with no --mizzey-* custom property in assets/css/tokens.css")

    def test_every_type_role_has_its_role_property_and_class(self) -> None:
        for path in self.tokens:
            if path.startswith("type."):
                role = path.split(".")[2]
                self.assertIn(f"--mizzey-type-{role}:", self.css, path)
                self.assertIn(f".mizzey-type--{role} {{", self.css, path)

    def test_every_token_class_reaches_theme_json(self) -> None:
        settings = self.theme.get("settings", {})
        found = {
            "color": {c["slug"] for c in settings.get("color", {}).get("palette", [])},
            "gradient": {g["slug"] for g in settings.get("color", {}).get("gradients", [])},
            "fontFamily": {f["slug"] for f in settings.get("typography", {}).get("fontFamilies", [])},
            "shadow": {s["slug"] for s in settings.get("shadow", {}).get("presets", [])},
            "space": {s["slug"] for s in settings.get("spacing", {}).get("spacingSizes", [])},
            "radius": {s["slug"] for s in settings.get("border", {}).get("radiusSizes", [])},
            "type": {s["slug"] for s in settings.get("typography", {}).get("fontSizes", [])},
        }
        missing = []
        for path, token in self.tokens.items():
            kind = path.split(".")[0] if path.split(".")[0] in ("space", "radius", "type") else token["$type"]
            slug = theme_tokens.slug(path)
            if kind == "type":
                slug = "type-" + path.split(".")[2]
            if kind == "color" and theme_tokens.mizzey(token).get("editorPalette", True) is False:
                self.assertNotIn(slug, found["color"], f"{path} is kept out of the editor's palette (D-35)")
                continue
            if kind in found and slug not in found[kind]:
                missing.append(path)
        self.assertEqual([], missing, "tokens whose class is in theme.json settings but which are missing from it")
        layout = settings.get("layout", {})
        self.assertEqual(self.tokens["layout.content-width"]["$value"], layout.get("wideSize"))
        self.assertEqual(self.tokens["layout.reading-width"]["$value"], layout.get("contentSize"))

    def test_the_checked_in_layer_is_what_the_generator_makes(self) -> None:
        for path, text in theme_tokens.generate(TOKENS, THEME).items():
            current = path.read_text(encoding="utf-8") if path.exists() else ""
            self.assertEqual(text, current, f"{path.name} is stale: run python tools/theme_tokens.py")

    def test_the_focus_ring_is_two_pixels_indigo_on_light_and_white_on_indigo(self) -> None:
        self.assertIn("--mizzey-focus-width: 2px;", self.css)
        self.assertIn("--mizzey-color-role-focus: var(--mizzey-color-brand-royal-indigo);", self.css)
        self.assertIn("--mizzey-color-role-focus-inverse: var(--mizzey-color-brand-white);", self.css)


class NoLiteralValuesOutsideTheTokenLayer(unittest.TestCase):
    def test_no_theme_stylesheet_holds_a_literal_colour_size_or_font(self) -> None:
        sheets = theme_stylesheets()
        self.assertTrue(sheets, "no hand-written stylesheet was found, so nothing was checked")
        problems = [f"{p.relative_to(ROOT).as_posix()}:{n}: {f}"
                    for p in sheets for n, f in literal_values(p.read_text(encoding="utf-8"))]
        self.assertEqual([], problems, "a literal value outside the generated token layer (K-3)")

    def test_the_rule_tells_a_literal_from_a_token(self) -> None:
        self.assertEqual([], literal_values("a { color: var(--mizzey-color-role-text); margin: 0; }"))
        self.assertEqual([], literal_values("/* #fff 12px Arial */ a { inline-size: 100%; white-space: nowrap; }"))
        for bad in ("color: #263B73", "color: rgb(0 0 0)", "padding: 12px", "font-size: 1.25rem",
                    "font-family: Inter", "gap: 2em", "color: white", "color: hsl(0 0% 0%)",
                    "transition: opacity 200ms", "transition-timing-function: cubic-bezier(0, 0, 1, 1)"):
            self.assertTrue(literal_values("a { " + bad + "; }"), bad)


class NoPhysicalLeftOrRight(unittest.TestCase):
    def test_no_style_in_the_theme_uses_a_physical_side(self) -> None:
        sources = sorted(p for kind in ("*.css", "*.html", "*.php", "*.json") for p in THEME.rglob(kind))
        problems = [f"{p.relative_to(ROOT).as_posix()}:{n}: {f}"
                    for p in sources for n, f in physical_sides(p.read_text(encoding="utf-8"))]
        self.assertEqual([], problems, "use logical properties (inline-start, inline-end), not left or right")

    def test_the_rule_catches_each_physical_form(self) -> None:
        for bad in ("margin-left: 0", "padding-right: 0", "border-left: 0", "left: 0", "right:0",
                    "text-align: left", "text-align:right", "float: left", "clear: right",
                    "border-top-left-radius: 0", "\"padding-left\": \"0\"", "scroll-margin-right: 0",
                    "\"textAlign\":\"right\"", "has-text-align-left"):
            self.assertTrue(physical_sides("a { " + bad + "; }"), bad)
        for good in ("margin-inline-start: 0", "inset-inline-end: 0", "text-align: start",
                     "border-start-start-radius: 0", "/* left: 0 */ a{}", "aria-label=\"Move left\""):
            self.assertEqual([], physical_sides(good), good)


if __name__ == "__main__":
    unittest.main()

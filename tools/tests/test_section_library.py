"""The section library's blocks follow the pattern of spec 066 (slice 1, "The section library"; SSC-01, NFR-04a, AC-14).

A library section is one complete block in `mizzey-site/src/Blocks/<name>/`, registered from its `block.json`, with
no JavaScript build step: the editor script is a plain script on the `wp.*` globals and the storefront is rendered
by PHP. This check reads the blocks as checked in and fails when:

- a block directory is not listed in `SectionLibrary::SECTIONS`, or a listed one has no `block.json`;
- a section is not a dynamic block of the library's category and text domain, does not carry the common
  "Show on the site" setting (`visible`, a boolean, shown by default), or lets staff change its design (colour,
  type, spacing, alignment, custom classes or raw HTML), which is fixed in code (G11);
- a setting stores a side as left or right instead of inline-start or inline-end;
- a block stylesheet holds a literal colour, size, time or font instead of a token, or a physical left or right;
- an editor script needs a build (an `import`, JSX or a `package.json`), shows an English sentence outside a
  `wp.i18n` call, or translates in another text domain (a section's storefront module, `view.js`, is checked for
  the build and the sentences too, but runs without the `wp.*` globals);
- the theme lets the editor's colour pickers offer more than the approved palette (custom colours, custom
  gradients, the default palettes, duotone).
"""

from __future__ import annotations

import json
import re
import unittest
from pathlib import Path

from tools.tests.test_theme_tokens import literal_values, physical_sides

ROOT = Path(__file__).resolve().parents[2]
BLOCKS = ROOT / "mizzey-site" / "src" / "Blocks"
LIBRARY = BLOCKS / "SectionLibrary.php"
THEME_JSON = ROOT / "mizzey-theme" / "theme.json"
DESIGN_SUPPORTS = ("color", "typography", "spacing", "dimensions", "border", "shadow", "background", "position",
                   "layout", "align", "alignWide", "customClassName", "className", "html", "filter", "interactivity")


def block_dirs() -> list[Path]:
    return sorted(p.parent for p in BLOCKS.glob("*/block.json"))


def listed_sections() -> list[str]:
    text = LIBRARY.read_text(encoding="utf-8") if LIBRARY.exists() else ""
    match = re.search(r"const\s+SECTIONS\s*=\s*\[([^\]]*)\]", text)
    return re.findall(r"'([a-z0-9-]+)'", match.group(1)) if match else []


def editor_scripts() -> list[Path]:
    """The editor scripts: every script of the library but a section's storefront module (view.js), which runs
    without the editor's wp.* globals."""
    return sorted(p for p in BLOCKS.rglob("*.js") if p.name != "view.js")


I18N_CALL = re.compile(r"\b(?:__|_x|_n|_nx|sprintf)\(\s*(?:'(?:[^'\\]|\\.)*'|\"(?:[^\"\\]|\\.)*\")")
STRING = re.compile(r"'((?:[^'\\]|\\.)*)'|\"((?:[^\"\\]|\\.)*)\"")


def untranslated_sentences(js: str) -> list[str]:
    """String literals a person could read (two words or more) that are not the first argument of a wp.i18n call."""
    clean = re.sub(r"/\*.*?\*/|^\s*//.*$", "", js, flags=re.S | re.M)
    clean = I18N_CALL.sub("", clean)
    found = []
    for match in STRING.finditer(clean):
        text = match.group(1) if match.group(1) is not None else match.group(2)
        if re.fullmatch(r"[a-z0-9 _./:#-]*", text) or not re.search(r"[A-Za-z]{2,}\s+[A-Za-z]{2,}", text):
            continue
        found.append(text)
    return found


def is_orange(colour: str) -> bool:
    """An orange or orange-red a reader would see as the brand's orange: hue 5 to 40, saturated, mid lightness."""
    import colorsys
    match = re.fullmatch(r"#([0-9a-fA-F]{6})", colour.strip())
    if not match:
        return False
    r, g, b = (int(match.group(1)[i:i + 2], 16) / 255 for i in (0, 2, 4))
    hue, light, sat = colorsys.rgb_to_hls(r, g, b)
    return 5 <= hue * 360 <= 40 and sat >= 0.6 and 0.35 <= light <= 0.8


class EveryBlockIsALibrarySection(unittest.TestCase):
    def test_the_library_lists_exactly_the_block_directories(self) -> None:
        dirs = [p.name for p in block_dirs()]
        self.assertTrue(dirs, "no library block was found, so nothing was checked")
        self.assertEqual(sorted(dirs), sorted(listed_sections()), "SectionLibrary::SECTIONS and src/Blocks/*/block.json differ")

    def test_each_section_is_a_complete_dynamic_block_with_the_common_show_setting(self) -> None:
        for directory in block_dirs():
            with self.subTest(block=directory.name):
                meta = json.loads((directory / "block.json").read_text(encoding="utf-8"))
                self.assertEqual(f"mizzey/{directory.name}", meta.get("name"))
                self.assertEqual(3, meta.get("apiVersion"))
                self.assertEqual("mizzey-sections", meta.get("category"))
                self.assertEqual("mizzey-site", meta.get("textdomain"))
                self.assertEqual("file:./render.php", meta.get("render"), "the storefront is rendered on the server")
                self.assertTrue((directory / "render.php").exists())
                self.assertEqual({"type": "boolean", "default": True}, meta.get("attributes", {}).get("visible"),
                                 "the common Show on the site setting")
                supports = meta.get("supports", {})
                opened = [key for key in DESIGN_SUPPORTS if supports.get(key) not in (None, False)]
                self.assertEqual([], opened, "a section's design is fixed in code; staff edit its content only")
                for key in ("html", "customClassName", "className"):
                    self.assertIs(False, supports.get(key), f"supports.{key} must be switched off")
                self.assertIn("mizzey-section-library-editor", json.dumps(meta.get("editorStyle", [])),
                              "the editor shows a hidden section as hidden")

    def test_no_setting_stores_a_physical_side(self) -> None:
        for directory in block_dirs():
            meta = json.loads((directory / "block.json").read_text(encoding="utf-8"))
            text = json.dumps(meta.get("attributes", {}))
            self.assertIsNone(re.search(r"\"(?:left|right)\"", text), f"{directory.name}: use inline-start or inline-end")


class BlockStylesAreTokensAndLogical(unittest.TestCase):
    def test_no_block_stylesheet_holds_a_literal_value_or_a_physical_side(self) -> None:
        sheets = sorted(BLOCKS.rglob("*.css"))
        self.assertTrue(sheets, "no block stylesheet was found, so nothing was checked")
        problems = [f"{p.relative_to(ROOT).as_posix()}:{n}: {f}" for p in sheets
                    for n, f in literal_values(p.read_text(encoding="utf-8")) + physical_sides(p.read_text(encoding="utf-8"))]
        self.assertEqual([], problems)

    def test_no_block_script_or_template_uses_a_physical_side(self) -> None:
        files = sorted([*BLOCKS.rglob("*.js"), *BLOCKS.rglob("*.php"), *BLOCKS.rglob("*.json")])
        problems = [f"{p.relative_to(ROOT).as_posix()}:{n}: {f}" for p in files
                    for n, f in physical_sides(p.read_text(encoding="utf-8"))]
        self.assertEqual([], problems)


class EditorScriptsNeedNoBuild(unittest.TestCase):
    def test_no_build_step_is_needed_or_added(self) -> None:
        self.assertFalse((ROOT / "package.json").exists() or (ROOT / "mizzey-site" / "package.json").exists(),
                         "this repository has no JavaScript build step")
        scripts = editor_scripts()
        self.assertTrue(scripts, "no editor script was found, so nothing was checked")
        for script in scripts:
            text = script.read_text(encoding="utf-8")
            with self.subTest(script=script.name):
                self.assertIsNone(re.search(r"^\s*import\s|\brequire\(", text, re.M), "plain script: no import or require")
                self.assertIsNone(re.search(r"return\s*\(?\s*<[A-Za-z]", text), "no JSX")
                self.assertIn("window.wp", text, "the editor script uses the wp.* globals")

    def test_a_storefront_module_needs_no_build_and_shows_no_words_of_its_own(self) -> None:
        for script in sorted(BLOCKS.rglob("view.js")):
            text = script.read_text(encoding="utf-8")
            with self.subTest(script=script.parent.name):
                self.assertIsNone(re.search(r"^\s*import\s|require\(", text, re.M), "plain module: no import or require")
                self.assertEqual([], untranslated_sentences(text), "the words a visitor reads come from the server, translated")

    def test_every_sentence_is_translated_in_the_site_domain(self) -> None:
        for script in editor_scripts():
            text = script.read_text(encoding="utf-8")
            with self.subTest(script=script.name):
                self.assertEqual([], untranslated_sentences(text), "a sentence outside wp.i18n")
                domains = re.findall(r"\b(?:__|_x)\(\s*(?:'(?:[^'\\]|\\.)*'|\"(?:[^\"\\]|\\.)*\")\s*,\s*(?:'([^']*)'|\"([^\"]*)\"|(\w+))", text)
                wrong = [d for d in domains if (d[0] or d[1] or d[2]) not in ("mizzey-site", "DOMAIN")]
                self.assertEqual([], wrong, "translate in the mizzey-site text domain")

    def test_the_sentence_rule_tells_a_sentence_from_an_identifier(self) -> None:
        self.assertEqual([], untranslated_sentences("el('ul', { className: 'mizzey-trust__list alignfull' });"))
        self.assertEqual([], untranslated_sentences("__('Show on the site', 'mizzey-site')"))
        self.assertEqual(["Show on the site"], untranslated_sentences("label: 'Show on the site'"))


class TheEditorOffersOnlyTheApprovedPalette(unittest.TestCase):
    def test_custom_colours_gradients_and_duotone_are_off(self) -> None:
        color = json.loads(THEME_JSON.read_text(encoding="utf-8"))["settings"]["color"]
        for key in ("custom", "customGradient", "customDuotone", "defaultPalette", "defaultGradients", "defaultDuotone"):
            self.assertIs(False, color.get(key), f"settings.color.{key}")
        self.assertEqual([], color.get("duotone"), "no duotone presets")
        self.assertTrue(color.get("palette"), "the approved palette is offered")

    def test_the_site_logo_is_cores_with_custom_logo_support(self) -> None:
        functions = (ROOT / "mizzey-theme" / "functions.php").read_text(encoding="utf-8")
        self.assertRegex(functions, r"add_theme_support\(\s*'custom-logo'", "the theme supports core's custom logo")
        own = [p for p in (ROOT / "mizzey-site" / "src").rglob("*.php") if "mizzey_logo" in p.read_text(encoding="utf-8")]
        self.assertEqual([], own, "no second logo setting beside core's")

    def test_no_orange_is_offered_to_editors(self) -> None:
        # D-35: Persimmon is a fill in fixed places only, never text; an editor's colour picker could make it text.
        palette = json.loads(THEME_JSON.read_text(encoding="utf-8"))["settings"]["color"]["palette"]
        self.assertTrue(palette, "no palette was read, so nothing was checked")
        offered = [entry["slug"] for entry in palette if is_orange(entry["color"])]
        self.assertEqual([], offered, "an orange colour in the editor's palette (D-35)")

    def test_the_orange_rule_tells_orange_from_the_other_colours(self) -> None:
        for orange in ("#FF6A4A", "#E65A42", "#C63D1F", "#EE5A3B", "#ff8800"):
            self.assertTrue(is_orange(orange), orange)
        for other in ("#263B73", "#CD2949", "#916300", "#FDEFD4", "#F3F1E9", "#1E1E1E", "rgba(38,59,115,.08)"):
            self.assertFalse(is_orange(other), other)


if __name__ == "__main__":
    unittest.main()

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
  `wp.i18n` call, or translates in another text domain;
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
    return sorted(BLOCKS.rglob("*.js"))


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


if __name__ == "__main__":
    unittest.main()

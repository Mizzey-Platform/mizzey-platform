"""Every word spec 066 adds is translated into Arabic and compiled (K-4; NFR-04a, FIX-04a).

The theme and the section library translate in the `mizzey-site` domain, whose Arabic catalogue is
`mizzey-site/languages/mizzey-site-ar.po`, compiled to `mizzey-site-ar.l10n.php` with
`wp i18n make-php mizzey-site/languages/mizzey-site-ar.po` and, for the editor scripts, to one JSON file per script
with `wp i18n make-json mizzey-site/languages/mizzey-site-ar.po` (named by the md5 of the script's path inside the
plugin, which is why their references in the `.po` are written from the plugin's root), and listed in
`mizzey-site.pot`. This check reads every
string the theme (`functions.php`, `inc/`) and the section library (`mizzey-site/src/Blocks/`, its PHP, its editor
scripts and its `block.json` files) pass to a translation function, and fails when one has no Arabic in the `.po`,
is missing from the compiled file or the template, or a plural has fewer than Arabic's six forms.

`theme.json`'s preset names (colours, sizes, spacing, shadows) are not in scope: they are labels of the editor's
pickers, and the administration is English only (ADM-159).
"""

from __future__ import annotations

import json
import re
import subprocess
import shutil
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
LANG = ROOT / "mizzey-site" / "languages"
PO = LANG / "mizzey-site-ar.po"
POT = LANG / "mizzey-site.pot"
COMPILED = LANG / "mizzey-site-ar.l10n.php"
SOURCES = [ROOT / "mizzey-theme" / "functions.php", *sorted((ROOT / "mizzey-theme" / "inc").glob("*.php")),
           *sorted((ROOT / "mizzey-site" / "src" / "Blocks").rglob("*.php")),
           *sorted((ROOT / "mizzey-site" / "src" / "Blocks").rglob("*.js"))]
BLOCK_JSON = sorted((ROOT / "mizzey-site" / "src" / "Blocks").glob("*/block.json"))

Q = r"""(?:'((?:[^'\\]|\\.)*)'|"((?:[^"\\]|\\.)*)")"""
SINGLE = re.compile(r"\b(?:__|_e|esc_html__|esc_attr__|esc_html_e|esc_attr_e)\(\s*" + Q + r"\s*,\s*'mizzey-site'")
CONTEXT = re.compile(r"\b(?:_x|esc_html_x|esc_attr_x)\(\s*" + Q + r"\s*,\s*" + Q + r"\s*,\s*'mizzey-site'")
PLURAL = re.compile(r"\b_n\(\s*" + Q + r"\s*,\s*" + Q + r"\s*,[^,]+,\s*'mizzey-site'")


def unquote(raw: str) -> str:
    return re.sub(r"\\(['\"\\])", r"\1", raw)


def pick(match: re.Match, first: int) -> str:
    return unquote(match.group(first) if match.group(first) is not None else match.group(first + 1))


def strings_in(path: Path) -> set[tuple[str | None, str, str | None]]:
    text = path.read_text(encoding="utf-8")
    return ({(None, pick(m, 1), None) for m in SINGLE.finditer(text)}
            | {(pick(m, 3), pick(m, 1), None) for m in CONTEXT.finditer(text)}
            | {(None, pick(m, 1), pick(m, 3)) for m in PLURAL.finditer(text)})


def wanted() -> set[tuple[str | None, str, str | None]]:
    """(context, msgid, plural) of every string the slice's code translates."""
    out: set[tuple[str | None, str, str | None]] = set()
    for path in SOURCES:
        text = path.read_text(encoding="utf-8")
        out |= {(None, pick(m, 1), None) for m in SINGLE.finditer(text)}
        out |= {(pick(m, 3), pick(m, 1), None) for m in CONTEXT.finditer(text)}
        out |= {(None, pick(m, 1), pick(m, 3)) for m in PLURAL.finditer(text)}
    for path in BLOCK_JSON:
        meta = json.loads(path.read_text(encoding="utf-8"))
        out.add(("block title", meta["title"], None))
        out.add(("block description", meta["description"], None))
        out |= {("block keyword", word, None) for word in meta.get("keywords", [])}
    return out


def po_entries(path: Path) -> dict[tuple[str | None, str], list[str]]:
    """(context, msgid) to its msgstr forms, from a .po or .pot file."""
    def joined(block: str, key: str) -> str | None:
        match = re.search(rf'^{key} ((?:".*"\n?)+)', block, re.M)
        if not match:
            return None
        return "".join(re.findall(r'"((?:[^"\\]|\\.)*)"', match.group(1))).replace('\\"', '"').replace("\\n", "\n")

    entries = {}
    for block in path.read_text(encoding="utf-8").split("\n\n"):
        block += "\n"
        msgid = joined(block, "msgid")
        if not msgid:
            continue
        forms = [joined(block, rf"msgstr\[{i}\]") for i in range(6)]
        forms = [f for f in forms if f is not None] or [joined(block, "msgstr") or ""]
        entries[(joined(block, "msgctxt"), msgid)] = forms
    return entries


class EveryStringOfTheSliceIsInArabic(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.wanted = wanted()
        cls.po = po_entries(PO)
        cls.pot = po_entries(POT)

    def test_the_slice_translates_something(self) -> None:
        self.assertGreater(len(self.wanted), 20, "the extraction found too few strings, so it is probably broken")

    def test_each_has_arabic_in_the_po(self) -> None:
        missing = []
        for context, msgid, plural in sorted(self.wanted, key=str):
            forms = self.po.get((context, msgid))
            need = 6 if plural else 1
            if not forms or len([f for f in forms if f.strip()]) < need:
                missing.append(f"{context + ' | ' if context else ''}{msgid}")
        self.assertEqual([], missing, "no Arabic for these strings in mizzey-site-ar.po")

    def test_each_is_in_the_template(self) -> None:
        missing = [msgid for context, msgid, _ in self.wanted if (context, msgid) not in self.pot]
        self.assertEqual([], sorted(missing), "missing from mizzey-site.pot")

    def test_each_editor_script_string_is_in_its_json(self) -> None:
        import hashlib
        missing = []
        for script in (p for p in SOURCES if p.suffix == ".js"):
            relative = script.relative_to(ROOT / "mizzey-site").as_posix()
            json_file = LANG / f"mizzey-site-ar-{hashlib.md5(relative.encode()).hexdigest()}.json"
            messages = json.loads(json_file.read_text(encoding="utf-8"))["locale_data"]["messages"] if json_file.is_file() else {}
            for context, msgid, _ in strings_in(script):
                key = (f"{context}\x04" if context else "") + msgid
                if not any(form for form in messages.get(key, [])):
                    missing.append(f"{relative}: {msgid}")
        self.assertEqual([], sorted(missing), "missing from the editor scripts' JSON: run wp i18n make-json")

    def test_each_is_compiled(self) -> None:
        php = shutil.which("php")
        self.assertIsNotNone(php, "the PHP command line reads the compiled file")
        done = subprocess.run([php, "-r", "echo json_encode(array_keys((require $argv[1])['messages']));", str(COMPILED)],
                              capture_output=True, text=True, encoding="utf-8")
        keys = set(json.loads(done.stdout))
        js_only = set().union(*(strings_in(p) for p in SOURCES if p.suffix == ".js"))
        js_only -= set().union(*(strings_in(p) for p in SOURCES if p.suffix == ".php"))
        missing = []
        for context, msgid, plural in self.wanted - js_only:
            key = (f"{context}\x04" if context else "") + msgid + (f"\x00{plural}" if plural else "")
            if key not in keys and ((f"{context}\x04" if context else "") + msgid) not in keys:
                missing.append(msgid)
        self.assertEqual([], sorted(missing), "missing from mizzey-site-ar.l10n.php: run wp i18n make-php")


if __name__ == "__main__":
    unittest.main()

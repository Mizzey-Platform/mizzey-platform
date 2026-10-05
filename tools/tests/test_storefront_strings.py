"""No hard-coded user-facing text in the storefront this engagement builds (NFR-04a, FR-007).

Why this exists, and why now. NFR-04a is one line of contract, "No hard-coded text anywhere in the storefront
interface", and it is violated one string at a time across every later slice. Today `mizzey-site/src` and
`mizzey-theme` contain **no** user-facing strings at all, which makes this the cheapest possible moment to put the
check in place and the most expensive moment to skip it.

Why it lives in `tools/tests/` rather than `tools/`. `tools/*.py` is the `tooling` path class, which a
`requirement` pull request may not touch; `tools/tests/` is `test-infra`, which it may. Verified against
`tools/scope_trace.py`, not assumed. The same constraint moved the backlog-totals logic into a test.

What it judges, and what it refuses to judge. Only this engagement's own code: `mizzey-site/src/`,
`mizzey-site/mizzey-site.php` and `mizzey-theme/`. Never WordPress, WooCommerce, WPML or Corex, whose strings are
their authors' business, and never the test harness, whose output is for developers.

It is deliberately narrow about what counts as user-facing, because a check that cries wolf gets switched off. A
string literal is reported only when it reads like a sentence a visitor could see: it contains a space or ends in
punctuation, it has at least two letters, and it is not any of the many kinds of identifier that happen to be
written as strings. Everything else is ignored, and the ignore rules are listed in one place below so they can be
argued with.

A literal inside a **logging** call is exempt for the same reason a translated one is: it is not interface. The
first run of this check flagged two diagnostics in `CostTranslationSync`, both arguments to `self::log()`, which
a developer reads in an error log and a visitor never sees; translating them would make a defect harder to
diagnose. The exemption is narrow and tested in both directions, because it is the obvious place for a hole:
`wp_die`, `echo`, `print` and an assignment all still fail on the same sentence.

It fails closed. A PHP file it cannot read is a failure, not a skip, because a check that silently skips is how
the board-metadata check once exempted the five largest PBIs.
"""

from __future__ import annotations

import re
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]

# This engagement's own storefront code, and nothing else.
SCANNED = ("mizzey-site/src", "mizzey-site/mizzey-site.php", "mizzey-theme")
# The harness is developer-facing: its output is evidence, not interface.
EXCLUDED = ("mizzey-site/tests",)

# WordPress translation functions. A literal inside one of these is translatable by definition.
TRANSLATION_FUNCTIONS = (
    "__", "_e", "_x", "_ex", "_n", "_nx", "_n_noop", "_nx_noop",
    "esc_html__", "esc_html_e", "esc_html_x",
    "esc_attr__", "esc_attr_e", "esc_attr_x",
    "translate", "translate_with_gettext_context",
)
CALL = re.compile(r"\b(" + "|".join(re.escape(f) for f in TRANSLATION_FUNCTIONS) + r")\s*\(")

# Logging calls. A literal here is read by a developer in an error log, never by a visitor, and NFR-04a governs
# the storefront interface. Translating a diagnostic would make a defect harder to diagnose, not easier.
# Narrow on purpose: wp_die, echo, print and a template do reach a visitor and are not listed.
LOGGING = re.compile(
    r"(?:\b(?:error_log|trigger_error)\s*\()"
    r"|(?:(?:self|static|parent)::(?:log|debug|info|notice|warning|error|critical)\s*\()"
    r"|(?:->\s*(?:log|debug|info|notice|warning|error|critical)\s*\()"
)

# A single- or double-quoted PHP string literal, with escapes.
LITERAL = re.compile(r"""(?P<q>['"])(?P<body>(?:\\.|(?!\1)[^\\])*)(?P=q)""")

# Not user-facing, however sentence-like they look. Each entry is a reason, not a convenience.
IGNORE_EXACT = {
    "mizzey-site",          # the text domain itself
    "utf-8", "UTF-8",
}
IGNORE_PATTERNS = (
    re.compile(r"^[a-z0-9_]+$"),                         # hook, option, meta and capability names
    re.compile(r"^[A-Za-z0-9_]+\\[A-Za-z0-9_\\]+$"),     # class names
    re.compile(r"^[a-z0-9-]+/[a-z0-9-/.]+$"),            # paths, REST routes, plugin files
    re.compile(r"^https?://"),                           # URLs
    re.compile(r"^[.#]?[a-z][a-z0-9-]*(\s+[.#]?[a-z][a-z0-9-]*)*$"),  # CSS classes and selectors
    re.compile(r"^[^A-Za-z]*$"),                         # no letters at all
    re.compile(r"^%[sd]"),                               # bare format specifiers
    re.compile(r"^[A-Za-z0-9_-]+$"),                     # any single identifier-shaped token
)


def php_files(root: Path = ROOT) -> list[Path]:
    out: list[Path] = []
    for target in SCANNED:
        p = root / target
        if p.is_file() and p.suffix == ".php":
            out.append(p)
        elif p.is_dir():
            out.extend(sorted(p.rglob("*.php")))
    return [f for f in out
            if not any(f.relative_to(root).as_posix().startswith(x) for x in EXCLUDED)]


def strip_noise(line: str) -> str:
    """Remove the parts of a line that cannot hold a user-facing string: comments, and declare/namespace/use."""
    if re.match(r"\s*(declare|namespace|use|require|require_once|include|include_once)\b", line):
        return ""
    line = re.sub(r"//.*$", "", line)
    line = re.sub(r"#(?!\[).*$", "", line)
    return line


def looks_user_facing(text: str) -> bool:
    if text in IGNORE_EXACT:
        return False
    if len(re.findall(r"[A-Za-z]", text)) < 2:
        return False
    # Markup, not interface text. A literal that is only tags and format specifiers has no words a visitor
    # reads: `<link rel="canonical" href="%s" />` is a template, while `<p>Add to cart</p>` is a sentence in a
    # wrapper and still counts. Found by running the check against the #242 SEO output classes.
    without_markup = re.sub(r"<[^>]*>", " ", text)
    without_markup = re.sub(r"%[sd\d.]*", " ", without_markup)
    if len(re.findall(r"[A-Za-z]", without_markup)) < 2:
        return False
    if any(p.match(text) for p in IGNORE_PATTERNS):
        return False
    # A sentence a visitor could read has a space, or ends in sentence punctuation.
    return " " in text.strip() or text.rstrip().endswith((".", "!", "?", ":"))


def _call_spans(line: str, pattern: re.Pattern[str]) -> list[tuple[int, int]]:
    """Character ranges of this line covered by a call matching `pattern`."""
    spans = []
    for m in pattern.finditer(line):
        depth, i = 0, m.end() - 1
        while i < len(line):
            if line[i] == "(":
                depth += 1
            elif line[i] == ")":
                depth -= 1
                if depth == 0:
                    break
            i += 1
        spans.append((m.start(), i if i < len(line) else len(line)))
    return spans


def exempt_spans(line: str) -> list[tuple[int, int]]:
    """Ranges where a literal is not storefront interface text: translated, or written to a log."""
    return _call_spans(line, CALL) + _call_spans(line, LOGGING)


def findings(root: Path = ROOT) -> list[str]:
    """`path:line: text` for every hard-coded user-facing string, and for every file that cannot be read."""
    out: list[str] = []
    for path in php_files(root):
        rel = path.relative_to(root).as_posix()
        try:
            source = path.read_text(encoding="utf-8")
        except (OSError, UnicodeDecodeError) as exc:
            # Fail closed. An unreadable file is not a clean file.
            out.append(f"{rel}: cannot be read, so it cannot be cleared: {exc}")
            continue
        in_block_comment = False
        open_exempt = 0
        for n, raw in enumerate(source.splitlines(), start=1):
            line = raw
            if in_block_comment:
                if "*/" not in line:
                    continue
                line = line.split("*/", 1)[1]
                in_block_comment = False
            while "/*" in line:
                before, _, after = line.partition("/*")
                if "*/" in after:
                    line = before + after.split("*/", 1)[1]
                else:
                    line = before
                    in_block_comment = True
                    break
            line = strip_noise(line)
            if not line.strip():
                continue
            spans = exempt_spans(line)
            # A call can span lines, as sprintf() in a log call usually does. If a line opens an exempt call and
            # does not close it, the following lines stay exempt until the parentheses balance.
            if spans and spans[-1][1] >= len(line) - 1 and line.count("(") > line.count(")"):
                open_exempt = line.count("(") - line.count(")")
            elif open_exempt:
                open_exempt += line.count("(") - line.count(")")
                spans = [(0, len(line))]
                if open_exempt <= 0:
                    open_exempt = 0
            for m in LITERAL.finditer(line):
                if any(a <= m.start() <= b for a, b in spans):
                    continue
                body = m.group("body")
                if looks_user_facing(body):
                    out.append(f"{rel}:{n}: {body!r} is user-facing and not translatable")
    return out


class StorefrontStrings(unittest.TestCase):
    def test_the_storefront_has_no_hard_coded_user_facing_text(self):
        problems = findings()
        self.assertEqual(problems, [], "\n".join(["NFR-04a:", *problems]))

    def test_something_is_actually_scanned(self):
        """A check that scans nothing passes everything. The file list is the check's own precondition."""
        files = php_files()
        self.assertTrue(files, f"no PHP found under {SCANNED}; the scan would pass by doing nothing")
        rels = {f.relative_to(ROOT).as_posix() for f in files}
        self.assertIn("mizzey-site/src/I18n/Localisation.php", rels)
        self.assertIn("mizzey-theme/functions.php", rels)
        self.assertFalse([r for r in rels if r.startswith("mizzey-site/tests")],
                         "the test harness is developer-facing and must not be judged")

    def test_a_hard_coded_string_is_caught_with_its_file_and_line(self):
        with self.subTest("the exact failure NFR-04a describes"):
            found = self._scan_snippet("<?php\necho 'Add to basket';\n")
            self.assertEqual(len(found), 1, found)
            self.assertIn(":2:", found[0])
            self.assertIn("Add to basket", found[0])

    def test_a_translated_string_passes(self):
        for snippet in (
            "<?php\necho esc_html__('Add to basket', 'mizzey-site');\n",
            "<?php\n_e('Add to basket', 'mizzey-site');\n",
            "<?php\necho sprintf(esc_html__('Showing %d items', 'mizzey-site'), 3);\n",
            "<?php\necho esc_html(_n('One item', '%d items', $n, 'mizzey-site'));\n",
        ):
            with self.subTest(snippet=snippet.splitlines()[1].strip()):
                self.assertEqual(self._scan_snippet(snippet), [])

    def test_identifiers_are_not_mistaken_for_interface_text(self):
        """A check that cries wolf gets switched off, so the quiet cases matter as much as the loud one."""
        for snippet in (
            "<?php\nadd_action('woocommerce_before_main_content', 'cb');\n",
            "<?php\nupdate_option('mizzey_launch_mode', 'live');\n",
            "<?php\n$x = get_post_meta($id, '_cogs_total_value', true);\n",
            "<?php\nregister_rest_route('mizzey/v1', '/status', []);\n",
            "<?php\n$class = 'wp-block-group alignfull';\n",
            "<?php\nload_theme_textdomain('mizzey-site', get_stylesheet_directory() . '/languages');\n",
            "<?php\n// A comment with a whole sentence in it, which is not interface text.\n$a = 1;\n",
            "<?php\n/* A block comment sentence. */\n$a = 1;\n",
            "<?php\necho current_user_can('manage_woocommerce') ? 1 : 0;\n",
        ):
            with self.subTest(snippet=snippet.splitlines()[1].strip()[:52]):
                self.assertEqual(self._scan_snippet(snippet), [])

    def test_a_log_message_is_not_storefront_interface(self):
        """Found by running the check: CostTranslationSync logs two diagnostics, read by developers only."""
        for snippet in (
            "<?php\nself::log('Cost was written to a translation, so the original value was kept.');\n",
            "<?php\nerror_log('Could not copy the cost to its translation.');\n",
            "<?php\n$logger->warning('Stock diverged between a product and its translation.');\n",
            "<?php\nself::log(sprintf(\n    'Cost %s was written to product %d, which is a translation.',\n"
            "    $a,\n    $b\n));\n",
        ):
            with self.subTest(snippet=snippet.splitlines()[1].strip()[:52]):
                self.assertEqual(self._scan_snippet(snippet), [])

    def test_text_that_does_reach_a_visitor_still_fails(self):
        """The logging exemption must not become a hole. These all render to someone."""
        for snippet in (
            "<?php\nwp_die('Your cart could not be loaded.');\n",
            "<?php\necho 'Your cart could not be loaded.';\n",
            "<?php\nprint 'Your cart could not be loaded.';\n",
            "<?php\n$message = 'Your cart could not be loaded.';\n",
        ):
            with self.subTest(snippet=snippet.splitlines()[1].strip()[:52]):
                found = self._scan_snippet(snippet)
                self.assertEqual(len(found), 1, f"expected one finding, got {found}")
                self.assertIn("could not be loaded", found[0])

    def test_markup_without_words_is_not_interface_text(self):
        """Found by running the check against #242 SEO output: a tag template has no words to translate."""
        for snippet in (
            "<?php\nprintf('<link rel=\"canonical\" href=\"%s\" />', $url);\n",
            "<?php\nprintf('<meta name=\"description\" content=\"%s\" />', $d);\n",
            "<?php\necho '<div class=\"wrap\"></div>';\n",
        ):
            with self.subTest(snippet=snippet.splitlines()[1].strip()[:50]):
                self.assertEqual(self._scan_snippet(snippet), [])

    def test_a_sentence_inside_markup_still_counts(self):
        """The markup exemption must not become a hole: words in a wrapper are still words."""
        for snippet in (
            "<?php\necho '<p>Your cart could not be loaded.</p>';\n",
            "<?php\nprintf('<span>%s items in your basket</span>', $n);\n",
        ):
            with self.subTest(snippet=snippet.splitlines()[1].strip()[:50]):
                found = self._scan_snippet(snippet)
                self.assertEqual(len(found), 1, f"expected one finding, got {found}")

    def test_an_unreadable_file_fails_rather_than_being_skipped(self):
        """A check that silently skips is how the board-metadata check once exempted its five largest rows."""
        import tempfile

        with tempfile.TemporaryDirectory() as d:
            root = Path(d)
            (root / "mizzey-site" / "src").mkdir(parents=True)
            (root / "mizzey-site" / "src" / "Broken.php").write_bytes(b"<?php echo '\xff\xfe not utf8';")
            found = self._scan_in(root)
            self.assertTrue(any("cannot be read" in f for f in found), found)

    # ---- helpers ----------------------------------------------------------------------------------------
    def _scan_snippet(self, php: str) -> list[str]:
        import tempfile

        with tempfile.TemporaryDirectory() as d:
            root = Path(d)
            (root / "mizzey-site" / "src").mkdir(parents=True)
            (root / "mizzey-site" / "src" / "Snippet.php").write_text(php, encoding="utf-8")
            return self._scan_in(root)

    def _scan_in(self, root: Path) -> list[str]:
        # The root is a parameter, not a patched global: a mutated module global breaks under a parallel runner
        # and leaks between cases if an assertion raises.
        return findings(root)


if __name__ == "__main__":
    unittest.main()

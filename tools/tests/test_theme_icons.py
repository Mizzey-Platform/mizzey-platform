"""The icon set the boards draw is one set of inline SVG icons that take their colour from the text (spec 066, slice 1).

Why this exists. C1-System, section 10, draws the line icons (24 px, 2 px stroke, round caps, the colour of their
ground), and C1-Components uses a few more (the filled heart, the tick that draws, the alert, the image glyph and
the empty-state grid). `mizzey-theme/inc/icons.php` holds them in one place. This check renders every icon through
PHP and fails when an icon the boards draw is missing, when an icon carries a colour of its own instead of
`currentColor` (the two-tone knock-outs take a token through a class), when a decorative icon is not hidden from
assistive technology or a labelled one has no accessible name, or when the menu icon's third line is not the
logo's wave.

It needs the PHP command line. Without it the check fails, it does not skip.
"""

from __future__ import annotations

import json
import re
import shutil
import subprocess
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
ICONS = ROOT / "mizzey-theme" / "inc" / "icons.php"
BOARD_ICONS = (
    # C1-System, section 10
    "search", "cart", "heart", "user", "userf", "menu", "home", "homef", "grid", "gridf", "help", "returns",
    "shield", "cod", "card", "wallet", "lock", "filter", "sort", "close", "back", "fwd", "down", "up", "plus",
    "minus", "check", "star", "pin", "restart", "share", "play", "pause", "trash", "tag",
    # C1-Components, beyond the system set
    "heart-filled", "tick", "alert", "image", "grid-empty",
)
WAVE = "M4 16.6C4.5 18.2 5.3 18.85 6.5 18.85C8.4 18.85 9.8 17.2 11.2 17.2C12.6 17.2 13.1 19 14.6 19C16.6 19 18.4 17.4 20 15.8"

RENDER = r"""
define('ABSPATH', __DIR__);
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
require $argv[1];
$out = ['names' => \MizzeySite\Theme\Icons\names(), 'plain' => [], 'labelled' => [],
        'unknown' => \MizzeySite\Theme\Icons\icon('no-such-icon')];
foreach (\MizzeySite\Theme\Icons\names() as $n) {
    $out['plain'][$n] = \MizzeySite\Theme\Icons\icon($n);
    $out['labelled'][$n] = \MizzeySite\Theme\Icons\icon($n, ['label' => 'A "label"', 'class' => 'extra']);
}
echo json_encode($out);
"""


def render() -> dict:
    php = shutil.which("php")
    if php is None:
        raise AssertionError("the PHP command line is needed to render the icons")
    done = subprocess.run([php, "-r", RENDER, str(ICONS)], capture_output=True, text=True, encoding="utf-8")
    if done.returncode != 0:
        raise AssertionError(done.stderr or done.stdout)
    return json.loads(done.stdout)


class IconSet(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.out = render()

    def test_every_icon_the_boards_draw_is_in_the_set(self) -> None:
        self.assertEqual([], sorted(set(BOARD_ICONS) - set(self.out["names"])))

    def test_every_icon_takes_its_colour_from_the_text(self) -> None:
        for name, svg in self.out["plain"].items():
            self.assertNotRegex(svg, r"#[0-9a-fA-F]{3,8}\b|rgb|hsl", name)
            colours = set(re.findall(r'(?:fill|stroke)="([^"]+)"', svg)) - {"none", "currentColor"}
            self.assertEqual(set(), colours, name)

    def test_a_decorative_icon_is_hidden_and_a_labelled_one_is_named(self) -> None:
        for name, svg in self.out["plain"].items():
            self.assertIn('aria-hidden="true"', svg, name)
            self.assertIn('focusable="false"', svg, name)
            self.assertIn('class="mizzey-icon mizzey-icon--' + name + '"', svg, name)
        for name, svg in self.out["labelled"].items():
            self.assertIn('role="img"', svg, name)
            self.assertIn('aria-label="A &quot;label&quot;"', svg, name)
            self.assertNotIn("aria-hidden", svg, name)
            self.assertIn("mizzey-icon--" + name + " extra", svg, name)

    def test_the_menu_icon_carries_the_logo_wave(self) -> None:
        self.assertIn(f'd="{WAVE}"', self.out["plain"]["menu"])

    def test_an_unknown_icon_renders_nothing(self) -> None:
        self.assertEqual("", self.out["unknown"])


if __name__ == "__main__":
    unittest.main()

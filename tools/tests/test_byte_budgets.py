"""What a storefront page loads of our own code stays inside its byte budget (spec 066, slice 1, K-6; D-21).

D-21 sets "byte budgets for scripts, styles and images per template", to be "set from the approved design in the
first storefront pull request, then enforced". The design's foundations budget the fonts (checked in
`test_theme_fonts.py`) and 8 KB of motion JavaScript, and say nothing of the rest; the numbers below are this slice's
proposal, sized to leave the later slices of spec 066 room, and are the owner's to confirm.

Counted, gzip-compressed at level 9 (what a server sends; KB is 1024 bytes):

- **Every page**: the theme's stylesheets (tokens, fonts, base, components), the theme's behaviour (`ui.js`), the
  WordPress Interactivity API runtime it imports, and the site plugin's storefront scripts that can load on any page
  (consent default and banner, tag manager, events, language cache). `ui.js` and the runtime load on every page on
  purpose: the header's drawers, which slice 2 builds, are on every page.
- **Home**: every page, plus every library section's stylesheet and the artwork the theme ships for them, as if every
  section were placed at once.

Not counted: WordPress's and WooCommerce's own assets, which this repository does not ship, the web fonts (their
own budget), and the content images staff upload.

The Interactivity runtime is read from a WordPress install when one is found (MIZZEY_WP_PATH, or app/wp in a folder
above the repository); otherwise its size at the pinned WordPress version is used, as recorded below.
"""

from __future__ import annotations

import gzip
import os
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
THEME = ROOT / "mizzey-theme"
SITE = ROOT / "mizzey-site"
KB = 1024
RUNTIME = Path("wp-includes/js/dist/script-modules/interactivity/index.min.js")
RUNTIME_RECORDED = 16070  # gzip bytes of the runtime at WordPress 7.1.5, measured 25 November 2026

BUDGETS = {
    "every page": {"styles": 40 * KB, "scripts": 45 * KB, "images": 0},
    "home": {"styles": 60 * KB, "scripts": 45 * KB, "images": 60 * KB},
}


def gz(data: bytes) -> int:
    return len(gzip.compress(data, 9))


def runtime_size() -> tuple[int, str]:
    roots = [Path(os.environ["MIZZEY_WP_PATH"])] if os.environ.get("MIZZEY_WP_PATH") else []
    roots += [d / "app" / "wp" for d in ROOT.parents]
    for root in roots:
        if (root / RUNTIME).is_file():
            return gz((root / RUNTIME).read_bytes()), str(root / RUNTIME)
    return RUNTIME_RECORDED, "recorded"


def template_files() -> dict[str, dict[str, list[Path]]]:
    every = {
        "styles": [THEME / "assets" / "css" / f"{name}.css" for name in ("tokens", "fonts", "base", "components")],
        "scripts": [THEME / "assets" / "js" / "ui.js"]
        + [SITE / "assets" / name for name in ("consent-default.js", "consent.js", "gtm.js", "events.js", "language-cache.js")],
        "images": [],
    }
    home = {
        "styles": every["styles"] + sorted((SITE / "src" / "Blocks").glob("*/style.css")),
        "scripts": list(every["scripts"]),
        "images": sorted((THEME / "assets" / "images").glob("*")),
    }
    return {"every page": every, "home": home}


def totals() -> dict[str, dict[str, int]]:
    runtime, _ = runtime_size()
    out = {}
    for template, kinds in template_files().items():
        out[template] = {kind: sum(gz(p.read_bytes()) for p in files) for kind, files in kinds.items()}
        out[template]["scripts"] += runtime
    return out


class EachTemplateStaysInsideItsBudget(unittest.TestCase):
    def test_every_counted_file_exists(self) -> None:
        missing = [str(p) for kinds in template_files().values() for files in kinds.values() for p in files if not p.is_file()]
        self.assertEqual([], missing)

    def test_styles_scripts_and_images_are_inside_the_budget(self) -> None:
        over = []
        for template, sizes in totals().items():
            for kind, size in sizes.items():
                if size > BUDGETS[template][kind]:
                    over.append(f"{template} {kind}: {size / KB:.1f} KB gzip, budget {BUDGETS[template][kind] / KB:.0f} KB")
        self.assertEqual([], over, "over the D-21 byte budget")

    def test_the_sum_counts_compressed_bytes(self) -> None:
        self.assertLess(gz(b"a" * 10000), 100)
        self.assertGreater(totals()["home"]["styles"], totals()["every page"]["styles"])


if __name__ == "__main__":
    for template, sizes in totals().items():
        print(template, {kind: f"{size / KB:.1f} KB" for kind, size in sizes.items()}, "runtime:", runtime_size()[1])

"""What a storefront page loads of our own code stays inside its byte budget (spec 066, slice 1, K-6; D-21).

D-21 sets "byte budgets for scripts, styles and images per template", to be "set from the approved design in the
first storefront pull request, then enforced". The design's foundations budget the fonts (checked in
`test_theme_fonts.py`) and 8 KB of motion JavaScript, and say nothing of the rest; the numbers below are this slice's
proposal, sized to leave the later slices of spec 066 room, and are the owner's to confirm.

Counted, gzip-compressed at level 9 (what a server sends; KB is 1024 bytes):

- **Every page**: the theme's stylesheets (tokens, fonts, base, components), the theme's behaviour (`ui.js`, and
  `early-taps.js`, printed inline in the head), the
  WordPress Interactivity API runtime it imports, and the site plugin's storefront scripts that can load on any page
  (consent default and banner, tag manager, events, language cache). `ui.js` and the runtime load on every page on
  purpose: the header's drawers, which slice 2 builds, are on every page.
- **Product**: every page, plus the header, the gallery and buying area (slice 9) and the product card and rail its
  lower part carries (slice 10). The buying area's modules fetched only after a press (`zoom.js`, the zoom's gestures,
  on the first zoom; `motion.js`, the flight to the cart and the share popover's place, on the first press) are not
  in the page's load. The van in the delivery box is slice 1's image, counted on the home page.
- **Listing** (slice 6): every page, plus the header, the category listing's stylesheet and script, and the product
  card and grid it carries.
- **Home**: every page, plus the header (slice 2), every library section's stylesheet and storefront module, the
  styles they share, the product card's and rail's stylesheets and script (slice 4), and the artwork the theme ships
  for them, as if every section were placed at once (slice 5).

Not counted: WordPress's and WooCommerce's own assets, which this repository does not ship, the web fonts (their
own budget), and the content images staff upload.

The Interactivity runtime is read from a WordPress install when one is found (MIZZEY_WP_PATH, or app/wp in a folder
above the repository); otherwise its size at the pinned WordPress version is used, as recorded below.
"""

from __future__ import annotations

import gzip
import os
import re
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
    "product": {"styles": 60 * KB, "scripts": 45 * KB, "images": 0},
    "listing": {"styles": 60 * KB, "scripts": 45 * KB, "images": 0},
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
    # Slice 3: the consent banner's look, the footer and the strip are on every page, with the script of the strip's
    # slider and the cookie preferences.
    every = {
        "styles": [THEME / "assets" / "css" / f"{name}.css" for name in ("tokens", "fonts", "base", "components", "consent", "footer", "strip")],
        "scripts": [THEME / "assets" / "js" / name for name in ("early-taps.js", "ui.js", "site-parts.js")]
        + [SITE / "assets" / name for name in ("consent-default.js", "consent.js", "gtm.js", "events.js", "language-cache.js")],
        "images": [],
    }
    blocks = SITE / "src" / "Blocks"
    cards = SITE / "src" / "Storefront" / "blocks"
    home = {
        "styles": every["styles"] + [THEME / "assets" / "css" / "header.css", blocks / "library.css"]
        + sorted(blocks.glob("*/style.css")) + sorted(cards.glob("*/style.css")),
        "scripts": list(every["scripts"]) + [THEME / "assets" / "js" / name for name in ("header.js", "after-load.js")]
        + sorted(blocks.glob("*/view.js")) + sorted(cards.glob("*/view.js")),
        "images": sorted((THEME / "assets" / "images").glob("*")),
    }
    # Slice 9: the product page is every page, the header, the gallery and buying area, and the product card and rail
    # its lower part carries (slice 10).
    buy = SITE / "src" / "Storefront" / "product"
    product = {
        "styles": every["styles"] + [THEME / "assets" / "css" / "header.css"] + sorted(cards.glob("*/style.css"))
        + sorted(buy.glob("*/style.css")),
        "scripts": list(every["scripts"]) + [THEME / "assets" / "js" / "header.js"] + sorted(cards.glob("*/view.js"))
        + sorted(buy.glob("*/view.js")),
        "images": [],
    }
    # Slice 6: a category listing is every page, the header, the listing's own stylesheet and script, and the product
    # card and the grid it carries (the rail's stylesheet, in its grid layout).
    listing = {
        "styles": every["styles"] + [THEME / "assets" / "css" / name for name in ("header.css", "listing.css")]
        + [cards / "product-card" / "style.css", cards / "product-rail" / "style.css"],
        "scripts": list(every["scripts"]) + [THEME / "assets" / "js" / name for name in ("header.js", "listing.js")]
        + [cards / "product-card" / "view.js"],
        "images": [],
    }
    return {"every page": every, "home": home, "product": product, "listing": listing}


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

    def test_the_early_taps_script_printed_in_the_head_stays_small(self) -> None:
        # As inc/performance.php prints it: no header comment, no whitespace next to punctuation. It is in every page's
        # head before anything else, so it is held to a few hundred bytes compressed.
        source = (THEME / "assets" / "js" / "early-taps.js").read_text(encoding="utf-8")
        source = re.sub(r"^\s*/\*\*.*?\*/", "", source, flags=re.S)
        source = re.sub(r"\s*([(){};,=:!&|<>?+\-])\s*", r"\1", re.sub(r"\s+", " ", source)).strip()
        self.assertNotIn("/*", source)
        # The punctuation is kept, only the space around it goes: the size checked is the size printed.
        self.assertTrue(source.startswith("(function(d){var root=d.documentElement,"), source[:60])
        self.assertLessEqual(len(source.encode()), 1536, "early-taps.js printed")
        self.assertLessEqual(gz(source.encode()), 700, "early-taps.js printed, gzip")

    def test_the_sum_counts_compressed_bytes(self) -> None:
        self.assertLess(gz(b"a" * 10000), 100)
        self.assertGreater(totals()["home"]["styles"], totals()["every page"]["styles"])


if __name__ == "__main__":
    for template, sizes in totals().items():
        print(template, {kind: f"{size / KB:.1f} KB" for kind, size in sizes.items()}, "runtime:", runtime_size()[1])

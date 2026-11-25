"""The product page's gallery and buying area follow the rules of spec 066 (slice 9; AC-6, AC-11, AC-14, K-3, K-4).

The browser test (t112) proves the page on a running site; this check reads the code as checked in and fails when:

- the block is not a dynamic block of the site's text domain with the Interactivity API on, or is offered in the
  inserter (the product template places it, G11), or the template does not place it;
- its stylesheet holds a literal colour, size, time or font, or a physical left or right (K-3, AC-14);
- its script shows a sentence of its own (NFR-04a), needs a build, imports anything but the Interactivity API, or
  claims an add before the server answers;
- a word it shows has no Arabic translation in mizzey-site-ar.po (K-4);
- the stock it shows writes anything: it reads WooCommerce's figure and never changes stock (PDP-06, ERP-07);
- an option is assumed to be a size or a colour (PDP-05: options are generic).
"""

from __future__ import annotations

import json
import re
import unittest
from pathlib import Path

from tools.tests.test_product_card import TRANSLATED, po_translations
from tools.tests.test_section_library import untranslated_sentences
from tools.tests.test_theme_tokens import literal_values, physical_sides

ROOT = Path(__file__).resolve().parents[2]
BUY = ROOT / "mizzey-site" / "src" / "Storefront" / "ProductBuy.php"
BLOCK = ROOT / "mizzey-site" / "src" / "Storefront" / "product" / "buy"
TEMPLATE = ROOT / "mizzey-theme" / "templates" / "single-product.html"


class TheBlockIsDynamicAndPlacedByTheTemplate(unittest.TestCase):
    def test_the_block_is_dynamic_interactive_and_not_in_the_inserter(self) -> None:
        meta = json.loads((BLOCK / "block.json").read_text(encoding="utf-8"))
        self.assertEqual("mizzey/product-buy", meta["name"])
        self.assertEqual(3, meta["apiVersion"])
        self.assertEqual("mizzey-site", meta["textdomain"])
        self.assertEqual("file:./render.php", meta["render"])
        self.assertEqual("file:./style.css", meta["style"], "a block loads only its own stylesheet (K-6)")
        self.assertEqual("file:./view.js", meta["viewScriptModule"])
        self.assertIs(True, meta["supports"].get("interactivity"))
        self.assertIs(False, meta["supports"].get("inserter"))

    def test_the_product_template_places_it_and_leaves_room_for_the_lower_page(self) -> None:
        html = TEMPLATE.read_text(encoding="utf-8")
        self.assertIn("<!-- wp:mizzey/product-buy /-->", html)
        self.assertIn("mizzey-product-page__details", html, "slice 10's place under the buying area")
        self.assertIn("wp:woocommerce/store-notices", html, "WooCommerce's own notices (a refused Buy Now) show")
        self.assertNotIn("legacy-template", html)


class StylesAreTokensAndLogical(unittest.TestCase):
    def test_no_literal_value_or_physical_side(self) -> None:
        css = (BLOCK / "style.css").read_text(encoding="utf-8")
        self.assertEqual([], literal_values(css) + physical_sides(css))

    def test_no_script_template_or_class_uses_a_physical_side(self) -> None:
        files = [BUY, TEMPLATE, *BLOCK.glob("*.js"), *BLOCK.glob("*.php"), *BLOCK.glob("*.json")]
        problems = [f"{p.name}:{n}: {f}" for p in files for n, f in physical_sides(p.read_text(encoding="utf-8"))]
        self.assertEqual([], problems)


class TheScriptShowsOnlyTheServersWords(unittest.TestCase):
    def setUp(self) -> None:
        self.js = (BLOCK / "view.js").read_text(encoding="utf-8")

    def test_no_sentence_of_its_own(self) -> None:
        self.assertEqual([], untranslated_sentences(self.js))

    def test_one_import_the_interactivity_api_and_no_build(self) -> None:
        self.assertEqual(["@wordpress/interactivity"], re.findall(r"^\s*import .* from '([^']+)';", self.js, re.M))
        self.assertIn("@wordpress/interactivity", (BLOCK / "view.asset.php").read_text(encoding="utf-8"))

    def test_nothing_claims_success_before_the_answer(self) -> None:
        send = self.js[self.js.index("async function send"):]
        send = send[:send.index("\n}\n")]
        answered = send.index("response.json()")
        for claim in ("classList.add( 'is-done' )", "mizzey-cart-changed", "toast( 'added' )", "cartLink.hidden = false"):
            self.assertGreater(send.index(claim), answered, f"{claim} only after the server answers")


class EveryWordIsTranslated(unittest.TestCase):
    def test_every_word_the_buying_area_shows_has_an_arabic_translation(self) -> None:
        translations = po_translations()
        words = {m.group(1).replace("\\'", "'") for m in TRANSLATED.finditer(BUY.read_text(encoding="utf-8"))}
        self.assertGreater(len(words), 40, "no translatable word was found, so nothing was checked")
        missing = sorted(w for w in words if not translations.get(w))
        self.assertEqual([], missing, "add these to mizzey-site-ar.po and run wp i18n make-php")


class StockIsReadAndOptionsAreGeneric(unittest.TestCase):
    def setUp(self) -> None:
        self.code = re.sub(r"/\*.*?\*/|//.*$", "", BUY.read_text(encoding="utf-8"), flags=re.S | re.M)

    def test_the_stock_shown_writes_nothing(self) -> None:
        for write in (r"->set_", r"->save\(", r"update_post_meta", r"wc_update_product_stock", r"StockGateway"):
            self.assertIsNone(re.search(write, self.code), write)

    def test_no_option_is_assumed_to_be_a_size_or_a_colour(self) -> None:
        for source in (self.code, (BLOCK / "view.js").read_text(encoding="utf-8")):
            self.assertIsNone(re.search(r"pa_size|pa_colou?r|'Size'|'Colou?r'", source))


if __name__ == "__main__":
    unittest.main()

"""The product card and the product rail follow the rules of spec 066 (slice 4; AC-5, AC-6, AC-14, K-3, K-4).

The browser test (t107) proves the states on a running site; this check reads the code as checked in and fails when:

- a card or rail block is not a dynamic block of the site's text domain with the Interactivity API on, or is offered
  in the inserter (the card is placed by code and by templates, not as a free block, G11);
- a card or rail stylesheet holds a literal colour, size, time or font, or a physical left or right (K-3, AC-14);
- the card's script shows a sentence of its own (every word comes from the server, translated: NFR-04a), needs a
  build, or imports anything but the Interactivity API;
- a word the card or the rail shows has no Arabic translation in mizzey-site-ar.po (K-4);
- the card's stock state writes anything: it reads WooCommerce's figure and never changes stock (PLP-03, ERP-07).
"""

from __future__ import annotations

import json
import re
import unittest
from pathlib import Path

from tools.tests.test_section_library import untranslated_sentences
from tools.tests.test_theme_tokens import literal_values, physical_sides

ROOT = Path(__file__).resolve().parents[2]
STOREFRONT = ROOT / "mizzey-site" / "src" / "Storefront"
BLOCKS = STOREFRONT / "blocks"
CARD = STOREFRONT / "ProductCard.php"
RAIL = STOREFRONT / "ProductRail.php"
STOCK = ROOT / "mizzey-site" / "src" / "Stock" / "CardStock.php"
PO = ROOT / "mizzey-site" / "languages" / "mizzey-site-ar.po"
NAMES = {"product-card": "mizzey/product-card", "product-rail": "mizzey/product-rail"}

TRANSLATED = re.compile(r"\b(?:__|esc_html__|esc_attr__|_n|_x)\(\s*'((?:[^'\\]|\\.)*)'")


def po_translations() -> dict[str, str]:
    text = PO.read_text(encoding="utf-8")
    found = {}
    for match in re.finditer(r'^msgid "((?:[^"\\]|\\.)*)"\n(?:msgid_plural "(?:[^"\\]|\\.)*"\n)?msgstr(?:\[0\])? "((?:[^"\\]|\\.)*)"', text, re.M):
        found[match.group(1).replace('\\"', '"')] = match.group(2)
    return found


class TheBlocksAreDynamicAndPlacedByCode(unittest.TestCase):
    def test_each_block_is_dynamic_interactive_and_not_in_the_inserter(self) -> None:
        for directory, name in NAMES.items():
            with self.subTest(block=directory):
                meta = json.loads((BLOCKS / directory / "block.json").read_text(encoding="utf-8"))
                self.assertEqual(name, meta["name"])
                self.assertEqual(3, meta["apiVersion"])
                self.assertEqual("mizzey-site", meta["textdomain"])
                self.assertEqual("file:./render.php", meta["render"])
                self.assertTrue((BLOCKS / directory / "render.php").exists())
                self.assertEqual("file:./style.css", meta["style"], "a block loads only its own stylesheet (K-6)")
                supports = meta["supports"]
                self.assertIs(True, supports.get("interactivity"))
                self.assertIs(False, supports.get("inserter"))
                self.assertIs(False, supports.get("html"))

    def test_the_card_takes_its_product_from_the_loop_or_its_attribute(self) -> None:
        meta = json.loads((BLOCKS / "product-card" / "block.json").read_text(encoding="utf-8"))
        self.assertIn("postId", meta["usesContext"])
        self.assertEqual("integer", meta["attributes"]["productId"]["type"])
        self.assertEqual("file:./view.js", meta["viewScriptModule"])

    def test_the_rail_has_the_two_layouts_and_stores_no_side(self) -> None:
        meta = json.loads((BLOCKS / "product-rail" / "block.json").read_text(encoding="utf-8"))
        self.assertEqual(["rail", "grid"], meta["attributes"]["layout"]["enum"])
        self.assertIsNone(re.search(r"\"(?:left|right)\"", json.dumps(meta)))


class StylesAreTokensAndLogical(unittest.TestCase):
    def test_no_literal_value_or_physical_side(self) -> None:
        sheets = sorted(BLOCKS.rglob("*.css"))
        self.assertEqual(2, len(sheets), "the card's and the rail's stylesheets")
        problems = [f"{p.relative_to(ROOT).as_posix()}:{n}: {f}" for p in sheets
                    for n, f in literal_values(p.read_text(encoding="utf-8")) + physical_sides(p.read_text(encoding="utf-8"))]
        self.assertEqual([], problems)

    def test_no_script_template_or_class_uses_a_physical_side(self) -> None:
        files = [CARD, RAIL, STOCK, *BLOCKS.rglob("*.js"), *BLOCKS.rglob("*.php"), *BLOCKS.rglob("*.json")]
        problems = [f"{p.relative_to(ROOT).as_posix()}:{n}: {f}" for p in files
                    for n, f in physical_sides(p.read_text(encoding="utf-8"))]
        self.assertEqual([], problems)


class TheScriptShowsOnlyTheServersWords(unittest.TestCase):
    def setUp(self) -> None:
        self.js = (BLOCKS / "product-card" / "view.js").read_text(encoding="utf-8")

    def test_no_sentence_of_its_own(self) -> None:
        self.assertEqual([], untranslated_sentences(self.js))

    def test_one_import_the_interactivity_api_and_no_build(self) -> None:
        imports = re.findall(r"^\s*import .* from '([^']+)';", self.js, re.M)
        self.assertEqual(["@wordpress/interactivity"], imports)
        self.assertIsNone(re.search(r"return\s*\(?\s*<[A-Za-z]", self.js), "no JSX")
        asset = (BLOCKS / "product-card" / "view.asset.php").read_text(encoding="utf-8")
        self.assertIn("@wordpress/interactivity", asset)

    def test_nothing_claims_success_before_the_answer(self) -> None:
        send = self.js[self.js.index("function* send"):]
        send = send[:send.index("\n}\n")]
        answered = send.index("response.json()")
        for claim in ("context.added = true", "mizzey-cart-changed", "toast( 'added' )"):
            self.assertGreater(send.index(claim), answered, f"{claim} only after the server answers")


class EveryWordIsTranslated(unittest.TestCase):
    def test_every_word_the_card_and_rail_show_has_an_arabic_translation(self) -> None:
        translations = po_translations()
        words = set()
        for source in (CARD, RAIL):
            words.update(m.group(1).replace("\\'", "'") for m in TRANSLATED.finditer(source.read_text(encoding="utf-8")))
        self.assertTrue(words, "no translatable word was found, so nothing was checked")
        missing = sorted(w for w in words if not translations.get(w))
        self.assertEqual([], missing, "add these to mizzey-site-ar.po and run wp i18n make-php")

    def test_the_four_labels_are_translated(self) -> None:
        translations = po_translations()
        for label in ("New", "Trending", "Limited", "Best Seller"):
            self.assertTrue(translations.get(label), label)


class TheStockStateOnlyReads(unittest.TestCase):
    def test_card_stock_writes_nothing(self) -> None:
        code = re.sub(r"/\*.*?\*/|//.*$", "", STOCK.read_text(encoding="utf-8"), flags=re.S | re.M)
        for write in (r"->set_", r"->save\(", r"update_post_meta", r"wc_update_product_stock", r"->adjust\(", r"StockGateway"):
            self.assertIsNone(re.search(write, code), write)
        self.assertIn("wc_get_low_stock_amount", code, "'Only N left' follows WooCommerce's low-stock amount")


if __name__ == "__main__":
    unittest.main()

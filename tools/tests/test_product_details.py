"""The lower product page follows the rules of spec 066 (slice 10; AC-12, AC-11 for PDP-12 and PDP-13, AC-14, K-3, K-4).

The browser test (t116) proves the page on a running site; this check reads the code as checked in and fails when:

- the block is not a dynamic block of the site's text domain, or is offered in the inserter (the product template
  places it, G11), or the template does not place it inside the place slice 9 left;
- its stylesheet holds a literal colour, size, time or font, or a physical left or right (K-3, AC-14);
- its script shows a sentence of its own (NFR-04a) or imports anything;
- a word it shows has no Arabic translation in mizzey-site-ar.po (K-4);
- the reviews it reads are not only the approved ones, or it writes a review, a rating, a photo or a list;
- the rails are not slice 4's rail.
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
DETAILS = ROOT / "mizzey-site" / "src" / "Storefront" / "ProductDetails.php"
REVIEWS = ROOT / "mizzey-site" / "src" / "Storefront" / "ProductReviews.php"
BLOCK = ROOT / "mizzey-site" / "src" / "Storefront" / "product" / "details"
TEMPLATE = ROOT / "mizzey-theme" / "templates" / "single-product.html"


def code(path: Path) -> str:
    text = re.sub(r"/\*.*?\*/", "", path.read_text(encoding="utf-8"), flags=re.S)
    return re.sub(r"(?<![:'\"])//[^\n]*", "", text)


class TheBlockIsDynamicAndPlacedByTheTemplate(unittest.TestCase):
    def test_the_block_is_dynamic_and_not_in_the_inserter(self) -> None:
        meta = json.loads((BLOCK / "block.json").read_text(encoding="utf-8"))
        self.assertEqual("mizzey/product-details", meta["name"])
        self.assertEqual(3, meta["apiVersion"])
        self.assertEqual("mizzey-site", meta["textdomain"])
        self.assertEqual("file:./render.php", meta["render"])
        self.assertEqual("file:./style.css", meta["style"], "a block loads only its own stylesheet (K-6)")
        self.assertEqual("file:./view.js", meta["viewScriptModule"])
        self.assertIs(False, meta["supports"].get("inserter"))

    def test_the_template_places_it_in_the_place_slice_9_left(self) -> None:
        html = TEMPLATE.read_text(encoding="utf-8")
        place = re.search(r'<div class="wp-block-group mizzey-product-page__details">(.*?)</div>', html, re.S)
        self.assertIsNotNone(place)
        self.assertIn("<!-- wp:mizzey/product-details /-->", place.group(1))
        self.assertLess(html.index("wp:mizzey/product-buy"), html.index("wp:mizzey/product-details"))


class StylesAreTokensAndLogical(unittest.TestCase):
    def test_no_literal_value_or_physical_side(self) -> None:
        css = (BLOCK / "style.css").read_text(encoding="utf-8")
        self.assertEqual([], literal_values(css) + physical_sides(css))

    def test_no_script_template_or_class_uses_a_physical_side(self) -> None:
        files = [DETAILS, REVIEWS, TEMPLATE, *BLOCK.glob("*.js"), *BLOCK.glob("*.php"), *BLOCK.glob("*.json")]
        problems = [f"{p.name}:{n}: {f}" for p in files for n, f in physical_sides(p.read_text(encoding="utf-8"))]
        self.assertEqual([], problems)

    def test_motion_has_its_reduced_form(self) -> None:
        css = (BLOCK / "style.css").read_text(encoding="utf-8")
        self.assertIn("prefers-reduced-motion: reduce", css)
        self.assertIn("prefers-reduced-motion: reduce", (BLOCK / "view.js").read_text(encoding="utf-8"))


class TheScriptsShowOnlyTheServersWords(unittest.TestCase):
    def setUp(self) -> None:
        self.scripts = {p.name: p.read_text(encoding="utf-8") for p in BLOCK.glob("*.js")}

    def test_three_scripts_the_page_view_the_photo_viewer_and_the_review_entry(self) -> None:
        self.assertEqual({"view.js", "viewer.js", "entry.js"}, set(self.scripts))

    def test_no_sentence_of_their_own_no_import_no_build(self) -> None:
        for name, js in self.scripts.items():
            self.assertEqual([], untranslated_sentences(js), name)
            self.assertEqual([], re.findall(r"^\s*import ", js, re.M), name)

    def test_the_page_and_the_viewer_send_nothing(self) -> None:
        for name in ("view.js", "viewer.js"):
            for call in ("fetch(", "XMLHttpRequest", "sendBeacon", "localStorage", "document.cookie"):
                self.assertNotIn(call, self.scripts[name], name)

    def test_the_entry_thanks_only_after_the_server_answers(self) -> None:
        js = self.scripts["entry.js"]
        self.assertEqual(2, js.count("fetch("), "the review, then its photos")
        self.assertLess(js.rindex("await answer("), js.index("form.dataset.done"))
        self.assertIn("is-busy", js)

    def test_the_viewer_and_the_entry_load_only_where_they_are_needed(self) -> None:
        source = code(DETAILS)
        self.assertIn("'mizzey-site/review-photos'", source)
        self.assertIn("'mizzey-site/review-entry'", source)
        self.assertLess(source.index("if ($total > 0 && function_exists('wp_enqueue_script_module'))"), source.index("'mizzey-site/review-photos'"))


class EveryWordIsTranslated(unittest.TestCase):
    def test_every_word_the_lower_page_shows_has_an_arabic_translation(self) -> None:
        translations = po_translations()
        words = set()
        for path in (DETAILS, REVIEWS):
            words |= {m.group(1).replace("\\'", "'") for m in TRANSLATED.finditer(path.read_text(encoding="utf-8"))}
        self.assertGreater(len(words), 30, "no translatable word was found, so nothing was checked")
        missing = sorted(w for w in words if not translations.get(w))
        self.assertEqual([], missing, "add these to mizzey-site-ar.po and run wp i18n make-php")


class OnlyApprovedReviewsAreReadAndNothingIsWritten(unittest.TestCase):
    def test_the_reviews_are_the_approved_ones_and_their_shown_photos(self) -> None:
        source = code(REVIEWS)
        self.assertIn("'status'   => 'approve'", source)
        self.assertIn("comment_approved !== '1'", source)
        self.assertIn("ReviewPhotos::visible(", source)

    def test_nothing_writes_a_review_a_rating_a_photo_or_a_list(self) -> None:
        for path in (DETAILS, REVIEWS):
            source = code(path)
            for write in (r"update_comment_meta", r"wp_insert_comment", r"wp_update_comment", r"wp_set_comment_status",
                          r"update_post_meta", r"update_user_meta", r"->save\(", r"->set_", r"RecentlyViewed::record"):
                self.assertIsNone(re.search(write, source), f"{path.name}: {write}")

    def test_the_rails_are_slice_4s_and_read_the_lists_of_specs_036_and_015(self) -> None:
        source = code(DETAILS)
        self.assertIn("'mizzey/product-rail'", source)
        self.assertIn("get_upsell_ids()", source)
        self.assertIn("RecentlyViewed::products(", source)


if __name__ == "__main__":
    unittest.main()

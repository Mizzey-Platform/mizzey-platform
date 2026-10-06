"""The site plugin itself declares that a product's stored cost is copied to its translations.

Why this exists. WooCommerce Multilingual copies a custom field to a new translation only when WPML holds a
setting for it. For the stored product cost, `_cogs_total_value`, that setting used to arrive only in a
configuration WPML downloads from its publisher's host. On a runtime that could not make the request, a cost that
was on a product before its Arabic record was created did not reach the Arabic record in three of four cases
(issue #252, measured on staging on 5 October 2026). `mizzey-site/wpml-config.xml` now declares the setting, so the
result no longer depends on a download.

A declaration of this kind was removed once before, on 4 October 2026, as "changing no outcome". It had declared
`_cogs_value`, which is the name of the admin form field and of the accessor, and is not a stored key. So this
check holds both halves: the stored key is declared as copied, and the name that is not stored is not declared in
its place.

What it cannot check: that WPML applies the file. That is scenario t29, on the baseline built without the
download (`MIZZEY_WPML_REMOTE_CONFIG=off`), which needs a runtime and does not run in CI.
"""

from __future__ import annotations

import unittest
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DECLARATION = ROOT / "mizzey-site" / "wpml-config.xml"


class CostFieldDeclaration(unittest.TestCase):
    def test_the_stored_cost_key_is_declared_as_copied_and_the_form_field_name_is_not(self):
        self.assertTrue(DECLARATION.is_file(), "mizzey-site/wpml-config.xml is missing")
        fields = {el.text.strip(): el.get("action") for el in ET.parse(DECLARATION).getroot().iter("custom-field")}
        self.assertEqual("copy", fields.get("_cogs_total_value"))
        self.assertNotIn("_cogs_value", fields)


if __name__ == "__main__":
    unittest.main()

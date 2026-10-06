"""Staging and development end their baselines with the same WPML settings, and the staging tool says so.

Why this exists. On 5 October 2026 the two baselines were found to differ, although both run the same three
baseline files. WPML downloads its published configuration from its own host when a plugin is activated, and that
download replaces a plugin's bundled `wpml-config.xml`. Development made the request. Staging may not make any
request that leaves the machine, so it kept the bundled files, and the one setting that copies a product's cost to
a new translation was missing there. Nothing reported it: the staging tool said the two "cannot drift".

The rule. The reset carries development's downloaded configuration to staging before the baseline runs, and ends
by comparing the WPML settings of the two runtimes, failing on any difference. `staging.py parity` makes the same
comparison at any time, and the backup fingerprint includes the settings, which sit in a table it otherwise skips.

What it cannot check: the comparison itself needs both runtimes, so it runs on the developer's machine and not in
CI. This file holds the comparison's logic and that the reset still calls it.
"""

from __future__ import annotations

import importlib.util
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
STAGING_DIR = ROOT / "mizzey-site" / "tests" / "staging"
BASELINE_DIR = ROOT / "mizzey-site" / "tests" / "integration" / "baseline"


def load_staging():
    spec = importlib.util.spec_from_file_location("mizzey_staging_tool", STAGING_DIR / "staging.py")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


class SettingsDifferences(unittest.TestCase):
    """The comparison names every setting that differs, and says nothing when none does."""

    @classmethod
    def setUpClass(cls):
        cls.differences = staticmethod(load_staging().differences)

    # What development holds, what staging holds, and what the comparison must say. The first three are the drift
    # as it was measured on 5 October 2026.
    CASES = (
        ("a setting missing on staging",
         {"fields": {"_price": 1, "_cogs_total_value": 1}}, {"fields": {"_price": 1}},
         ["fields._cogs_total_value: development 1, staging absent"]),
        ("a member missing from a list on staging",
         {"locked": ["_price", "_cogs_total_value"]}, {"locked": ["_price"]},
         ["locked: only on development ['_cogs_total_value']"]),
        ("a setting held as zero on one side and absent on the other",
         {"types": {"shop_subscription": 0}}, {"types": {}},
         ["types.shop_subscription: development 0, staging absent"]),
        ("a setting only on staging",
         {"fields": {}}, {"fields": {"_extra": 2}},
         ["fields._extra: development absent, staging 2"]),
        ("a value that changed",
         {"types": {"product": 1}}, {"types": {"product": 0}},
         ["types.product: development 1, staging 0"]),
        ("the same settings in another order",
         {"fields": {"_price": 1, "_sku": 1}, "locked": ["_price", "_sku"], "types": {"shop_order": 0}},
         {"types": {"shop_order": 0}, "locked": ["_sku", "_price"], "fields": {"_sku": 1, "_price": 1}},
         []),
    )

    def test_each_kind_of_difference_is_named_and_identical_settings_are_not(self):
        for label, development, staging, expected in self.CASES:
            with self.subTest(label):
                self.assertEqual(expected, self.differences(development, staging))


class ResetHoldsTheTwoBaselinesTogether(unittest.TestCase):
    """Removing either half of the correction would bring the drift back without a word."""

    @classmethod
    def setUpClass(cls):
        source = (STAGING_DIR / "staging.py").read_text(encoding="utf-8")
        start = source.index("def cmd_reset(")
        cls.reset = source[start:source.index("\ndef ", start + 1)]

    def test_the_configuration_is_carried_before_the_baseline_runs(self):
        self.assertIn("carry_wpml_config()", self.reset)
        self.assertLess(self.reset.index("carry_wpml_config()"), self.reset.index('"setup.php"'))

    def test_the_reset_ends_by_comparing_the_two_runtimes(self):
        self.assertIn("require_parity()", self.reset)
        self.assertGreater(self.reset.index("require_parity()"), self.reset.index("seed()"))

    def test_the_fingerprint_includes_the_wpml_settings(self):
        fingerprint = (STAGING_DIR / "fingerprint.php").read_text(encoding="utf-8")
        self.assertIn("'wpml_settings'", fingerprint)
        self.assertTrue((BASELINE_DIR / "wpml-settings.php").is_file())


if __name__ == "__main__":
    unittest.main()

"""Staging's data reads like the closed design: scenario leftovers are removed by name, and nothing else is.

`mizzey-site/tests/staging/seed/05-scenario-leftovers.php` removes what an integration scenario left behind when its
run stopped before its clean-up (the phone menu showed "t847286 serums" on staging, 25 November 2026). It finds them
by the run tag every scenario names its fixtures with (`_scenario-tags.php`). These tests run that matcher in PHP
against the names a scenario makes and the names the seeds and the store make, which it must never touch.

What they cannot check: the removal itself, on a database. That is the seed run twice on a runtime, recorded in the
pull request.
"""

from __future__ import annotations

import json
import shutil
import subprocess
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
TAGS = ROOT / "mizzey-site" / "tests" / "staging" / "seed" / "_scenario-tags.php"


def match(kind: str, names: list[str]) -> dict[str, bool]:
    code = (f"require {json.dumps(str(TAGS))};"
            f"$out = array(); foreach (json_decode($argv[1], true) as $n) {{ $out[$n] = mizzey_scenario_{kind}($n); }}"
            "echo json_encode($out);")
    done = subprocess.run(["php", "-r", code, json.dumps(names)], capture_output=True, text=True, check=True)
    return json.loads(done.stdout)


@unittest.skipUnless(shutil.which("php"), "PHP is not on the PATH")
class AScenarioLeftoverIsFoundByItsRunTag(unittest.TestCase):

    def test_a_scenario_run_tag_starts_a_slug_a_code_or_a_login(self):
        found = match("tag", ["t847286-serums", "T975851-kitchen", "t1101234-skincare", "t847286ten", "t847286c",
                              "t86123-serums-ar", "t847286", "t40_nobody_1234567", "t72adm4821", "t102-shirt-5512",
                              "T72-PRICE-3390", "t4x_wpreg_88123", "t11_admin_99812", "t39e-1234", "Q164154",
                              "q164154-haircare"])
        self.assertTrue(all(found.values()), found)

    def test_the_seeds_and_the_stores_own_names_are_never_a_run_tag(self):
        found = match("tag", ["sample-beauty", "sample-care", "demo_customer", "client_operator", "staging_admin",
                              "sample_reviewer_mariam", "tools", "toners", "t-shirts", "t1234-short", "travel",
                              "uncategorized", "welcome-1a2b3c4d5e", "quatish", "q12345", "SAMPLE-L374", "tirtir"])
        self.assertFalse(any(found.values()), found)

    def test_a_scenario_sku_and_only_a_scenario_sku(self):
        self.assertTrue(all(match("sku", ["t847286-SKU", "t847286-TWIN", "T975851-MZ-1001", "T72-PRICE-3390",
                                          "t102-shirt-5512-S"]).values()))
        self.assertFalse(any(match("sku", ["SAMPLE-002-24U", "SAMPLE-L374", "DEMO-S-001", "DEMO-LOAD-001", "MZ-1001",
                                           "TEA-001", "T1-01"]).values()))

    def test_a_scenario_address_at_example_invalid_and_only_there(self):
        self.assertTrue(all(match("address", ["t847286buyer@example.invalid", "t847286w@example.invalid",
                                              "t41-reviewer-55123@example.invalid", "t72adm4821@example.invalid"]).values()))
        self.assertFalse(any(match("address", ["customer01@example.invalid", "demo-customer@example.invalid",
                                               "t847286buyer@example.com", "sample-reviewer-omar@example.invalid"]).values()))


if __name__ == "__main__":
    unittest.main()

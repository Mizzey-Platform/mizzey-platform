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
import re
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


class NoSampleWordInTheData(unittest.TestCase):
    """The owner, 26 November 2026: no "Sample" word anywhere in staging's data (names, labels, descriptions,
    addresses). The only places the old wording may stay are the lines that find it, to rename what an earlier run
    wrote; slugs and SKUs keep their addresses."""

    SEED = ROOT / "mizzey-site" / "tests" / "staging" / "seed"
    # What an earlier run or the installation wrote, named only so a later run can find it and rename or remove it.
    OLD = ("'Sample Street'", "'Sample image (staging)'", "'Sample Page'", "'Hello Mizzey (sample number on the staging copy)'",
           "'مرحبًا ميزي (رقم تجريبي في نسخة العرض)'")

    def written(self, path: Path) -> list[str]:
        source = re.sub(r"/\*.*?\*/", "", path.read_text(encoding="utf-8"), flags=re.S)
        lines = [line for line in source.splitlines() if not line.strip().startswith("//")]
        found = []
        for line in lines:
            for literal in re.findall(r"'(?:[^'\\]|\\.)*'", line):
                if literal in self.OLD or literal.startswith("'sample-") or literal.startswith("'SAMPLE-"):
                    continue
                if re.search(r"\bSample\b|\(sample|تجريبي|عيّنة|عينة", literal):
                    found.append(f"{path.name}: {literal}")
        return found

    def test_no_seed_writes_the_word(self):
        found = [hit for path in sorted(self.SEED.glob("*.php")) for hit in self.written(path)]
        self.assertEqual([], found)

    def test_the_checks_look_for_the_names_the_seeds_write(self):
        for name in ("staging-pass.cjs", "browser-matrix.cjs"):
            text = (self.SEED.parent / name).read_text(encoding="utf-8")
            self.assertNotRegex(text, r"'Sample (Product|Care|Brand|Collection|Variable)|تجريبي", name)


if __name__ == "__main__":
    unittest.main()

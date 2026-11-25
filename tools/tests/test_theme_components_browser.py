"""The overlays and tabs of slice 1 behave in a real browser (spec 066, slice 1; AC-14).

Runs `mizzey-site/tests/browser/theme-components.cjs`, which drives Chromium over the component preview in
English and Arabic, at 1440 and 390 wide: focus moves into each drawer, sheet and dialog, stays inside on Tab and
Shift+Tab, Escape and the scrim close it, focus returns to the control that opened it, the drawers come from the
mirrored edge in Arabic, reduced motion fades instead of sliding, and tabs follow the arrow keys.

It needs node, the PHP command line, Playwright on the developer's machine and a local WordPress for the
Interactivity API module, so it runs only when MIZZEY_BROWSER_TESTS=1 is set (about a minute):

    MIZZEY_BROWSER_TESTS=1 python -m unittest tools.tests.test_theme_components_browser
"""

from __future__ import annotations

import json
import os
import shutil
import subprocess
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SCRIPT = ROOT / "mizzey-site" / "tests" / "browser" / "theme-components.cjs"


@unittest.skipUnless(os.environ.get("MIZZEY_BROWSER_TESTS") == "1", "set MIZZEY_BROWSER_TESTS=1 to run the browser check")
class OverlaysInABrowser(unittest.TestCase):
    def test_drawers_sheets_dialogs_and_tabs(self) -> None:
        node = shutil.which("node")
        self.assertIsNotNone(node, "node is needed")
        done = subprocess.run([node, str(SCRIPT)], capture_output=True, text=True, encoding="utf-8", timeout=600)
        lines = [line for line in done.stdout.splitlines() if line.startswith("{")]
        self.assertTrue(lines, done.stderr or done.stdout)
        result = json.loads(lines[-1])
        self.assertNotIn("error", result, result.get("error"))
        self.assertEqual([], result["failed"])
        self.assertEqual(0, done.returncode)


if __name__ == "__main__":
    unittest.main()

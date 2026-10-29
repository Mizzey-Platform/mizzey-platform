"""The handover baseline records what was handed over, and a comparison shows what changed since.

`tools/handover_baseline.py` hashes the program files of a WordPress tree at go-live and later compares a tree, or
a backup archive of one, with that record. These tests build a small tree in a temporary folder.

What they cannot check: a real hosting backup's layout, or a production server's file system.
"""

from __future__ import annotations

import contextlib
import io
import json
import os
import subprocess
import tempfile
import unittest
import zipfile
from pathlib import Path

from tools import handover_baseline as hb

FILES = {
    "index.php": "<?php // core",
    "wp-includes/version.php": "<?php\n$wp_version = '7.1.2';\n",
    "wp-admin/admin.php": "<?php // admin",
    "wp-content/plugins/woocommerce/woocommerce.php": "<?php\n/**\n * Plugin Name: WooCommerce\n * Version: 11.1.0\n */\n",
    "wp-content/plugins/mizzey-site/mizzey-site.php": "<?php\n/**\n * Plugin Name: Mizzey Site\n * Version: 1.0.0\n */\n",
    "wp-content/plugins/mizzey-site/src/Stock.php": "<?php class Stock {}",
    "wp-content/themes/mizzey-theme/style.css": "/*\nTheme Name: Mizzey\nVersion: 1.0.0\n*/\n",
    "wp-content/themes/mizzey-theme/templates/single-product.html": "<!-- wp:post-title /-->",
    "wp-config.php": "<?php define('DB_PASSWORD', 'x');",
    "wp-content/uploads/2026/12/a.jpg": "jpg",
    "wp-content/debug.log": "noise",
}


def write_tree(root: Path, files: dict):
    for rel, text in files.items():
        p = root / rel
        p.parent.mkdir(parents=True, exist_ok=True)
        p.write_bytes(text.encode())


def run(*argv):
    out = io.StringIO()
    with contextlib.redirect_stdout(out), contextlib.redirect_stderr(io.StringIO()):
        try:
            code = hb.main(list(argv))
        except SystemExit as e:
            code = e.code
    return code, out.getvalue()


class Baseline(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.d = Path(self.tmp.name)
        self.site = self.d / "site"
        write_tree(self.site, FILES)
        self.base = self.d / "baseline.json"
        code, _ = run("make", "--root", str(self.site), "--tag", "v1.0.0", "--out", str(self.base))
        self.assertEqual(code, 0)
        self.digest = hb.sha256(self.base.read_bytes())

    def tearDown(self):
        self.tmp.cleanup()

    def check(self, root=None, *extra):
        return run("check", "--root", str(root or self.site), "--baseline", str(self.base),
                   "--expect", self.digest, *extra)

    def test_excludes_server_files_and_records_versions(self):
        doc = json.loads(self.base.read_text(encoding="utf-8"))
        self.assertNotIn("wp-config.php", doc["files"])
        self.assertNotIn("wp-content/uploads/2026/12/a.jpg", doc["files"])
        self.assertNotIn("wp-content/debug.log", doc["files"])
        self.assertEqual(doc["file_count"], 8)
        self.assertEqual(doc["components"]["wordpress"], "7.1.2")
        self.assertEqual(doc["components"]["plugins"]["woocommerce"]["version"], "11.1.0")
        self.assertEqual(doc["components"]["themes"]["mizzey-theme"]["version"], "1.0.0")

    def test_unchanged_tree_is_clean(self):
        code, text = self.check()
        self.assertEqual(code, 0, text)
        self.assertIn("unchanged", text)

    def test_runtime_files_changing_is_not_a_difference(self):
        write_tree(self.site, {"wp-content/uploads/2027/01/b.jpg": "new", "wp-config.php": "changed",
                               "wp-content/debug.log": "more noise"})
        code, text = self.check()
        self.assertEqual(code, 0, text)

    def test_reports_changed_added_removed_and_versions(self):
        write_tree(self.site, {
            "wp-content/themes/mizzey-theme/templates/single-product.html": "<!-- edited by someone -->",
            "wp-content/plugins/woocommerce/woocommerce.php":
                "<?php\n/**\n * Plugin Name: WooCommerce\n * Version: 11.3.0\n */\n",
            "wp-content/plugins/ai-helper/ai-helper.php": "<?php\n/**\n * Plugin Name: AI Helper\n * Version: 0.1\n */\n",
        })
        (self.site / "wp-content/plugins/mizzey-site/src/Stock.php").unlink()
        code, text = self.check()
        self.assertEqual(code, 1)
        self.assertIn("**2 changed, 1 added, 1 removed**", text)
        self.assertIn("plugin woocommerce: 11.1.0 -> 11.3.0", text)
        self.assertIn("plugin ai-helper: added (0.1)", text)
        self.assertIn("changed: `wp-content/themes/mizzey-theme/templates/single-product.html`", text)
        self.assertIn("removed: `wp-content/plugins/mizzey-site/src/Stock.php`", text)
        self.assertIn("| theme: mizzey-theme | 1 | 0 | 0 |", text)

    def test_links_are_listed_not_followed(self):
        outside = self.d / "outside"
        write_tree(outside, {"node_modules/big.js": "x" * 100, "plugin.php": "<?php"})
        link = self.site / "wp-content/plugins/linked"
        if os.name == "nt":  # a junction, which is what a Windows development runtime uses and needs no privilege
            made = subprocess.run(["cmd", "/c", "mklink", "/J", str(link), str(outside)], capture_output=True)
            if made.returncode:
                self.skipTest("this machine cannot create a junction")
        else:
            link.symlink_to(outside, target_is_directory=True)
        out = self.d / "linked.json"
        code, _ = run("make", "--root", str(self.site), "--tag", "v1.0.0", "--out", str(out))
        self.assertEqual(code, 0)
        doc = json.loads(out.read_text(encoding="utf-8"))
        self.assertEqual(doc["links_not_hashed"], ["wp-content/plugins/linked"])
        self.assertFalse(any(p.startswith("wp-content/plugins/linked/") for p in doc["files"]))

    def test_tampered_baseline_is_refused(self):
        doc = json.loads(self.base.read_text(encoding="utf-8"))
        doc["files"]["index.php"]["sha256"] = "0" * 64
        self.base.write_text(json.dumps(doc), encoding="utf-8")
        code, text = self.check()
        self.assertEqual(code, 2)
        self.assertIn("NOT the signed one", text)

    def test_baseline_is_never_overwritten(self):
        code, _ = run("make", "--root", str(self.site), "--tag", "v1.0.0", "--out", str(self.base))
        self.assertNotEqual(code, 0)
        self.assertEqual(hb.sha256(self.base.read_bytes()), self.digest)

    def test_archive_root_with_strip(self):
        z = self.d / "backup.zip"
        with zipfile.ZipFile(z, "w") as arc:
            for rel, text in FILES.items():
                arc.writestr("public_html/" + rel, text)
        code, text = self.check(z, "--strip", "public_html/")
        self.assertEqual(code, 0, text)

    def test_release_must_match_tree(self):
        rel = self.d / "mizzey-v1.0.0.zip"
        with zipfile.ZipFile(rel, "w") as arc:
            for name, text in FILES.items():
                if not name.startswith("wp-content/uploads") and name != "wp-config.php":
                    arc.writestr(name, text)
        ok = self.d / "ok.json"
        code, _ = run("make", "--root", str(self.site), "--tag", "v1.0.0", "--release", str(rel), "--out", str(ok))
        self.assertEqual(code, 0)
        self.assertEqual(json.loads(ok.read_text(encoding="utf-8"))["release"]["sha256"], hb.sha256(rel.read_bytes()))

        write_tree(self.site, {"wp-content/plugins/mizzey-site/src/Stock.php": "<?php // hotfix on server"})
        bad = self.d / "bad.json"
        code, _ = run("make", "--root", str(self.site), "--tag", "v1.0.0", "--release", str(rel), "--out", str(bad))
        self.assertNotEqual(code, 0)
        self.assertFalse(bad.exists())


if __name__ == "__main__":
    unittest.main()

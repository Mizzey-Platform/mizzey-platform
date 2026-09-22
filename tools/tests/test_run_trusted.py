import contextlib
import io
import shutil
import subprocess
import tempfile
import unittest
from pathlib import Path

from tools import run_trusted as rt

ROOT = Path(__file__).resolve().parents[2]
TOOLS = ("__init__.py", "scope_trace.py", "house_rules.py", "run_trusted.py")


def git(cwd, *args):
    subprocess.run(["git", *args], cwd=cwd, check=True, capture_output=True, text=True)


def install_tools(d: Path):
    (d / "tools").mkdir(exist_ok=True)
    for f in TOOLS:
        shutil.copy(ROOT / "tools" / f, d / "tools" / f)
    shutil.copytree(ROOT / "docs" / "scope", d / "docs" / "scope", dirs_exist_ok=True)


def new_repo(d: Path):
    git(d, "init", "-q", "-b", "main")
    git(d, "config", "user.email", "t@example.invalid")
    git(d, "config", "user.name", "t")
    git(d, "config", "core.autocrlf", "false")


def commit(d: Path, msg: str):
    git(d, "add", "-A")
    git(d, "commit", "-q", "-m", msg)


def run(d: Path, body: str):
    bf = d.parent / f"{d.name}-body.md"
    bf.write_text(body, encoding="utf-8")
    out = io.StringIO()
    with contextlib.redirect_stdout(out):
        rc = rt.main(["--repo", str(d), "--base", "main", "--body-file", str(bf)])
    return rc, out.getvalue()


GOV = "Classification: internal:governance\nClient-facing behaviour change: none\nSensitive changes: checker\n"
CI = "Classification: internal:ci\nClient-facing behaviour change: none\n"


class TrustedChecker(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.d = Path(self.tmp.name) / "repo"
        self.d.mkdir()
        new_repo(self.d)

    def tearDown(self):
        self.tmp.cleanup()

    def test_bootstrap_when_base_has_no_checker(self):
        (self.d / "README.md").write_text("# x\n", encoding="utf-8")
        commit(self.d, "base without checker")
        git(self.d, "switch", "-q", "-c", "pr")
        install_tools(self.d)
        commit(self.d, "introduce checker")
        rc, out = run(self.d, GOV)
        self.assertIn("BOOTSTRAP", out)
        self.assertNotIn("trusted scope-trace", out)
        self.assertIn("tools/scope_trace.py", out)  # listed as governance-sensitive
        self.assertEqual(rc, 0, out)

    def test_trusted_checker_catches_a_pr_that_disables_its_own_checker(self):
        install_tools(self.d)
        commit(self.d, "base with checker")
        git(self.d, "switch", "-q", "-c", "pr")
        (self.d / "tools" / "scope_trace.py").write_text("import sys\nsys.exit(0)\n", encoding="utf-8")
        (self.d / "mizzey-extras").mkdir()
        (self.d / "mizzey-extras" / "mizzey-extras.php").write_text("<?php // new plugin\n", encoding="utf-8")
        commit(self.d, "weaken checker and add a plugin")
        rc, out = run(self.d, CI)
        self.assertIn("trusted scope-trace (base): FAIL", out)
        self.assertIn("proposed scope-trace (PR): pass", out)  # the sabotaged copy passes, which is the point
        self.assertIn("not covered by the path policy", out)
        self.assertEqual(rc, 1)

    def test_legitimate_pr_passes_both_checkers(self):
        install_tools(self.d)
        commit(self.d, "base with checker")
        git(self.d, "switch", "-q", "-c", "pr")
        (self.d / "docs").mkdir(exist_ok=True)
        (self.d / "docs" / "note.md").write_text("A clean note.\n", encoding="utf-8")
        commit(self.d, "docs only")
        rc, out = run(self.d, "Classification: internal:documentation\nClient-facing behaviour change: none\n")
        self.assertIn("trusted scope-trace (base): pass", out)
        self.assertIn("proposed scope-trace (PR): pass", out)
        self.assertEqual(rc, 0, out)


if __name__ == "__main__":
    unittest.main()

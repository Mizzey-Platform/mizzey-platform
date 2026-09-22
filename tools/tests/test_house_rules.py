import os
import subprocess
import tempfile
import unittest
from pathlib import Path

from tools import house_rules as hr

DASH = "—"


class LineChecks(unittest.TestCase):
    def test_added_em_dash_fails(self):
        self.assertEqual(hr.check_lines([("docs/a.md", 3, f"one {DASH} two")]), ["docs/a.md:3: em dash"])

    def test_added_emoji_fails(self):
        self.assertEqual(hr.check_lines([("README.md", 1, "done \U0001F389")]), ["README.md:1: emoji"])

    def test_excluded_and_non_markdown_paths_pass(self):
        added = [(".claude/skills/wp-guard/SKILL.md", 1, DASH), ("docs/engagement/x.md", 1, DASH),
                 ("tools/x.py", 1, DASH)]
        self.assertEqual(hr.check_lines(added), [])

    def test_parse_only_added_lines(self):
        diff = ("diff --git a/D.md b/D.md\n--- a/D.md\n+++ b/D.md\n@@ -4 +4,2 @@\n"
                f"-old {DASH} line\n+new clean line\n+another\n")
        self.assertEqual(hr.parse_added_lines(diff), [("D.md", 4, "new clean line"), ("D.md", 5, "another")])


class FileChecks(unittest.TestCase):
    def test_contract_pdf_and_archives_fail(self):
        errs = hr.check_added_files(["docs/x/Agreement.pdf", "pack.zip", "a/b/.env", "certs/site.pem"])
        self.assertEqual(len(errs), 4)

    def test_new_file_under_engagement_fails(self):
        self.assertTrue(hr.check_added_files(["docs/engagement/new.md"]))

    def test_allowed_files_pass(self):
        self.assertEqual(hr.check_added_files([".env.example", "docs/adr/0001-x.md", "tools/x.py"]), [])


def git(cwd, *args):
    subprocess.run(["git", *args], cwd=cwd, check=True, capture_output=True, text=True)


class HistoricalFilesAreNotRejudged(unittest.TestCase):
    """End to end in a scratch git repository: an old em dash elsewhere in a touched file does not fail."""

    def test_only_changed_lines_are_checked(self):
        with tempfile.TemporaryDirectory() as d:
            git(d, "init", "-q", "-b", "main")
            git(d, "config", "user.email", "t@example.invalid")
            git(d, "config", "user.name", "t")
            Path(d, "DECISIONS.md").write_text(f"# Log\n\nold entry {DASH} kept\n", encoding="utf-8")
            git(d, "add", ".")
            git(d, "commit", "-q", "-m", "base")
            with open(Path(d, "DECISIONS.md"), "a", encoding="utf-8") as f:
                f.write("\nnew entry, clean\n")
            git(d, "commit", "-qam", "clean change")
            old_root = hr.ROOT
            hr.ROOT = Path(d)
            try:
                self.assertEqual(hr.main(["--base", "HEAD~1"]), 0)
                with open(Path(d, "DECISIONS.md"), "a", encoding="utf-8") as f:
                    f.write(f"\nbad {DASH} entry\n")
                git(d, "commit", "-qam", "bad change")
                self.assertEqual(hr.main(["--base", "HEAD~1"]), 1)
            finally:
                hr.ROOT = old_root


if __name__ == "__main__":
    unittest.main()

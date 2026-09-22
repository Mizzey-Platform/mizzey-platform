import json
import shutil
import tempfile
import unittest
from pathlib import Path

from tools import repo_checks as rc

ROOT = Path(__file__).resolve().parents[2]


class RepoChecks(unittest.TestCase):
    def test_current_tree_passes(self):
        self.assertEqual(rc.check_taskstoissues(ROOT) + rc.check_stack(ROOT) + rc.check_scope(ROOT), [])

    def test_taskstoissues_skill_fails(self):
        with tempfile.TemporaryDirectory() as d:
            Path(d, ".claude", "skills", "speckit-taskstoissues").mkdir(parents=True)
            self.assertTrue(rc.check_taskstoissues(Path(d)))

    def test_enabled_taskstoissues_hook_fails(self):
        with tempfile.TemporaryDirectory() as d:
            Path(d, ".specify").mkdir()
            Path(d, ".specify", "extensions.yml").write_text(
                "hooks:\n  after_tasks:\n  - extension: git\n    command: speckit.taskstoissues\n    enabled: true\n",
                encoding="utf-8")
            self.assertTrue(rc.check_taskstoissues(Path(d)))

    def test_corex_mismatch_fails(self):
        with tempfile.TemporaryDirectory() as d:
            shutil.copy(ROOT / "stack.lock.json", d)
            lock = json.loads((ROOT / "corex.lock").read_text(encoding="utf-8"))
            lock["version"] = "v9.9.9"
            Path(d, "corex.lock").write_text(json.dumps(lock), encoding="utf-8")
            self.assertTrue(any("corex.lock" in e for e in rc.check_stack(Path(d))))


if __name__ == "__main__":
    unittest.main()

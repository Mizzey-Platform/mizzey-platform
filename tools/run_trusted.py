"""Run the PR checks with a trusted checker where one exists (F-4).

A pull request supplies its own copy of tools/, so on its own the checker under review could be weakened by the
PR it judges. This runner therefore:

1. **Trusted run.** If the base branch has tools/scope_trace.py, it exports the base branch's tools/ and
   docs/scope/ to a temporary folder (git archive, no checkout) and runs that copy against this checkout.
2. **Proposed run.** It always also runs this checkout's own checkers, so a PR that changes the checker is judged
   by both versions. The proposed checker's unit tests run in the CI baseline job.
3. **Bootstrap.** If the base branch has no checker, which is only true for the PR that introduces it, it says so
   and runs the proposed checkers only. That PR is reviewed by hand, with the abuse tests in tools/tests.
4. It lists every changed checker, CI, constitution, scope-record and deployment-sensitive file.

Limits, stated plainly: a PR can still edit .github/workflows/ci.yml to stop calling this runner, and on the free
GitHub plan nothing server-side stops a merge with red or missing checks. This runner makes such a change visible;
it does not make it impossible.

Usage: python tools/run_trusted.py --base origin/main [--event "$GITHUB_EVENT_PATH" | --body-file body.md]
"""

from __future__ import annotations

import argparse
import io
import subprocess
import sys
import tarfile
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
WATCHED = ("tools/", ".github/workflows/", ".githooks/", ".specify/memory/constitution.md", "docs/scope/",
           "tools/build-dist.mjs", "tools/corex-sync.mjs", "corex.lock", "stack.lock.json")


def git(repo: Path, *args: str, check: bool = True) -> subprocess.CompletedProcess:
    return subprocess.run(["git", *args], cwd=repo, capture_output=True, check=check)


def base_has_checker(repo: Path, base: str) -> bool:
    return git(repo, "cat-file", "-e", f"{base}:tools/scope_trace.py", check=False).returncode == 0


def export_base(repo: Path, base: str, dest: Path) -> None:
    tar = git(repo, "archive", "--format=tar", base, "tools", "docs/scope").stdout
    with tarfile.open(fileobj=io.BytesIO(tar)) as t:
        t.extractall(dest)


def watched_changes(repo: Path, base: str) -> list[str]:
    out = git(repo, "diff", "--name-only", "-M", f"{base}...HEAD").stdout.decode("utf-8").splitlines()
    return [p for p in out if any(p == w or p.startswith(w) for w in WATCHED)]


def run(label: str, cmd: list[str]) -> int:
    print(f"--- {label}: {' '.join(Path(c).name if i == 1 else c for i, c in enumerate(cmd))}")
    proc = subprocess.run(cmd, capture_output=True, text=True, encoding="utf-8")
    print(proc.stdout + proc.stderr, end="", flush=True)
    return proc.returncode


def main(argv: list[str] | None = None) -> int:
    ap = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    ap.add_argument("--base", required=True)
    ap.add_argument("--repo", type=Path, default=ROOT)
    ap.add_argument("--event", type=Path)
    ap.add_argument("--body-file", type=Path)
    a = ap.parse_args(argv)
    repo = a.repo.resolve()
    body = ["--event", str(a.event)] if a.event else (["--body-file", str(a.body_file)] if a.body_file else [])
    py = sys.executable
    results: dict[str, int] = {}

    changed = watched_changes(repo, a.base)
    if changed:
        print("Governance-sensitive files changed in this PR (review these diffs explicitly):")
        for p in changed:
            print(f"  {p}")

    if base_has_checker(repo, a.base):
        with tempfile.TemporaryDirectory() as tmp:
            export_base(repo, a.base, Path(tmp))
            common = ["--repo", str(repo), "--base", a.base]
            results["trusted scope-trace (base)"] = run("trusted scope-trace (base)",
                                                        [py, str(Path(tmp, "tools", "scope_trace.py")), *common, *body])
            results["trusted house-rules (base)"] = run("trusted house-rules (base)",
                                                        [py, str(Path(tmp, "tools", "house_rules.py")), *common])
    else:
        print(f"BOOTSTRAP: {a.base} has no tools/scope_trace.py. Only the proposed checker runs. This PR introduces "
              "the checker, so its checker and CI diff need explicit human review.")

    results["proposed scope-trace (PR)"] = run("proposed scope-trace (PR)",
                                               [py, str(repo / "tools" / "scope_trace.py"), "--repo", str(repo),
                                                "--base", a.base, *body])
    results["proposed house-rules (PR)"] = run("proposed house-rules (PR)",
                                               [py, str(repo / "tools" / "house_rules.py"), "--repo", str(repo),
                                                "--base", a.base])
    print("Summary:")
    for k, v in results.items():
        print(f"  {k}: {'pass' if v == 0 else 'FAIL'}")
    return 0 if all(v == 0 for v in results.values()) else 1


if __name__ == "__main__":
    sys.exit(main())

"""House rules on changed lines only.

Checks the lines a PR adds, never whole files, so history written before the rules existed is not re-judged:

- Markdown: no emoji, no em dash (the client-document house style, applied to engineering docs from 22 Sep 2026).
- Files added or renamed into place: no contract PDFs or office documents, no archives, no key or certificate files, no env files, and
  nothing new under docs/engagement/ (confidential contract copies, being removed under D-03).

Vendored and generated paths are excluded. See EXCLUDED.

Usage: python tools/house_rules.py --base origin/main
"""

from __future__ import annotations

import argparse
import fnmatch
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent

EXCLUDED = (
    ".claude/skills/*",          # vendored: Spec Kit and guard-skills, pinned in docs/tooling.md
    ".specify/scripts/*",
    ".specify/extensions/*",
    ".specify/integrations/*",
    ".specify/workflows/*",
    "docs/engagement/*",         # historical Option C contract copies
    "discovery/generated/*",     # generated
    "discovery/data/*",          # has its own gate
    "scripts/stories.json",
)
FORBIDDEN_FILES = ("*.pdf", "*.doc", "*.docx", "*.xls", "*.xlsx", "*.ppt", "*.pptx", "*.zip", "*.7z", "*.rar",
                   "*.p12", "*.pfx", "*.pem", "*.key", ".env", ".env.*", "*/.env", "*/.env.*")
FORBIDDEN_DIRS = ("docs/engagement/",)
EM_DASH = "—"
EMOJI = re.compile("[\U0001F000-\U0001FAFF☀-➿⬀-⯿️]")


def excluded(path: str) -> bool:
    return any(fnmatch.fnmatch(path, pat) for pat in EXCLUDED)


def parse_added_lines(diff: str) -> list[tuple[str, int, str]]:
    """(path, new line number, text) for every added line in a unified diff with -U0."""
    out, path, line = [], None, 0
    for raw in diff.splitlines():
        if raw.startswith("+++ "):
            path = raw[6:] if raw.startswith("+++ b/") else None
        elif raw.startswith("@@"):
            m = re.search(r"\+(\d+)", raw)
            line = int(m.group(1)) if m else 0
        elif raw.startswith("+") and path is not None:
            out.append((path, line, raw[1:]))
            line += 1
    return out


def check_lines(added: list[tuple[str, int, str]]) -> list[str]:
    errs = []
    for path, n, text in added:
        if not path.endswith(".md") or excluded(path):
            continue
        if EM_DASH in text:
            errs.append(f"{path}:{n}: em dash")
        if EMOJI.search(text):
            errs.append(f"{path}:{n}: emoji")
    return errs


def check_added_files(files: list[str]) -> list[str]:
    errs = []
    for f in files:
        if Path(f).name == ".env.example":
            continue
        if any(f.startswith(d) for d in FORBIDDEN_DIRS):
            errs.append(f"{f}: nothing new may be added under docs/engagement/")
        elif any(fnmatch.fnmatch(f.lower(), p) or fnmatch.fnmatch(Path(f).name.lower(), p) for p in FORBIDDEN_FILES):
            errs.append(f"{f}: file type not allowed in the repository (contract documents, archives, keys, env)")
    return errs


def git(repo: Path, *args: str) -> str:
    return subprocess.run(["git", *args], cwd=repo, capture_output=True, text=True, check=True,
                          encoding="utf-8").stdout


def main(argv: list[str] | None = None) -> int:
    ap = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    ap.add_argument("--base", required=True)
    ap.add_argument("--repo", type=Path, default=None, help="checkout to judge (default: this script's repository)")
    a = ap.parse_args(argv)
    repo = (a.repo or ROOT).resolve()
    rng = f"{a.base}...HEAD"
    added_files = [f for f in git(repo, "diff", "--name-only", "-M", "--diff-filter=ACR", rng).splitlines() if f]
    diff = git(repo, "diff", "-U0", "-M", "--diff-filter=ACMR", "--no-color", rng, "--", "*.md")
    added = parse_added_lines(diff)
    errs = check_added_files(added_files) + check_lines(added)
    for e in errs:
        print(e)
    print(f"house-rules: {len(added_files)} added files, {len(added)} added markdown lines, {len(errs)} problems")
    return 1 if errs else 0


if __name__ == "__main__":
    sys.exit(main())

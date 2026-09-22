"""Whole-tree consistency checks for the engineering configuration.

- Task-to-issue generation stays disabled: no speckit-taskstoissues skill, no enabled taskstoissues hook.
- The agent-context skill and extension stay removed, and CLAUDE.md carries no Spec Kit managed block.
- stack.lock.json is well formed, and its CoreX entry matches corex.lock.
- docs/scope/register-ids.json matches its recorded source (docs/scope/SOURCE.md hash and count).
- docs/scope/open-items.json cites only ids that exist in the register.

Usage: python tools/repo_checks.py
"""

from __future__ import annotations

import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
STATUSES = {"tested", "installed-untested", "unverified", "not-selected"}
REQUIRED = {"php", "wordpress", "woocommerce", "corex", "wpml", "wcml"}


def check_taskstoissues(root: Path) -> list[str]:
    errs = []
    if (root / ".claude" / "skills" / "speckit-taskstoissues").exists():
        errs.append(".claude/skills/speckit-taskstoissues exists; task-to-issue generation must stay disabled")
    ext = root / ".specify" / "extensions.yml"
    if ext.exists():
        text = ext.read_text(encoding="utf-8")
        for block in re.split(r"\n\s*-\s+extension:", text):
            if "taskstoissues" in block and re.search(r"enabled:\s*true", block):
                errs.append(".specify/extensions.yml enables a taskstoissues hook")
    return errs


def check_agent_context(root: Path) -> list[str]:
    errs = []
    if (root / ".claude" / "skills" / "speckit-agent-context-update").exists():
        errs.append(".claude/skills/speckit-agent-context-update exists; it rewrites CLAUDE.md and must stay removed")
    if (root / ".specify" / "extensions" / "agent-context").exists():
        errs.append(".specify/extensions/agent-context exists; the agent-context extension must stay removed")
    claude = root / "CLAUDE.md"
    if claude.exists() and "SPECKIT START" in claude.read_text(encoding="utf-8"):
        errs.append("CLAUDE.md contains a Spec Kit managed block; it must stay a plain pointer to AGENTS.md")
    return errs


def check_stack(root: Path) -> list[str]:
    errs = []
    try:
        lock = json.loads((root / "stack.lock.json").read_text(encoding="utf-8"))
        corex = json.loads((root / "corex.lock").read_text(encoding="utf-8"))
    except (OSError, ValueError) as e:
        return [f"stack.lock.json or corex.lock unreadable: {e}"]
    comps = lock.get("components", {})
    for name in sorted(REQUIRED - set(comps)):
        errs.append(f"stack.lock.json: missing component {name}")
    for name, c in comps.items():
        if c.get("status") not in STATUSES:
            errs.append(f"stack.lock.json: {name} has status {c.get('status')!r}")
        if c.get("status") in {"tested", "installed-untested"} and not c.get("version"):
            errs.append(f"stack.lock.json: {name} is {c['status']} but has no version")
    cx = comps.get("corex", {})
    if cx.get("version") != corex.get("version") or cx.get("commit") != corex.get("commit"):
        errs.append("stack.lock.json corex entry does not match corex.lock")
    if comps.get("wordpress", {}).get("version") != corex.get("wordpress"):
        errs.append("stack.lock.json wordpress version does not match corex.lock")
    return errs


def check_scope(root: Path) -> list[str]:
    errs = []
    data = json.loads((root / "docs" / "scope" / "register-ids.json").read_text(encoding="utf-8"))
    source = (root / "docs" / "scope" / "SOURCE.md").read_text(encoding="utf-8")
    if data["source"]["sha256"] not in source:
        errs.append("register-ids.json source hash is not the one recorded in docs/scope/SOURCE.md")
    if f"| Ids extracted | {data['count']} " not in source:
        errs.append("register-ids.json id count differs from docs/scope/SOURCE.md")
    if data["count"] != len(data["ids"]):
        errs.append("register-ids.json count field differs from the number of ids")
    items = json.loads((root / "docs" / "scope" / "open-items.json").read_text(encoding="utf-8"))
    for cx, item in items.get("contradictions", {}).items():
        for rid in item.get("ids", []) + item.get("related_ids", []):
            if rid not in data["ids"]:
                errs.append(f"open-items.json {cx}: {rid} is not in the register")
    return errs


def main() -> int:
    errs = check_taskstoissues(ROOT) + check_agent_context(ROOT) + check_stack(ROOT) + check_scope(ROOT)
    for e in errs:
        print(e)
    print(f"repo-checks: {len(errs)} problems")
    return 1 if errs else 0


if __name__ == "__main__":
    sys.exit(main())

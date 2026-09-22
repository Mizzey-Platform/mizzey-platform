"""Scope-trace check for pull requests (constitution M-1 to M-6).

Checks two things:

1. The PR body declares a work category. `requirement` PRs cite obligation ids that are traced in a spec.
   `internal:<category>` PRs change no client-facing behaviour (except `security-maintenance`, which must say what
   changes) and touch no feature spec or site code.
2. Every spec added or changed in the PR has a valid Register trace, and acceptance criteria that cite only traced
   ids. Criteria for P1-E rows stay provisional while PRE-09 is open. Open contradictions touching the spec are cited.

What it cannot check: whether a criterion's behaviour is actually supported by the wording of the row it cites.
That is a review judgement (M-1). The per-criterion Traces column makes it reviewable.

Usage (CI):     python tools/scope_trace.py --base origin/main --event "$GITHUB_EVENT_PATH"
Usage (local):  python tools/scope_trace.py --base origin/main --body-file pr-body.md
Without --event or --body-file only the spec checks run (for example on a push to main).
"""

from __future__ import annotations

import argparse
import json
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
IDS_FILE = ROOT / "docs" / "scope" / "register-ids.json"
OPEN_FILE = ROOT / "docs" / "scope" / "open-items.json"

OBLIGATION = {"P1", "P1-L", "P1-E", "DLV"}
INTERNAL = {"governance", "ci", "test-infrastructure", "tooling", "security-maintenance", "documentation"}
SITE_CODE = ("mizzey-site/", "mizzey-theme/")
SPEC_RE = re.compile(r"^specs/\d{3}-[a-z0-9-]+/spec\.md$")
ID_RE = re.compile(r"(?<![A-Za-z0-9-])([A-Z]{2,5}-\d{1,3}[a-z]?)(?![0-9A-Za-z])")
CX_RE = re.compile(r"\bCX-\d{2}\b")


def strip_comments(text: str) -> str:
    return re.sub(r"<!--.*?-->", "", text, flags=re.S)


def section(md: str, title: str) -> str | None:
    """Body of the `## <title>` section (title matched as a prefix, case-insensitive), or None."""
    lines = md.splitlines()
    out: list[str] | None = None
    for line in lines:
        if line.startswith("## "):
            if out is not None:
                break
            if line[3:].strip().lower().startswith(title.lower()):
                out = []
            continue
        if out is not None:
            out.append(line)
    return None if out is None else "\n".join(out)


def table_rows(body: str) -> list[dict[str, str]]:
    rows, header = [], None
    for line in body.splitlines():
        if not line.strip().startswith("|"):
            if header is not None and rows:
                break
            continue
        cells = [c.strip() for c in line.strip().strip("|").split("|")]
        if header is None:
            header = [c.lower() for c in cells]
            continue
        if set("".join(cells)) <= set("-: "):
            continue
        rows.append(dict(zip(header, cells)))
    return rows


def known_ids(text: str, ids: dict) -> list[str]:
    return [i for i in ID_RE.findall(text) if i in ids]


def check_spec(path: str, md: str, ids: dict, open_items: dict) -> list[str]:
    errs: list[str] = []
    md = strip_comments(md)
    trace_body = section(md, "Register trace")
    if trace_body is None:
        return [f"{path}: missing '## Register trace' section"]
    trace = table_rows(trace_body)
    if not trace:
        return [f"{path}: Register trace table has no rows"]
    traced: dict[str, dict] = {}
    for r in trace:
        rid = r.get("id", "").strip("`* ")
        if rid not in ids:
            errs.append(f"{path}: Register trace id {rid!r} is not in the Feature Register")
            continue
        reg = ids[rid]
        scope, stage = r.get("scope", "").strip("`* "), r.get("stage", "").strip("`* ")
        if reg["scope"] not in OBLIGATION:
            errs.append(f"{path}: {rid} is {reg['scope']} in the register and creates no obligation; "
                        "move it to 'Context rows'")
            continue
        if scope != reg["scope"]:
            errs.append(f"{path}: {rid} scope written as {scope!r}, register says {reg['scope']!r}")
        if stage != reg["stage"]:
            errs.append(f"{path}: {rid} stage written as {stage!r}, register says {reg['stage']!r} "
                        "(the contractual stage cannot be moved, M-6)")
        traced[rid] = reg

    crit_body = section(md, "Contractual acceptance criteria")
    crit = table_rows(crit_body) if crit_body is not None else []
    if not crit:
        errs.append(f"{path}: missing or empty '## Contractual acceptance criteria' table")
    pre09_open = not open_items.get("gates", {}).get("PRE-09", {}).get("approved", False)
    for r in crit:
        label = r.get("#", "?")
        cited = known_ids(r.get("traces", ""), ids)
        mentioned = set(cited) | set(known_ids(r.get("criterion", ""), ids))
        if not cited:
            errs.append(f"{path}: criterion {label} cites no register id in Traces")
        stray = sorted(mentioned - set(traced))
        if stray:
            errs.append(f"{path}: criterion {label} refers to {', '.join(stray)}, which "
                        "{} not in the Register trace".format("is" if len(stray) == 1 else "are"))
        status = r.get("status", "").strip().lower()
        if not status:
            errs.append(f"{path}: criterion {label} has no Status")
        if pre09_open and any(traced.get(i, {}).get("scope") == "P1-E" for i in cited) and status == "final":
            errs.append(f"{path}: criterion {label} traces a P1-E id and is marked final while PRE-09 is "
                        "not approved (M-3); mark it provisional")

    open_body = section(md, "Open contract items") or ""
    for cx, item in open_items.get("contradictions", {}).items():
        if item.get("status") != "open":
            continue
        touched = set(item.get("ids", [])) | set(item.get("related_ids", []))
        hit = sorted(touched & set(traced))
        if hit and cx not in open_body:
            errs.append(f"{path}: {', '.join(hit)} touched by open contradiction {cx}; cite it under "
                        "'## Open contract items' (M-2)")
    return errs


def field(body: str, name: str) -> str | None:
    m = re.search(rf"^\**{re.escape(name)}\**\s*:\s*\**\s*(.+?)\s*$", body, flags=re.M | re.I)
    return m.group(1).strip("`* ") if m else None


def spec_trace_ids(root: Path, ids: dict) -> set[str]:
    out: set[str] = set()
    for spec in sorted(root.glob("specs/[0-9][0-9][0-9]-*/spec.md")):
        body = section(strip_comments(spec.read_text(encoding="utf-8")), "Register trace") or ""
        out.update(r.get("id", "").strip("`* ") for r in table_rows(body))
    return out & set(ids)


def check_pr(body: str, changed: list[str], ids: dict, traced_in_specs: set[str]) -> list[str]:
    body = strip_comments(body or "")
    errs: list[str] = []
    cls = (field(body, "Classification") or "").lower()
    if cls == "requirement":
        raw = field(body, "Requirement ids") or ""
        cited = ID_RE.findall(raw)
        if not cited:
            errs.append("PR: 'Requirement ids:' must list at least one register id")
        for rid in cited:
            if rid not in ids:
                errs.append(f"PR: {rid} is not in the Feature Register")
            elif ids[rid]["scope"] not in OBLIGATION:
                errs.append(f"PR: {rid} is {ids[rid]['scope']} and creates no obligation")
            elif rid not in traced_in_specs:
                errs.append(f"PR: {rid} is not traced in any specs/NNN-*/spec.md Register trace")
    elif cls.startswith("internal:"):
        cat = cls.split(":", 1)[1]
        if cat not in INTERNAL:
            errs.append(f"PR: unknown internal category {cat!r}; use one of {', '.join(sorted(INTERNAL))}")
        change = (field(body, "Client-facing behaviour change") or "").lower()
        if not change:
            errs.append("PR: internal work must state 'Client-facing behaviour change: none' "
                        "(or, for security-maintenance, what changes)")
        elif change != "none" and cat != "security-maintenance":
            errs.append(f"PR: internal:{cat} may not change client-facing behaviour; reclassify as "
                        "requirement with a register id, or raise a Change Request")
        specs = [f for f in changed if SPEC_RE.match(f)]
        if specs:
            errs.append(f"PR: internal work may not add or change feature specs: {', '.join(specs)}")
        site = [f for f in changed if f.startswith(SITE_CODE)]
        if site and cat != "security-maintenance":
            errs.append(f"PR: internal:{cat} may not change site code: {', '.join(site[:5])}")
    else:
        errs.append("PR: 'Classification:' must be 'requirement' or 'internal:<category>'")
    return errs


def git_changed(base: str) -> list[str]:
    out = subprocess.run(["git", "diff", "--name-only", "--diff-filter=ACMR", f"{base}...HEAD"],
                         cwd=ROOT, capture_output=True, text=True, check=True).stdout
    return [line for line in out.splitlines() if line]


def main(argv: list[str] | None = None) -> int:
    ap = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    ap.add_argument("--base", required=True)
    ap.add_argument("--event", type=Path, help="GitHub event JSON (pull_request)")
    ap.add_argument("--body-file", type=Path)
    a = ap.parse_args(argv)
    ids = json.loads(IDS_FILE.read_text(encoding="utf-8"))["ids"]
    open_items = json.loads(OPEN_FILE.read_text(encoding="utf-8"))
    changed = git_changed(a.base)

    errs: list[str] = []
    for f in changed:
        if SPEC_RE.match(f):
            errs += check_spec(f, (ROOT / f).read_text(encoding="utf-8"), ids, open_items)

    body = None
    if a.event and a.event.exists():
        pr = json.loads(a.event.read_text(encoding="utf-8")).get("pull_request")
        body = (pr or {}).get("body") if pr else None
        if pr is not None and body is None:
            body = ""
    elif a.body_file:
        body = a.body_file.read_text(encoding="utf-8")
    if body is not None:
        errs += check_pr(body, changed, ids, spec_trace_ids(ROOT, ids))

    for e in errs:
        print(f"::error::{e}" if "GITHUB_ACTIONS" in __import__("os").environ else e)
    checked = sum(1 for f in changed if SPEC_RE.match(f))
    print(f"scope-trace: {len(changed)} changed files, {checked} specs checked, "
          f"PR body {'checked' if body is not None else 'not checked'}, {len(errs)} problems")
    return 1 if errs else 0


if __name__ == "__main__":
    sys.exit(main())

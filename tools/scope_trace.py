"""Scope-trace check for pull requests (constitution M-1 to M-6, work categories).

Checks three things:

1. **Classification.** The PR body declares exactly one work category. `requirement` PRs cite obligation ids that
   are traced in a spec. `internal:<category>` PRs change no client-facing behaviour, except `security-maintenance`,
   which must give concrete evidence and describe the change.
2. **Path policy.** Every added, modified, renamed (old and new path) and deleted file is classified by
   PATH_POLICY, an explicit allow-list. Each category may touch only the path classes allowed for it. A path no rule
   covers, such as a new top-level directory, is rejected for every category until the policy is extended through
   an internal:governance PR. Governance-sensitive and delivery-affecting paths are reported and need extra
   declarations.
3. **Specs.** Every added, modified or renamed spec has a valid Register trace and valid acceptance criteria. Every
   token in a criterion's Traces must be a traced register id. Statuses come from a fixed set. P1-E criteria are
   never `final` while PRE-09 is unapproved. Open contradictions touching the spec are cited. Specs are never
   deleted.

What it cannot check: whether a criterion's behaviour is really supported by the wording of the row it cites, or
whether a declared delivery impact or security change is true. Those are review judgements. The checker makes them
visible.

Usage (CI):     python tools/scope_trace.py --base origin/main --event "$GITHUB_EVENT_PATH"
Usage (local):  python tools/scope_trace.py --base origin/main --body-file pr-body.md
Trusted run:    python <base copy>/tools/scope_trace.py --repo <checkout> --base origin/main --event ...
Without --event or --body-file only the spec and deletion checks run (for example on a push to main).
"""

from __future__ import annotations

import argparse
import fnmatch
import json
import os
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent

OBLIGATION = {"P1", "P1-L", "P1-E", "DLV"}
INTERNAL = ("governance", "ci", "test-infrastructure", "tooling", "security-maintenance", "documentation")
SPEC_RE = re.compile(r"^specs/\d{3}-[a-z0-9-]+/spec\.md$")
ID_TOKEN = re.compile(r"^[A-Z][A-Z0-9]{1,6}-\d{1,3}[a-z]?$")
ID_RE = re.compile(r"(?<![A-Za-z0-9-])([A-Z]{2,5}-\d{1,3}[a-z]?)(?![0-9A-Za-z])")

# Ordered: the first matching rule wins. fnmatch '*' also matches '/'.
PATH_POLICY: tuple[tuple[str, str], ...] = (
    ("specs/[0-9][0-9][0-9]-*", "feature-spec"),
    ("specs/.gitkeep", "workflow-config"),
    ("mizzey-site/tests/*", "site-tests"),
    ("mizzey-theme/tests/*", "site-tests"),
    ("mizzey-site/*", "site-code"),
    ("mizzey-theme/*", "site-code"),
    ("tools/build-dist.mjs", "delivery-tooling"),
    ("tools/corex-sync.mjs", "delivery-tooling"),
    ("corex.lock", "version-lock"),
    ("*composer.json", "version-lock"),
    ("*composer.lock", "version-lock"),
    ("*package.json", "version-lock"),
    ("*package-lock.json", "version-lock"),
    (".github/workflows/*", "governance-control"),
    (".githooks/*", "governance-control"),
    ("tools/scope_trace.py", "governance-control"),
    ("tools/house_rules.py", "governance-control"),
    ("tools/repo_checks.py", "governance-control"),
    ("tools/extract_register_ids.py", "governance-control"),
    ("tools/run_trusted.py", "governance-control"),
    (".specify/memory/constitution.md", "governance-control"),
    ("docs/scope/*", "governance-control"),
    ("stack.lock.json", "governance-control"),
    ("AGENTS.md", "governance-control"),
    ("CLAUDE.md", "governance-control"),
    ("CONTRIBUTING.md", "governance-control"),
    (".gitignore", "governance-control"),
    (".gitattributes", "governance-control"),
    ("tools/tests/*", "test-infra"),
    ("discovery/tests/*", "test-infra"),
    ("tools/*.py", "tooling"),
    ("tools/*.mjs", "tooling"),
    ("discovery/*", "tooling"),
    ("scripts/*", "tooling"),
    (".specify/*", "workflow-config"),
    (".claude/skills/*", "workflow-config"),
    (".github/*", "workflow-config"),
    # The UX and design source of truth. Data and prose, and under design/wireframes/ only, the approved wireframe
    # artefact format: standalone static HTML and CSS, which are design prototypes and not application code. No
    # script and no other file type is accepted anywhere under design/, so nothing executable can arrive there.
    ("design/*.md", "design"),
    ("design/*.json", "design"),
    ("design/wireframes/*.html", "design"),
    ("design/wireframes/*.css", "design"),
    ("docs/engagement/*", "docs"),
    ("docs/*.md", "docs"),
    ("README.md", "docs"),
    ("PROGRESS.md", "docs"),
    ("DECISIONS.md", "docs"),
    ("COREX-WORKAROUNDS.md", "docs"),
)

ALLOWED: dict[str, set[str]] = {
    "requirement": {"feature-spec", "site-code", "site-tests", "test-infra", "docs", "delivery-tooling", "version-lock",
                    "design"},
    "governance": {"governance-control", "workflow-config", "test-infra", "tooling", "docs", "design"},
    "ci": {"governance-control", "workflow-config", "test-infra", "docs"},
    "test-infrastructure": {"test-infra", "site-tests", "docs"},
    "tooling": {"tooling", "test-infra", "delivery-tooling", "docs"},
    "documentation": {"docs", "design"},
    "security-maintenance": {"site-code", "site-tests", "test-infra", "tooling", "delivery-tooling", "version-lock",
                             "docs"},
}
SENSITIVE = {"governance-control", "delivery-tooling", "version-lock"}
DELIVERY = {"delivery-tooling", "version-lock"}
TEST_CLASSES = {"site-tests", "test-infra"}

STATUS_FIXED = {"final", "provisional"}
STATUS_PENDING = re.compile(r"^pending (CX-\d{2}|PRE-09|OD-\d{2})$")
EVIDENCE_RE = re.compile(r"(CVE-\d{4}-\d{4,}|GHSA-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}|https?://\S+|^local:\s*\S.{15,})",
                         re.I)


def classify(path: str) -> str:
    for pattern, cls in PATH_POLICY:
        if fnmatch.fnmatchcase(path, pattern):
            return cls
    return "unclassified"


def strip_comments(text: str) -> str:
    return re.sub(r"<!--.*?-->", "", text, flags=re.S)


def section(md: str, title: str) -> str | None:
    out: list[str] | None = None
    for line in md.splitlines():
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


def check_status(label: str, status: str, cited_scopes: set[str], pre09_open: bool, open_items: dict,
                 path: str) -> list[str]:
    s = status.strip()
    if s not in STATUS_FIXED and not STATUS_PENDING.match(s):
        return [f"{path}: criterion {label} status {status!r} is not allowed; use final, provisional, "
                "or pending CX-nn / PRE-09 / OD-nn"]
    errs = []
    m = STATUS_PENDING.match(s)
    if m and m.group(1).startswith("CX-"):
        cx = open_items.get("contradictions", {}).get(m.group(1))
        if cx is None:
            errs.append(f"{path}: criterion {label} is pending {m.group(1)}, which is not in open-items.json")
    if "P1-E" in cited_scopes and pre09_open and s == "final":
        errs.append(f"{path}: criterion {label} traces a P1-E id and is final while PRE-09 is not approved (M-3); "
                    "use provisional or pending PRE-09")
    return errs


def engineering_ids(open_items: dict) -> dict[str, str]:
    """Rows a spec may trace although the register does not carry them as an obligation: id -> the CX that allows it.

    Only a contradiction Mustafa has resolved can list one, under `resolution.engineering_ids`. It lets engineering
    proceed on his decision while the contract-side correction is outstanding. It never makes a criterion
    acceptable: every criterion citing such a row must be `pending CX-nn`.
    """
    out = {}
    for cx, item in open_items.get("contradictions", {}).items():
        if item.get("status") == "resolved":
            for rid in item.get("resolution", {}).get("engineering_ids", []):
                out[rid] = cx
    return out


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
    engineering = engineering_ids(open_items)
    for r in trace:
        rid = r.get("id", "").strip("`* ")
        if rid not in ids:
            errs.append(f"{path}: Register trace id {rid!r} is not in the Feature Register")
            continue
        reg = ids[rid]
        scope, stage = r.get("scope", "").strip("`* "), r.get("stage", "").strip("`* ")
        if reg["scope"] not in OBLIGATION:
            if rid not in engineering:
                errs.append(f"{path}: {rid} is {reg['scope']} in the register and creates no obligation; "
                            "move it to 'Context rows'")
                continue
            # An owner-resolved contradiction lets engineering trace the row. It is still not an obligation in the
            # register, so the scope and stage are written as the register has them and no criterion may be final.
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
        tokens = [t for t in re.split(r"[,;\s]+", r.get("traces", "").replace("`", "")) if t]
        if not tokens:
            errs.append(f"{path}: criterion {label} cites no register id in Traces")
        cited = []
        for t in tokens:
            if not ID_TOKEN.match(t):
                errs.append(f"{path}: criterion {label} Traces has {t!r}, which is not a register id")
            elif t not in ids:
                errs.append(f"{path}: criterion {label} cites {t}, which is not in the Feature Register")
            elif t not in traced:
                errs.append(f"{path}: criterion {label} cites {t}, which is not in the Register trace")
            else:
                cited.append(t)
        stray = sorted({i for i in ID_RE.findall(r.get("criterion", "")) if i in ids} - set(traced))
        if stray:
            errs.append(f"{path}: criterion {label} refers to {', '.join(stray)}, which "
                        "{} not in the Register trace".format("is" if len(stray) == 1 else "are"))
        errs += check_status(label, r.get("status", ""), {traced[i]["scope"] for i in cited}, pre09_open,
                             open_items, path)
        for i in cited:
            if i in engineering and r.get("status", "").strip() != f"pending {engineering[i]}":
                errs.append(f"{path}: criterion {label} cites {i}, which the register does not carry as an "
                            f"obligation; it is traced only under {engineering[i]}, so its status must be "
                            f"'pending {engineering[i]}'")

    open_body = section(md, "Open contract items") or ""
    for cx, item in open_items.get("contradictions", {}).items():
        if item.get("status") != "open":
            continue
        hit = sorted((set(item.get("ids", [])) | set(item.get("related_ids", []))) & set(traced))
        if hit and cx not in open_body:
            errs.append(f"{path}: {', '.join(hit)} touched by open contradiction {cx}; cite it under "
                        "'## Open contract items' (M-2)")
    return errs


def fields(body: str, name: str) -> list[str]:
    pat = rf"^\**{re.escape(name)}\**\s*:\s*\**\s*(.*?)\s*$"
    return [m.strip("`* ") for m in re.findall(pat, body, flags=re.M | re.I)]


def one_field(body: str, name: str, errs: list[str]) -> str:
    vals = fields(body, name)
    if len(vals) > 1:
        errs.append(f"PR: '{name}:' appears {len(vals)} times; give it exactly once")
    return vals[0] if vals else ""


def spec_trace_ids(root: Path, ids: dict) -> set[str]:
    out: set[str] = set()
    for spec in sorted(root.glob("specs/[0-9][0-9][0-9]-*/spec.md")):
        body = section(strip_comments(spec.read_text(encoding="utf-8")), "Register trace") or ""
        out.update(r.get("id", "").strip("`* ") for r in table_rows(body))
    return out & set(ids)


def touched_paths(changes: list[tuple[str, str, str | None]]) -> list[str]:
    out = []
    for _, path, old in changes:
        out.append(path)
        if old:
            out.append(old)
    return out


def check_deletions(changes: list[tuple[str, str, str | None]]) -> list[str]:
    errs = []
    for status, path, old in changes:
        gone = old if status == "R" else (path if status == "D" else None)
        if gone and SPEC_RE.match(gone):
            errs.append(f"{gone}: feature specs are never deleted or moved; mark the spec superseded instead")
    return errs


def sensitive_report(changes: list[tuple[str, str, str | None]]) -> list[tuple[str, str]]:
    return sorted({(p, classify(p)) for p in touched_paths(changes) if classify(p) in SENSITIVE})


def check_spec_branch(changes: list[tuple[str, str, str | None]], head_ref: str | None) -> list[str]:
    """A newly added spec's directory must equal the branch name (AGENTS.md workflow step 2 and 3)."""
    if not head_ref:
        return []
    errs = []
    for status, path, _ in changes:
        if status == "A" and SPEC_RE.match(path) and path.split("/")[1] != head_ref:
            errs.append(f"{path}: new spec directory {path.split('/')[1]!r} does not match the branch {head_ref!r}; "
                        "create the branch from the Spec Kit name and pass SPECIFY_FEATURE_DIRECTORY")
    return errs


def check_pr(body: str, changes: list[tuple[str, str, str | None]], ids: dict, traced_in_specs: set[str]) -> list[str]:
    body = strip_comments(body or "")
    errs: list[str] = []
    cls = one_field(body, "Classification", errs).lower()
    change = one_field(body, "Client-facing behaviour change", errs)
    classes = {p: classify(p) for p in touched_paths(changes)}

    if cls == "requirement":
        cat = "requirement"
        cited = ID_RE.findall(one_field(body, "Requirement ids", errs))
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
            errs.append(f"PR: unknown internal category {cat!r}; use one of {', '.join(INTERNAL)}")
            return errs
        if not change:
            errs.append("PR: internal work must state 'Client-facing behaviour change: none' "
                        "(security-maintenance: describe the change)")
        elif change.lower() != "none" and cat != "security-maintenance":
            errs.append(f"PR: internal:{cat} may not change client-facing behaviour; reclassify as "
                        "requirement with a register id, or raise a Change Request")
        if cat == "security-maintenance":
            evidence = one_field(body, "Security evidence", errs)
            if not EVIDENCE_RE.search(evidence):
                errs.append("PR: security-maintenance needs 'Security evidence:' with a CVE or GHSA id, an advisory "
                            "or upgrade URL, or 'local: <defect and how it was found>'")
            elif evidence.lower().startswith("local:") and not any(c in TEST_CLASSES for c in classes.values()):
                errs.append("PR: a local security defect must come with a regression test in this PR")
            if not one_field(body, "Security change", errs):
                errs.append("PR: security-maintenance needs 'Security change:' describing what changes and for whom")
    else:
        errs.append("PR: 'Classification:' must be 'requirement' or 'internal:<category>'")
        return errs

    allowed = ALLOWED[cat]
    for path, pc in sorted(classes.items()):
        if pc == "unclassified":
            errs.append(f"PR: {path} is not covered by the path policy (PATH_POLICY in tools/scope_trace.py). "
                        "New application directories are not allowed; extend the policy in an internal:governance PR")
        elif pc not in allowed:
            errs.append(f"PR: {cls} may not change {path} ({pc})")
    touched_classes = set(classes.values())
    if touched_classes & SENSITIVE and not one_field(body, "Sensitive changes", errs):
        errs.append("PR: governance-sensitive or delivery-affecting paths changed; add 'Sensitive changes:' "
                    "explaining each for the reviewer")
    if touched_classes & DELIVERY and not one_field(body, "Delivery impact", errs):
        errs.append("PR: build, deployment or version-lock files changed; add 'Delivery impact:' describing what "
                    "changes in the deployed artifact (or why nothing does)")
    return errs


def git_changes(repo: Path, base: str) -> list[tuple[str, str, str | None]]:
    out = subprocess.run(["git", "diff", "--name-status", "-M", f"{base}...HEAD"], cwd=repo, capture_output=True,
                         text=True, check=True, encoding="utf-8").stdout
    changes = []
    for line in out.splitlines():
        parts = line.split("\t")
        status = parts[0][0]
        if status == "R" or status == "C":
            changes.append(("R" if status == "R" else "A", parts[2], parts[1] if status == "R" else None))
        else:
            changes.append((status, parts[1], None))
    return changes


def current_branch(repo: Path) -> str | None:
    """The checked-out branch, for local runs without a GitHub event. None when detached (as in CI checkouts)."""
    out = subprocess.run(["git", "rev-parse", "--abbrev-ref", "HEAD"], cwd=repo, capture_output=True, text=True)
    name = out.stdout.strip()
    return name if out.returncode == 0 and name and name != "HEAD" else None


def main(argv: list[str] | None = None) -> int:
    ap = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    ap.add_argument("--base", required=True)
    ap.add_argument("--repo", type=Path, default=ROOT, help="checkout to judge (default: this script's repository)")
    ap.add_argument("--event", type=Path, help="GitHub event JSON (pull_request)")
    ap.add_argument("--body-file", type=Path)
    ap.add_argument("--head-ref", help="PR branch name (read from --event when omitted)")
    a = ap.parse_args(argv)
    ids = json.loads((ROOT / "docs" / "scope" / "register-ids.json").read_text(encoding="utf-8"))["ids"]
    open_items = json.loads((ROOT / "docs" / "scope" / "open-items.json").read_text(encoding="utf-8"))
    repo = a.repo.resolve()
    changes = git_changes(repo, a.base)

    errs = check_deletions(changes)
    for status, path, _ in changes:
        if status != "D" and SPEC_RE.match(path):
            errs += check_spec(path, (repo / path).read_text(encoding="utf-8"), ids, open_items)

    body, head_ref = None, a.head_ref
    if a.event and a.event.exists():
        pr = json.loads(a.event.read_text(encoding="utf-8")).get("pull_request")
        if pr is not None:
            body = pr.get("body") or ""
            head_ref = head_ref or (pr.get("head") or {}).get("ref")
    elif a.body_file:
        body = a.body_file.read_text(encoding="utf-8")
    if body is not None:
        errs += check_pr(body, changes, ids, spec_trace_ids(repo, ids))
        errs += check_spec_branch(changes, head_ref or current_branch(repo))

    gh = "GITHUB_ACTIONS" in os.environ
    for path, pc in sensitive_report(changes):
        print(f"::warning::sensitive change: {path} ({pc})" if gh else f"SENSITIVE {path} ({pc})")
    for e in errs:
        print(f"::error::{e}" if gh else e)
    specs = sum(1 for s, p, _ in changes if s != "D" and SPEC_RE.match(p))
    print(f"scope-trace: {len(changes)} changed files, {specs} specs checked, "
          f"{len(sensitive_report(changes))} sensitive, PR body {'checked' if body is not None else 'not checked'}, "
          f"{len(errs)} problems")
    return 1 if errs else 0


if __name__ == "__main__":
    sys.exit(main())

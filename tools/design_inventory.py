"""Check the design inventory against the signed scope, and write its coverage report.

`design/inventory/` is the UX source of truth: surfaces, flows, components and placeholders. This tool holds it to
the register mirror (`docs/scope/register-ids.json`) and to PBI ownership (`docs/scope/backlog-ownership.json`),
and writes `design/coverage.md`. It reads no contract document: the signed sources stay outside the repository.

What it fails on:

- A surface with a missing field or a value outside the fixed sets.
- A `contracted` surface with no delivery row, with a row that creates no obligation (DEF, P2, P3, OUT), or with a
  row its `owner_pbi` does not own.
- A surface that is not `contracted` and still cites a register row, or does not say why it exists.
- A flow step, a component, a placeholder or a `composes` entry that points at a surface that does not exist.
- A customer-facing surface that does not account for both reading directions.
- A row whose wording names an email and that no email surface carries.
- A delivery row that is neither on a surface nor marked `no_surface` with a reason, or that is marked both.
- Derived fields that have drifted: `stage`, `flow_steps`, a placeholder's `affected_surfaces`.
- A `design/coverage.md` that is not what this tool would write.

What it cannot check: whether a surface is the right reading of the wording of its rows. That is a review
judgement, made against the signed register.

Usage: python tools/design_inventory.py            check, and fail on a problem or a stale report
       python tools/design_inventory.py --write    check, then write design/coverage.md
"""

from __future__ import annotations

import argparse
import io
import json
import sys
from collections import Counter
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent

KINDS = ("page", "shell", "overlay", "component", "email", "admin_extension", "report", "system_state", "message")
SCOPE_TYPES = ("contracted", "native_required", "internal_operational", "provider_hosted")
AUDIENCES = ("visitor", "customer", "staff", "developer", "system")
FREEDOM = ("free", "constrained", "annotate_only", "none")
STATUSES = ("inventoried", "briefed", "wireframed", "reviewed", "branded", "approved", "not_designed")
ERP = ("none", "partial", "per PRE-09")
FIELDS = ("id", "name", "kind", "scope_type", "audience", "register_ids", "context_ids", "excluded_ids", "owner_pbi",
          "stage", "route", "flow_steps", "acceptance", "states", "responsive", "rtl", "native_baseline",
          "design_freedom", "open_items", "erp_dependency", "status")
STAGES = {"S1": "S1", "S2": "S2", "-": "per PRE-09"}
# Rows whose register wording names an email. Each must be carried by a surface of kind `email`.
EMAIL_ROWS = ("NOTF-01", "NOTF-02", "NOTF-03", "NOTF-04", "NOTF-06", "NOTF-08", "AUTH-04", "AUTH-05", "AUTH-12",
              "REV-05")
CUSTOMER_FACING = {"visitor", "customer"}


def load(root: Path) -> dict:
    def read(*parts: str):
        return json.loads(root.joinpath(*parts).read_text(encoding="utf-8"))

    surfaces = read("design", "inventory", "surfaces.json")
    return {
        "surfaces": surfaces["surfaces"],
        "no_surface": surfaces["no_surface"],
        "flows": read("design", "inventory", "flows.json")["flows"],
        "components": read("design", "inventory", "components.json")["components"],
        "placeholders": read("design", "inventory", "placeholders.json")["placeholders"],
        "sources": read("design", "sources.json")["sources"],
        "ids": read("docs", "scope", "register-ids.json")["ids"],
        "slices": read("docs", "scope", "backlog-ownership.json")["slices"],
    }


def owners(slices: list[dict]) -> dict[str, object]:
    """Register id -> the PBI that accepts it: the issue number, or the slice key for an unseeded slice."""
    return {rid: (s["issue"] or s["key"]) for s in slices for rid in s["ids"]}


def stage_of(register_ids: list[str], ids: dict) -> str | None:
    found = sorted({ids[r]["stage"] for r in register_ids if r in ids})
    if not found:
        return None
    return STAGES[found[0]] if len(found) == 1 else "mixed: " + ", ".join(found)


def flow_steps(flows: list[dict]) -> dict[str, list[str]]:
    out: dict[str, list[str]] = {}
    for f in flows:
        for step in f["steps"]:
            for sid in step["surfaces"]:
                out.setdefault(sid, []).append(step["step"])
    return out


def affected(surfaces: list[dict]) -> dict[str, list[str]]:
    out: dict[str, list[str]] = {}
    for s in surfaces:
        for item in s["open_items"]:
            out.setdefault(item, []).append(s["id"])
    return out


def classify_rows(data: dict) -> dict[str, dict]:
    """Every delivery row -> how the inventory accounts for it.

    `direct`: a surface owned by the row's own PBI carries it. `via_surface`: it shapes a surface another PBI owns,
    as context or as an acceptance scenario. `no_surface`: marked so, with a reason. `uncovered`: none of these.
    """
    ids = data["ids"]
    direct: dict[str, list[str]] = {}
    via: dict[str, list[str]] = {}
    for s in data["surfaces"]:
        for r in s["register_ids"]:
            direct.setdefault(r, []).append(s["id"])
        for r in s["context_ids"] + s["acceptance"]["scenarios"]:
            via.setdefault(r, []).append(s["id"])
    marked = {r: reason for reason, block in data["no_surface"].items() for r in block["ids"]}
    out = {}
    for rid, row in ids.items():
        if not row["obligation"]:
            continue
        if rid in direct:
            out[rid] = {"how": "direct", "surfaces": direct[rid]}
        elif rid in via:
            out[rid] = {"how": "via_surface", "surfaces": via[rid]}
        elif rid in marked:
            out[rid] = {"how": "no_surface", "reason": marked[rid]}
        else:
            out[rid] = {"how": "uncovered"}
    return out


def validate(data: dict) -> list[str]:
    errs: list[str] = []
    ids, own = data["ids"], owners(data["slices"])
    surfaces = data["surfaces"]
    known = {s["id"] for s in surfaces}
    placeholder_ids = {p["id"] for p in data["placeholders"]}
    for sid, n in Counter(s["id"] for s in surfaces).items():
        if n > 1:
            errs.append(f"surface {sid}: id used {n} times")

    for s in surfaces:
        sid = s.get("id", "?")
        for f in FIELDS:
            if f not in s:
                errs.append(f"surface {sid}: missing field {f}")
        if any(f not in s for f in FIELDS):
            continue
        for value, allowed, label in ((s["kind"], KINDS, "kind"), (s["scope_type"], SCOPE_TYPES, "scope_type"),
                                      (s["design_freedom"], FREEDOM, "design_freedom"),
                                      (s["status"], STATUSES, "status"), (s["erp_dependency"], ERP, "erp_dependency")):
            if value not in allowed:
                errs.append(f"surface {sid}: {label} {value!r} is not one of {', '.join(allowed)}")
        if not s["audience"] or any(a not in AUDIENCES for a in s["audience"]):
            errs.append(f"surface {sid}: audience must be drawn from {', '.join(AUDIENCES)}")
        for r in s["register_ids"] + s["context_ids"]:
            if r not in ids:
                errs.append(f"surface {sid}: {r} is not in the Feature Register")
            elif not ids[r]["obligation"]:
                errs.append(f"surface {sid}: {r} is {ids[r]['scope']} and creates no launch obligation; "
                            "move it to excluded_ids")
        for r in s["excluded_ids"]:
            if r not in ids:
                errs.append(f"surface {sid}: excluded id {r} is not in the Feature Register")
            elif ids[r]["obligation"]:
                errs.append(f"surface {sid}: {r} is a delivery row and cannot be listed as excluded")
        if s["scope_type"] == "contracted":
            if not s["register_ids"]:
                errs.append(f"surface {sid}: contracted, and backed by no delivery row")
            for r in s["register_ids"]:
                if r in own and own[r] != s["owner_pbi"]:
                    errs.append(f"surface {sid}: {r} is owned by {own[r]}, not by {s['owner_pbi']} "
                                "(one row, one PBI); cite it under context_ids")
        else:
            if s["register_ids"] or s["context_ids"]:
                errs.append(f"surface {sid}: {s['scope_type']} surfaces cite no register row")
            if s["owner_pbi"] is not None:
                errs.append(f"surface {sid}: {s['scope_type']} surfaces have no accepting PBI")
            if not s.get("scope_note"):
                errs.append(f"surface {sid}: {s['scope_type']} surfaces must say why they exist (scope_note)")
        if s["stage"] != stage_of(s["register_ids"], ids):
            errs.append(f"surface {sid}: stage {s['stage']!r} is not what its rows give "
                        f"({stage_of(s['register_ids'], ids)!r})")
        for r in s["acceptance"]["scenarios"]:
            if r not in ids or not r.startswith("AC-"):
                errs.append(f"surface {sid}: acceptance scenario {r} is not an AC row of the register")
        for c in s.get("composes", []):
            if c not in known:
                errs.append(f"surface {sid}: composes {c}, which is not a surface")
        for item in s["open_items"]:
            if item not in placeholder_ids:
                errs.append(f"surface {sid}: open item {item} has no entry in placeholders.json")
        if not s["states"] or any("state" not in st for st in s["states"]):
            errs.append(f"surface {sid}: states must each name a state")
        for st in s["states"]:
            if st.get("requires") and st["requires"] not in ids:
                errs.append(f"surface {sid}: state {st['state']} requires {st['requires']}, which is not in the register")
        if set(s["responsive"]) != {"mobile", "tablet", "desktop"}:
            errs.append(f"surface {sid}: responsive must describe mobile, tablet and desktop")
        rtl = s["rtl"]
        if CUSTOMER_FACING & set(s["audience"]) and s["scope_type"] != "provider_hosted":
            if rtl.get("applies") is not True or not rtl.get("mirrors") or not rtl.get("does_not_mirror"):
                errs.append(f"surface {sid}: a customer-facing surface must account for English left to right and "
                            "Arabic right to left (rtl.applies, mirrors, does_not_mirror)")
        elif rtl.get("applies") is not True and not rtl.get("reason"):
            errs.append(f"surface {sid}: rtl does not apply and gives no reason")

    steps = flow_steps(data["flows"])
    for f in data["flows"]:
        for step in f["steps"]:
            if not step["surfaces"]:
                errs.append(f"flow step {step['step']}: resolves to no surface")
            for sid in step["surfaces"]:
                if sid not in known:
                    errs.append(f"flow step {step['step']}: {sid} is not a surface")
            for r in step["register_ids"]:
                if r not in ids or not ids[r]["obligation"]:
                    errs.append(f"flow step {step['step']}: {r} is not a delivery row")
    for s in surfaces:
        if s.get("flow_steps") != steps.get(s["id"], []):
            errs.append(f"surface {s['id']}: flow_steps differs from flows.json")

    for c in data["components"]:
        for sid in c["used_by"]:
            if sid not in known:
                errs.append(f"component {c['id']}: used by {sid}, which is not a surface")
        for r in c["register_ids"]:
            if r not in ids or not ids[r]["obligation"]:
                errs.append(f"component {c['id']}: {r} is not a delivery row")
    for cid, n in Counter(c["id"] for c in data["components"]).items():
        if n > 1:
            errs.append(f"component {cid}: id used {n} times")

    used = affected(surfaces)
    for p in data["placeholders"]:
        if p["affected_surfaces"] != used.get(p["id"], []):
            errs.append(f"placeholder {p['id']}: affected_surfaces differs from the surfaces that cite it")
        for field in ("unknown", "placeholder_allowed", "must_not_assume", "replaced_by"):
            if not p.get(field):
                errs.append(f"placeholder {p['id']}: {field} is empty")

    email = {r for s in surfaces if s["kind"] == "email" for r in s["register_ids"] + s["context_ids"]}
    for r in EMAIL_ROWS:
        if r not in email:
            errs.append(f"{r} names an email and no email surface carries it")

    marked: dict[str, str] = {}
    for reason, block in data["no_surface"].items():
        if not block.get("reason"):
            errs.append(f"no_surface {reason}: gives no reason")
        for r in block["ids"]:
            if r not in ids or not ids[r]["obligation"]:
                errs.append(f"no_surface {reason}: {r} is not a delivery row")
            if r in marked:
                errs.append(f"no_surface: {r} is listed under {marked[r]} and {reason}")
            marked[r] = reason
    for rid, c in classify_rows(data).items():
        if c["how"] == "uncovered":
            errs.append(f"{rid}: on no surface and not marked no_surface")
        elif c["how"] != "no_surface" and rid in marked:
            errs.append(f"{rid}: marked no_surface ({marked[rid]}) and also on {', '.join(c['surfaces'])}")

    for src in data["sources"]:
        for field in ("id", "name", "version", "role", "authority_rank", "source"):
            if src.get(field) in (None, ""):
                errs.append(f"sources.json {src.get('id', '?')}: {field} is empty")
    return errs


def pbi_label(pbi) -> str:
    return "none" if pbi is None else f"#{pbi}" if isinstance(pbi, int) else str(pbi)


def dependency_findings(data: dict) -> dict[str, list]:
    """Where a PBI's `design_dependency` and the surfaces it owns disagree. Reported, never changed here."""
    by_owner: dict[object, list[dict]] = {}
    for s in data["surfaces"]:
        if s["owner_pbi"] is not None:
            by_owner.setdefault(s["owner_pbi"], []).append(s)
    missing, admin_only, none_owned = [], [], []
    for sl in data["slices"]:
        key = sl["issue"] or sl["key"]
        mine = by_owner.get(key, [])
        facing = [s for s in mine if CUSTOMER_FACING & set(s["audience"])]
        if sl["design_dependency"] == "none" and facing:
            missing.append((key, sl["title"], facing))
        elif sl["design_dependency"] == "none" and mine:
            admin_only.append((key, sl["title"], mine))
        elif sl["design_dependency"] != "none" and not mine:
            none_owned.append((key, sl["title"]))
    noted = [s for s in data["surfaces"] if s.get("build_note")]
    return {"missing": missing, "admin_only": admin_only, "none_owned": none_owned, "noted": noted}


def table(header: list[str], rows: list[list[object]]) -> list[str]:
    out = ["| " + " | ".join(header) + " |", "|" + "|".join("---" for _ in header) + "|"]
    out += ["| " + " | ".join(str(c) for c in r) + " |" for r in rows]
    return out


def counted(counter: Counter, order: tuple[str, ...] | None = None) -> list[list[object]]:
    keys = [k for k in order if k in counter] if order else sorted(counter, key=lambda k: (-counter[k], str(k)))
    return [[k, counter[k]] for k in keys]


def render(data: dict) -> str:
    surfaces, ids = data["surfaces"], data["ids"]
    rows = classify_rows(data)
    how = Counter(c["how"] for c in rows.values())
    delivery = len(rows)
    L: list[str] = []
    L += ["# Design inventory coverage", "",
          "Generated by `tools/design_inventory.py --write` from `design/inventory/`, "
          "`docs/scope/register-ids.json` and `docs/scope/backlog-ownership.json`. Do not edit it by hand: change "
          "the inventory and regenerate. `tools/tests/test_design_inventory.py` fails when it is stale.", "",
          "**This report is not a design and approves nothing.** It shows that the inventory accounts for the signed "
          "scope. Whether each surface is the right reading of its rows is a review judgement.", ""]

    L += ["## 1. The inventory", ""]
    L += table(["Measure", "Count"], [["Surfaces", len(surfaces)], ["Flows", len(data["flows"])],
                                      ["Flow steps", sum(len(f["steps"]) for f in data["flows"])],
                                      ["Components", len(data["components"])],
                                      ["Open design inputs", len(data["placeholders"])]])
    L += ["", "**Surfaces by kind**", ""]
    L += table(["Kind", "Surfaces"], counted(Counter(s["kind"] for s in surfaces), KINDS))
    L += ["", "**Surfaces by scope type**", ""]
    L += table(["Scope type", "Surfaces"], counted(Counter(s["scope_type"] for s in surfaces), SCOPE_TYPES))
    L += ["", "**Surfaces by audience.** A surface seen by two audiences counts under each.", ""]
    L += table(["Audience", "Surfaces"], counted(Counter(a for s in surfaces for a in s["audience"]), AUDIENCES))
    L += ["", "**Surfaces by stage**", ""]
    L += table(["Stage", "Surfaces"], counted(Counter(s["stage"] or "no register row" for s in surfaces)))
    L += ["", "**Surfaces by accepting PBI.** `none` is a surface with no register row, which no PBI accepts.", ""]
    titles = {(sl["issue"] or sl["key"]): sl["title"] for sl in data["slices"]}
    by_pbi = Counter(s["owner_pbi"] for s in surfaces)
    L += table(["PBI", "Surfaces", "Title"],
               [[pbi_label(k), n, titles.get(k, "")] for k, n in
                sorted(by_pbi.items(), key=lambda kv: (kv[0] is None, isinstance(kv[0], str), str(kv[0]).zfill(6)))])

    L += ["", "## 2. Delivery rows", "",
          f"The register has {delivery} delivery rows (P1, P1-L, P1-E, DLV). Each is accounted for exactly once.", ""]
    L += table(["How the row is accounted for", "Rows"], [
        ["On a surface its own PBI owns", how["direct"]],
        ["On a surface another PBI owns, as context or as an acceptance scenario", how["via_surface"]],
        ["**Design-relevant, total**", f"**{how['direct'] + how['via_surface']}**"],
        ["Marked `no_surface`, with a reason", how["no_surface"]],
        ["**Uncovered**", f"**{how['uncovered']}**"]])
    L += ["", "**Rows with no surface, by reason**", ""]
    L += table(["Reason", "Rows", "What it means"],
               [[f"`{k}`", len(b["ids"]), b["reason"]] for k, b in data["no_surface"].items()])
    L += ["", "The ids under each reason are in `design/inventory/surfaces.json`, `no_surface`.", ""]
    via = sorted((r, c["surfaces"]) for r, c in rows.items() if c["how"] == "via_surface")
    L += ["**Rows carried by a surface another PBI owns**", "",
          "The row stays with its own PBI for acceptance. The surface it shapes is drawn once, by its owner.", ""]
    L += table(["Row", "Scope", "Surfaces"], [[r, ids[r]["scope"], ", ".join(f"`{x}`" for x in sorted(set(s))[:6]) +
                                               (" and more" if len(set(s)) > 6 else "")] for r, s in via])

    L += ["", "## 3. Checks", "",
          "Each line is enforced by `tools/design_inventory.py` and fails CI when it does not hold.", ""]
    contracted = [s for s in surfaces if s["scope_type"] == "contracted"]
    facing = [s for s in surfaces if CUSTOMER_FACING & set(s["audience"]) and s["scope_type"] != "provider_hosted"]
    L += table(["Check", "Result"], [
        ["Every delivery row is on a surface or marked `no_surface` with a reason", f"{delivery} of {delivery}"],
        ["No contracted surface cites a DEF, P2, P3 or OUT row as a launch obligation",
         f"{len(contracted)} contracted surfaces, 0 such rows"],
        ["Every row of a contracted surface is owned by that surface's PBI", "Holds"],
        ["No surface outside contracted scope cites a register row", f"{len(surfaces) - len(contracted)} surfaces"],
        ["Every flow step resolves to a surface", f"{sum(len(f['steps']) for f in data['flows'])} steps"],
        ["Every customer-facing surface accounts for English left to right and Arabic right to left",
         f"{len(facing)} surfaces"],
        ["Every row that names an email is carried by an email surface", f"{len(EMAIL_ROWS)} rows"],
        ["Every open item on a surface has a placeholder rule", f"{len(data['placeholders'])} rules"]])

    f = dependency_findings(data)
    L += ["", "## 4. PBIs and their design dependency", "",
          "`design_dependency` in `docs/scope/backlog-ownership.json` is compared with the surfaces each PBI owns. "
          "**Nothing is changed here.** These are findings for the owner.", "",
          "**4.1 Marked `none`, and owns a customer-facing surface.** The field does not reflect these surfaces.", ""]
    L += table(["PBI", "Title", "Customer-facing surfaces it owns"],
               [[pbi_label(k), t, ", ".join(f"`{s['id']}`" for s in ss)] for k, t, ss in f["missing"]]) \
        if f["missing"] else ["None."]
    L += ["", "**4.2 Marked `none`, and owns staff surfaces only.** These are additions to the standard "
              "administration. They are annotated, not part of the storefront interface design, so `none` may be "
              "right. Listed so the choice is deliberate.", ""]
    L += table(["PBI", "Title", "Staff surfaces it owns"],
               [[pbi_label(k), t, ", ".join(f"`{s['id']}`" for s in ss)] for k, t, ss in f["admin_only"]]) \
        if f["admin_only"] else ["None."]
    L += ["", "**4.3 Marked `needs design`, and owns no surface.** Expected for a PBI that owns the interface design as a "
              "whole (PRE-03b) and no single surface.", ""]
    L += table(["PBI", "Title"], [[pbi_label(k), t] for k, t in f["none_owned"]]) if f["none_owned"] else ["None."]
    L += ["", "**4.4 Surfaces whose backing rows sit with a PBI that does not build the screen.**", ""]
    L += table(["Surface", "Accepting PBI", "Finding"],
               [[f"`{s['id']}`", pbi_label(s["owner_pbi"]), s["build_note"]] for s in f["noted"]]) \
        if f["noted"] else ["None."]

    L += ["", "## 5. Open design inputs", "",
          "Each has a rule in `design/inventory/placeholders.json`: what may stand in now, and what may not be "
          "assumed.", ""]
    L += table(["Id", "Kind", "Input", "Surfaces affected"],
               [[p["id"], p["kind"], p["title"], len(p["affected_surfaces"])] for p in data["placeholders"]])

    L += ["", "## 6. Every surface", ""]
    L += table(["Id", "Surface", "Kind", "Scope type", "PBI", "Stage", "Rows"],
               [[f"`{s['id']}`", s["name"], s["kind"], s["scope_type"], pbi_label(s["owner_pbi"]),
                 s["stage"] or "", len(s["register_ids"])] for s in surfaces])
    return "\n".join(L) + "\n"


def main(argv: list[str] | None = None) -> int:
    ap = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    ap.add_argument("--write", action="store_true", help="write design/coverage.md")
    a = ap.parse_args(argv)
    data = load(ROOT)
    errs = validate(data)
    report = ROOT / "design" / "coverage.md"
    if not errs:
        text = render(data)
        if a.write:
            with io.open(report, "w", encoding="utf-8", newline="\n") as fh:
                fh.write(text)
        elif not report.exists() or report.read_text(encoding="utf-8") != text:
            errs.append("design/coverage.md is stale; run python tools/design_inventory.py --write")
    for e in errs:
        print(e)
    print(f"design-inventory: {len(data['surfaces'])} surfaces, {len(data['flows'])} flows, "
          f"{len(data['components'])} components, {len(data['placeholders'])} placeholders, {len(errs)} problems")
    return 1 if errs else 0


if __name__ == "__main__":
    sys.exit(main())

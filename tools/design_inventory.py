"""Check the design inventory against the signed scope, and write its coverage report.

`design/inventory/` is the UX source of truth: surfaces, flows, components and placeholders. This tool holds it to
the register mirror (`docs/scope/register-ids.json`) and to PBI ownership (`docs/scope/backlog-ownership.json`),
and writes `design/coverage.md`. It reads no contract document: the signed sources stay outside the repository.

What it fails on:

- A surface with a missing field or a value outside the fixed sets.
- A `contracted` surface with no delivery row, with a row that creates no obligation (DEF, P2, P3, OUT), or with a
  row its `owner_pbi` does not own.
- A surface that is not `contracted` and still cites a register row, or does not say why it exists.
- A `build_pbi` that is not a PBI, a contracted surface with none, or a build PBI that differs from the accepting
  PBI without a `build_note` saying why.
- A decision id on a surface that is not an owner design decision, or a decision recorded as a client confirmation
  or as added scope.
- An interaction option with fewer than two candidate patterns, with a status outside `open`, `provisional` and
  `locked`, with a baseline or a selection that is not one of its candidates, or that claims more than its status
  allows: an open option has no baseline, and only a locked one has a selected pattern and a decision source.
- An interaction option whose constraints are untyped, or whose `constrained_by` does not say what its constraints
  say: a part of a pattern the contract fixes must show as fixed, so it is not read as open to redesign.
- A surface no PBI builds that does not say where it comes from, or a PBI named for one to fill the field.
- A flow step, a component, a placeholder or a `composes` entry that points at a surface that does not exist.
- A customer-facing surface that does not account for both reading directions.
- A row whose wording names an email and that no email surface carries.
- A delivery row that is neither on a surface nor marked `no_surface` with a reason, or that is marked both.
- Derived fields that have drifted: `stage`, `flow_steps`, the `affected_surfaces` of a placeholder or a decision.
- A `design/coverage.md` that is not what this tool would write.

Two PBIs can stand behind one surface, and they are kept apart. `owner_pbi` is the PBI that accepts the surface's
register rows: one row, one accepting PBI, as `docs/scope/backlog-ownership.json` has it. `build_pbi` is the PBI
that implements the visible surface. They are usually the same. Where they differ, nothing moves in the ownership
file: the surface says so, and the report lists it.

Five kinds of statement are kept apart, so that a wireframe cannot turn a choice the contract leaves open into a
permanent product decision: a contractual requirement (`register_ids`), a platform constraint (`native_baseline`,
and an option's `constraints`), a locked owner decision (`owner_design_decisions`), a provisional UX baseline and
an open design choice (both in `interaction-options.json`). A provisional baseline is not a requirement and not an
owner-confirmed final product decision.

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
          "build_pbi", "stage", "route", "flow_steps", "acceptance", "states", "responsive", "rtl", "native_baseline",
          "design_freedom", "open_items", "decisions", "interaction_options", "erp_dependency", "status")
OPTION_STATUSES = ("open", "provisional", "locked")
OPTION_STAGES = ("low_fidelity_review", "high_fidelity_review", "localisation_review")
OPTION_FIELDS = ("id", "name", "affected_surfaces", "requirement_outcome", "candidate_patterns", "working_baseline",
                 "status", "constrained_by", "decision_stage", "constraints", "selection_criteria", "selected_pattern",
                 "decision_source")
# What limits a choice. `contract_pattern`: the signed wording fixes part of the pattern itself, and that part is not
# a design alternative. `contract_condition`: a contracted requirement every candidate must meet. `platform`: what
# the platform or a technical fact limits.
CONSTRAINT_TYPES = ("contract_pattern", "contract_condition", "platform")
ORIGINS = ("mizzey", "corex", "wordpress", "woocommerce", "provider", "unknown")
STAGES = {"S1": "S1", "S2": "S2", "-": "per PRE-09"}
# Rows whose register wording names an email. Each must be carried by a surface of kind `email`.
EMAIL_ROWS = ("NOTF-01", "NOTF-02", "NOTF-03", "NOTF-04", "NOTF-06", "NOTF-08", "AUTH-04", "AUTH-05", "AUTH-12",
              "REV-05")
CUSTOMER_FACING = {"visitor", "customer"}


def load(root: Path) -> dict:
    def read(*parts: str):
        return json.loads(root.joinpath(*parts).read_text(encoding="utf-8"))

    surfaces = read("design", "inventory", "surfaces.json")
    placeholders = read("design", "inventory", "placeholders.json")
    return {
        "surfaces": surfaces["surfaces"],
        "no_surface": surfaces["no_surface"],
        "flows": read("design", "inventory", "flows.json")["flows"],
        "components": read("design", "inventory", "components.json")["components"],
        "placeholders": placeholders["placeholders"],
        "decisions": placeholders["owner_design_decisions"],
        "options": read("design", "inventory", "interaction-options.json")["options"],
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


def ruled(surfaces: list[dict]) -> dict[str, list[str]]:
    """Owner design decision -> the surfaces it governs."""
    out: dict[str, list[str]] = {}
    for s in surfaces:
        for item in s["decisions"]:
            out.setdefault(item, []).append(s["id"])
    return out


def opted(options: list[dict]) -> dict[str, list[str]]:
    """Surface -> the interaction options that bear on it."""
    out: dict[str, list[str]] = {}
    for o in options:
        for sid in o["affected_surfaces"]:
            out.setdefault(sid, []).append(o["id"])
    return out


def pbis(slices: list[dict]) -> set:
    return {s["issue"] or s["key"] for s in slices}


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
    decision_ids = {d["id"] for d in data["decisions"]}
    known_pbis = pbis(data["slices"])
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
        build = s["build_pbi"]
        if build is None:
            if s["scope_type"] == "contracted":
                errs.append(f"surface {sid}: contracted, and no PBI builds it (build_pbi)")
            if s.get("implementation_origin") not in ORIGINS:
                errs.append(f"surface {sid}: no PBI builds it, so implementation_origin must be one of "
                            f"{', '.join(ORIGINS)}")
            elif (s["scope_type"] == "provider_hosted") != (s["implementation_origin"] == "provider"):
                errs.append(f"surface {sid}: origin `provider` is for provider-hosted surfaces, and only for them")
        elif "implementation_origin" in s:
            errs.append(f"surface {sid}: implementation_origin is for a surface no PBI builds")
        elif build not in known_pbis:
            errs.append(f"surface {sid}: build_pbi {build} is not a PBI of the ownership file")
        if build is not None and build != s["owner_pbi"] and not s.get("build_note"):
            errs.append(f"surface {sid}: built by {build} and accepted by {s['owner_pbi']}; say why in build_note")
        if build == s["owner_pbi"] and s.get("build_note"):
            errs.append(f"surface {sid}: build_note is for a build PBI that differs from the accepting PBI")
        for item in s["decisions"]:
            if item not in decision_ids:
                errs.append(f"surface {sid}: {item} is not an owner design decision of placeholders.json")
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

    option_ids = [o.get("id", "?") for o in data["options"]]
    chosen = opted([o for o in data["options"] if "affected_surfaces" in o])
    for s in surfaces:
        if s.get("interaction_options") != chosen.get(s["id"], []):
            errs.append(f"surface {s['id']}: interaction_options differs from interaction-options.json")
    for oid, n in Counter(option_ids).items():
        if n > 1:
            errs.append(f"interaction option {oid}: id used {n} times")
    for o in data["options"]:
        oid = o.get("id", "?")
        missing = [f for f in OPTION_FIELDS if f not in o]
        if missing:
            errs.append(f"interaction option {oid}: missing {', '.join(missing)}")
            continue
        if oid in placeholder_ids or oid in decision_ids:
            errs.append(f"interaction option {oid}: also an open input or a locked owner decision; it is one thing only")
        if o["status"] not in OPTION_STATUSES:
            errs.append(f"interaction option {oid}: status {o['status']!r} is not one of {', '.join(OPTION_STATUSES)}")
        if o["decision_stage"] not in OPTION_STAGES:
            errs.append(f"interaction option {oid}: decision_stage is not one of {', '.join(OPTION_STAGES)}")
        if len(o["candidate_patterns"]) < 2:
            errs.append(f"interaction option {oid}: a choice needs at least two candidate patterns")
        for field in ("requirement_outcome", "selection_criteria"):
            if not o[field]:
                errs.append(f"interaction option {oid}: {field} is empty")
        typed = [c for c in o["constraints"] if isinstance(c, dict) and c.get("type") in CONSTRAINT_TYPES and c.get("text")]
        if len(typed) != len(o["constraints"]):
            errs.append(f"interaction option {oid}: every constraint needs a text and a type from "
                        f"{', '.join(CONSTRAINT_TYPES)}")
        limits = [label for label, t in (("contract", "contract_pattern"), ("platform", "platform"))
                  if any(c["type"] == t for c in typed)]
        if o["constrained_by"] != limits:
            errs.append(f"interaction option {oid}: constrained_by says {o['constrained_by']} and its constraints say "
                        f"{limits}; a pattern the contract fixes must show as fixed")
        for sid in o["affected_surfaces"]:
            if sid not in known:
                errs.append(f"interaction option {oid}: affects {sid}, which is not a surface")
        if not o["affected_surfaces"] and not o.get("applies_to"):
            errs.append(f"interaction option {oid}: names no surface and does not say what it applies to")
        for field in ("working_baseline", "selected_pattern"):
            if o[field] is not None and o[field] not in o["candidate_patterns"]:
                errs.append(f"interaction option {oid}: {field} is not one of its candidate patterns")
        if o["status"] == "open" and o["working_baseline"] is not None:
            errs.append(f"interaction option {oid}: open, and already has a working baseline; that is provisional")
        if o["status"] == "provisional" and o["working_baseline"] is None:
            errs.append(f"interaction option {oid}: provisional, and names no working baseline")
        if o["status"] != "locked" and o["selected_pattern"] is not None:
            errs.append(f"interaction option {oid}: {o['status']}, and already has a selected pattern; a baseline is "
                        "not a selection")
        if o["status"] == "locked" and (o["selected_pattern"] is None or not o["decision_source"]):
            errs.append(f"interaction option {oid}: locked, and does not say which pattern was selected and by whom")
        if o["status"] == "provisional" and not o["decision_source"]:
            errs.append(f"interaction option {oid}: provisional, and does not say who set the baseline")

    governed = ruled(surfaces)
    for d in data["decisions"]:
        if d.get("status") != "locked":
            errs.append(f"decision {d['id']}: an owner design decision is locked; an open or provisional pattern "
                        "belongs in interaction-options.json")
        if d["id"] in placeholder_ids:
            errs.append(f"decision {d['id']}: also listed as an open placeholder; a decided question is not open")
        if d["affected_surfaces"] != governed.get(d["id"], []):
            errs.append(f"decision {d['id']}: affected_surfaces differs from the surfaces that cite it")
        if d.get("client_confirmed") is not False or d.get("adds_scope") is not False:
            errs.append(f"decision {d['id']}: an owner design decision is not a client confirmation and adds no scope")
        for field in ("question", "decision", "must_not", "decided_by", "date", "record"):
            if not d.get(field):
                errs.append(f"decision {d['id']}: {field} is empty")

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


def first_release(s: dict) -> bool:
    return s["stage"] != "S2"


def needs_design(s: dict) -> bool:
    """A surface a customer sees and that this engagement lays out: not a provider's, and not left as the platform has it."""
    return bool(CUSTOMER_FACING & set(s["audience"])) and s["design_freedom"] in ("free", "constrained")


def dependency_findings(data: dict) -> dict[str, list]:
    """Where a PBI's `design_dependency` and the surfaces it builds disagree. Reported, never changed here.

    Read on `build_pbi`, the PBI that implements the visible surface, not on the PBI that accepts its rows.
    """
    built: dict[object, list[dict]] = {}
    for s in data["surfaces"]:
        if s["build_pbi"] is not None:
            built.setdefault(s["build_pbi"], []).append(s)
    customer, second, staff_only, none_built = [], [], [], []
    for sl in data["slices"]:
        key = sl["issue"] or sl["key"]
        mine = built.get(key, [])
        facing = [s for s in mine if needs_design(s)]
        now = [s for s in facing if first_release(s)]
        marked = sl["design_dependency"]
        if now:
            customer.append((key, sl["title"], marked, now))
        elif facing:
            second.append((key, sl["title"], marked, facing))
        elif marked == "none":
            # Additions to the standard administration: contracted surfaces only, so a platform screen with no
            # register row does not make a PBI look like a design question.
            additions = [s for s in mine if s["scope_type"] == "contracted"]
            if additions:
                staff_only.append((key, sl["title"], additions))
        else:
            none_built.append((key, sl["title"], mine))
    differs = [s for s in data["surfaces"] if s["build_pbi"] is not None and s["build_pbi"] != s["owner_pbi"]]
    unbuilt = [s for s in data["surfaces"] if s["build_pbi"] is None]
    return {"customer": customer, "second": second, "staff_only": staff_only, "none_built": none_built,
            "differs": differs, "unbuilt": unbuilt,
            "missing": [c for c in customer if c[2] == "none"]}


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
                                      ["Open design inputs", len(data["placeholders"])],
                                      ["Locked owner decisions", len(data["decisions"])],
                                      ["Provisional UX choices",
                                       sum(1 for o in data["options"] if o["status"] == "provisional")],
                                      ["Open interaction choices",
                                       sum(1 for o in data["options"] if o["status"] == "open")],
                                      ["Locked interaction choices",
                                       sum(1 for o in data["options"] if o["status"] == "locked")]])
    L += ["", "**Surfaces by design freedom.** How a contracted capability may be expressed. It never widens what "
              "is built.", ""]
    meaning = {"free": "Composition, hierarchy, layout and interaction patterns may be explored",
               "constrained": "A platform block, an integration or a contractual condition limits the solution",
               "annotate_only": "Mainly a provider's or the standard administration's: documented, not redesigned",
               "none": "No design choice to make"}
    freedom = Counter(s["design_freedom"] for s in surfaces)
    L += table(["Design freedom", "Surfaces", "Meaning"], [[f"`{k}`", freedom.get(k, 0), meaning[k]] for k in FREEDOM])
    L += ["", "**Surfaces by kind**", ""]
    L += table(["Kind", "Surfaces"], counted(Counter(s["kind"] for s in surfaces), KINDS))
    L += ["", "**Surfaces by scope type**", ""]
    L += table(["Scope type", "Surfaces"], counted(Counter(s["scope_type"] for s in surfaces), SCOPE_TYPES))
    L += ["", "**Surfaces by audience.** A surface seen by two audiences counts under each.", ""]
    L += table(["Audience", "Surfaces"], counted(Counter(a for s in surfaces for a in s["audience"]), AUDIENCES))
    L += ["", "**Surfaces by stage**", ""]
    L += table(["Stage", "Surfaces"], counted(Counter(s["stage"] or "no register row" for s in surfaces)))
    L += ["", "**Surfaces by PBI.** `Accepts` counts the surfaces whose register rows the PBI accepts (`owner_pbi`). "
              "`Builds` counts the surfaces it implements (`build_pbi`). `none` is a surface with no register row, which "
              "no PBI accepts, or one nothing here builds.", ""]
    titles = {(sl["issue"] or sl["key"]): sl["title"] for sl in data["slices"]}
    accepts = Counter(s["owner_pbi"] for s in surfaces)
    builds = Counter(s["build_pbi"] for s in surfaces)
    order = sorted(set(accepts) | set(builds), key=lambda k: (k is None, isinstance(k, str), str(k).zfill(6)))
    L += table(["PBI", "Accepts", "Builds", "Title"],
               [[pbi_label(k), accepts.get(k, 0), builds.get(k, 0), titles.get(k, "")] for k in order])

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
        ["Every open item on a surface has a placeholder rule", f"{len(data['placeholders'])} rules"],
        ["Every contracted surface names the PBI that builds it, and says why where it is not the accepting PBI",
         f"{len(contracted)} surfaces"],
        ["No owner design decision is recorded as a client confirmation or as added scope",
         f"{len(data['decisions'])} decisions"],
        ["No interaction option claims more than its status allows: a baseline is not a selection",
         f"{len(data['options'])} options"],
        ["Every constraint of an interaction option is typed, and a pattern the contract fixes shows as fixed",
         f"{sum(len(o['constraints']) for o in data['options'])} constraints"],
        ["No PBI is named for a surface that nothing here builds; each says where it comes from",
         f"{sum(1 for s in surfaces if s['build_pbi'] is None)} surfaces"]])

    f = dependency_findings(data)
    names = lambda ss: ", ".join(f"`{x['id']}`" for x in ss)  # noqa: E731
    L += ["", "## 4. Accepting PBI and build PBI", "",
          "`owner_pbi` is the PBI that accepts a surface's register rows. `build_pbi` is the PBI that implements the "
          "visible surface. **No row changes owner here**: `docs/scope/backlog-ownership.json` is untouched.", "",
          "**4.1 Surfaces built by a PBI other than the one that accepts their rows**", ""]
    L += table(["Surface", "Accepts", "Builds", "Why"],
               [[f"`{x['id']}`", pbi_label(x["owner_pbi"]), pbi_label(x["build_pbi"]), x["build_note"]]
                for x in f["differs"]]) if f["differs"] else ["None."]
    L += ["", "**4.2 Surfaces no PBI builds.** A provider's interface, or a platform screen. **No PBI is invented to "
              "fill the field**: `build_pbi` stays empty until there is a real implementation owner, and "
              "`implementation_origin` says where the surface comes from today.", ""]
    L += table(["Surface", "Scope type", "Origin", "Why it exists"],
               [[f"`{x['id']}`", x["scope_type"], f"`{x['implementation_origin']}`", x["scope_note"]]
                for x in f["unbuilt"]]) if f["unbuilt"] else ["None."]

    L += ["", "## 5. Design dependency", "",
          "`design_dependency` in `docs/scope/backlog-ownership.json`, compared with the surfaces each PBI **builds**. "
          "A surface counts when a customer sees it and this engagement lays it out: `design_freedom` is `free` or "
          "`constrained`. **Nothing is changed in the ownership file.** These are findings for the owner.", "",
          "**5.1 PBIs that build a customer-facing surface in the first release.** Each needs the design artefact "
          "before that surface is implemented. `Field` is what the ownership file says today.", ""]
    L += table(["PBI", "Title", "Field", "Finding", "Customer-facing surfaces it builds"],
               [[pbi_label(k), t, f"`{m}`", "**Field does not reflect these surfaces**" if m == "none" else "Consistent",
                 names(ss)] for k, t, m, ss in f["customer"]]) if f["customer"] else ["None."]
    L += ["", "**5.2 Second-release slices that build a customer-facing surface.** Not in the initial S1 wireframe pass. "
              "Each gets its own low-fidelity pass before PRE-03b is approved and is part of the final interface-design "
              "package (DQ-04). They are built in the second release.", ""]
    L += table(["Slice", "Title", "Field", "Surfaces"],
               [[pbi_label(k), t, f"`{m}`", names(ss)] for k, t, m, ss in f["second"]]) if f["second"] else ["None."]
    L += ["", "**5.3 Marked `none`, and builds additions to the standard administration only.** Kept as `none` by "
              "owner ruling (D-14): the additions stay inventoried and annotated, and are not made dependent on "
              "PRE-03b.", ""]
    L += table(["PBI", "Title", "Surfaces it builds"],
               [[pbi_label(k), t, names(ss)] for k, t, ss in f["staff_only"]]) if f["staff_only"] else ["None."]
    L += ["", "**5.4 Marked `needs design`, and builds no customer-facing surface of this inventory.** The field may "
              "be right for another reason, such as rows that shape a surface another PBI builds. Listed so it is "
              "deliberate.", ""]
    L += table(["PBI", "Title", "Surfaces it builds"],
               [[pbi_label(k), t, names(ss) or "none"] for k, t, ss in f["none_built"]]) if f["none_built"] else ["None."]

    L += ["", "## 6. Open design inputs", "",
          "Each has a rule in `design/inventory/placeholders.json`: what may stand in now, and what may not be "
          "assumed.", ""]
    L += table(["Id", "Kind", "Input", "Surfaces affected"],
               [[p["id"], p["kind"], p["title"], len(p["affected_surfaces"])] for p in data["placeholders"]])

    L += ["", "## 7. Locked owner decisions", "",
          "Boundaries the owner decided in D-14 for questions no source answered. **Owner design decisions: not client "
          "confirmations, and not scope additions.** No surface waits on one.", ""]
    L += table(["Id", "Question", "Decision", "Not to be done", "Surfaces"],
               [[d["id"], d["title"], d["decision"] + (" **Boundary:** " + d["boundary"] if d.get("boundary") else ""),
                 d["must_not"], names([{"id": x} for x in d["affected_surfaces"]])] for d in data["decisions"]])

    L += ["", "## 8. Interaction options", "",
          "UX choices with more than one legitimate pattern, where neither the contract nor the platform fixes one "
          "(`design/inventory/interaction-options.json`). **A provisional baseline is not a requirement and not an "
          "owner-confirmed final product decision.** It is where the first wireframes start, and it may change in "
          "review without a scope change, provided the pattern chosen still meets the requirement and the constraints. "
          "None of these adds a capability.", ""]
    stage = {"low_fidelity_review": "Low-fidelity review", "high_fidelity_review": "High-fidelity review",
             "localisation_review": "Design and localisation review"}
    L += table(["Id", "Choice", "Status", "Constrained by", "Working baseline", "Candidate patterns", "Selected at",
                "Surfaces"],
               [[o["id"], o["name"], f"`{o['status']}`", ", ".join(o["constrained_by"]) or "nothing: genuinely open",
                 o["working_baseline"] or "None yet", "; ".join(o["candidate_patterns"]), stage[o["decision_stage"]],
                 o.get("applies_to") or names([{"id": x} for x in o["affected_surfaces"]])] for o in data["options"]])
    fixed = [(o["id"], c["text"]) for o in data["options"] for c in o["constraints"] if c["type"] == "contract_pattern"]
    L += ["", "**What the contract fixes inside these choices.** Each line is part of a pattern the signed wording "
              "settles. **It is not a design alternative**, and the candidates above all keep it. Changing one needs a "
              "requirement decision, not a design review.", ""]
    L += table(["Choice", "Fixed by the contract"], [[i, t] for i, t in fixed]) if fixed else ["None."]

    L += ["", "## 9. Every surface", ""]
    L += table(["Id", "Surface", "Kind", "Scope type", "Accepts", "Builds", "Stage", "Rows"],
               [[f"`{s['id']}`", s["name"], s["kind"], s["scope_type"], pbi_label(s["owner_pbi"]),
                 pbi_label(s["build_pbi"]), s["stage"] or "", len(s["register_ids"])] for s in surfaces])
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
          f"{len(data['components'])} components, {len(data['placeholders'])} open inputs, "
          f"{len(data['decisions'])} locked decisions, {len(data['options'])} interaction options, {len(errs)} problems")
    return 1 if errs else 0


if __name__ == "__main__":
    sys.exit(main())

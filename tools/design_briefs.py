"""Generate one design brief per surface from the design inventory.

A brief is what a designer reads before drawing one surface. It is **generated, never written**: every line comes
from `design/inventory/` (surfaces, flows, components, placeholders, interaction options), `design/direction.md`,
`design/wireframes/manifest.json`, and the register mirror and PBI ownership in `docs/scope/`. Nothing about a
requirement is typed into this file, so a brief cannot say more than the inventory does, and it cannot drift from
it: `tools/tests/test_design_briefs.py` fails when a committed brief is not what this tool would write.

**A committed brief carries ids and structure, not the wording of the signed register.** The repository is public
and the signed documents stay outside it (`design/sources.json`). A designer reads the wording of each row in the
signed register. On the developer's machine the wording can be merged into working copies with `--register`, and
those are written outside the repository only: this tool refuses to write them inside it.

Which briefs are committed is decided by the manifest, not here: an entry whose `brief` is set has one.

Usage: python tools/design_briefs.py                         check that the committed briefs are current
       python tools/design_briefs.py --write                 write the brief of every manifest entry that has one
       python tools/design_briefs.py --surface sf-cart       print one brief, whether or not it is committed
       python tools/design_briefs.py --register <register.md> --out <folder outside the repository>
                                                             working copies with the row wording merged in
"""

from __future__ import annotations

import argparse
import io
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent

# How a state is grouped in a brief. The first rule that matches the state's name wins, so the order matters.
STATE_GROUPS = (
    ("Default", r"^default$"),
    ("Stock", r"stock|unavailable|cannot_validate|quantity_reduced|price_changed|combination"),
    ("Authentication", r"signed_in|signed_out|guest|account_holder|suspended|rate_limited|credentials|sign_in_required|"
                       r"not_permitted|permission_denied"),
    ("Loading", r"loading|adding|updating|placing|redirecting|verifying|typing|pending$"),
    ("Empty", r"^empty|^no_|^none_|^zero$|_empty$|empty_"),
    ("Validation", r"validation|unticked|required|field_error|summary_error|exceeds|wrong_"),
    ("Error", r"error|failed|invalid|expired|used_link|not_found|timeout|refused|unknown|declined"),
    ("Success", r"success|saved|added|sent|submitted|applied|verified|accepted|moved_to|removed|qualified|exported"),
    ("Content length", r"long_|^many|^few$|many_"),
    ("Interaction", r"hover|focus|^open$|^closed$|expanded|collapsed|zoom|selected|nested"),
)
FREEDOM = {
    "free": "The designer may explore composition, hierarchy, visual treatment, layout and interaction patterns, as "
            "long as the signed capability and its states remain satisfied.",
    "constrained": "A platform, a native block, an integration or a contractual condition limits the solution. Design "
                   "decisions still exist within that boundary.",
    "annotate_only": "The interface belongs mainly to a provider or to the standard administration. The handoff, the "
                     "state or the extension is documented. The external or native product is not redesigned.",
    "none": "There is no design choice to make.",
}
CONSTRAINT = {"contract_pattern": "Fixed by the contract. Not a design alternative",
              "contract_condition": "Contracted condition every pattern must meet",
              "platform": "Platform limit"}
STAGE = {"low_fidelity_review": "the owner's review of the low-fidelity wireframes",
         "high_fidelity_review": "the owner's review of the high-fidelity design",
         "localisation_review": "the design and localisation review of the Arabic experience"}
ID_CELL = re.compile(r"^\*{0,2}([A-Z]{2,5}-\d{1,3}[a-z]?)\*{0,2}(?![0-9A-Za-z-])")


def load(root: Path) -> dict:
    def read(*parts: str):
        return json.loads(root.joinpath(*parts).read_text(encoding="utf-8"))

    surfaces = read("design", "inventory", "surfaces.json")
    placeholders = read("design", "inventory", "placeholders.json")
    return {
        "surfaces": surfaces["surfaces"],
        "flows": read("design", "inventory", "flows.json")["flows"],
        "components": read("design", "inventory", "components.json")["components"],
        "placeholders": placeholders["placeholders"],
        "decisions": placeholders["owner_design_decisions"],
        "options": read("design", "inventory", "interaction-options.json")["options"],
        "manifest": read("design", "wireframes", "manifest.json"),
        "ids": read("docs", "scope", "register-ids.json")["ids"],
        "slices": read("docs", "scope", "backlog-ownership.json")["slices"],
        "direction": root.joinpath("design", "direction.md").read_text(encoding="utf-8"),
    }


def direction_parts(text: str) -> dict:
    """The parts of design/direction.md a brief quotes, read by heading so the brief follows the document."""
    sections: dict[str, list[str]] = {}
    current = None
    for line in text.splitlines():
        if line.startswith("## "):
            current = line[3:].strip()
            sections[current] = []
        elif current is not None:
            sections[current].append(line)

    def rows(title: str) -> list[list[str]]:
        out = []
        for line in sections[title]:
            if line.startswith("|") and not set(line) <= set("|-: "):
                out.append([c.strip() for c in line.strip().strip("|").split("|")])
        return out[1:]

    def bullets(title: str) -> list[str]:
        out: list[str] = []
        for line in sections[title]:
            if line.startswith("- "):
                out.append(line[2:].strip())
            elif line.startswith("  ") and line.strip() and out:
                out[-1] += " " + line.strip()
        return out

    def sentence(text: str) -> str:
        return text if text.endswith((".", ":", "?", "!")) else text + "."

    target = next(line for line in sections["The target"] if line.strip())
    stage = {r[0]: r[1] for r in rows("How this applies at each stage")}
    return {
        "target": target,
        "is": [r[0] for r in rows("The target")],
        "is_not": [r[0] for r in rows("What it is not")],
        "motion": bullets("Motion and feedback"),
        "low_fidelity": sentence(stage["Low-fidelity wireframes"]),
    }


def pbi(key, titles: dict) -> str:
    if key is None:
        return "none"
    label = f"#{key}" if isinstance(key, int) else str(key)
    return f"{label}, {titles[key]}" if key in titles else label


def group_of(state: str) -> str:
    for name, pattern in STATE_GROUPS:
        if re.search(pattern, state):
            return name
    return "Other"


def table(header: list[str], rows: list[list[object]]) -> list[str]:
    out = ["| " + " | ".join(header) + " |", "|" + "|".join("---" for _ in header) + "|"]
    return out + ["| " + " | ".join(str(c).replace("|", "/") for c in r) + " |" for r in rows]


def register_wording(path: Path) -> dict[str, str]:
    """Row id -> its wording, read from the signed register on the developer's machine. Never committed."""
    out: dict[str, str] = {}
    for line in path.read_text(encoding="utf-8").splitlines():
        if not line.startswith("|"):
            continue
        cells = [c.strip() for c in line.strip().strip("|").split("|")]
        m = ID_CELL.match(cells[0]) if cells else None
        if m and m.group(1) not in out and len(cells) > 1:
            out[m.group(1)] = re.sub(r"\s+", " ", re.sub(r"\*\*|\[[a-z]+\]", "", cells[1])).strip()
    return out


def brief(surface_id: str, data: dict, wording: dict[str, str] | None = None) -> str:
    surfaces = {s["id"]: s for s in data["surfaces"]}
    s = surfaces[surface_id]
    ids = data["ids"]
    titles = {(sl["issue"] or sl["key"]): sl["title"] for sl in data["slices"]}
    owner = {rid: (sl["issue"] or sl["key"]) for sl in data["slices"] for rid in sl["ids"]}
    entry = next(e for e in data["manifest"]["entries"] if e["surface"] == surface_id)
    batches = {b["id"]: b for b in data["manifest"]["batches"]}
    parts = direction_parts(data["direction"])
    words = wording or {}

    def row(rid: str) -> list[object]:
        r = [f"`{rid}`", ids[rid]["scope"], "per PRE-09" if ids[rid]["stage"] == "-" else ids[rid]["stage"],
             pbi(owner.get(rid), {})]
        return r + [words.get(rid, "")] if wording is not None else r

    row_header = ["Row", "Scope", "Stage", "Accepting PBI"] + (["Wording in the signed register"] if wording is not None else [])
    L: list[str] = [f"# Brief: {s['name']} (`{s['id']}`)", ""]
    if wording is None:
        L += ["Generated by `tools/design_briefs.py` from `design/inventory/`, `design/direction.md` and "
              "`design/wireframes/manifest.json`. Do not edit it: change the inventory and regenerate.", "",
              "**This brief carries ids and structure, not the wording of the signed register.** Read each row listed "
              "under Scope in the signed register before drawing (`design/sources.json` says where it is).", ""]
    else:
        L += ["**Working copy with the wording of the signed register. Confidential: not for the repository.** "
              "Generated by `tools/design_briefs.py --register` on the developer's machine.", ""]

    # ---- surface
    L += ["## Surface", ""]
    facts = [["Id", f"`{s['id']}`"], ["Name", s["name"]], ["Kind", f"`{s['kind']}`"],
             ["Scope type", f"`{s['scope_type']}`"], ["Stage", s["stage"] or "No register row"],
             ["Accepting PBI (`owner_pbi`)", pbi(s["owner_pbi"], titles)],
             ["Build PBI (`build_pbi`)", pbi(s["build_pbi"], titles) if s["build_pbi"] is not None else
              f"none. Origin: `{s['implementation_origin']}`"],
             ["Audience", ", ".join(s["audience"])]]
    if s["route"]:
        facts += [["Address, English", f"`{s['route']['en']}`"], ["Address, Arabic", f"`{s['route']['ar']}`"]]
    if s.get("route_note"):
        facts.append(["Address note", s["route_note"]])
    L += table(["", ""], facts)
    if s.get("build_note"):
        L += ["", f"**Accepting and building differ.** {s['build_note']}"]

    # ---- purpose
    L += ["", "## Purpose", ""]
    L += [s["purpose"]] if s.get("purpose") else ["The inventory records no purpose for this surface yet. One is written "
                                                  "before it is briefed for a wireframe."]
    served = [(f, st) for f in data["flows"] for st in f["steps"] if surface_id in st["surfaces"]]
    flows = list(dict.fromkeys((f["id"], f["name"]) for f, _ in served))
    if flows:
        L += ["", "Flows it serves: " + "; ".join(f"{name} (`{fid}`)" for fid, name in flows) + "."]
    if s.get("scope_note"):
        L += ["", f"**Why it exists, with no register row.** {s['scope_note']}"]

    # ---- scope
    L += ["", "## Scope", ""]
    if s["register_ids"]:
        L += ["**Rows this surface is drawn for.** Each is accepted by this surface's accepting PBI.", ""]
        L += table(row_header, [row(r) for r in s["register_ids"]])
    else:
        L += ["**This surface is drawn for no register row.** It is not contracted scope and is never presented as "
              "contracted."]
    if s["context_ids"]:
        L += ["", "**Rows that shape it, accepted elsewhere.** They stay with their own PBI.", ""]
        L += table(row_header, [row(r) for r in s["context_ids"]])
    if s["excluded_ids"]:
        L += ["", "**Not to be drawn here.** These neighbouring rows are deferred, later-phase or out of scope.", ""]
        L += table(["Row", "Scope"] + (["Wording in the signed register"] if wording is not None else []),
                   [[f"`{r}`", ids[r]["scope"]] + ([words.get(r, "")] if wording is not None else [])
                    for r in s["excluded_ids"]])
    L += ["", "**What must not be invented.**", "",
          "- No capability, field or control beyond what the rows above contract. A new customer capability or "
          "business behaviour needs scope authority. How a contracted capability is presented is design.",
          "- Nothing from Option C material, and nothing because another store has it."]
    if s["open_items"]:
        L.append("- No value for an open input. Each has a placeholder rule under Open inputs.")
    if s["erp_dependency"] != "none":
        L.append("- No ERP timing, mechanism, reservation or stock wording. Structural states only, until PRE-09.")
    a = s["acceptance"]
    if a["stories"] or a["scenarios"] or a["operations_checks"]:
        L += ["", "**Acceptance references.**", ""]
        if a["stories"]:
            L.append("- Functional Specification stories that trace these rows: " + ", ".join(a["stories"]) + ".")
        if a["scenarios"]:
            L.append("- Acceptance scenarios exercised here: " + ", ".join(a["scenarios"]) + ".")
        if a["operations_checks"]:
            L.append("- Store operations checks: " + ", ".join(a["operations_checks"]) + ".")

    # ---- structure
    L += ["", "## Structure", "",
          "**Required information and actions** are those of the rows under Scope, in the wording of the signed "
          "register. The steps below are where a flow passes through this surface."]
    if served:
        L += [""] + table(["Step", "What happens", "Rows exercised"],
                          [[f"`{st['step']}`", st["what"], ", ".join(st["register_ids"])] for _, st in served])
    else:
        L += ["", "No flow step passes through this surface."]
    if s.get("composes"):
        L += ["", "**Contains:** " + ", ".join(f"{surfaces[c]['name']} (`{c}`)" for c in s["composes"]) + "."]
    inside = [o for o in data["surfaces"] if surface_id in o.get("composes", [])]
    if inside:
        L += ["", "**Appears inside:** " + ", ".join(f"{o['name']} (`{o['id']}`)" for o in inside) + "."]
    used = [c for c in data["components"] if surface_id in c["used_by"]]
    if used:
        L += ["", "**Components.** Structure and states only: no brand value.", ""]
        L += table(["Component", "Layer", "States", "Note"],
                   [[f"{c['name']} (`{c['id']}`)", c["layer"], ", ".join(c["states"]),
                     " ".join(x for x in (c.get("note"), c.get("rtl")) if x)] for c in used])
        for c in used:
            lib = c.get("section_library")
            if lib:
                L += ["", f"**{c['name']} is a section of the curated library.** Editable: "
                          f"{', '.join(lib['editable'])}. Locked: {', '.join(lib['locked'])}."
                          + (f" Variants: {', '.join(lib['allowed_variants'])}. {lib['variants_status']}"
                             if lib["allowed_variants"] else f" {lib['variants_status']}")]

    # ---- states
    L += ["", "## States", "", "Every state below is drawn. A state that is not drawn is a state the build would invent.",
          ""]
    L += table(["State", "Group", "Required by", "Note"],
               [[f"`{st['state']}`", group_of(st["state"]), f"`{st['requires']}`" if st.get("requires") else "",
                 st.get("note", "")] for st in s["states"]])

    # ---- responsive, localisation
    L += ["", "## Responsive", ""]
    L += table(["Viewport", "Behaviour"], [[v.capitalize(), s["responsive"][v]] for v in ("mobile", "tablet", "desktop")])
    L += ["", "## Localisation", ""]
    rtl = s["rtl"]
    if rtl.get("applies"):
        L += ["English left to right is the canonical design. Arabic right to left is the same design mirrored, never a "
              "second layout. Both are drawn.", ""]
        L += table(["", ""], [["Mirrors", "; ".join(rtl["mirrors"])], ["Does not mirror", "; ".join(rtl["does_not_mirror"])]]
                   + ([["Numerals", rtl["numerals"]]] if rtl.get("numerals") else [])
                   + ([["Note", rtl["notes"]]] if rtl.get("notes") else []))
    else:
        L += [f"Arabic right to left does not apply. {rtl.get('reason', '')}".strip()]

    # ---- design freedom, native baseline
    L += ["", "## Design freedom", "", f"`{s['design_freedom']}`: {FREEDOM[s['design_freedom']]}", "",
          "It governs how the contracted capability is expressed, never what functionality exists.",
          "", "## Native baseline", "", s["native_baseline"] or "None recorded."]
    if s["erp_dependency"] != "none":
        L += ["", f"**ERP dependency: `{s['erp_dependency']}`.**"]

    # ---- open inputs, decisions, interaction options
    L += ["", "## Open inputs", ""]
    holders = {p["id"]: p for p in data["placeholders"]}
    if s["open_items"]:
        L += table(["Input", "Kind", "What is unknown", "Placeholder allowed now", "Must not be assumed"],
                   [[f"`{i}` {holders[i]['title']}", holders[i]["kind"], holders[i]["unknown"],
                     holders[i]["placeholder_allowed"], holders[i]["must_not_assume"]] for i in s["open_items"]])
    else:
        L += ["None."]
    decisions = {d["id"]: d for d in data["decisions"]}
    if s["decisions"]:
        L += ["", "**Locked owner decisions that govern this surface.** Not client confirmations, not scope additions.", ""]
        L += table(["Decision", "What was decided", "Not to be done"],
                   [[f"`{i}` {decisions[i]['title']}", decisions[i]["decision"], decisions[i]["must_not"]]
                    for i in s["decisions"]])
    L += ["", "## Interaction options", ""]
    options = {o["id"]: o for o in data["options"]}
    everywhere = [o for o in data["options"] if o.get("applies_to") and not o["affected_surfaces"]
                  and rtl.get("applies")]
    mine = [options[i] for i in s["interaction_options"]] + everywhere
    if not mine:
        L += ["None recorded for this surface."]
    for o in mine:
        L += [f"### {o['id']}: {o['name']}", "",
              f"Status `{o['status']}`. " + {
                  "open": "No pattern is preferred yet: propose, recommend one and show the alternatives.",
                  "provisional": "A working baseline, not a requirement and not a final owner decision. Start from it, "
                                 "and say if another candidate is better.",
                  "locked": "Selected. Draw the selected pattern."}[o["status"]], "",
              f"**Outcome required.** {o['requirement_outcome']}", ""]
        L += table(["", ""], [["Working baseline", o["working_baseline"] or "None"],
                              ["Selected pattern", o["selected_pattern"] or "None"],
                              ["Decided at", STAGE[o["decision_stage"]]]])
        L += ["", "Candidate patterns:", ""] + [f"- {c}" for c in o["candidate_patterns"]]
        if o["constraints"]:
            L += [""] + table(["Constraint", "What it is"], [[c["text"], CONSTRAINT[c["type"]]] for c in o["constraints"]])
        L += ["", "Weighed in the review: " + "; ".join(o["selection_criteria"]) + ".", ""]
    if mine:
        L.pop()

    # ---- direction
    L += ["", "## Design direction", ""]
    if s["audience"] == ["staff"]:
        L += ["The experience target in `design/direction.md` is written for the storefront. This is an addition to the "
              "standard administration: it follows the standard screens and is not restyled."]
    else:
        L += [parts["target"], "", "It is: " + "; ".join(x[0].lower() + x[1:] for x in parts["is"]) + ".", "",
              "It is not: " + "; ".join(x[0].lower() + x[1:] for x in parts["is_not"]) + ".", "",
              f"**At low fidelity.** {parts['low_fidelity']}"]
        if s["kind"] != "email":
            L += ["", "**Motion is annotated, not built.** Where motion matters, a note states the trigger, the intended "
                      "effect, whether it is essential or decorative, and the behaviour under reduced motion. What the "
                      "direction asks of motion:", ""] + [f"- {m}" for m in parts["motion"]]
    L += ["", "The direction is not the brand identity. Wireframes are neutral: greys, a system typeface, no logo, no "
              "final imagery."]

    # ---- review status
    L += ["", "## Review status", ""]
    batch = batches.get(entry["batch"])
    frames = [f for f in data["manifest"]["calibration"]["frames"] if surface_id in f["surfaces"]]
    L += table(["", ""], [
        ["Review status", f"`{entry['review_status']}`"],
        ["Review batch", f"{batch['name']} (`{batch['id']}`)" if batch else "None"],
        ["English, left to right", f"`{entry['en_ltr']}`"], ["Arabic, right to left", f"`{entry['ar_rtl']}`"],
        ["Mobile", f"`{entry['mobile']}`"], ["Desktop", f"`{entry['desktop']}`"],
        ["States covered", f"{len(entry['states_covered'])} of {len(s['states'])}"],
        ["Interaction decisions pending", ", ".join(entry["interaction_pending"]) or "None"],
        ["Calibration frames", ", ".join(f"{f['name']} (`{f['id']}`)" for f in frames) or "None"]])
    L += ["", "Statuses run: `briefed`, `exploration`, `candidate_selected`, `wireframed`, `reviewed`."]
    if s.get("note"):
        L += ["", "## Note", "", s["note"]]
    return "\n".join(L) + "\n"


def committed(data: dict) -> dict[str, str]:
    """Path -> text of every brief the manifest says is committed."""
    return {e["brief"]: brief(e["surface"], data) for e in data["manifest"]["entries"] if e.get("brief")}


def check(root: Path, data: dict) -> list[str]:
    errs = []
    want = committed(data)
    for path, text in want.items():
        target = root / path
        if not target.is_file():
            errs.append(f"{path}: missing; run python tools/design_briefs.py --write")
        elif target.read_text(encoding="utf-8") != text:
            errs.append(f"{path}: stale; run python tools/design_briefs.py --write")
    folder = root / "design" / "briefs"
    for existing in sorted(folder.glob("*.md")):
        rel = existing.relative_to(root).as_posix()
        if existing.name != "README.md" and rel not in want:
            errs.append(f"{rel}: a brief the manifest does not list")
    return errs


def main(argv: list[str] | None = None) -> int:
    ap = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    ap.add_argument("--write", action="store_true", help="write every brief the manifest lists")
    ap.add_argument("--surface", help="print the brief of one surface")
    ap.add_argument("--register", type=Path, help="the signed register, to merge its wording into working copies")
    ap.add_argument("--out", type=Path, help="where working copies go; must be outside the repository")
    a = ap.parse_args(argv)
    data = load(ROOT)
    if a.surface:
        sys.stdout.write(brief(a.surface, data))
        return 0
    if a.register:
        if a.out is None:
            print("--register needs --out, a folder outside the repository")
            return 1
        out = a.out.resolve()
        if out == ROOT or ROOT in out.parents:
            print("refused: briefs with the wording of the signed register are never written inside the repository")
            return 1
        wording = register_wording(a.register)
        out.mkdir(parents=True, exist_ok=True)
        entries = [e for e in data["manifest"]["entries"] if e.get("brief")]
        for e in entries:
            with io.open(out / f"{e['surface']}.md", "w", encoding="utf-8", newline="\n") as fh:
                fh.write(brief(e["surface"], data, wording))
        print(f"design-briefs: {len(entries)} working copies with register wording written to {out}")
        return 0
    if a.write:
        want = committed(data)
        for path, text in want.items():
            with io.open(ROOT / path, "w", encoding="utf-8", newline="\n") as fh:
                fh.write(text)
        stale = [p for p in (ROOT / "design" / "briefs").glob("*.md")
                 if p.name != "README.md" and p.relative_to(ROOT).as_posix() not in want]
        for p in stale:
            print(f"{p.relative_to(ROOT).as_posix()}: not listed by the manifest; remove it or list it")
        print(f"design-briefs: {len(want)} briefs written")
        return 1 if stale else 0
    errs = check(ROOT, data)
    for e in errs:
        print(e)
    print(f"design-briefs: {len(committed(data))} briefs, {len(errs)} problems")
    return 1 if errs else 0


if __name__ == "__main__":
    sys.exit(main())

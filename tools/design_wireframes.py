"""Check the wireframe production manifest, and that a wireframe stays a design prototype.

`design/wireframes/` holds low-fidelity wireframes as standalone static HTML and CSS. They are design artefacts,
reviewed in a browser. They are not production frontend code, not WordPress templates, not theme code, not the
contracted working HTML and CSS of PRE-03b, and not reusable application components. This tool is what keeps
them that way, because the path policy can only judge a file's name.

Two things are checked.

1. **The manifest**, `design/wireframes/manifest.json`. It tracks design production and nothing else: which
   surface is in which review batch, which artefacts exist, in which language and viewport, which states they
   cover, and how far the review has gone. It never restates a requirement. It is held to
   `design/inventory/surfaces.json`: one entry per surface, the pending interaction choices derived from the
   inventory, a brief wherever a surface is past `not_started`, and no file the manifest does not know.

2. **Every HTML and CSS file under `design/wireframes/`.** What fails:

   - script in any form: a `script` element, an inline event handler, a `javascript:` address;
   - PHP or any other server tag;
   - anything fetched from outside: an absolute address, a protocol-relative address, an `@import`, a `url()`,
     a `data:` address, an `iframe`, `object` or `embed`, an image or a web font;
   - a stylesheet link that does not resolve to a CSS file inside `design/wireframes/`;
   - a named product library (Tailwind, Bootstrap, shadcn, GSAP, Swiper and the like);
   - any colour that is not a grey: the wireframes carry no brand colour;
   - `@font-face`: the wireframes carry no final typography;
   - a document with no language and direction, or with ones that disagree (`en` with `ltr`, `ar` with `rtl`);
   - a document that does not say which canonical surfaces it shows, or names one that does not exist;
   - a motion note that does not state its trigger, its effect, whether it is essential or decorative, and what
     happens under reduced motion;
   - a stylesheet large enough to be a library rather than a small neutral structural system.

What it cannot check: whether a wireframe is good, or whether it draws only what its surfaces list. Those are
review judgements, made against the brief.

Usage: python tools/design_wireframes.py
"""

from __future__ import annotations

import json
import re
import sys
from html.parser import HTMLParser
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
WIREFRAMES = "design/wireframes"

REVIEW = ("not_started", "briefed", "exploration", "candidate_selected", "wireframed", "reviewed", "not_applicable")
CELL = ("not_started", "drawn", "reviewed", "not_applicable")
# What the manifest's review status means for the coarse status a surface carries in the inventory.
INVENTORY_STATUS = {"not_started": ("inventoried",), "briefed": ("briefed",), "exploration": ("briefed",),
                    "candidate_selected": ("briefed",), "wireframed": ("wireframed",),
                    "reviewed": ("reviewed", "branded", "approved"), "not_applicable": ("not_designed", "inventoried")}
LANGUAGES = {"en-ltr": ("en", "ltr", "en"), "ar-rtl": ("ar", "rtl", "ar")}
VIEWPORTS = ("mobile", "desktop")
DIRECTIONS = ("a", "b", "c")
CALIBRATION_STATUSES = ("not_started", "drawn", "reviewed")
MAX_CSS_BYTES = 24 * 1024
MAX_HTML_BYTES = 256 * 1024

LIBRARIES = ("tailwind", "bootstrap", "shadcn", "gsap", "swiper", "jquery", "alpinejs", "htmx", "foundation.min",
             "bulma", "materialize", "fontawesome", "font-awesome", "animate.css")
GREY_NAMES = {"black", "white", "gray", "grey", "silver", "gainsboro", "lightgray", "lightgrey", "darkgray",
              "darkgrey", "dimgray", "dimgrey", "whitesmoke"}
# Every CSS named colour that is not a grey. A wireframe may use none of them.
COLOUR_NAMES = set("""
aliceblue antiquewhite aqua aquamarine azure beige bisque blanchedalmond blue blueviolet brown burlywood cadetblue
chartreuse chocolate coral cornflowerblue cornsilk crimson cyan darkblue darkcyan darkgoldenrod darkgreen darkkhaki
darkmagenta darkolivegreen darkorange darkorchid darkred darksalmon darkseagreen darkslateblue darkslategray
darkslategrey darkturquoise darkviolet deeppink deepskyblue dodgerblue firebrick floralwhite forestgreen fuchsia
ghostwhite gold goldenrod green greenyellow honeydew hotpink indianred indigo ivory khaki lavender lavenderblush
lawngreen lemonchiffon lightblue lightcoral lightcyan lightgoldenrodyellow lightgreen lightpink lightsalmon
lightseagreen lightskyblue lightslategray lightslategrey lightsteelblue lightyellow lime limegreen linen magenta
maroon mediumaquamarine mediumblue mediumorchid mediumpurple mediumseagreen mediumslateblue mediumspringgreen
mediumturquoise mediumvioletred midnightblue mintcream mistyrose moccasin navajowhite navy oldlace olive olivedrab
orange orangered orchid palegoldenrod palegreen paleturquoise palevioletred papayawhip peachpuff peru pink plum
powderblue purple rebeccapurple red rosybrown royalblue saddlebrown salmon sandybrown seagreen seashell sienna
skyblue slateblue slategray slategrey snow springgreen steelblue tan teal thistle tomato turquoise violet wheat
yellow yellowgreen
""".split())
COLOUR_PROPERTY = re.compile(r"(?:^|[;{\s])(?:[a-z-]*colou?r|background(?:-color)?|border(?:-[a-z-]+)?|outline(?:-color)?|"
                             r"fill|stroke|box-shadow|text-shadow|text-decoration(?:-color)?|caret-color|accent-color|"
                             r"column-rule(?:-color)?|--[a-z0-9-]+)\s*:\s*([^;{}]+)", re.I)
HEX = re.compile(r"#([0-9a-fA-F]{3,8})\b")
RGB = re.compile(r"rgba?\(\s*([0-9.]+%?)[\s,]+([0-9.]+%?)[\s,]+([0-9.]+%?)", re.I)
HSL = re.compile(r"hsla?\(\s*[0-9.a-z-]+[\s,]+([0-9.]+)%", re.I)
OTHER_COLOUR_FUNCTION = re.compile(r"\b(?:lab|lch|oklab|oklch|color|color-mix|hwb)\(", re.I)
COMMENT = re.compile(r"/\*.*?\*/", re.S)
MOTION_FIELDS = ("data-trigger", "data-effect", "data-role", "data-reduced-motion")


def load(root: Path) -> dict:
    def read(*parts: str):
        return json.loads(root.joinpath(*parts).read_text(encoding="utf-8"))

    return {
        "manifest": read("design", "wireframes", "manifest.json"),
        "surfaces": read("design", "inventory", "surfaces.json")["surfaces"],
        "options": read("design", "inventory", "interaction-options.json")["options"],
    }


def pending(surface: dict, options: list[dict]) -> list[str]:
    """The interaction choices that bear on a surface and are not yet locked. Derived from the inventory."""
    locked = {o["id"] for o in options if o["status"] == "locked"}
    return [o for o in surface["interaction_options"] if o not in locked]


def calibration_artifacts(manifest: dict) -> dict[str, tuple[str, str, str]]:
    """Every file the calibration set may hold: path -> (frame, direction, language)."""
    out = {}
    for frame in manifest["calibration"]["frames"]:
        for direction in frame.get("status", {}):
            for language in frame.get("languages", []):
                if language in LANGUAGES:
                    path = f"{WIREFRAMES}/s1/calibration/{direction}/{frame['id']}.{LANGUAGES[language][2]}.html"
                    out[path] = (frame["id"], direction, language)
    return out


def check_manifest(data: dict, root: Path) -> list[str]:
    errs: list[str] = []
    manifest, surfaces = data["manifest"], {s["id"]: s for s in data["surfaces"]}
    batches = {b["id"]: b for b in manifest["batches"]}
    second = [b["id"] for b in manifest["batches"] if b.get("release") == "S2"]
    if len(second) != 1:
        errs.append("manifest: exactly one batch carries the second release (release S2)")
    entries = manifest["entries"]
    seen = [e.get("surface") for e in entries]
    for sid in sorted(set(seen)):
        if seen.count(sid) > 1:
            errs.append(f"manifest: {sid} has {seen.count(sid)} entries")
    for sid in sorted(set(surfaces) - set(seen)):
        errs.append(f"manifest: surface {sid} has no entry")
    for e in entries:
        sid = e.get("surface")
        if sid not in surfaces:
            errs.append(f"manifest: {sid} is not a surface of the inventory")
            continue
        s = surfaces[sid]
        for field in ("batch", "brief", "artifacts", "en_ltr", "ar_rtl", "mobile", "desktop", "states_covered",
                      "interaction_pending", "review_status"):
            if field not in e:
                errs.append(f"manifest {sid}: missing {field}")
        if any(f not in e for f in ("batch", "brief", "artifacts", "en_ltr", "ar_rtl", "mobile", "desktop",
                                    "states_covered", "interaction_pending", "review_status")):
            continue
        for cell in ("en_ltr", "ar_rtl", "mobile", "desktop"):
            if e[cell] not in CELL:
                errs.append(f"manifest {sid}: {cell} {e[cell]!r} is not one of {', '.join(CELL)}")
        if e["review_status"] not in REVIEW:
            errs.append(f"manifest {sid}: review_status {e['review_status']!r} is not one of {', '.join(REVIEW)}")
            continue
        # Which batch, and whether it may have none
        if e["batch"] is None:
            if s["scope_type"] != "provider_hosted" and not e.get("not_in_low_fidelity"):
                errs.append(f"manifest {sid}: in no review batch, and does not say why (not_in_low_fidelity)")
            if e["review_status"] != "not_applicable":
                errs.append(f"manifest {sid}: in no review batch, so its review status is not_applicable")
        elif e["batch"] not in batches:
            errs.append(f"manifest {sid}: batch {e['batch']} is not a batch")
        else:
            if s["scope_type"] == "provider_hosted":
                errs.append(f"manifest {sid}: a provider's interface is not wireframed and is in no batch")
            if (s["stage"] == "S2") != (batches[e["batch"]].get("release") == "S2"):
                errs.append(f"manifest {sid}: second-release surfaces are in the second-release batch, and only they "
                            "are (DQ-04)")
        if s["rtl"].get("applies") is not True and e["ar_rtl"] != "not_applicable":
            errs.append(f"manifest {sid}: Arabic does not apply to this surface, so ar_rtl is not_applicable")
        if s["rtl"].get("applies") is True and e["batch"] is not None and e["ar_rtl"] == "not_applicable":
            errs.append(f"manifest {sid}: a customer-facing surface is drawn in Arabic too; ar_rtl cannot be "
                        "not_applicable")
        if e["interaction_pending"] != pending(s, data["options"]):
            errs.append(f"manifest {sid}: interaction_pending differs from the inventory")
        if s["status"] not in INVENTORY_STATUS[e["review_status"]]:
            errs.append(f"manifest {sid}: review status {e['review_status']} and the inventory status "
                        f"{s['status']} disagree")
        # The brief
        started = e["review_status"] not in ("not_started", "not_applicable")
        if e["brief"] is not None:
            if e["brief"] != f"design/briefs/{sid}.md":
                errs.append(f"manifest {sid}: its brief is design/briefs/{sid}.md")
            elif not (root / e["brief"]).is_file():
                errs.append(f"manifest {sid}: brief {e['brief']} does not exist; run tools/design_briefs.py --write")
            if not started:
                errs.append(f"manifest {sid}: has a brief, so its review status is at least briefed")
        elif started:
            errs.append(f"manifest {sid}: {e['review_status']}, and has no brief")
        if started and not s.get("purpose"):
            errs.append(f"manifest {sid}: briefed, and the inventory gives the surface no purpose")
        # The artefacts
        prefix = f"{WIREFRAMES}/{'s2' if s['stage'] == 'S2' else 's1'}/"
        for path in e["artifacts"]:
            name = path.rsplit("/", 1)[-1]
            if not path.startswith(prefix) or not re.fullmatch(re.escape(sid) + r"\.(en|ar)\.html", name):
                errs.append(f"manifest {sid}: artefact {path} is not {prefix}<batch>/{sid}.en.html or .ar.html")
            elif not (root / path).is_file():
                errs.append(f"manifest {sid}: artefact {path} does not exist")
        drawn = [c for c in ("en_ltr", "ar_rtl", "mobile", "desktop") if e[c] in ("drawn", "reviewed")]
        if drawn and not e["artifacts"]:
            errs.append(f"manifest {sid}: {', '.join(drawn)} marked drawn, and no artefact is listed")
        if e["artifacts"] and e["review_status"] in ("not_started", "briefed", "not_applicable"):
            errs.append(f"manifest {sid}: has artefacts, so its review status is past briefed")
        known_states = {st["state"] for st in s["states"]}
        for state in e["states_covered"]:
            if state not in known_states:
                errs.append(f"manifest {sid}: covers state {state}, which the surface does not list")
        if e["review_status"] == "reviewed" and set(e["states_covered"]) != known_states:
            missing = ", ".join(sorted(known_states - set(e["states_covered"])))
            errs.append(f"manifest {sid}: reviewed, and these required states are not covered: {missing}")

    # The calibration set
    cal = manifest["calibration"]
    labels = [d.get("id") for d in manifest["directions"]]
    if labels != list(DIRECTIONS):
        errs.append("manifest: the calibration has the three structural directions a, b and c")
    frame_ids = [f.get("id") for f in cal["frames"]]
    for fid in sorted(set(frame_ids)):
        if frame_ids.count(fid) > 1:
            errs.append(f"calibration {fid}: id used {frame_ids.count(fid)} times")
    for frame in cal["frames"]:
        fid = frame.get("id", "?")
        if frame.get("viewport") not in VIEWPORTS:
            errs.append(f"calibration {fid}: viewport is mobile or desktop")
        if not frame.get("languages") or any(lang not in LANGUAGES for lang in frame["languages"]):
            errs.append(f"calibration {fid}: languages are drawn from {', '.join(LANGUAGES)}")
        elif "en-ltr" not in frame["languages"]:
            errs.append(f"calibration {fid}: English is the canonical design and is always drawn")
        if sorted(frame.get("status", {})) != list(DIRECTIONS):
            errs.append(f"calibration {fid}: one status for each of the three directions")
        elif any(v not in CALIBRATION_STATUSES for v in frame["status"].values()):
            errs.append(f"calibration {fid}: a status is one of {', '.join(CALIBRATION_STATUSES)}")
        wanted: list[str] = []
        for sid in frame.get("surfaces", []):
            if sid not in surfaces:
                errs.append(f"calibration {fid}: {sid} is not a surface of the inventory")
                continue
            s = surfaces[sid]
            if s["stage"] == "S2" or s["scope_type"] != "contracted" or not {"visitor", "customer"} & set(s["audience"]):
                errs.append(f"calibration {fid}: {sid} is not a first-release, contracted, customer-facing surface")
            for o in pending(s, data["options"]):
                if o not in wanted:
                    wanted.append(o)
        if not frame.get("surfaces"):
            errs.append(f"calibration {fid}: reuses no existing surface")
        for pair in frame.get("shows", []):
            sid, _, state = pair.partition(":")
            if sid not in frame.get("surfaces", []) or sid not in surfaces or                     state not in {st["state"] for st in surfaces[sid]["states"]}:
                errs.append(f"calibration {fid}: shows {pair}, which is not a state of a surface in the frame")
        if frame.get("interaction_options") != wanted:
            errs.append(f"calibration {fid}: interaction_options differs from the inventory")
    # A declared calibration file may exist while its direction still reads not_started: the designer creates
    # the files and does not edit the manifest, and the status is recorded after the design review.
    expected = calibration_artifacts(manifest)
    for frame in cal["frames"]:
        for direction, status in frame.get("status", {}).items():
            if status == "not_started":
                continue
            for path, (fid, d, _) in expected.items():
                if fid == frame["id"] and d == direction and not (root / path).is_file():
                    errs.append(f"calibration {frame['id']}: direction {direction} is {status}, and {path} is missing")
    sel = manifest["selection"]
    if sel.get("selected_direction") not in (None, *DIRECTIONS):
        errs.append("manifest: the selected direction is a, b, c or nothing yet")
    all_reviewed = all(v == "reviewed" for f in cal["frames"] for v in f.get("status", {}).values())
    if sel.get("selected_direction") is not None and not all_reviewed:
        errs.append("manifest: a direction is selected before every calibration frame has been reviewed")
    if sel.get("selected_direction") is not None and not sel.get("decision_source"):
        errs.append("manifest: a direction is selected, and the record does not say by whom and where")

    # No file the manifest does not know
    listed = {p for e in entries for p in e.get("artifacts", [])} | set(expected)
    folder = root / WIREFRAMES
    if folder.is_dir():
        for path in sorted(folder.rglob("*.html")):
            rel = path.relative_to(root).as_posix()
            if rel not in listed:
                errs.append(f"{rel}: a wireframe the manifest does not list")
    return errs


class Document(HTMLParser):
    """What a wireframe document declares, and what it must not contain."""

    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.problems: list[str] = []
        self.root: dict[str, str] | None = None
        self.meta: dict[str, str] = {}
        self.stylesheets: list[str] = []
        self.styles: list[str] = []
        self._in_style = False

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        a = {k.lower(): (v or "") for k, v in attrs}
        if tag == "html":
            self.root = a
        if tag in ("script", "iframe", "object", "embed", "img", "picture", "source", "video", "audio", "base",
                   "foreignobject", "image", "use"):
            self.problems.append(f"a <{tag}> element")
        if tag == "style":
            self._in_style = True
        for name, value in a.items():
            if name.startswith("on"):
                self.problems.append(f"an inline event handler ({name})")
            if name in ("href", "src", "action", "formaction", "xlink:href", "poster", "srcset", "data"):
                self.address(tag, name, value.strip())
            if name == "style":
                self.styles.append(value)
        if tag == "meta" and a.get("name", "").startswith("wf-"):
            self.meta[a["name"]] = a.get("content", "")
        if tag == "link":
            if a.get("rel", "").lower() == "stylesheet":
                self.stylesheets.append(a.get("href", ""))
            else:
                self.problems.append(f"a <link rel=\"{a.get('rel', '')}\">: only a stylesheet may be linked")
        if "wf-motion" in a.get("class", "").split():
            missing = [f for f in MOTION_FIELDS if not a.get(f)]
            if missing:
                self.problems.append("a motion note without " + ", ".join(missing))
            elif a["data-role"] not in ("essential", "decorative"):
                self.problems.append("a motion note whose data-role is not essential or decorative")

    def address(self, tag: str, name: str, value: str) -> None:
        low = value.lower()
        if low.startswith(("javascript:", "data:", "vbscript:")):
            self.problems.append(f"a {low.split(':')[0]}: address")
        elif "//" in low.split("?")[0].split("#")[0] or re.match(r"^[a-z][a-z0-9+.-]*:", low):
            self.problems.append(f"an address outside the wireframes ({value})")
        elif tag == "form" and value not in ("", "#"):
            self.problems.append("a form that submits somewhere")

    def handle_endtag(self, tag: str) -> None:
        if tag == "style":
            self._in_style = False

    def handle_data(self, data: str) -> None:
        if self._in_style:
            self.styles.append(data)

    def handle_pi(self, data: str) -> None:
        self.problems.append("a server tag")


def channel(value: str) -> float:
    return float(value[:-1]) * 2.55 if value.endswith("%") else float(value)


def check_css(text: str) -> list[str]:
    """Problems in one piece of CSS: a stylesheet, a style element or a style attribute."""
    problems = []
    css = COMMENT.sub("", text)
    low = css.lower()
    for token, label in (("@import", "@import"), ("@font-face", "@font-face"), ("url(", "url()"),
                         ("expression(", "expression()"), ("behavior:", "behavior"), ("-moz-binding", "-moz-binding")):
        if token in low:
            problems.append(label)
    # Colours live in declarations. Selectors are left out, so an id selector is not read as a hex colour.
    css = " ".join(re.findall(r"\{([^{}]*)\}", css)) if "{" in css else css
    for h in HEX.findall(css):
        digits = h[:3] if len(h) in (3, 4) else h[:6]
        parts = list(digits) if len(digits) == 3 else [digits[0:2], digits[2:4], digits[4:6]]
        if len({p.lower() for p in parts}) != 1:
            problems.append(f"a colour that is not a grey (#{h})")
    for r, g, b in RGB.findall(css):
        if len({round(channel(r)), round(channel(g)), round(channel(b))}) != 1:
            problems.append(f"a colour that is not a grey (rgb {r} {g} {b})")
    for saturation in HSL.findall(css):
        if float(saturation) != 0:
            problems.append(f"a colour that is not a grey (hsl saturation {saturation}%)")
    if OTHER_COLOUR_FUNCTION.search(css):
        problems.append("a colour function other than hex, rgb or hsl")
    for value in COLOUR_PROPERTY.findall(css):
        for word in re.findall(r"[a-z]+", value.lower()):
            if word in COLOUR_NAMES:
                problems.append(f"a colour that is not a grey ({word})")
    return problems


def check_text(text: str) -> list[str]:
    low = text.lower()
    problems = [f"a product library ({name})" for name in LIBRARIES if name in low]
    if "<?" in text or "<%" in text or "{{" in text or "{%" in text:
        problems.append("a server or template tag")
    return problems


def check_html(rel: str, text: str, surfaces: set[str], root: Path) -> list[str]:
    doc = Document()
    doc.feed(text)
    problems = check_text(text) + doc.problems
    for style in doc.styles:
        problems += check_css(style)
    attrs = doc.root or {}
    lang, direction = attrs.get("lang", ""), attrs.get("dir", "")
    if (lang, direction) not in {("en", "ltr"), ("ar", "rtl")}:
        problems.append(f"lang {lang!r} with dir {direction!r}: a wireframe is en with ltr, or ar with rtl")
    name_lang = re.search(r"\.(en|ar)\.html$", rel)
    if not name_lang:
        problems.append("a file name that does not end .en.html or .ar.html")
    elif name_lang.group(1) != lang:
        problems.append(f"a file named .{name_lang.group(1)}.html whose document language is {lang!r}")
    shown = doc.meta.get("wf-surfaces", "").split()
    if not shown:
        problems.append("no <meta name=\"wf-surfaces\"> naming the canonical surfaces it shows")
    for sid in shown:
        if sid not in surfaces:
            problems.append(f"it names {sid}, which is not a surface of the inventory")
    in_calibration = re.search(r"/calibration/([^/]+)/", rel)
    if in_calibration and doc.meta.get("wf-direction") != in_calibration.group(1):
        problems.append(f"a calibration frame of direction {in_calibration.group(1)} whose wf-direction says "
                        f"{doc.meta.get('wf-direction')!r}")
    if doc.meta.get("wf-viewport") not in VIEWPORTS + ("mobile desktop",):
        problems.append("no <meta name=\"wf-viewport\"> saying mobile, desktop, or mobile desktop")
    if not doc.stylesheets:
        problems.append("no link to the shared wireframe stylesheet")
    base = (root / rel).parent
    for href in doc.stylesheets:
        target = (base / href.split("?")[0].split("#")[0]).resolve()
        inside = (root / WIREFRAMES).resolve() in target.parents
        if not href.lower().endswith(".css") or not inside:
            problems.append(f"a stylesheet outside design/wireframes/ ({href})")
        elif not target.is_file():
            problems.append(f"a stylesheet that does not exist ({href})")
    if len(text.encode("utf-8")) > MAX_HTML_BYTES:
        problems.append(f"more than {MAX_HTML_BYTES // 1024} KB")
    return [f"{rel}: {p}" for p in dict.fromkeys(problems)]


def check_stylesheet(rel: str, text: str) -> list[str]:
    problems = check_text(text) + check_css(text)
    if len(text.encode("utf-8")) > MAX_CSS_BYTES:
        problems.append(f"more than {MAX_CSS_BYTES // 1024} KB: a small neutral structural system, not a library")
    return [f"{rel}: {p}" for p in dict.fromkeys(problems)]


def check_artefacts(root: Path, surfaces: set[str]) -> list[str]:
    errs: list[str] = []
    folder = root / WIREFRAMES
    if not folder.is_dir():
        return errs
    for path in sorted(folder.rglob("*")):
        if not path.is_file():
            continue
        rel = path.relative_to(root).as_posix()
        if path.suffix == ".html":
            errs += check_html(rel, path.read_text(encoding="utf-8"), surfaces, root)
        elif path.suffix == ".css":
            errs += check_stylesheet(rel, path.read_text(encoding="utf-8"))
        elif path.suffix not in (".md", ".json"):
            errs.append(f"{rel}: only HTML, CSS, Markdown and JSON belong under design/wireframes/")
    return errs


def validate(root: Path = ROOT) -> list[str]:
    data = load(root)
    return check_manifest(data, root) + check_artefacts(root, {s["id"] for s in data["surfaces"]})


def main() -> int:
    data = load(ROOT)
    errs = validate(ROOT)
    for e in errs:
        print(e)
    manifest = data["manifest"]
    files = sum(1 for p in (ROOT / WIREFRAMES).rglob("*") if p.suffix in (".html", ".css"))
    briefed = sum(1 for e in manifest["entries"] if e.get("brief"))
    print(f"design-wireframes: {len(manifest['entries'])} entries, {len(manifest['batches'])} batches, "
          f"{len(manifest['calibration']['frames'])} calibration frames, {briefed} briefs, {files} artefact files, "
          f"{len(errs)} problems")
    return 1 if errs else 0


if __name__ == "__main__":
    sys.exit(main())

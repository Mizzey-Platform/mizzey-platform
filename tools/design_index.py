"""Generate the navigation page of the wireframes, `design/wireframes/index.html`.

One page from which every wireframe can be reached: by journey (the customer flows and the staff flows, step by
step, each step linking to the screens it uses), by review batch (every surface with its states, languages, viewports
and review status), and by shared component. It is **generated, never written**: every line comes from
`design/wireframes/manifest.json` and `design/inventory/` (surfaces, flows, components), so it cannot drift from them.
`tools/design_wireframes.py` fails when the committed page is not what this tool writes.

The page is static HTML with one inline style block: no script, no outside resource, greys only, so it follows the
same rules as the wireframes it links to. Open it in a browser from the repository checkout.

Usage: python tools/design_index.py           check that the committed page is current
       python tools/design_index.py --write   write it
"""

from __future__ import annotations

import argparse
import html
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
WIREFRAMES = "design/wireframes"
INDEX = f"{WIREFRAMES}/index.html"
LANG = {"en": "English", "ar": "Arabic"}

STYLE = """
:root{color-scheme:light;--ink:#1f1f1f;--muted:#5c5c5c;--line:#d6d6d6;--soft:#f3f3f3;--chip:#e6e6e6}
*{box-sizing:border-box}
body{margin:0;font:15px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans Arabic",sans-serif;color:var(--ink);background:#fff}
main{max-inline-size:1180px;margin:0 auto;padding:24px 16px 64px}
h1{font-size:26px;margin:0 0 4px}h2{font-size:21px;margin:40px 0 8px;padding-block-start:16px;border-block-start:1px solid var(--line)}
h3{font-size:17px;margin:24px 0 6px}p{margin:6px 0}.muted{color:var(--muted)}
nav.toc{display:flex;flex-wrap:wrap;gap:8px;margin:16px 0}
nav.toc a,.chip{display:inline-block;padding:4px 10px;border:1px solid var(--line);border-radius:999px;background:var(--soft);color:var(--ink);text-decoration:none;font-size:14px}
a{color:#111}a:focus-visible{outline:3px solid #111;outline-offset:2px}
details{border:1px solid var(--line);border-radius:8px;margin:8px 0;background:#fff}
summary{cursor:pointer;padding:10px 14px;font-weight:600}
summary .muted{font-weight:400}
.body{padding:0 14px 12px}
table{border-collapse:collapse;inline-size:100%;font-size:14px}
th,td{text-align:start;vertical-align:top;padding:7px 8px;border-block-end:1px solid var(--line)}
th{background:var(--soft);font-weight:600}
td.links a{display:inline-block;margin-inline-end:8px}
.states{color:var(--muted);font-size:13px}
ol.steps{padding-inline-start:22px;margin:6px 0}ol.steps li{margin:6px 0}
.legend{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;margin:12px 0}
.legend div{background:var(--soft);border-radius:8px;padding:8px 12px;font-size:14px}
""".strip()


def load(root: Path) -> dict:
    def read(*parts: str):
        return json.loads(root.joinpath(*parts).read_text(encoding="utf-8"))

    return {
        "manifest": read("design", "wireframes", "manifest.json"),
        "surfaces": read("design", "inventory", "surfaces.json")["surfaces"],
        "flows": read("design", "inventory", "flows.json")["flows"],
        "components": read("design", "inventory", "components.json")["components"],
    }


def esc(text: object) -> str:
    return html.escape(str(text), quote=True)


def rel(path: str) -> str:
    """A manifest artefact path, relative to the index page."""
    return path[len(WIREFRAMES) + 1:] if path.startswith(WIREFRAMES + "/") else path


def language_of(path: str) -> str:
    parts = path.rsplit(".", 2)
    return parts[1] if len(parts) == 3 else ""


def surface_links(sid: str, entries: dict, surfaces: dict) -> str:
    """The links to one surface's files, or why there is none."""
    e = entries.get(sid)
    name = surfaces.get(sid, {}).get("name", sid)
    if not e or not e.get("artifacts"):
        status = e["review_status"] if e else "not in the manifest"
        why = "not wireframed: a provider's or the standard screen" if status == "not_applicable" else status
        return f'<span class="muted" title="{esc(name)}">{esc(sid)} ({esc(why)})</span>'
    links = " ".join(f'<a href="{esc(rel(p))}" lang="en">{esc(LANG.get(language_of(p), language_of(p)))}</a>'
                     for p in e["artifacts"])
    return f'<span title="{esc(name)}"><b>{esc(sid)}</b>: {links}</span>'


def build(data: dict) -> str:
    manifest = data["manifest"]
    entries = {e["surface"]: e for e in manifest["entries"]}
    surfaces = {s["id"]: s for s in data["surfaces"]}
    batches = manifest["batches"]
    drawn = [e for e in manifest["entries"] if e.get("artifacts")]
    files = sum(len(e["artifacts"]) for e in drawn)
    reviewed = sum(1 for e in manifest["entries"] if e["review_status"] == "reviewed")
    closure = manifest.get("stabilisation", {}).get("phase_closure", {})
    customer = [f for f in data["flows"] if not f["id"].startswith("S")]
    staff = [f for f in data["flows"] if f["id"].startswith("S")]

    out: list[str] = []
    w = out.append
    w("<!doctype html>")
    w('<html lang="en" dir="ltr">')
    w("<head>")
    w('<meta charset="utf-8">')
    w('<meta name="viewport" content="width=device-width, initial-scale=1">')
    w("<title>Mizzey wireframes, navigation</title>")
    w(f"<style>\n{STYLE}\n</style>")
    w("</head>")
    w("<body>")
    w("<main>")
    w("<h1>Mizzey wireframes</h1>")
    status = (f"The low-fidelity phase closed on {esc(closure['date'])}. " if closure.get("date") else "")
    w(f'<p class="muted">{status}{len(drawn)} drawn surfaces in {files} files, {reviewed} reviewed, in '
      f"{len(batches)} review batches. Generated from the manifest and the inventory by tools/design_index.py; "
      "do not edit this page by hand.</p>")
    w('<div class="legend">'
      "<div><b>Structure only.</b> Greys, a system typeface and labelled boxes: not the brand, not the "
      "interface design, not PRE-03b.</div>"
      "<div><b>Languages.</b> English is left to right, Arabic right to left; the notes are in English in both.</div>"
      "<div><b>States.</b> Each file draws every state listed for its surface, at mobile 360 and desktop widths.</div>"
      "<div><b>Administration.</b> English only, desktop, drawn on the standard WordPress and WooCommerce "
      "screens.</div></div>")
    w('<nav class="toc" aria-label="Sections">'
      '<a href="#journeys">Customer journeys</a><a href="#staff">Staff flows</a>'
      '<a href="#batches">All screens by batch</a><a href="#components">Shared components</a></nav>')

    def flow_section(anchor: str, title: str, flows: list[dict], intro: str) -> None:
        w(f'<h2 id="{anchor}">{esc(title)}</h2>')
        w(f'<p class="muted">{esc(intro)}</p>')
        for f in flows:
            w(f'<details id="{esc(f["id"])}"><summary>{esc(f["id"])} {esc(f["name"])} '
              f'<span class="muted">({len(f["steps"])} steps, {esc(", ".join(f.get("audience", [])))}, '
              f'{esc(f.get("stage", ""))})</span></summary><div class="body"><ol class="steps">')
            for s in f["steps"]:
                links = "; ".join(surface_links(x, entries, surfaces) for x in s.get("surfaces", []))
                w(f'<li><b>{esc(s["step"])}</b> {esc(s["what"])}<br>{links}</li>')
            w("</ol></div></details>")

    flow_section("journeys", "Customer journeys", customer,
                 "Each journey step by step. A step links to the screens it uses, in each language drawn.")
    flow_section("staff", "Staff flows", staff,
                 "The administration flows. Screens are drawn on the standard administration, in English.")

    w('<h2 id="batches">All screens by batch</h2>')
    w('<p class="muted">Every surface of the inventory that belongs to a wireframe batch, with its states and files.</p>')
    for b in batches:
        members = [e for e in manifest["entries"] if e.get("batch") == b["id"]]
        drawn_here = sum(1 for e in members if e.get("artifacts"))
        w(f'<details id="{esc(b["id"])}"><summary>{esc(b["id"])} {esc(b["name"])} '
          f'<span class="muted">({esc(b["release"])}, {drawn_here} drawn of {len(members)})</span></summary>'
          '<div class="body"><table><thead><tr><th scope="col">Surface</th><th scope="col">Files</th>'
          '<th scope="col">States</th><th scope="col">Status</th></tr></thead><tbody>')
        for e in sorted(members, key=lambda x: x["surface"]):
            s = surfaces.get(e["surface"], {})
            files_html = (" ".join(f'<a href="{esc(rel(p))}">{esc(LANG.get(language_of(p), language_of(p)))}</a>'
                                   for p in e["artifacts"]) if e.get("artifacts") else '<span class="muted">none</span>')
            states = ", ".join(x["state"] for x in s.get("states", []))
            w(f'<tr><td><b>{esc(e["surface"])}</b><br>{esc(s.get("name", ""))}</td><td class="links">{files_html}</td>'
              f'<td class="states">{esc(states)}</td><td>{esc(e["review_status"])}</td></tr>')
        w("</tbody></table></div></details>")

    w('<h2 id="components">Shared components</h2>')
    w('<p class="muted">The structural components and the screens that use them. Brand values arrive later.</p>')
    layers: dict[str, list[dict]] = {}
    for c in data["components"]:
        layers.setdefault(c.get("layer", "other"), []).append(c)
    for layer in sorted(layers):
        w(f'<details><summary>{esc(layer)} <span class="muted">({len(layers[layer])})</span></summary><div class="body">'
          '<table><thead><tr><th scope="col">Component</th><th scope="col">Used by</th></tr></thead><tbody>')
        for c in sorted(layers[layer], key=lambda x: x["id"]):
            used = "; ".join(surface_links(x, entries, surfaces) for x in c.get("used_by", []))
            w(f'<tr><td><b>{esc(c["id"])}</b><br>{esc(c.get("name", ""))}</td><td>{used}</td></tr>')
        w("</tbody></table></div></details>")

    w("</main>")
    w("</body>")
    w("</html>")
    return "\n".join(out) + "\n"


def check(root: Path, data: dict | None = None) -> list[str]:
    """The committed page, when there is one, is exactly what this tool writes."""
    target = root / INDEX
    if not target.is_file():
        return []
    want = build(data if data is not None else load(root))
    if target.read_text(encoding="utf-8") != want:
        return [f"{INDEX}: stale; run python tools/design_index.py --write"]
    return []


def main(argv: list[str] | None = None) -> int:
    ap = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    ap.add_argument("--write", action="store_true", help="write design/wireframes/index.html")
    a = ap.parse_args(argv)
    data = load(ROOT)
    if a.write:
        (ROOT / INDEX).write_text(build(data), encoding="utf-8", newline="\n")
        print(f"design-index: wrote {INDEX}")
        return 0
    errs = check(ROOT, data)
    for e in errs:
        print(e)
    print(f"design-index: {'missing (not committed yet)' if not (ROOT / INDEX).is_file() else 'checked'}, "
          f"{len(errs)} problems")
    return 1 if errs else 0


if __name__ == "__main__":
    sys.exit(main())

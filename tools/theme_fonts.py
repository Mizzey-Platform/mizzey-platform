"""Make the theme's self-hosted fonts: Inter and Tajawal, WOFF2, cut per script (spec 066, slice 1; FOUNDATIONS A.7).

Sources (open-licence files, outside this repository; this tool never downloads anything):

- Inter 4.1, the official release, `<brand>/official-releases/inter-4.1/`. `InterVariable.ttf` is preferred: it is
  cut to the weight range 300 to 900 with the optical-size axis pinned at 14 (text size), one file for every
  weight. Without it, the static `Inter-<Style>.ttf` files of the same release are used, one file per weight. One
  Inter version only; the brand pack's Inter 3.019 is never used.
- Tajawal 1.700: Regular from `<brand>/official-releases/tajawal-1.700/`, Light, Medium and Black from the brand
  pack, `<brand>/final-pack/Fonts/` (the same version).

`<brand>` is the workspace `design/brand` folder: `--brand-dir`, or `MIZZEY_BRAND_DIR`, or the first `design/brand`
found above the repository.

    python tools/theme_fonts.py [--brand-dir PATH] [--theme DIR] [--keep-hinting]

It writes, under `<theme>/assets/`:

- `fonts/<face>-<hash>.woff2`: Inter cut to Latin; Tajawal cut to Arabic plus the shared spaces, brackets and
  punctuation and its own digits 0 to 9, with its Latin letters left out (Latin words are always Inter). `<hash>` is the first eight hex
  digits of the file's SHA-256, so the file can be cached for a year; files of an earlier build are removed;
- `fonts/Inter-OFL.txt`, `fonts/Tajawal-OFL.txt`: the licences, which travel with the files;
- `fonts/fonts.json`: the faces (read by `tools/theme_tokens.py` into `theme.json`), the files each kind of page
  loads, the faces preloaded per language, and any source that was not found;
- `css/fonts.css`: the fallback faces, a system font resized to the web font so the swap moves nothing
  (`size-adjust` and the three metric overrides, measured from the files), and "Inter Ar", Inter again at 94% for
  Latin runs inside Arabic text.

Then run `python tools/theme_tokens.py`. A missing source is reported, listed in `missing_sources`, and exits 1.
"""

from __future__ import annotations

import argparse
import hashlib
import io
import json
import os
import shutil
import sys
from dataclasses import dataclass
from pathlib import Path

from fontTools import subset
from fontTools.ttLib import TTFont
from fontTools.varLib import instancer

ROOT = Path(__file__).resolve().parents[1]
THEME = ROOT / "mizzey-theme"

LATIN = ("U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+20AC, U+2122, "
         "U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD")
# The Arabic cut keeps Tajawal's own digits 0 to 9 (with the , . % already in it): Arabic pages show digits in
# Tajawal as the home boards draw them (the owner's ruling, 25 November 2026); Latin words stay Inter at 94%.
ARABIC = ("U+0600-06FF, U+0750-077F, U+FB50-FDFF, U+FE70-FEFC, U+200C-200F, U+2010-2011, U+0020-0029, "
          "U+002C-002E, U+0030-003A, U+00A0, U+00AB, U+00BB")
INTER_FEATURES = ["kern", "liga", "calt", "tnum", "case", "ccmp", "locl", "mark", "mkmk"]
VARIABLE_LIMITS = {"wght": (300, 900), "opsz": 14}

INTER = "official-releases/inter-4.1"
TAJAWAL = "official-releases/tajawal-1.700"
PACK = "final-pack/Fonts"
STYLES = {300: "Light", 400: "Regular", 500: "Medium", 700: "Bold", 900: "Black"}
LICENCES = (("Inter-OFL.txt", f"{INTER}/LICENSE.txt"), ("Tajawal-OFL.txt", f"{TAJAWAL}/OFL.txt"))
FALLBACKS = {"Inter": ["Inter Fallback"], "Tajawal": ["Inter Ar", "Tajawal Fallback", "Inter Fallback"]}
INTER_AR_SIZE = "94%"
# Latin fallbacks are measured against the system font; the Arabic size-adjust is the joined-forms measurement of
# FOUNDATIONS A.7, because fontTools cannot shape Arabic. Vertical overrides are always measured from the files.
LATIN_FALLBACKS = (("Inter Fallback", "300 500", "Arial", "arial.ttf", 400),
                   ("Inter Fallback", "600 900", "Arial Bold", "arialbd.ttf", 900))
ARABIC_FALLBACKS = (("Tajawal Fallback", "300 500", "Tahoma", 400, 0.991),
                    ("Tajawal Fallback", "600 900", "Tahoma Bold", 900, 0.9942))
SAMPLE = ("Originals, well chosen. Vital Proteins Collagen Peptides, pasture raised, 20 oz. Free delivery in Cairo on "
          "orders over 1,500 EGP. Add to cart, then check out in two steps. Only 3 left in stock.")


@dataclass(frozen=True)
class Face:
    family: str
    weight: str          # "400", or "300 900" for the variable face
    stem: str            # file name before the hash
    source: str          # path under <brand>
    script: str          # latin or arabic
    variable: bool = False

    def covers(self, weight: int) -> bool:
        low, _, high = self.weight.partition(" ")
        return int(low) <= weight <= int(high or low)


def faces_for(brand: Path | None) -> list[Face]:
    """The faces to build: Inter variable when its file is there, the statics otherwise; Tajawal per weight."""
    if brand and (brand / INTER / "InterVariable.ttf").is_file():
        inter = [Face("Inter", "300 900", "inter-var-latin", f"{INTER}/InterVariable.ttf", "latin", True)]
    else:
        inter = [Face("Inter", str(w), f"inter-{w}-latin", f"{INTER}/Inter-{STYLES[w]}.ttf", "latin")
                 for w in (300, 400, 500, 700, 900)]
    tajawal = [Face("Tajawal", str(w), f"tajawal-{w}-arabic", f"{folder}/Tajawal-{STYLES[w]}.ttf", "arabic")
               for w, folder in ((300, PACK), (400, TAJAWAL), (500, PACK), (900, PACK))]
    return inter + tajawal


def page_sets(faces: list[Face]) -> dict[str, list[Face]]:
    """What each kind of page loads (FOUNDATIONS A.7). Arabic pages take Inter for Latin runs, digits and prices."""
    def inter(weights):
        return [f for f in faces if f.family == "Inter" and any(f.covers(w) for w in weights)]

    def tajawal(weights):
        return [f for f in faces if f.family == "Tajawal" and any(f.covers(w) for w in weights)]

    text = (400, 500, 700, 900)
    return {"en": inter(text), "en-lead": inter((300,) + text), "ar": tajawal((400, 500, 900)) + inter(text),
            "ar-lead": tajawal((300, 400, 500, 900)) + inter(text)}


def preloads(faces: list[Face]) -> dict[str, list[Face]]:
    """English: the Inter file for body text. Arabic: Tajawal 400 and 900 (FOUNDATIONS A.7)."""
    def pick(family, weight):
        return [f for f in faces if f.family == family and f.covers(weight)][:1]

    return {"en": pick("Inter", 400), "ar": pick("Tajawal", 400) + pick("Tajawal", 900)}


def find_brand_dir(given: str | None) -> Path | None:
    candidates = [given, os.environ.get("MIZZEY_BRAND_DIR")] + [str(p / "design" / "brand") for p in ROOT.parents]
    for candidate in candidates:
        if candidate and (Path(candidate) / "official-releases").is_dir():
            return Path(candidate)
    return None


def open_font(path: Path) -> TTFont:
    """A font read into memory, so no file handle stays open (Windows cannot remove an open file)."""
    return TTFont(io.BytesIO(Path(path).read_bytes()))


def subset_options(script: str, keep_hinting: bool) -> subset.Options:
    options = subset.Options()
    options.layout_features = INTER_FEATURES if script == "latin" else ["*"]
    options.hinting = keep_hinting
    options.name_IDs = ["*"]
    options.notdef_outline = True
    return options


def unicodes(script: str) -> list[int]:
    return subset.parse_unicodes((LATIN if script == "latin" else ARABIC).replace(" ", ""))


def woff2_bytes(font: TTFont) -> bytes:
    font.flavor = "woff2"
    buffer = io.BytesIO()
    font.save(buffer)
    return buffer.getvalue()


def static_woff2(source: Path, script: str, keep_hinting: bool) -> bytes:
    options = subset_options(script, keep_hinting)
    font = subset.load_font(io.BytesIO(Path(source).read_bytes()), options)
    subsetter = subset.Subsetter(options)
    subsetter.populate(unicodes=unicodes(script))
    subsetter.subset(font)
    return woff2_bytes(font)


def variable_woff2(source: Path, script: str, keep_hinting: bool) -> bytes:
    """Subset first (the instancer needs the glyph set settled), then limit wght and pin opsz."""
    options = subset_options(script, keep_hinting)
    font = subset.load_font(io.BytesIO(Path(source).read_bytes()), options)
    subsetter = subset.Subsetter(options)
    subsetter.populate(unicodes=unicodes(script))
    subsetter.subset(font)
    return woff2_bytes(instancer.instantiateVariableFont(font, VARIABLE_LIMITS))


def metric_font(face: Face, brand: Path, weight: int) -> TTFont:
    font = open_font(brand / face.source)
    if face.variable:
        font = instancer.instantiateVariableFont(font, {"wght": weight, "opsz": VARIABLE_LIMITS["opsz"]})
    return font


def vertical(font: TTFont) -> tuple[float, float, float]:
    """Ascent, descent and line gap in em, as browsers read them (typo metrics when the font asks for them)."""
    upm = font["head"].unitsPerEm
    os2 = font["OS/2"]
    if os2.fsSelection & (1 << 7):
        return os2.sTypoAscender / upm, -os2.sTypoDescender / upm, os2.sTypoLineGap / upm
    hhea = font["hhea"]
    return hhea.ascent / upm, -hhea.descent / upm, hhea.lineGap / upm


def average_advance(font: TTFont, text: str) -> float:
    cmap, hmtx, upm = font.getBestCmap(), font["hmtx"], font["head"].unitsPerEm
    widths = [hmtx[cmap[ord(c)]][0] / upm for c in text if ord(c) in cmap]
    return sum(widths) / len(widths)


def pct(value: float) -> str:
    return f"{value * 100:.2f}".rstrip("0").rstrip(".") + "%"


def face_rule(name: str, weights: str, local: str, adjust: float, metrics: tuple[float, float, float]) -> str:
    ascent, descent, gap = (m / adjust for m in metrics)
    return "\n".join(["@font-face {", f'\tfont-family: "{name}";', f'\tsrc: local("{local}");',
                      f"\tfont-weight: {weights};", f"\tsize-adjust: {pct(adjust)};",
                      f"\tascent-override: {pct(ascent)};", f"\tdescent-override: {pct(descent)};",
                      f"\tline-gap-override: {pct(gap)};", "}", ""])


def fallback_css(brand: Path, built: dict[Face, str], system_fonts: Path) -> str:
    out = ["/* Generated by tools/theme_fonts.py. Do not edit: run the tool again. Fallback faces resized to the",
           "   web fonts so the swap moves nothing (FOUNDATIONS A.7), and Inter again at 94% for Latin inside Arabic. */",
           ""]

    def web(family, weight):
        return next((f for f in built if f.family == family and f.covers(weight)), None)

    for name, weights, local, system_file, weight in LATIN_FALLBACKS:
        face = web("Inter", weight)
        if face and (system_fonts / system_file).is_file():
            font = metric_font(face, brand, weight)
            adjust = average_advance(font, SAMPLE) / average_advance(open_font(system_fonts / system_file), SAMPLE)
            out.append(face_rule(name, weights, local, adjust, vertical(font)))
    for name, weights, local, weight, adjust in ARABIC_FALLBACKS:
        face = web("Tajawal", weight)
        if face:
            out.append(face_rule(name, weights, local, adjust, vertical(metric_font(face, brand, weight))))
    for face, file in built.items():
        if face.family == "Inter":
            out.append("\n".join([
                "@font-face {", '\tfont-family: "Inter Ar";', f'\tsrc: url("../fonts/{file}") format("woff2");',
                f"\tfont-weight: {face.weight};", "\tfont-style: normal;", "\tfont-display: swap;",
                f"\tsize-adjust: {INTER_AR_SIZE};", f"\tunicode-range: {LATIN};", "}", ""]))
    return "\n".join(out)


def build(brand: Path | None, theme: Path = THEME, keep_hinting: bool = False,
          system_fonts: Path | None = None) -> dict:
    out_dir, css = theme / "assets" / "fonts", theme / "assets" / "css" / "fonts.css"
    system_fonts = system_fonts or Path(os.environ.get("WINDIR", "C:/Windows")) / "Fonts"
    out_dir.mkdir(parents=True, exist_ok=True)
    faces, missing, built = faces_for(brand), [], {}
    for face in faces:
        source = brand / face.source if brand else None
        if source is None or not source.is_file():
            missing.append(face.source)
            continue
        data = (variable_woff2 if face.variable else static_woff2)(source, face.script, keep_hinting)
        built[face] = f"{face.stem}-{hashlib.sha256(data).hexdigest()[:8]}.woff2"
        (out_dir / built[face]).write_bytes(data)
    for old in out_dir.glob("*.woff2"):
        if old.name not in built.values():
            old.unlink()
    for target, relative in LICENCES:
        if brand and (brand / relative).is_file():
            shutil.copyfile(brand / relative, out_dir / target)
        else:
            missing.append(relative)
    listed = [f for f in faces if f in built]
    manifest = {
        "$description": "Generated by tools/theme_fonts.py. Do not edit: run the tool again.",
        "faces": [{"fontFamily": f.family, "fontWeight": f.weight, "fontStyle": "normal", "fontDisplay": "swap",
                   "src": [f"file:./assets/fonts/{built[f]}"], "unicodeRange": LATIN if f.script == "latin" else ARABIC,
                   "file": built[f], "bytes": (out_dir / built[f]).stat().st_size,
                   "source": open_font(brand / f.source)["name"].getDebugName(5)} for f in listed],
        "fallbacks": FALLBACKS,
        "pages": {page: [built[f] for f in members if f in built] for page, members in page_sets(faces).items()},
        "preload": {lang: [built[f] for f in members if f in built] for lang, members in preloads(faces).items()},
        "missing_sources": missing,
    }
    (out_dir / "fonts.json").write_text(json.dumps(manifest, indent="\t") + "\n", encoding="utf-8", newline="\n")
    if built:
        css.parent.mkdir(parents=True, exist_ok=True)
        css.write_text(fallback_css(brand, built, system_fonts), encoding="utf-8", newline="\n")
    return manifest


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__.split("\n\n")[0])
    parser.add_argument("--brand-dir", help="the Mizzey workspace design/brand folder")
    parser.add_argument("--theme", type=Path, default=THEME)
    parser.add_argument("--keep-hinting", action="store_true", help="keep TrueType hinting (larger static files)")
    args = parser.parse_args(argv)
    brand = find_brand_dir(args.brand_dir)
    if brand is None:
        print("no design/brand folder with official-releases/ found; pass --brand-dir", file=sys.stderr)
    manifest = build(brand, args.theme, args.keep_hinting)
    fonts = args.theme / "assets" / "fonts"
    for page, files in manifest["pages"].items():
        print(f"{page}: {len(files)} files, {sum((fonts / f).stat().st_size for f in files) / 1024:.1f} KB")
    for item in manifest["missing_sources"]:
        print(f"missing source: {item}", file=sys.stderr)
    return 1 if manifest["missing_sources"] else 0


if __name__ == "__main__":
    sys.exit(main())

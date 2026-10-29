"""Record exactly what was handed over at go-live, and later show what changed since.

    python tools/handover_baseline.py make  --root <wordpress root> --tag v1.0.0 --out baseline-v1.0.0.json
                                            [--release mizzey-v1.0.0.zip] [--strip <folder/>]
    python tools/handover_baseline.py check --root <wordpress root> --baseline baseline-v1.0.0.json
                                            [--expect <baseline sha256>] [--strip <folder/>] [--report report.md]
    python tools/handover_baseline.py hash  baseline-v1.0.0.json

--root is a directory, or a .zip, .tar, .tar.gz or .tgz copy of one (for example a hosting backup).
--strip removes a leading folder from archive entries, for a backup that wraps the site in public_html/.

make   hashes every file of the live tree at handover, except the places the server and the store write to
       at runtime (listed in the baseline under "excluded"). It also records the plugin, theme and WordPress
       versions it finds. With --release it checks that every file of the release archive is on the tree
       unchanged and refuses to write a baseline if one is not: the baseline must describe the release.
       It prints the baseline's own sha256. That value goes into the signed Handover Baseline Record.

check  compares a tree with a baseline and reports every file changed, added or removed, grouped by area,
       plus every version change. With --expect it first proves the baseline file is the signed one.
       Exit code 0: identical. 1: differences found. 2: the baseline does not match --expect.

hash   prints a baseline file's sha256, to compare with the signed record.

Files only. Changes made in the database (settings, content, which plugins are active) are not visible here;
the store's audit log records administrative actions. Standard library only, Python 3.8 or later.
"""

from __future__ import annotations

import argparse
import datetime as dt
import fnmatch
import hashlib
import io
import json
import os
import re
import stat
import sys
import tarfile
import zipfile
from pathlib import Path, PurePosixPath

SCHEMA = 1

# Written by the server or the running store, so a difference here says nothing about who changed the code.
EXCLUDED = [
    "wp-config.php",
    ".htaccess",
    ".user.ini",
    "php.ini",
    "error_log",
    "*.log",
    ".git/",
    ".well-known/",
    "wp-content/uploads/",
    "wp-content/languages/wpml/",
    "wp-content/cache/",
    "wp-content/upgrade/",
    "wp-content/upgrade-temp-backup/",
    "wp-content/wflogs/",
    "wp-content/backups/",
    "wp-content/updraft/",
    "wp-content/ai1wm-backups/",
    "wp-content/wc-logs/",
    "wp-content/advanced-cache.php",
    "wp-content/object-cache.php",
]

HEADER_BYTES = 8192


def sha256(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def is_link(p: Path) -> bool:
    """A symlink, or a Windows junction (which Python before 3.12 does not report as a link)."""
    if p.is_symlink():
        return True
    try:
        attrs = os.lstat(p).st_file_attributes  # Windows only
    except (AttributeError, OSError):
        return False
    return bool(attrs & stat.FILE_ATTRIBUTE_REPARSE_POINT)


def excluded(path: str, patterns: list[str]) -> bool:
    for pat in patterns:
        if pat.endswith("/"):
            if path.startswith(pat) or ("/" + pat) in ("/" + path):
                return True
        elif "/" in pat:
            if fnmatch.fnmatchcase(path, pat):
                return True
        elif fnmatch.fnmatchcase(PurePosixPath(path).name, pat):
            return True
    return False


# ---------------------------------------------------------------------------------------------------------------
# Reading a tree: a directory, or an archive of one. Every source yields (posix path, bytes) for regular files.

class Tree:
    def __init__(self, root: str, strip: str = ""):
        self.root = Path(root)
        self.strip = strip.strip("/") + "/" if strip.strip("/") else ""
        if not self.root.exists():
            raise SystemExit(f"handover-baseline: {root} does not exist")
        self.links: list[str] = []

    def _rel(self, name: str) -> str | None:
        name = name.replace("\\", "/")
        while name.startswith("./"):
            name = name[2:]
        if self.strip:
            if not name.startswith(self.strip):
                return None
            name = name[len(self.strip):]
        name = name.strip("/")
        if not name or ".." in PurePosixPath(name).parts:
            return None
        return name

    def files(self, patterns: list[str]):
        """Yield (path, read) for every regular file not excluded. Excluded files are never read, excluded
        folders are never entered, and links (symlinks and Windows junctions) are listed, not followed."""
        r = self.root
        self.skipped = 0
        if r.is_dir():
            for top, dirs, names in os.walk(r, followlinks=False):
                base = Path(top)
                keep = []
                for d in sorted(dirs):
                    rel = (base / d).relative_to(r).as_posix()
                    if is_link(base / d):
                        self.links.append(rel)
                    elif excluded(rel + "/", patterns):
                        self.skipped += 1
                    else:
                        keep.append(d)
                dirs[:] = keep
                for n in sorted(names):
                    p = base / n
                    rel = self._rel(p.relative_to(r).as_posix())
                    if rel is None:
                        continue
                    if is_link(p):
                        self.links.append(rel)
                    elif excluded(rel, patterns):
                        self.skipped += 1
                    elif p.is_file():
                        yield rel, p.read_bytes
        elif zipfile.is_zipfile(r):
            with zipfile.ZipFile(r) as z:
                for info in sorted(z.infolist(), key=lambda i: i.filename):
                    if info.is_dir():
                        continue
                    rel = self._rel(info.filename)
                    if rel is None:
                        continue
                    if (info.external_attr >> 16) & 0o170000 == 0o120000:
                        self.links.append(rel)
                    elif excluded(rel, patterns):
                        self.skipped += 1
                    else:
                        yield rel, (lambda i=info: z.read(i))
        elif tarfile.is_tarfile(r):
            with tarfile.open(r) as t:
                for m in sorted(t.getmembers(), key=lambda m: m.name):
                    rel = self._rel(m.name)
                    if rel is None:
                        continue
                    if m.issym() or m.islnk():
                        self.links.append(rel)
                    elif not m.isfile():
                        continue
                    elif excluded(rel, patterns):
                        self.skipped += 1
                    else:
                        yield rel, (lambda m=m: t.extractfile(m).read())
        else:
            raise SystemExit(f"handover-baseline: {r} is not a directory, a zip or a tar archive")


def header_field(text: str, field: str) -> str | None:
    m = re.search(r"^[ \t/*#@]*" + re.escape(field) + r":[ \t]*(.+)$", text, re.M | re.I)
    return m.group(1).strip().rstrip("*/").strip() if m else None


def scan(tree: Tree, patterns: list[str]):
    """Hash every kept file and collect component versions as the files go past."""
    files: dict[str, dict] = {}
    components = {"wordpress": None, "plugins": {}, "themes": {}}
    for rel, read in tree.files(patterns):
        data = read()
        files[rel] = {"sha256": sha256(data), "bytes": len(data)}
        parts = rel.split("/")
        if rel == "wp-includes/version.php":
            m = re.search(rb"\$wp_version\s*=\s*'([^']+)'", data)
            components["wordpress"] = m.group(1).decode() if m else None
        elif len(parts) == 4 and parts[:2] == ["wp-content", "plugins"] and parts[3].endswith(".php"):
            text = data[:HEADER_BYTES].decode("utf-8", "replace")
            name = header_field(text, "Plugin Name")
            if name:
                components["plugins"][parts[2]] = {"name": name, "version": header_field(text, "Version"),
                                                   "main_file": f"{parts[2]}/{parts[3]}"}
        elif len(parts) == 4 and parts[:2] == ["wp-content", "themes"] and parts[3] == "style.css":
            text = data[:HEADER_BYTES].decode("utf-8", "replace")
            if header_field(text, "Theme Name"):
                components["themes"][parts[2]] = {"name": header_field(text, "Theme Name"),
                                                  "version": header_field(text, "Version")}
    return files, components, tree.skipped


def area(path: str) -> str:
    parts = path.split("/")
    if parts[0] == "wp-content" and len(parts) > 2 and parts[1] in ("plugins", "themes"):
        return f"{parts[1][:-1]}: {parts[2]}"
    if parts[0] == "wp-content" and len(parts) > 1 and parts[1] == "mu-plugins":
        return "must-use plugins"
    if parts[0] == "wp-content" and len(parts) > 1 and parts[1] == "languages":
        return "language files"
    if parts[0] in ("wp-admin", "wp-includes") or (len(parts) == 1 and parts[0].endswith(".php")):
        return "WordPress core"
    return "other"


def canonical(doc: dict) -> bytes:
    return (json.dumps(doc, indent=1, sort_keys=True, ensure_ascii=False) + "\n").encode("utf-8")


# ---------------------------------------------------------------------------------------------------------------

def cmd_make(a) -> int:
    if not re.match(r"^v\d+\.\d+\.\d+$", a.tag):
        raise SystemExit(f"handover-baseline: {a.tag!r} is not a release tag: use vX.Y.Z")
    tree = Tree(a.root, a.strip)
    patterns = EXCLUDED + list(a.exclude or [])
    files, components, skipped = scan(tree, patterns)
    if not files:
        raise SystemExit("handover-baseline: no files found under --root; check the path and --strip")
    if "wp-includes/version.php" not in files:
        print("handover-baseline: warning: no wp-includes/version.php, is --root the WordPress root?",
              file=sys.stderr)

    release = None
    if a.release:
        rel_path = Path(a.release)
        blob = rel_path.read_bytes()
        mismatched, missing, count = [], [], 0
        with zipfile.ZipFile(io.BytesIO(blob)) as z:
            for info in z.infolist():
                if info.is_dir() or excluded(info.filename, patterns):
                    continue
                count += 1
                live = files.get(info.filename)
                if live is None:
                    missing.append(info.filename)
                elif live["sha256"] != sha256(z.read(info)):
                    mismatched.append(info.filename)
        if mismatched or missing:
            for p in missing[:20]:
                print(f"  missing from tree: {p}", file=sys.stderr)
            for p in mismatched[:20]:
                print(f"  differs from release: {p}", file=sys.stderr)
            raise SystemExit(f"handover-baseline: the tree is not the release: {len(missing)} missing, "
                             f"{len(mismatched)} different. Deploy the release, then make the baseline.")
        release = {"archive": rel_path.name, "sha256": sha256(blob), "files_checked": count}

    doc = {
        "name": "mizzey-handover-baseline",
        "schema": SCHEMA,
        "tag": a.tag,
        "commit": a.commit,
        "made_at": dt.datetime.now(dt.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
        "source": a.label or Path(a.root).name,
        "release": release,
        "components": components,
        "excluded": patterns,
        "links_not_hashed": sorted(tree.links),
        "file_count": len(files),
        "files": files,
    }
    out = Path(a.out)
    if out.exists() and not a.force:
        raise SystemExit(f"handover-baseline: {out} exists; a baseline is never overwritten (use --force "
                         "only for a draft that has not been signed)")
    body = canonical(doc)
    out.write_bytes(body)
    print(f"baseline  {out}")
    print(f"files     {len(files)} hashed, {skipped} excluded, {len(tree.links)} links listed")
    if release:
        print(f"release   {release['archive']} matches the tree ({release['files_checked']} files)")
    print(f"sha256    {sha256(body)}")
    print("Record the sha256 above in the Handover Baseline Record, and give the client a copy of the file.")
    return 0


def version_changes(old: dict, new: dict) -> list[str]:
    lines = []
    if old.get("wordpress") != new.get("wordpress"):
        lines.append(f"WordPress core: {old.get('wordpress')} -> {new.get('wordpress')}")
    for kind in ("plugins", "themes"):
        o, n = old.get(kind, {}), new.get(kind, {})
        label = kind[:-1]
        for slug in sorted(set(o) | set(n)):
            if slug not in n:
                lines.append(f"{label} {slug}: removed (was {o[slug].get('version')})")
            elif slug not in o:
                lines.append(f"{label} {slug}: added ({n[slug].get('version')})")
            elif o[slug].get("version") != n[slug].get("version"):
                lines.append(f"{label} {slug}: {o[slug].get('version')} -> {n[slug].get('version')}")
    return lines


def cmd_check(a) -> int:
    raw = Path(a.baseline).read_bytes()
    digest = sha256(raw)
    if a.expect and a.expect.strip().lower() != digest:
        print(f"The baseline file is NOT the signed one.\n  expected {a.expect.strip().lower()}\n"
              f"  actual   {digest}\nStop: compare against the copy the client holds.")
        return 2
    base = json.loads(raw.decode("utf-8"))
    if base.get("name") != "mizzey-handover-baseline":
        raise SystemExit("handover-baseline: that file is not a handover baseline")

    tree = Tree(a.root, a.strip)
    files, components, _ = scan(tree, base["excluded"])
    old = base["files"]
    changed = sorted(p for p in old.keys() & files.keys() if old[p]["sha256"] != files[p]["sha256"])
    added = sorted(files.keys() - old.keys())
    removed = sorted(old.keys() - files.keys())
    versions = version_changes(base["components"], components)

    groups: dict[str, dict[str, list[str]]] = {}
    for kind, paths in (("changed", changed), ("added", added), ("removed", removed)):
        for p in paths:
            groups.setdefault(area(p), {"changed": [], "added": [], "removed": []})[kind].append(p)

    now = dt.datetime.now(dt.timezone.utc).strftime("%Y-%m-%d %H:%M UTC")
    out = [
        f"# Comparison with handover baseline {base['tag']}",
        "",
        f"Baseline made {base['made_at']}, sha256 `{digest}`"
        + (" (matches the signed value)." if a.expect else " (not checked against a signed value)."),
        f"Compared {now} against `{a.label or Path(a.root).name}`.",
        "",
        f"**{len(changed)} changed, {len(added)} added, {len(removed)} removed**, out of "
        f"{base['file_count']} files handed over.",
        "",
    ]
    if versions:
        out += ["## Version changes", ""] + [f"- {v}" for v in versions] + [""]
    if groups:
        out += ["## By area", "", "| Area | Changed | Added | Removed |", "|---|---:|---:|---:|"]
        for name in sorted(groups):
            g = groups[name]
            out.append(f"| {name} | {len(g['changed'])} | {len(g['added'])} | {len(g['removed'])} |")
        out.append("")
        limit = a.limit
        for name in sorted(groups):
            out += [f"## {name}", ""]
            for kind in ("changed", "added", "removed"):
                paths = groups[name][kind]
                out += [f"- {kind}: `{p}`" for p in paths[:limit]]
                if len(paths) > limit:
                    out.append(f"- {kind}: and {len(paths) - limit} more")
            out.append("")
    else:
        out += ["Every file handed over is present and unchanged, and nothing was added.", ""]
    out += ["Not covered: excluded paths (listed in the baseline) and anything stored in the database.", ""]

    text = "\n".join(out)
    if a.report:
        Path(a.report).write_bytes(text.encode("utf-8"))
        print(f"report    {a.report}")
    print(text)
    return 1 if (changed or added or removed) else 0


def cmd_hash(a) -> int:
    print(sha256(Path(a.baseline).read_bytes()))
    return 0


def main(argv=None) -> int:
    p = argparse.ArgumentParser(description="Handover baseline: what was delivered, and what changed since.")
    sub = p.add_subparsers(dest="cmd", required=True)

    m = sub.add_parser("make", help="record the baseline at handover")
    m.add_argument("--root", required=True)
    m.add_argument("--tag", required=True, help="the release tag handed over, vX.Y.Z")
    m.add_argument("--commit", help="the commit the tag points to, recorded as given")
    m.add_argument("--release", help="the release archive; the tree must contain it unchanged")
    m.add_argument("--out", required=True)
    m.add_argument("--strip", default="")
    m.add_argument("--label", help="a name for the tree, for example 'production, 15 Dec 2026'")
    m.add_argument("--exclude", action="append", help="an extra pattern to leave out, repeatable")
    m.add_argument("--force", action="store_true")
    m.set_defaults(fn=cmd_make)

    c = sub.add_parser("check", help="compare a tree with a baseline")
    c.add_argument("--root", required=True)
    c.add_argument("--baseline", required=True)
    c.add_argument("--expect", help="the sha256 written in the signed Handover Baseline Record")
    c.add_argument("--strip", default="")
    c.add_argument("--label")
    c.add_argument("--report", help="also write the report to this file")
    c.add_argument("--limit", type=int, default=200, help="paths listed per area and kind")
    c.set_defaults(fn=cmd_check)

    h = sub.add_parser("hash", help="print a baseline file's sha256")
    h.add_argument("baseline")
    h.set_defaults(fn=cmd_hash)

    a = p.parse_args(argv)
    return a.fn(a)


if __name__ == "__main__":
    sys.exit(main())

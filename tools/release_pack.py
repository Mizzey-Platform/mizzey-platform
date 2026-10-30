"""Pack the release archive from the built WordPress tree (DECISIONS.md D-16).

    python tools/release_pack.py fetch-core [--into ../app/wp]
    python tools/release_pack.py pack --tag vX.Y.Z [--dist ../app/corex/dist] [--out ../app/release]

`tools/build-dist.mjs` builds the flat tree: WordPress core, CoreX at the commit `corex.lock` pins, and this
repository's plugin and theme. The CoreX builder packages nothing else, so `pack` adds what a running
store also needs, at the versions `stack.lock.json` records, and writes two files:

    mizzey-<tag>.zip           the WordPress root is the archive root, with no folder around it
    mizzey-<tag>.zip.sha256    one line, in the format `sha256sum -c` reads

What it adds. WooCommerce and the carrier plugin, from wordpress.org. The Arabic language packs of WordPress and
of WooCommerce, from wordpress.org. `mizzey-release.json`, the manifest.

What it leaves out of the built tree. The CoreX plugins and add-ons the store does not run (`COREX_CARRIED` names
the ones it does), so a site running the release cannot activate them. The manifest names them under
`plugins.left_out`, and `corex-release.json` is rewritten to describe the archive as shipped.

What it never adds. The translation plugin family is paid software, and this repository, its releases and its
workflow artifacts are public. The manifest lists those five plugins with their versions under
`plugins.server_supplied`, and the server puts them in at deploy time from a folder of its own and checks them
against that list. Nothing here downloads, reads or writes them.

What the archive deliberately lacks, because the server owns it: `wp-config.php`, `wp-content/uploads`, and
`wp-content/languages/wpml`, where the translation plugin writes the files it generates. The manifest names the
last two under `runtime_writable`, for the deploy to link to a writable place.

The built tree is read and never changed. Plugin and language archives are copied member by member into the
release archive, and a member that would land outside its own folder, or is a link, stops the build.

`fetch-core` exists for CI, where no WordPress is on disk: it downloads the WordPress version
`stack.lock.json` records as the tested runtime, checks it against the checksum wordpress.org publishes beside
it, and unpacks it where `tools/corex-sync.mjs` expects it.
"""

from __future__ import annotations

import argparse
import datetime as dt
import hashlib
import io
import json
import re
import stat
import subprocess
import sys
import tarfile
import urllib.request
import zipfile
from dataclasses import dataclass
from pathlib import Path, PurePosixPath
from typing import Callable, NamedTuple

ROOT = Path(__file__).resolve().parent.parent
APP = ROOT.parent / "app"

TAG_RE = re.compile(r"^v\d+\.\d+\.\d+$")
MANIFEST = "mizzey-release.json"
VENDOR = PurePosixPath("wp-content/vendor")
LOCALES = ("ar",)

# stack.lock.json component -> the plugin's folder and the file that carries its header.
BUNDLED = {
    "woocommerce": ("woocommerce", "woocommerce.php"),
    "bosta": ("bosta-woocommerce", "bosta-woocommerce.php"),
    "wp-2fa": ("wp-2fa", "wp-2fa.php"),
}
# Paid, so never in the archive while the repository is public. Listed in the manifest for the server to check.
SERVER_SUPPLIED = {
    "wpml": ("sitepress-multilingual-cms", "sitepress.php"),
    "wpml-string-translation": ("wpml-string-translation", "plugin.php"),
    "wpml-media-translation": ("wpml-media-translation", "plugin.php"),
    "wpml-cms-nav": ("wpml-cms-nav", "plugin.php"),
    "wcml": ("woocommerce-multilingual", "wpml-woocommerce.php"),
}
# The CoreX plugins the store runs: the same eight the staging tool activates. The CoreX builder packages every
# CoreX plugin and add-on; the others (bookings, careers, the site kits and the rest) are not part of this store,
# so they are left out of the release and cannot be activated on a site that runs it. A CoreX plugin that a later
# pin adds is left out too, until it is named here.
COREX_CARRIED = ("corex-blocks", "corex-config", "corex-core", "corex-email", "corex-forms", "corex-guides",
                 "corex-media", "corex-ui")
COREX_MANIFEST = "corex-release.json"
# Written at runtime, so absent from the archive and linked to a writable place by the deploy.
RUNTIME_WRITABLE = ("wp-content/uploads", "wp-content/languages/wpml")
# Never in a release, wherever they came from. Compared with each path segment, lower case.
FORBIDDEN_NAMES = {"wp-config.php", ".git", ".github", "node_modules", ".env", "debug.log"}
# A local web server's own file at the WordPress root. The builder copies it from a development runtime that has
# one, a release is served by a server that reads none, so it is left out and not refused.
LOCAL_STATE = {".htaccess"}
FORBIDDEN_SUFFIXES = (".sql", ".sql.gz", ".pem", ".key", ".p12", ".pfx")

PLUGIN_URL = "https://downloads.wordpress.org/plugin/{slug}.{version}.zip"
CORE_PACK_URL = "https://downloads.wordpress.org/translation/core/{version}/{locale}.zip"
PLUGIN_PACK_URL = "https://downloads.wordpress.org/translation/plugin/{slug}/{version}/{locale}.zip"
CORE_URL = "https://wordpress.org/wordpress-{version}.tar.gz"

SILENCE = b"<?php\n// Silence is golden.\n"

Fetch = Callable[[str], bytes]


class Refused(SystemExit):
    def __init__(self, why: str):
        super().__init__(f"release-pack: {why}")


def http_fetch(url: str) -> bytes:
    request = urllib.request.Request(url, headers={"User-Agent": "mizzey-release-pack"})
    with urllib.request.urlopen(request, timeout=300) as response:
        return response.read()


def sha256(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def read_lock(root: Path) -> dict:
    stack = json.loads((root / "stack.lock.json").read_text(encoding="utf-8"))["components"]
    corex = json.loads((root / "corex.lock").read_text(encoding="utf-8"))
    for name in (*BUNDLED, *SERVER_SUPPLIED):
        if not stack.get(name, {}).get("version"):
            raise Refused(f"stack.lock.json records no version for {name}")
    runtime = stack["wordpress"].get("observed_runtime") or stack["wordpress"]["version"]
    return {"stack": stack, "corex": corex, "wordpress": runtime}


# ---- reading the pieces -------------------------------------------------------------------------------------

def header_version(source: bytes) -> str | None:
    """The Version line of a WordPress plugin header, or None when the file is not a plugin's main file."""
    head = source[:8192].decode("utf-8", "replace")
    if "Plugin Name:" not in head:
        return None
    found = re.search(r"^[ \t/*#@]*Version:\s*(\S+)", head, flags=re.M | re.I)
    return found.group(1) if found else None


def safe_members(archive: zipfile.ZipFile, label: str) -> list[zipfile.ZipInfo]:
    """The files of an archive that came from outside, refusing anything that is not a plain file in place."""
    members = []
    for info in archive.infolist():
        name = info.filename
        if name.startswith("__MACOSX/") or info.is_dir():
            continue
        path = PurePosixPath(name)
        if "\\" in name or path.is_absolute() or ".." in path.parts or re.match(r"^[A-Za-z]:", name):
            raise Refused(f"{label}: member {name!r} would be written outside its folder")
        if stat.S_ISLNK(info.external_attr >> 16):
            raise Refused(f"{label}: member {name!r} is a link")
        members.append(info)
    return members


def plugin_files(data: bytes, slug: str, main_file: str, version: str) -> dict[str, bytes]:
    """The files of one plugin archive, keyed by their path in the release, after checking it is that plugin."""
    label = f"{slug} {version}"
    with zipfile.ZipFile(io.BytesIO(data)) as archive:
        members = safe_members(archive, label)
        stray = sorted({m.filename.split("/")[0] for m in members} - {slug})
        if stray or not members:
            raise Refused(f"{label}: the archive must hold one folder named {slug}, found {stray or 'nothing'}")
        files = {f"wp-content/plugins/{m.filename}": archive.read(m) for m in members}
    main = files.get(f"wp-content/plugins/{slug}/{main_file}")
    found = header_version(main) if main is not None else None
    if found != version:
        raise Refused(f"{label}: {slug}/{main_file} says version {found}, stack.lock.json says {version}")
    return files


def language_files(data: bytes, folder: str, label: str) -> dict[str, bytes]:
    """The files of one language pack. A pack is flat: its files sit at the top of the archive."""
    with zipfile.ZipFile(io.BytesIO(data)) as archive:
        members = safe_members(archive, label)
        if not members or any("/" in m.filename for m in members):
            raise Refused(f"{label}: a language pack holds files only, with no folder")
        return {f"{folder}/{m.filename}": archive.read(m) for m in members}


def tree_files(dist: Path) -> dict[str, Path]:
    """Every file of the built tree, by its path in the release. A link anywhere in it stops the build."""
    files = {}
    pending = [dist]
    while pending:
        here = pending.pop()
        for entry in sorted(here.iterdir()):
            if entry.is_symlink():
                raise Refused(f"the built tree holds a link: {entry.relative_to(dist).as_posix()}")
            if entry.is_dir():
                pending.append(entry)
            elif entry.is_file() and not (here == dist and entry.name in LOCAL_STATE):
                files[entry.relative_to(dist).as_posix()] = entry
    return files


def wordpress_version(dist: Path) -> str:
    source = (dist / "wp-includes" / "version.php").read_text(encoding="utf-8")
    return re.search(r"\$wp_version\s*=\s*'([^']+)'", source).group(1)


def check_tree(dist: Path, wordpress: str) -> dict:
    """The built tree is the one build-dist.mjs makes for this site, on the WordPress the lock records.

    Returns what it was built with: its plugins, its themes, and which CoreX vendor tree it carries.
    """
    release = dist / "corex-release.json"
    # Since CoreX v0.43.4 the builder installs the package's own vendor tree at wp-content/vendor/. A tree with
    # vendor/ at its root was built by an older builder and does not load CoreX on a host.
    for needed in (release, dist / "wp-includes" / "version.php", dist / "wp-content" / "plugins" / "mizzey-site",
                   dist / "wp-content" / "themes" / "mizzey-theme", dist / VENDOR / "autoload.php"):
        if not needed.exists():
            raise Refused(f"{dist} is not a built tree: {needed.relative_to(dist).as_posix()} is missing. "
                          "Run node tools/build-dist.mjs first")
    found = wordpress_version(dist)
    if found != wordpress:
        raise Refused(f"the built tree holds WordPress {found}, stack.lock.json records {wordpress} as tested")
    built = json.loads(release.read_text(encoding="utf-8"))
    plugins = sorted(built.get("plugins", []))
    missing = [name for name in COREX_CARRIED if name not in plugins or not (dist / "wp-content" / "plugins" / name).is_dir()]
    if missing:
        raise Refused(f"the built tree lacks the CoreX plugins the store runs: {', '.join(missing)}")
    # The builder installs production packages only. Recorded rather than assumed, so a tree that carries the test
    # toolchain says so in the manifest.
    vendor = "dev" if (dist / VENDOR / "pestphp").exists() else "production"
    return {"plugins": plugins, "themes": sorted(built.get("themes", [])), "vendor": vendor,
            "left_out": [name for name in plugins if name.startswith("corex-") and name not in COREX_CARRIED]}


def refuse_forbidden(paths) -> None:
    for path in paths:
        parts = [p.lower() for p in path.split("/")]
        low = path.lower()
        if FORBIDDEN_NAMES & set(parts) or low.endswith(FORBIDDEN_SUFFIXES):
            raise Refused(f"{path} must never be in a release")
        if any(low == w or low.startswith(w + "/") for w in RUNTIME_WRITABLE):
            raise Refused(f"{path} is written at runtime and belongs to the server, not to a release")
        if any(low.startswith(f"wp-content/plugins/{slug}/") for slug, _ in SERVER_SUPPLIED.values()):
            raise Refused(f"{path} is paid software and must not be in a release of a public repository")


# ---- pack ---------------------------------------------------------------------------------------------------

class Build(NamedTuple):
    """Which build this is: its tag, the commit the tag points at, and that commit's time."""
    tag: str
    commit: str
    moment: dt.datetime


@dataclass(frozen=True)
class Origin:
    """What a build reads besides the built tree: the repository, the network, and the commit it is a build of.

    The defaults are the real ones. A test names its own, so nothing in a test touches the network or git.
    """
    root: Path = ROOT
    fetch: Fetch = http_fetch
    cache: Path | None = None
    commit: str | None = None
    moment: dt.datetime | None = None

    def download(self, url: str) -> bytes:
        """What the address serves, kept in the cache folder when there is one so a second build asks for nothing."""
        if self.cache is None:
            return self.fetch(url)
        held = self.cache / re.sub(r"[^A-Za-z0-9.-]+", "_", url.split("://", 1)[1])
        if not held.exists():
            self.cache.mkdir(parents=True, exist_ok=True)
            held.write_bytes(self.fetch(url))
        return held.read_bytes()

    def build(self, tag: str) -> Build:
        commit = self.commit or git(self.root, "rev-parse", "HEAD")
        moment = self.moment or dt.datetime.fromtimestamp(
            int(git(self.root, "log", "-1", "--format=%ct", commit)), dt.timezone.utc)
        return Build(tag, commit, moment)


class Contents:
    """What the archive will hold: the built tree's files, read from disk when they are written, what is added
    to them in memory, and a record of each plugin and language pack that was added."""

    def __init__(self, on_disk: dict[str, Path]):
        self.on_disk = on_disk
        self.added: dict[str, bytes] = {}
        self.plugins: list[dict] = []
        self.packs: list[dict] = []

    def add(self, files: dict[str, bytes], what: str) -> None:
        clash = sorted((set(files) & set(self.on_disk)) | (set(files) & set(self.added)))
        if clash:
            raise Refused(f"{what} would overwrite {clash[0]} and {len(clash) - 1} more")
        self.added.update(files)

    def names(self) -> list[str]:
        return sorted(set(self.on_disk) | set(self.added))

    def read(self, name: str) -> bytes:
        return self.added[name] if name in self.added else self.on_disk[name].read_bytes()


def add_plugins(contents: Contents, lock: dict, origin: Origin) -> None:
    for component, (slug, main_file) in BUNDLED.items():
        version = lock["stack"][component]["version"]
        url = PLUGIN_URL.format(slug=slug, version=version)
        data = origin.download(url)
        contents.add(plugin_files(data, slug, main_file, version), f"{slug} {version}")
        contents.plugins.append({"slug": slug, "version": version, "main_file": f"{slug}/{main_file}",
                                 "source": url, "sha256": sha256(data)})


def language_packs(lock: dict, locale: str) -> tuple:
    """The packs of one language: what each is called, the folder it unpacks into, and its address."""
    wordpress, woocommerce = lock["wordpress"], lock["stack"]["woocommerce"]["version"]
    return (
        (f"WordPress {wordpress} {locale}", "wp-content/languages",
         CORE_PACK_URL.format(version=wordpress, locale=locale)),
        (f"WooCommerce {woocommerce} {locale}", "wp-content/languages/plugins",
         PLUGIN_PACK_URL.format(slug="woocommerce", version=woocommerce, locale=locale)),
    )


def add_language_packs(contents: Contents, lock: dict, origin: Origin) -> None:
    for locale in LOCALES:
        for label, folder, url in language_packs(lock, locale):
            data = origin.download(url)
            contents.add(language_files(data, folder, label), label)
            contents.packs.append({"pack": label, "source": url, "sha256": sha256(data)})


def leave_out(contents: Contents, left_out: list[str]) -> None:
    """Drop the CoreX plugins the store does not run, and keep CoreX's own description of the package true.

    `corex-release.json` lists every folder the package holds, what each holds, and where each namespace loads
    from. The entries of the folders left out are removed from it, so it describes the archive as shipped. The
    Composer autoloader keeps its entries for them. CoreX loads an add-on's classes only when the add-on is
    installed and active, and no code kept in the archive asks for them otherwise (checked on CoreX v0.47.0).
    """
    if not left_out:
        return
    folders = tuple(f"wp-content/plugins/{name}" for name in left_out)
    for name in [n for n in contents.on_disk if n.startswith(tuple(f + "/" for f in folders))]:
        del contents.on_disk[name]
    described = json.loads(contents.on_disk.pop(COREX_MANIFEST).read_text(encoding="utf-8"))
    described["plugins"] = [p for p in described.get("plugins", []) if p not in left_out]
    if "release_paths" in described:
        described["release_paths"] = [p for p in described["release_paths"] if p not in folders]
    if isinstance(described.get("contents"), dict):
        described["contents"] = {p: v for p, v in described["contents"].items() if p not in folders}
    psr4 = (described.get("autoload") or {}).get("psr4")
    if isinstance(psr4, dict):
        described["autoload"]["psr4"] = {ns: path for ns, path in psr4.items()
                                         if not str(path).startswith(tuple(f + "/" for f in folders))}
    contents.added[COREX_MANIFEST] = (json.dumps(described, indent=2) + "\n").encode("utf-8")


def assemble(dist: Path, lock: dict, origin: Origin, left_out: list[str] | None = None) -> Contents:
    """Everything the archive will hold, except its manifest."""
    contents = Contents(tree_files(dist))
    leave_out(contents, left_out or [])
    add_plugins(contents, lock, origin)
    add_language_packs(contents, lock, origin)
    for guard in ("wp-content/index.php", "wp-content/plugins/index.php", "wp-content/themes/index.php"):
        if guard not in contents.on_disk:
            contents.added[guard] = SILENCE
    refuse_forbidden([*contents.on_disk, *contents.added])
    return contents


def build_manifest(build: Build, lock: dict, built: dict, contents: Contents) -> dict:
    stack, corex = lock["stack"], lock["corex"]
    return {
        "name": "mizzey-release",
        "schema": 1,
        "tag": build.tag,
        "commit": build.commit,
        "built_at": build.moment.strftime("%Y-%m-%dT%H:%M:%SZ"),
        "wordpress": lock["wordpress"],
        "php": corex.get("php"),
        "corex": {"version": corex["version"], "commit": corex["commit"], "vendor": built["vendor"]},
        "plugins": {
            "built": [name for name in built["plugins"] if name not in built["left_out"]],
            "left_out": built["left_out"],
            "bundled": contents.plugins,
            "server_supplied": [
                {"slug": slug, "version": stack[component]["version"], "main_file": f"{slug}/{main_file}"}
                for component, (slug, main_file) in SERVER_SUPPLIED.items()],
        },
        "themes": built["themes"],
        "language_packs": contents.packs,
        "runtime_writable": list(RUNTIME_WRITABLE),
        "not_in_this_archive": ["wp-config.php", *RUNTIME_WRITABLE,
                                *(f"wp-content/plugins/{slug}" for slug, _ in SERVER_SUPPLIED.values())],
    }


def write_archive(target: Path, contents: Contents, moment: dt.datetime) -> int:
    """One archive, entries in path order, every file 0644 and stamped with the commit's time."""
    stamp = (max(moment.year, 1980), moment.month, moment.day, moment.hour, moment.minute, moment.second)
    names = contents.names()
    with zipfile.ZipFile(target, "w", zipfile.ZIP_DEFLATED, compresslevel=6) as archive:
        for name in names:
            info = zipfile.ZipInfo(name, date_time=stamp)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = (stat.S_IFREG | 0o644) << 16
            archive.writestr(info, contents.read(name))
    return len(names)


def git(root: Path, *args: str) -> str:
    return subprocess.run(["git", "-C", str(root), *args], capture_output=True, text=True, check=True).stdout.strip()


def pack(tag: str, dist: Path, out: Path, origin: Origin | None = None) -> dict:
    origin = origin or Origin()
    if not TAG_RE.match(tag):
        raise Refused(f"{tag!r} is not a release tag: use vX.Y.Z")
    lock = read_lock(origin.root)
    built = check_tree(dist, lock["wordpress"])
    build = origin.build(tag)
    contents = assemble(dist, lock, origin, built["left_out"])
    manifest = build_manifest(build, lock, built, contents)
    contents.added[MANIFEST] = (json.dumps(manifest, indent=1) + "\n").encode("utf-8")

    out.mkdir(parents=True, exist_ok=True)
    archive = out / f"mizzey-{tag}.zip"
    count = write_archive(archive, contents, build.moment)
    digest = sha256(archive.read_bytes())
    checksum = out / f"{archive.name}.sha256"
    checksum.write_text(f"{digest}  {archive.name}\n", encoding="utf-8", newline="\n")
    return {"archive": str(archive), "checksum": str(checksum), "sha256": digest, "files": count,
            "bytes": archive.stat().st_size, "manifest": manifest}


# ---- fetch-core ---------------------------------------------------------------------------------------------

def fetch_core(into: Path, origin: Origin | None = None) -> str:
    """WordPress core, at the tested version, checked against the checksum published beside it."""
    origin = origin or Origin()
    fetch = origin.fetch
    version = read_lock(origin.root)["wordpress"]
    if into.exists() and any(into.iterdir()):
        raise Refused(f"{into} is not empty: fetch-core only fills an empty place")
    url = CORE_URL.format(version=version)
    data, expected = fetch(url), fetch(url + ".sha1").decode("ascii", "replace").strip()
    if hashlib.sha1(data).hexdigest() != expected:
        raise Refused(f"{url} does not match the checksum wordpress.org publishes for it")
    unpack_core(data, into, url)
    return version


def unpack_core(data: bytes, into: Path, url: str) -> None:
    """Unpack the WordPress archive without the `wordpress/` folder it wraps everything in."""
    into.mkdir(parents=True, exist_ok=True)
    base = into.resolve()
    with tarfile.open(fileobj=io.BytesIO(data), mode="r:gz") as tar:
        for member in tar.getmembers():
            parts = PurePosixPath(member.name).parts
            if not parts or parts[0] != "wordpress" or ".." in parts or member.issym() or member.islnk():
                raise Refused(f"{url}: unexpected member {member.name!r}")
            target = base.joinpath(*parts[1:])
            if member.isdir():
                target.mkdir(parents=True, exist_ok=True)
            elif member.isfile():
                target.parent.mkdir(parents=True, exist_ok=True)
                target.write_bytes(tar.extractfile(member).read())


def main(argv: list[str] | None = None) -> int:
    ap = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    sub = ap.add_subparsers(dest="command", required=True)
    core = sub.add_parser("fetch-core")
    core.add_argument("--into", type=Path, default=APP / "wp")
    p = sub.add_parser("pack")
    p.add_argument("--tag", required=True)
    p.add_argument("--dist", type=Path, default=APP / "corex" / "dist")
    p.add_argument("--out", type=Path, default=APP / "release")
    p.add_argument("--cache", type=Path, help="keep what is downloaded here, and read it from here next time")
    a = ap.parse_args(argv)
    if a.command == "fetch-core":
        print(f"WordPress {fetch_core(a.into)} unpacked into {a.into}")
        return 0
    made = pack(a.tag, a.dist, a.out, Origin(cache=a.cache))
    print(json.dumps({k: v for k, v in made.items() if k != "manifest"}, indent=1))
    print(json.dumps(made["manifest"], indent=1))
    return 0


if __name__ == "__main__":
    sys.exit(main())

"""Pack the release archive from the built WordPress tree (DECISIONS.md D-16).

    python tools/release_pack.py fetch-core [--into ../app/wp]
    python tools/release_pack.py pack --tag vX.Y.Z [--dist ../app/corex/dist] [--out ../app/release]

`tools/build-dist.mjs` builds the flat tree: WordPress core, CoreX at the commit `corex.lock` pins, and this
repository's plugin and theme. The CoreX v0.42.0 builder packages nothing else, so `pack` adds what a running
store also needs, at the versions `stack.lock.json` records, and writes two files:

    mizzey-<tag>.zip           the WordPress root is the archive root, with no folder around it
    mizzey-<tag>.zip.sha256    one line, in the format `sha256sum -c` reads

What it adds. WooCommerce and the carrier plugin, from wordpress.org. The Arabic language packs of WordPress and
of WooCommerce, from wordpress.org. `mizzey-release.json`, the manifest.

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
from pathlib import Path, PurePosixPath
from typing import Callable

ROOT = Path(__file__).resolve().parent.parent
APP = ROOT.parent / "app"

TAG_RE = re.compile(r"^v\d+\.\d+\.\d+$")
MANIFEST = "mizzey-release.json"
LOCALES = ("ar",)

# stack.lock.json component -> the plugin's folder and the file that carries its header.
BUNDLED = {
    "woocommerce": ("woocommerce", "woocommerce.php"),
    "bosta": ("bosta-woocommerce", "bosta-woocommerce.php"),
}
# Paid, so never in the archive while the repository is public. Listed in the manifest for the server to check.
SERVER_SUPPLIED = {
    "wpml": ("sitepress-multilingual-cms", "sitepress.php"),
    "wpml-string-translation": ("wpml-string-translation", "plugin.php"),
    "wpml-media-translation": ("wpml-media-translation", "plugin.php"),
    "wpml-cms-nav": ("wpml-cms-nav", "plugin.php"),
    "wcml": ("woocommerce-multilingual", "wpml-woocommerce.php"),
}
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
    """The built tree is the one build-dist.mjs makes for this site, on the WordPress the lock records."""
    release = dist / "corex-release.json"
    for needed in (release, dist / "wp-includes" / "version.php", dist / "wp-content" / "plugins" / "mizzey-site",
                   dist / "wp-content" / "themes" / "mizzey-theme", dist / "vendor" / "autoload.php"):
        if not needed.exists():
            raise Refused(f"{dist} is not a built tree: {needed.relative_to(dist).as_posix()} is missing. "
                          "Run node tools/build-dist.mjs first")
    found = wordpress_version(dist)
    if found != wordpress:
        raise Refused(f"the built tree holds WordPress {found}, stack.lock.json records {wordpress} as tested")
    return json.loads(release.read_text(encoding="utf-8"))


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

def cached(fetch: Fetch, cache: Path | None, url: str) -> bytes:
    if cache is None:
        return fetch(url)
    held = cache / re.sub(r"[^A-Za-z0-9.-]+", "_", url.split("://", 1)[1])
    if not held.exists():
        cache.mkdir(parents=True, exist_ok=True)
        held.write_bytes(fetch(url))
    return held.read_bytes()


def assemble(dist: Path, lock: dict, fetch: Fetch, cache: Path | None = None) -> tuple[dict, dict, list, list]:
    """Everything the archive will hold. Returns (files from disk, files in memory, plugin records, packs)."""
    on_disk = tree_files(dist)
    added: dict[str, bytes] = {}
    plugins, packs = [], []

    def add(files: dict[str, bytes], what: str) -> None:
        clash = sorted((set(files) & set(on_disk)) | (set(files) & set(added)))
        if clash:
            raise Refused(f"{what} would overwrite {clash[0]} and {len(clash) - 1} more")
        added.update(files)

    for component, (slug, main_file) in BUNDLED.items():
        version = lock["stack"][component]["version"]
        url = PLUGIN_URL.format(slug=slug, version=version)
        data = cached(fetch, cache, url)
        add(plugin_files(data, slug, main_file, version), f"{slug} {version}")
        plugins.append({"slug": slug, "version": version, "main_file": f"{slug}/{main_file}",
                        "source": url, "sha256": sha256(data)})

    woocommerce = lock["stack"]["woocommerce"]["version"]
    for locale in LOCALES:
        for label, folder, url in (
            (f"WordPress {lock['wordpress']} {locale}", "wp-content/languages",
             CORE_PACK_URL.format(version=lock["wordpress"], locale=locale)),
            (f"WooCommerce {woocommerce} {locale}", "wp-content/languages/plugins",
             PLUGIN_PACK_URL.format(slug="woocommerce", version=woocommerce, locale=locale)),
        ):
            data = cached(fetch, cache, url)
            add(language_files(data, folder, label), label)
            packs.append({"pack": label, "source": url, "sha256": sha256(data)})

    for guard in ("wp-content/index.php", "wp-content/plugins/index.php", "wp-content/themes/index.php"):
        if guard not in on_disk:
            added[guard] = SILENCE
    refuse_forbidden([*on_disk, *added])
    return on_disk, added, plugins, packs


def build_manifest(tag: str, commit: str, built_at: str, lock: dict, corex_release: dict, dist: Path,
                   plugins: list, packs: list) -> dict:
    stack, corex = lock["stack"], lock["corex"]
    return {
        "name": "mizzey-release",
        "schema": 1,
        "tag": tag,
        "commit": commit,
        "built_at": built_at,
        "wordpress": lock["wordpress"],
        "php": corex.get("php"),
        "corex": {"version": corex["version"], "commit": corex["commit"],
                  # The builder copies CoreX's vendor tree as it finds it: with the test toolchain, or without it
                  # and without seven CLI commands (corex#201, COREX-WORKAROUNDS.md).
                  "vendor": "dev" if (dist / "vendor" / "pestphp").exists() else "production"},
        "plugins": {
            "built": sorted(corex_release.get("plugins", [])),
            "bundled": plugins,
            "server_supplied": [
                {"slug": slug, "version": stack[component]["version"], "main_file": f"{slug}/{main_file}"}
                for component, (slug, main_file) in SERVER_SUPPLIED.items()],
        },
        "themes": sorted(corex_release.get("themes", [])),
        "language_packs": packs,
        "runtime_writable": list(RUNTIME_WRITABLE),
        "not_in_this_archive": ["wp-config.php", *RUNTIME_WRITABLE,
                                *(f"wp-content/plugins/{slug}" for slug, _ in SERVER_SUPPLIED.values())],
    }


def write_archive(target: Path, on_disk: dict[str, Path], in_memory: dict[str, bytes], moment: dt.datetime) -> int:
    """One archive, entries in path order, every file 0644 and stamped with the commit's time."""
    stamp = (max(moment.year, 1980), moment.month, moment.day, moment.hour, moment.minute, moment.second)
    names = sorted(set(on_disk) | set(in_memory))
    with zipfile.ZipFile(target, "w", zipfile.ZIP_DEFLATED, compresslevel=6) as archive:
        for name in names:
            info = zipfile.ZipInfo(name, date_time=stamp)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = (stat.S_IFREG | 0o644) << 16
            archive.writestr(info, in_memory[name] if name in in_memory else on_disk[name].read_bytes())
    return len(names)


def git(root: Path, *args: str) -> str:
    return subprocess.run(["git", "-C", str(root), *args], capture_output=True, text=True, check=True).stdout.strip()


def pack(tag: str, dist: Path, out: Path, root: Path = ROOT, fetch: Fetch = http_fetch, cache: Path | None = None,
         commit: str | None = None, moment: dt.datetime | None = None) -> dict:
    if not TAG_RE.match(tag):
        raise Refused(f"{tag!r} is not a release tag: use vX.Y.Z")
    lock = read_lock(root)
    corex_release = check_tree(dist, lock["wordpress"])
    commit = commit or git(root, "rev-parse", "HEAD")
    moment = moment or dt.datetime.fromtimestamp(int(git(root, "log", "-1", "--format=%ct", commit)), dt.timezone.utc)
    on_disk, added, plugins, packs = assemble(dist, lock, fetch, cache)
    manifest = build_manifest(tag, commit, moment.strftime("%Y-%m-%dT%H:%M:%SZ"), lock, corex_release, dist,
                              plugins, packs)
    added[MANIFEST] = (json.dumps(manifest, indent=1) + "\n").encode("utf-8")

    out.mkdir(parents=True, exist_ok=True)
    archive = out / f"mizzey-{tag}.zip"
    count = write_archive(archive, on_disk, added, moment)
    digest = sha256(archive.read_bytes())
    checksum = out / f"{archive.name}.sha256"
    checksum.write_text(f"{digest}  {archive.name}\n", encoding="utf-8", newline="\n")
    return {"archive": str(archive), "checksum": str(checksum), "sha256": digest, "files": count,
            "bytes": archive.stat().st_size, "manifest": manifest}


# ---- fetch-core ---------------------------------------------------------------------------------------------

def fetch_core(into: Path, root: Path = ROOT, fetch: Fetch = http_fetch) -> str:
    """WordPress core, at the tested version, checked against the checksum published beside it."""
    version = read_lock(root)["wordpress"]
    if into.exists() and any(into.iterdir()):
        raise Refused(f"{into} is not empty: fetch-core only fills an empty place")
    url = CORE_URL.format(version=version)
    data, expected = fetch(url), fetch(url + ".sha1").decode("ascii", "replace").strip()
    if hashlib.sha1(data).hexdigest() != expected:
        raise Refused(f"{url} does not match the checksum wordpress.org publishes for it")
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
    return version


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
    made = pack(a.tag, a.dist, a.out, cache=a.cache)
    print(json.dumps({k: v for k, v in made.items() if k != "manifest"}, indent=1))
    print(json.dumps(made["manifest"], indent=1))
    return 0


if __name__ == "__main__":
    sys.exit(main())

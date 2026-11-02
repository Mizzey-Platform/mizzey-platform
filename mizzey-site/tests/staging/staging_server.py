"""The staging and demonstration copy on the developer's server (DECISIONS.md D-16).

`staging.py` runs this module when its profile is `server`, which it is on anything but Windows. The commands
keep their names and their meaning; what differs is where things are, and who owns them:

                    the copy on the Windows machine     the copy on the server
    code            an export of a commit, copied       a tagged release, unpacked by the server's deploy and
                                                        read-only to PHP
    PHP, WP-CLI     the machine's own                   inside the site's PHP container, through `docker exec`
    web server      its own Apache process              the server's, which this tool never starts or stops
    database        MySQL 8.3, created by `db-create`   MariaDB 11.4, created by the server with the site
    configuration   written by `build`                  the server's own wp-config.php
    staging plugin  copied into the tree                in a folder beside the releases, read-only to PHP
    sign-in gate    the web server, for tunnel traffic  Cloudflare Access, in front of the server

    build           check the deployed release, and install the staging layer beside it
    reset           DESTRUCTIVE for staging only: reinstall, apply the baseline, load synthetic data
    seed            DESTRUCTIVE for staging only: load the synthetic data again
    parity          compare the WPML settings of this copy with development's, carried in a file
    backup          database and uploads into a dated, checksummed backup set
    restore <set>   DESTRUCTIVE for staging only: put a backup set back
    restore-test    back up, destroy, restore, and compare fingerprints. Writes the evidence file
    check           show that the marker, the mail capture and the block on outside requests are in force
    status          what is deployed, and whether it answers
    server-config   print the wp-config.php this tool expects, with new keys, for the server to install

Three places, and only the first is the server's:

    /srv/hosting/sites/mizzeystaging/current        the release. Never written here
    /srv/hosting/sites/mizzeystaging/staging-layer  the staging plugin and the scripts WP-CLI runs. Written by
                                                    this tool as the host user, and read-only inside the container
    ~/mizzey-staging                                credentials, backups, evidence. Never visible to PHP

No password is ever an argument. The database client runs inside the container and reads the password from the
container's own environment, and a generated password reaches PHP through the environment of one `docker exec`.
"""

from __future__ import annotations

import argparse
import base64
import datetime as dt
import gzip
import hashlib
import http.client
import json
import os
import re
import secrets
import shutil
import subprocess
import sys
import tarfile
import time
import urllib.parse
from pathlib import Path, PurePosixPath

import staging as local

HERE = Path(__file__).resolve().parent
REPO = HERE.parents[2]
BASELINE = HERE.parent / "integration" / "baseline"

SITE = "mizzeystaging"
CONTAINER = f"{SITE}-php"
HOST_SITE = Path("/srv/hosting/sites") / SITE
IN_SITE = f"/srv/sites/{SITE}"
LAYER = HOST_SITE / "staging-layer"
IN_LAYER = f"{IN_SITE}/staging-layer"
IN_SHARED = f"{IN_SITE}/shared"
IN_UPLOADS = f"{IN_SHARED}/uploads"
STATE = Path.home() / "mizzey-staging"

DB_NAME = DB_USER = SITE
DB_HOST = "mariadb"
TABLE_PREFIX = "mzs_"
PUBLIC_HOST = "staging.mizzey.com"
LOCAL_HOST = "mizzey-staging.localhost"
PUBLIC_URL = f"https://{PUBLIC_HOST}"
LOCAL_URL = f"http://{LOCAL_HOST}"
MANIFEST = "mizzey-release.json"

# What the container must be able to read to run a reset, by its place under the layer.
LAYER_SOURCES = {
    "staging": (HERE, ("fingerprint.php", "wpml-remote-config.php", "server-admin-visit.php", "server-probe.php")),
    "staging/seed": (HERE / "seed", None),
    "staging/mu-plugins": (HERE / "mu-plugins", ("mizzey-staging.php",)),
    "integration/baseline": (BASELINE, ("setup.php", "ia-endpoints.php", "wpml-settings.php")),
}
SEED_SCRIPTS = ("10-users.php", "15-store-settings.php", "20-catalogue-sources.php",
                "30-catalogue-translations.php", "40-orders.php", "50-reports.php")
PARITY_FILES = ("wpml-remote-config.json", "wpml-development-settings.json")
BOSTA_DISTRICTS = "bosta-districts.json"
NOT_HERE = {
    "db-create": "the server creates the database and its account when it creates the site",
    "up": "the server's own stack serves staging; this tool does not start it",
    "down": "the server's own stack serves staging; this tool does not stop it",
    "tunnel-config": "the address is fixed in the server's configuration and closed by Cloudflare Access",
    "parity-export": "the export is made on the development machine",
}
# The database client inside the container. The password comes from the container's environment, never from here.
DB_SHELL = ('tool=$(command -v "$1" || command -v "$2") || { echo "no database client in the container" >&2; '
            'exit 127; }; shift 2; MYSQL_PWD="$DB_PASSWORD" exec "$tool" -h"$DB_HOST" -u"$DB_USER" "$@"')
DB_CLIENT = ("mariadb", "mysql")
DB_DUMP = ("mariadb-dump", "mysqldump")

Refused = local.Refused
say = local.say
run = subprocess.run


# ---- the container ------------------------------------------------------------------------------------------

def docker(*args: str, stdin=None, data: bytes | None = None, stdout=subprocess.PIPE, pass_env: tuple = (),
           check: bool = True):
    """One command inside the PHP container. Its input is an open file (`stdin`) or bytes (`data`). `pass_env`
    names variables to hand over, by name and never by value."""
    fed = stdin is not None or data is not None
    command = ["docker", "exec", *(["-i"] if fed else []),
               *[part for name in pass_env for part in ("-e", name)], CONTAINER, *[str(a) for a in args]]
    proc = run(command, stdin=stdin, input=data, stdout=stdout, stderr=subprocess.PIPE, check=False)
    if check and proc.returncode:
        raise SystemExit(f"{' '.join(command[:8])} ... failed ({proc.returncode}):\n{text(proc.stderr)[-2000:]}")
    return proc


def text(data) -> str:
    return (data or b"").decode("utf-8", "replace") if isinstance(data, (bytes, bytearray)) else str(data or "")


def wp(*args: str, check: bool = True, themes: bool = False, url: str | None = None, pass_env: tuple = ()) -> str:
    flags = [f"--path={IN_SITE}/current", *([] if themes else ["--skip-themes"]), *([f"--url={url}"] if url else [])]
    proc = docker("wp", *flags, *args, pass_env=pass_env, check=False)
    if check and proc.returncode:
        raise SystemExit(f"wp {' '.join(args)} failed ({proc.returncode}):\n{text(proc.stderr)[-2000:]}\n"
                         f"{text(proc.stdout)[-800:]}")
    return text(proc.stdout).strip()


def last_json(output: str):
    return json.loads(output.splitlines()[-1])


def in_layer(*parts: str) -> str:
    return "/".join((IN_LAYER, *parts))


# ---- guards -------------------------------------------------------------------------------------------------

def current_release() -> Path:
    """The deployed release. It must be one of this site's releases, made by the release pipeline."""
    current = (HOST_SITE / "current").resolve()
    if (HOST_SITE / "releases").resolve() not in current.parents:
        raise Refused(f"{HOST_SITE / 'current'} does not point into {HOST_SITE / 'releases'}")
    if not (current / MANIFEST).is_file():
        raise Refused(f"{current} holds no {MANIFEST}: it is not a release made by the release pipeline")
    return current


def release_manifest() -> dict:
    return json.loads((current_release() / MANIFEST).read_text(encoding="utf-8"))


def config_facts() -> dict:
    """Read from the running wp-config.php, which works before WordPress is installed and after a reset."""
    return {row["name"]: row["value"] for row in json.loads(wp("config", "list", "--format=json", "--skip-plugins"))}


def config_problems(facts: dict) -> list[str]:
    """Everything about the server's configuration that is not the staging copy this tool was written for."""
    expected = {"DB_NAME": DB_NAME, "DB_USER": DB_USER, "DB_HOST": DB_HOST, "table_prefix": TABLE_PREFIX,
                "WP_ENVIRONMENT_TYPE": "staging", "MIZZEY_STAGING_ROOT": IN_SHARED,
                "WPMU_PLUGIN_DIR": in_layer("staging", "mu-plugins")}
    found = [f"{name} is {facts.get(name)!r}, expected {value!r}" for name, value in expected.items()
             if facts.get(name) != value]
    for name in ("MIZZEY_STAGING", "WP_HTTP_BLOCK_EXTERNAL", "DISALLOW_FILE_MODS"):
        if str(facts.get(name)).lower() not in ("1", "true"):
            found.append(f"{name} is not switched on")
    if urllib.parse.urlsplit(str(facts.get("WP_HOME"))).netloc not in (PUBLIC_HOST, LOCAL_HOST):
        found.append(f"WP_HOME is {facts.get('WP_HOME')!r}, which is not a staging address")
    return found


def guard_tree() -> None:
    current_release()
    site = text(docker("printenv", "SITE_NAME", check=False).stdout).strip()
    if site != SITE:
        raise Refused(f"the container {CONTAINER} says its site is {site!r}, not {SITE!r}")


def guard_destructive() -> None:
    """Checked against the running configuration, not against what this file expects it to be."""
    guard_tree()
    if os.environ.get("MIZZEY_CONFIRM_STAGING") != "yes":
        raise Refused("set MIZZEY_CONFIRM_STAGING=yes")
    problems = config_problems(config_facts())
    if problems:
        raise Refused("this is not the staging copy: " + "; ".join(problems))


# ---- build --------------------------------------------------------------------------------------------------

def header_version(main_file: Path) -> str | None:
    if not main_file.is_file():
        return None
    with open(main_file, encoding="utf-8", errors="replace") as source:
        found = re.search(r"^[ \t/*#@]*Version:\s*(\S+)", source.read(8192), flags=re.M | re.I)
    return found.group(1) if found else None


def supplied_plugin_problems(release: Path, manifest: dict) -> list[str]:
    """The plugins the release lists and does not carry: each must be in place, at the version it lists."""
    found = []
    for plugin in manifest["plugins"]["server_supplied"]:
        relative = PurePosixPath(plugin["main_file"])
        if relative.is_absolute() or ".." in relative.parts:
            found.append(f"{plugin['slug']}: the manifest names {plugin['main_file']!r}, which is not a plugin file")
            continue
        version = header_version(release / "wp-content" / "plugins" / relative)
        if version != plugin["version"]:
            found.append(f"{plugin['slug']}: the release lists {plugin['version']}, the server has "
                         f"{version or 'no copy'}")
    return found


def writable_problems(manifest: dict) -> list[str]:
    """PHP must be able to write where the release says it writes, and nowhere in the release itself."""
    found = [f"{path} is not writable by PHP: link it to a folder under shared/"
             for path in manifest["runtime_writable"]
             if docker("test", "-w", f"{IN_SITE}/current/{path}", check=False).returncode]
    if docker("test", "-w", f"{IN_SITE}/current/index.php", check=False).returncode == 0:
        found.append("the release itself is writable by PHP: it is meant to be read-only")
    return found


def layer_files() -> dict[str, Path]:
    files = {}
    for place, (source, names) in LAYER_SOURCES.items():
        for name in names if names is not None else sorted(p.name for p in source.glob("*.php")):
            files[f"{place}/{name}"] = source / name
    # Of the two parity files only the configuration is read by PHP. The settings are compared on this side.
    carried = STATE / "parity" / PARITY_FILES[0]
    if carried.is_file():
        files[f"parity/{PARITY_FILES[0]}"] = carried
    # Bosta's district list, which the staging plugin answers the carrier plugin's request from.
    districts = STATE / "parity" / BOSTA_DISTRICTS
    if districts.is_file():
        files[f"parity/{BOSTA_DISTRICTS}"] = districts
    return files


def install_layer() -> list[str]:
    """Put the staging plugin and the scripts where the container can read them and cannot write them.

    Each file is replaced in one step, so the staging plugin is never absent while the site is answering, and a
    file this tool no longer ships is removed afterwards.
    """
    if LAYER.parent != HOST_SITE or LAYER.name != "staging-layer" or LAYER.is_symlink():
        raise Refused(f"{LAYER} is not the staging layer of {HOST_SITE}")
    wanted = layer_files()
    for relative, source in wanted.items():
        target = LAYER / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        arriving = target.with_name(target.name + ".arriving")
        shutil.copyfile(source, arriving)
        os.chmod(arriving, 0o644)
        os.replace(arriving, target)
    for folder in [LAYER, *(p for p in LAYER.rglob("*") if p.is_dir())]:
        os.chmod(folder, 0o755)
    for stale in [p for p in LAYER.rglob("*") if p.is_file() and p.relative_to(LAYER).as_posix() not in wanted]:
        stale.unlink()
    return sorted(wanted)


def state(*parts: str) -> Path:
    STATE.mkdir(mode=0o700, exist_ok=True)
    return STATE.joinpath(*parts)


def cmd_build(a) -> None:
    guard_tree()
    release, manifest = current_release(), release_manifest()
    for folder in ("backups", "evidence", "parity"):
        state(folder).mkdir(exist_ok=True)
    installed = install_layer()
    docker("mkdir", "-p", f"{IN_SHARED}/mail", f"{IN_SHARED}/logs")
    problems = supplied_plugin_problems(release, manifest) + writable_problems(manifest) \
        + config_problems(config_facts())
    tool_commit = text(run(["git", "-C", str(REPO), "rev-parse", "HEAD"], stdout=subprocess.PIPE,
                           stderr=subprocess.PIPE, check=False).stdout).strip()
    build = {"built": local.now(), "profile": "server", "release": release.name, "tag": manifest["tag"],
             "commit": manifest["commit"], "corex": manifest["corex"], "wordpress": manifest["wordpress"],
             "tool_commit": tool_commit, "tool_is_the_release_commit": tool_commit == manifest["commit"],
             "layer": installed, "problems": problems}
    state("BUILD.json").write_text(json.dumps(build, indent=1) + "\n", encoding="utf-8")
    say(json.dumps(build, indent=1))
    if not build["tool_is_the_release_commit"]:
        say("NOTE: this checkout is not the commit the release was built from. The seed and the baseline are read "
            "from this checkout.")
    if problems:
        raise SystemExit("the deployed release is not ready for staging:\n  " + "\n  ".join(problems))


TWO_FACTOR_KEY_FILE = "two-factor.key"


def two_factor_key() -> str:
    """WP 2FA's encryption key for this installation: made once, then the same key every time.

    Every two-factor enrolment is encrypted with it, so a new key would make every enrolment unreadable. It is kept
    in the tool's own owner-only state, outside the repository and outside anything PHP can serve, and back it up
    with the server configuration (docs/two-factor-recovery.md). A file that is not a 32-byte base64 key is refused
    rather than replaced, because replacing it would lock every enrolled staff member out. specs/008-admin-two-factor,
    FR-004.
    """
    path = state(TWO_FACTOR_KEY_FILE)
    if path.exists():
        key = path.read_text(encoding="ascii").strip()
        try:
            if len(base64.b64decode(key, validate=True)) != 32:
                raise ValueError
        except ValueError:
            raise SystemExit(f"{path} does not hold a 32-byte base64 key; it was not replaced. Restore it from backup.")
        return key
    key = base64.b64encode(secrets.token_bytes(32)).decode("ascii")
    path.write_text(key + "\n", encoding="ascii")
    os.chmod(path, 0o600)
    return key


def cmd_server_config(a) -> None:
    salts = {f"SALT_{i}": secrets.token_urlsafe(48) for i in range(1, 10)}
    sys.stdout.write(local.render("wp-config-server.php.tmpl", {
        "TABLE_PREFIX": TABLE_PREFIX, "PUBLIC_HOST": PUBLIC_HOST, "LOCAL_HOST": LOCAL_HOST, "IN_SHARED": IN_SHARED,
        "IN_MU_PLUGINS": in_layer("staging", "mu-plugins"), "TWO_FACTOR_KEY": two_factor_key(), **salts}))


# ---- database -----------------------------------------------------------------------------------------------

def database(tool: tuple, *args: str, data: bytes | None = None, check: bool = True):
    return docker("sh", "-c", DB_SHELL, "sh", *tool, *args, data=data, check=check)


def sql(query: str) -> str:
    return text(database(DB_CLIENT, "--default-character-set=utf8mb4", "-N", "-e", query, DB_NAME).stdout).strip()


def table_count() -> int:
    return int(sql("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE();"))


def empty_database() -> int:
    """Drop every table and view. The database itself, its collation and its grants are the server's and stay."""
    listed = sql("SELECT table_name, table_type FROM information_schema.tables WHERE table_schema = DATABASE();")
    drops = []
    for line in listed.splitlines():
        name, kind = line.split("\t")
        if not re.fullmatch(r"[A-Za-z0-9_$]+", name):
            raise SystemExit(f"table name {name!r} is not one this tool will put in a statement")
        drops.append(f"DROP {'VIEW' if kind == 'VIEW' else 'TABLE'} IF EXISTS `{name}`")
    if drops:
        sql("SET FOREIGN_KEY_CHECKS=0; " + "; ".join(drops) + ";")
    return len(drops)


# ---- what a visitor gets ------------------------------------------------------------------------------------

def http_address() -> tuple[str, int]:
    host, _, port = os.environ.get("MIZZEY_STAGING_HTTP", "127.0.0.1:80").partition(":")
    return host, int(port or 80)


def fetch(path: str, cookies: dict | None = None, redirects: int = 5) -> dict:
    """One request to the server's own web server on this machine, under the local staging host name.

    It goes to the loopback address directly and names the host in the request, so it does not depend on how this
    machine resolves a `.localhost` name, and it never leaves the machine or passes Cloudflare.
    """
    headers = {"Host": LOCAL_HOST, "User-Agent": "mizzey-staging-tool"}
    if cookies:
        headers["Cookie"] = "; ".join(f"{k}={urllib.parse.quote(v, safe='')}" for k, v in cookies.items())
    connection = http.client.HTTPConnection(*http_address(), timeout=90)
    try:
        connection.request("GET", path, headers=headers)
        response = connection.getresponse()
        body = response.read().decode("utf-8", "replace")
        location = urllib.parse.urlsplit(response.getheader("Location") or "")
        if response.status in (301, 302, 303, 307, 308) and redirects and location.netloc in ("", LOCAL_HOST):
            onward = location.path + (f"?{location.query}" if location.query else "")
            return fetch(onward, cookies, redirects - 1)
        return {"status": response.status, "landed": path, "robots": response.getheader("X-Robots-Tag"), "body": body}
    finally:
        connection.close()


def http_checks() -> dict:
    """What a visitor gets. The status alone proves nothing, because WordPress with no tables answers 200 with its
    install screen, so each answer also records where it landed, whether it is the seeded store, and the marker."""
    out = {}
    for path in ("/", "/ar/", "/shop/", "/product/sample-product-01/", "/wp-login.php"):
        try:
            answer = fetch(path)
        except OSError as e:
            out[path] = {"status": f"no answer: {e}"}
            continue
        body = answer["body"]
        out[path] = {"status": answer["status"], "landed": answer["landed"],
                     "install_screen": "install.php" in answer["landed"] or "wp-admin/install" in body,
                     "seeded_product": "Sample Product" in body or "منتج تجريبي" in body,
                     "marked": "mizzey-staging-banner" in body, "robots": answer["robots"]}
    return out


# ---- reset and seed -----------------------------------------------------------------------------------------

def load_credentials() -> dict:
    f = state("CREDENTIALS.json")
    return json.loads(f.read_text(encoding="utf-8")) if f.exists() else {}


def save_credentials(creds: dict) -> None:
    creds["about"] = ("Synthetic staging credentials, generated on this machine. Staging holds no real data. Never "
                      "commit this file and never reuse these values anywhere else.")
    f = state("CREDENTIALS.json")
    f.write_text(json.dumps(creds, indent=1) + "\n", encoding="utf-8")
    os.chmod(f, 0o600)


def with_secret(name: str, value: str, *wp_args: str, **kw) -> str:
    """Run WP-CLI with one secret in its environment. `docker exec -e NAME` copies it by name, so it is in no
    argument list on this machine or in the container."""
    os.environ[name] = value
    try:
        return wp(*wp_args, pass_env=(name,), **kw)
    finally:
        del os.environ[name]


def admin_visit() -> None:
    """Load the wp-admin Plugins page once as an administrator, which is when WPML applies a plugin's translation
    configuration. On the server the request is made from here and not from PHP: the site's public address is
    behind Cloudflare Access, and PHP may make no request that leaves the machine."""
    script = in_layer("staging", "server-admin-visit.php")
    opened = last_json(wp("eval-file", script, "open", url=LOCAL_URL))
    try:
        status = fetch("/wp-admin/plugins.php", cookies=opened["cookies"], redirects=0)["status"]
    finally:
        closed = wp("eval-file", script, "close", str(opened["user"]), url=LOCAL_URL)
    say(f"  wp-admin plugins.php: HTTP {status}\n  " + closed[-400:].replace("\n", "\n  "))
    if status != 200:
        raise SystemExit("the wp-admin visit did not get the Plugins page, so no translation configuration was applied")


def parity_file(name: str) -> Path:
    f = state("parity", name)
    if not f.is_file():
        raise Refused(f"{f} is missing. On the development machine run `staging.py parity-export <folder>` and copy "
                      f"its two files into {state('parity')}, or set MIZZEY_STAGING_WPML_CARRY=off to reset "
                      "without development's configuration")
    return f


def carry_wpml_config() -> None:
    """Hand staging the configuration WPML downloaded on development. Staging itself makes no outside request."""
    sent = json.loads(parity_file(PARITY_FILES[0]).read_text(encoding="utf-8"))
    expected = {f"{kind}/{name}": hashlib.md5(str(xml).encode("utf-8")).hexdigest()
                for kind in ("plugins", "themes") for name, xml in (sent.get(kind) or {}).items()}
    landed = last_json(wp("eval-file", in_layer("staging", "wpml-remote-config.php"), "import",
                          in_layer("parity", PARITY_FILES[0])))
    if not expected or landed["files"] != expected:
        raise SystemExit(f"the WPML configuration did not carry: the file holds {sorted(expected)}, "
                         f"staging holds {landed['files']}")
    say("WPML configuration carried from development: " + ", ".join(sorted(expected)))


def wpml_settings() -> dict:
    return last_json(wp("eval-file", in_layer("integration", "baseline", "wpml-settings.php")))


def parity_differences() -> tuple[list[str], dict]:
    development = json.loads(parity_file(PARITY_FILES[1]).read_text(encoding="utf-8"))
    staging = wpml_settings()
    return local.differences(development, staging), staging


def require_parity() -> None:
    found, staging = parity_differences()
    if found:
        raise SystemExit("the WPML settings of staging and development differ:\n  " + "\n  ".join(found[:60])
                         + (f"\n  and {len(found) - 60} more" if len(found) > 60 else ""))
    say(f"WPML settings: staging and development are identical ({len(staging['settings'])} settings)")


def cmd_parity(a) -> None:
    guard_tree()
    require_parity()


def seed() -> None:
    """Synthetic data only. Each step is its own process: sources first, translations second (finding A11)."""
    creds = load_credentials()
    passwords = {u: secrets.token_urlsafe(14) for u in ("client_operator", "demo_customer")}
    for script in SEED_SCRIPTS:
        out = with_secret("MIZZEY_SEED_PASSWORDS", json.dumps(passwords), "eval-file",
                          in_layer("staging", "seed", script))
        say(f"seed/{script}: {out.splitlines()[-1] if out else ''}")
    for user, password in passwords.items():
        creds[user] = {"user": user, "password": password}
    save_credentials(creds)


def cmd_seed(a) -> None:
    guard_destructive()
    install_layer()
    seed()


def cmd_reset(a) -> None:
    guard_destructive()
    carried = os.environ.get("MIZZEY_STAGING_WPML_CARRY", "on") != "off"
    if carried:
        for name in PARITY_FILES:
            parity_file(name)
    install_layer()
    if table_count():
        say("backing up before the reset")
        backup("pre-reset")
    creds = load_credentials()
    creds["staging_admin"] = {"user": "staging_admin", "password": secrets.token_urlsafe(14)}
    say(f"database emptied: {empty_database()} tables dropped")
    # Installed with a password nobody keeps, then given the real one through the environment.
    wp("core", "install", f"--url={PUBLIC_URL}", "--title=Mizzey (staging)", "--admin_user=staging_admin",
       "--admin_email=staging-admin@example.invalid", "--skip-email", "--skip-plugins")
    with_secret("MIZZEY_ADMIN_PASSWORD", creds["staging_admin"]["password"], "eval",
                'wp_set_password( getenv( "MIZZEY_ADMIN_PASSWORD" ), get_user_by( "login", "staging_admin" )->ID );',
                "--skip-plugins")
    for plugin in local.ACTIVE_PLUGINS:
        wp("plugin", "activate", plugin)
    wp("theme", "activate", "mizzey-theme", themes=True)
    wp("eval", 'update_option("permalink_structure", "/%postname%/");')
    if wp("eval", 'echo get_option("permalink_structure");') != "/%postname%/":
        raise SystemExit("permalink_structure did not take")
    wp("rewrite", "flush")
    if carried:
        carry_wpml_config()
    else:
        say("WPML configuration NOT carried from development (MIZZEY_STAGING_WPML_CARRY=off)")
    say("baseline/setup.php")
    say("  " + wp("eval-file", in_layer("integration", "baseline", "setup.php"))[-400:].replace("\n", "\n  "))
    say("baseline/admin-visit, made from this machine")
    admin_visit()
    say("baseline/ia-endpoints.php")
    say("  " + wp("eval-file", in_layer("integration", "baseline", "ia-endpoints.php"))[-400:].replace("\n", "\n  "))
    save_credentials(creds)
    seed()
    wp("rewrite", "flush")
    wp("cache", "flush")
    say(f"staging reset and seeded. Credentials: {state('CREDENTIALS.json')}")
    # Last, so that a reset that fails here leaves a complete staging copy to look at.
    if carried:
        require_parity()


# ---- backup and restore -------------------------------------------------------------------------------------

def fingerprint() -> dict:
    return last_json(wp("eval-file", in_layer("staging", "fingerprint.php")))


def backup(label: str = "manual") -> Path:
    guard_tree()
    stamp = dt.datetime.now().strftime("%Y%m%d-%H%M%S")
    out = state("backups", f"{stamp}-{label}")
    out.mkdir(parents=True)
    dump = database(DB_DUMP, "--single-transaction", "--no-tablespaces", "--routines", DB_NAME)
    with gzip.open(out / "database.sql.gz", "wb", compresslevel=6) as packed:
        packed.write(dump.stdout)
    with open(out / "uploads.tar.gz", "wb") as packed:
        docker("tar", "-C", IN_SHARED, "-czf", "-", "uploads", stdout=packed)
    build = state("BUILD.json")
    manifest = {"taken": local.now(), "label": label, "environment": "staging", "database": DB_NAME,
                "engine": sql("SELECT VERSION();"),
                "build": json.loads(build.read_text(encoding="utf-8")) if build.exists() else None,
                "files": {n: {"bytes": (out / n).stat().st_size, "sha256": local.sha256(out / n)}
                          for n in ("database.sql.gz", "uploads.tar.gz")},
                "fingerprint": fingerprint()}
    (out / "manifest.json").write_text(json.dumps(manifest, indent=1) + "\n", encoding="utf-8")
    sets = sorted(p for p in state("backups").iterdir() if p.is_dir())
    for old in sets[:-local.RETENTION]:
        shutil.rmtree(old)
    say(f"backup set {out.name}: " + ", ".join(f"{n} {v['bytes']} bytes" for n, v in manifest["files"].items())
        + f"; kept {min(len(sets), local.RETENTION)} of {len(sets)} sets (retention {local.RETENTION})")
    return out


def cmd_backup(a) -> None:
    backup(a.label)


def check_uploads_archive(archive: Path) -> None:
    """An archive this tool made itself, still refused if a member would land outside uploads or is a link."""
    with tarfile.open(archive) as tar:
        for member in tar.getmembers():
            parts = PurePosixPath(member.name).parts
            if not parts or parts[0] != "uploads" or ".." in parts or member.issym() or member.islnk():
                raise SystemExit(f"archive member {member.name} does not belong in uploads")


def clear_uploads() -> None:
    """Empty the folder and keep the folder: it is the server's, and the release links to it."""
    docker("find", IN_UPLOADS, "-mindepth", "1", "-delete")


def restore(source: Path) -> None:
    guard_destructive()
    manifest = json.loads((source / "manifest.json").read_text(encoding="utf-8"))
    if manifest["environment"] != "staging" or manifest["database"] != DB_NAME:
        raise Refused("this backup set was not taken from this staging copy")
    for name, meta in manifest["files"].items():
        if local.sha256(source / name) != meta["sha256"]:
            raise SystemExit(f"{name} does not match its checksum: the backup set is damaged")
    check_uploads_archive(source / "uploads.tar.gz")
    empty_database()
    # Unpacked here and handed over as plain SQL: a child process given the file handle of a gzip stream reads the
    # compressed bytes. The first restore test on the local copy failed on exactly that.
    with gzip.open(source / "database.sql.gz", "rb") as packed:
        loaded = database(DB_CLIENT, "--default-character-set=utf8mb4", DB_NAME, data=packed.read(), check=False)
    if loaded.returncode:
        raise SystemExit(f"the database did not load: {text(loaded.stderr)[-600:]}")
    clear_uploads()
    with open(source / "uploads.tar.gz", "rb") as packed:
        docker("tar", "-C", IN_SHARED, "-xzf", "-", stdin=packed)
    wp("cache", "flush")
    wp("rewrite", "flush")


def cmd_restore(a) -> None:
    source = state("backups", a.set)
    if not source.is_dir():
        raise SystemExit(f"no backup set {a.set}")
    restore(source)
    say(f"restored {a.set}")


def cmd_restore_test(a) -> None:
    """The restore is performed, not described: back up, destroy, prove the damage, restore, compare."""
    guard_destructive()
    record: dict = {"started": local.now(), "environment": "staging", "profile": "server", "database": DB_NAME,
                    "engine": sql("SELECT VERSION();"), "steps": []}

    def step(name: str, **facts) -> None:
        record["steps"].append({"step": name, "at": local.now(), **facts})
        say(f"{name}: {json.dumps(facts, ensure_ascii=False)[:300]}")

    before, http_before = fingerprint(), http_checks()
    step("1 fingerprint before", fingerprint=before, http=http_before)
    source = backup("restore-test")
    manifest = json.loads((source / "manifest.json").read_text(encoding="utf-8"))
    step("2 backup taken", set=source.name, files=manifest["files"])

    empty_database()
    clear_uploads()
    left = text(docker("sh", "-c", f'find "{IN_UPLOADS}" -type f | wc -l').stdout).strip()
    step("3 staging destroyed", tables_left=table_count(), upload_files_left=int(left), http=http_checks())

    started = time.time()
    restore(source)
    step("4 restored from the backup set", seconds=round(time.time() - started, 1))
    after, http_after = fingerprint(), http_checks()
    # Volatile tables are restored but not compared: see fingerprint.php.
    compared = [k for k in sorted(set(before) | set(after)) if k != "rows_volatile"]
    same = all(before.get(k) == after.get(k) for k in compared)
    step("5 fingerprint after", fingerprint=after, http=http_after)
    record.update({"finished": local.now(), "fingerprints_identical": same,
                   "http_identical": http_after == http_before, "compared": compared,
                   "not_compared": {"rows_volatile": [before.get("rows_volatile"), after.get("rows_volatile")]},
                   "differences": {k: [before.get(k), after.get(k)] for k in compared
                                   if before.get(k) != after.get(k)},
                   "result": "PASS" if same and http_after == http_before else "FAIL"})
    evidence = state("evidence", f"restore-test-{dt.datetime.now().strftime('%Y%m%d-%H%M%S')}.json")
    evidence.write_text(json.dumps(record, indent=1, ensure_ascii=False) + "\n", encoding="utf-8")
    say(f"restore test: {record['result']}. Evidence: {evidence}")
    if record["result"] != "PASS":
        raise SystemExit(1)


# ---- check and status ---------------------------------------------------------------------------------------

def cmd_check(a) -> None:
    """Shown, not assumed: the marker, the mail capture, the block on outside requests, and the instruction to
    search engines. It sends one message to an address that cannot exist, which the staging plugin captures."""
    guard_tree()
    problems = config_problems(config_facts())
    probe = last_json(wp("eval-file", in_layer("staging", "server-probe.php")))
    pages = {path: fetch(path) for path in ("/", "/wp-login.php")}
    checks = {
        "configuration is the staging copy": not problems,
        "the staging plugin is loaded": probe["plugin_loaded"],
        "mail is captured and not sent": probe["mail_captured"] and not probe["mailer_built"],
        "an outside request is refused": probe["outside_request"] == "refused",
        "every screen is marked": all("mizzey-staging-banner" in page["body"] for page in pages.values()),
        "search engines are told not to index": all("noindex" in (page["robots"] or "") for page in pages.values()),
    }
    record = {"checked": local.now(), "profile": "server", "release": release_manifest()["tag"], "checks": checks,
              "configuration_problems": problems, "probe": probe,
              "pages": {path: {"status": page["status"], "robots": page["robots"]} for path, page in pages.items()}}
    evidence = state("evidence", f"check-{dt.datetime.now().strftime('%Y%m%d-%H%M%S')}.json")
    evidence.write_text(json.dumps(record, indent=1) + "\n", encoding="utf-8")
    for name, passed in checks.items():
        say(f"{'PASS' if passed else 'FAIL'}  {name}")
    say(f"Evidence: {evidence}")
    if not all(checks.values()):
        raise SystemExit(1)


def cmd_status(a) -> None:
    link = HOST_SITE / "current"
    manifest = HOST_SITE / "current" / MANIFEST
    release = json.loads(manifest.read_text(encoding="utf-8")) if manifest.is_file() else None
    try:
        answer = fetch("/wp-login.php", redirects=0)["status"]
    except OSError as e:
        answer = f"no answer: {e}"
    build = STATE / "BUILD.json"
    backups = STATE / "backups"
    say(json.dumps({"profile": "server", "current": os.readlink(link) if link.is_symlink() else None,
                    "release": release and {k: release[k] for k in ("tag", "commit", "wordpress")},
                    "container": text(docker("printenv", "SITE_NAME", check=False).stdout).strip() or None,
                    "url": PUBLIC_URL, "local_url": LOCAL_URL, "http": answer,
                    "build": json.loads(build.read_text(encoding="utf-8")) if build.exists() else None,
                    "backups": sorted(p.name for p in backups.iterdir() if p.is_dir())[-3:]
                    if backups.exists() else []}, indent=1))


def main(argv: list[str] | None = None) -> int:
    ap = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    sub = ap.add_subparsers(dest="command", required=True)
    for name in ("build", "reset", "seed", "parity", "restore-test", "check", "status", "server-config"):
        sub.add_parser(name)
    sub.add_parser("backup").add_argument("--label", default="manual")
    sub.add_parser("restore").add_argument("set")
    for name in NOT_HERE:
        sub.add_parser(name).add_argument("rest", nargs="*")
    a = ap.parse_args(argv)
    if a.command in NOT_HERE:
        raise Refused(f"`{a.command}` is not run on the server: {NOT_HERE[a.command]}")
    if hasattr(sys.stdout, "reconfigure"):
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    globals()["cmd_" + a.command.replace("-", "_")](a)
    return 0

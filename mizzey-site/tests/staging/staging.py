"""The local staging and demonstration environment (PBI #244, NFR-08 and NFR-09, DECISIONS.md D-10 and D-12).

    python mizzey-site/tests/staging/staging.py <command>

    build [--ref origin/main]   assemble the staging tree from a named commit, write its configuration
                                (--code-only refreshes just the site code after a merge)
    db-create                   create the staging database and its own database account
    up | down | status          start, stop or report the staging web server
    reset                       DESTRUCTIVE for staging only: reinstall, apply the baseline, load synthetic data
    parity                      compare the WPML settings of staging and development, and fail if they differ
    backup                      database and uploads into a dated, checksummed backup set
    restore <set>               DESTRUCTIVE for staging only: put a backup set back
    restore-test                back up, destroy, restore, and compare fingerprints. Writes the evidence file
    tunnel-config <hostname>    allow a public host name and print the Cloudflare Tunnel commands

Staging is a separate runtime from development, on the same machine:

                    development                         staging and demonstration
    directory       ../app/wp                           ../app-staging/wp
    web server      the WAMP Apache service, port 80    its own Apache process, 127.0.0.1:8088
    database        mizzey, as root                     mizzey_staging, as mizzey_staging (no other grant)
    table prefix    mz_                                 mzs_
    code            the working tree, linked            an export of a named commit, copied
    data            scenario fixtures                   synthetic data only, from seed/
    mail            the machine's mail settings         captured to ../app-staging/mail, never sent

Nothing here is production, and nothing here may be pointed at production: every destructive command checks the
directory, the database name, the database host and the environment type first, and refuses otherwise. Secrets
are generated on this machine and stay in ../app-staging, which is outside the repository.
"""

from __future__ import annotations

import argparse
import datetime as dt
import gzip
import hashlib
import json
import os
import re
import secrets
import shutil
import subprocess
import sys
import tarfile
import time
import urllib.request
from pathlib import Path

HERE = Path(__file__).resolve().parent
REPO = HERE.parents[2]
DEV_APP = REPO.parent / "app"
STAGING = REPO.parent / "app-staging"
WP = STAGING / "wp"
BASELINE = HERE.parent / "integration" / "baseline"

PORT = 8088
LOCAL_URL = f"http://127.0.0.1:{PORT}"
DB_NAME = "mizzey_staging"
DB_USER = "mizzey_staging"
TABLE_PREFIX = "mzs_"
RETENTION = 7

APACHE = Path("C:/wamp64/bin/apache/apache2.4.59")
PHP = Path("C:/wamp64/bin/php/php8.3.6")
MYSQL_BIN = Path("C:/wamp64/bin/mysql/mysql8.3.0/bin")
# Full paths: on Windows a child process is looked up on the parent PATH, not on the one passed to it.
MYSQL = str(MYSQL_BIN / "mysql.exe")
MYSQLDUMP = str(MYSQL_BIN / "mysqldump.exe")

# Copied from the development runtime as they are: the same tested versions (stack.lock.json).
THIRD_PARTY_PLUGINS = ("woocommerce", "sitepress-multilingual-cms", "wpml-string-translation",
                       "woocommerce-multilingual", "wpml-media-translation", "wpml-cms-nav", "bosta-woocommerce")
# Linked to the pinned CoreX checkout, which corex.lock fixes to one commit and which nobody edits.
COREX_PLUGINS = {"corex-core": "plugins", "corex-config": "plugins", "corex-blocks": "plugins",
                 "corex-forms": "plugins", "corex-guides": "addons", "corex-email": "addons",
                 "corex-media": "addons", "corex-ui": "addons"}
ACTIVE_PLUGINS = ("woocommerce", "sitepress-multilingual-cms", "wpml-string-translation",
                  "woocommerce-multilingual", "corex-core", "corex-config", "corex-blocks", "corex-forms",
                  "corex-guides", "corex-email", "corex-media", "corex-ui", "bosta-woocommerce", "mizzey-site")


class Refused(SystemExit):
    def __init__(self, why: str):
        super().__init__(f"refusing: {why}")


def env() -> dict:
    e = dict(os.environ)
    e["PATH"] = f"{MYSQL_BIN}{os.pathsep}{e.get('PATH', '')}"
    return e


def sh(args: list, check: bool = True, **kw) -> subprocess.CompletedProcess:
    return subprocess.run([str(a) for a in args], env=env(), capture_output=True, text=True, encoding="utf-8",
                          errors="replace", check=check, **kw)


def wp(*args: str, check: bool = True, themes: bool = False, path: Path = WP) -> str:
    exe = shutil.which("wp") or "wp"
    flags = [f"--path={path}"] + ([] if themes else ["--skip-themes"])
    proc = sh([exe, *flags, *args], check=False)
    if check and proc.returncode:
        raise SystemExit(f"wp {' '.join(args)} failed ({proc.returncode}):\n{proc.stderr[-2000:]}\n{proc.stdout[-800:]}")
    return proc.stdout.strip()


def say(message: str) -> None:
    print(message, flush=True)


# ---- guards -------------------------------------------------------------------------------------------------

def guard_tree() -> None:
    """The staging tree is the only tree these commands may write to."""
    if WP.resolve().as_posix().lower().split("/")[-2:] != ["app-staging", "wp"]:
        raise Refused(f"{WP} is not the staging tree")
    if WP.resolve() == (DEV_APP / "wp").resolve():
        raise Refused("the staging tree resolves to the development runtime")


def guard_destructive() -> None:
    """Checked against the running configuration, not against what this file expects it to be."""
    guard_tree()
    if os.environ.get("MIZZEY_CONFIRM_STAGING") != "yes":
        raise Refused("set MIZZEY_CONFIRM_STAGING=yes")
    # Read from wp-config.php itself, which works before WordPress is installed and after a database is dropped.
    listed = json.loads(wp("config", "list", "--format=json", "--skip-plugins"))
    facts = {row["name"]: row["value"] for row in listed}
    name, host, user, prefix = facts["DB_NAME"], facts["DB_HOST"], facts["DB_USER"], facts["table_prefix"]
    kind, flag = facts.get("WP_ENVIRONMENT_TYPE"), str(facts.get("MIZZEY_STAGING")).lower() in ("1", "true")
    if name != DB_NAME or user != DB_USER or prefix != TABLE_PREFIX:
        raise Refused(f"database is {name} as {user} with prefix {prefix}, not the staging database")
    if host not in ("localhost", "127.0.0.1"):
        raise Refused(f"database host is {host}")
    if kind != "staging" or not flag:
        raise Refused(f"environment type is {kind}, not staging")


# ---- build --------------------------------------------------------------------------------------------------

def copy_tree(src: Path, dst: Path, skip: tuple = ()) -> None:
    if is_link(dst):
        raise SystemExit(f"{dst} is a link: refusing to remove through it")
    if dst.exists():
        shutil.rmtree(dst)
    shutil.copytree(src, dst, ignore=shutil.ignore_patterns(*skip) if skip else None)


def is_link(path: Path) -> bool:
    """A symlink, or a Windows junction. A junction is a directory to is_dir() and must never be walked."""
    try:
        return path.is_symlink() or bool(getattr(os.lstat(path), "st_file_attributes", 0) & 0x400)
    except FileNotFoundError:
        return False


def link(target: Path, at: Path) -> None:
    if is_link(at):
        # rmdir removes a junction itself and leaves what it points at alone.
        os.rmdir(at) if at.is_dir() else os.unlink(at)
    elif at.exists():
        raise SystemExit(f"{at} exists and is not a link: remove it by hand")
    if os.name == "nt":
        sh(["cmd", "/c", "mklink", "/J", str(at), str(target)])
    else:
        os.symlink(target, at, target_is_directory=True)


def extract(tar: tarfile.TarFile, into: Path) -> None:
    """Extract an archive this script made itself, refusing any member that would land outside `into`."""
    root = into.resolve()
    for member in tar.getmembers():
        target = (root / member.name).resolve()
        if root != target and root not in target.parents:
            raise SystemExit(f"archive member {member.name} would be written outside {root}")
        if member.issym() or member.islnk():
            raise SystemExit(f"archive member {member.name} is a link")
    tar.extractall(root)


def export_commit(ref: str, sha: str, content: Path) -> None:
    """Export the site plugin and the theme from one commit, without their tests, so staging runs merged code."""
    archive = STAGING / "run" / f"export-{sha[:12]}.tar"
    with open(archive, "wb") as out:
        subprocess.run(["git", "-C", str(REPO), "archive", "--format=tar", sha, "mizzey-site", "mizzey-theme"],
                       stdout=out, check=True)
    stage = STAGING / "run" / "export"
    if stage.exists():
        shutil.rmtree(stage)
    with tarfile.open(archive) as tar:
        extract(tar, stage)
    shutil.rmtree(stage / "mizzey-site" / "tests", ignore_errors=True)
    copy_tree(stage / "mizzey-site", content / "plugins" / "mizzey-site")
    copy_tree(stage / "mizzey-theme", content / "themes" / "mizzey-theme")
    shutil.rmtree(stage)
    archive.unlink()


def render(template: str, values: dict) -> str:
    text = (HERE / template).read_text(encoding="utf-8")
    for key, value in values.items():
        text = text.replace("{{" + key + "}}", str(value))
    left = re.findall(r"\{\{[A-Z_]+\}\}", text)
    if left:
        raise SystemExit(f"{template}: unfilled {sorted(set(left))}")
    return text


def cmd_build(a) -> None:
    guard_tree()
    sha = sh(["git", "-C", REPO, "rev-parse", a.ref]).stdout.strip()
    merged = sh(["git", "-C", REPO, "merge-base", "--is-ancestor", sha, "origin/main"], check=False).returncode == 0
    lock = json.loads((REPO / "corex.lock").read_text(encoding="utf-8"))
    corex_head = sh(["git", "-C", DEV_APP / "corex", "rev-parse", "HEAD"]).stdout.strip()
    if corex_head != lock["commit"]:
        raise Refused(f"the CoreX checkout is at {corex_head[:8]}, corex.lock says {lock['commit'][:8]}")
    for d in ("run", "logs", "mail", "backups", "evidence"):
        (STAGING / d).mkdir(parents=True, exist_ok=True)

    content = WP / "wp-content"
    if a.code_only and not (content / "plugins" / "woocommerce").exists():
        raise Refused("--code-only needs a staging tree that has been built in full once")
    if not a.code_only:
        say("WordPress core, copied from the development runtime")
        dev_wp = DEV_APP / "wp"
        WP.mkdir(exist_ok=True)
        for entry in dev_wp.iterdir():
            if entry.name in ("wp-content", "wp-config.php", ".htaccess"):
                continue
            if entry.is_dir():
                copy_tree(entry, WP / entry.name)
            else:
                shutil.copy2(entry, WP / entry.name)
        content = WP / "wp-content"
        for d in ("plugins", "themes", "mu-plugins", "uploads"):
            (content / d).mkdir(parents=True, exist_ok=True)
        shutil.copy2(dev_wp / "wp-content" / "index.php", content / "index.php")
        copy_tree(dev_wp / "wp-content" / "languages", content / "languages")

        say("third-party plugins, copied")
        for name in THIRD_PARTY_PLUGINS:
            copy_tree(dev_wp / "wp-content" / "plugins" / name, content / "plugins" / name)
        say("CoreX, linked to the pinned checkout")
        for name, group in COREX_PLUGINS.items():
            link(DEV_APP / "corex" / group / name, content / "plugins" / name)
        link(DEV_APP / "corex" / "theme", content / "themes" / "corex")
    say(f"mizzey-site and mizzey-theme, exported from {sha[:12]}")
    export_commit(a.ref, sha, content)
    shutil.copy2(HERE / "mu-plugins" / "mizzey-staging.php", content / "mu-plugins" / "mizzey-staging.php")

    config = WP / "wp-config.php"
    if not config.exists():
        password = secrets.token_urlsafe(24)
        salts = {f"SALT_{i}": secrets.token_urlsafe(48) for i in range(1, 10)}
        config.write_text(render("wp-config-staging.php.tmpl", {
            "DB_NAME": DB_NAME, "DB_USER": DB_USER, "DB_PASSWORD": password, "TABLE_PREFIX": TABLE_PREFIX,
            "PORT": PORT, "STAGING_ROOT": STAGING.as_posix(), **salts}), encoding="utf-8", newline="\n")
        say("wp-config.php written with new keys and a new database password")
    gate = STAGING / "run" / "htpasswd"
    creds = load_credentials()
    if not gate.exists():
        creds["tunnel_gate"] = {"user": "mizzey-review", "password": secrets.token_urlsafe(12)}
        line = sh([APACHE / "bin" / "htpasswd.exe", "-nbB", creds["tunnel_gate"]["user"],
                   creds["tunnel_gate"]["password"]]).stdout.strip()
        gate.write_text(line + "\n", encoding="utf-8", newline="\n")
        save_credentials(creds)
    (STAGING / "run" / "httpd-staging.conf").write_text(render("httpd-staging.conf.tmpl", {
        "APACHE": APACHE.as_posix(), "PHP": PHP.as_posix(), "STAGING_ROOT": STAGING.as_posix(), "PORT": PORT}),
        encoding="utf-8", newline="\n")
    hosts = STAGING / "allowed-hosts.txt"
    if not hosts.exists():
        hosts.write_text("# One public host name per line, written by `staging.py tunnel-config`.\n", encoding="utf-8")
    build = {"built": now(), "ref": a.ref, "commit": sha, "merged_to_main": merged,
             "corex": {"version": lock["version"], "commit": lock["commit"]},
             "source": "mizzey-site and mizzey-theme exported with git archive; tests excluded",
             "scope": "site code only" if a.code_only else "full tree"}
    (STAGING / "BUILD.json").write_text(json.dumps(build, indent=1) + "\n", encoding="utf-8")
    say(json.dumps(build, indent=1))
    if not merged:
        say("NOTE: this commit is not on origin/main. Staging shows unmerged code until it is rebuilt.")


def now() -> str:
    return dt.datetime.now(dt.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")


def load_credentials() -> dict:
    f = STAGING / "CREDENTIALS.json"
    return json.loads(f.read_text(encoding="utf-8")) if f.exists() else {}


def save_credentials(creds: dict) -> None:
    creds["about"] = ("Synthetic staging credentials, generated on this machine. Staging holds no real data. Never "
                      "commit this file and never reuse these values anywhere else.")
    (STAGING / "CREDENTIALS.json").write_text(json.dumps(creds, indent=1) + "\n", encoding="utf-8")


# ---- database -----------------------------------------------------------------------------------------------

def db_client(tool: str = MYSQL) -> list[str]:
    """The database client as the staging account. The password is read from an option file, never passed as an
    argument, so it cannot appear in a process list, a log or a traceback."""
    options = STAGING / "run" / "staging-client.cnf"
    lines = ["[client]", f"user={DB_USER}", f'password="{config_value("DB_PASSWORD")}"', "host=127.0.0.1",
             "default-character-set=utf8mb4", ""]
    options.write_text("\n".join(lines), encoding="utf-8", newline="\n")
    return [tool, f"--defaults-extra-file={options}"]


def config_value(name: str) -> str:
    text = (WP / "wp-config.php").read_text(encoding="utf-8")
    return re.search(rf"define\(\s*'{name}',\s*'([^']*)'", text).group(1)


def cmd_db_create(a) -> None:
    guard_tree()
    password = config_value("DB_PASSWORD")
    admin = [MYSQL, f"-u{os.environ.get('MIZZEY_DB_ADMIN_USER', 'root')}", "-h127.0.0.1", "-P3306"]
    if os.environ.get("MIZZEY_DB_ADMIN_PASSWORD"):
        admin.append(f"-p{os.environ['MIZZEY_DB_ADMIN_PASSWORD']}")
    sql = (f"CREATE DATABASE IF NOT EXISTS `{DB_NAME}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;"
           f"CREATE USER IF NOT EXISTS '{DB_USER}'@'localhost' IDENTIFIED BY '{password}';"
           f"ALTER USER '{DB_USER}'@'localhost' IDENTIFIED BY '{password}';"
           f"GRANT ALL PRIVILEGES ON `{DB_NAME}`.* TO '{DB_USER}'@'localhost';FLUSH PRIVILEGES;")
    # Sent on standard input, so the new password is never an argument.
    made = subprocess.run(admin, input=sql.encode("utf-8"), env=env(), capture_output=True)
    if made.returncode:
        raise SystemExit("could not create the staging database or account (is the admin connection right?)")
    grants =sh([*admin, "-N", "-e", f"SHOW GRANTS FOR '{DB_USER}'@'localhost';"]).stdout.strip().splitlines()
    say("\n".join(re.sub(r"IDENTIFIED BY PASSWORD '[^']*'", "", g) for g in grants))
    reach = sh([*db_client(), "-N", "-e", "SHOW DATABASES;"]).stdout.split()
    visible = sorted(set(reach) - {"information_schema", "performance_schema"})
    say(f"databases the staging account can see: {visible}")
    if visible != [DB_NAME]:
        raise SystemExit("the staging account can reach a database other than its own")


# ---- web server ---------------------------------------------------------------------------------------------

def pid() -> int | None:
    f = STAGING / "run" / "httpd.pid"
    if not f.exists():
        return None
    try:
        value = int(f.read_text().strip())
    except ValueError:
        return None
    alive = sh(["tasklist", "/FI", f"PID eq {value}", "/NH"], check=False).stdout
    return value if "httpd" in alive else None


def answers() -> int | None:
    try:
        with urllib.request.urlopen(urllib.request.Request(LOCAL_URL + "/wp-login.php", method="HEAD"), timeout=20) as r:
            return r.status
    except urllib.error.HTTPError as e:
        return e.code
    except OSError:
        return None


def cmd_up(a) -> None:
    guard_tree()
    if pid():
        return say(f"already running, pid {pid()}, {LOCAL_URL}")
    conf = STAGING / "run" / "httpd-staging.conf"
    test = sh([APACHE / "bin" / "httpd.exe", "-t", "-f", conf], check=False)
    if test.returncode:
        raise SystemExit(f"Apache refuses the staging configuration:\n{test.stderr}")
    flags = 0x00000008 | 0x00000200 if os.name == "nt" else 0  # DETACHED_PROCESS | CREATE_NEW_PROCESS_GROUP
    # The machine's PHP loads Xdebug in development mode, which writes a stack trace with every argument's value
    # under each warning in the PHP error log. One of those arguments is the database object, so the staging
    # database password was written to logs/php-error.log thousands of times (found on 5 October 2026). Staging
    # runs without Xdebug: the variable overrides the setting for this process and its children only.
    server_env = env()
    server_env["XDEBUG_MODE"] = "off"
    subprocess.Popen([str(APACHE / "bin" / "httpd.exe"), "-f", str(conf)], env=server_env, creationflags=flags,
                     stdin=subprocess.DEVNULL, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, close_fds=True)
    for _ in range(40):
        time.sleep(0.5)
        if pid():
            break
    say(f"staging web server: pid {pid()}, {LOCAL_URL}")


def cmd_down(a) -> None:
    p = pid()
    if not p:
        return say("not running")
    sh(["taskkill", "/PID", str(p), "/T", "/F"], check=False)
    say(f"stopped pid {p}")


def cmd_status(a) -> None:
    build = STAGING / "BUILD.json"
    say(json.dumps({"tree": str(WP), "exists": WP.exists(), "server_pid": pid(), "url": LOCAL_URL,
                    "http": answers() if pid() else None,
                    "build": json.loads(build.read_text(encoding="utf-8")) if build.exists() else None,
                    "backups": sorted(p.name for p in (STAGING / "backups").glob("*") if p.is_dir())[-3:]
                    if (STAGING / "backups").exists() else []}, indent=1))


# ---- reset and seed -----------------------------------------------------------------------------------------

def cmd_reset(a) -> None:
    guard_destructive()
    if not pid():
        raise Refused("the staging web server is not running (the baseline visits wp-admin over HTTP): run `up`")
    if (WP / "wp-content" / "uploads").exists() and wp("db", "tables", "--all-tables-with-prefix", check=False):
        say("backing up before the reset")
        backup("pre-reset")
    creds = load_credentials()
    creds["staging_admin"] = {"user": "staging_admin", "password": secrets.token_urlsafe(14)}
    wp("db", "reset", "--yes", "--skip-plugins")
    wp("core", "install", f"--url={LOCAL_URL}", "--title=Mizzey (staging)", "--admin_user=staging_admin",
       "--admin_email=staging-admin@example.invalid", f"--admin_password={creds['staging_admin']['password']}",
       "--skip-email", "--skip-plugins")
    for plugin in ACTIVE_PLUGINS:
        wp("plugin", "activate", plugin)
    wp("theme", "activate", "mizzey-theme", themes=True)
    # Set from PHP: a leading-slash argument is rewritten into a Windows path by Git Bash (see reset-runtime.sh).
    wp("eval", 'update_option("permalink_structure", "/%postname%/");')
    if wp("eval", 'echo get_option("permalink_structure");') != "/%postname%/":
        raise SystemExit("permalink_structure did not take")
    wp("rewrite", "flush")
    # The baseline files are the ones the development runtime gets, but they are not the baseline's only input:
    # WPML also works from a configuration it downloads, which staging may not fetch. It is carried across here,
    # before the wp-admin visit that applies it, and the reset ends by comparing the two runtimes.
    carry_wpml_config()
    for script in ("setup.php", "admin-visit.php", "ia-endpoints.php"):
        say(f"baseline/{script}")
        say("  " + wp("eval-file", str(BASELINE / script))[-400:].replace("\n", "\n  "))
    save_credentials(creds)
    seed()
    wp("rewrite", "flush")
    wp("cache", "flush")
    say(f"staging reset and seeded. Credentials: {STAGING / 'CREDENTIALS.json'}")
    # Last, so that a reset that fails here leaves a complete staging copy to look at.
    require_parity()


# ---- the same WPML settings as development ------------------------------------------------------------------

def carry_wpml_config() -> None:
    """Copy the configuration WPML downloaded on development into staging, which makes no outside request."""
    carried = STAGING / "run" / "wpml-remote-config.json"
    script = str(HERE / "wpml-remote-config.php")
    sent = json.loads(wp("eval-file", script, "export", carried.as_posix(), path=DEV_APP / "wp").splitlines()[-1])
    landed = json.loads(wp("eval-file", script, "import", carried.as_posix()).splitlines()[-1])
    if not sent["files"] or (sent["index"], sent["files"]) != (landed["index"], landed["files"]):
        raise SystemExit(f"the WPML configuration did not carry: development holds {sent}, staging holds {landed}")
    say("WPML configuration carried from development: " + ", ".join(sorted(sent["files"])))


def wpml_settings(path: Path) -> dict:
    return json.loads(wp("eval-file", str(BASELINE / "wpml-settings.php"), path=path).splitlines()[-1])


def differences(development, staging, at: str = "") -> list[str]:
    """Every setting that differs between the two runtimes, by name, with the value each one holds."""
    if isinstance(development, dict) and isinstance(staging, dict):
        found = []
        for key in sorted(set(development) | set(staging)):
            name = f"{at}.{key}" if at else str(key)
            if key not in staging:
                found.append(f"{name}: development {json.dumps(development[key])[:120]}, staging absent")
            elif key not in development:
                found.append(f"{name}: development absent, staging {json.dumps(staging[key])[:120]}")
            else:
                found += differences(development[key], staging[key], name)
        return found
    if isinstance(development, list) and isinstance(staging, list):
        members = [sorted({json.dumps(v, sort_keys=True) for v in side}) for side in (development, staging)]
        found = []
        for label, mine, theirs in (("development", *members), ("staging", *reversed(members))):
            only = [json.loads(v) for v in mine if v not in theirs]
            if only:
                found.append(f"{at}: only on {label} {only}")
        return found
    if development != staging:
        return [f"{at}: development {json.dumps(development)[:120]}, staging {json.dumps(staging)[:120]}"]
    return []


def require_parity() -> None:
    """Fail unless staging and development hold the same WPML settings, reached from the same configuration."""
    development, staging = wpml_settings(DEV_APP / "wp"), wpml_settings(WP)
    found = differences(development, staging)
    if found:
        raise SystemExit("the WPML settings of staging and development differ:\n  " + "\n  ".join(found[:60])
                         + (f"\n  and {len(found) - 60} more" if len(found) > 60 else ""))
    say(f"WPML settings: staging and development are identical ({len(staging['settings'])} settings, "
        f"{len(staging['settings'].get('translation-management.custom_fields_translation') or {})} custom fields, "
        f"configuration from {len(staging['sources']['downloaded_plugins'])} downloaded and "
        f"{len(staging['sources']['bundled_plugins'])} bundled files)")


def cmd_parity(a) -> None:
    guard_tree()
    require_parity()


def seed() -> None:
    """Synthetic data only. Each step is its own process: sources first, translations second (finding A11)."""
    creds = load_credentials()
    passwords = {u: secrets.token_urlsafe(14) for u in ("client_operator", "demo_customer")}
    os.environ["MIZZEY_SEED_PASSWORDS"] = json.dumps(passwords)
    for script in ("10-users.php", "15-store-settings.php", "20-catalogue-sources.php",
                   "30-catalogue-translations.php", "40-orders.php", "50-reports.php"):
        out = wp("eval-file", str(HERE / "seed" / script))
        say(f"seed/{script}: {out.splitlines()[-1] if out else ''}")
    for user, password in passwords.items():
        creds[user] = {"user": user, "password": password}
    save_credentials(creds)


def cmd_seed(a) -> None:
    guard_destructive()
    seed()


# ---- backup and restore -------------------------------------------------------------------------------------

def sha256(path: Path) -> str:
    h = hashlib.sha256()
    with open(path, "rb") as f:
        for block in iter(lambda: f.read(1 << 20), b""):
            h.update(block)
    return h.hexdigest()


def fingerprint() -> dict:
    return json.loads(wp("eval-file", str(HERE / "fingerprint.php")).splitlines()[-1])


def backup(label: str = "manual") -> Path:
    guard_tree()
    stamp = dt.datetime.now().strftime("%Y%m%d-%H%M%S")
    out = STAGING / "backups" / f"{stamp}-{label}"
    out.mkdir(parents=True)
    sql = out / "database.sql"
    with open(sql, "wb") as f:
        subprocess.run([*db_client(MYSQLDUMP), "--single-transaction", "--no-tablespaces", "--routines", DB_NAME],
                       stdout=f, env=env(), check=True, stderr=subprocess.PIPE)
    with open(sql, "rb") as src, gzip.open(out / "database.sql.gz", "wb", compresslevel=6) as dst:
        shutil.copyfileobj(src, dst)
    sql.unlink()
    uploads = WP / "wp-content" / "uploads"
    with tarfile.open(out / "uploads.tar.gz", "w:gz") as tar:
        tar.add(uploads, arcname="uploads")
    build = STAGING / "BUILD.json"
    manifest = {"taken": now(), "label": label, "environment": "staging", "database": DB_NAME,
                "build": json.loads(build.read_text(encoding="utf-8")) if build.exists() else None,
                "files": {n: {"bytes": (out / n).stat().st_size, "sha256": sha256(out / n)}
                          for n in ("database.sql.gz", "uploads.tar.gz")},
                "fingerprint": fingerprint()}
    (out / "manifest.json").write_text(json.dumps(manifest, indent=1) + "\n", encoding="utf-8")
    sets = sorted(p for p in (STAGING / "backups").iterdir() if p.is_dir())
    for old in sets[:-RETENTION]:
        shutil.rmtree(old)
    say(f"backup set {out.name}: " + ", ".join(f"{n} {v['bytes']} bytes" for n, v in manifest["files"].items())
        + f"; kept {min(len(sets), RETENTION)} of {len(sets)} sets (retention {RETENTION})")
    return out


def cmd_backup(a) -> None:
    backup(a.label)


def restore(source: Path) -> None:
    guard_destructive()
    manifest = json.loads((source / "manifest.json").read_text(encoding="utf-8"))
    if manifest["environment"] != "staging" or manifest["database"] != DB_NAME:
        raise Refused("this backup set was not taken from staging")
    for name, meta in manifest["files"].items():
        if sha256(source / name) != meta["sha256"]:
            raise SystemExit(f"{name} does not match its checksum: the backup set is damaged")
    client = db_client()
    sh([*client, "-e", f"DROP DATABASE `{DB_NAME}`; CREATE DATABASE `{DB_NAME}` CHARACTER SET utf8mb4 "
                       "COLLATE utf8mb4_unicode_520_ci;"])
    # Unpacked to a real file first: a child process reads the file handle, which for a gzip stream is the
    # compressed bytes. The first restore test failed on exactly that.
    plain = STAGING / "run" / "restore.sql"
    with gzip.open(source / "database.sql.gz", "rb") as packed, open(plain, "wb") as out:
        shutil.copyfileobj(packed, out)
    try:
        with open(plain, "rb") as dump:
            loaded = subprocess.run([*client, DB_NAME], stdin=dump, env=env(), capture_output=True)
        if loaded.returncode:
            raise SystemExit(f"the database did not load: {loaded.stderr.decode('utf-8', 'replace')[-600:]}")
    finally:
        plain.unlink(missing_ok=True)
    uploads = WP / "wp-content" / "uploads"
    if uploads.exists():
        shutil.rmtree(uploads)
    with tarfile.open(source / "uploads.tar.gz") as tar:
        extract(tar, WP / "wp-content")
    wp("cache", "flush")
    wp("rewrite", "flush")


def cmd_restore(a) -> None:
    source = STAGING / "backups" / a.set
    if not source.is_dir():
        raise SystemExit(f"no backup set {a.set}")
    restore(source)
    say(f"restored {a.set}")


def http_checks() -> dict:
    """What a visitor gets. The status alone proves nothing, because WordPress with no tables answers 200 with its
    install screen, so each answer also records where it landed and whether it is the seeded store."""
    out = {}
    for path in ("/", "/ar/", "/shop/", "/product/sample-product-01/", "/wp-login.php"):
        try:
            with urllib.request.urlopen(LOCAL_URL + path, timeout=90) as r:
                body = r.read().decode("utf-8", "replace")
                out[path] = {"status": r.status, "landed": r.url.replace(LOCAL_URL, ""),
                             "install_screen": "install.php" in r.url or "wp-admin/install" in body,
                             "seeded_product": "Sample Product" in body or "منتج تجريبي" in body}
        except urllib.error.HTTPError as e:
            out[path] = {"status": e.code}
        except OSError as e:
            out[path] = {"status": f"no answer: {e}"}
    return out


def cmd_restore_test(a) -> None:
    """The restore is performed, not described: back up, destroy, prove the damage, restore, compare."""
    guard_destructive()
    if not pid():
        raise Refused("the staging web server is not running: run `up`")
    record: dict = {"started": now(), "environment": "staging", "database": DB_NAME, "steps": []}

    def step(name: str, **facts) -> None:
        record["steps"].append({"step": name, "at": now(), **facts})
        say(f"{name}: {json.dumps(facts, ensure_ascii=False)[:300]}")

    before, http_before = fingerprint(), http_checks()
    step("1 fingerprint before", fingerprint=before, http=http_before)
    source = backup("restore-test")
    manifest = json.loads((source / "manifest.json").read_text(encoding="utf-8"))
    step("2 backup taken", set=source.name, files=manifest["files"])

    client = [*db_client(), "-N"]
    sh([*client, "-e", f"DROP DATABASE `{DB_NAME}`; CREATE DATABASE `{DB_NAME}`;"])
    shutil.rmtree(WP / "wp-content" / "uploads")
    (WP / "wp-content" / "uploads").mkdir()
    tables = sh([*client, "-e", f"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='{DB_NAME}';"])
    step("3 staging destroyed", tables_left=int(tables.stdout.strip()),
         upload_files_left=sum(1 for p in (WP / "wp-content" / "uploads").rglob("*") if p.is_file()),
         http=http_checks())

    started = time.time()
    restore(source)
    step("4 restored from the backup set", seconds=round(time.time() - started, 1))
    after, http_after = fingerprint(), http_checks()
    # Volatile tables are restored but not compared: see fingerprint.php.
    compared = [k for k in sorted(set(before) | set(after)) if k != "rows_volatile"]
    same = all(before.get(k) == after.get(k) for k in compared)
    step("5 fingerprint after", fingerprint=after, http=http_after)
    record.update({"finished": now(), "fingerprints_identical": same, "http_identical": http_after == http_before,
                   "compared": compared, "not_compared": {"rows_volatile": [before.get("rows_volatile"),
                                                                              after.get("rows_volatile")]},
                   "differences": {k: [before.get(k), after.get(k)] for k in compared
                                   if before.get(k) != after.get(k)},
                   "result": "PASS" if same and http_after == http_before else "FAIL"})
    evidence = STAGING / "evidence" / f"restore-test-{dt.datetime.now().strftime('%Y%m%d-%H%M%S')}.json"
    evidence.write_text(json.dumps(record, indent=1, ensure_ascii=False) + "\n", encoding="utf-8")
    say(f"restore test: {record['result']}. Evidence: {evidence}")
    if record["result"] != "PASS":
        raise SystemExit(1)


# ---- tunnel -------------------------------------------------------------------------------------------------

def cmd_tunnel_config(a) -> None:
    guard_tree()
    host = a.hostname.strip().lower()
    if not re.fullmatch(r"[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+", host):
        raise SystemExit(f"{host!r} is not a host name")
    f = STAGING / "allowed-hosts.txt"
    lines = f.read_text(encoding="utf-8").splitlines()
    if host not in lines:
        f.write_text("\n".join(lines + [host]) + "\n", encoding="utf-8")
    (STAGING / "run" / "cloudflared.yml").write_text(render("cloudflared.yml.tmpl", {
        "HOSTNAME": host, "PORT": PORT, "STAGING_ROOT": STAGING.as_posix()}), encoding="utf-8", newline="\n")
    say(f"{host} is allowed. Tunnel configuration: {STAGING / 'run' / 'cloudflared.yml'}")
    say("See docs/staging-environment.md, 'The Cloudflare Tunnel', for the commands that follow the sign-in.")


def main(argv: list[str] | None = None) -> int:
    ap = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    sub = ap.add_subparsers(dest="command", required=True)
    b = sub.add_parser("build")
    b.add_argument("--ref", default="origin/main")
    b.add_argument("--code-only", action="store_true",
                   help="refresh only the site plugin, the theme and the staging plugin: a minute, not ten")
    for name in ("db-create", "up", "down", "status", "reset", "seed", "restore-test", "parity"):
        sub.add_parser(name)
    sub.add_parser("backup").add_argument("--label", default="manual")
    sub.add_parser("restore").add_argument("set")
    sub.add_parser("tunnel-config").add_argument("hostname")
    a = ap.parse_args(argv)
    if hasattr(sys.stdout, "reconfigure"):
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    globals()["cmd_" + a.command.replace("-", "_")](a)
    return 0


if __name__ == "__main__":
    sys.exit(main())

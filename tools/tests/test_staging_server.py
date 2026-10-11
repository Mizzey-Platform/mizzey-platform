"""The staging tool on the developer's server keeps the guards of the local copy (D-16).

`mizzey-site/tests/staging/staging_server.py` runs PHP and WP-CLI inside the site's container through
`docker exec`. Nothing here has a container, so these tests replace the one function that starts a process and
look at what the tool would have run, and at what it refuses to run.

What they cannot check: that the commands do on the server what they say. That is the first reset, the restore
test and `staging.py check` on the server itself, on MariaDB 11.4, and it is recorded when it has been run.
"""

from __future__ import annotations

import http.server
import importlib
import io
import json
import os
import subprocess
import sys
import tarfile
import tempfile
import threading
import unittest
from pathlib import Path
from unittest import mock

ROOT = Path(__file__).resolve().parents[2]
STAGING_DIR = ROOT / "mizzey-site" / "tests" / "staging"

sys.path.insert(0, str(STAGING_DIR))
srv = importlib.import_module("staging_server")
local = importlib.import_module("staging")

MANIFEST = {
    "tag": "v0.1.0", "commit": "a" * 40, "wordpress": "7.1.2", "corex": {"version": "v0.42.0"},
    "plugins": {"server_supplied": [
        {"slug": "sitepress-multilingual-cms", "version": "4.9.7", "main_file": "sitepress-multilingual-cms/sitepress.php"},
        {"slug": "woocommerce-multilingual", "version": "5.5.7", "main_file": "woocommerce-multilingual/wpml-woocommerce.php"},
    ]},
    "runtime_writable": ["wp-content/uploads", "wp-content/languages/wpml"],
}


def staging_config() -> dict:
    return {"DB_NAME": "mizzeystaging", "DB_USER": "mizzeystaging", "DB_HOST": "mariadb", "DB_PASSWORD": "s3cret-db",
            "table_prefix": "mzs_", "WP_ENVIRONMENT_TYPE": "staging", "MIZZEY_STAGING": True,
            "MIZZEY_STAGING_ROOT": "/srv/sites/mizzeystaging/shared",
            "WPMU_PLUGIN_DIR": "/srv/sites/mizzeystaging/staging-layer/staging/mu-plugins",
            "WP_HTTP_BLOCK_EXTERNAL": True, "DISALLOW_FILE_MODS": True, "WP_HOME": "https://staging.mizzey.com"}


class Container:
    """Stands in for `subprocess.run`. Records every command and answers the few the guards read."""

    def __init__(self):
        self.calls: list[dict] = []
        self.site = "mizzeystaging"
        self.config = staging_config()
        self.writable = {f"/srv/sites/mizzeystaging/current/{p}" for p in MANIFEST["runtime_writable"]}
        self.tables = "mzs_posts\tBASE TABLE\nmzs_view\tVIEW"
        self.wp_answers: dict[str, str] = {}

    def __call__(self, command, stdin=None, input=None, stdout=None, stderr=None, check=False):
        self.calls.append({"command": list(command), "input": input,
                           "env": {k: v for k, v in os.environ.items() if k.startswith("MIZZEY_")}})
        code, out = 0, ""
        if command[0] == "git":
            out = "b" * 40
        else:
            inner = command[command.index(srv.CONTAINER) + 1:]
            if inner[0] == "printenv":
                out = self.site
            elif inner[0] == "test":
                code = 0 if inner[2] in self.writable else 1
            elif inner[0] == "wp" and "config" in inner:
                out = json.dumps([{"name": k, "value": v} for k, v in self.config.items()])
            elif inner[0] == "wp":
                out = next((answer for key, answer in self.wp_answers.items() if key in " ".join(inner)), "")
            elif inner[0] == "sh" and any("information_schema.tables" in part and "table_type" in part for part in inner):
                out = self.tables
        return subprocess.CompletedProcess(command, code, out.encode("utf-8"), b"")

    def commands(self) -> list[str]:
        return [" ".join(call["command"]) for call in self.calls]


class OnTheServer(unittest.TestCase):
    """A site folder with one deployed release, a state folder, and a container that answers."""

    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        base = Path(self.tmp.name).resolve()
        self.site = base / "sites" / "mizzeystaging"
        self.release = self.site / "releases" / "v0.1.0"
        plugins = self.release / "wp-content" / "plugins"
        for main_file, version in (("sitepress-multilingual-cms/sitepress.php", "4.9.7"),
                                   ("woocommerce-multilingual/wpml-woocommerce.php", "5.5.7")):
            (plugins / main_file).parent.mkdir(parents=True)
            (plugins / main_file).write_text(f"<?php\n/**\n * Plugin Name: x\n * Version: {version}\n */\n", encoding="utf-8")
        (self.release / "mizzey-release.json").write_text(json.dumps(MANIFEST), encoding="utf-8")
        local.link(self.release, self.site / "current")
        self.addCleanup(lambda: os.rmdir(self.site / "current") if os.name == "nt" else os.unlink(self.site / "current"))
        self.container = Container()
        for name, value in {"HOST_SITE": self.site, "LAYER": self.site / "staging-layer", "STATE": base / "state",
                            "run": self.container, "say": lambda *lines: None}.items():
            patcher = mock.patch.object(srv, name, value)
            patcher.start()
            self.addCleanup(patcher.stop)
        confirm = mock.patch.dict(os.environ, {"MIZZEY_CONFIRM_STAGING": "yes"})
        confirm.start()
        self.addCleanup(confirm.stop)

    def refused(self, fragment: str, call=None) -> None:
        with self.assertRaises(SystemExit) as caught:
            (call or srv.guard_destructive)()
        self.assertIn(fragment, str(caught.exception))


class DestructiveCommandsRefuse(OnTheServer):

    def test_the_staging_copy_with_the_confirmation_passes(self):
        srv.guard_destructive()

    def test_without_the_confirmation(self):
        with mock.patch.dict(os.environ, {"MIZZEY_CONFIRM_STAGING": "no"}):
            self.refused("set MIZZEY_CONFIRM_STAGING=yes")

    def test_another_database(self):
        self.container.config["DB_NAME"] = "mizzey"
        self.refused("DB_NAME is 'mizzey'")

    def test_another_database_host(self):
        self.container.config["DB_HOST"] = "db.example.com"
        self.refused("DB_HOST is 'db.example.com'")

    def test_another_table_prefix(self):
        self.container.config["table_prefix"] = "wp_"
        self.refused("table_prefix is 'wp_'")

    def test_an_environment_that_is_not_staging(self):
        self.container.config["WP_ENVIRONMENT_TYPE"] = "production"
        self.refused("WP_ENVIRONMENT_TYPE is 'production'")

    def test_the_staging_flag_off(self):
        self.container.config["MIZZEY_STAGING"] = False
        self.refused("MIZZEY_STAGING is not switched on")

    def test_outside_requests_allowed(self):
        del self.container.config["WP_HTTP_BLOCK_EXTERNAL"]
        self.refused("WP_HTTP_BLOCK_EXTERNAL is not switched on")

    def test_an_address_that_is_not_staging(self):
        self.container.config["WP_HOME"] = "https://mizzey.com"
        self.refused("is not a staging address")

    def test_a_container_that_serves_another_site(self):
        self.container.site = "mizzey"
        self.refused("says its site is 'mizzey'")

    def test_a_folder_that_is_not_a_release_of_the_pipeline(self):
        (self.release / "mizzey-release.json").unlink()
        self.refused("is not a release made by the release pipeline")

    def test_every_destructive_command_asks_the_guard_first(self):
        self.container.config["DB_NAME"] = "mizzey"
        args = mock.Mock(set="20261011-000000-manual")
        (srv.state("backups", args.set)).mkdir(parents=True)
        (srv.state("backups", args.set) / "manifest.json").write_text("{}", encoding="utf-8")
        for command in (srv.cmd_reset, srv.cmd_seed, srv.cmd_restore, srv.cmd_restore_test):
            before = len(self.container.calls)
            self.refused("DB_NAME is 'mizzey'", lambda: command(args))
            ran = self.container.commands()[before:]
            self.assertFalse([c for c in ran if "DROP" in c or "core install" in c or "-delete" in c], command.__name__)

    def test_the_commands_the_server_owns_are_refused_by_name(self):
        for command in ("up", "down", "db-create", "tunnel-config"):
            with self.assertRaises(SystemExit) as caught:
                srv.main([command])
            self.assertIn("is not run on the server", str(caught.exception))
        self.assertEqual(self.container.calls, [])


class NoSecretIsAnArgument(OnTheServer):

    def test_a_generated_password_reaches_php_by_name_only(self):
        srv.with_secret("MIZZEY_ADMIN_PASSWORD", "generated-pass", "eval", "1;")
        call = self.container.calls[-1]
        self.assertNotIn("generated-pass", " ".join(call["command"]))
        self.assertEqual(call["command"][2:4], ["-e", "MIZZEY_ADMIN_PASSWORD"])
        self.assertEqual(call["env"]["MIZZEY_ADMIN_PASSWORD"], "generated-pass")
        self.assertNotIn("MIZZEY_ADMIN_PASSWORD", os.environ)

    def test_the_database_client_takes_its_password_from_the_container(self):
        srv.sql("SELECT 1;")
        command = " ".join(self.container.calls[-1]["command"])
        self.assertIn('MYSQL_PWD="$DB_PASSWORD"', command)
        self.assertNotIn("s3cret-db", command)
        self.assertNotIn("-p", self.container.calls[-1]["command"])


class TheDatabaseIsEmptiedNotDropped(OnTheServer):

    def test_every_table_and_view_is_dropped_and_the_database_is_kept(self):
        self.assertEqual(srv.empty_database(), 2)
        statement = self.container.calls[-1]["command"][-2]
        self.assertIn("DROP TABLE IF EXISTS `mzs_posts`", statement)
        self.assertIn("DROP VIEW IF EXISTS `mzs_view`", statement)
        self.assertNotIn("DROP DATABASE", " ".join(self.container.commands()))

    def test_a_table_name_that_could_end_the_statement_is_refused(self):
        self.container.tables = "mzs_posts`; DROP DATABASE x; --\tBASE TABLE"
        before = len(self.container.calls)
        with self.assertRaises(SystemExit):
            srv.empty_database()
        self.assertEqual(len(self.container.calls), before + 1)


class TheDeployedReleaseIsChecked(OnTheServer):

    def test_the_listed_plugins_in_place_at_their_versions(self):
        self.assertEqual(srv.supplied_plugin_problems(self.release, MANIFEST), [])

    def test_a_listed_plugin_that_is_missing(self):
        (self.release / "wp-content" / "plugins" / "sitepress-multilingual-cms" / "sitepress.php").unlink()
        self.assertEqual(srv.supplied_plugin_problems(self.release, MANIFEST),
                         ["sitepress-multilingual-cms: the release lists 4.9.7, the server has no copy"])

    def test_a_listed_plugin_at_another_version(self):
        main = self.release / "wp-content" / "plugins" / "woocommerce-multilingual" / "wpml-woocommerce.php"
        main.write_text("<?php\n/* Plugin Name: x\n * Version: 5.6.0\n */", encoding="utf-8")
        self.assertEqual(srv.supplied_plugin_problems(self.release, MANIFEST),
                         ["woocommerce-multilingual: the release lists 5.5.7, the server has 5.6.0"])

    def test_a_manifest_that_points_outside_the_plugins_folder(self):
        manifest = {"plugins": {"server_supplied": [{"slug": "x", "version": "1", "main_file": "../../wp-config.php"}]}}
        self.assertIn("is not a plugin file", srv.supplied_plugin_problems(self.release, manifest)[0])

    def test_a_runtime_path_php_cannot_write(self):
        self.container.writable.discard("/srv/sites/mizzeystaging/current/wp-content/languages/wpml")
        self.assertEqual(srv.writable_problems(MANIFEST),
                         ["wp-content/languages/wpml is not writable by PHP: link it to a folder under shared/"])

    def test_a_release_php_can_write(self):
        self.container.writable.add("/srv/sites/mizzeystaging/current/index.php")
        self.assertIn("meant to be read-only", srv.writable_problems(MANIFEST)[0])

    def test_build_records_the_release_and_fails_on_a_problem(self):
        (self.release / "wp-content" / "plugins" / "sitepress-multilingual-cms" / "sitepress.php").unlink()
        with self.assertRaises(SystemExit) as caught:
            srv.cmd_build(None)
        self.assertIn("the server has no copy", str(caught.exception))
        build = json.loads((srv.STATE / "BUILD.json").read_text(encoding="utf-8"))
        self.assertEqual((build["tag"], build["commit"], build["tool_is_the_release_commit"]),
                         ("v0.1.0", "a" * 40, False))


class TheStagingLayer(OnTheServer):

    def test_the_plugin_and_the_scripts_land_where_the_container_reads_them(self):
        installed = srv.install_layer()
        for expected in ("staging/mu-plugins/mizzey-staging.php", "staging/fingerprint.php",
                         "staging/wpml-remote-config.php", "staging/server-admin-visit.php",
                         "staging/server-probe.php", "staging/seed/10-users.php",
                         "integration/baseline/setup.php", "integration/baseline/wpml-settings.php"):
            self.assertIn(expected, installed)
            self.assertTrue((srv.LAYER / expected).is_file(), expected)

    def test_every_seed_step_the_reset_runs_is_installed(self):
        installed = srv.install_layer()
        for script in srv.SEED_SCRIPTS:
            self.assertIn(f"staging/seed/{script}", installed)

    def test_the_fingerprint_finds_the_settings_file_it_requires(self):
        srv.install_layer()
        source = (srv.LAYER / "staging" / "fingerprint.php").read_text(encoding="utf-8")
        self.assertIn("__DIR__ . '/../integration/baseline/wpml-settings.php'", source)
        self.assertTrue((srv.LAYER / "staging" / ".." / "integration" / "baseline" / "wpml-settings.php").is_file())

    def test_nothing_from_the_layer_is_a_test_scenario_or_a_tool(self):
        installed = srv.install_layer()
        self.assertFalse([name for name in installed if not name.endswith((".php", ".json"))])
        self.assertFalse([name for name in installed if "admin-visit.php" in name and "server-" not in name])

    def test_a_file_the_tool_no_longer_ships_is_removed(self):
        srv.install_layer()
        stale = srv.LAYER / "staging" / "old-script.php"
        stale.write_text("<?php", encoding="utf-8")
        srv.install_layer()
        self.assertFalse(stale.exists())

    def test_the_carried_configuration_is_installed_and_the_settings_file_is_not(self):
        for name in srv.PARITY_FILES:
            srv.state("parity").mkdir(exist_ok=True)
            srv.state("parity", name).write_text("{}", encoding="utf-8")
        installed = srv.install_layer()
        self.assertIn("parity/wpml-remote-config.json", installed)
        self.assertNotIn("parity/wpml-development-settings.json", installed)

    def test_the_configuration_names_the_folder_the_plugin_is_installed_in(self):
        srv.install_layer()
        with mock.patch("sys.stdout", new_callable=io.StringIO) as printed:
            srv.cmd_server_config(None)
        config = printed.getvalue()
        self.assertIn(f"define( 'WPMU_PLUGIN_DIR', '{srv.in_layer('staging', 'mu-plugins')}' );", config)
        self.assertTrue((srv.LAYER / "staging" / "mu-plugins" / "mizzey-staging.php").is_file())

    def test_the_configuration_it_prints_is_the_one_its_guard_accepts(self):
        with mock.patch("sys.stdout", new_callable=io.StringIO) as printed:
            srv.cmd_server_config(None)
        config = printed.getvalue()
        for fragment in ("$table_prefix = 'mzs_';", "define( 'WP_ENVIRONMENT_TYPE', 'staging' );",
                         "define( 'MIZZEY_STAGING', true );", "define( 'WP_HTTP_BLOCK_EXTERNAL', true );",
                         "define( 'DISALLOW_FILE_MODS', true );",
                         "define( 'MIZZEY_STAGING_ROOT', '/srv/sites/mizzeystaging/shared' );",
                         "$mizzey_public = 'staging.mizzey.com';", "$mizzey_local  = 'mizzey-staging.localhost';"):
            self.assertIn(fragment, config)
        self.assertNotIn("{{", config)
        self.assertNotIn("DB_PASSWORD', '", config)


class TranslationSettingsFromDevelopment(OnTheServer):

    def carried(self, plugins: dict) -> None:
        srv.state("parity").mkdir(exist_ok=True)
        srv.state("parity", "wpml-remote-config.json").write_text(json.dumps({"index": {}, "plugins": plugins}),
                                                                  encoding="utf-8")

    def test_a_reset_without_the_carried_files_is_refused_before_anything_is_destroyed(self):
        self.refused("parity-export", lambda: srv.cmd_reset(None))
        self.assertFalse([c for c in self.container.commands() if "DROP" in c or "core install" in c])

    def test_the_configuration_that_landed_must_be_the_one_in_the_file(self):
        self.carried({"woocommerce-multilingual": "<wpml-config/>"})
        self.container.wp_answers["wpml-remote-config.php import"] = json.dumps(
            {"files": {"plugins/woocommerce-multilingual": "0" * 32}})
        with self.assertRaises(SystemExit) as caught:
            srv.carry_wpml_config()
        self.assertIn("did not carry", str(caught.exception))

    def test_the_configuration_is_imported_from_the_layer(self):
        import hashlib
        self.carried({"woocommerce-multilingual": "<wpml-config/>"})
        self.container.wp_answers["wpml-remote-config.php import"] = json.dumps(
            {"files": {"plugins/woocommerce-multilingual": hashlib.md5(b"<wpml-config/>").hexdigest()}})
        srv.carry_wpml_config()
        self.assertIn("/srv/sites/mizzeystaging/staging-layer/parity/wpml-remote-config.json",
                      self.container.calls[-1]["command"])

    def test_a_setting_that_differs_from_development_fails_parity_and_is_named(self):
        srv.state("parity").mkdir(exist_ok=True)
        srv.state("parity", "wpml-development-settings.json").write_text(
            json.dumps({"settings": {"default_language": "en"}}), encoding="utf-8")
        self.container.wp_answers["wpml-settings.php"] = json.dumps({"settings": {"default_language": "ar"}})
        with self.assertRaises(SystemExit) as caught:
            srv.cmd_parity(None)
        self.assertIn('settings.default_language: development "en", staging "ar"', str(caught.exception))


class TheUploadsArchive(unittest.TestCase):

    def archive(self, members: dict[str, bytes], link: str | None = None) -> Path:
        tmp = tempfile.TemporaryDirectory()
        self.addCleanup(tmp.cleanup)
        path = Path(tmp.name) / "uploads.tar.gz"
        with tarfile.open(path, "w:gz") as tar:
            for name, body in members.items():
                info = tarfile.TarInfo(name)
                info.size = len(body)
                tar.addfile(info, io.BytesIO(body))
            if link:
                info = tarfile.TarInfo(link)
                info.type, info.linkname = tarfile.SYMTYPE, "/etc/passwd"
                tar.addfile(info)
        return path

    def test_an_archive_of_uploads_only_is_accepted(self):
        srv.check_uploads_archive(self.archive({"uploads/2026/10/a.png": b"png"}))

    def test_a_member_outside_uploads_is_refused(self):
        for name in ("mail/a.json", "uploads/../../current/index.php", "/etc/passwd"):
            with self.assertRaises(SystemExit):
                srv.check_uploads_archive(self.archive({name: b"x"}))

    def test_a_link_is_refused(self):
        with self.assertRaises(SystemExit):
            srv.check_uploads_archive(self.archive({}, link="uploads/link"))


class PagesAreFetchedOnTheServerItself(unittest.TestCase):
    """A real request to a web server on the loopback address, standing in for the server's own."""

    @classmethod
    def setUpClass(cls):
        cls.seen: list[dict] = []

        class Handler(http.server.BaseHTTPRequestHandler):
            def do_GET(self):
                cls.seen.append({"path": self.path, "host": self.headers.get("Host"), "cookie": self.headers.get("Cookie")})
                routes = {"/old/": (301, "http://mizzey-staging.localhost/new/?a=1"),
                          "/away/": (302, "https://staging.mizzey.com/elsewhere/")}
                status, location = routes.get(self.path, (200, None))
                self.send_response(status)
                if location:
                    self.send_header("Location", location)
                self.send_header("X-Robots-Tag", "noindex, nofollow")
                self.end_headers()
                self.wfile.write(b'<div id="mizzey-staging-banner">STAGING</div>')

            def log_message(self, *args):
                pass

        cls.server = http.server.ThreadingHTTPServer(("127.0.0.1", 0), Handler)
        threading.Thread(target=cls.server.serve_forever, daemon=True).start()
        cls.address = mock.patch.dict(os.environ, {"MIZZEY_STAGING_HTTP": f"127.0.0.1:{cls.server.server_port}"})
        cls.address.start()

    @classmethod
    def tearDownClass(cls):
        cls.address.stop()
        cls.server.shutdown()
        cls.server.server_close()

    def setUp(self):
        self.seen.clear()

    def test_the_request_names_the_local_staging_host(self):
        answer = srv.fetch("/shop/")
        self.assertEqual((answer["status"], answer["robots"]), (200, "noindex, nofollow"))
        self.assertEqual(self.seen[0]["host"], "mizzey-staging.localhost")

    def test_a_redirect_within_the_local_host_is_followed(self):
        answer = srv.fetch("/old/")
        self.assertEqual((answer["status"], answer["landed"]), (200, "/new/?a=1"))

    def test_a_redirect_to_the_public_address_is_reported_and_not_followed(self):
        answer = srv.fetch("/away/")
        self.assertEqual((answer["status"], answer["landed"]), (302, "/away/"))
        self.assertEqual(len(self.seen), 1)

    def test_sign_in_cookies_are_sent_encoded(self):
        srv.fetch("/wp-admin/plugins.php", cookies={"wordpress_abc": "visit_1|1790000000|token|hmac"})
        self.assertEqual(self.seen[0]["cookie"], "wordpress_abc=visit_1%7C1790000000%7Ctoken%7Chmac")


class TheProfile(unittest.TestCase):

    def test_it_can_be_named_and_a_wrong_name_is_refused(self):
        with mock.patch.dict(os.environ, {"MIZZEY_STAGING_PROFILE": "server"}):
            self.assertEqual(local.profile(), "server")
        with mock.patch.dict(os.environ, {"MIZZEY_STAGING_PROFILE": "production"}):
            with self.assertRaises(SystemExit):
                local.profile()

    def test_the_server_keeps_the_local_copys_plugins_and_retention(self):
        self.assertIs(srv.local.ACTIVE_PLUGINS, local.ACTIVE_PLUGINS)
        self.assertEqual(srv.local.RETENTION, 7)


if __name__ == "__main__":
    unittest.main()

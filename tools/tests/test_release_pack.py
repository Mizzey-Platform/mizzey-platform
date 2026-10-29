"""The release archive holds what a deploy needs, and never what the server or a licence owns (D-16).

`tools/release_pack.py` adds the wordpress.org plugins and language packs to the tree `tools/build-dist.mjs`
built, and writes `mizzey-<tag>.zip` with its checksum. These tests build a small tree and small archives in a
temporary folder and hand the packer a fetcher that reads them, so nothing is downloaded.

What they cannot check: that wordpress.org serves the versions `stack.lock.json` records. The release workflow
finds that out, and the packer refuses a plugin whose own header disagrees with the lock.
"""

from __future__ import annotations

import datetime as dt
import hashlib
import io
import json
import stat
import tarfile
import tempfile
import unittest
import zipfile
from pathlib import Path

from tools import release_pack as rp

MOMENT = dt.datetime(2026, 10, 11, 12, 0, 0, tzinfo=dt.timezone.utc)
STACK = {
    "wordpress": {"version": "7.1", "observed_runtime": "7.1.2"},
    "woocommerce": {"version": "11.1.0"},
    "bosta": {"version": "4.5.7"},
    "wp-2fa": {"version": "4.2.1"},
    "wpml": {"version": "4.9.7"},
    "wpml-string-translation": {"version": "3.5.4"},
    "wpml-media-translation": {"version": "3.1.2"},
    "wpml-cms-nav": {"version": "1.5.8"},
    "wcml": {"version": "5.5.7"},
}


def zipped(files: dict[str, bytes], links: tuple[str, ...] = ()) -> bytes:
    held = io.BytesIO()
    with zipfile.ZipFile(held, "w") as archive:
        for name, body in files.items():
            info = zipfile.ZipInfo(name)
            if name in links:
                info.external_attr = (stat.S_IFLNK | 0o777) << 16
            archive.writestr(info, body)
    return held.getvalue()


def plugin_zip(slug: str, main_file: str, version: str, extra: dict[str, bytes] | None = None) -> bytes:
    header = f"<?php\n/**\n * Plugin Name: {slug}\n * Version: {version}\n */\n".encode()
    return zipped({f"{slug}/{main_file}": header, f"{slug}/readme.txt": b"readme", **(extra or {})})


class Fixture(unittest.TestCase):
    """A built tree, a lock, and the four archives wordpress.org would serve for it."""

    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.root = Path(self.tmp.name) / "repo"
        self.dist = Path(self.tmp.name) / "dist"
        self.out = Path(self.tmp.name) / "out"
        self.root.mkdir()
        (self.root / "stack.lock.json").write_text(json.dumps({"components": STACK}), encoding="utf-8")
        (self.root / "corex.lock").write_text(json.dumps(
            {"version": "v0.42.0", "commit": "c" * 40, "wordpress": "7.1", "php": ">=8.3"}), encoding="utf-8")
        for path, body in {
            "index.php": "<?php // front",
            "wp-includes/version.php": "<?php\n$wp_version = '7.1.2';\n",
            "wp-content/plugins/mizzey-site/mizzey-site.php": "<?php // site",
            "wp-content/plugins/corex-core/corex-core.php": "<?php // corex",
            "wp-content/themes/mizzey-theme/style.css": "/* theme */",
            "wp-content/vendor/autoload.php": "<?php // autoload",
            "corex-release.json": json.dumps({"plugins": ["mizzey-site", "corex-core"], "themes": ["mizzey-theme"]}),
        }.items():
            (self.dist / path).parent.mkdir(parents=True, exist_ok=True)
            (self.dist / path).write_text(body, encoding="utf-8")
        self.served = {
            rp.PLUGIN_URL.format(slug="woocommerce", version="11.1.0"):
                plugin_zip("woocommerce", "woocommerce.php", "11.1.0"),
            rp.PLUGIN_URL.format(slug="bosta-woocommerce", version="4.5.7"):
                plugin_zip("bosta-woocommerce", "bosta-woocommerce.php", "4.5.7"),
            rp.PLUGIN_URL.format(slug="wp-2fa", version="4.2.1"):
                plugin_zip("wp-2fa", "wp-2fa.php", "4.2.1"),
            rp.CORE_PACK_URL.format(version="7.1.2", locale="ar"): zipped({"ar.mo": b"core", "admin-ar.mo": b"admin"}),
            rp.PLUGIN_PACK_URL.format(slug="woocommerce", version="11.1.0", locale="ar"):
                zipped({"woocommerce-ar.mo": b"woo"}),
        }
        self.asked: list[str] = []

    def fetch(self, url: str) -> bytes:
        self.asked.append(url)
        return self.served[url]

    def pack(self, tag: str = "v0.1.0") -> dict:
        origin = rp.Origin(root=self.root, fetch=self.fetch, commit="a" * 40, moment=MOMENT)
        return rp.pack(tag, self.dist, self.out, origin)

    def refused(self, fragment: str, tag: str = "v0.1.0") -> None:
        with self.assertRaises(rp.Refused) as caught:
            self.pack(tag)
        self.assertIn(fragment, str(caught.exception))


class TheArchive(Fixture):

    def test_two_files_are_written_under_the_names_the_server_downloads(self):
        self.pack()
        self.assertEqual(sorted(p.name for p in self.out.iterdir()), ["mizzey-v0.1.0.zip", "mizzey-v0.1.0.zip.sha256"])

    def test_the_checksum_file_is_one_line_sha256sum_can_check(self):
        made = self.pack()
        digest = hashlib.sha256((self.out / "mizzey-v0.1.0.zip").read_bytes()).hexdigest()
        self.assertEqual((self.out / "mizzey-v0.1.0.zip.sha256").read_bytes(),
                         f"{digest}  mizzey-v0.1.0.zip\n".encode())
        self.assertEqual(made["sha256"], digest)

    def test_the_wordpress_root_is_the_archive_root(self):
        self.pack()
        with zipfile.ZipFile(self.out / "mizzey-v0.1.0.zip") as archive:
            names = set(archive.namelist())
        for expected in ("index.php", "wp-includes/version.php", "wp-content/vendor/autoload.php", "corex-release.json",
                         "mizzey-release.json", "wp-content/index.php",
                         "wp-content/plugins/mizzey-site/mizzey-site.php",
                         "wp-content/plugins/woocommerce/woocommerce.php",
                         "wp-content/plugins/bosta-woocommerce/bosta-woocommerce.php",
                         "wp-content/plugins/wp-2fa/wp-2fa.php",
                         "wp-content/languages/ar.mo", "wp-content/languages/plugins/woocommerce-ar.mo"):
            self.assertIn(expected, names)

    def test_what_the_server_owns_is_absent(self):
        self.pack()
        with zipfile.ZipFile(self.out / "mizzey-v0.1.0.zip") as archive:
            names = archive.namelist()
        self.assertNotIn("wp-config.php", names)
        for owned in ("wp-content/uploads", "wp-content/languages/wpml", "wp-content/mu-plugins"):
            self.assertFalse([n for n in names if n == owned or n.startswith(owned + "/")], owned)

    def test_a_local_web_server_file_at_the_root_is_left_out_and_one_a_plugin_ships_is_kept(self):
        (self.dist / ".htaccess").write_text("# local", encoding="utf-8")
        shipped = self.dist / "wp-content" / "plugins" / "corex-core" / "private" / ".htaccess"
        shipped.parent.mkdir()
        shipped.write_text("deny from all", encoding="utf-8")
        self.pack()
        with zipfile.ZipFile(self.out / "mizzey-v0.1.0.zip") as archive:
            names = archive.namelist()
        self.assertNotIn(".htaccess", names)
        self.assertIn("wp-content/plugins/corex-core/private/.htaccess", names)

    def test_every_entry_is_a_plain_file_readable_by_the_web_server(self):
        self.pack()
        with zipfile.ZipFile(self.out / "mizzey-v0.1.0.zip") as archive:
            modes = {info.external_attr >> 16 for info in archive.infolist()}
        self.assertEqual(modes, {stat.S_IFREG | 0o644})

    def test_the_same_inputs_give_the_same_archive(self):
        first = self.pack()["sha256"]
        self.assertEqual(self.pack()["sha256"], first)

    def test_the_built_tree_is_not_changed(self):
        before = sorted(p.relative_to(self.dist).as_posix() for p in self.dist.rglob("*"))
        self.pack()
        self.assertEqual(sorted(p.relative_to(self.dist).as_posix() for p in self.dist.rglob("*")), before)


class NothingPaid(Fixture):
    """While the repository is public the translation plugin family is listed, never fetched, never packed."""

    def test_the_manifest_lists_the_five_plugins_at_the_versions_the_lock_records(self):
        supplied = self.pack()["manifest"]["plugins"]["server_supplied"]
        self.assertEqual({(p["slug"], p["version"], p["main_file"]) for p in supplied}, {
            ("sitepress-multilingual-cms", "4.9.7", "sitepress-multilingual-cms/sitepress.php"),
            ("wpml-string-translation", "3.5.4", "wpml-string-translation/plugin.php"),
            ("wpml-media-translation", "3.1.2", "wpml-media-translation/plugin.php"),
            ("wpml-cms-nav", "1.5.8", "wpml-cms-nav/plugin.php"),
            ("woocommerce-multilingual", "5.5.7", "woocommerce-multilingual/wpml-woocommerce.php"),
        })

    def test_the_manifest_in_the_archive_is_the_one_returned(self):
        made = self.pack()
        with zipfile.ZipFile(self.out / "mizzey-v0.1.0.zip") as archive:
            self.assertEqual(json.loads(archive.read("mizzey-release.json")), made["manifest"])

    def test_no_file_of_the_family_is_in_the_archive(self):
        self.pack()
        with zipfile.ZipFile(self.out / "mizzey-v0.1.0.zip") as archive:
            names = archive.namelist()
        for slug, _ in rp.SERVER_SUPPLIED.values():
            self.assertFalse([n for n in names if n.startswith(f"wp-content/plugins/{slug}/")], slug)

    def test_nothing_is_asked_of_any_host_but_wordpress_org(self):
        self.pack()
        self.assertEqual(len(self.asked), 5)
        for url in self.asked:
            self.assertTrue(url.startswith("https://downloads.wordpress.org/"), url)
            self.assertFalse([slug for slug, _ in rp.SERVER_SUPPLIED.values() if slug in url], url)

    def test_a_built_tree_that_already_holds_one_is_refused(self):
        stray = self.dist / "wp-content" / "plugins" / "sitepress-multilingual-cms" / "sitepress.php"
        stray.parent.mkdir(parents=True)
        stray.write_text("<?php", encoding="utf-8")
        self.refused("paid software")


class Refusals(Fixture):

    def test_a_tag_that_is_not_a_release_tag(self):
        self.refused("not a release tag", tag="0.1.0")
        self.refused("not a release tag", tag="v0.1.0-rc1")

    def test_a_plugin_whose_header_disagrees_with_the_lock(self):
        self.served[rp.PLUGIN_URL.format(slug="woocommerce", version="11.1.0")] = plugin_zip(
            "woocommerce", "woocommerce.php", "11.2.0")
        self.refused("says version 11.2.0, stack.lock.json says 11.1.0")

    def test_a_plugin_archive_with_a_second_folder(self):
        self.served[rp.PLUGIN_URL.format(slug="bosta-woocommerce", version="4.5.7")] = plugin_zip(
            "bosta-woocommerce", "bosta-woocommerce.php", "4.5.7", {"other/file.php": b"<?php"})
        self.refused("must hold one folder named bosta-woocommerce")

    def test_an_archive_member_that_climbs_out_of_its_folder(self):
        self.served[rp.PLUGIN_URL.format(slug="woocommerce", version="11.1.0")] = plugin_zip(
            "woocommerce", "woocommerce.php", "11.1.0", {"woocommerce/../../wp-config.php": b"<?php"})
        self.refused("outside its folder")

    def test_an_archive_member_that_is_a_link(self):
        held = zipped({"woocommerce/woocommerce.php": b"<?php\n/* Plugin Name: w\n * Version: 11.1.0 */",
                       "woocommerce/link": b"/etc/passwd"}, links=("woocommerce/link",))
        self.served[rp.PLUGIN_URL.format(slug="woocommerce", version="11.1.0")] = held
        self.refused("is a link")

    def test_a_language_pack_with_a_folder_in_it(self):
        self.served[rp.CORE_PACK_URL.format(version="7.1.2", locale="ar")] = zipped({"nested/ar.mo": b"x"})
        self.refused("holds files only")

    def test_a_built_tree_on_another_wordpress(self):
        (self.dist / "wp-includes" / "version.php").write_text("<?php\n$wp_version = '7.2';\n", encoding="utf-8")
        self.refused("holds WordPress 7.2, stack.lock.json records 7.1.2")

    def test_a_folder_that_is_not_a_built_tree(self):
        (self.dist / "corex-release.json").unlink()
        self.refused("is not a built tree")

    def test_a_tree_from_a_builder_before_corex_v0_43_4_is_refused(self):
        # That builder left vendor/ at the root, where a host's CoreX no longer looks for it.
        (self.dist / "wp-content" / "vendor" / "autoload.php").unlink()
        (self.dist / "vendor").mkdir()
        (self.dist / "vendor" / "autoload.php").write_text("<?php // autoload", encoding="utf-8")
        self.refused("wp-content/vendor/autoload.php is missing")

    def test_the_manifest_says_which_vendor_tree_the_package_carries(self):
        self.assertEqual(self.pack()["manifest"]["corex"]["vendor"], "production")
        (self.dist / "wp-content" / "vendor" / "pestphp").mkdir()
        self.assertEqual(self.pack("v0.1.1")["manifest"]["corex"]["vendor"], "dev")

    def test_a_configuration_file_in_the_built_tree(self):
        (self.dist / "wp-config.php").write_text("<?php", encoding="utf-8")
        self.refused("wp-config.php must never be in a release")

    def test_uploads_in_the_built_tree(self):
        (self.dist / "wp-content" / "uploads").mkdir()
        (self.dist / "wp-content" / "uploads" / "a.png").write_bytes(b"png")
        self.refused("belongs to the server")

    def test_a_database_dump_in_the_built_tree(self):
        (self.dist / "wp-content" / "dump.sql").write_text("-- dump", encoding="utf-8")
        self.refused("must never be in a release")


class FetchCore(unittest.TestCase):

    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.root = Path(self.tmp.name) / "repo"
        self.root.mkdir()
        (self.root / "stack.lock.json").write_text(json.dumps({"components": STACK}), encoding="utf-8")
        (self.root / "corex.lock").write_text("{}", encoding="utf-8")
        held = io.BytesIO()
        with tarfile.open(fileobj=held, mode="w:gz") as tar:
            for name, body in {"wordpress/index.php": b"<?php", "wordpress/wp-includes/version.php": b"<?php"}.items():
                info = tarfile.TarInfo(name)
                info.size = len(body)
                tar.addfile(info, io.BytesIO(body))
        self.tarball = held.getvalue()
        self.url = rp.CORE_URL.format(version="7.1.2")

    def origin(self, checksum: str) -> rp.Origin:
        served = {self.url: self.tarball, self.url + ".sha1": checksum.encode()}
        return rp.Origin(root=self.root, fetch=served.__getitem__)

    def test_the_tested_version_is_unpacked_without_its_outer_folder(self):
        into = Path(self.tmp.name) / "wp"
        version = rp.fetch_core(into, self.origin(hashlib.sha1(self.tarball).hexdigest()))
        self.assertEqual(version, "7.1.2")
        self.assertTrue((into / "index.php").is_file())
        self.assertTrue((into / "wp-includes" / "version.php").is_file())

    def test_a_download_that_does_not_match_the_published_checksum_is_refused(self):
        with self.assertRaises(rp.Refused):
            rp.fetch_core(Path(self.tmp.name) / "wp", self.origin("0" * 40))
        self.assertFalse((Path(self.tmp.name) / "wp").exists())

    def test_a_place_that_already_holds_files_is_left_alone(self):
        into = Path(self.tmp.name) / "wp"
        into.mkdir()
        (into / "wp-config.php").write_text("<?php", encoding="utf-8")
        with self.assertRaises(rp.Refused):
            rp.fetch_core(into, self.origin(hashlib.sha1(self.tarball).hexdigest()))


if __name__ == "__main__":
    unittest.main()

"""The home page sign-up counts a network address the way the limit means it (spec 066, slice 5; HOME-09).

`MizzeySite\\Signups\\SignupService::network()` is what the per-address limit is keyed by: an IPv4 address as it is, an
IPv6 address by its /64 network. An IPv4-mapped IPv6 address (`::ffff:203.0.113.7`, what a dual-stack server may
report for an IPv4 client) is an IPv4 client: it must be counted as that IPv4 address, not folded into the one /64
every mapped address shares, which would put all IPv4 visitors behind such a server on one counter.

The function needs no WordPress: the service file is loaded with ABSPATH defined, and only this method is called.
"""

from __future__ import annotations

import json
import shutil
import subprocess
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SERVICE = ROOT / "mizzey-site" / "src" / "Signups" / "SignupService.php"

RUN = r"""
define('ABSPATH', __DIR__);
require $argv[1];
$out = [];
foreach (array_slice($argv, 2) as $address) {
    $out[$address] = \MizzeySite\Signups\SignupService::network($address);
}
echo json_encode($out);
"""

CASES = {
    "203.0.113.7": "203.0.113.7",
    "::ffff:203.0.113.7": "203.0.113.7",
    "::FFFF:198.51.100.20": "198.51.100.20",
    "2001:db8:1:2::1": "2001:db8:1:2::/64",
    "2001:db8:1:2:ffff:ffff:ffff:9": "2001:db8:1:2::/64",
    "2001:db8:1:3::1": "2001:db8:1:3::/64",
    "not an address": "not an address",
}


class SignupNetwork(unittest.TestCase):
    def test_each_address_is_counted_by_its_network(self) -> None:
        php = shutil.which("php")
        if php is None:
            self.skipTest("php is not on PATH")
        done = subprocess.run([php, "-r", RUN, str(SERVICE), *CASES], capture_output=True, text=True, encoding="utf-8")
        self.assertEqual(0, done.returncode, done.stderr or done.stdout)
        self.assertEqual(CASES, json.loads(done.stdout))

    def test_two_mapped_ipv4_clients_are_two_counters(self) -> None:
        php = shutil.which("php")
        if php is None:
            self.skipTest("php is not on PATH")
        done = subprocess.run([php, "-r", RUN, str(SERVICE), "::ffff:203.0.113.7", "::ffff:203.0.113.8"],
                              capture_output=True, text=True, encoding="utf-8")
        self.assertEqual(0, done.returncode, done.stderr or done.stdout)
        keys = json.loads(done.stdout)
        self.assertNotEqual(keys["::ffff:203.0.113.7"], keys["::ffff:203.0.113.8"])


if __name__ == "__main__":
    unittest.main()

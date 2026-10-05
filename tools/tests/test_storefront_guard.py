"""Every scenario that fetches a storefront page goes through the storefront guard.

Why this exists. Until 5 October 2026 the development baseline left WooCommerce's "coming soon" page in front of
every store page. Four scenarios fetched store pages as a visitor and passed on that placeholder, on fourteen
fetches, because it answers 200 in the right language with a title and a canonical link
(`docs/2026-10-05-storefront-placeholder-guard.md`). `mizzey-site/tests/integration/_storefront.php` now fails a
scenario that is served a placeholder. That only helps if a new scenario cannot fetch a page without it, which is
what this check holds.

The rule. In a scenario file, a direct HTTP fetch is followed, within a few lines, by a call to
`storefront_refuse_shell(`, or the line of the fetch says why the guard does not apply:
`storefront-guard: exempt, <reason>`. A fetch of a JSON endpoint is the usual reason. Fetches made through the
shared workflows (`_workflows.php`) are administrator requests to wp-admin and the REST API, not storefront pages,
and are outside this rule.

It also holds the other half: the baseline switches the placeholder off, and a scenario exists that proves the
guard against the real placeholder. Deleting either would bring the old state back silently.

It fails closed: a scenario file it cannot read is a failure, not a skip.
"""

from __future__ import annotations

import re
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SCENARIOS = ROOT / "mizzey-site" / "tests" / "integration"
FETCH = re.compile(r"\bwp_remote_(?:get|request|head)\s*\(")
GUARD = "storefront_refuse_shell("
EXEMPT = re.compile(r"storefront-guard: exempt, \S")
# How far below a fetch its guard may sit: a multi-line call, its error branch, and the reading of the body.
WINDOW = 24


def unguarded_fetches(source: str) -> list[int]:
    """Line numbers of fetches that neither reach the guard nor say why they are exempt."""
    lines = source.splitlines()
    found = []
    for number, line in enumerate(lines, start=1):
        if not FETCH.search(line) or EXEMPT.search(line):
            continue
        if not any(GUARD in later for later in lines[number - 1:number - 1 + WINDOW]):
            found.append(number)
    return found


class StorefrontGuard(unittest.TestCase):
    def test_every_page_fetch_in_a_scenario_reaches_the_guard(self) -> None:
        scenarios = sorted(SCENARIOS.glob("t*.php"))
        self.assertTrue(scenarios, "no scenario files were found, so nothing was checked")
        problems = []
        for path in scenarios:
            for number in unguarded_fetches(path.read_text(encoding="utf-8")):
                problems.append(f"{path.relative_to(ROOT).as_posix()}:{number}")
        self.assertEqual([], problems, "a fetch reaches neither storefront_refuse_shell() nor an exemption with its reason")

    def test_the_rule_tells_a_guarded_fetch_from_an_unguarded_one(self) -> None:
        guarded = "$r = wp_remote_get( $url );\n$body = wp_remote_retrieve_body( $r );\nstorefront_refuse_shell( $url, 200, $body );\n"
        exempt = "$r = wp_remote_get( $api ); // storefront-guard: exempt, a JSON endpoint and not a page.\n"
        bare = "$r = wp_remote_get( $url );\n$body = wp_remote_retrieve_body( $r );\n"
        no_reason = "$r = wp_remote_get( $url ); // storefront-guard: exempt, \n"
        far = "$r = wp_remote_get( $url );\n" + "$x = 1;\n" * WINDOW + "storefront_refuse_shell( $url, 200, $body );\n"
        self.assertEqual([], unguarded_fetches(guarded))
        self.assertEqual([], unguarded_fetches(exempt))
        self.assertEqual([1], unguarded_fetches(bare))
        self.assertEqual([1], unguarded_fetches(no_reason))
        self.assertEqual([1], unguarded_fetches(far))

    def test_the_baseline_opens_the_store_and_the_guard_is_proved_against_the_real_placeholder(self) -> None:
        setup = (SCENARIOS / "baseline" / "setup.php").read_text(encoding="utf-8")
        self.assertIn("update_option( 'woocommerce_coming_soon', 'no' );", setup,
                      "the test baseline no longer switches the commerce placeholder off")
        proof = (SCENARIOS / "t27-storefront-guard.php").read_text(encoding="utf-8")
        self.assertIn("update_option( 'woocommerce_coming_soon', 'yes' );", proof,
                      "the guard scenario no longer exercises the real placeholder")


if __name__ == "__main__":
    unittest.main()

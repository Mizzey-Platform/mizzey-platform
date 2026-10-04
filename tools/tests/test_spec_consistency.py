"""A feature's Spec Kit artifacts must not contradict each other about a measured fact.

Why this exists. The 002 bilingual-baseline analysis checked criteria coverage, requirement citations, register
ids and path classes, and passed. External review then found that `spec.md`'s Native coverage still said the
Arabic storefront was not reachable over HTTP, that `lang="ar"` was unresolved, and that a missing `.htaccess`
was an open runtime gap, while `research.md`, `plan.md` and `tasks.md` all recorded the served measurement that
settled all three. Four artifacts, two different stories, and nothing compared the factual claims.

So this does. Each CLAIM below is a fact the artifacts must agree on, expressed as text that must NOT appear
(the stale phrasing) and, where a positive statement is required, text that must appear in the named artifact.
It is deliberately phrase-based rather than clever: a contradiction in an engineering document is a sentence, and
a sentence is what a reader acts on.

It reads; it never writes. Add a claim when a measurement settles something that more than one artifact states.
"""

from __future__ import annotations

import re
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
FEATURE = "specs/002-bilingual-platform-baseline"
ARTIFACTS = ("spec.md", "research.md", "plan.md", "tasks.md", "analysis.md", "checklists/requirements.md")

# Each claim: (name, [stale phrases that must not appear anywhere], {artifact: [phrases that must appear]})
CLAIMS: tuple[tuple[str, list[str], dict[str, list[str]]], ...] = (
    (
        "the Arabic storefront is reachable over HTTP",
        [
            "reachable over HTTP** | **not today",
            "Arabic storefront cannot be reached",
            "/ar/ cannot be served",
        ],
        {
            "spec.md": ['`/ar/` returns 200 with'],
            "research.md": ['dir="rtl" lang="ar"'],
            "plan.md": ['dir="rtl" lang="ar"'],
        },
    ),
    (
        'lang="ar" is verified on a served request',
        [
            "attribute **in Arabic** | **unresolved",
            "The `lang` attribute in Arabic is unresolved",
            'lang="ar" is unresolved',
        ],
        {
            "spec.md": ['Resolved: `lang="ar"` is emitted correctly'],
            "research.md": ["artifact"],
        },
    ),
    (
        "AC-3, AC-4 and AC-6 are met natively and need tests, not implementation",
        [
            "AC-3 requires `lang=\"ar\"`, so the plan has to settle it",
        ],
        {
            "spec.md": ["AC-3, AC-4 and AC-6 are met natively"],
            "plan.md": ["**AC-3, AC-4 and AC-6 are met natively.**"],
            "research.md": ["**Three criteria are met with no code at all.**"],
        },
    ),
    (
        "rewrite configuration is a deterministic baseline prerequisite, not a platform defect",
        [
            "VERIFIED as a configuration gap",
            "achievable here, and not yet configured",
        ],
        {
            "spec.md": ["not a multilingual\nplatform defect", "VERIFIED as\na baseline prerequisite"],
            "tasks.md": ["Do not use\n`wp rewrite flush --hard`"],
        },
    ),
    (
        "AC-5 is technical acceptance, not a restatement of FIX-04",
        [
            "Kept, and flagged here so a reviewer can",
            "It is kept, and flagged, so that it can be disagreed with",
        ],
        {
            "spec.md": ["**Technical acceptance, not a restatement of the row",
                        "**does not mention translation identity**"],
            "analysis.md": ["classified as technical acceptance"],
            "checklists/requirements.md": ["technical acceptance needed to"],
        },
    ),
)

# A measured value stated in more than one artifact must be stated identically.
SHARED_FACTS: tuple[tuple[str, str], ...] = (
    ("the served Arabic document element", 'dir="rtl" lang="ar"'),
    ("the served English document element", 'lang="en-US"'),
)


def read(artifact: str) -> str:
    return (ROOT / FEATURE / artifact).read_text(encoding="utf-8")


def squash(text: str) -> str:
    """Collapse whitespace, so a phrase is found whether or not the line wrapped."""
    return re.sub(r"\s+", " ", text)


class SpecConsistency(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.text = {a: read(a) for a in ARTIFACTS}
        cls.flat = {a: squash(t) for a, t in cls.text.items()}

    def test_every_artifact_exists(self) -> None:
        for a in ARTIFACTS:
            with self.subTest(artifact=a):
                self.assertTrue((ROOT / FEATURE / a).is_file(), f"{FEATURE}/{a} is missing")

    def test_no_artifact_repeats_a_stale_claim(self) -> None:
        """The failure external review caught: one artifact still telling the old story."""
        for name, stale, _required in CLAIMS:
            for phrase in stale:
                needle = squash(phrase)
                for artifact, flat in self.flat.items():
                    with self.subTest(claim=name, artifact=artifact):
                        self.assertFalse(
                            needle in flat,
                            f"{artifact} still says {phrase!r}, which contradicts the settled "
                            f"claim {name!r}")

    def test_each_claim_is_stated_where_it_belongs(self) -> None:
        for name, _stale, required in CLAIMS:
            for artifact, phrases in required.items():
                for phrase in phrases:
                    with self.subTest(claim=name, artifact=artifact):
                        self.assertTrue(
                            squash(phrase) in self.flat[artifact],
                            f"{artifact} no longer states the settled claim {name!r}. "
                            f"Expected to find: {phrase!r}")

    def test_a_shared_measured_value_is_written_the_same_way_everywhere(self) -> None:
        for label, value in SHARED_FACTS:
            holders = [a for a, flat in self.flat.items() if squash(value) in flat]
            with self.subTest(fact=label):
                self.assertGreaterEqual(
                    len(holders), 2,
                    f"{label} ({value}) appears in {len(holders)} artifact(s); it is a shared measured value")

    def test_ac9_is_the_only_criterion_left_unverified(self) -> None:
        """Honesty check: staging is owned by #244, and nothing else may hide behind it."""
        flat = self.flat["spec.md"]
        self.assertTrue(squash("**AC-9 is the only criterion that needs staging.**") in flat,
                        "spec.md no longer states that AC-9 is the only criterion "
                        "needing staging")
        self.assertTrue("UNVERIFIED" in self.text["spec.md"],
                        "spec.md marks nothing UNVERIFIED; the browser matrix must stay so")
        unverified_rows = [
            line for line in self.text["spec.md"].splitlines()
            if line.startswith("|") and "UNVERIFIED" in line
        ]
        self.assertEqual(
            len(unverified_rows), 1,
            f"expected exactly one UNVERIFIED Native coverage row, the browser matrix; found "
            f"{len(unverified_rows)}: {unverified_rows}")
        self.assertTrue("Browser rendering" in unverified_rows[0],
                        f"the one UNVERIFIED row is not the browser matrix: "
                        f"{unverified_rows[0]!r}")

    def test_the_approved_browser_matrix_is_recorded_and_bounded(self) -> None:
        flat = self.flat["spec.md"]
        for browser in ("Chrome", "Safari", "Edge", "Firefox", "Chrome on Android", "Safari on iOS"):
            with self.subTest(browser=browser):
                self.assertTrue(browser in flat,
                                f"{browser} is no longer in the approved matrix")
        for excluded in ("Internet Explorer", "Opera Mini", "in-app browsers"):
            with self.subTest(excluded=excluded):
                self.assertTrue(excluded in flat,
                                f"{excluded} must stay recorded as excluded, not dropped")

    def test_safeguards_are_not_presented_as_deliverables(self) -> None:
        flat = self.flat["spec.md"]
        self.assertTrue(squash("engineering safeguards and evidence") in flat,
                        "spec.md no longer classifies S-1 and S-2 as safeguards and evidence")
        self.assertTrue(squash("not\nclient deliverables") in flat,
                        "spec.md no longer says the safeguards are not client deliverables")

    def test_the_probe_boundaries_are_still_stated(self) -> None:
        """What the pilot and the probes bought must not quietly drop out of the spec."""
        flat = self.flat["spec.md"] + " " + self.flat["plan.md"] + " " + self.flat["research.md"]
        for boundary in ("multilingual metadata synchronisation", "stock synchronisation",
                         "price synchronisation"):
            with self.subTest(boundary=boundary):
                self.assertTrue(boundary in flat,
                                f"the artifacts no longer state that no {boundary} is built")


if __name__ == "__main__":
    unittest.main()

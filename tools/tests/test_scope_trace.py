import json
import unittest
from pathlib import Path

from tools import scope_trace as st

ROOT = Path(__file__).resolve().parents[2]
IDS = json.loads((ROOT / "docs" / "scope" / "register-ids.json").read_text(encoding="utf-8"))["ids"]
OPEN = json.loads((ROOT / "docs" / "scope" / "open-items.json").read_text(encoding="utf-8"))


def spec(trace_rows, crit_rows, open_items="- None"):
    trace = "\n".join(f"| {i} | {s} | {g} | text |" for i, s, g in trace_rows)
    crit = "\n".join(f"| {n} | {c} | {t} | {s} |" for n, c, t, s in crit_rows)
    return (
        "# Feature Specification: x\n\n## Register trace [checked]\n\n"
        "| ID | Scope | Stage | Register wording (short, verbatim) |\n|---|---|---|---|\n" + trace +
        "\n\n## Open contract items\n\n" + open_items +
        "\n\n## Contractual acceptance criteria [checked]\n\n| # | Criterion | Traces | Status |\n|---|---|---|---|\n" +
        crit + "\n\n## User Scenarios\n\ntext\n"
    )


VALID = spec([("ADM-27", "P1", "S1"), ("MIG-13", "P1-L", "S1")],
             [("AC-1", "A cost field exists on every product and variant", "ADM-27", "final"),
              ("AC-2", "Cost is migrated where present", "MIG-13", "final")],
             open_items="- CX-01 (pending)")


class SpecChecks(unittest.TestCase):
    def check(self, md):
        return st.check_spec("specs/001-x/spec.md", md, IDS, OPEN)

    def test_valid_spec_passes(self):
        self.assertEqual(self.check(VALID), [])

    def test_missing_trace_section(self):
        errs = self.check("# Spec\n\n## Contractual acceptance criteria\n\n| # | Criterion | Traces | Status |\n")
        self.assertIn("missing '## Register trace'", errs[0])

    def test_unknown_id_in_trace(self):
        errs = self.check(spec([("ADM-999", "P1", "S1")], [("AC-1", "x", "ADM-999", "final")]))
        self.assertTrue(any("not in the Feature Register" in e for e in errs))

    def test_deferred_id_in_trace_fails(self):
        errs = self.check(spec([("ROLE-06", "DEF", "-")], [("AC-1", "x", "ROLE-06", "final")], "- CX-01"))
        self.assertTrue(any("DEF in the register and creates no obligation" in e for e in errs))

    def test_p2_id_in_trace_fails(self):
        errs = self.check(spec([("RPT-02", "P2", "-")], [("AC-1", "x", "RPT-02", "final")]))
        self.assertTrue(any("creates no obligation" in e for e in errs))

    def test_scope_written_wrong(self):
        errs = self.check(spec([("MIG-13", "P1", "S1")], [("AC-1", "x", "MIG-13", "final")]))
        self.assertTrue(any("register says 'P1-L'" in e for e in errs))

    def test_stage_cannot_move(self):
        errs = self.check(spec([("ADM-27", "P1", "S2")], [("AC-1", "x", "ADM-27", "final")], "- CX-01"))
        self.assertTrue(any("stage written as 'S2'" in e for e in errs))

    def test_criterion_without_id(self):
        errs = self.check(spec([("ADM-27", "P1", "S1")], [("AC-1", "x", "-", "final")], "- CX-01"))
        self.assertTrue(any("cites no register id" in e for e in errs))

    def test_valid_id_attached_to_unrelated_scope(self):
        # ADM-27 is a real obligation, but the criterion reaches into RPT-02 (P2, margin reporting).
        errs = self.check(spec([("ADM-27", "P1", "S1")],
                               [("AC-1", "Margin report per product as in RPT-02", "ADM-27", "final")], "- CX-01"))
        self.assertTrue(any("refers to RPT-02" in e for e in errs))

    def test_criterion_cites_untraced_obligation_id(self):
        errs = self.check(spec([("ADM-27", "P1", "S1")], [("AC-1", "x", "ADM-27, SHIP-16", "final")], "- CX-01"))
        self.assertTrue(any("refers to SHIP-16" in e for e in errs))

    def test_p1e_criterion_cannot_be_final_before_pre09(self):
        errs = self.check(spec([("ERP-03", "P1-E", "S1")], [("AC-1", "x", "ERP-03", "final")]))
        self.assertTrue(any("PRE-09" in e for e in errs))
        self.assertEqual(self.check(spec([("ERP-03", "P1-E", "S1")], [("AC-1", "x", "ERP-03", "provisional")])), [])

    def test_open_contradiction_must_be_cited(self):
        errs = self.check(spec([("ADM-27", "P1", "S1")], [("AC-1", "x", "ADM-27", "final")]))
        self.assertTrue(any("CX-01" in e for e in errs))

    def test_comments_are_ignored(self):
        md = VALID.replace("## Register trace [checked]", "<!-- ## Register trace -->\n## Register trace [checked]")
        self.assertEqual(self.check(md), [])


TRACED = {"ADM-27", "MIG-13"}


def body(cls, ids=None, change=None):
    lines = [f"Classification: {cls}"]
    if ids is not None:
        lines.append(f"Requirement ids: {ids}")
    if change is not None:
        lines.append(f"Client-facing behaviour change: {change}")
    return "\n".join(lines)


class PrChecks(unittest.TestCase):
    def check(self, b, changed=()):
        return st.check_pr(b, list(changed), IDS, TRACED)

    def test_missing_classification(self):
        self.assertIn("Classification", self.check("Summary only")[0])

    def test_template_comment_is_not_a_classification(self):
        self.assertTrue(self.check("<!-- Classification: internal:governance -->"))

    def test_requirement_valid(self):
        self.assertEqual(self.check(body("requirement", "ADM-27, MIG-13")), [])

    def test_requirement_missing_ids(self):
        self.assertTrue(any("at least one" in e for e in self.check(body("requirement", ""))))

    def test_requirement_deferred_id(self):
        self.assertTrue(any("creates no obligation" in e for e in self.check(body("requirement", "ROLE-06"))))

    def test_requirement_id_not_in_any_spec(self):
        self.assertTrue(any("not traced in any" in e for e in self.check(body("requirement", "SHIP-16"))))

    def test_governance_exception_passes(self):
        self.assertEqual(self.check(body("internal:governance", change="none"),
                                    [".github/workflows/ci.yml", "AGENTS.md"]), [])

    def test_governance_must_state_no_behaviour_change(self):
        self.assertTrue(self.check(body("internal:governance")))

    def test_governance_cannot_change_behaviour(self):
        errs = self.check(body("internal:governance", change="adds a checkout banner"))
        self.assertTrue(any("may not change client-facing behaviour" in e for e in errs))

    def test_governance_cannot_touch_site_code(self):
        errs = self.check(body("internal:ci", change="none"), ["mizzey-site/src/Foo.php"])
        self.assertTrue(any("may not change site code" in e for e in errs))

    def test_internal_cannot_add_feature_spec(self):
        errs = self.check(body("internal:documentation", change="none"), ["specs/002-cart/spec.md"])
        self.assertTrue(any("feature specs" in e for e in errs))

    def test_security_maintenance_may_touch_site_code(self):
        self.assertEqual(self.check(body("internal:security-maintenance", change="escapes an admin notice"),
                                    ["mizzey-site/src/Foo.php"]), [])

    def test_unknown_internal_category(self):
        self.assertTrue(any("unknown internal category" in e for e in
                            self.check(body("internal:feature", change="none"))))


if __name__ == "__main__":
    unittest.main()

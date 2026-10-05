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
              ("AC-2", "Cost is visible only to roles with financial permission", "ADM-27", "pending CX-01"),
              ("AC-3", "Cost is migrated where present", "MIG-13", "final")],
             open_items="- CX-01 (pending)")
ERP = [("ERP-03", "P1-E", "S1")]


class SpecChecks(unittest.TestCase):
    def check(self, md):
        return st.check_spec("specs/001-x/spec.md", md, IDS, OPEN)

    def assertError(self, errs, text):
        self.assertTrue(any(text in e for e in errs), f"expected {text!r} in {errs}")

    def test_valid_spec_passes(self):
        self.assertEqual(self.check(VALID), [])

    def test_missing_trace_section(self):
        errs = self.check("# Spec\n\n## Contractual acceptance criteria\n\n| # | Criterion | Traces | Status |\n")
        self.assertIn("missing '## Register trace'", errs[0])

    def test_unknown_id_in_trace(self):
        self.assertError(self.check(spec([("ADM-999", "P1", "S1")], [("AC-1", "x", "ADM-999", "final")])),
                         "not in the Feature Register")

    def test_deferred_id_in_trace_fails(self):
        errs = self.check(spec([("ROLE-06", "DEF", "-")], [("AC-1", "x", "ROLE-06", "final")], "- CX-01"))
        self.assertError(errs, "DEF in the register and creates no obligation")

    def test_p2_id_in_trace_fails(self):
        self.assertError(self.check(spec([("RPT-02", "P2", "-")], [("AC-1", "x", "RPT-02", "final")])),
                         "creates no obligation")

    def test_scope_written_wrong(self):
        self.assertError(self.check(spec([("MIG-13", "P1", "S1")], [("AC-1", "x", "MIG-13", "final")])),
                         "register says 'P1-L'")

    def test_stage_cannot_move(self):
        errs = self.check(spec([("ADM-27", "P1", "S2")], [("AC-1", "x", "ADM-27", "final")], "- CX-01"))
        self.assertError(errs, "stage written as 'S2'")

    def test_criterion_without_id(self):
        self.assertError(self.check(spec([("ADM-27", "P1", "S1")], [("AC-1", "x", "", "final")], "- CX-01")),
                         "cites no register id")

    def test_valid_id_attached_to_unrelated_scope(self):
        errs = self.check(spec([("ADM-27", "P1", "S1")],
                               [("AC-1", "Margin report per product as in RPT-02", "ADM-27", "final")], "- CX-01"))
        self.assertError(errs, "refers to RPT-02")

    def test_criterion_cites_untraced_obligation_id(self):
        errs = self.check(spec([("ADM-27", "P1", "S1")], [("AC-1", "x", "ADM-27, SHIP-16", "final")], "- CX-01"))
        self.assertError(errs, "cites SHIP-16, which is not in the Register trace")

    # Regression: ChatGPT review, a real id plus an invented id in one criterion was accepted.
    def test_real_id_plus_invented_id_fails(self):
        errs = self.check(spec([("ADM-27", "P1", "S1")], [("AC-1", "x", "ADM-27, ADM-999", "final")], "- CX-01"))
        self.assertError(errs, "cites ADM-999, which is not in the Feature Register")

    def test_non_id_token_in_traces_fails(self):
        errs = self.check(spec([("ADM-27", "P1", "S1")], [("AC-1", "x", "ADM-27 and more", "final")], "- CX-01"))
        self.assertError(errs, "'and', which is not a register id")

    # Regression: ChatGPT review, 'approved' and 'finalized' slipped past the P1-E gate.
    def test_p1e_status_approved_fails(self):
        errs = self.check(spec(ERP, [("AC-1", "x", "ERP-03", "approved")]))
        self.assertError(errs, "status 'approved' is not allowed")

    def test_p1e_status_finalized_fails(self):
        errs = self.check(spec(ERP, [("AC-1", "x", "ERP-03", "finalized")]))
        self.assertError(errs, "status 'finalized' is not allowed")

    def test_p1e_final_before_pre09_fails(self):
        self.assertError(self.check(spec(ERP, [("AC-1", "x", "ERP-03", "final")])), "PRE-09 is not approved")

    def test_p1e_provisional_or_pending_pre09_passes(self):
        self.assertEqual(self.check(spec(ERP, [("AC-1", "x", "ERP-03", "provisional")])), [])
        self.assertEqual(self.check(spec(ERP, [("AC-1", "x", "ERP-03", "pending PRE-09")])), [])

    def test_unknown_status_fails_for_any_id(self):
        errs = self.check(spec([("MIG-13", "P1-L", "S1")], [("AC-1", "x", "MIG-13", "done")]))
        self.assertError(errs, "status 'done' is not allowed")

    def test_pending_unknown_contradiction_fails(self):
        errs = self.check(spec([("MIG-13", "P1-L", "S1")], [("AC-1", "x", "MIG-13", "pending CX-99")]))
        self.assertError(errs, "CX-99, which is not in open-items.json")

    # The live CX-01 was resolved by D-10, so the rule is tested against a contradiction this file controls.
    STILL_OPEN = {"gates": OPEN["gates"],
                  "contradictions": {"CX-90": {"status": "open", "ids": ["ROLE-06"], "related_ids": ["ADM-27"]}}}

    def test_open_contradiction_must_be_cited(self):
        md = spec([("ADM-27", "P1", "S1")], [("AC-1", "x", "ADM-27", "final")])
        errs = st.check_spec("specs/001-x/spec.md", md, IDS, self.STILL_OPEN)
        self.assertError(errs, "touched by open contradiction CX-90")

    def test_resolved_contradiction_need_not_be_cited(self):
        closed = {"gates": OPEN["gates"],
                  "contradictions": {"CX-90": {"status": "resolved", "ids": ["ROLE-06"], "related_ids": ["ADM-27"]}}}
        md = spec([("ADM-27", "P1", "S1")], [("AC-1", "x", "ADM-27", "final")])
        self.assertEqual(st.check_spec("specs/001-x/spec.md", md, IDS, closed), [])

    def test_a_resolution_does_not_make_a_deferred_row_traceable(self):
        # D-10 decides the Accountant role exists at launch. ROLE-06 still reads DEF in the register, and the
        # checker must keep refusing it until the document route changes the register itself.
        self.assertEqual(IDS["ROLE-06"]["scope"], "DEF")
        errs = self.check(spec([("ROLE-06", "DEF", "-")], [("AC-1", "x", "ROLE-06", "final")]))
        self.assertError(errs, "creates no obligation")

    def test_cx01_is_closed_with_a_recorded_owner_resolution(self):
        cx = OPEN["contradictions"]["CX-01"]
        self.assertEqual(cx["status"], "resolved")
        self.assertTrue(cx["evidence"], "the historical contradiction must stay in the record")
        self.assertIs(cx["resolution"]["client_confirmed"], False)
        self.assertIn("D-10", cx["resolution"]["record"])

    def test_no_working_decision_claims_client_confirmation(self):
        decisions = {k: v for k, v in OPEN["working_decisions"].items() if isinstance(v, dict)}
        self.assertTrue(decisions)
        for key, item in decisions.items():
            self.assertIs(item["client_confirmed"], False, key)
            self.assertEqual(item["decided_by"], "owner", key)
            self.assertIn(item["class"], OPEN["working_decisions"]["classes"], key)

    def test_comments_are_ignored(self):
        md = VALID.replace("## Register trace [checked]", "<!-- ## Register trace -->\n## Register trace [checked]")
        self.assertEqual(self.check(md), [])


TRACED = {"ADM-27", "MIG-13"}


def body(cls, change="none", ids=None, **extra):
    lines = [f"Classification: {cls}"]
    if ids is not None:
        lines.append(f"Requirement ids: {ids}")
    if change is not None:
        lines.append(f"Client-facing behaviour change: {change}")
    names = {"sensitive": "Sensitive changes", "delivery": "Delivery impact", "evidence": "Security evidence",
             "security": "Security change"}
    lines += [f"{names[k]}: {v}" for k, v in extra.items()]
    return "\n".join(lines)


def m(*paths):
    return [("M", p, None) for p in paths]


class PrChecks(unittest.TestCase):
    def check(self, b, changes=()):
        return st.check_pr(b, list(changes), IDS, TRACED)

    def assertError(self, errs, text):
        self.assertTrue(any(text in e for e in errs), f"expected {text!r} in {errs}")

    # Classification
    def test_missing_classification(self):
        self.assertIn("Classification", self.check("Summary only")[0])

    def test_template_comment_is_not_a_classification(self):
        self.assertTrue(self.check("<!-- Classification: internal:governance -->"))

    def test_duplicate_classification_fails(self):
        b = "Classification: internal:governance\nClient-facing behaviour change: none\nClassification: requirement"
        self.assertError(self.check(b, m("docs/x.md")), "'Classification:' appears 2 times")

    def test_unknown_internal_category(self):
        self.assertError(self.check(body("internal:feature")), "unknown internal category")

    # Requirement PRs
    def test_legitimate_requirement_pr_passes(self):
        changes = m("specs/001-product-cost-capture/spec.md", "mizzey-site/src/Catalogue/Cost.php",
                    "mizzey-site/tests/CostTest.php")
        self.assertEqual(self.check(body("requirement", None, "ADM-27, MIG-13"), changes), [])

    def test_requirement_missing_ids(self):
        self.assertError(self.check(body("requirement", None, "")), "at least one")

    def test_deferred_requirement_fails(self):
        self.assertError(self.check(body("requirement", None, "ROLE-06"), m("mizzey-site/src/A.php")),
                         "creates no obligation")

    def test_requirement_id_not_in_any_spec(self):
        self.assertError(self.check(body("requirement", None, "SHIP-16")), "not traced in any")

    def test_requirement_may_not_change_governance_controls(self):
        errs = self.check(body("requirement", None, "ADM-27", sensitive="x"), m("tools/scope_trace.py"))
        self.assertError(errs, "may not change tools/scope_trace.py (governance-control)")

    # Legitimate internal work
    def test_governance_exception_passes(self):
        self.assertEqual(self.check(body("internal:governance", sensitive="adds CI"),
                                    m(".github/workflows/ci.yml", "AGENTS.md", "docs/tooling.md")), [])

    def test_legitimate_internal_tooling_change_passes(self):
        self.assertEqual(self.check(body("internal:tooling"), m("discovery/run_probes.py", "tools/tests/test_x.py")), [])

    def test_build_tool_correction_needs_delivery_impact(self):
        errs = self.check(body("internal:tooling", sensitive="fixes a path"), m("tools/build-dist.mjs"))
        self.assertError(errs, "add 'Delivery impact:'")
        ok = body("internal:tooling", sensitive="fixes a path", delivery="artifact contents unchanged; path bug only")
        self.assertEqual(self.check(ok, m("tools/build-dist.mjs")), [])

    # The original four controls still fail
    def test_control_internal_touching_site_code(self):
        self.assertError(self.check(body("internal:ci"), m("mizzey-site/src/Foo.php")), "may not change mizzey-site")

    def test_control_internal_claiming_behaviour_change(self):
        errs = self.check(body("internal:governance", "adds a banner"), m("AGENTS.md"))
        self.assertError(errs, "may not change client-facing behaviour")

    def test_control_internal_adding_feature_spec(self):
        errs = self.check(body("internal:governance"), [("A", "specs/002-cart/spec.md", None)])
        self.assertError(errs, "(feature-spec)")

    def test_control_requirement_citing_def(self):
        self.assertError(self.check(body("requirement", None, "ROLE-06")), "creates no obligation")

    def test_internal_must_state_no_behaviour_change(self):
        self.assertTrue(self.check(body("internal:governance", None), m("docs/x.md")))

    # Abuse cases from REVIEW.md section 5
    def test_a1_new_top_level_directory_rejected_for_every_category(self):
        for cls in ["internal:ci", "internal:governance", "internal:tooling", "internal:documentation"]:
            errs = self.check(body(cls), [("A", "mizzey-extras/mizzey-extras.php", None)])
            self.assertError(errs, "not covered by the path policy")
        errs = self.check(body("requirement", None, "ADM-27"), [("A", "mizzey-extras/mizzey-extras.php", None)])
        self.assertError(errs, "not covered by the path policy")

    def test_a1_code_hidden_in_docs_rejected(self):
        self.assertError(self.check(body("internal:documentation"), [("A", "docs/widget.php", None)]),
                         "not covered by the path policy")

    def test_a2_build_tool_not_allowed_for_governance_or_docs(self):
        for cls in ["internal:governance", "internal:ci", "internal:documentation"]:
            errs = self.check(body(cls, sensitive="x", delivery="y"), m("tools/build-dist.mjs"))
            self.assertError(errs, "(delivery-tooling)")

    def test_a3_security_maintenance_free_text_rejected(self):
        errs = self.check(body("internal:security-maintenance", "adds a checkout upsell banner"),
                          m("mizzey-site/src/Upsell.php"))
        self.assertError(errs, "needs 'Security evidence:'")
        self.assertError(errs, "needs 'Security change:'")

    def test_security_with_advisory_passes(self):
        b = body("internal:security-maintenance", "none", evidence="CVE-2026-12345, WooCommerce 11.1.1 release notes",
                 security="escapes the order note field", sensitive="plugin bump", delivery="WooCommerce 11.1.0 to 11.1.1")
        self.assertEqual(self.check(b, m("mizzey-site/src/Notes.php", "corex.lock")), [])

    def test_local_security_defect_needs_a_regression_test(self):
        b = body("internal:security-maintenance", "none", evidence="local: stored XSS in admin notice, found in review",
                 security="escapes the notice text")
        self.assertError(self.check(b, m("mizzey-site/src/Notice.php")), "must come with a regression test")
        self.assertEqual(self.check(b, m("mizzey-site/src/Notice.php", "mizzey-site/tests/NoticeTest.php")), [])

    def test_a4_a5_checker_and_workflow_changes_need_a_sensitive_declaration(self):
        errs = self.check(body("internal:governance"), m("tools/scope_trace.py", ".github/workflows/ci.yml"))
        self.assertError(errs, "add 'Sensitive changes:'")

    def test_a7_version_lock_not_allowed_for_governance(self):
        errs = self.check(body("internal:governance", sensitive="x", delivery="y"), m("corex.lock"))
        self.assertError(errs, "(version-lock)")

    def test_a8_spec_deletion_rejected(self):
        self.assertError(st.check_deletions([("D", "specs/001-x/spec.md", None)]), "never deleted")
        self.assertError(self.check(body("internal:documentation"), [("D", "specs/001-x/spec.md", None)]),
                         "(feature-spec)")

    def test_spec_rename_rejected(self):
        self.assertError(st.check_deletions([("R", "specs/009-y/spec.md", "specs/001-x/spec.md")]), "never deleted")

    def test_rename_checks_both_paths(self):
        errs = self.check(body("internal:documentation"), [("R", "docs/moved.md", "mizzey-site/src/Old.php")])
        self.assertError(errs, "mizzey-site/src/Old.php (site-code)")


class SpecBranch(unittest.TestCase):
    def test_new_spec_must_match_branch(self):
        changes = [("A", "specs/001-product-cost-capture/spec.md", None)]
        self.assertEqual(st.check_spec_branch(changes, "001-product-cost-capture"), [])
        self.assertTrue(st.check_spec_branch(changes, "001-cost"))

    def test_edits_to_existing_specs_are_not_branch_bound(self):
        self.assertEqual(st.check_spec_branch([("M", "specs/001-x/spec.md", None)], "fix/typo"), [])


class PathPolicy(unittest.TestCase):
    def test_every_file_in_the_repository_is_classified(self):
        import subprocess
        files = subprocess.run(["git", "ls-files"], cwd=ROOT, capture_output=True, text=True, check=True).stdout.split()
        unclassified = [f for f in files if st.classify(f) == "unclassified"]
        self.assertEqual(unclassified, [])

    def test_examples(self):
        self.assertEqual(st.classify("mizzey-site/tests/x.php"), "site-tests")
        self.assertEqual(st.classify("mizzey-theme/theme.json"), "site-code")
        self.assertEqual(st.classify("tools/run_trusted.py"), "governance-control")
        self.assertEqual(st.classify("some-new-plugin/plugin.php"), "unclassified")


if __name__ == "__main__":
    unittest.main()

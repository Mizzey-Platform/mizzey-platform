import unittest

from tools import extract_register_ids as ex

MD = """
| ID | Requirement | § | Scope | Stage |
|---|---|---|---|---|
| ADM-27 | **Product cost** | 10.2 | **P1** [key] | S1 |
| RPT-08 | Funnel | 11 | P1-L (via GA4) | S1 |
| **ERP-09** | No interim operation | - | **OUT (EX-23)** | - |
| ROLE-06a | Accountant, advanced | 3 / 11 | P2 | - |

| ID | Feature | What the Launch Platform provides instead |
|---|---|---|
| ROLE-06 | Accountant permission profile | As above |

| ID | Requirement | Scope | Stage |
|---|---|---|---|
| ERP-03 | Cart takes account of ERP stock | P1-E | S1 |
"""


class Extraction(unittest.TestCase):
    def test_reads_scope_and_stage_by_column_name(self):
        rows = ex.extract(MD)
        self.assertEqual(rows["ADM-27"]["scope"], "P1")
        self.assertTrue(rows["ADM-27"]["key"])
        self.assertEqual(rows["RPT-08"]["scope"], "P1-L")
        self.assertEqual(rows["ERP-09"]["scope"], "OUT")
        erp = {k: v for k, v in rows["ERP-03"].items() if k != "line"}
        self.assertEqual(erp, {"scope": "P1-E", "stage": "S1", "key": False, "obligation": True})
        self.assertFalse(rows["ROLE-06a"]["obligation"])

    def test_the_contractual_stage_is_the_last_stage_column(self):
        # The customer journey table heads two columns Stage: the journey step, then the release stage.
        journey = """
| ID | Stage | Requirement | § | Scope | Stage |
|---|---|---|---|---|---|
| JRN-01 | Discovery | Arrives from search | 4 | P1 | S1 |
| JRN-11 | Repeat purchase | Reorders | 4 | P1-L | S2 |
"""
        rows = ex.extract(MD + journey)
        self.assertEqual(rows["JRN-01"]["stage"], "S1")
        self.assertEqual(rows["JRN-11"]["stage"], "S2")

    def test_tables_without_scope_column_are_ignored(self):
        self.assertNotIn("ROLE-06", ex.extract(MD))

    def test_conflicting_rows_stop_extraction(self):
        bad = MD + "\n| ID | Requirement | § | Scope | Stage |\n|---|---|---|---|---|\n| ADM-27 | again | - | P2 | - |\n"
        with self.assertRaises(SystemExit):
            ex.extract(bad)


if __name__ == "__main__":
    unittest.main()

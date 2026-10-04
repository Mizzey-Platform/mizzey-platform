"""The board snapshot's Scope class and Stage must be computed from the exact register ids, not chosen.

Why this exists. The earlier validation proved every cited id was a real delivery id, which it did, and then five
PBIs still carried a Scope class picked from the PBI's primary purpose rather than from its rows: #243 labelled
DLV while citing a P1 row, #246 and #247 labelled P1-L while citing P1 rows, #253 and #255 labelled mixed while
citing only P1 rows. Real ids, wrong classification. This closes that gap.

Scope class is a function of the cited ids:
    one distinct delivery scope -> that scope; more than one -> mixed.
Stage is the same function over the register's stage column, and is **never** derived from Scope class: a PBI can
be `mixed` scope and S1 stage, or P1 scope and `mixed` stage.

`ERP blocked` is deliberately **not** checked against the ids. It records a dependency on PRE-09 or the ERP, which
no scope value implies: #243 is `yes` while citing DLV and P1 rows, because the specification is what the ERP
meeting produces. What is enforced is that its option set stays a dependency vocabulary and never encodes a scope
class again.

The GitHub Project API cannot be reached from CI, so this validates the snapshot table in the board document,
which the maintenance procedure keeps equal to the live board. The reconciliation step that compares document to
board is recorded beside the table.
"""

import io
import json
import re
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DOC_GLOB = 'docs/*-github-project-proposal.md'
DELIVERY = ('P1', 'P1-L', 'P1-E', 'DLV')
SCOPE_ORDER = {'P1': 0, 'P1-L': 1, 'P1-E': 2, 'DLV': 3}
ERP_OPTIONS = {'no', 'partial', 'yes'}

# "| 3 | #243 | Title | E-PRE | PRE-09, ERP-10 | **mixed** | **mixed** | **Blocked** | **yes** | ..."
ROW = re.compile(r'^\|\s*(\d+)\s*\|\s*#(\d+)\s*\|([^|]*)\|([^|]*)\|([^|]*)\|\s*\*\*([^*]+)\*\*\s*\|'
                 r'\s*\*\*([^*]+)\*\*\s*\|\s*\*\*([^*]+)\*\*\s*\|\s*\*\*([^*]+)\*\*\s*\|')


def register():
    return json.loads((ROOT / 'docs' / 'scope' / 'register-ids.json').read_text(encoding='utf-8'))['ids']


def scope_of(ids, reg):
    """The Scope class the cited ids imply."""
    found = sorted({reg[i]['scope'] for i in ids if i in reg and reg[i]['scope'] in DELIVERY},
                   key=lambda s: SCOPE_ORDER[s])
    return found[0] if len(found) == 1 else 'mixed'


def stage_of(ids, reg):
    """The Stage the cited ids imply, read from the register's own stage column."""
    found = sorted({reg[i]['stage'] for i in ids if i in reg})
    return found[0] if len(found) == 1 else 'mixed'


def snapshot_rows():
    """Every row of the board snapshot table, as (order, issue, ids, scope, stage, status, erp)."""
    doc = sorted(ROOT.glob(DOC_GLOB))
    if not doc:
        return []
    text = doc[0].read_text(encoding='utf-8')
    out = []
    for line in text.splitlines():
        m = ROW.match(line)
        if not m:
            continue
        order, issue, _title, _epic, ids, scope, stage, status, erp = m.groups()
        out.append({
            'order': int(order),
            'issue': int(issue),
            'ids': re.findall(r'\b[A-Z]{2,6}-\d{1,3}[a-z]?\b', ids),
            'ids_abbreviated': 'listed in the issue' in ids,
            'scope': scope.strip(),
            'stage': stage.strip(),
            'status': status.strip(),
            'erp': erp.strip(),
        })
    return out


class BoardMetadata(unittest.TestCase):
    def setUp(self):
        self.reg = register()
        self.rows = snapshot_rows()

    def test_the_snapshot_table_is_present(self):
        self.assertTrue(self.rows, 'no board snapshot rows found; has the table format changed?')

    def test_scope_class_is_computed_from_the_cited_ids(self):
        for r in self.rows:
            if r['ids_abbreviated']:
                continue  # the full list lives in the issue; nothing to compute from here
            with self.subTest(issue=r['issue']):
                self.assertEqual(r['scope'], scope_of(r['ids'], self.reg),
                                 f"#{r['issue']} Scope class does not match its cited ids "
                                 f"({[(i, self.reg[i]['scope']) for i in r['ids'] if i in self.reg]})")

    def test_stage_is_computed_from_the_register_and_not_from_scope(self):
        for r in self.rows:
            if r['ids_abbreviated']:
                continue
            with self.subTest(issue=r['issue']):
                self.assertEqual(r['stage'], stage_of(r['ids'], self.reg),
                                 f"#{r['issue']} Stage does not match the register stages of its cited ids "
                                 f"({[(i, self.reg[i]['stage']) for i in r['ids'] if i in self.reg]})")

    def test_every_cited_id_is_a_real_delivery_row(self):
        for r in self.rows:
            for i in r['ids']:
                with self.subTest(issue=r['issue'], id=i):
                    self.assertIn(i, self.reg, f'{i} is not a register id')
                    self.assertIn(self.reg[i]['scope'], DELIVERY,
                                  f"{i} is {self.reg[i]['scope']}, which is not a delivery scope")

    def test_an_s1_pbi_does_not_cite_an_s2_row(self):
        for r in self.rows:
            if r['stage'] != 'S1':
                continue
            for i in r['ids']:
                with self.subTest(issue=r['issue'], id=i):
                    self.assertNotEqual(self.reg[i]['stage'], 'S2',
                                        f"#{r['issue']} is staged S1 but cites {i}, which the register stages S2")

    def test_erp_blocked_is_a_dependency_vocabulary_only(self):
        # It must never encode a scope class again: "yes (P1-E)" was the value this rule exists to prevent.
        for r in self.rows:
            with self.subTest(issue=r['issue']):
                self.assertIn(r['erp'], ERP_OPTIONS,
                              f"#{r['issue']} ERP blocked is {r['erp']!r}; allowed: {sorted(ERP_OPTIONS)}")
                for scope in DELIVERY:
                    self.assertNotIn(scope, r['erp'],
                                     f"#{r['issue']} ERP blocked encodes the scope class {scope}; "
                                     'the field records a dependency and Scope class records scope')

    def test_erp_blocked_is_independent_of_scope_class(self):
        # Not a style point: a PBI citing no P1-E row can still be fully ERP-blocked, and the board must be able
        # to say so. If this ever fails, the two fields have been collapsed into one.
        independent = [r for r in self.rows
                       if r['erp'] != 'no' and 'P1-E' not in [self.reg[i]['scope'] for i in r['ids'] if i in self.reg]]
        self.assertTrue(independent,
                        'no PBI is ERP-blocked without citing a P1-E row, which suggests ERP blocked is being '
                        'derived from Scope class rather than recorded as a dependency')

    def test_rows_are_a_contiguous_delivery_order(self):
        orders = sorted(r['order'] for r in self.rows)
        self.assertEqual(orders, list(range(1, len(orders) + 1)),
                         f'delivery order is not 1..{len(orders)} without gaps or repeats: {orders}')

    def test_status_values_keep_verified_and_accepted_apart(self):
        allowed = {'Blocked', 'Ready', 'In progress', 'In review', 'Verified', 'Accepted'}
        for r in self.rows:
            with self.subTest(issue=r['issue']):
                self.assertIn(r['status'], allowed)

    # ---- the rules themselves, independent of the current board -----------------------------------------
    def test_scope_rule_is_one_distinct_class_else_mixed(self):
        reg = self.reg
        one = next(i for i in reg if reg[i]['scope'] == 'P1')
        two = next(i for i in reg if reg[i]['scope'] == 'P1-L')
        self.assertEqual(scope_of([one], reg), 'P1')
        self.assertEqual(scope_of([one, one], reg), 'P1')
        self.assertEqual(scope_of([one, two], reg), 'mixed')

    def test_stage_rule_treats_an_unstaged_row_as_its_own_value(self):
        reg = self.reg
        self.assertEqual(reg['PRE-09']['stage'], '-', 'the register no longer leaves PRE-09 unstaged')
        self.assertEqual(reg['PRE-01']['stage'], 'S1')
        self.assertEqual(stage_of(['PRE-09'], reg), '-')
        self.assertEqual(stage_of(['PRE-09', 'ERP-10'], reg), 'mixed')
        self.assertEqual(stage_of(['PRE-01', 'ERP-10'], reg), 'S1')


if __name__ == '__main__':
    unittest.main()

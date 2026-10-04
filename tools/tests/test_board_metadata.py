"""The board snapshot's Scope class and Stage must be computed from the exact register ids, for every PBI.

Why this exists. An earlier validation proved every cited id was a real delivery id, which it did, and five PBIs
still carried a Scope class picked from the PBI's primary purpose rather than from its rows: #243 labelled DLV
while citing a P1 row, #246 and #247 labelled P1-L while citing P1 rows, #253 and #255 labelled mixed while citing
only P1 rows. Real ids, wrong classification.

Why it reads two places. The snapshot table abbreviates a long id list so the twelve columns stay readable, and an
earlier version of this test skipped those rows, which quietly exempted the PBIs with the most rows. The document
therefore also carries an appendix listing every id in full, and **this test reads the appendix**, so nothing is
skipped. Both are generated from the live Project in one pass, so there is one truth rather than two.

Scope class is a function of the cited ids:
    one distinct delivery scope -> that scope; more than one -> mixed.
Stage is the same function over the register's stage column, and is **never** derived from Scope class: a PBI can
be mixed scope and S1 stage, or P1 scope and mixed stage.

`ERP blocked` is deliberately **not** computed from the ids. It records a dependency on PRE-09 or the ERP, which no
scope value implies: #243 is `yes` while citing a single DLV row, because the specification is what the ERP meeting
produces. What is enforced is that its vocabulary stays a dependency vocabulary and never encodes a scope class.

One row, one accepting owner. Three rows were once cited by two PBIs each: FIX-04 and NFR-04 on #241 and #246, and
ERP-10 on #245 and #243. They were corrected on the board on 4 October 2026, each second citation becoming a
Dependencies entry, and the exact-id total moved 131 to 129 to 126. Citation uniqueness is enforced by
`test_backlog_totals.py`, which reads the ownership map; this file enforces that whatever is cited is classified
correctly.

The GitHub Project API is not reachable from CI, so this validates the generated snapshot. The step that compares
the document with the live board is by hand, and is recorded beside the table.
"""

import json
import re
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DOC_GLOB = 'docs/*-github-project-proposal.md'
DELIVERY = ('P1', 'P1-L', 'P1-E', 'DLV')
SCOPE_ORDER = {'P1': 0, 'P1-L': 1, 'P1-E': 2, 'DLV': 3}
ERP_OPTIONS = {'no', 'partial', 'yes'}
STATUS_OPTIONS = {'Blocked', 'Ready', 'In progress', 'In review', 'Verified', 'Accepted'}
SEEDED = 15

# "| 3 | #243 | Title | E-PRE | PRE-09, ERP-10 | **mixed** | **mixed** | **Blocked** | **yes** | ..."
ROW = re.compile(r'^\|\s*(\d+)\s*\|\s*#(\d+)\s*\|([^|]*)\|([^|]*)\|([^|]*)\|\s*\*\*([^*]+)\*\*\s*\|'
                 r'\s*\*\*([^*]+)\*\*\s*\|\s*\*\*([^*]+)\*\*\s*\|\s*\*\*([^*]+)\*\*\s*\|')
# "- **#242** (30 ids): `IA-01, IA-02, ...`"
APPENDIX = re.compile(r'^- \*\*#(\d+)\*\*\s*\((\d+) ids?\):\s*`([^`]+)`')


def register():
    return json.loads((ROOT / 'docs' / 'scope' / 'register-ids.json').read_text(encoding='utf-8'))['ids']


def document():
    docs = sorted(ROOT.glob(DOC_GLOB))
    return docs[0].read_text(encoding='utf-8') if docs else ''


def scope_of(ids, reg):
    """The Scope class the cited ids imply."""
    found = sorted({reg[i]['scope'] for i in ids if i in reg and reg[i]['scope'] in DELIVERY},
                   key=lambda s: SCOPE_ORDER[s])
    return found[0] if len(found) == 1 else 'mixed'


def stage_of(ids, reg):
    """The Stage the cited ids imply, read from the register's own stage column."""
    found = sorted({reg[i]['stage'] for i in ids if i in reg})
    return found[0] if len(found) == 1 else 'mixed'


# The register writes an unstaged row as a literal '-'. The board's Stage field has no '-' option; it has
# 'per PRE-09', which the field definition records as existing for a row the register defers to the ERP
# specification. Adding '-' to the field would replace its option set and clear every stored value, so the one
# spelling difference is declared here. One value, one direction: nothing else is mapped.
STAGE_SPELLING = {'-': 'per PRE-09'}


def stage_accepts(computed, on_board):
    """Does the board's Stage value state the computed register stage?"""
    return on_board == computed or on_board == STAGE_SPELLING.get(computed)


def exact_ids():
    """issue number -> the complete cited id list, from the appendix."""
    out = {}
    for line in document().splitlines():
        m = APPENDIX.match(line)
        if m:
            ids = [x.strip() for x in m.group(3).split(',') if x.strip()]
            out[int(m.group(1))] = {'ids': ids, 'claimed_count': int(m.group(2))}
    return out


def table_rows():
    """Every row of the snapshot table, joined to the appendix so no row lacks its exact ids."""
    appendix = exact_ids()
    out = []
    for line in document().splitlines():
        m = ROW.match(line)
        if not m:
            continue
        order, issue, title, epic, ids_cell, scope, stage, status, erp = m.groups()
        n = int(issue)
        out.append({
            'order': int(order),
            'issue': n,
            'title': title.strip(),
            'epic': epic.strip(),
            'cell_abbreviated': 'listed in the issue' in ids_cell,
            'ids': appendix.get(n, {}).get('ids'),
            'scope': scope.strip(),
            'stage': stage.strip(),
            'status': status.strip(),
            'erp': erp.strip(),
        })
    return out


class BoardMetadata(unittest.TestCase):
    def setUp(self):
        self.reg = register()
        self.rows = table_rows()
        self.appendix = exact_ids()

    # ---- coverage: the guard against a row escaping validation -------------------------------------------
    def test_all_fifteen_seeded_pbis_are_present(self):
        self.assertEqual(len(self.rows), SEEDED, f'expected {SEEDED} snapshot rows, found {len(self.rows)}')

    def test_no_row_escapes_validation_through_an_abbreviated_cell(self):
        """An abbreviated table cell must not exempt a PBI: its exact ids must be in the appendix."""
        missing = [r['issue'] for r in self.rows if not r['ids']]
        self.assertEqual(missing, [],
                         f'these PBIs have no exact id list in the appendix, so Scope class and Stage would not '
                         f'be validated for them: {missing}')
        abbreviated = [r['issue'] for r in self.rows if r['cell_abbreviated']]
        for n in abbreviated:
            with self.subTest(issue=n):
                self.assertIn(n, self.appendix,
                              f'#{n} abbreviates its ids in the table and is absent from the appendix')

    def test_every_pbi_is_actually_evaluated(self):
        evaluated = [r['issue'] for r in self.rows if r['ids']]
        self.assertEqual(len(evaluated), SEEDED,
                         f'only {len(evaluated)} of {SEEDED} PBIs carry exact ids and are therefore evaluated')

    def test_the_appendix_count_matches_its_own_list(self):
        for n, a in self.appendix.items():
            with self.subTest(issue=n):
                self.assertEqual(len(a['ids']), a['claimed_count'],
                                 f"#{n} says {a['claimed_count']} ids and lists {len(a['ids'])}")

    # ---- the rules, over every PBI ----------------------------------------------------------------------
    def test_scope_class_is_computed_from_the_cited_ids(self):
        for r in self.rows:
            with self.subTest(issue=r['issue']):
                self.assertEqual(r['scope'], scope_of(r['ids'], self.reg),
                                 f"#{r['issue']} Scope class does not match its cited ids "
                                 f"({sorted({(i, self.reg[i]['scope']) for i in r['ids'] if i in self.reg})})")

    def test_stage_is_computed_from_the_register_and_not_from_scope(self):
        for r in self.rows:
            computed = stage_of(r['ids'], self.reg)
            with self.subTest(issue=r['issue']):
                self.assertTrue(
                    stage_accepts(computed, r['stage']),
                    f"#{r['issue']} Stage reads {r['stage']!r}; the register stages of its cited ids give "
                    f"{computed!r} "
                    f"({sorted({(i, self.reg[i]['stage']) for i in r['ids'] if i in self.reg})})")

    def test_the_stage_spelling_is_one_value_and_not_a_loophole(self):
        # 'per PRE-09' may stand for an unstaged row and for nothing else. If this fails, a real stage can hide
        # behind it, which is the error the row table was corrected for in the first place.
        self.assertEqual(STAGE_SPELLING, {'-': 'per PRE-09'})
        self.assertTrue(stage_accepts('-', 'per PRE-09'))
        self.assertTrue(stage_accepts('-', '-'))
        self.assertTrue(stage_accepts('S1', 'S1'))
        self.assertFalse(stage_accepts('S1', 'per PRE-09'), 'an S1 PBI must not be spelled per PRE-09')
        self.assertFalse(stage_accepts('S2', 'per PRE-09'), 'an S2 PBI must not be spelled per PRE-09')
        self.assertFalse(stage_accepts('mixed', 'per PRE-09'))
        self.assertFalse(stage_accepts('-', 'S1'), 'an unstaged row must not be promoted to S1')

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

    def test_no_range_is_used_as_a_citation(self):
        for n, a in self.appendix.items():
            with self.subTest(issue=n):
                for i in a['ids']:
                    self.assertRegex(i, r'^[A-Z]{2,6}-\d{1,3}[a-z]?$',
                                     f'#{n} cites {i!r}, which is not a single register id')

    def test_erp_blocked_is_a_dependency_vocabulary_only(self):
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
        # to say so. If this fails, the two fields have been collapsed into one.
        independent = [r for r in self.rows
                       if r['erp'] != 'no'
                       and 'P1-E' not in [self.reg[i]['scope'] for i in r['ids'] if i in self.reg]]
        self.assertTrue(independent,
                        'no PBI is ERP-blocked without citing a P1-E row, which suggests ERP blocked is being '
                        'derived from Scope class rather than recorded as a dependency')

    def test_rows_are_a_contiguous_delivery_order(self):
        orders = sorted(r['order'] for r in self.rows)
        self.assertEqual(orders, list(range(1, len(orders) + 1)),
                         f'delivery order is not 1..{len(orders)} without gaps or repeats: {orders}')

    def test_status_values_keep_verified_and_accepted_apart(self):
        for r in self.rows:
            with self.subTest(issue=r['issue']):
                self.assertIn(r['status'], STATUS_OPTIONS)

    def test_the_documented_field_options_match_what_the_rows_use(self):
        """The field-definition table is generated from the live Project, so every used value must appear in it."""
        text = document()
        for r in self.rows:
            for label, value in (('Scope class', r['scope']), ('Stage', r['stage']),
                                 ('ERP blocked', r['erp']), ('Status', r['status'])):
                with self.subTest(issue=r['issue'], field=label):
                    self.assertIn(f'`{value}`', text,
                                  f"#{r['issue']} uses {label} {value!r}, which the documented option set "
                                  'does not list')

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

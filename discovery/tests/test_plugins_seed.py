import unittest

from discovery import check, schema


class TestPluginSeed(unittest.TestCase):
    def setUp(self):
        self.plugins = schema.load('plugins')

    def test_six_needs_are_seeded(self):
        self.assertEqual(len(self.plugins), 6)

    def test_ids_are_unique_and_sequential(self):
        self.assertEqual([p['id'] for p in self.plugins], ['PL-%02d' % n for n in range(1, 7)])

    def test_every_row_has_every_field(self):
        for p in self.plugins:
            self.assertEqual(schema.missing_fields(p, schema.PLUGIN_FIELDS), [], p['id'])

    def test_every_decision_names_a_candidate_that_was_evaluated(self):
        # Replaces test_nothing_is_decided_yet, which guarded the seed state and
        # could only pass before the PLUGIN pass ran. The invariant that outlives
        # the seed is stronger and the gate does not check it: a decision must be
        # one of the options the row actually weighed, so a product nobody
        # evaluated cannot arrive in a register that feeds a client figure.
        for p in self.plugins:
            if p['decision'] is None or p['decision'] == 'build instead':
                continue
            names = [c['name'] for c in p['candidates']]
            self.assertIn(p['decision'], names, p['id'])

    def test_a_decided_row_weighed_at_least_two_options(self):
        # The template requires at least two real options before deciding. The
        # exception is a need that turns out not to exist: PL-06 was closed by
        # P-021 finding the capability native, and researching a second plugin
        # for a problem nobody has is not diligence.
        for p in self.plugins:
            if p['decision'] is None or p['id'] == 'PL-06':
                continue
            self.assertGreaterEqual(len(p['candidates']), 2, p['id'])

    def test_the_translation_need_names_the_quoted_figure_as_a_candidate(self):
        # Keyed on the id, not the prose: renaming the need must not break this.
        # Loosened from equality to membership when the pass added the real
        # alternative. The intent was never "exactly one candidate", it was that
        # the figure already in the client's hands is on the table to be beaten.
        translation = [p for p in self.plugins if p['id'] == 'PL-01'][0]
        self.assertTrue(translation['candidates'],
                        'the 107 USD already quoted must appear as a candidate to beat')
        self.assertIn(schema.QUOTED_ANNUAL_USD,
                      [c['cost_annual'] for c in translation['candidates']])

    def test_the_undecided_seed_does_not_fail_the_gate(self):
        data = schema.Data(schema.load_stories(), [], [], self.plugins, [])
        self.assertEqual(check.check_plugin_complete(data), [])
        self.assertEqual(check.check_plugin_ceiling(data), [])


if __name__ == '__main__':
    unittest.main()

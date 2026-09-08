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

    def test_nothing_is_decided_yet(self):
        for p in self.plugins:
            self.assertIsNone(p['decision'], p['id'])

    def test_the_translation_need_names_the_quoted_figure_as_a_candidate(self):
        # Keyed on the id, not the prose: renaming the need must not break this.
        translation = [p for p in self.plugins if p['id'] == 'PL-01'][0]
        self.assertTrue(translation['candidates'],
                        'the 107 USD already quoted must appear as a candidate to beat')
        self.assertEqual([c['cost_annual'] for c in translation['candidates']],
                         [schema.QUOTED_ANNUAL_USD])

    def test_the_undecided_seed_does_not_fail_the_gate(self):
        data = schema.Data(schema.load_stories(), [], [], self.plugins, [])
        self.assertEqual(check.check_plugin_complete(data), [])
        self.assertEqual(check.check_plugin_ceiling(data), [])


if __name__ == '__main__':
    unittest.main()

import unittest

from discovery import schema


class TestProbeSeed(unittest.TestCase):
    def setUp(self):
        self.probes = schema.load('probes')
        self.stories = {s['id'] for s in schema.load_stories()}

    def test_probes_are_seeded(self):
        # A floor, not an equality. The phase exists to add probes as the design
        # is questioned, and an exact count would fail on the first one added.
        self.assertGreaterEqual(len(self.probes), 15)

    def test_ids_are_unique_and_sequential(self):
        ids = [p['id'] for p in self.probes]
        self.assertEqual(ids, ['P-%03d' % n for n in range(1, len(ids) + 1)],
                         'probe ids must run from P-001 with no gaps and no duplicates')

    def test_every_probe_has_every_field(self):
        for p in self.probes:
            self.assertEqual(schema.missing_fields(p, schema.PROBE_FIELDS), [], p['id'])

    def test_every_probe_records_what_the_design_assumes(self):
        for p in self.probes:
            self.assertTrue(p['expected'].strip(), p['id'] + ' must record expected before running')

    def test_nothing_has_been_observed_yet(self):
        for p in self.probes:
            self.assertIsNone(p['observed'], p['id'])
            self.assertIsNone(p['verdict'], p['id'])

    def test_every_named_story_exists(self):
        for p in self.probes:
            for sid in p['stories']:
                self.assertIn(sid, self.stories, '%s names %s' % (p['id'], sid))

    def test_methods_are_known(self):
        for p in self.probes:
            self.assertIn(p['method'], ('wp-cli', 'php', 'playwright'), p['id'])


if __name__ == '__main__':
    unittest.main()

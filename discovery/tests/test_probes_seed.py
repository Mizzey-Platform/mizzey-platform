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

    def test_a_probe_is_either_unrun_or_fully_recorded(self):
        # Asserting nothing has run was right while the set was only seeded, but
        # it turns the first real run into a test failure. The invariant that
        # survives the phase is that a row cannot be half recorded: a verdict
        # with no evidence, or evidence with no environment, is the state worth
        # refusing.
        for p in self.probes:
            recorded = [p['observed'], p['verdict'], p['env'], p['run_at']]
            if all(v is None for v in recorded):
                continue
            self.assertTrue(str(p['observed'] or '').strip(),
                            p['id'] + ' has a result but nothing observed')
            self.assertIn(p['verdict'], ('confirmed', 'refuted', 'partial'), p['id'])
            self.assertTrue(p['env'], p['id'] + ' has a result but no environment')
            self.assertTrue(p['run_at'], p['id'] + ' has a result but no run date')

    def test_every_named_story_exists(self):
        for p in self.probes:
            for sid in p['stories']:
                self.assertIn(sid, self.stories, '%s names %s' % (p['id'], sid))

    def test_methods_are_known(self):
        for p in self.probes:
            self.assertIn(p['method'], ('wp-cli', 'php', 'playwright'), p['id'])


if __name__ == '__main__':
    unittest.main()

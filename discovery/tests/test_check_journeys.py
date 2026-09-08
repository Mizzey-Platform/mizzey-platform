import os
import shutil
import tempfile
import unittest

from discovery import check

STORIES = [{'id': 'US-01-01', 'epic': 'E01', 'key': False, 'leverage': 'partial', 'trace': 'FIX-04'}]


def data(stories=None, verdicts=(), probes=(), plugins=(), journeys=()):
    """A Data bundle built by keyword, so nothing can be transposed by position."""
    return check.Data(STORIES if stories is None else stories,
                      list(verdicts), list(probes), list(plugins), list(journeys))


class TestJourneySteps(unittest.TestCase):
    def test_a_step_with_no_story_fails(self):
        journeys = [{'id': 'J-01', 'name': 'Guest buys', 'actor': 'guest',
                     'language': 'both', 'steps': [{'n': 1, 'stories': []}], 'gaps': []}]
        problems = check.check_journey_steps(data(journeys=journeys))
        self.assertEqual(len(problems), 1)
        self.assertIn('J-01', problems[0])

    def test_an_unknown_actor_fails(self):
        journeys = [{'id': 'J-01', 'name': 'x', 'actor': 'wizard', 'language': 'both',
                     'steps': [{'n': 1, 'stories': ['US-01-01']}], 'gaps': []}]
        problems = check.check_journey_steps(data(journeys=journeys))
        self.assertTrue(any('wizard' in p for p in problems))

    def test_an_unknown_language_fails(self):
        journeys = [{'id': 'J-01', 'name': 'x', 'actor': 'guest', 'language': 'fr',
                     'steps': [{'n': 1, 'stories': ['US-01-01']}], 'gaps': []}]
        problems = check.check_journey_steps(data(journeys=journeys))
        self.assertTrue(any('fr' in p for p in problems))

    def test_a_well_formed_journey_passes(self):
        journeys = [{'id': 'J-01', 'name': 'Guest buys', 'actor': 'guest', 'language': 'both',
                     'steps': [{'n': 1, 'stories': ['US-01-01']}], 'gaps': []}]
        self.assertEqual(check.check_journey_steps(data(journeys=journeys)), [])


class TestHouseRules(unittest.TestCase):
    def setUp(self):
        self.dir = tempfile.mkdtemp()
        self.addCleanup(shutil.rmtree, self.dir)

    def write(self, name, text):
        with open(os.path.join(self.dir, name), 'w', encoding='utf-8') as fh:
            fh.write(text)

    def test_an_em_dash_in_generated_output_fails(self):
        self.write('report.md', 'A sentence — with an em dash.\n')
        problems = check.check_house_rules_in(self.dir)
        self.assertEqual(len(problems), 1)
        self.assertIn('em dash', problems[0])

    def test_an_emoji_in_generated_output_fails(self):
        self.write('report.md', 'All good \U0001F600\n')
        problems = check.check_house_rules_in(self.dir)
        self.assertEqual(len(problems), 1)
        self.assertIn('emoji', problems[0])

    def test_clean_output_passes(self):
        self.write('report.md', 'A clean sentence, with a comma.\n')
        self.assertEqual(check.check_house_rules_in(self.dir), [])

    def test_only_markdown_is_examined(self):
        self.write('data.json', '{"note": "an em dash — in data is fine"}')
        self.assertEqual(check.check_house_rules_in(self.dir), [])

    def test_a_missing_directory_is_not_a_failure(self):
        self.assertEqual(check.check_house_rules_in(os.path.join(self.dir, 'nope')), [])


if __name__ == '__main__':
    unittest.main()

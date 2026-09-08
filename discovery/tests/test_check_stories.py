import unittest

from discovery import check

STORIES = [
    {'id': 'US-01-01', 'epic': 'E01', 'key': True, 'leverage': 'partial', 'trace': 'FIX-04'},
    {'id': 'US-04-02', 'epic': 'E04', 'key': False, 'leverage': 'native', 'trace': 'PDP-02'},
]


class TestUnknownStoryReferences(unittest.TestCase):
    def test_a_verdict_for_an_unknown_story_fails(self):
        verdicts = [{'story': 'US-99-99'}]
        problems = check.check_unknown_stories(STORIES, verdicts, [], [], [])
        self.assertEqual(len(problems), 1)
        self.assertIn('US-99-99', problems[0])

    def test_a_probe_naming_an_unknown_story_fails(self):
        probes = [{'id': 'P-001', 'stories': ['US-99-99']}]
        problems = check.check_unknown_stories(STORIES, [], probes, [], [])
        self.assertEqual(len(problems), 1)
        self.assertIn('P-001', problems[0])

    def test_a_journey_step_naming_an_unknown_story_fails(self):
        journeys = [{'id': 'J-01', 'steps': [{'n': 1, 'stories': ['US-99-99']}]}]
        problems = check.check_unknown_stories(STORIES, [], [], [], journeys)
        self.assertEqual(len(problems), 1)
        self.assertIn('J-01', problems[0])

    def test_known_story_ids_pass(self):
        verdicts = [{'story': 'US-01-01'}]
        probes = [{'id': 'P-001', 'stories': ['US-04-02']}]
        journeys = [{'id': 'J-01', 'steps': [{'n': 1, 'stories': ['US-01-01']}]}]
        self.assertEqual(check.check_unknown_stories(STORIES, verdicts, probes, [], journeys), [])


class TestTraceability(unittest.TestCase):
    def test_a_verdict_for_a_story_with_no_annex_a_trace_fails(self):
        stories = [{'id': 'US-01-01', 'epic': 'E01', 'key': True, 'trace': '  '}]
        problems = check.check_traceable(stories, [{'story': 'US-01-01'}], [], [], [])
        self.assertEqual(len(problems), 1)
        self.assertIn('US-01-01', problems[0])

    def test_a_traced_story_passes(self):
        self.assertEqual(check.check_traceable(STORIES, [{'story': 'US-01-01'}], [], [], []), [])


if __name__ == '__main__':
    unittest.main()

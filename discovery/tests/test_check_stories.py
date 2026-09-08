import unittest

from discovery import check

STORIES = [
    {'id': 'US-01-01', 'epic': 'E01', 'key': True, 'leverage': 'partial', 'trace': 'FIX-04'},
    {'id': 'US-04-02', 'epic': 'E04', 'key': False, 'leverage': 'native', 'trace': 'PDP-02'},
]


def data(stories=None, verdicts=(), probes=(), plugins=(), journeys=()):
    """A Data bundle built by keyword, so nothing can be transposed by position."""
    return check.Data(STORIES if stories is None else stories,
                      list(verdicts), list(probes), list(plugins), list(journeys))


class TestUnknownStoryReferences(unittest.TestCase):
    def test_a_verdict_for_an_unknown_story_fails(self):
        problems = check.check_unknown_stories(data(verdicts=[{'story': 'US-99-99'}]))
        self.assertEqual(len(problems), 1)
        self.assertIn('US-99-99', problems[0])

    def test_a_probe_naming_an_unknown_story_fails(self):
        problems = check.check_unknown_stories(
            data(probes=[{'id': 'P-001', 'stories': ['US-99-99']}]))
        self.assertEqual(len(problems), 1)
        self.assertIn('P-001', problems[0])

    def test_a_plugin_naming_an_unknown_story_fails(self):
        problems = check.check_unknown_stories(
            data(plugins=[{'id': 'PL-01', 'stories': ['US-99-99']}]))
        self.assertEqual(len(problems), 1)
        self.assertIn('PL-01', problems[0])

    def test_a_journey_step_naming_an_unknown_story_fails(self):
        problems = check.check_unknown_stories(
            data(journeys=[{'id': 'J-01', 'steps': [{'n': 1, 'stories': ['US-99-99']}]}]))
        self.assertEqual(len(problems), 1)
        self.assertIn('J-01', problems[0])

    def test_known_story_ids_pass(self):
        self.assertEqual(check.check_unknown_stories(data(
            verdicts=[{'story': 'US-01-01'}],
            probes=[{'id': 'P-001', 'stories': ['US-04-02']}],
            journeys=[{'id': 'J-01', 'steps': [{'n': 1, 'stories': ['US-01-01']}]}])), [])


class TestTraceability(unittest.TestCase):
    def test_a_verdict_for_a_story_with_no_annex_a_trace_fails(self):
        stories = [{'id': 'US-01-01', 'epic': 'E01', 'key': True, 'trace': '  '}]
        problems = check.check_traceable(
            data(stories=stories, verdicts=[{'story': 'US-01-01'}]))
        self.assertEqual(len(problems), 1)
        self.assertIn('US-01-01', problems[0])

    def test_a_traced_story_passes(self):
        self.assertEqual(check.check_traceable(data(verdicts=[{'story': 'US-01-01'}])), [])

    def test_an_unknown_story_is_left_to_the_other_rule(self):
        """One fault, one message. check_unknown_stories already reports this."""
        self.assertEqual(check.check_traceable(data(verdicts=[{'story': 'US-99-99'}])), [])


if __name__ == '__main__':
    unittest.main()

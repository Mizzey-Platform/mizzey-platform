import unittest

from discovery import riskset, schema


class TestRiskSet(unittest.TestCase):
    def setUp(self):
        self.stories = schema.load_stories()

    def test_the_real_dataset_gives_seventy_three(self):
        self.assertEqual(len(riskset.risk_set(self.stories)), 73)

    def test_the_light_pass_remainder_is_one_hundred_and_thirty_two(self):
        self.assertEqual(len(riskset.light_set(self.stories)), 132)

    def test_the_two_sets_do_not_overlap(self):
        self.assertEqual(riskset.risk_set(self.stories) & riskset.light_set(self.stories), set())

    def test_the_two_sets_cover_every_story(self):
        both = riskset.risk_set(self.stories) | riskset.light_set(self.stories)
        self.assertEqual(len(both), len(self.stories))

    def test_custom_epic_stories_are_in(self):
        rows = [{'id': 'US-13-01', 'epic': 'E13', 'key': False}]
        self.assertEqual(riskset.risk_set(rows), {'US-13-01'})

    def test_key_stories_are_in_whatever_the_epic(self):
        rows = [{'id': 'US-04-01', 'epic': 'E04', 'key': True}]
        self.assertEqual(riskset.risk_set(rows), {'US-04-01'})

    def test_an_ordinary_story_is_out(self):
        rows = [{'id': 'US-04-02', 'epic': 'E04', 'key': False}]
        self.assertEqual(riskset.risk_set(rows), set())


if __name__ == '__main__':
    unittest.main()

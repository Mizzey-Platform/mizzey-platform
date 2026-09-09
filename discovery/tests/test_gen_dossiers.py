import unittest

from discovery import gen_epic_dossiers as gen

STORIES = [
    {'id': 'US-13-01', 'epic': 'E13', 'epicname': 'RETURNS AND REFUNDS, CUSTOMER SIDE',
     'title': 'Request a return', 'narrative': 'As a shopper, I want to request a return',
     'trace': 'RET-01', 'leverage': 'custom', 'points': 8, 'sprint': 9, 'stage': 'S1',
     'stagenum': 4, 'prio': 'Must', 'key': True, 'scope': 'P1',
     'ac': ['The request captures evidence']},
]
VERDICTS = [
    {'story': 'US-13-01', 'claimed': 'custom', 'actual': 'build', 'confidence': 'proved',
     'evidence': ['P-005'], 'plugins': [], 'gap_rows': [5], 'points_flag': 'under',
     'risk': 'Woo has no returns workflow at all', 'open': ['Who approves a partial refund?']},
]


class TestDossier(unittest.TestCase):
    def setUp(self):
        self.text = gen.dossier('E13', STORIES, VERDICTS, [], [])

    def test_the_epic_name_is_the_heading(self):
        self.assertIn('RETURNS AND REFUNDS, CUSTOMER SIDE', self.text)

    def test_the_story_appears_with_its_trace(self):
        self.assertIn('US-13-01', self.text)
        self.assertIn('RET-01', self.text)

    def test_the_verdict_is_shown_beside_the_claim(self):
        self.assertIn('custom', self.text)
        self.assertIn('build', self.text)
        self.assertIn('proved', self.text)

    def test_open_questions_are_surfaced(self):
        self.assertIn('Who approves a partial refund?', self.text)

    def test_a_points_flag_that_is_not_ok_is_called_out(self):
        self.assertIn('under', self.text)

    def test_a_story_with_no_verdict_reads_as_not_yet_validated(self):
        text = gen.dossier('E13', STORIES, [], [], [])
        self.assertIn('not yet validated', text)

    def test_no_em_dash_and_no_emoji(self):
        self.assertNotIn('—', self.text)

    def test_headings_stay_at_h3_or_above(self):
        for line in self.text.split('\n'):
            if line.startswith('#'):
                self.assertLessEqual(len(line) - len(line.lstrip('#')), 3, line)


if __name__ == '__main__':
    unittest.main()

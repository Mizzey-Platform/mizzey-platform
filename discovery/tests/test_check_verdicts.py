import unittest

from discovery import check

# US-13-01 is in a custom epic, so it is in the risk set.
# US-04-02 is not key and not custom, so it gets the light pass.
STORIES = [
    {'id': 'US-13-01', 'epic': 'E13', 'key': False, 'leverage': 'custom', 'trace': 'RET-01'},
    {'id': 'US-04-02', 'epic': 'E04', 'key': False, 'leverage': 'native', 'trace': 'PDP-02'},
]


def data(stories=None, verdicts=(), probes=(), plugins=(), journeys=()):
    """A Data bundle built by keyword, so nothing can be transposed by position."""
    return check.Data(STORIES if stories is None else stories,
                      list(verdicts), list(probes), list(plugins), list(journeys))


def verdict(story, **kw):
    row = {'story': story, 'claimed': 'custom', 'actual': 'build',
           'confidence': 'reasoned', 'evidence': [], 'plugins': [],
           'gap_rows': [5], 'points_flag': 'ok', 'risk': '', 'open': []}
    row.update(kw)
    return row


class TestAssumedInRiskSet(unittest.TestCase):
    def test_a_risk_set_story_left_assumed_fails(self):
        problems = check.check_no_assumed_in_risk_set(
            data(verdicts=[verdict('US-13-01', confidence='assumed')]))
        self.assertEqual(len(problems), 1)
        self.assertIn('US-13-01', problems[0])

    def test_a_light_pass_story_may_stay_assumed(self):
        self.assertEqual(check.check_no_assumed_in_risk_set(
            data(verdicts=[verdict('US-04-02', confidence='assumed'),
                           verdict('US-13-01')])), [])

    def test_a_risk_set_story_with_no_verdict_at_all_fails(self):
        problems = check.check_no_assumed_in_risk_set(data())
        self.assertEqual(len(problems), 1)
        self.assertIn('US-13-01', problems[0])


class TestProvedNeedsEvidence(unittest.TestCase):
    def test_proved_with_no_probe_fails(self):
        problems = check.check_proved_has_evidence(
            data(verdicts=[verdict('US-13-01', confidence='proved', evidence=[])]))
        self.assertEqual(len(problems), 1)

    def test_proved_naming_a_probe_that_does_not_exist_fails(self):
        problems = check.check_proved_has_evidence(
            data(verdicts=[verdict('US-13-01', confidence='proved', evidence=['P-999'])],
                 probes=[{'id': 'P-001'}]))
        self.assertEqual(len(problems), 1)
        self.assertIn('P-999', problems[0])

    def test_proved_on_a_probe_that_has_not_run_fails(self):
        problems = check.check_proved_has_evidence(
            data(verdicts=[verdict('US-13-01', confidence='proved', evidence=['P-001'])],
                 probes=[{'id': 'P-001', 'verdict': None}]))
        self.assertEqual(len(problems), 1)
        self.assertIn('has not been run', problems[0])

    def test_proved_with_a_probe_that_ran_passes(self):
        self.assertEqual(check.check_proved_has_evidence(
            data(verdicts=[verdict('US-13-01', confidence='proved', evidence=['P-001'])],
                 probes=[{'id': 'P-001', 'verdict': 'confirmed'}])), [])


class TestOneVerdictPerStory(unittest.TestCase):
    def test_a_duplicate_row_fails(self):
        problems = check.check_one_verdict_per_story(
            data(verdicts=[verdict('US-13-01', confidence='assumed'),
                           verdict('US-13-01', confidence='reasoned')]))
        self.assertEqual(len(problems), 1)
        self.assertIn('US-13-01', problems[0])

    def test_a_story_reported_once_however_many_duplicates(self):
        problems = check.check_one_verdict_per_story(
            data(verdicts=[verdict('US-13-01'), verdict('US-13-01'), verdict('US-13-01')]))
        self.assertEqual(len(problems), 1)

    def test_distinct_stories_pass(self):
        self.assertEqual(check.check_one_verdict_per_story(
            data(verdicts=[verdict('US-13-01'), verdict('US-04-02')])), [])


class TestAllowedValues(unittest.TestCase):
    def test_a_misspelt_confidence_fails(self):
        problems = check.check_allowed_values(
            data(verdicts=[verdict('US-13-01', confidence='reasonned')]))
        self.assertEqual(len(problems), 1)
        self.assertIn('reasonned', problems[0])

    def test_an_unknown_actual_fails(self):
        problems = check.check_allowed_values(
            data(verdicts=[verdict('US-13-01', actual='mostly')]))
        self.assertEqual(len(problems), 1)

    def test_an_unknown_points_flag_fails(self):
        problems = check.check_allowed_values(
            data(verdicts=[verdict('US-13-01', points_flag='way under')]))
        self.assertEqual(len(problems), 1)

    def test_an_undecided_actual_is_allowed(self):
        self.assertEqual(check.check_allowed_values(
            data(verdicts=[verdict('US-13-01', actual=None)])), [])

    def test_a_bad_probe_verdict_fails(self):
        problems = check.check_allowed_values(
            data(probes=[{'id': 'P-001', 'verdict': 'maybe'}]))
        self.assertEqual(len(problems), 1)
        self.assertIn('P-001', problems[0])


class TestBuildNeedsAGapRow(unittest.TestCase):
    def test_build_with_no_gap_row_and_no_risk_note_fails(self):
        problems = check.check_build_traces_to_gap(
            data(verdicts=[verdict('US-13-01', actual='build', gap_rows=[], risk='')]))
        self.assertEqual(len(problems), 1)

    def test_build_with_no_gap_row_but_an_explanation_passes(self):
        self.assertEqual(check.check_build_traces_to_gap(
            data(verdicts=[verdict('US-13-01', actual='build', gap_rows=[],
                                   risk='Not in section 7; found by probe P-012')])), [])

    def test_native_needs_no_gap_row(self):
        self.assertEqual(check.check_build_traces_to_gap(
            data(verdicts=[verdict('US-04-02', actual='native', gap_rows=[], risk='')])), [])


if __name__ == '__main__':
    unittest.main()

import unittest

from discovery import gen_journey_docs, gen_plugin_register, gen_validation_report, schema

STORIES = [
    {'id': 'US-13-01', 'epic': 'E13', 'epicname': 'RETURNS', 'title': 'Request a return',
     'trace': 'RET-01', 'leverage': 'custom', 'points': 8, 'key': True},
    {'id': 'US-04-02', 'epic': 'E04', 'epicname': 'PRODUCT DETAIL', 'title': 'See a price',
     'trace': 'PDP-02', 'leverage': 'native', 'points': 2, 'key': False},
]
PROBES = [
    {'id': 'P-001', 'question': 'Does Woo merge the cart?', 'gap_rows': [1],
     'expected': 'It replaces', 'observed': 'It replaced', 'verdict': 'confirmed',
     'stories': [], 'script': 'x.php', 'method': 'php', 'env': {}, 'run_at': '2026-09-09',
     'notes': ''},
    {'id': 'P-002', 'question': 'Is the price snapshot native?', 'gap_rows': [],
     'expected': 'Native', 'observed': 'Not on variations', 'verdict': 'refuted',
     'stories': [], 'script': 'y.php', 'method': 'php', 'env': {}, 'run_at': '2026-09-09',
     'notes': ''},
]


class TestValidationReport(unittest.TestCase):
    def setUp(self):
        self.text = gen_validation_report.report(
            schema.Data(STORIES, [], PROBES, [], []))

    def test_refuted_probes_are_called_out_first(self):
        self.assertIn('P-002', self.text)
        self.assertLess(self.text.index('Refuted'), self.text.index('Confirmed'))

    def test_the_expected_and_observed_are_shown_together(self):
        self.assertIn('Native', self.text)
        self.assertIn('Not on variations', self.text)

    def test_the_risk_set_size_is_reported(self):
        self.assertIn('0 of 1', self.text)

    def test_no_em_dash(self):
        self.assertNotIn('\u2014', self.text)


class TestPluginRegister(unittest.TestCase):
    def test_the_total_is_summed_and_compared_to_the_quote(self):
        plugins = [{'id': 'PL-01', 'need': 'Translation', 'stories': [], 'candidates': [],
                    'decision': 'Example', 'evidence': [], 'cost_annual': 107,
                    'currency': 'USD', 'licence': 'commercial', 'owner': 'client'}]
        text = gen_plugin_register.register(plugins)
        self.assertIn('107', text)
        self.assertIn('Example', text)

    def test_an_undecided_need_is_listed_as_outstanding(self):
        plugins = [{'id': 'PL-02', 'need': 'Google sign in', 'stories': [], 'candidates': [],
                    'decision': None, 'evidence': [], 'cost_annual': None,
                    'currency': 'USD', 'licence': None, 'owner': 'client'}]
        self.assertIn('not yet decided', gen_plugin_register.register(plugins))


class TestJourneyDocs(unittest.TestCase):
    def test_each_step_lists_its_stories(self):
        journeys = [{'id': 'J-01', 'name': 'Guest buys and returns', 'actor': 'guest',
                     'language': 'both',
                     'steps': [{'n': 1, 'description': 'Add to cart',
                                'stories': ['US-04-02'], 'screen': 'pdp'}],
                     'gaps': ['Cart is replaced on login']}]
        text = gen_journey_docs.journey_doc(journeys[0], STORIES)
        self.assertIn('US-04-02', text)
        self.assertIn('See a price', text)
        self.assertIn('Cart is replaced on login', text)


if __name__ == '__main__':
    unittest.main()

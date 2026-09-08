import unittest

from discovery import check

STORIES = [{'id': 'US-01-01', 'epic': 'E01', 'key': False, 'leverage': 'partial', 'trace': 'FIX-04'}]


def data(stories=None, verdicts=(), probes=(), plugins=(), journeys=()):
    """A Data bundle built by keyword, so nothing can be transposed by position."""
    return check.Data(STORIES if stories is None else stories,
                      list(verdicts), list(probes), list(plugins), list(journeys))


def plugin(pid, **kw):
    row = {'id': pid, 'need': 'Multilingual product data', 'stories': ['US-01-01'],
           'candidates': [], 'decision': 'Example Plugin', 'evidence': [],
           'cost_annual': 107, 'currency': 'USD', 'licence': 'commercial',
           'owner': 'client', 'acknowledged_over_quote': False}
    row.update(kw)
    return row


class TestPluginCompleteness(unittest.TestCase):
    def test_a_decision_with_no_cost_fails(self):
        problems = check.check_plugin_complete(data(plugins=[plugin('PL-01', cost_annual=None)]))
        self.assertEqual(len(problems), 1)
        self.assertIn('PL-01', problems[0])

    def test_a_decision_with_no_licence_fails(self):
        problems = check.check_plugin_complete(data(plugins=[plugin('PL-01', licence=None)]))
        self.assertEqual(len(problems), 1)

    def test_an_undecided_need_is_not_yet_a_failure(self):
        self.assertEqual(check.check_plugin_complete(
            data(plugins=[plugin('PL-01', decision=None, cost_annual=None, licence=None)])), [])

    def test_build_instead_needs_no_cost(self):
        self.assertEqual(check.check_plugin_complete(
            data(plugins=[plugin('PL-01', decision='build instead',
                                 cost_annual=None, licence=None)])), [])

    def test_a_complete_decision_passes(self):
        self.assertEqual(check.check_plugin_complete(data(plugins=[plugin('PL-01')])), [])


class TestPluginCeiling(unittest.TestCase):
    def test_a_total_above_the_quoted_figure_fails(self):
        rows = [plugin('PL-01', cost_annual=107), plugin('PL-02', cost_annual=60)]
        problems = check.check_plugin_ceiling(data(plugins=rows))
        self.assertEqual(len(problems), 1)
        self.assertIn('167', problems[0])
        self.assertIn('107', problems[0])

    def test_an_acknowledged_overage_passes(self):
        rows = [plugin('PL-01', cost_annual=107),
                plugin('PL-02', cost_annual=60, acknowledged_over_quote=True)]
        self.assertEqual(check.check_plugin_ceiling(data(plugins=rows)), [])

    def test_a_total_at_the_quoted_figure_passes(self):
        self.assertEqual(check.check_plugin_ceiling(
            data(plugins=[plugin('PL-01', cost_annual=107)])), [])

    def test_non_usd_costs_are_not_silently_summed(self):
        rows = [plugin('PL-01', cost_annual=107),
                plugin('PL-02', cost_annual=50, currency='EUR')]
        problems = check.check_plugin_ceiling(data(plugins=rows))
        self.assertTrue(any('EUR' in p for p in problems))


if __name__ == '__main__':
    unittest.main()

import unittest

from discovery import schema


class TestSchema(unittest.TestCase):
    def test_verdict_allowed_values(self):
        self.assertEqual(schema.ACTUAL, ('native', 'extend', 'build', 'plugin'))
        self.assertEqual(schema.CONFIDENCE, ('proved', 'reasoned', 'assumed'))
        self.assertEqual(schema.POINTS_FLAG, ('ok', 'under', 'over'))

    def test_custom_epics_are_the_five_from_the_spec(self):
        self.assertEqual(schema.CUSTOM_EPICS, frozenset({'E13', 'E15', 'E20', 'E22', 'E23'}))

    def test_quoted_ceiling_matches_what_the_client_was_told(self):
        self.assertEqual(schema.QUOTED_ANNUAL_USD, 107)

    def test_the_overage_acknowledgement_is_part_of_a_plugin_row(self):
        self.assertIn('acknowledged_over_quote', schema.PLUGIN_FIELDS)

    def test_the_data_bundle_names_all_five_datasets(self):
        self.assertEqual(schema.Data._fields,
                         ('stories', 'verdicts', 'probes', 'plugins', 'journeys'))

    def test_load_data_returns_a_bundle_with_the_real_stories(self):
        data = schema.load_data()
        self.assertEqual(len(data.stories), 205)
        self.assertIsInstance(data.verdicts, list)

    def test_missing_fields_are_reported(self):
        row = {'story': 'US-01-01'}
        missing = schema.missing_fields(row, schema.VERDICT_FIELDS)
        self.assertIn('actual', missing)
        self.assertIn('confidence', missing)
        self.assertNotIn('story', missing)

    def test_complete_row_reports_nothing_missing(self):
        row = {k: None for k in schema.VERDICT_FIELDS}
        self.assertEqual(schema.missing_fields(row, schema.VERDICT_FIELDS), [])

    def test_blank_verdict_row_has_every_field(self):
        self.assertEqual(sorted(schema.blank_verdict('US-01-01').keys()),
                         sorted(schema.VERDICT_FIELDS))
        self.assertEqual(schema.blank_verdict('US-01-01')['story'], 'US-01-01')


if __name__ == '__main__':
    unittest.main()

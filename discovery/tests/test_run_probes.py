import unittest

from discovery import run_probes


class TestParseResult(unittest.TestCase):
    def test_valid_json_is_parsed(self):
        out = '{"observed": "Woo replaced the cart", "verdict": "confirmed"}'
        result = run_probes.parse_result(out)
        self.assertEqual(result['verdict'], 'confirmed')
        self.assertEqual(result['observed'], 'Woo replaced the cart')

    def test_surrounding_noise_is_tolerated(self):
        out = 'PHP Notice: something\n{"observed": "x", "verdict": "partial"}\ntrailing\n'
        self.assertEqual(run_probes.parse_result(out)['verdict'], 'partial')

    def test_unparseable_output_raises(self):
        with self.assertRaises(ValueError):
            run_probes.parse_result('no json here at all')

    def test_an_unknown_verdict_raises(self):
        with self.assertRaises(ValueError):
            run_probes.parse_result('{"observed": "x", "verdict": "maybe"}')

    def test_a_missing_observed_raises(self):
        with self.assertRaises(ValueError):
            run_probes.parse_result('{"verdict": "confirmed"}')


class TestApplyResult(unittest.TestCase):
    def test_the_row_is_updated_in_place(self):
        row = {'id': 'P-001', 'observed': None, 'verdict': None, 'env': None, 'run_at': None}
        run_probes.apply_result(row, {'observed': 'x', 'verdict': 'refuted'}, {'wp': '7.1'})
        self.assertEqual(row['verdict'], 'refuted')
        self.assertEqual(row['observed'], 'x')
        self.assertEqual(row['env'], {'wp': '7.1'})
        self.assertIsNotNone(row['run_at'])

    def test_run_at_is_an_iso_date(self):
        row = {'id': 'P-001', 'observed': None, 'verdict': None, 'env': None, 'run_at': None}
        run_probes.apply_result(row, {'observed': 'x', 'verdict': 'confirmed'}, {})
        self.assertRegex(row['run_at'], r'^\d{4}-\d{2}-\d{2}$')


class TestSelect(unittest.TestCase):
    def test_selecting_by_id(self):
        rows = [{'id': 'P-001'}, {'id': 'P-002'}]
        self.assertEqual(run_probes.select(rows, ['P-002']), [rows[1]])

    def test_selecting_nothing_returns_everything(self):
        rows = [{'id': 'P-001'}, {'id': 'P-002'}]
        self.assertEqual(run_probes.select(rows, []), rows)

    def test_an_unknown_id_raises(self):
        with self.assertRaises(KeyError):
            run_probes.select([{'id': 'P-001'}], ['P-999'])


if __name__ == '__main__':
    unittest.main()

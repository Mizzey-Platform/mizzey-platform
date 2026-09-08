import os
import unittest

HERE = os.path.dirname(os.path.abspath(__file__))
TASKS = os.path.join(os.path.dirname(HERE), 'tasks')

NAMES = ('PROBE', 'VERDICT', 'PLUGIN', 'JOURNEY', 'WIREFRAME')


class TestTaskTemplates(unittest.TestCase):
    def read(self, name):
        with open(os.path.join(TASKS, name + '.md'), encoding='utf-8') as fh:
            return fh.read()

    def flat(self, name):
        """Whitespace collapsed, so a line wrap cannot hide a required phrase."""
        return ' '.join(self.read(name).split())

    def test_all_five_exist(self):
        for name in NAMES:
            self.assertTrue(os.path.exists(os.path.join(TASKS, name + '.md')), name)

    def test_each_states_the_two_lanes_rule(self):
        for name in NAMES:
            self.assertIn('Annex A', self.flat(name), name)

    def test_each_tells_a_blocked_agent_to_write_open_and_continue(self):
        for name in NAMES:
            self.assertIn('do not stop', self.flat(name).lower(), name)

    def test_each_names_the_files_to_read_and_the_file_to_write(self):
        for name in NAMES:
            text = self.read(name)
            self.assertIn('## Read', text, name)
            self.assertIn('## Write', text, name)

    def test_each_forbids_exploring_the_archive(self):
        for name in NAMES:
            self.assertIn('_archive', self.flat(name), name)

    def test_no_em_dash_and_no_emoji(self):
        for name in NAMES:
            self.assertNotIn('\u2014', self.read(name), name)


if __name__ == '__main__':
    unittest.main()

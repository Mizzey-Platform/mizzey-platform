import os
import unittest

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)


class TestScaffold(unittest.TestCase):
    def test_stories_json_is_reachable(self):
        path = os.path.join(ROOT, '..', 'scripts', 'stories.json')
        self.assertTrue(os.path.exists(path), 'stories.json must be reachable from discovery/')

    def test_data_directories_exist(self):
        for name in ('data', 'probes', 'generated', 'tasks'):
            self.assertTrue(os.path.isdir(os.path.join(ROOT, name)), name + ' must exist')


if __name__ == '__main__':
    unittest.main()

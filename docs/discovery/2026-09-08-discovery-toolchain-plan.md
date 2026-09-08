# Discovery Toolchain Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the data schema, gate and generators that Step 0 of the discovery phase requires, so that Steps 2 to 6 can be handed to parallel agents without drift.

**Architecture:** Four sidecar JSON datasets keyed by story id, never overwriting the generated `stories.json`. A pure-function gate (`check.py`) that returns a list of failure strings, wrapped by a thin CLI so every rule is unit-testable. Generators read the datasets and write markdown into `generated/`, which is committed but never hand-edited.

**Tech Stack:** Python 3.10 standard library only. `unittest` for tests (pytest is not installed). `wp-cli` for probe execution. No third-party dependencies.

**Source spec:** `docs/discovery/2026-09-08-discovery-phase-design.md`

**Scope:** This plan implements Step 0 only. Step 1 (`wp corex make:site Mizzey`) is Mustafa's and blocks Steps 2 onward. Steps 2 to 6 are content work executed with this toolchain, not part of this plan.

---

## File Structure

All paths relative to `C:\wamp64\www\mizzey\platform`.

| File | Responsibility |
|---|---|
| `discovery/schema.py` | Field definitions, allowed values, constants. The single source of truth for shape |
| `discovery/riskset.py` | Computes the 73-story risk-weighted set from `scripts/stories.json` |
| `discovery/check.py` | The gate. One function per rule, all returning `list[str]` |
| `discovery/run_probes.py` | Executes probe scripts, writes `observed`, `verdict`, `env`, `run_at` back |
| `discovery/gen_epic_dossiers.py` | 27 markdown dossiers |
| `discovery/gen_validation_report.py` | The consolidated validation report |
| `discovery/gen_plugin_register.py` | The plugin register with total annual cost |
| `discovery/gen_journey_docs.py` | One document per journey |
| `discovery/data/*.json` | The four datasets |
| `discovery/probes/` | Probe scripts. Populated in Step 2, not by this plan |
| `discovery/generated/` | Generator output. Committed, never hand-edited |
| `discovery/tasks/*.md` | The five agent task templates |
| `discovery/tests/test_*.py` | Unit tests, run with `python -m unittest` |

`discovery/` sits at the repo root beside `scripts/`, not under `docs/`, because it contains executable code.

---

## Task 1: Scaffold and test runner

**Files:**
- Create: `discovery/__init__.py`
- Create: `discovery/tests/__init__.py`
- Create: `discovery/tests/test_scaffold.py`
- Create: `discovery/data/.gitkeep`, `discovery/probes/.gitkeep`, `discovery/generated/.gitkeep`, `discovery/tasks/.gitkeep`

- [ ] **Step 1: Create the directory tree**

```bash
cd C:/wamp64/www/mizzey/platform
mkdir -p discovery/data discovery/probes discovery/generated discovery/tasks discovery/tests
touch discovery/__init__.py discovery/tests/__init__.py
touch discovery/data/.gitkeep discovery/probes/.gitkeep discovery/generated/.gitkeep discovery/tasks/.gitkeep
```

- [ ] **Step 2: Write the failing test**

Create `discovery/tests/test_scaffold.py`:

```python
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
```

- [ ] **Step 3: Run the test**

Run: `cd C:/wamp64/www/mizzey/platform && python -m unittest discovery.tests.test_scaffold -v`
Expected: PASS, 2 tests. If `stories.json` fails, the directory tree is in the wrong place.

- [ ] **Step 4: Remove the empty discovery folders from the parent**

The empty `discovery/` and `design/` trees created in the parent on 8 September are superseded by this one for data. The parent keeps only scratch space.

```bash
rm -rf "C:/wamp64/www/mizzey/discovery/01-epics" "C:/wamp64/www/mizzey/discovery/02-user-stories" "C:/wamp64/www/mizzey/discovery/03-journeys" "C:/wamp64/www/mizzey/discovery/04-validation" "C:/wamp64/www/mizzey/discovery/05-plugins"
mkdir -p "C:/wamp64/www/mizzey/discovery/scratch"
```

Leave `C:/wamp64/www/mizzey/discovery/README.md` and the whole `design/` tree in place. Wireframes are built in Step 6 and stay in the parent, because they are not data.

- [ ] **Step 5: Update the parent discovery README to point at the repo**

Replace the table in `C:/wamp64/www/mizzey/discovery/README.md` with:

```markdown
The discovery datasets, generators and gate live in the platform repo, under
version control, at `../platform/discovery/`. Read
`../platform/docs/discovery/2026-09-08-discovery-phase-design.md` before working here.

This folder holds only scratch: probe output, screenshots, and working notes that
are not part of the dataset.
```

- [ ] **Step 6: Commit**

```bash
cd C:/wamp64/www/mizzey/platform
git add discovery/
git commit -m "Scaffold the discovery toolchain

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 2: The schema module

**Files:**
- Create: `discovery/schema.py`
- Create: `discovery/tests/test_schema.py`

- [ ] **Step 1: Write the failing test**

Create `discovery/tests/test_schema.py`:

```python
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
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `python -m unittest discovery.tests.test_schema -v`
Expected: FAIL with `ModuleNotFoundError: No module named 'discovery.schema'`

- [ ] **Step 3: Write the implementation**

Create `discovery/schema.py`:

```python
"""Field definitions and constants for the discovery datasets.

This is the single source of truth for the shape of the four sidecar datasets.
Nothing else defines a field name.
"""

import json
import os
from collections import namedtuple

HERE = os.path.dirname(os.path.abspath(__file__))
DATA = os.path.join(HERE, 'data')
STORIES = os.path.join(HERE, '..', 'scripts', 'stories.json')

# The five epics the Technical Design classes as custom, where WooCommerce
# contributes nothing. 44 stories, 211 points, 31 per cent of the 670 total.
CUSTOM_EPICS = frozenset({'E13', 'E15', 'E20', 'E22', 'E23'})

# Technical Design section 11 puts about 107 USD a year in front of the client
# for the translation plugin, and nothing else with a meaningful licence cost.
# A register total above this is a commercial conversation, not a technical one.
QUOTED_ANNUAL_USD = 107

ACTUAL = ('native', 'extend', 'build', 'plugin')
CONFIDENCE = ('proved', 'reasoned', 'assumed')
POINTS_FLAG = ('ok', 'under', 'over')
PROBE_VERDICT = ('confirmed', 'refuted', 'partial')
ACTORS = ('shopper', 'guest', 'admin', 'fulfilment', 'support')
LANGUAGES = ('ar', 'en', 'both')

VERDICT_FIELDS = ('story', 'claimed', 'actual', 'confidence', 'evidence',
                  'plugins', 'gap_rows', 'points_flag', 'risk', 'open')

PROBE_FIELDS = ('id', 'question', 'stories', 'gap_rows', 'script', 'method',
                'expected', 'observed', 'verdict', 'env', 'run_at', 'notes')

PLUGIN_FIELDS = ('id', 'need', 'stories', 'candidates', 'decision', 'evidence',
                 'cost_annual', 'currency', 'licence', 'owner',
                 'acknowledged_over_quote')

JOURNEY_FIELDS = ('id', 'name', 'actor', 'language', 'steps', 'gaps')

# Every dataset in one bundle. Rules and generators take this rather than five
# positional lists: probes and plugins are both lists of dicts with an id, so as
# positional arguments they could be transposed at a call site and a test would
# pass for the wrong reason. Naming the field makes that impossible.
Data = namedtuple('Data', 'stories verdicts probes plugins journeys')


def missing_fields(row, fields):
    """Return the field names absent from row, in declaration order."""
    return [f for f in fields if f not in row]


def blank_verdict(story_id):
    """A verdict row with every field present and nothing decided."""
    return {
        'story': story_id,
        'claimed': None,
        'actual': None,
        'confidence': 'assumed',
        'evidence': [],
        'plugins': [],
        'gap_rows': [],
        'points_flag': 'ok',
        'risk': '',
        'open': [],
    }


def load(name):
    """Load one dataset by bare name, returning [] if it does not exist yet."""
    path = os.path.join(DATA, name + '.json')
    if not os.path.exists(path):
        return []
    with open(path, encoding='utf-8') as fh:
        return json.load(fh)


def save(name, rows):
    """Write one dataset, pretty printed, newline terminated, UTF-8."""
    path = os.path.join(DATA, name + '.json')
    with open(path, 'w', encoding='utf-8') as fh:
        json.dump(rows, fh, ensure_ascii=False, indent=2)
        fh.write('\n')


def load_stories():
    """The generated story list. Read only. Never write to this file."""
    with open(STORIES, encoding='utf-8') as fh:
        return json.load(fh)


def load_data():
    """Every dataset, in one bundle."""
    return Data(load_stories(), load('verdicts'), load('probes'),
                load('plugins'), load('journeys'))
```

- [ ] **Step 4: Run the tests**

Run: `python -m unittest discovery.tests.test_schema -v`
Expected: PASS, 9 tests.

- [ ] **Step 5: Commit**

```bash
git add discovery/schema.py discovery/tests/test_schema.py
git commit -m "Add the discovery dataset schema

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 3: The risk-weighted set

**Files:**
- Create: `discovery/riskset.py`
- Create: `discovery/tests/test_riskset.py`

- [ ] **Step 1: Write the failing test**

Create `discovery/tests/test_riskset.py`:

```python
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
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `python -m unittest discovery.tests.test_riskset -v`
Expected: FAIL with `ModuleNotFoundError: No module named 'discovery.riskset'`

- [ ] **Step 3: Write the implementation**

Create `discovery/riskset.py`:

```python
"""Which stories get full validation and which get a light pass.

Risk-weighted, per the design: the five custom epics plus every story flagged
key. That is 73 of the 205, and it is where the effort and the risk are.
"""

from discovery.schema import CUSTOM_EPICS


def risk_set(stories):
    """Story ids requiring full validation."""
    return {s['id'] for s in stories
            if s['epic'] in CUSTOM_EPICS or s['key']}


def light_set(stories):
    """Story ids requiring only a confirmation that native is true."""
    risky = risk_set(stories)
    return {s['id'] for s in stories if s['id'] not in risky}
```

- [ ] **Step 4: Run the tests**

Run: `python -m unittest discovery.tests.test_riskset -v`
Expected: PASS, 7 tests.

- [ ] **Step 5: Commit**

```bash
git add discovery/riskset.py discovery/tests/test_riskset.py
git commit -m "Compute the risk-weighted story set

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 4: Gate rules for story references

**Files:**
- Create: `discovery/check.py`
- Create: `discovery/tests/test_check_stories.py`

Every rule is a function taking the loaded datasets and returning `list[str]`. An empty list means the rule passed. This makes each rule testable without touching disk.

- [ ] **Step 1: Write the failing test**

Create `discovery/tests/test_check_stories.py`:

```python
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
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `python -m unittest discovery.tests.test_check_stories -v`
Expected: FAIL with `ModuleNotFoundError: No module named 'discovery.check'`

- [ ] **Step 3: Write the implementation**

Create `discovery/check.py`:

```python
"""The discovery gate.

Every rule is a function with the signature
    rule(data) -> list[str]
returning one string per problem. An empty list means the rule passed.

`data` is the Data bundle below rather than five positional lists. Probes and
plugins are both lists of dicts with an id, so as positional arguments they could
be transposed at a call site and a test would pass for the wrong reason. Naming
the field makes that impossible.

Keeping rules pure means each one is unit-testable without touching disk, and
adding a rule is adding a function to RULES.
"""

import sys

from discovery import riskset, schema
from discovery.schema import Data  # re-exported so tests can build a bundle


def check_unknown_stories(data):
    """No dataset may reference a story id that is not in stories.json."""
    known = {s['id'] for s in data.stories}
    problems = []
    for v in data.verdicts:
        if v.get('story') not in known:
            problems.append('verdict names unknown story %s' % v.get('story'))
    for p in data.probes:
        for sid in p.get('stories', []):
            if sid not in known:
                problems.append('probe %s names unknown story %s' % (p.get('id'), sid))
    for pl in data.plugins:
        for sid in pl.get('stories', []):
            if sid not in known:
                problems.append('plugin %s names unknown story %s' % (pl.get('id'), sid))
    for j in data.journeys:
        for step in j.get('steps', []):
            for sid in step.get('stories', []):
                if sid not in known:
                    problems.append('journey %s step %s names unknown story %s'
                                    % (j.get('id'), step.get('n'), sid))
    return problems


def check_traceable(data):
    """Every story carrying a verdict must trace to Annex A."""
    trace = {s['id']: (s.get('trace') or '').strip() for s in data.stories}
    # A verdict naming a story that does not exist is check_unknown_stories'
    # problem, not this one. Staying silent here keeps one fault to one message.
    return ['verdict for %s, which has no Annex A trace' % v['story']
            for v in data.verdicts
            if v.get('story') in trace and not trace[v['story']]]


RULES = [
    check_unknown_stories,
    check_traceable,
]


def run_checks():
    """Run every rule over every dataset. Returns the problem list."""
    data = schema.load_data()
    problems = []
    for rule in RULES:
        problems.extend(rule(data))
    return problems


def main():
    problems = run_checks()
    for p in problems:
        print('  FAIL  ' + p)
    if problems:
        print('
%d problem(s)' % len(problems))
        return 1
    print('clean')
    return 0


if __name__ == '__main__':
    sys.exit(main())
```

- [ ] **Step 4: Run the tests**

Run: `python -m unittest discovery.tests.test_check_stories -v`
Expected: PASS, 8 tests.

- [ ] **Step 5: Run the gate against the empty datasets**

Run: `python -m discovery.check`
Expected: `clean`

- [ ] **Step 6: Commit**

```bash
git add discovery/check.py discovery/tests/test_check_stories.py
git commit -m "Add the discovery gate with story reference rules

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 5: Gate rules for verdicts

**Files:**
- Modify: `discovery/check.py` (add three rule functions, extend `RULES`)
- Create: `discovery/tests/test_check_verdicts.py`

- [ ] **Step 1: Write the failing test**

Create `discovery/tests/test_check_verdicts.py`:

```python
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


class TestRiskSetHasRealVerdict(unittest.TestCase):
    def test_a_risk_set_story_left_assumed_fails(self):
        problems = check.check_risk_set_has_real_verdict(
            data(verdicts=[verdict('US-13-01', confidence='assumed')]))
        self.assertEqual(len(problems), 1)
        self.assertIn('US-13-01', problems[0])

    def test_a_light_pass_story_may_stay_assumed(self):
        self.assertEqual(check.check_risk_set_has_real_verdict(
            data(verdicts=[verdict('US-04-02', confidence='assumed'),
                           verdict('US-13-01')])), [])

    def test_a_risk_set_story_with_no_verdict_at_all_fails(self):
        problems = check.check_risk_set_has_real_verdict(data())
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


class TestNoDuplicateRows(unittest.TestCase):
    def test_a_duplicate_verdict_fails(self):
        problems = check.check_no_duplicate_rows(
            data(verdicts=[verdict('US-13-01', confidence='assumed'),
                           verdict('US-13-01', confidence='reasoned')]))
        self.assertEqual(len(problems), 1)
        self.assertIn('US-13-01', problems[0])

    def test_a_key_is_reported_once_however_many_duplicates(self):
        problems = check.check_no_duplicate_rows(
            data(verdicts=[verdict('US-13-01'), verdict('US-13-01'), verdict('US-13-01')]))
        self.assertEqual(len(problems), 1)

    def test_a_duplicate_probe_id_fails(self):
        problems = check.check_no_duplicate_rows(
            data(probes=[{'id': 'P-001', 'verdict': None},
                         {'id': 'P-001', 'verdict': 'confirmed'}]))
        self.assertEqual(len(problems), 1)
        self.assertIn('P-001', problems[0])

    def test_a_duplicate_plugin_id_fails(self):
        problems = check.check_no_duplicate_rows(
            data(plugins=[{'id': 'PL-01'}, {'id': 'PL-01'}]))
        self.assertEqual(len(problems), 1)

    def test_a_duplicate_journey_id_fails(self):
        problems = check.check_no_duplicate_rows(
            data(journeys=[{'id': 'J-01'}, {'id': 'J-01'}]))
        self.assertEqual(len(problems), 1)

    def test_distinct_keys_pass(self):
        self.assertEqual(check.check_no_duplicate_rows(
            data(verdicts=[verdict('US-13-01'), verdict('US-04-02')],
                 probes=[{'id': 'P-001'}, {'id': 'P-002'}])), [])


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

    def test_build_with_a_gap_row_passes(self):
        self.assertEqual(check.check_build_traces_to_gap(
            data(verdicts=[verdict('US-13-01', actual='build', gap_rows=[5], risk='')])), [])

    def test_native_needs_no_gap_row(self):
        self.assertEqual(check.check_build_traces_to_gap(
            data(verdicts=[verdict('US-04-02', actual='native', gap_rows=[], risk='')])), [])


if __name__ == '__main__':
    unittest.main()
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `python -m unittest discovery.tests.test_check_verdicts -v`
Expected: FAIL with `AttributeError: module 'discovery.check' has no attribute 'check_risk_set_has_real_verdict'`

- [ ] **Step 3: Write the implementation**

In `discovery/check.py`, insert these three functions immediately after `check_traceable`:

```python
def check_risk_set_has_real_verdict(data):
    """Every risk-set story needs a verdict, and none of them may stay assumed.

    A missing verdict is the same failure as an assumed one: in both cases
    nobody has looked.
    """
    risky = riskset.risk_set(data.stories)
    # Last write wins here. check_no_duplicate_rows is what makes that safe.
    seen = {v['story']: v for v in data.verdicts if 'story' in v}
    problems = []
    for sid in sorted(risky):
        if sid not in seen:
            problems.append('%s is in the risk set and has no verdict' % sid)
        elif seen[sid].get('confidence') == 'assumed':
            problems.append('%s is in the risk set and is still assumed' % sid)
    return problems


def check_proved_has_evidence(data):
    """confidence "proved" means a probe ran and reached a verdict.

    Citing a probe that exists but was never executed is the false confidence
    this whole dataset exists to prevent, so an unrun probe is not evidence.
    """
    # Last write wins here. check_no_duplicate_rows is what makes that safe:
    # without it a second row reusing a probe id could mask an unrun probe and
    # let a proved verdict through the whole gate.
    by_id = {p.get('id'): p for p in data.probes}
    problems = []
    for v in data.verdicts:
        if v.get('confidence') != 'proved':
            continue
        evidence = v.get('evidence') or []
        if not evidence:
            problems.append('%s is proved with no probe in evidence' % v.get('story'))
        for pid in evidence:
            if pid not in by_id:
                problems.append('%s cites probe %s, which does not exist'
                                % (v.get('story'), pid))
            elif not by_id[pid].get('verdict'):
                problems.append('%s is proved on probe %s, which has not been run'
                                % (v.get('story'), pid))
    return problems


def check_no_duplicate_rows(data):
    """Every row must be reachable by its key.

    The rules index each dataset by key, so a duplicate silently wins and the
    row it replaced is never inspected. For probes that is not cosmetic: a
    second row reusing an id can mask an unrun probe and let a proved verdict
    through the entire gate. Parallel agents append to these files, so say so
    rather than letting the later row erase the earlier one.
    """
    problems = []
    for label, rows, key in (('verdict', data.verdicts, 'story'),
                             ('probe', data.probes, 'id'),
                             ('plugin', data.plugins, 'id'),
                             ('journey', data.journeys, 'id')):
        seen = set()
        duplicates = []
        for row in rows:
            value = row.get(key)
            if value in seen and value not in duplicates:
                duplicates.append(value)
            seen.add(value)
        problems.extend('%s %s appears in more than one row' % (label, value)
                        for value in duplicates)
    return problems


def check_build_traces_to_gap(data):
    """Anything classed build should trace to a Technical Design gap row.

    If it does not, that is allowed, but it means section 7 missed something and
    the risk note has to say so.
    """
    return ['%s is classed build with no gap row and no explanation in risk' % v.get('story')
            for v in data.verdicts
            if v.get('actual') == 'build'
            and not (v.get('gap_rows') or [])
            and not (v.get('risk') or '').strip()]
```

And this one, which closes the loop on the allowed-value tuples in `schema.py`.
Without it, `confidence: "reasonned"` passes the gate silently, which is exactly the failure
a single source of truth exists to prevent:

```python
def check_allowed_values(data):
    """A typo in an enumerated field must not pass silently.

    None means undecided and is allowed. A wrong string is not.
    """
    problems = []
    for v in data.verdicts:
        for field, allowed in (('actual', schema.ACTUAL),
                               ('confidence', schema.CONFIDENCE),
                               ('points_flag', schema.POINTS_FLAG)):
            value = v.get(field)
            if value is not None and value not in allowed:
                problems.append('%s has %s %r, which is not one of %s'
                                % (v.get('story'), field, value, ', '.join(allowed)))
    for p in data.probes:
        value = p.get('verdict')
        if value is not None and value not in schema.PROBE_VERDICT:
            problems.append('probe %s has verdict %r, which is not one of %s'
                            % (p.get('id'), value, ', '.join(schema.PROBE_VERDICT)))
    return problems
```

Then extend `RULES`:

```python
RULES = [
    check_unknown_stories,
    check_traceable,
    check_risk_set_has_real_verdict,
    check_proved_has_evidence,
    check_build_traces_to_gap,
    check_allowed_values,
    check_no_duplicate_rows,
]
```

- [ ] **Step 4: Run the tests**

Run: `python -m unittest discovery.tests.test_check_verdicts -v`
Expected: PASS, 22 tests.

- [ ] **Step 5: Confirm the gate now reports the real backlog**

Run: `python -m discovery.check`
Expected: 73 failures, each reading `US-NN-NN is in the risk set and has no verdict`. This is correct. The gate is telling you the phase has not been done yet.

- [ ] **Step 6: Commit**

```bash
git add discovery/check.py discovery/tests/test_check_verdicts.py
git commit -m "Add verdict rules to the discovery gate

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 6: Gate rules for plugins

**Files:**
- Modify: `discovery/check.py` (add two rule functions, extend `RULES`)
- Create: `discovery/tests/test_check_plugins.py`

- [ ] **Step 1: Write the failing test**

Create `discovery/tests/test_check_plugins.py`:

```python
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

    def test_a_negative_cost_fails(self):
        problems = check.check_plugin_complete(data(plugins=[plugin('PL-01', cost_annual=-150)]))
        self.assertEqual(len(problems), 1)
        self.assertIn('negative', problems[0])

    def test_an_empty_decision_is_not_the_same_as_undecided(self):
        problems = check.check_plugin_complete(
            data(plugins=[plugin('PL-01', decision='', cost_annual=500)]))
        self.assertEqual(len(problems), 1)
        self.assertIn('Use null for undecided', problems[0])

    def test_a_non_string_decision_fails(self):
        problems = check.check_plugin_complete(
            data(plugins=[plugin('PL-01', decision=0, cost_annual=500)]))
        self.assertEqual(len(problems), 1)

    def test_a_non_numeric_cost_fails_rather_than_crashing(self):
        problems = check.check_plugin_complete(
            data(plugins=[plugin('PL-01', cost_annual='250')]))
        self.assertEqual(len(problems), 1)
        self.assertIn('non-numeric', problems[0])

    def test_a_cost_that_disagrees_with_the_chosen_candidate_fails(self):
        problems = check.check_plugin_complete(data(plugins=[plugin(
            'PL-01', decision='Example Plugin', cost_annual=0,
            candidates=[{'name': 'Example Plugin', 'cost_annual': 250}])]))
        self.assertEqual(len(problems), 1)
        self.assertIn('listed at', problems[0])

    def test_a_cost_that_agrees_with_the_chosen_candidate_passes(self):
        self.assertEqual(check.check_plugin_complete(data(plugins=[plugin(
            'PL-01', decision='Example Plugin', cost_annual=107,
            candidates=[{'name': 'Example Plugin', 'cost_annual': 107}])])), [])

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

    def test_an_acknowledgement_on_another_row_does_not_license_an_overage(self):
        rows = [plugin('PL-01', cost_annual=500),
                plugin('PL-02', cost_annual=1, acknowledged_over_quote=True)]
        problems = check.check_plugin_ceiling(data(plugins=rows))
        self.assertEqual(len(problems), 1)
        self.assertIn('501', problems[0])

    def test_a_blank_decision_does_not_hide_a_cost_from_the_total(self):
        rows = [plugin('PL-01', decision='', cost_annual=500)]
        self.assertEqual(check.check_plugin_complete(data(plugins=rows)) != [], True)

    def test_non_usd_costs_are_not_silently_summed(self):
        rows = [plugin('PL-01', cost_annual=107),
                plugin('PL-02', cost_annual=50, currency='EUR')]
        problems = check.check_plugin_ceiling(data(plugins=rows))
        self.assertTrue(any('EUR' in p for p in problems))


if __name__ == '__main__':
    unittest.main()
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `python -m unittest discovery.tests.test_check_plugins -v`
Expected: FAIL with `AttributeError: module 'discovery.check' has no attribute 'check_plugin_complete'`

- [ ] **Step 3: Write the implementation**

In `discovery/check.py`, insert after `check_build_traces_to_gap`:

```python
def _is_decided(pl):
    """True when the row names a real product to buy, so its cost counts.

    None means undecided, which is work in progress rather than a failure.
    "build instead" is a decision that costs no licence. Anything else that is
    not a real name is reported by check_plugin_complete, not silently skipped.
    """
    decision = pl.get('decision')
    return (isinstance(decision, str) and decision.strip()
            and decision != 'build instead')


def _money(value):
    """Format a cost without inventing or hiding decimals."""
    return '%d' % value if float(value).is_integer() else '%.2f' % value


def _usable_cost(pl):
    """The row's cost when it is a number that can be summed, else None."""
    cost = pl.get('cost_annual')
    if isinstance(cost, bool) or not isinstance(cost, (int, float)) or cost < 0:
        return None
    return cost


def check_plugin_complete(data):
    """A decided plugin needs a licence and a sane annual figure.

    An undecided need is work in progress, not a failure. "build instead" is a
    decision that costs no licence.
    """
    problems = []
    for pl in data.plugins:
        decision = pl.get('decision')
        if decision is None:
            continue
        if not isinstance(decision, str) or not decision.strip():
            # A blanked cell is not the same as an undecided one. Skipping it
            # would drop the cost and licence checks on a row that may carry a
            # real figure.
            problems.append('plugin %s has decision %r. Use null for undecided'
                            % (pl.get('id'), decision))
            continue
        if decision == 'build instead':
            continue
        cost = pl.get('cost_annual')
        if cost is None:
            problems.append('plugin %s is decided with no cost_annual' % pl.get('id'))
        elif isinstance(cost, bool) or not isinstance(cost, (int, float)):
            # Left unguarded this raises and takes the whole gate down with it,
            # which is worse than a wrong answer because nothing else gets run.
            problems.append('plugin %s has a non-numeric cost_annual, %r'
                            % (pl.get('id'), cost))
        elif cost < 0:
            # A negative figure on one row subtracts from the register total and
            # can hide a real overage on another, which would put a cost above
            # the quoted figure in front of the client with nobody told.
            problems.append('plugin %s has a negative cost_annual, %s'
                            % (pl.get('id'), cost))
        if not pl.get('licence'):
            problems.append('plugin %s is decided with no licence' % pl.get('id'))
        for candidate in pl.get('candidates') or []:
            listed = candidate.get('cost_annual')
            if candidate.get('name') != decision or listed is None:
                continue
            if listed != cost:
                problems.append('plugin %s is decided as %s at %r, but that candidate is '
                                'listed at %r' % (pl.get('id'), decision, cost, listed))
    return problems


def check_plugin_ceiling(data):
    """The register total must not quietly exceed what the client was quoted.

    Technical Design section 11 put about 107 USD a year in front of them. Going
    above that is a commercial conversation. It is allowed, but the rows that
    acknowledge it have to account for the overage: a cheap row carrying the
    flag cannot license an expensive row that does not.
    """
    problems = []
    total = 0
    acknowledged = 0
    for pl in data.plugins:
        if not _is_decided(pl):
            continue
        cost = _usable_cost(pl)
        if not cost:
            continue
        currency = pl.get('currency') or 'USD'
        if currency != 'USD':
            problems.append('plugin %s is priced in %s and cannot be summed against the '
                            'USD figure quoted to the client' % (pl.get('id'), currency))
            continue
        total += cost
        if pl.get('acknowledged_over_quote'):
            acknowledged += cost
    if (total > schema.QUOTED_ANNUAL_USD
            and total - acknowledged > schema.QUOTED_ANNUAL_USD):
        problems.append('plugin register totals %s USD a year against the %d USD quoted to '
                        'the client in Technical Design section 11, and the acknowledged rows '
                        'do not account for the difference'
                        % (_money(total), schema.QUOTED_ANNUAL_USD))
    return problems
```

Then extend `RULES` with `check_plugin_complete` and `check_plugin_ceiling`.

- [ ] **Step 4: Run the tests**

Run: `python -m unittest discovery.tests.test_check_plugins -v`
Expected: PASS, 17 tests.

- [ ] **Step 5: Commit**

```bash
git add discovery/check.py discovery/tests/test_check_plugins.py
git commit -m "Guard the plugin register against the quoted annual figure

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 7: Gate rules for journeys and the house rules

**Files:**
- Modify: `discovery/check.py` (add two rule functions, extend `RULES`)
- Create: `discovery/tests/test_check_journeys.py`

- [ ] **Step 1: Write the failing test**

Create `discovery/tests/test_check_journeys.py`:

```python
import os
import shutil
import tempfile
import unittest

from discovery import check

STORIES = [{'id': 'US-01-01', 'epic': 'E01', 'key': False, 'leverage': 'partial', 'trace': 'FIX-04'}]


def data(stories=None, verdicts=(), probes=(), plugins=(), journeys=()):
    """A Data bundle built by keyword, so nothing can be transposed by position."""
    return check.Data(STORIES if stories is None else stories,
                      list(verdicts), list(probes), list(plugins), list(journeys))


class TestJourneySteps(unittest.TestCase):
    def test_a_step_with_no_story_fails(self):
        journeys = [{'id': 'J-01', 'name': 'Guest buys', 'actor': 'guest',
                     'language': 'both', 'steps': [{'n': 1, 'stories': []}], 'gaps': []}]
        problems = check.check_journey_steps(data(journeys=journeys))
        self.assertEqual(len(problems), 1)
        self.assertIn('J-01', problems[0])

    def test_an_unknown_actor_fails(self):
        journeys = [{'id': 'J-01', 'name': 'x', 'actor': 'wizard', 'language': 'both',
                     'steps': [{'n': 1, 'stories': ['US-01-01']}], 'gaps': []}]
        problems = check.check_journey_steps(data(journeys=journeys))
        self.assertTrue(any('wizard' in p for p in problems))

    def test_an_unknown_language_fails(self):
        journeys = [{'id': 'J-01', 'name': 'x', 'actor': 'guest', 'language': 'fr',
                     'steps': [{'n': 1, 'stories': ['US-01-01']}], 'gaps': []}]
        problems = check.check_journey_steps(data(journeys=journeys))
        self.assertTrue(any('fr' in p for p in problems))

    def test_a_well_formed_journey_passes(self):
        journeys = [{'id': 'J-01', 'name': 'Guest buys', 'actor': 'guest', 'language': 'both',
                     'steps': [{'n': 1, 'stories': ['US-01-01']}], 'gaps': []}]
        self.assertEqual(check.check_journey_steps(data(journeys=journeys)), [])


class TestHouseRules(unittest.TestCase):
    def setUp(self):
        self.dir = tempfile.mkdtemp()
        self.addCleanup(shutil.rmtree, self.dir)

    def write(self, name, text):
        with open(os.path.join(self.dir, name), 'w', encoding='utf-8') as fh:
            fh.write(text)

    def test_an_em_dash_in_generated_output_fails(self):
        self.write('report.md', 'A sentence — with an em dash.\n')
        problems = check.check_house_rules_in(self.dir)
        self.assertEqual(len(problems), 1)
        self.assertIn('em dash', problems[0])

    def test_an_emoji_in_generated_output_fails(self):
        self.write('report.md', 'All good \U0001F600\n')
        problems = check.check_house_rules_in(self.dir)
        self.assertEqual(len(problems), 1)
        self.assertIn('emoji', problems[0])

    def test_clean_output_passes(self):
        self.write('report.md', 'A clean sentence, with a comma.\n')
        self.assertEqual(check.check_house_rules_in(self.dir), [])

    def test_only_markdown_is_examined(self):
        self.write('data.json', '{"note": "an em dash — in data is fine"}')
        self.assertEqual(check.check_house_rules_in(self.dir), [])

    def test_a_nested_markdown_file_is_examined(self):
        os.makedirs(os.path.join(self.dir, 'nested'))
        with open(os.path.join(self.dir, 'nested', 'deep.md'), 'w', encoding='utf-8') as fh:
            fh.write('Nested \u2014 dash.\n')
        problems = check.check_house_rules_in(self.dir)
        self.assertEqual(len(problems), 1)
        self.assertIn('nested/deep.md', problems[0])

    def test_an_uppercase_extension_is_examined(self):
        self.write('REPORT.MD', 'Shouting \u2014 loudly.\n')
        self.assertEqual(len(check.check_house_rules_in(self.dir)), 1)

    def test_an_unreadable_file_is_reported_rather_than_raising(self):
        with open(os.path.join(self.dir, 'broken.md'), 'wb') as fh:
            fh.write(b'\xff\xfe not utf 8 at all')
        problems = check.check_house_rules_in(self.dir)
        self.assertEqual(len(problems), 1)
        self.assertIn('could not be read', problems[0])

    def test_a_missing_directory_is_not_a_failure(self):
        self.assertEqual(check.check_house_rules_in(os.path.join(self.dir, 'nope')), [])


if __name__ == '__main__':
    unittest.main()
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `python -m unittest discovery.tests.test_check_journeys -v`
Expected: FAIL with `AttributeError: module 'discovery.check' has no attribute 'check_journey_steps'`

- [ ] **Step 3: Write the implementation**

Add `import os` and `import unicodedata` to the imports at the top of `discovery/check.py`, then insert after `check_plugin_ceiling`:

```python
GENERATED = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'generated')


def check_journey_steps(data):
    """A journey step that names no story is narrative, not validation."""
    problems = []
    for j in data.journeys:
        if j.get('actor') not in schema.ACTORS:
            problems.append('journey %s has actor %s, which is not one of %s'
                            % (j.get('id'), j.get('actor'), ', '.join(schema.ACTORS)))
        if j.get('language') not in schema.LANGUAGES:
            problems.append('journey %s has language %s, which is not one of %s'
                            % (j.get('id'), j.get('language'), ', '.join(schema.LANGUAGES)))
        for step in j.get('steps', []):
            if not step.get('stories'):
                problems.append('journey %s step %s names no story'
                                % (j.get('id'), step.get('n')))
    return problems


def _is_emoji(ch):
    return ord(ch) > 0x2500 and unicodedata.category(ch) == 'So'


def check_house_rules_in(directory):
    """No emoji and no em dash in generated markdown.

    These documents feed client-facing work even though they are not sent, and
    the house rule is easier to keep than to retrofit.

    Walks the tree rather than one level, matches the extension case
    insensitively, and reports an unreadable file rather than raising: an
    exception here would take the whole gate down and nothing else would run.
    """
    problems = []
    if not os.path.isdir(directory):
        return problems
    for root, _dirs, names in os.walk(directory):
        for name in sorted(names):
            if not name.lower().endswith('.md'):
                continue
            path = os.path.join(root, name)
            label = os.path.relpath(path, directory).replace(os.sep, '/')
            try:
                with open(path, encoding='utf-8') as fh:
                    text = fh.read()
            except (OSError, UnicodeDecodeError) as exc:
                problems.append('%s could not be read: %s' % (label, exc))
                continue
            if '\u2014' in text:
                problems.append('%s contains an em dash' % label)
            found = sorted({ch for ch in text if _is_emoji(ch)})
            if found:
                problems.append('%s contains emoji: %s' % (label, ' '.join(found)))
    return problems

def check_house_rules(data):
    """Rule-signature wrapper so the gate can run it alongside the others.

    It reads the generated directory rather than the datasets, so it ignores
    data entirely. That is the one rule here that is about output, not input.
    """
    return check_house_rules_in(GENERATED)
```

Then extend `RULES` with `check_journey_steps` and `check_house_rules`.

- [ ] **Step 4: Run the tests**

Run: `python -m unittest discovery.tests.test_check_journeys -v`
Expected: PASS, 12 tests.

- [ ] **Step 5: Run the whole suite**

Run: `python -m unittest discover -s discovery/tests -t . -v`
Expected: PASS, 77 tests.

- [ ] **Step 6: Commit**

```bash
git add discovery/check.py discovery/tests/test_check_journeys.py
git commit -m "Add journey and house rules to the discovery gate

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 8: Seed the probe list

**Files:**
- Create: `discovery/data/probes.json`
- Create: `discovery/tests/test_probes_seed.py`

The 15 probes from the design, section 5.1, with `expected` recorded before anything runs. This is the point of the exercise: a probe that refutes the Technical Design is only legible if the prior claim was written down first.

- [ ] **Step 1: Write the failing test**

Create `discovery/tests/test_probes_seed.py`:

```python
import unittest

from discovery import schema


class TestProbeSeed(unittest.TestCase):
    def setUp(self):
        self.probes = schema.load('probes')
        self.stories = {s['id'] for s in schema.load_stories()}

    def test_fifteen_probes_are_seeded(self):
        self.assertEqual(len(self.probes), 15)

    def test_ids_are_unique_and_sequential(self):
        ids = [p['id'] for p in self.probes]
        self.assertEqual(ids, ['P-%03d' % n for n in range(1, 16)])

    def test_every_probe_has_every_field(self):
        for p in self.probes:
            self.assertEqual(schema.missing_fields(p, schema.PROBE_FIELDS), [], p['id'])

    def test_every_probe_records_what_the_design_assumes(self):
        for p in self.probes:
            self.assertTrue(p['expected'].strip(), p['id'] + ' must record expected before running')

    def test_nothing_has_been_observed_yet(self):
        for p in self.probes:
            self.assertIsNone(p['observed'], p['id'])
            self.assertIsNone(p['verdict'], p['id'])

    def test_every_named_story_exists(self):
        for p in self.probes:
            for sid in p['stories']:
                self.assertIn(sid, self.stories, '%s names %s' % (p['id'], sid))

    def test_methods_are_known(self):
        for p in self.probes:
            self.assertIn(p['method'], ('wp-cli', 'php', 'playwright'), p['id'])


if __name__ == '__main__':
    unittest.main()
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `python -m unittest discovery.tests.test_probes_seed -v`
Expected: FAIL, `AssertionError: 0 != 15`

- [ ] **Step 3: Find the story ids each probe bears on**

The seed must name real story ids. Run this to get the candidates for each probe area:

```bash
cd C:/wamp64/www/mizzey/platform/scripts
python -c "
import json
S=json.load(open('stories.json'))
for pat in ['cart','price','coupon|promo','idempot|duplicate','translat|arabic|bilingual','search','import|migrat','stock|inventor','right to left|rtl','shipping','status|carrier','role','refund','wishlist']:
    import re
    hits=[s['id'] for s in S if re.search(pat, (s['title']+' '+s['narrative']).lower())]
    print(pat, '->', hits[:6])
"
```

Use the output to fill the `stories` array of each probe. Where a probe bears on no specific story (the `corex-kit-woo` seam probe), leave `stories` empty and record the reason in `notes`.

- [ ] **Step 4: Write the seed**

Create `discovery/data/probes.json`. Each row follows this shape exactly. The `expected` text is what the Technical Design asserts today, quoted or closely paraphrased from section 7.

```json
[
  {
    "id": "P-001",
    "question": "On login, does WooCommerce replace the guest session cart rather than merging it, and does woocommerce_cart_loaded_from_session fire where the Technical Design assumes?",
    "stories": [],
    "gap_rows": [1],
    "script": "P-001-cart-merge-on-login.php",
    "method": "wp-cli",
    "expected": "Woo replaces the session cart with the persistent cart, or keeps the session cart and discards the saved one, depending on path. It does not merge. TDD section 7 row 1 calls this the most delicate logic in the storefront.",
    "observed": null,
    "verdict": null,
    "env": null,
    "run_at": null,
    "notes": "Fill stories from the CART epic, E05."
  }
]
```

Complete the file with all fifteen, in the priority order from the design, section 5.1:

| id | Question to encode | gap_rows | method |
|---|---|---|---|
| P-001 | Cart merge on login | 1 | wp-cli |
| P-002 | Is the BR-005 price snapshot genuinely native, and at what point is the price frozen | none | wp-cli |
| P-003 | Do coupons stack freely with no priority, per BR-004 | 3 | wp-cli |
| P-004 | Is order placement idempotent, and is a repeated provider callback a no-operation | 12 | php |
| P-005 | What does corex-kit-woo actually gate, and is anything active | none | wp-cli |
| P-006 | Does the candidate translation plugin translate Woo product data, including variations and attributes | 17 | playwright |
| P-007 | What does the WordPress LIKE search return for Arabic and Franco-Arabic terms | 9 | wp-cli |
| P-008 | Does the Woo CSV importer detect Arabic encoding, and what happens on a re-run | 6 | wp-cli |
| P-009 | Does stock decrement hold under concurrent checkout | none | php |
| P-010 | Does the CoreX M3 header, nav and footer mirror correctly in right to left at every breakpoint | none | playwright |
| P-011 | Can free shipping be conditioned on item count rather than order amount | 2 | wp-cli |
| P-012 | Can order statuses be registered, and does an unrecognised carrier status change anything | 10 | php |
| P-013 | What capabilities do the Woo default roles carry, and what is missing for the six required | 15 | wp-cli |
| P-014 | Is a partial refund capped at the paid amount against the gateway | none | php |
| P-015 | Does a guest wishlist transfer on sign-in, and does it share the cart-merge failure mode | 13 | php |

- [ ] **Step 5: Run the tests**

Run: `python -m unittest discovery.tests.test_probes_seed -v`
Expected: PASS, 7 tests.

- [ ] **Step 6: Run the gate**

Run: `python -m discovery.check`
Expected: still the 73 missing-verdict failures, and no new ones. The probe seed must not introduce a failure.

- [ ] **Step 7: Commit**

```bash
git add discovery/data/probes.json discovery/tests/test_probes_seed.py
git commit -m "Seed the fifteen probes with what the Technical Design assumes

Recording expected before anything runs is the point: a probe that refutes
section 7 is only legible if the prior claim was written down first.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 9: Seed the plugin needs

**Files:**
- Create: `discovery/data/plugins.json`
- Create: `discovery/tests/test_plugins_seed.py`

Six needs, drawn from Technical Design section 11 and rows 16 to 19. Each seeded undecided, so the gate does not fail on them until a decision is made.

- [ ] **Step 1: Write the failing test**

Create `discovery/tests/test_plugins_seed.py`:

```python
import unittest

from discovery import check, schema


class TestPluginSeed(unittest.TestCase):
    def setUp(self):
        self.plugins = schema.load('plugins')

    def test_six_needs_are_seeded(self):
        self.assertEqual(len(self.plugins), 6)

    def test_ids_are_unique_and_sequential(self):
        self.assertEqual([p['id'] for p in self.plugins], ['PL-%02d' % n for n in range(1, 7)])

    def test_every_row_has_every_field(self):
        for p in self.plugins:
            self.assertEqual(schema.missing_fields(p, schema.PLUGIN_FIELDS), [], p['id'])

    def test_nothing_is_decided_yet(self):
        for p in self.plugins:
            self.assertIsNone(p['decision'], p['id'])

    def test_the_translation_need_names_the_quoted_figure_as_a_candidate(self):
        # Keyed on the id, not the prose: renaming the need must not break this.
        translation = [p for p in self.plugins if p['id'] == 'PL-01'][0]
        self.assertTrue(translation['candidates'],
                        'the 107 USD already quoted must appear as a candidate to beat')
        self.assertEqual([c['cost_annual'] for c in translation['candidates']],
                         [schema.QUOTED_ANNUAL_USD])

    def test_the_undecided_seed_does_not_fail_the_gate(self):
        data = schema.Data(schema.load_stories(), [], [], self.plugins, [])
        self.assertEqual(check.check_plugin_complete(data), [])
        self.assertEqual(check.check_plugin_ceiling(data), [])


if __name__ == '__main__':
    unittest.main()
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `python -m unittest discovery.tests.test_plugins_seed -v`
Expected: FAIL, `AssertionError: 0 != 6`

- [ ] **Step 3: Write the seed**

Create `discovery/data/plugins.json` with six rows in this shape:

```json
[
  {
    "id": "PL-01",
    "need": "Translation for multilingual products, categories and content, per FIX-04 and SSC-21",
    "stories": [],
    "candidates": [
      {
        "name": "The plugin behind the Technical Design section 11 figure",
        "licence": "commercial",
        "cost_annual": 107,
        "currency": "USD",
        "last_update": null,
        "installs": null,
        "maintenance_risk": null,
        "notes": "This is the figure already in the client's hands. Any candidate has to beat it or justify exceeding it."
      }
    ],
    "decision": null,
    "evidence": [],
    "cost_annual": null,
    "currency": "USD",
    "licence": null,
    "owner": "client",
    "acknowledged_over_quote": false
  }
]
```

The remaining five, all seeded with `candidates: []` and `decision: null`:

| id | need | TDD row | owner |
|---|---|---|---|
| PL-02 | Google sign in, per AUTH-10 and AUTH-13 | 16 | client |
| PL-03 | Redirect manager and missing page log, per MKT-13 and MKT-14 | 19 | client |
| PL-04 | Two factor authentication for admin, per ADM-131 | none | client |
| PL-05 | Product feeds for Meta and Google Merchant, per MKT-05 and MKT-06 | 18 | client |
| PL-06 | Campaign parameter preservation to the order record, per MKT-09 | 18 | client |

Fill `stories` for each from `stories.json` by searching the `trace` field for the requirement ids named in `need`:

```bash
cd C:/wamp64/www/mizzey/platform/scripts
python -c "
import json
S=json.load(open('stories.json'))
for req in ['FIX-04','SSC-21','AUTH-10','AUTH-13','MKT-13','MKT-14','ADM-131','MKT-05','MKT-06','MKT-09']:
    print(req, '->', [s['id'] for s in S if req in s['trace']])
"
```

- [ ] **Step 4: Run the tests**

Run: `python -m unittest discovery.tests.test_plugins_seed -v`
Expected: PASS, 6 tests.

- [ ] **Step 5: Commit**

```bash
git add discovery/data/plugins.json discovery/tests/test_plugins_seed.py
git commit -m "Seed the six plugin needs, all undecided

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 10: The probe runner

**Files:**
- Create: `discovery/run_probes.py`
- Create: `discovery/tests/test_run_probes.py`

- [ ] **Step 1: Write the failing test**

Create `discovery/tests/test_run_probes.py`:

```python
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
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `python -m unittest discovery.tests.test_run_probes -v`
Expected: FAIL with `ModuleNotFoundError: No module named 'discovery.run_probes'`

- [ ] **Step 3: Write the implementation**

Create `discovery/run_probes.py`:

```python
"""Execute probe scripts and write their results back into probes.json.

    python -m discovery.run_probes            every probe
    python -m discovery.run_probes P-001      one, by id

A probe prints a single JSON object on stdout:

    {"observed": "what actually happened", "verdict": "confirmed"}

Recording the environment on every run makes the suite a regression check: when
WooCommerce updates, re-run it and find out what changed.
"""

import datetime
import json
import os
import re
import subprocess
import sys

from discovery import schema

PROBES_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'probes')

# The Mizzey site created in Step 1. Override with MIZZEY_WP when it moves.
WP_PATH = os.environ.get('MIZZEY_WP', 'C:/wamp64/www/corex/wp')


def parse_result(stdout):
    """Pull the JSON object out of a probe's stdout and validate it."""
    match = re.search(r'\{.*\}', stdout, re.S)
    if not match:
        raise ValueError('no JSON object found in probe output')
    try:
        data = json.loads(match.group(0))
    except json.JSONDecodeError as exc:
        raise ValueError('probe output is not valid JSON: %s' % exc)
    if 'observed' not in data:
        raise ValueError('probe output has no "observed"')
    if data.get('verdict') not in schema.PROBE_VERDICT:
        raise ValueError('probe verdict %r is not one of %s'
                         % (data.get('verdict'), ', '.join(schema.PROBE_VERDICT)))
    return data


def apply_result(row, result, env):
    """Write a parsed result onto a probe row, in place."""
    row['observed'] = result['observed']
    row['verdict'] = result['verdict']
    row['env'] = env
    row['run_at'] = datetime.date.today().isoformat()


def select(rows, ids):
    """The rows to run. No ids means all of them."""
    if not ids:
        return rows
    index = {r['id']: r for r in rows}
    for pid in ids:
        if pid not in index:
            raise KeyError(pid)
    return [index[pid] for pid in ids]


def environment():
    """WordPress, WooCommerce and CoreX versions, recorded with every run."""
    def wp(*args):
        try:
            out = subprocess.run(['wp'] + list(args) + ['--path=' + WP_PATH],
                                 capture_output=True, text=True, timeout=60)
            return out.stdout.strip()
        except Exception as exc:
            return 'unavailable: %s' % exc

    return {
        'wp': wp('core', 'version'),
        'woocommerce': wp('plugin', 'get', 'woocommerce', '--field=version'),
        'corex': wp('plugin', 'get', 'corex-core', '--field=version'),
        'wp_path': WP_PATH,
    }


def run_one(row, env):
    """Execute one probe. Returns None on success, or a message on failure."""
    script = os.path.join(PROBES_DIR, row['script'])
    if not os.path.exists(script):
        return '%s: script %s does not exist' % (row['id'], row['script'])
    if row['method'] == 'playwright':
        cmd = ['node', script]
    elif row['method'] == 'php':
        cmd = ['wp', 'eval-file', script, '--path=' + WP_PATH]
    else:
        cmd = ['wp', 'eval-file', script, '--path=' + WP_PATH]
    try:
        out = subprocess.run(cmd, capture_output=True, text=True, timeout=300)
    except Exception as exc:
        return '%s: %s' % (row['id'], exc)
    try:
        result = parse_result(out.stdout)
    except ValueError as exc:
        return '%s: %s\nstderr: %s' % (row['id'], exc, out.stderr.strip()[:500])
    apply_result(row, result, env)
    return None


def main(argv):
    rows = schema.load('probes')
    try:
        wanted = select(rows, argv)
    except KeyError as exc:
        print('unknown probe %s' % exc)
        return 1
    env = environment()
    failures = []
    for row in wanted:
        problem = run_one(row, env)
        if problem:
            failures.append(problem)
            print('  FAIL  ' + problem)
        else:
            print('  %-9s %s' % (row['verdict'], row['id']))
    schema.save('probes', rows)
    if failures:
        print('\n%d probe(s) did not run' % len(failures))
        return 1
    print('\n%d probe(s) recorded' % len(wanted))
    return 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))
```

- [ ] **Step 4: Run the tests**

Run: `python -m unittest discovery.tests.test_run_probes -v`
Expected: PASS, 10 tests.

- [ ] **Step 5: Confirm it reports missing scripts rather than crashing**

Run: `python -m discovery.run_probes P-001`
Expected: `FAIL  P-001: script P-001-cart-merge-on-login.php does not exist`, exit 1. This is correct. The probe scripts are written in Step 2.

- [ ] **Step 6: Commit**

```bash
git add discovery/run_probes.py discovery/tests/test_run_probes.py
git commit -m "Add the probe runner

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 11: Epic dossier generator

**Files:**
- Create: `discovery/gen_epic_dossiers.py`
- Create: `discovery/tests/test_gen_dossiers.py`

- [ ] **Step 1: Write the failing test**

Create `discovery/tests/test_gen_dossiers.py`:

```python
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
        self.assertNotIn('\u2014', self.text)

    def test_headings_stay_at_h3_or_above(self):
        for line in self.text.split('\n'):
            if line.startswith('#'):
                self.assertLessEqual(len(line) - len(line.lstrip('#')), 3, line)


if __name__ == '__main__':
    unittest.main()
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `python -m unittest discovery.tests.test_gen_dossiers -v`
Expected: FAIL with `ModuleNotFoundError: No module named 'discovery.gen_epic_dossiers'`

- [ ] **Step 3: Write the implementation**

Create `discovery/gen_epic_dossiers.py`:

```python
"""Generate one dossier per epic from the datasets.

    python -m discovery.gen_epic_dossiers

Output goes to generated/. Never hand-edit it: the next run overwrites.
"""

import io
import os
import sys

from discovery import riskset, schema

OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'generated')


def dossier(epic, stories, verdicts, probes, plugins):
    """The markdown for one epic."""
    rows = [s for s in stories if s['epic'] == epic]
    if not rows:
        return ''
    by_story = {v['story']: v for v in verdicts}
    probe_by_id = {p['id']: p for p in probes}
    risky = riskset.risk_set(stories)

    o = io.StringIO()
    w = o.write
    w('# %s: %s\n\n' % (epic, rows[0]['epicname']))
    w('Generated. Do not edit this file. Correct the dataset and regenerate.\n\n')
    w('%d stories, %d points. Claimed leverage: %s.\n\n'
      % (len(rows), sum(s['points'] for s in rows), rows[0]['leverage']))

    validated = sum(1 for s in rows if s['id'] in by_story)
    w('Validated: %d of %d.\n\n' % (validated, len(rows)))

    w('## Stories\n\n')
    w('| Story | Title | Trace | Set | Claimed | Actual | Confidence | Points |\n')
    w('|---|---|---|---|---|---|---|---|\n')
    for s in rows:
        v = by_story.get(s['id'])
        in_set = 'risk' if s['id'] in risky else 'light'
        if v:
            actual, confidence = v.get('actual') or '', v.get('confidence') or ''
            flag = v.get('points_flag') or 'ok'
            points = '%d (%s)' % (s['points'], flag) if flag != 'ok' else str(s['points'])
        else:
            actual, confidence, points = 'not yet validated', '', str(s['points'])
        w('| %s | %s | %s | %s | %s | %s | %s | %s |\n'
          % (s['id'], s['title'], s['trace'], in_set, s['leverage'], actual, confidence, points))
    w('\n')

    risks = [(s, by_story[s['id']]) for s in rows
             if s['id'] in by_story and (by_story[s['id']].get('risk') or '').strip()]
    if risks:
        w('## Risks\n\n')
        for s, v in risks:
            w('**%s, %s.** %s\n\n' % (s['id'], s['title'], v['risk']))

    evidence = sorted({pid for s in rows if s['id'] in by_story
                       for pid in (by_story[s['id']].get('evidence') or [])})
    if evidence:
        w('## Evidence\n\n')
        w('| Probe | Question | Verdict |\n|---|---|---|\n')
        for pid in evidence:
            p = probe_by_id.get(pid, {})
            w('| %s | %s | %s |\n' % (pid, p.get('question', 'unknown probe'),
                                      p.get('verdict') or 'not yet run'))
        w('\n')

    questions = [(s['id'], q) for s in rows if s['id'] in by_story
                 for q in (by_story[s['id']].get('open') or [])]
    if questions:
        w('## Open questions\n\n')
        for sid, q in questions:
            w('- **%s.** %s\n' % (sid, q))
        w('\n')

    return o.getvalue()


def main():
    stories = schema.load_stories()
    verdicts = schema.load('verdicts')
    probes = schema.load('probes')
    plugins = schema.load('plugins')
    os.makedirs(OUT, exist_ok=True)
    epics = sorted({s['epic'] for s in stories})
    for epic in epics:
        text = dossier(epic, stories, verdicts, probes, plugins)
        path = os.path.join(OUT, 'epic-%s.md' % epic)
        with open(path, 'w', encoding='utf-8') as fh:
            fh.write(text)
    print('%d dossiers written to generated/' % len(epics))
    return 0


if __name__ == '__main__':
    sys.exit(main())
```

- [ ] **Step 4: Run the tests**

Run: `python -m unittest discovery.tests.test_gen_dossiers -v`
Expected: PASS, 8 tests.

- [ ] **Step 5: Generate and inspect**

Run: `python -m discovery.gen_epic_dossiers`
Expected: `27 dossiers written to generated/`

Run: `cat discovery/generated/epic-E13.md`
Expected: four stories, all reading `not yet validated`, since no verdicts exist yet.

- [ ] **Step 6: Confirm the house rules still pass on real output**

Run: `python -m discovery.check 2>&1 | grep -c "em dash\|emoji"`
Expected: `0`

- [ ] **Step 7: Commit**

```bash
git add discovery/gen_epic_dossiers.py discovery/tests/test_gen_dossiers.py discovery/generated/
git commit -m "Generate an epic dossier per epic

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 12: The remaining three generators

**Files:**
- Create: `discovery/gen_validation_report.py`
- Create: `discovery/gen_plugin_register.py`
- Create: `discovery/gen_journey_docs.py`
- Create: `discovery/tests/test_gen_reports.py`

- [ ] **Step 1: Write the failing test**

Create `discovery/tests/test_gen_reports.py`:

```python
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
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `python -m unittest discovery.tests.test_gen_reports -v`
Expected: FAIL with `ModuleNotFoundError: No module named 'discovery.gen_validation_report'`

- [ ] **Step 3: Write `gen_validation_report.py`**

```python
"""The consolidated validation report.

    python -m discovery.gen_validation_report

Refuted probes lead, because a refuted probe means the Technical Design says
something untrue and something downstream has to change.
"""

import io
import os
import sys

from discovery import riskset, schema

OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'generated')


def report(data):
    risky = riskset.risk_set(data.stories)
    by_story = {v['story']: v for v in data.verdicts}
    o = io.StringIO()
    w = o.write

    w('# Validation Report\n\n')
    w('Generated. Do not edit this file. Correct the dataset and regenerate.\n\n')
    validated = sum(1 for sid in risky if sid in by_story)
    w('Risk-weighted set validated: %d of %d.\n' % (validated, len(risky)))
    w('Probes run: %d of %d.\n\n'
      % (sum(1 for p in data.probes if p.get('verdict')), len(data.probes)))

    refuted = [p for p in data.probes if p.get('verdict') == 'refuted']
    w('## Refuted\n\n')
    if refuted:
        w('The Technical Design asserts something these probes did not find. '
          'Each one needs a disposition under the design, section 8.\n\n')
        for p in refuted:
            w('### %s\n\n' % p['id'])
            w('**Question.** %s\n\n' % p['question'])
            w('**The design assumed.** %s\n\n' % p['expected'])
            w('**Observed.** %s\n\n' % p['observed'])
            if p.get('gap_rows'):
                w('**Technical Design rows affected.** %s\n\n'
                  % ', '.join(str(r) for r in p['gap_rows']))
    else:
        w('Nothing refuted.\n\n')

    partial = [p for p in data.probes if p.get('verdict') == 'partial']
    w('## Partial\n\n')
    if partial:
        for p in partial:
            w('- **%s.** %s Observed: %s\n' % (p['id'], p['question'], p['observed']))
        w('\n')
    else:
        w('Nothing partial.\n\n')

    confirmed = [p for p in data.probes if p.get('verdict') == 'confirmed']
    w('## Confirmed\n\n')
    if confirmed:
        for p in confirmed:
            w('- **%s.** %s\n' % (p['id'], p['question']))
        w('\n')
    else:
        w('Nothing confirmed yet.\n\n')

    drift = [(sid, by_story[sid]) for sid in sorted(risky)
             if sid in by_story and by_story[sid].get('points_flag') != 'ok']
    w('## Estimate drift\n\n')
    if drift:
        w('Stage 4 already runs at 68 points a week and Stage 5 at 92, against a 55 '
          'average. These flags compound that.\n\n')
        w('| Story | Flag | Risk |\n|---|---|---|\n')
        for sid, v in drift:
            w('| %s | %s | %s |\n' % (sid, v['points_flag'], v.get('risk', '')))
        w('\n')
    else:
        w('No story is flagged under or over.\n\n')

    questions = [(v['story'], q) for v in data.verdicts for q in (v.get('open') or [])]
    w('## Open questions\n\n')
    if questions:
        for sid, q in questions:
            w('- **%s.** %s\n' % (sid, q))
        w('\n')
    else:
        w('None outstanding.\n\n')

    return o.getvalue()


def main():
    text = report(schema.load_data())
    os.makedirs(OUT, exist_ok=True)
    with open(os.path.join(OUT, 'validation-report.md'), 'w', encoding='utf-8') as fh:
        fh.write(text)
    print('validation-report.md written')
    return 0


if __name__ == '__main__':
    sys.exit(main())
```

- [ ] **Step 4: Write `gen_plugin_register.py`**

```python
"""The plugin register, with the total measured against what the client was quoted.

    python -m discovery.gen_plugin_register
"""

import io
import os
import sys

from discovery import schema

OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'generated')


def register(plugins):
    o = io.StringIO()
    w = o.write
    w('# Plugin Register\n\n')
    w('Generated. Do not edit this file. Correct the dataset and regenerate.\n\n')

    decided = [p for p in plugins if p.get('decision')]
    outstanding = [p for p in plugins if not p.get('decision')]

    w('## Decided\n\n')
    if decided:
        w('| Id | Need | Decision | Licence | Annual cost | Licence held by |\n')
        w('|---|---|---|---|---|---|\n')
        for p in decided:
            cost = ('%s %s' % (p.get('cost_annual'), p.get('currency', 'USD'))
                    if p.get('cost_annual') else 'nil')
            w('| %s | %s | %s | %s | %s | %s |\n'
              % (p['id'], p['need'], p['decision'], p.get('licence') or 'none',
                 cost, p.get('owner') or ''))
        w('\n')
    else:
        w('Nothing decided yet.\n\n')

    total = sum(p.get('cost_annual') or 0 for p in decided
                if (p.get('currency') or 'USD') == 'USD')
    w('**Total, USD a year: %d.** Quoted to the client in Technical Design section 11: '
      'about %d.\n\n' % (total, schema.QUOTED_ANNUAL_USD))
    if total > schema.QUOTED_ANNUAL_USD:
        w('> This exceeds the figure already in the client\'s hands. It is a commercial '
          'conversation, not a technical one.\n\n')

    w('## Outstanding\n\n')
    if outstanding:
        for p in outstanding:
            w('- **%s.** %s, not yet decided.\n' % (p['id'], p['need']))
        w('\n')
    else:
        w('Every need is decided.\n\n')

    return o.getvalue()


def main():
    text = register(schema.load('plugins'))
    os.makedirs(OUT, exist_ok=True)
    with open(os.path.join(OUT, 'plugin-register.md'), 'w', encoding='utf-8') as fh:
        fh.write(text)
    print('plugin-register.md written')
    return 0


if __name__ == '__main__':
    sys.exit(main())
```

- [ ] **Step 5: Write `gen_journey_docs.py`**

```python
"""One document per journey.

    python -m discovery.gen_journey_docs
"""

import io
import os
import sys

from discovery import schema

OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'generated')


def journey_doc(journey, stories):
    titles = {s['id']: s['title'] for s in stories}
    o = io.StringIO()
    w = o.write
    w('# %s: %s\n\n' % (journey['id'], journey['name']))
    w('Generated. Do not edit this file. Correct the dataset and regenerate.\n\n')
    w('Actor: %s. Language: %s.\n\n' % (journey['actor'], journey['language']))

    w('## Steps\n\n')
    w('| Step | What happens | Stories | Screen |\n|---|---|---|---|\n')
    for step in journey.get('steps', []):
        named = ', '.join('%s (%s)' % (sid, titles.get(sid, 'unknown'))
                          for sid in step.get('stories', []))
        w('| %s | %s | %s | %s |\n'
          % (step.get('n'), step.get('description', ''), named, step.get('screen', '')))
    w('\n')

    gaps = journey.get('gaps') or []
    w('## Gaps this journey exposes\n\n')
    if gaps:
        w('Failures visible only across stories, which no single story shows.\n\n')
        for g in gaps:
            w('- %s\n' % g)
        w('\n')
    else:
        w('None recorded.\n\n')

    return o.getvalue()


def main():
    stories = schema.load_stories()
    journeys = schema.load('journeys')
    os.makedirs(OUT, exist_ok=True)
    for j in journeys:
        path = os.path.join(OUT, 'journey-%s.md' % j['id'])
        with open(path, 'w', encoding='utf-8') as fh:
            fh.write(journey_doc(j, stories))
    print('%d journey document(s) written' % len(journeys))
    return 0


if __name__ == '__main__':
    sys.exit(main())
```

- [ ] **Step 6: Run the tests**

Run: `python -m unittest discovery.tests.test_gen_reports -v`
Expected: PASS, 7 tests.

- [ ] **Step 7: Generate everything and run the gate**

```bash
python -m discovery.gen_epic_dossiers
python -m discovery.gen_validation_report
python -m discovery.gen_plugin_register
python -m discovery.gen_journey_docs
python -m discovery.check
```

Expected: the generators report their file counts, and `check` reports the 73 missing verdicts and nothing about em dashes or emoji.

- [ ] **Step 8: Commit**

```bash
git add discovery/gen_validation_report.py discovery/gen_plugin_register.py discovery/gen_journey_docs.py discovery/tests/test_gen_reports.py discovery/generated/
git commit -m "Add the validation report, plugin register and journey generators

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 13: The five agent task templates

**Files:**
- Create: `discovery/tasks/PROBE.md`, `VERDICT.md`, `PLUGIN.md`, `JOURNEY.md`, `WIREFRAME.md`
- Create: `discovery/tests/test_task_templates.py`

- [ ] **Step 1: Write the failing test**

Create `discovery/tests/test_task_templates.py`:

```python
import os
import unittest

HERE = os.path.dirname(os.path.abspath(__file__))
TASKS = os.path.join(os.path.dirname(HERE), 'tasks')

NAMES = ('PROBE', 'VERDICT', 'PLUGIN', 'JOURNEY', 'WIREFRAME')


class TestTaskTemplates(unittest.TestCase):
    def read(self, name):
        with open(os.path.join(TASKS, name + '.md'), encoding='utf-8') as fh:
            return fh.read()

    def test_all_five_exist(self):
        for name in NAMES:
            self.assertTrue(os.path.exists(os.path.join(TASKS, name + '.md')), name)

    def test_each_states_the_two_lanes_rule(self):
        for name in NAMES:
            self.assertIn('Annex A', self.read(name), name)

    def test_each_tells_a_blocked_agent_to_write_open_and_continue(self):
        for name in NAMES:
            text = self.read(name)
            self.assertIn('do not stop', text.lower(), name)

    def test_each_names_the_files_to_read_and_the_file_to_write(self):
        for name in NAMES:
            text = self.read(name)
            self.assertIn('## Read', text, name)
            self.assertIn('## Write', text, name)

    def test_each_forbids_exploring_the_archive(self):
        for name in NAMES:
            self.assertIn('_archive', self.read(name), name)

    def test_no_em_dash_and_no_emoji(self):
        for name in NAMES:
            self.assertNotIn('\u2014', self.read(name), name)


if __name__ == '__main__':
    unittest.main()
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `python -m unittest discovery.tests.test_task_templates -v`
Expected: FAIL, the files do not exist.

- [ ] **Step 3: Write `discovery/tasks/PROBE.md`**

```markdown
# Task type: PROBE

You are running one probe. You will be given a probe id, such as `P-001`.

## Read

- `CLAUDE.md` at the project root
- `docs/discovery/2026-09-08-discovery-phase-design.md`, sections 5 and 8
- The single row in `discovery/data/probes.json` whose `id` matches yours
- `final docs/Client/Branded/Mizzey-Operations-Platform-Technical-Design.md`, section 7,
  the rows named in your `gap_rows`

Read nothing else. In particular, never read anything under `_archive`: it holds superseded
pricing drafts and will give you wrong figures.

## Write

- One probe script at `discovery/probes/<your script filename>`, taken from the `script`
  field of your row
- Nothing else. You must not touch `verdicts.json`, `plugins.json` or `journeys.json`

## What the script must do

Answer the `question` field, and nothing wider. Print exactly one JSON object on stdout:

    {"observed": "what actually happened, in one or two sentences", "verdict": "confirmed"}

`verdict` is `confirmed`, `refuted`, or `partial`, measured against the `expected` field,
which records what the Technical Design assumes today.

Write the script so it can be run again later without leaving state behind. When it needs
fixtures, it creates them and removes them.

The runner executes it: `python -m discovery.run_probes <your id>`.

## Rules

- **Do not edit the `expected` field.** It is the prior claim, recorded before the test, and
  a refutation is only legible against it.
- **Refuting the Technical Design is a good outcome**, not a failure. Report it plainly.
- Anything with no id in the Feature Register, Annex A, is out of scope. It is not a defect
  and it does not become work. Note it and move on.
- If you cannot answer, add a sentence to your row's `notes` saying exactly what blocked you,
  and stop cleanly. **Do not stop to ask a question.** Questions are collected and reviewed
  in one batch at the gate.

## Done

The script exists, runs, and prints a valid result object. `python -m discovery.check` reports
nothing new.
```

- [ ] **Step 4: Write `discovery/tasks/VERDICT.md`**

```markdown
# Task type: VERDICT

You are writing the verdict rows for one epic. You will be given an epic id, such as `E13`.

## Read

- `CLAUDE.md` at the project root
- `docs/discovery/2026-09-08-discovery-phase-design.md`, sections 3.2 and 8
- Every story in `scripts/stories.json` whose `epic` matches yours
- Every probe in `discovery/data/probes.json` whose `stories` intersect yours, or whose
  `gap_rows` are relevant
- `final docs/Client/Branded/Mizzey-Operations-Platform-Technical-Design.md`, section 7
- `final docs/Client/Branded/Mizzey-Operations-Platform-Functional-Specification.md`, the
  sections covering your epic

Read nothing else. Never read anything under `_archive`.

## Write

- One row per story of your epic in `discovery/data/verdicts.json`
- Nothing else. You must not run probes, and you must never edit `scripts/stories.json`,
  which is generated from the specification and will be overwritten

## The row

Use `discovery.schema.blank_verdict(story_id)` as the starting shape, then fill:

- `claimed`: the `leverage` from the story. Copy it, do not judge it
- `actual`: `native`, `extend`, `build`, or `plugin`
- `confidence`: `proved` only if a probe ran and you name it in `evidence`. Otherwise
  `reasoned`. Never leave a risk-set story at `assumed`
- `evidence`: probe ids. Required when `confidence` is `proved`
- `gap_rows`: Technical Design section 7 rows. If you class something `build` with no gap
  row, section 7 missed it, and `risk` must say so
- `points_flag`: `under` if the story is worth more than it was pointed, `over` if less.
  This is how estimate drift surfaces before Stage 4, which already runs at 68 points a week
- `open`: anything you could not resolve

## Rules

- Anything with no id in the Feature Register, Annex A, is not a story and does not become
  one, however good an idea it looks. Record it in `open` and move on.
- Never edit acceptance criteria. If the specification is wrong, say so in `open`. The
  specification is corrected and regenerated, never patched at the edges.
- If you cannot decide, write the question into `open` and carry on to the next story.
  **Do not stop to ask.** Questions are reviewed in one batch at the gate.

## Done

Every story in your epic has a row. `python -m discovery.check` reports nothing about your
epic.
```

- [ ] **Step 5: Write `discovery/tasks/PLUGIN.md`**

```markdown
# Task type: PLUGIN

You are deciding one plugin need. You will be given a need id, such as `PL-02`.

## Read

- `CLAUDE.md` at the project root
- `docs/discovery/2026-09-08-discovery-phase-design.md`, section 3.3
- Your single row in `discovery/data/plugins.json`
- `final docs/Client/Branded/Mizzey-Operations-Platform-Technical-Design.md`, section 11
- Any probe in `discovery/data/probes.json` that bears on your need

Read nothing else. Never read anything under `_archive`.

## Write

- Your single row in `discovery/data/plugins.json`
- Nothing else

## The decision

Fill `candidates` with at least two real options before deciding, each with name, licence,
annual cost, currency, date last updated, active installs, and a maintenance risk judgement.

Then set `decision` to the chosen name, or to `build instead`, and fill `cost_annual`,
`currency`, `licence` and `owner`. Cite the probes that support it in `evidence`.

## The cost ceiling, which is the point of this task

Technical Design section 11 has already put **about 107 USD a year** in front of the client,
and named everything else as free or free-tier. **That figure is in their hands.**

If your decision takes the register above it, set `acknowledged_over_quote` to `true` and
state the reason in the row. The gate fails otherwise. This is not a technical judgement you
are allowed to absorb quietly: it is a commercial conversation Mustafa has to have.

Licences are held in the client's name, per Annex A R-14. `owner` is `client` unless there
is a stated reason otherwise.

## Rules

- A plugin that solves a problem nobody has is not a saving. Every need traces to story ids.
- Prefer a maintained free option to an unmaintained paid one, and say why in the row.
- If you cannot decide, leave `decision` as `null`, write what is missing into the row's
  candidate notes, and finish. **Do not stop to ask.**

## Done

The row has candidates, a decision, a licence, an annual figure, and evidence.
`python -m discovery.check` reports nothing about your row.
```

- [ ] **Step 6: Write `discovery/tasks/JOURNEY.md`**

```markdown
# Task type: JOURNEY

You are writing one end-to-end journey. You will be given a journey id and a one-line brief,
such as `J-01, guest adds to cart, signs in, checks out on cash on delivery, requests a return`.

## Read

- `CLAUDE.md` at the project root
- `docs/discovery/2026-09-08-discovery-phase-design.md`, section 3.4
- `discovery/data/verdicts.json`, for the stories your journey crosses
- `scripts/stories.json`, for titles and acceptance criteria
- The generated dossiers in `discovery/generated/` for the epics you cross

Read nothing else. Never read anything under `_archive`.

## Write

- One row in `discovery/data/journeys.json`
- Nothing else

## The row

- `actor`: one of shopper, guest, admin, fulfilment, support
- `language`: `ar`, `en`, or `both`. Arabic is not a translation pass. Where direction
  carries meaning, the journey differs, and that is a step, not a footnote
- `steps`: ordered. **Every step names at least one story id.** A step with no story is
  narrative, not validation, and the gate rejects it
- `gaps`: the reason this task exists

## What a gap is

A failure visible only across stories, which no single story shows. The worked example is
already known: a guest builds a cart, signs in, and WooCommerce replaces the cart instead of
merging it. That is Technical Design row 1, it spans E05 and E06, and it is invisible in 205
separate rows.

Look hardest at the seams: guest to account, cart to checkout, payment to fulfilment,
delivery to return, English to Arabic.

A gap you find becomes a new probe. Write it into `gaps` plainly enough that someone can turn
it into a yes-or-no question.

## Rules

- Every step traces to a story id, and every story id traces to Annex A. A step you want that
  has no story behind it is out of scope. Record it in `gaps` and move on.
- Do not invent acceptance criteria. Read them.
- If you cannot resolve something, write it into `gaps` and finish the journey.
  **Do not stop to ask.**

## Done

The row validates. `python -m discovery.gen_journey_docs` renders it and
`python -m discovery.check` reports nothing about it.
```

- [ ] **Step 7: Write `discovery/tasks/WIREFRAME.md`**

```markdown
# Task type: WIREFRAME

You are building the low fidelity screens for one journey. You will be given a journey id.

## Read

- `CLAUDE.md` at the project root
- `docs/discovery/2026-09-08-discovery-phase-design.md`, section 9
- The generated journey document at `discovery/generated/journey-<id>.md`
- The stories that journey names, in `scripts/stories.json`

Read nothing else. Never read anything under `_archive`.

## Write

- HTML and CSS screens under `C:/wamp64/www/mizzey/design/01-wireframes/<journey id>/`
- Nothing in the platform repo. Wireframes are not data

## What these are for

Validating that the scenario holds as a sequence of screens, before anything is built. They
are structural. Boxes, labels, real copy where copy carries meaning, and nothing else.

**Do not apply visual identity.** The client's brand identity has not arrived. Applying a
provisional one produces work that is thrown away and, worse, invites approval of something
that is not the design.

The contracted deliverable is working HTML and CSS with design tokens, not a design-tool
source file. These wireframes are the first step toward that, so write real HTML and CSS,
not images.

## Arabic

Every screen is checked right to left. Direction-carrying icons mirror. This is US-01-01, it
is 13 points, and it touches every screen in the build. A wireframe that only works in
English has validated nothing.

## Rules

- One screen per journey step that needs one. Reuse a screen across steps where the journey
  reuses it, and say so.
- Every screen names the story ids it serves, in an HTML comment at the top.
- If a step cannot be drawn because the journey is ambiguous, note it in the journey row's
  `gaps` and draw the rest. **Do not stop to ask.**

## Done

Every step of the journey that needs a screen has one, both directions render, and each file
names its stories.
```

- [ ] **Step 8: Run the tests**

Run: `python -m unittest discovery.tests.test_task_templates -v`
Expected: PASS, 6 tests.

- [ ] **Step 9: Commit**

```bash
git add discovery/tasks/ discovery/tests/test_task_templates.py
git commit -m "Add the five agent task templates

Each names the exact files to read, so no agent goes exploring and finds a
superseded figure in _archive, and each tells a blocked agent to record the
question and continue rather than stopping.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Task 14: Wire it together and hand off

**Files:**
- Create: `discovery/README.md`
- Modify: `.gitignore`
- Modify: `CLAUDE.md` at the project root (add the toolchain to section 7)

- [ ] **Step 1: Add the untracked board state to .gitignore**

Append to `.gitignore`:

```
# Board sync state, local to whoever ran the setup script
scripts/.board-state.json
```

- [ ] **Step 1b: Normalise line endings**

Git reports `LF will be replaced by CRLF` on every Python and JSON file written here, so the
datasets would carry different line endings depending on who wrote them. Create `.gitattributes`
at the repo root:

```
* text=auto eol=lf
*.png binary
*.pdf binary
*.zip binary
```

Then renormalise what is already committed:

```bash
git add --renormalize .
git status --short
```

Expect a list of files whose line endings changed. Commit them with the rest of this task.

- [ ] **Step 2: Write `discovery/README.md`**

```markdown
# discovery

The dataset, gate and generators for the discovery and validation phase. Read
`../docs/discovery/2026-09-08-discovery-phase-design.md` first.

## Commands

    python -m unittest discover -s discovery/tests -t .   run the tests
    python -m discovery.check                             the gate
    python -m discovery.run_probes [P-001 ...]            run probes
    python -m discovery.gen_epic_dossiers                 27 dossiers
    python -m discovery.gen_validation_report             the report
    python -m discovery.gen_plugin_register               the register
    python -m discovery.gen_journey_docs                  one per journey

## Rules

`scripts/stories.json` is generated from the Functional Specification. **Never edit it.**
All new data goes in `data/`, keyed by story id.

`generated/` is output. **Never edit it.** Correct the dataset and regenerate.

The gate must pass before any stage gate in the design, section 7.

## The datasets

| File | One row per |
|---|---|
| `data/probes.json` | Empirical test |
| `data/verdicts.json` | Story |
| `data/plugins.json` | Plugin need |
| `data/journeys.json` | Journey |

## Handing work to an agent

Give it one task type from `tasks/` and one id. The template names every file it may read and
the single file it may write, so parallel agents cannot collide and none goes exploring.
```

- [ ] **Step 3: Add the toolchain to the root CLAUDE.md**

In `C:/wamp64/www/mizzey/CLAUDE.md`, at the end of section 7, append:

```markdown
**The discovery toolchain lives at `platform/discovery/`.** The datasets are in `data/`, the
gate is `python -m discovery.check`, and the five agent task templates are in `tasks/`. Read
`platform/docs/discovery/2026-09-08-discovery-phase-design.md` before working in this phase.
```

- [ ] **Step 4: Run the whole suite and the gate one final time**

```bash
cd C:/wamp64/www/mizzey/platform
python -m unittest discover -s discovery/tests -t . -v
python -m discovery.check
```

Expected: all tests pass. The gate reports exactly 73 failures, all of the form
`US-NN-NN is in the risk set and has no verdict`. That is the correct state: the toolchain is
built and the phase has not been run.

- [ ] **Step 5: Commit**

```bash
git add .gitignore discovery/README.md
git commit -m "Document the discovery toolchain and ignore local board state

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
cd C:/wamp64/www/mizzey
```

- [ ] **Step 6: Hand off Step 1 to Mustafa**

The toolchain is done. Step 1 of the design is blocking and is not an agent's:

```bash
wp corex make:site Mizzey
```

It needs a database, a local host entry, and the CoreX plugins activated. Set `MIZZEY_WP` to
the resulting path so `run_probes.py` finds it:

```bash
export MIZZEY_WP=C:/wamp64/www/mizzey-site/wp
```

Until that exists, `run_probes.py` falls back to the CoreX development install at
`C:/wamp64/www/corex/wp`, which is fine for writing probe scripts against but must not be
where the recorded results come from.

Once the site is up, Step 2 begins: fifteen PROBE tasks, dispatchable in parallel.

---

## Verification

At the end of this plan:

- `python -m unittest discover -s discovery/tests -t .` passes, 121 tests
- `python -m discovery.check` reports 73 missing verdicts and nothing else
- `discovery/generated/` holds 27 epic dossiers, a validation report and a plugin register
- `discovery/data/probes.json` holds 15 seeded probes with `expected` recorded and nothing observed
- `discovery/data/plugins.json` holds 6 undecided needs
- `discovery/tasks/` holds 5 templates
- Fourteen commits, each one green

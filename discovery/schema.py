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

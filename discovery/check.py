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
        print('\n%d problem(s)' % len(problems))
        return 1
    print('clean')
    return 0


if __name__ == '__main__':
    sys.exit(main())

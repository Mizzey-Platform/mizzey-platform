"""The discovery gate.

Every rule is a function with the signature
    rule(stories, verdicts, probes, plugins, journeys) -> list[str]
returning one string per problem. An empty list means the rule passed.

Keeping rules pure means each one is unit-testable without touching disk, and
adding a rule is adding a function to RULES.
"""

import sys

from discovery import riskset, schema


def _known_ids(stories):
    return {s['id'] for s in stories}


def check_unknown_stories(stories, verdicts, probes, plugins, journeys):
    """No dataset may reference a story id that is not in stories.json."""
    known = _known_ids(stories)
    problems = []
    for v in verdicts:
        if v.get('story') not in known:
            problems.append('verdict names unknown story %s' % v.get('story'))
    for p in probes:
        for sid in p.get('stories', []):
            if sid not in known:
                problems.append('probe %s names unknown story %s' % (p.get('id'), sid))
    for pl in plugins:
        for sid in pl.get('stories', []):
            if sid not in known:
                problems.append('plugin %s names unknown story %s' % (pl.get('id'), sid))
    for j in journeys:
        for step in j.get('steps', []):
            for sid in step.get('stories', []):
                if sid not in known:
                    problems.append('journey %s step %s names unknown story %s'
                                    % (j.get('id'), step.get('n'), sid))
    return problems


def check_traceable(stories, verdicts, probes, plugins, journeys):
    """Every story carrying a verdict must trace to Annex A."""
    trace = {s['id']: (s.get('trace') or '').strip() for s in stories}
    return ['verdict for %s, which has no Annex A trace' % v['story']
            for v in verdicts
            if v.get('story') in trace and not trace[v['story']]]


RULES = [
    check_unknown_stories,
    check_traceable,
]


def run_checks():
    """Load every dataset and run every rule. Returns the problem list."""
    stories = schema.load_stories()
    verdicts = schema.load('verdicts')
    probes = schema.load('probes')
    plugins = schema.load('plugins')
    journeys = schema.load('journeys')
    problems = []
    for rule in RULES:
        problems.extend(rule(stories, verdicts, probes, plugins, journeys))
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

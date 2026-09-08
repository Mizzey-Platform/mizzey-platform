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


RULES = [
    check_unknown_stories,
    check_traceable,
    check_risk_set_has_real_verdict,
    check_proved_has_evidence,
    check_build_traces_to_gap,
    check_allowed_values,
    check_no_duplicate_rows,
    check_plugin_complete,
    check_plugin_ceiling,
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

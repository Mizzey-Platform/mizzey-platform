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

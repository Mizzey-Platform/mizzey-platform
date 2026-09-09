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
import shutil
import subprocess
import sys

from discovery import schema

PROBES_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'probes')


def executable(name):
    """Resolve a command to a full path before handing it to subprocess.

    On Windows both `wp` and `node` are .bat or .cmd shims. subprocess.run
    without shell=True does not apply PATHEXT, so a bare name raises
    FileNotFoundError even though the command runs fine in the shell.
    shutil.which does apply PATHEXT, and resolving here keeps shell=True out of
    the runner, so a path with a space in it cannot be re-split by a shell.
    Falls back to the bare name so the failure still names the command.
    """
    return shutil.which(name) or name


# The Mizzey site created in Step 1. Override with MIZZEY_WP when it moves.
WP_PATH = os.environ.get('MIZZEY_WP', 'C:/wamp64/www/corex/wp')


def parse_result(stdout):
    """Pull the JSON object out of a probe's stdout and validate it.

    Decodes from each opening brace rather than matching greedily from the
    first to the last: wp-cli and PHP notices routinely contain braces of their
    own, and a greedy match swallows them and fails on valid output. Two
    objects is an error rather than a guess about which one was meant.
    """
    decoder = json.JSONDecoder()
    found = []
    i = 0
    while i < len(stdout):
        if stdout[i] != '{':
            i += 1
            continue
        try:
            value, end = decoder.raw_decode(stdout[i:])
        except ValueError:
            i += 1
            continue
        if isinstance(value, dict):
            found.append(value)
            i += end  # skip what was consumed, so a nested brace is not recounted
        else:
            i += 1
    if not found:
        raise ValueError('no JSON object found in probe output')
    if len(found) > 1:
        raise ValueError('probe printed %d JSON objects, expected one' % len(found))
    data = found[0]
    observed = data.get('observed')
    if not isinstance(observed, str) or not observed.strip():
        # A verdict with nothing observed would still stamp run_at and env onto
        # the row, so it would read as run while carrying no evidence at all.
        raise ValueError('probe output has no usable "observed"')
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
            out = subprocess.run([executable('wp')] + list(args) + ['--path=' + WP_PATH],
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
        cmd = [executable('node'), script]
    elif row['method'] == 'php':
        cmd = [executable('wp'), 'eval-file', script, '--path=' + WP_PATH]
    else:
        cmd = [executable('wp'), 'eval-file', script, '--path=' + WP_PATH]
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

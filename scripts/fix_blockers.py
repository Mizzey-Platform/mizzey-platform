"""Repair the blocked-until sections on the story issues.

Two bugs in the first pass. The client-input table in Part Seven section 33 has
three columns, not four, so it was never read, and CR-01 through CR-15 blocked
nothing. And a cell naming a range, "US-08-01 to US-08-07", only produced its
two endpoints.
"""
import json, io, os, re, subprocess, collections, time, sys

REPO = 'MustafaShaaban/mizzey-platform'
HERE = os.path.dirname(os.path.abspath(__file__))
SPEC = r'C:\wamp64\www\mizzey\final docs\Client\Branded\Mizzey-Operations-Platform-Functional-Specification.md'
issues = json.load(io.open(os.path.join(HERE, 'issues.json'), encoding='utf-8'))
S = {s['id']: s for s in json.load(io.open(os.path.join(HERE, 'stories.json'), encoding='utf-8'))}
spec = io.open(SPEC, encoding='utf-8').read()


def expand(cell):
    """Every story id a cell names, ranges included."""
    found = set()
    for a, b in re.findall(r'(US-\d\d-\d\d)\s+to\s+(US-\d\d-\d\d)', cell):
        e1, n1 = a[3:5], int(a[6:8])
        e2, n2 = b[3:5], int(b[6:8])
        if e1 == e2:
            for n in range(n1, n2 + 1):
                found.add('US-%s-%02d' % (e1, n))
    found.update(re.findall(r'US-\d\d-\d\d', cell))
    return found


blocks = collections.defaultdict(list)

# Section 32, open decisions. Four columns.
sec32 = spec[spec.find('## 32. Open decisions'):spec.find('## 33. Client inputs')]
for ident, need, stories, when in re.findall(
        r'^\| \*\*(OD-\d+)\*\* \| (.+?) \| (.+?) \| (.+?) \|$', sec32, re.M):
    for sid in expand(stories):
        blocks[sid].append(('decision', ident, need.strip(), when.strip()))

# Section 33, client inputs. Three columns, and the id sits inside the first.
sec33 = spec[spec.find('## 33. Client inputs'):spec.find('## 34. Assumptions')]
for first, stories, when in re.findall(r'^\| (.+?) \| (.+?) \| (.+?) \|$', sec33, re.M):
    m = re.match(r'\*\*(CR-\d+)\*\*\s*(.*)', first.strip())
    if not m:
        continue
    ident, need = m.group(1), m.group(2).strip()
    for sid in expand(stories):
        blocks[sid].append(('input', ident, need, when.strip()))

print('%d stories carry a blocker, from %d decisions and inputs'
      % (len(blocks), len(set(b[1] for v in blocks.values() for b in v))))
missing = [s for s in blocks if s not in S]
if missing:
    print('  ids named in Part Seven that are not stories: %s' % ', '.join(sorted(missing)))
    for s in missing:
        del blocks[s]


def gh(args, body=None):
    p = subprocess.run(['gh'] + args, capture_output=True, text=True, input=body, encoding='utf-8')
    return p.returncode, (p.stdout or '').strip(), (p.stderr or '').strip()


SECTION = re.compile(r'\n### Blocked until\n\n(?:- .*\n)+')
done = 0
for sid in sorted(blocks):
    num = issues.get(sid)
    if not num:
        continue
    rc, out, err = gh(['api', '/repos/%s/issues/%d' % (REPO, num), '--jq', '.body'])
    if rc:
        print('  read failed %s' % sid)
        continue
    body = out.replace('\\n', '\n') if '\\n' in out and '\n' not in out else out

    lines = []
    for kind, ident, need, when in sorted(blocks[sid], key=lambda b: b[1]):
        what = 'decision' if kind == 'decision' else 'input'
        lines.append('- **%s** (client %s) %s _(%s)_' % (ident, what, need, when.lower()))
    section = '\n### Blocked until\n\n' + '\n'.join(lines) + '\n'

    if SECTION.search(body):
        body = SECTION.sub(section, body)
    else:
        body = body.replace('\n---\n\nEpic #', section + '\n---\n\nEpic #')

    labels = sorted(set('blocked:client-%s' % ('decision' if k == 'decision' else 'input')
                        for k, _, _, _ in blocks[sid]))
    payload = json.dumps({'body': body})
    rc, _, err = gh(['api', '--method', 'PATCH', '/repos/%s/issues/%d' % (REPO, num),
                     '--input', '-'], body=payload)
    if rc:
        print('  patch failed %s %s' % (sid, err.splitlines()[0] if err else ''))
        continue
    gh(['issue', 'edit', str(num), '--repo', REPO] +
       [a for l in labels for a in ('--add-label', l)])
    done += 1
    if done % 15 == 0:
        print('  %d updated' % done)
        sys.stdout.flush()
    time.sleep(0.9)

print('\n%d issues updated' % done)

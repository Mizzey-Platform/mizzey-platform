"""Generate the epic and story issues on mizzey-platform from the specification."""
import json, io, os, re, subprocess, collections, time, sys

# Retired 22 Sep 2026: an Option C board and backlog generator. It writes to GitHub or to client documents,
# and scripts/issues.json is missing, so a rerun would duplicate issues. See scripts/README.md.
if os.environ.get('MIZZEY_LEGACY_BOARD_SCRIPTS') != '1':
    raise SystemExit('Retired Option C script. Not for Option B use. See scripts/README.md.')

REPO = 'MustafaShaaban/mizzey-platform'
HERE = os.path.dirname(os.path.abspath(__file__))
SPEC = r'C:\wamp64\www\mizzey\final docs\Client\Branded\Mizzey-Operations-Platform-Functional-Specification.md'
S = json.load(io.open(os.path.join(HERE, 'stories.json'), encoding='utf-8'))
MS = json.load(io.open(os.path.join(HERE, 'milestones.json'), encoding='utf-8'))
STATE = os.path.join(HERE, 'issues.json')
created = json.load(io.open(STATE, encoding='utf-8')) if os.path.exists(STATE) else {}

MILESTONE_FOR_STAGE = {
    1: 'Stage 1. Start and specification', 2: 'Stage 2. Foundation and products',
    3: 'Stage 3. Shop front complete', 4: 'Stage 4. Operations system',
    5: 'Stage 5. Migration and reports', 6: 'Stage 6. Live and handed over',
    7: 'Second release and contingency',
}

# Which open decisions and client inputs block which stories, read straight out
# of Part Seven of the specification so the two cannot disagree.
spec = io.open(SPEC, encoding='utf-8').read()
blocks = collections.defaultdict(list)
part7 = spec[spec.find('## 32. Open decisions'):spec.find('## 34. Assumptions')]
for row in re.findall(r'^\| \*\*(OD-\d+|CR-\d+)\*\* \| (.+?) \| (.+?) \| (.+?) \|$', part7, re.M):
    ident, need, stories, when = row
    for sid in re.findall(r'US-\d\d-\d\d', stories):
        blocks[sid].append((ident, need.strip(), when.strip()))

epics = collections.OrderedDict()
for s in S:
    epics.setdefault(s['epic'], s['epicname'])


def gh(args, body=None):
    p = subprocess.run(['gh'] + args, capture_output=True, text=True, input=body, encoding='utf-8')
    return p.returncode, (p.stdout or '').strip(), (p.stderr or '').strip()


def create(title, body, labels, milestone=None):
    payload = {'title': title, 'body': body, 'labels': labels}
    if milestone:
        payload['milestone'] = MS[milestone]
    for attempt in range(4):
        rc, out, err = gh(['api', '--method', 'POST', '-H', 'Accept: application/vnd.github+json',
                           '/repos/%s/issues' % REPO, '--input', '-'], body=json.dumps(payload))
        if rc == 0:
            return json.loads(out)['number']
        if 'secondary rate limit' in err.lower() or 'abuse' in err.lower():
            time.sleep(60)
            continue
        print('  FAILED %s :: %s' % (title[:50], err.splitlines()[0] if err else ''))
        return None
    return None


def scope_label(scope):
    if 'DLV' in scope:
        return 'scope:DLV'
    if scope.strip().startswith('P1-L'):
        return 'scope:P1-L'
    return 'scope:P1'


# ------------------------------------------------------------------ epics
print('epics')
for e, name in epics.items():
    key = 'epic:' + e
    if key in created:
        continue
    mine = [s for s in S if s['epic'] == e]
    sprints = sorted(set(s['sprint'] for s in mine))
    stages = sorted(set(s['stagenum'] for s in mine))
    lev = mine[0]['leverage']
    body = io.StringIO()
    body.write('**%s. %s**\n\n' % (e, name.title()))
    body.write('%d stories, %d points. Sprint%s %s. Stage%s %s. WooCommerce leverage: **%s**.\n\n'
               % (len(mine), sum(s['points'] for s in mine),
                  '' if len(sprints) == 1 else 's', ', '.join(str(x) for x in sprints),
                  '' if len(stages) == 1 else 's', ', '.join(str(x) for x in stages), lev))
    if lev == 'custom':
        body.write('> WooCommerce contributes nothing to this epic. Every story in it is a module '
                   'written on CoreX from scratch, and the estimate reflects that.\n\n')
    body.write('### Stories\n\n')
    for s in sorted(mine, key=lambda x: x['id']):
        marks = []
        if s['key']:
            marks.append('key')
        if s['early']:
            marks.append('built early')
        if s['prio'] == 'Later':
            marks.append('second release')
        body.write('- [ ] **%s** %s (sprint %d, %d pts%s)\n'
                   % (s['id'], s['title'], s['sprint'], s['points'],
                      ', ' + ', '.join(marks) if marks else ''))
    trace = sorted(set(t for s in mine for t in re.findall(r'[A-Z]{2,6}-\d{1,3}[a-z]?', s['trace'])))
    body.write('\n### Requirements delivered\n\n%s\n' % ', '.join(trace))
    body.write('\n---\n\nSpecification: `docs/engagement/Mizzey-Operations-Platform-Functional-Specification.md`, '
               'EPIC %s. Scope authority is the Feature Register MS-ANX-2026-001.\n' % e)
    labels = ['type:epic', 'epic:' + e, 'leverage:' + lev]
    n = create('%s. %s' % (e, name.title()), body.getvalue(), labels,
               MILESTONE_FOR_STAGE[min(stages)])
    if n:
        created[key] = n
        print('  #%-4d %s. %s' % (n, e, name.title()))
        json.dump(created, io.open(STATE, 'w', encoding='utf-8'), indent=1)
        time.sleep(1.1)

# ---------------------------------------------------------------- stories
print('\nstories')
done = 0
for s in sorted(S, key=lambda x: (x['sprint'], x['id'])):
    if s['id'] in created:
        continue
    b = io.StringIO()
    b.write('_%s_\n\n' % s['narrative'])
    b.write('| | |\n|---|---|\n')
    b.write('| **Traceability** | %s |\n' % s['trace'])
    b.write('| **Scope** | %s |\n' % s['scope'])
    b.write('| **Stage** | %s, accepted at the Stage %d gate |\n' % (s['stage'], s['stagenum']))
    b.write('| **Sprint** | %d%s |\n' % (s['sprint'],
            ', built before the stage that accepts it' if s['early'] else ''))
    b.write('| **Priority** | %s |\n' % s['prio'])
    b.write('| **Estimate** | %d points |\n' % s['points'])
    b.write('| **WooCommerce** | %s |\n' % s['leverage'])
    b.write('| **Epic** | %s. %s |\n\n' % (s['epic'], s['epicname'].title()))
    b.write('### Acceptance criteria\n\n')
    b.write('_From the Functional Specification. Do not edit here: correct the specification and '
            'regenerate, so the board and the contract cannot drift apart._\n\n')
    for i, ac in enumerate(s['ac'], 1):
        b.write('- [ ] **%d.** %s\n' % (i, ac))
    b.write('\n### Also required\n\n')
    b.write('- [ ] English and Arabic, right to left, no layout break (GC-01)\n')
    b.write('- [ ] Phone, tablet and desktop (GC-04)\n')
    b.write('- [ ] Exception paths, not only the successful path (GC-06)\n')
    if s['epic'] in ('E15', 'E16', 'E17', 'E18', 'E19', 'E20', 'E21', 'E22', 'E23', 'E24'):
        b.write('- [ ] Permitted roles enforced, and a role without permission cannot reach it (GC-07)\n')
        b.write('- [ ] Audit entry written where money, price, stock, permissions or refunds change (GC-08)\n')
    if s['id'] in blocks:
        b.write('\n### Blocked until\n\n')
        for ident, need, when in blocks[s['id']]:
            b.write('- **%s** %s _(%s)_\n' % (ident, need, when.lower()))
    b.write('\n---\n\nEpic #%s. Specification: EPIC %s, %s.\n'
            % (created.get('epic:' + s['epic'], '?'), s['epic'], s['id']))

    labels = ['type:story', 'epic:' + s['epic'], scope_label(s['scope']),
              'leverage:' + s['leverage'], 'priority:' + s['prio'].lower(),
              'sprint-%02d' % s['sprint']]
    if s['key']:
        labels.append('key-requirement')
    if s['early']:
        labels.append('built-early')
    if s['id'] in blocks:
        kinds = set('blocked:client-decision' if i.startswith('OD') else 'blocked:client-input'
                    for i, _, _ in blocks[s['id']])
        labels.extend(sorted(kinds))

    n = create('%s %s' % (s['id'], s['title']), b.getvalue(), labels,
               MILESTONE_FOR_STAGE[s['stagenum']])
    if n:
        created[s['id']] = n
        done += 1
        if done % 10 == 0:
            print('  %d created, latest #%d %s' % (done, n, s['id']))
            sys.stdout.flush()
        json.dump(created, io.open(STATE, 'w', encoding='utf-8'), indent=1)
        time.sleep(1.1)

print('\ntotal issues on record: %d' % len(created))

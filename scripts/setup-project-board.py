"""Build the GitHub Project board for the Mizzey delivery.

Run this once, after granting the project scope:

    gh auth refresh -s project,read:project
    python scripts/setup-project-board.py

It creates the board, adds a Sprint and a Points field, puts every epic and
story issue on it, and sets those two fields from the Delivery Backlog. Epic,
scope, WooCommerce leverage, priority and key requirement are already labels on
the issues, so the board filters on them without needing fields of their own.

Safe to re run. It records what it has done in scripts/.board-state.json and
skips anything already placed.

One thing this cannot do. GitHub does not allow an iteration field to be created
through the API, only in the browser. Sprint is therefore a single select field.
If you want the roadmap view, add an iteration field by hand in the board
settings and copy the Sprint values across; nothing else here depends on it.
"""
import json, io, os, subprocess, sys, time

OWNER = 'MustafaShaaban'
REPO = 'MustafaShaaban/mizzey-platform'
TITLE = 'Mizzey Operations Platform delivery'

HERE = os.path.dirname(os.path.abspath(__file__))
STATE_PATH = os.path.join(HERE, '.board-state.json')
STORIES = os.path.join(HERE, 'backlog.json')

STAGE_OF_SPRINT = {}
for _s in range(1, 17):
    STAGE_OF_SPRINT[_s] = (1 if _s <= 2 else 2 if _s <= 5 else 3 if _s <= 8
                           else 4 if _s <= 11 else 5 if _s <= 13 else 6 if _s == 14 else 7)


def gh(args, body=None, tolerate=False):
    p = subprocess.run(['gh'] + args, capture_output=True, text=True,
                       input=body, encoding='utf-8')
    if p.returncode and not tolerate:
        sys.exit('gh %s failed:\n%s' % (' '.join(args[:3]), (p.stderr or '').strip()))
    return p.returncode, (p.stdout or '').strip(), (p.stderr or '').strip()


def load(path, default):
    if os.path.exists(path):
        return json.load(io.open(path, encoding='utf-8'))
    return default


def save(path, data):
    json.dump(data, io.open(path, 'w', encoding='utf-8'), indent=1)


rc, _, err = gh(['project', 'list', '--owner', OWNER, '--limit', '1'], tolerate=True)
if rc:
    sys.exit('The project scope is missing. Run this first:\n\n    '
             'gh auth refresh -s project,read:project\n')

state = load(STATE_PATH, {})
backlog = load(STORIES, None)
if backlog is None:
    sys.exit('scripts/backlog.json is missing. It is written by the backlog generator.')

# ------------------------------------------------------------------- board
if 'project' not in state:
    _, out, _ = gh(['project', 'create', '--owner', OWNER, '--title', TITLE, '--format', 'json'])
    p = json.loads(out)
    state['project'] = {'number': p['number'], 'id': p['id'], 'url': p['url']}
    save(STATE_PATH, state)
    print('board created: %s' % p['url'])
else:
    print('board: %s' % state['project']['url'])

num = str(state['project']['number'])
pid = state['project']['id']

# ------------------------------------------------------------------ fields
_, out, _ = gh(['project', 'field-list', num, '--owner', OWNER, '--format', 'json', '--limit', '60'])
fields = {f['name']: f for f in json.loads(out)['fields']}

if 'Sprint' not in fields:
    opts = ','.join('Sprint %02d' % n for n in range(1, 17))
    gh(['project', 'field-create', num, '--owner', OWNER, '--name', 'Sprint',
        '--data-type', 'SINGLE_SELECT', '--single-select-options', opts])
    print('field created: Sprint')
if 'Points' not in fields:
    gh(['project', 'field-create', num, '--owner', OWNER, '--name', 'Points',
        '--data-type', 'NUMBER'])
    print('field created: Points')
if 'Stage' not in fields:
    opts = ','.join(['Stage %d' % n for n in range(1, 7)] + ['Second release'])
    gh(['project', 'field-create', num, '--owner', OWNER, '--name', 'Stage',
        '--data-type', 'SINGLE_SELECT', '--single-select-options', opts])
    print('field created: Stage')

_, out, _ = gh(['project', 'field-list', num, '--owner', OWNER, '--format', 'json', '--limit', '60'])
fields = {f['name']: f for f in json.loads(out)['fields']}
sprint_opt = {o['name']: o['id'] for o in fields['Sprint'].get('options', [])}
stage_opt = {o['name']: o['id'] for o in fields['Stage'].get('options', [])}

# ------------------------------------------------------------------- items
placed = state.setdefault('items', {})
todo = [s for s in backlog if s['id'] not in placed]
print('%d issues to place, %d already on the board' % (len(todo), len(placed)))

for i, s in enumerate(todo, 1):
    url = 'https://github.com/%s/issues/%d' % (REPO, s['issue'])
    rc, out, err = gh(['project', 'item-add', num, '--owner', OWNER, '--url', url,
                       '--format', 'json'], tolerate=True)
    if rc:
        print('  add failed %-12s %s' % (s['id'], err.splitlines()[0] if err else ''))
        continue
    item_id = json.loads(out)['id']

    gh(['project', 'item-edit', '--id', item_id, '--project-id', pid,
        '--field-id', fields['Sprint']['id'],
        '--single-select-option-id', sprint_opt['Sprint %02d' % s['sprint']]], tolerate=True)
    gh(['project', 'item-edit', '--id', item_id, '--project-id', pid,
        '--field-id', fields['Points']['id'],
        '--number', str(s['points'])], tolerate=True)
    st = STAGE_OF_SPRINT[s['sprint']]
    gh(['project', 'item-edit', '--id', item_id, '--project-id', pid,
        '--field-id', fields['Stage']['id'],
        '--single-select-option-id',
        stage_opt['Stage %d' % st if st <= 6 else 'Second release']], tolerate=True)

    placed[s['id']] = item_id
    if i % 10 == 0:
        save(STATE_PATH, state)
        print('  %d of %d placed' % (i, len(todo)))
        sys.stdout.flush()
    time.sleep(0.4)

save(STATE_PATH, state)
print('\ndone. %d items on the board.' % len(placed))
print(state['project']['url'])
print('\nSuggested views, added by hand in the browser:')
print('  Board   grouped by Status, filtered to one sprint at a time')
print('  Table   grouped by Sprint, with Points summed, to see the week load')
print('  Table   grouped by Stage, to see what each payment is buying')

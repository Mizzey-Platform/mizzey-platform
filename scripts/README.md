# Scripts (retired, Option C)

**`stories.json` is not scope and is not design authority (D-14).** Its 205 stories describe Option C, which was
never signed. No surface, flow or wireframe is derived from it: see [`design/README.md`](../design/README.md).

**Retired on 22 September 2026.** These generated the Option C board (GitHub Project #4 and its 232 issues)
from the Option C Functional Specification. They are kept as history. Each exits immediately unless
`MIZZEY_LEGACY_BOARD_SCRIPTS=1` is set, because `make_issues.py`, `fix_blockers.py` and `setup-project-board.py`
write to GitHub, `gen_backlog.py` writes into the client document folder, and `issues.json` is missing, so a
rerun would duplicate issues. Option B work items come from Spec Kit specs (see `AGENTS.md`), and no backlog
generator is approved yet.

---

The board is generated from the Functional Specification, not typed. These are what generate it.

| Script | What it does |
|---|---|
| `gen_backlog.py` | Parses the specification into `stories.json`, assigns every story a sprint and a point estimate, and writes the Delivery Backlog markdown |
| `make_issues.py` | Creates the 27 epic and 205 story issues from `stories.json` |
| `fix_blockers.py` | Rewrites the blocked-until section on every issue from Part Seven of the specification |
| `setup-project-board.py` | Creates the Projects v2 board and places every issue on it |
| `stories.json` | The parsed specification. Regenerated, never hand edited |
| `backlog.json` | Story id to issue number, with sprint and points. Feeds the board script |

## The rule

**A ticket is generated, not written.** If an acceptance criterion is wrong, correct
`docs/engagement/Mizzey-Operations-Platform-Functional-Specification.md`, re run the generator, and let it
update the issue. Editing the criterion on the issue instead makes the board and the contract disagree,
and the contract is the one that gets enforced.

## Order

```bash
python scripts/gen_backlog.py            # specification  ->  stories.json + the backlog document
python scripts/make_issues.py            # stories.json   ->  GitHub issues
python scripts/fix_blockers.py           # specification  ->  the blocked-until sections

gh auth refresh -s project,read:project  # once, interactive
python scripts/setup-project-board.py    # issues         ->  the board
```

Every one is safe to re run. They record what they have already done and skip it.

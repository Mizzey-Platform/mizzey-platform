# Scripts

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

# Task type: VERDICT

You are writing the verdict rows for one epic. You will be given an epic id, such as `E13`.

## Read

- `CLAUDE.md` at the project root
- `docs/discovery/2026-09-08-discovery-phase-design.md`, sections 3.2 and 8
- Every story in `scripts/stories.json` whose `epic` matches yours
- Every probe in `discovery/data/probes.json` whose `stories` intersect yours, or whose
  `gap_rows` are relevant
- `final docs/Client/Branded/Mizzey-Operations-Platform-Technical-Design.md`, section 7
- `final docs/Client/Branded/Mizzey-Operations-Platform-Functional-Specification.md`, the
  sections covering your epic

Read nothing else. Never read anything under `_archive`.

## Write

- One row per story of your epic in `discovery/data/verdicts.json`
- Nothing else. You must not run probes, and you must never edit `scripts/stories.json`,
  which is generated from the specification and will be overwritten

## The row

Use `discovery.schema.blank_verdict(story_id)` as the starting shape, then fill:

- `claimed`: the `leverage` from the story. Copy it, do not judge it
- `actual`: `native`, `extend`, `build`, or `plugin`
- `confidence`: `proved` only if a probe ran and you name it in `evidence`. Otherwise
  `reasoned`. Never leave a risk-set story at `assumed`
- `evidence`: probe ids. Required when `confidence` is `proved`
- `gap_rows`: Technical Design section 7 rows. If you class something `build` with no gap
  row, section 7 missed it, and `risk` must say so
- `points_flag`: `under` if the story is worth more than it was pointed, `over` if less.
  This is how estimate drift surfaces before Stage 4, which already runs at 68 points a week
- `open`: anything you could not resolve

## Rules

- Anything with no id in the Feature Register, Annex A, is not a story and does not become
  one, however good an idea it looks. Record it in `open` and move on.
- Never edit acceptance criteria. If the specification is wrong, say so in `open`. The
  specification is corrected and regenerated, never patched at the edges.
- If you cannot decide, write the question into `open` and carry on to the next story.
  **Do not stop to ask.** Questions are reviewed in one batch at the gate.

## Done

Every story in your epic has a row. `python -m discovery.check` reports nothing about your
epic.

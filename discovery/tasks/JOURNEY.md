# Task type: JOURNEY

You are writing one end-to-end journey. You will be given a journey id and a one-line brief,
such as `J-01, guest adds to cart, signs in, checks out on cash on delivery, requests a return`.

## Read

- `CLAUDE.md` at the project root
- `docs/discovery/2026-09-08-discovery-phase-design.md`, section 3.4
- `discovery/data/verdicts.json`, for the stories your journey crosses
- `scripts/stories.json`, for titles and acceptance criteria
- The generated dossiers in `discovery/generated/` for the epics you cross

Read nothing else. Never read anything under `_archive`.

## Write

- One row in `discovery/data/journeys.json`
- Nothing else

## The row

- `actor`: one of shopper, guest, admin, fulfilment, support
- `language`: `ar`, `en`, or `both`. Arabic is not a translation pass. Where direction
  carries meaning, the journey differs, and that is a step, not a footnote
- `steps`: ordered. **Every step names at least one story id.** A step with no story is
  narrative, not validation, and the gate rejects it
- `gaps`: the reason this task exists

## What a gap is

A failure visible only across stories, which no single story shows. The worked example is
already known: a guest builds a cart, signs in, and WooCommerce replaces the cart instead of
merging it. That is Technical Design row 1, it spans E05 and E06, and it is invisible in 205
separate rows.

Look hardest at the seams: guest to account, cart to checkout, payment to fulfilment,
delivery to return, English to Arabic.

A gap you find becomes a new probe. Write it into `gaps` plainly enough that someone can turn
it into a yes-or-no question.

## Rules

- Every step traces to a story id, and every story id traces to Annex A. A step you want that
  has no story behind it is out of scope. Record it in `gaps` and move on.
- Do not invent acceptance criteria. Read them.
- If you cannot resolve something, write it into `gaps` and finish the journey.
  **Do not stop to ask.**

## Done

The row validates. `python -m discovery.gen_journey_docs` renders it and
`python -m discovery.check` reports nothing about it.

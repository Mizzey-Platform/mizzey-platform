# Task type: PROBE

You are running one probe. You will be given a probe id, such as `P-001`.

## Read

- `CLAUDE.md` at the project root
- `docs/discovery/2026-09-08-discovery-phase-design.md`, sections 5 and 8
- The single row in `discovery/data/probes.json` whose `id` matches yours
- `final docs/Client/Branded/Mizzey-Operations-Platform-Technical-Design.md`, section 7,
  the rows named in your `gap_rows`

Read nothing else. In particular, never read anything under `_archive`: it holds superseded
pricing drafts and will give you wrong figures.

## Write

- One probe script at `discovery/probes/<your script filename>`, taken from the `script`
  field of your row
- Nothing else. You must not touch `verdicts.json`, `plugins.json` or `journeys.json`

## What the script must do

Answer the `question` field, and nothing wider. Print exactly one JSON object on stdout:

    {"observed": "what actually happened, in one or two sentences", "verdict": "confirmed"}

`verdict` is `confirmed`, `refuted`, or `partial`, measured against the `expected` field,
which records what the Technical Design assumes today.

Write the script so it can be run again later without leaving state behind. When it needs
fixtures, it creates them and removes them.

The runner executes it: `python -m discovery.run_probes <your id>`.

## Rules

- **Do not edit the `expected` field.** It is the prior claim, recorded before the test, and
  a refutation is only legible against it.
- **Refuting the Technical Design is a good outcome**, not a failure. Report it plainly.
- Anything with no id in the Feature Register, Annex A, is out of scope. It is not a defect
  and it does not become work. Note it and move on.
- If you cannot answer, add a sentence to your row's `notes` saying exactly what blocked you,
  and stop cleanly. **Do not stop to ask a question.** Questions are collected and reviewed
  in one batch at the gate.

## Done

The script exists, runs, and prints a valid result object. `python -m discovery.check` reports
nothing new.

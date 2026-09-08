# Task type: PLUGIN

You are deciding one plugin need. You will be given a need id, such as `PL-02`.

## Read

- `CLAUDE.md` at the project root
- `docs/discovery/2026-09-08-discovery-phase-design.md`, section 3.3
- Your single row in `discovery/data/plugins.json`
- `final docs/Client/Branded/Mizzey-Operations-Platform-Technical-Design.md`, section 11
- Any probe in `discovery/data/probes.json` that bears on your need

Read nothing else. Never read anything under `_archive`.

## Write

- Your single row in `discovery/data/plugins.json`
- Nothing else

## The decision

Fill `candidates` with at least two real options before deciding, each with name, licence,
annual cost, currency, date last updated, active installs, and a maintenance risk judgement.

Then set `decision` to the chosen name, or to `build instead`, and fill `cost_annual`,
`currency`, `licence` and `owner`. Cite the probes that support it in `evidence`.

## The cost ceiling, which is the point of this task

Technical Design section 11 has already put **about 107 USD a year** in front of the client,
and named everything else as free or free-tier. **That figure is in their hands.**

If your decision takes the register above it, set `acknowledged_over_quote` to `true` and
state the reason in the row. The gate fails otherwise. This is not a technical judgement you
are allowed to absorb quietly: it is a commercial conversation Mustafa has to have.

Licences are held in the client's name, per Annex A R-14. `owner` is `client` unless there
is a stated reason otherwise.

## Rules

- A plugin that solves a problem nobody has is not a saving. Every need traces to story ids.
- Prefer a maintained free option to an unmaintained paid one, and say why in the row.
- If you cannot decide, leave `decision` as `null`, write what is missing into the row's
  candidate notes, and finish. **Do not stop to ask.**

## Done

The row has candidates, a decision, a licence, an annual figure, and evidence.
`python -m discovery.check` reports nothing about your row.

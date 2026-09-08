# Task type: PLUGIN

You are deciding one plugin need. You will be given a need id, such as `PL-02`.

## Where you are

**Every path below is relative to `C:\wamp64\www\mizzey`.** That directory contains `platform/`,
which is the git repo, alongside `final docs/`, `design/` and `_archive/`. Paths starting
`platform/` are inside the repo. Paths starting `final docs/` are not. Run python commands from
`C:\wamp64\www\mizzey\platform`.

## Read

- `CLAUDE.md`
- `platform/docs/discovery/2026-09-08-discovery-phase-design.md`, section 3.3
- Your single row in `platform/discovery/data/plugins.json`
- `final docs/Client/Branded/Mizzey-Operations-Platform-Technical-Design.md`, section 11
- Any probe in `platform/discovery/data/probes.json` that bears on your need

Read nothing else. Never read anything under `_archive/`.

## Write

- Your single row in `platform/discovery/data/plugins.json`
- Nothing else

## The decision

Fill `candidates` with at least two real options before deciding. Each candidate is an object with
these exact keys, which are the keys already used in the `PL-01` seed row:

    name, licence, cost_annual, currency, last_update, installs, maintenance_risk, notes

Then set `decision` to the chosen `name`, or to the exact string `build instead`, and fill
`cost_annual`, `currency`, `licence` and `owner` on the row itself. Cite the probes that support
it in `evidence`.

`decision` must be a non-empty string. Leaving it `null` means undecided, which is allowed while
you work. An empty string is not the same thing and is refused.

`cost_annual` must be a number and must not be negative. If the chosen candidate lists a
`cost_annual`, the row's `cost_annual` must match it.

## The cost ceiling, which is the point of this task

Technical Design section 11 has already put **about 107 USD a year** in front of the client, and
named everything else as free or free-tier. **That figure is in their hands.**

If your decision takes the register above it, set `acknowledged_over_quote` to `true` **on the row
that causes the overage** and state the reason in the row. The acknowledging rows must account for
the difference: a one dollar row cannot license a five hundred dollar one. The gate refuses
otherwise.

This is not a technical judgement you are allowed to absorb quietly. It is a commercial
conversation Mustafa has to have.

Licences are held in the client's name, per Annex A R-14. `owner` is `client` unless there is a
stated reason otherwise.

## Rules

- A plugin that solves a problem nobody has is not a saving. Every need traces to story ids.
- Prefer a maintained free option to an unmaintained paid one, and say why in the row.
- **Never invent a product name, version, install count or cost.** This register feeds a figure
  that reaches a client. If you cannot verify a number, leave it `null` and say so in `notes`.
- If you cannot decide, leave `decision` as `null`, write what is missing into the candidate
  notes, and finish. **Do not stop to ask.**

## Done

The row has candidates, a decision, a licence, an annual figure and evidence, and the gate reports
nothing about it:

```bash
cd C:/wamp64/www/mizzey/platform && python -m discovery.check | grep "plugin"
```

That must print nothing.

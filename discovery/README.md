# discovery

The dataset, gate and generators for the discovery and validation phase. Read
`../docs/discovery/2026-09-08-discovery-phase-design.md` first.

## Commands

    python -m unittest discover -s discovery/tests -t .   run the tests
    python -m discovery.check                             the gate
    python -m discovery.run_probes [P-001 ...]            run probes
    python -m discovery.gen_epic_dossiers                 27 dossiers
    python -m discovery.gen_validation_report             the report
    python -m discovery.gen_plugin_register               the register
    python -m discovery.gen_journey_docs                  one per journey

## Rules

`scripts/stories.json` is generated from the Functional Specification. **Never edit it.**
All new data goes in `data/`, keyed by story id.

`generated/` is output. **Never edit it.** Correct the dataset and regenerate.

The gate must pass before any stage gate in the design, section 7.

## The datasets

| File | One row per |
|---|---|
| `data/probes.json` | Empirical test |
| `data/verdicts.json` | Story |
| `data/plugins.json` | Plugin need |
| `data/journeys.json` | Journey |

## Handing work to an agent

Give it one task type from `tasks/` and one id. The template names every file it may read and
the single file it may write, so parallel agents cannot collide and none goes exploring.

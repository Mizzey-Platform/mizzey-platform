# Task type: WIREFRAME

You are building the low fidelity screens for one journey. You will be given a journey id.

## Read

- `CLAUDE.md` at the project root
- `docs/discovery/2026-09-08-discovery-phase-design.md`, section 9
- The generated journey document at `discovery/generated/journey-<id>.md`
- The stories that journey names, in `scripts/stories.json`

Read nothing else. Never read anything under `_archive`.

## Write

- HTML and CSS screens under `C:/wamp64/www/mizzey/design/01-wireframes/<journey id>/`
- Nothing in the platform repo. Wireframes are not data

## What these are for

Validating that the scenario holds as a sequence of screens, before anything is built. They
are structural. Boxes, labels, real copy where copy carries meaning, and nothing else.

**Do not apply visual identity.** The client's brand identity has not arrived. Applying a
provisional one produces work that is thrown away and, worse, invites approval of something
that is not the design.

The contracted deliverable is working HTML and CSS with design tokens, not a design-tool
source file. These wireframes are the first step toward that, so write real HTML and CSS,
not images.

## Arabic

Every screen is checked right to left. Direction-carrying icons mirror. This is US-01-01, it
is 13 points, and it touches every screen in the build. A wireframe that only works in
English has validated nothing.

## Rules

- One screen per journey step that needs one. Reuse a screen across steps where the journey
  reuses it, and say so.
- Every screen names the story ids it serves, in an HTML comment at the top.
- If a step cannot be drawn because the journey is ambiguous, note it in the journey row's
  `gaps` and draw the rest. **Do not stop to ask.**

## Done

Every step of the journey that needs a screen has one, both directions render, and each file
names its stories.

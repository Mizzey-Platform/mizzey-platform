# Task type: WIREFRAME

> **RETIRED. Option C, historical reference only (D-14, 6 October 2026).** Do not follow this file. Option C was
> never signed, and `scripts/stories.json` is not scope. Design work is governed by
> [`design/README.md`](../../design/README.md): the signed Option B scope and `docs/scope/backlog-ownership.json`
> are the authority, and no wireframe is drawn until the owner has reviewed `design/coverage.md`.

You are building the low fidelity screens for one journey. You will be given a journey id.

## Where you are

**Every path below is relative to `C:\wamp64\www\mizzey`.** That directory contains `platform/`,
which is the git repo, alongside `design/`, `final docs/` and `_archive/`. Wireframes go in
`design/`, which is **not** in the repo. Run python commands from `C:\wamp64\www\mizzey\platform`.

## Read

- `CLAUDE.md`
- `platform/docs/discovery/2026-09-08-discovery-phase-design.md`, section 9
- The generated journey document at `platform/discovery/generated/journey-<id>.md`
- The stories that journey names, in `platform/scripts/stories.json`

Read nothing else. Never read anything under `_archive/`.

## Write

- HTML and CSS screens under `design/01-wireframes/<journey id>/`
- Nothing in the platform repo. Wireframes are not data

## What these are for

Validating that the scenario holds as a sequence of screens, before anything is built. They are
structural. Boxes, labels, real copy where copy carries meaning, and nothing else.

**Do not apply visual identity.** The client's brand identity has not arrived. Applying a
provisional one produces work that is thrown away and, worse, invites approval of something that
is not the design.

The contracted deliverable is working HTML and CSS with design tokens, not a design-tool source
file. These wireframes are the first step toward that, so write real HTML and CSS, not images.

## Arabic

Every screen is checked right to left. Direction-carrying icons mirror. This is US-01-01, it is 13
points, and it touches every screen in the build. A wireframe that only works in English has
validated nothing.

## Rules

- Every screen serves a step of the journey, and every step traces to a story id, and every story
  id traces to Annex A. **A screen for something with no Annex A id is out of scope.** It is not a
  wireframe, it is a change request. Note it in the journey row's `gaps` and do not draw it.
- One screen per journey step that needs one. Reuse a screen across steps where the journey reuses
  it, and say so.
- Every screen names the story ids it serves, in an HTML comment on the first line, in the form
  `<!-- stories: US-05-10, US-05-11 -->`.
- If a step cannot be drawn because the journey is ambiguous, note it in the journey row's `gaps`
  and draw the rest. **Do not stop to ask.**

## Done

Every step of the journey that needs a screen has one, each names its stories, and each renders in
both directions. Check the first two mechanically:

```bash
cd "C:/wamp64/www/mizzey/design/01-wireframes/<journey id>"
grep -L "<!-- stories:" *.html
grep -L 'dir="rtl"' *.html
```

Both must print nothing. `grep -L` lists files **missing** the pattern, so any filename printed is
a screen that is not finished.

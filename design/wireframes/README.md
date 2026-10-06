# Wireframes: the artefact format

**No wireframe exists yet.** This folder holds the format, the production manifest and the workflow. The first
wireframes are the calibration set, drawn when the owner says so.

How the work is sequenced and reviewed is in [workflow.md](workflow.md). What is tracked is in
[manifest.json](manifest.json).

## What a wireframe is

A low-fidelity wireframe here is a **standalone static HTML and CSS file**, opened in a real browser so the
structural UX can be reviewed as it would be used.

**These files are design prototypes only.** They are not:

- production frontend code;
- WordPress templates;
- theme code;
- WooCommerce overrides;
- the contracted working HTML and CSS of PRE-03b;
- reusable application components.

Nothing here is copied into `mizzey-theme/` or `mizzey-site/`, and nothing there is copied here. When the design
reaches the contracted working HTML and CSS stage, that work is built for the purpose, from the approved design.

## Where things go

```text
design/wireframes/
  README.md            this file
  workflow.md          the calibration set, the review model, the batches, the sequence
  manifest.json        design-production status, one entry per surface
  _shared/
    wireframe.css      the small neutral structural system every wireframe links
  s1/                  first-release surfaces
    calibration/<a|b|c>/<frame id>.<en|ar>.html
    <batch id>/<surface id>.<en|ar>.html
  s2/                  contracted second-release surfaces, in their own later pass
    <batch id>/<surface id>.<en|ar>.html
```

One file is one language. A surface has an English file and, where Arabic applies, an Arabic file. A file may
show the mobile and the desktop frame of its surface, and several of its states, on one page.

## Rules

| Rule | Why |
|---|---|
| HTML and CSS only | A wireframe is looked at, not run |
| No JavaScript, in any form: no `script`, no inline handler, no `javascript:` address | It is not an application. Interaction is shown as states and described in notes |
| No PHP, no server or template tag | It is not a template |
| No WordPress or WooCommerce integration | It is not theme code |
| No framework or library: no Tailwind, Bootstrap, shadcn, GSAP, Swiper or any other | No library is chosen before the design (`../direction.md`) |
| Nothing fetched from outside: no absolute address, `@import`, `url()`, image, web font, `iframe` | A wireframe is self-contained and carries no production asset |
| Greys only | No brand colour exists yet (OD-01) |
| A system typeface only, no `@font-face` | No final typography exists yet |
| No logo and no final imagery | A labelled box stands in. Placeholder product imagery stays visually neutral |
| Realistic content lengths and a realistic ecommerce structure | The structure is what is being settled, in both languages |

`tools/design_wireframes.py` enforces every row above that a tool can see, and the path policy accepts HTML and
CSS under `design/wireframes/` only. Script, PHP and every other file type are refused anywhere under `design/`.

## The shared stylesheet

`_shared/wireframe.css` is a small neutral structural system, and nothing more:

- viewport frames, for mobile and desktop;
- a spacing scale;
- a simple type hierarchy;
- borders;
- placeholder surfaces, for imagery and for content not yet written;
- grids;
- basic responsive layout;
- right to left, through logical properties;
- a visible focus state;
- the annotation blocks described below.

The stylesheet itself is added in the pull request that follows the path policy for this format, since a pull
request may not bring in a file type it also authorises.

**It is not the design system and must not become one.** It has no component library, no brand token and no
final value. A direction or a surface may add its own small stylesheet beside its files. The checker caps the size
of any stylesheet here, so a library cannot arrive as one.

## What every wireframe file declares

```html
<!doctype html>
<html lang="en" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="wf-surfaces" content="sf-cart sf-free-shipping-progress">
  <meta name="wf-viewport" content="mobile desktop">
  <meta name="wf-direction" content="a">
  <title>Cart</title>
  <link rel="stylesheet" href="../../_shared/wireframe.css">
</head>
```

| Declaration | Meaning | Checked |
|---|---|---|
| `lang` and `dir` | `en` with `ltr`, or `ar` with `rtl`. The file name ends `.en.html` or `.ar.html` to match | Yes |
| `wf-surfaces` | The canonical surface ids the file shows. Every id exists in `../inventory/surfaces.json` | Yes |
| `wf-viewport` | `mobile`, `desktop`, or `mobile desktop` | Yes |
| `wf-direction` | For a calibration frame only: `a`, `b` or `c`, matching its folder | Yes |
| The stylesheet link | Resolves to a CSS file inside `design/wireframes/` | Yes |

Every surface keeps its canonical id. A wireframe never introduces a surface: if something seems to be missing
from the inventory, it is raised, not drawn.

## Annotations

A wireframe carries its reasoning on the page, in notes that are plainly not part of the interface.

| Note | Class | What it records |
|---|---|---|
| State | `wf-state` | Which state of which surface a frame shows, by the state's name in the inventory |
| Option | `wf-option` | Which interaction option a frame exercises, which candidate it shows, and whether that is the working baseline |
| Recommendation | `wf-recommend` | Where alternatives are shown, which one the designer recommends and why |
| Placeholder | `wf-placeholder` | Which open input a value stands in for, by its id in `../inventory/placeholders.json` |
| Motion | `wf-motion` | Motion that matters, described and not built |

### Motion is annotated, not implemented

No animation is built at low fidelity. Where motion matters to the experience, the wireframe says what is
intended:

```html
<aside class="wf-note wf-motion"
       data-trigger="The customer presses Add to Cart"
       data-effect="The mini-cart appears from the inline-end edge and the new line is marked"
       data-role="essential"
       data-reduced-motion="The mini-cart appears in place with no movement">
  Add to Cart gives immediate feedback.
</aside>
```

| Field | Content |
|---|---|
| `data-trigger` | What starts it |
| `data-effect` | The intended effect |
| `data-role` | `essential`, when the customer needs it to understand what happened, or `decorative` |
| `data-reduced-motion` | What happens when the visitor asks for reduced motion |

All four are required, and the checker refuses a motion note without them. Final motion is specified after the
high-fidelity design.

## Languages, viewports and states

- **English left to right is the canonical design. Arabic right to left is the same design mirrored**, never a
  second layout. The Arabic file is the same structure with `dir="rtl"`, Arabic placeholder content of realistic
  length, and the mirroring rules of its surface.
- **Mobile first**, then desktop. Tablet is described where it differs.
- **Every state its surface lists is drawn**, by the state's name. The manifest records which are covered, and a
  surface is not `reviewed` until all are.

## Checks

```bash
python tools/design_wireframes.py
python tools/design_briefs.py
python -m unittest tools.tests.test_design_wireframes tools.tests.test_design_briefs
```

The first checks the manifest against the inventory and every HTML and CSS file here against the rules above.
The second checks that the committed briefs are what the generator would write. What no tool can check is
whether a wireframe is good, and whether it draws only what its surfaces list: that is the review.

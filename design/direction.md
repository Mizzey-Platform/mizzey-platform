# Design direction: the experience target

Set by the owner on 6 October 2026 (D-14). This says what quality of experience the design aims at, before any
wireframe is drawn.

**It is not the brand identity.** It chooses no colour, no typeface, no logo treatment and no imagery. Those
arrive from the client (OD-01). It is also not scope: it adds no capability and changes no register row. It
guides how the contracted capabilities are composed and how they feel to use.

## The target

Mizzey should feel like a contemporary, polished, modern ecommerce product.

| The experience is | Meaning |
|---|---|
| Clean, but not empty or visually poor | Restraint with substance. Space is used on purpose, not left over |
| Simple to understand, but not generic | A first-time visitor knows what to do. It does not look like every other store |
| Modern rather than traditional ecommerce | Current patterns for cards, drawers, overlays and navigation |
| Premium and polished, without luxury for its own sake | Care in detail. Nothing ornamental that slows a purchase |
| Visually engaging and catchy, with strong usability | It holds attention and never makes shopping harder |
| Product-led | The product and its imagery carry the page. The interface steps back |
| Clear in hierarchy | One thing is most important on each view, and it is obvious which |
| Generous but purposeful with whitespace | Room to breathe, without pushing the product or the action out of reach |
| Polished on mobile | Mobile is the first design, not a reduced desktop |

## Motion and feedback

Motion is part of the quality, used with taste.

- Responsive feedback for every action: the customer sees that a press, an add or a change registered.
- Subtle hover, focus and press states.
- Smooth transitions for drawers, dialogs, the cart and the filters.
- Skeleton and loading transitions where they help.
- Optional scroll-based or reveal motion, where it adds value.
- No animation that costs performance, accessibility or usability. Motion is reduced when the visitor asks for
  reduced motion (NFR-11), and it never delays a purchase.

## What it is not

| Not this | Because |
|---|---|
| An old-fashioned WooCommerce or store-template appearance | The platform's defaults are the baseline to build on, not the look to ship |
| A production interface that still looks like a wireframe | Neutral is a stage, not the result |
| Minimalism so severe it feels cheap or unfinished | Clean is not empty |
| A generic corporate website | It is a store, and it should feel like one |
| An animation-heavy showcase that makes shopping harder | The purpose is buying, with pleasure, not watching |

## How this applies at each stage

| Stage | What this direction governs |
|---|---|
| Low-fidelity wireframes | Composition, hierarchy, flow and interaction patterns. They stay neutral: greys, one system typeface per script, no brand. A neutral wireframe is still composed with this target in mind |
| High-fidelity design | Everything above, expressed through the brand identity once the client supplies it |
| Working HTML and CSS | The same, as built, with motion and feedback real |

## Components and libraries come after the design

**No implementation library is chosen here, and none is a product constraint.** Design determines the component
system first. Implementation libraries are selected later, to support that design.

Established professional component and interaction patterns, and lightweight libraries, may be used where they
fit. **Mizzey must not look like a default library theme.**

Categories that may be evaluated later, none of them selected: accessible component patterns, slider and gallery
libraries, lightweight motion libraries, icon systems, and CSS or design-token utilities.

Selection happens after the structural UX direction is understood, and weighs:

- compatibility with WordPress and CoreX;
- compatibility with WooCommerce;
- accessibility;
- bundle size and performance;
- right to left;
- maintainability;
- whether the browser, CSS, CoreX or WooCommerce already solves it natively.

## What stays out of reach of this document

The signed scope decides what the store does. This document never widens it. A proposal that gives the customer a
new capability or the business a new behaviour needs scope authority, however well it fits the target. See
`README.md`, "Presentation is design. Capability is scope."

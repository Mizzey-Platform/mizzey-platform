# Feature Specification: third-party admin styles kept to their own screens

**Feature Branch**: `063-admin-plugin-styles`

**Created**: 2026-11-05

**Status**: Draft

**Work type**: requirement

**Input**: Mustafa, 5 November 2026, on staging's order list: "the design of this page is broken, something is
overriding it". Measured: Paymob's `admin.css` turns the status filter row (All, Pending, Confirmed...) into a card
with pills; Bosta's `main.css`, loaded on every admin screen, makes every page title 30px, every bulk-action button a
large green one, every list screen's filters stack one per row (`.tablenav .actions` at full width), and resizes every
table header and the search box. WooCommerce's own toolbar rule makes our "Cash collected, not remitted" box 32px tall.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| ADM-159 | P1 | S1 | Administrative interface in English, presented consistently across the standard store screens and the screens built in this engagement |

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| PAY-13 | P1 | Paymob's official plugin: its screens and its panel on a single order keep its sheet |
| SHIP-08 | P1-L | Bosta's official plugin: its pages, its shipping settings and the order screens keep its sheet |
| SHIP-16 | P1-L | Our cash-on-delivery filter on the order list (specs/041-bosta) |

## Open contract items

- None. Whether Paymob stays at all waits on the payment gateway choice (I-06); this slice changes nothing in it.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | The order list shows WordPress's own status filter row, title, bulk actions, filters on one row and table header | ADM-159 | final |
| AC-2 | Bosta's buttons and filters on the order list keep working and are shown in WordPress's button and field style | ADM-159 | final |
| AC-3 | The "Cash collected, not remitted" filter is a checkbox of normal size | ADM-159 | final |
| AC-4 | WordPress's other screens (dashboard, posts, products and the rest) load neither plugin's admin sheet | ADM-159 | final |
| AC-5 | Each plugin's own screens keep its sheet: Paymob's settings, Bosta's pages and shipping settings, and the single order for both | ADM-159 | final |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The order list looks like the rest of the admin (Priority: High)

1. **Given** the order list, **When** staff open it, **Then** the status filters, title, bulk actions and filters look
   as on every other list, and Bosta's buttons sit on one row in the same style (AC-1, AC-2, AC-3).
2. **Given** any other list (posts, products, users), **When** it opens, **Then** nothing of Bosta's or Paymob's
   styling is on it (AC-4).

### Edge Cases

- Bosta's own settings live inside WooCommerce's settings (shipping, section `bosta`): they keep Bosta's sheet.
- Bosta's order preview (its timeline) is on the order list: Bosta's sheet stays there.
- A plugin update that renames a handle leaves its sheet where the plugin puts it: t103 then fails, by name.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Removing a plugin's sheet from a screen | `wp_dequeue_style()` after the plugin enqueues it | Native, used |
| WordPress's admin colour on restored buttons | `--wp-admin-theme-color` | Native, used |
| Keeping a plugin's sheet to its screens | Neither plugin offers it | Gap: `PluginAdminStyles` |

## Requirements

### Functional Requirements

- **FR-001**: Paymob's admin sheet MUST be removed from the order list only (AC-1, AC-5).
- **FR-002**: Bosta's admin sheets MUST be removed everywhere except its pages, its shipping settings and the order
  screens (AC-4, AC-5).
- **FR-003**: On the order screens our sheet MUST print after Bosta's and restore WordPress's title, bulk actions,
  filter row, table header and search box, and set Bosta's controls in WordPress's style (AC-1, AC-2).
- **FR-004**: The cash-on-delivery filter MUST be marked so its checkbox keeps a checkbox's size (AC-3).
- **FR-005**: No third-party plugin file may be edited.

### Key Entities

- None.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- The designed admin look, if any, is PRE-03b's; this restores WordPress's own.

## Success Criteria

- **SC-001**: Staff see one consistent admin; no screen carries another plugin's styling.

## Delivery stage

Contractual stage: S1. Engineering timing: 5 November 2026.

## Assumptions

- The plugins keep their style handles (`paymob-admin-css`; `myCSS`, `pickupsCSS`, `flexshipCSS`, `fulfillmentCSS`).

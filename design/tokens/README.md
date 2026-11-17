# Tokens

`tokens.json` holds the Mizzey design tokens, built from the client's brand identity, received on 17 November 2026
(OD-01). The format is the W3C Design Tokens format (DTCG): every token has a `$type` and a `$value`, and a role
points at a palette value with `{path}`. No CSS, token or component library is chosen. The theme turns these values
into CSS custom properties when the contracted HTML and CSS stage starts (`--mizzey-*`, see `mizzey-theme/theme.json`).

**Written approval of the brand direction (CR-02) is still open.** It blocks final acceptance, not this work
(M-10). Until the client approves the interface design, every value here can change.

## The brand

| Part | Value |
|---|---|
| Logo | Option 01 of the brand proposal: the wordmark with the double Z and the wave, and a ZZ mark for small sizes |
| Colours | Royal Indigo #263B73, Persimmon #E65A42, Cream #F3F1E9. Mizzey Ink #1E1E1E is in the proposal palette |
| Type | Inter for English and Tajawal for Arabic: Black for headlines, Medium for sub-headers, Light for body |
| Voice | The Savvy Companion: smart, playful, plain, never overdone |

## What each status means

Every token carries `$extensions.mizzey.status`.

| Status | Meaning |
|---|---|
| `brand` | A value from the brand pack, unchanged |
| `derived` | Computed from a brand value: a tint, a shade, or the lightest step that still meets a contrast target |
| `carried` | A structural value already used by the frozen low-fidelity wireframes (spacing, focus ring, breakpoints) |
| `proposed` | A starting value for the high-fidelity design. Design confirms or changes it (D-15) |

All role assignments (`color.role.*`, `type.*`) are `proposed`. They are the brand applied in the most direct way,
under the contrast rules below, not a design direction.

## Contrast

Every text colour meets WCAG 2.2 AA (4.5:1) on both light surfaces, White and Cream, and every control boundary
and the focus ring meet 3:1 (1.4.11). Checked on 18 November 2026:

| Pair | Ratio |
|---|---|
| Text (Ink) on Cream / White | 14.74 / 16.67 |
| Muted text on Cream / White | 4.93 / 5.57 |
| Cream on Royal Indigo (header, primary button) | 9.50 |
| Ink on Persimmon (badge text) | 4.69 |
| Cream on Persimmon (large text only) | 3.14 |
| Text-safe Persimmon #B84835 on Cream / White | 4.62 / 5.23 |
| Success, warning, danger on Cream / White / their own tint | 4.6 or more |
| Control border on Cream / White | 3.27 / 3.69 |
| Focus ring (Persimmon) on Cream / White / Royal Indigo | 3.14 / 3.56 / 3.02 |

Rules that follow from the brand colours:

- **Persimmon never carries small Cream or White text.** Cream on Persimmon is 3.14:1, so it is for text of 24px and
  up (18.66px bold). Smaller text on Persimmon uses Ink.
- **Persimmon as text on a light surface** uses `color.persimmon.700` (#B84835), not the brand Persimmon (3.14:1).
- **The logo accent #FF6A4A is logo artwork only.** It is not a UI colour.
- **Danger is crimson (#CD2949)**, kept apart from Persimmon in hue, and always comes with an icon and words, never
  colour alone.

## Typography

Latin roles keep the wireframe sizes. Arabic roles are sized one step larger with taller lines, because Tajawal
sets smaller than Inter at the same size (component `fn-type-roles`). Prices, quantities and order numbers use Inter
with tabular figures in both languages (Western digits, DQ-01, provisional). Captions use Medium, not Light.

## Open points

1. Which orange is the brand orange on screen: the sheet says #E65A42, the logo files use #FF6A4A.
2. Whether Mizzey Ink stays in the palette. It is not on the final sheet; `color.role.text` uses it.
3. Body text in Light (300) is the brand rule. It reads thin at 16px, more so in Tajawal; design to confirm.
4. Whether "Good Finds, Made Easy." is the final tagline, and its Arabic version.

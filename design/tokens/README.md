# Tokens

`tokens.json` holds the Mizzey design tokens, built from the client's brand identity, received on 17 November 2026
(OD-01). The format is the W3C Design Tokens format (DTCG): every token has a `$type` and a `$value`, and a role
points at a palette value with `{path}`. No CSS, token or component library is chosen. The theme turns these values
into CSS custom properties when the contracted HTML and CSS stage starts (`--mizzey-*`, see `mizzey-theme/theme.json`).

The client approved the brand identity before it was handed over, and the owner's rulings of 18 November 2026
settle the open points (D-34). The interface design built on these tokens still goes to the client for approval
(PRE-03b).

## The brand

| Part | Value |
|---|---|
| Logo | Option 01 of the brand proposal: the wordmark with the double Z and the wave, and a ZZ mark for small sizes. Used exactly as supplied, in its own colours; never recoloured (D-34) |
| Colours | Royal Indigo #263B73, Persimmon #FF6A4A, Cream #F3F1E9, Mizzey Ink #1E1E1E |
| Type | Inter for English and Tajawal for Arabic: Black for headlines, Medium for sub-headers, Regular for body text on screen, Light for large running text (D-34) |
| Voice | The Savvy Companion: smart, playful, plain, never overdone |

## What each status means

Every token carries `$extensions.mizzey.status`.

| Status | Meaning |
|---|---|
| `brand` | A value from the brand pack, unchanged |
| `decided` | An owner decision recorded in `DECISIONS.md` |
| `derived` | Computed from a brand value: a tint, a shade, or the lightest step that still meets a contrast target |
| `carried` | A structural value already used by the frozen low-fidelity wireframes (spacing, focus ring, breakpoints) |
| `proposed` | A starting value for the high-fidelity design. Design confirms or changes it (D-15) |

The role assignments (`color.role.*`, `type.*`) are `proposed`, except the body and lead type weights, which are
`decided`. They are the brand applied in the most direct way, under the contrast rules below, not a design direction.

## Contrast

Every text colour meets WCAG 2.2 AA (4.5:1) on both light surfaces, White and Cream, and every control boundary
and the focus ring meet 3:1 (1.4.11). Checked on 18 November 2026:

| Pair | Ratio |
|---|---|
| Text (Ink) on Cream / White | 14.74 / 16.67 |
| Muted text on Cream / White | 4.93 / 5.57 |
| Cream on Royal Indigo (header, primary button) | 9.50 |
| Ink on Persimmon (all text on Persimmon) | 5.88 |
| Persimmon #FF6A4A on White / Cream (fails, fills only) | 2.83 / 2.51 |
| Icon Persimmon #EE5A3B on White / Cream / Royal Indigo | 3.41 / 3.01 / 3.15 |
| Text-safe Persimmon #C63D1F on Cream / White | 4.54 / 5.14 |
| Success, warning, danger on Cream / White / their own tint | 4.6 or more |
| Control border on Cream / White | 3.27 / 3.69 |
| Focus ring (#EE5A3B) on Cream / White / Royal Indigo | 3.01 / 3.41 / 3.15 |

Rules that follow from the brand colours:

- **Text on Persimmon is always Ink.** White is 2.83:1 and Cream 2.51:1 on #FF6A4A, below even the 3:1 for large
  text, so no heading size makes them pass.
- **Persimmon on a light surface is a fill, not a line.** As text it uses `color.persimmon.700` (#C63D1F); as an
  icon, border or focus ring it uses `color.persimmon.600` (#EE5A3B). The brand #FF6A4A is 2.83:1 on White.
- **The interface orange is Persimmon #FF6A4A**, the same orange as the logo's i-dot and wave (owner's correction,
  18 Nov 2026, D-34). The logo is placed as supplied and never recoloured (D-34).
- **Danger is crimson (#CD2949)**, kept apart from Persimmon in hue, and always comes with an icon and words, never
  colour alone.

## Typography

Latin roles keep the wireframe sizes. Arabic roles are sized one step larger with taller lines, because Tajawal
sets smaller than Inter at the same size (component `fn-type-roles`). Prices, quantities and order numbers use Inter
with tabular figures in both languages (Western digits, DQ-01, provisional).

Body text is Regular (400), not the brand's Light (D-34): Light is a thin weight that reads poorly at 16px on a
phone, and Tajawal Light most of all, and body text carries product details, prices and checkout terms. The brand
Light keeps its place in large running text: the `lead` role (20px and up) for intro and editorial paragraphs.
Captions and labels use Medium.

## Open points

1. Whether "Good Finds, Made Easy." is the final tagline, and its Arabic version.

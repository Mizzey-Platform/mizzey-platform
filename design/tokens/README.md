# Tokens

`tokens.json` holds the Mizzey design tokens, built from the client's brand identity, received on 17 November 2026
(OD-01), and since 25 November 2026 carrying exactly what the closed Stage 2 design draws: Concept 1 at version
1795564351-dde6 (D-36), its System board and the workspace tokens behind it, the board winning where the two
differed. The format is the W3C Design Tokens format (DTCG): every token has a `$type` and a `$value`, and a role
points at a palette value with `{path}`. `tools/theme_tokens.py` turns them into the theme's `--mizzey-*` custom
properties and `theme.json` settings; a token change flows through by running it again.

The client approved the brand identity before it was handed over, and the owner's rulings of 18 November 2026
settle the open points (D-34, D-35). The interface design built on these tokens still goes to the client for
approval (PRE-03b).

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
| `decided` | An owner decision recorded in `DECISIONS.md`, the closed design (D-36) included |
| `derived` | Computed from a brand value: a tint, a shade, or the lightest step that still meets a contrast target |
| `carried` | A structural value already used by the frozen low-fidelity wireframes |
| `proposed` | A starting value to confirm or change (D-15) |

Two more extensions: `fluid` (`[at 390, at 1440]` in rem, or one pair per typography part) becomes a `clamp()`
between the two design widths, and `keepWhenReduced` marks the durations that stay under reduced motion.

## What the tokens hold

| Group | Content |
|---|---|
| `color` | The brand five; the Indigo family (50, 100, 200, 300, 500, 700); the page glows; muted text, hairline and scrim; the status colours and their tints; the roles |
| `gradient` | Deep (Indigo 700, Royal Indigo, Indigo 500 at 135 degrees) and Air (the two glows) |
| `font` | The three stacks and the weights 300, 400, 500, 700 (prices only) and 900 |
| `type` | Every role of the System board, in Latin and Arabic: display, h1, h2, h3, title, lead, body, body-strong, button, input, label, caption, badge; and price, price-lg and old-price, which are Inter in both languages |
| `space` | The 8-point scale, 4 to 72, and the fluid section space, 40 at 390 to 72 at 1440 |
| `radius` | xs 8, sm 12, field 16, card 20, tile 24, sheet 28, drawer 28, pill |
| `elevation` | L1 to L4, Indigo tinted, and the primary button glow |
| `focus` | 2px ring, 2px offset |
| `motion` | Durations 1 to 10 (80 to 1100 ms) and the reduced form, the easings and springs, distances, press scales, stagger |
| `layout` | Columns, gutters and margins per width, content width 1272, reading width 840, measures, bars |
| `control` | Button, field, search, chip, option, stepper, badge, target, header and strip sizes |
| `breakpoint` | Phone 320, tablet 600, desktop 1024, wide 1440 |

## Contrast

Every text colour meets WCAG 2.2 AA (4.5:1) on the page ground, White, and every control boundary and the focus ring
meet 3:1 (1.4.11). `tools/tests/test_design_inventory.py` checks the role pairs. Checked on 25 November 2026:

| Pair | Ratio |
|---|---|
| Text (Ink) on White / on the Tint | 16.67 / 14.76 |
| Muted text #5B6480 on White / on the Tint | 5.87 / 5.20 |
| White on Royal Indigo (strip, footer, primary button) | 10.74 |
| White on Indigo 700 (primary hover) / on Indigo 500 (end of Deep) | 14.35 / 7.03 |
| Ink on Persimmon (all text on Persimmon) | 5.88 |
| Persimmon #FF6A4A on White (fails, fills only) / White on Persimmon (fails) | 2.83 / 2.83 |
| Sale price and highlight text, Royal Indigo, on White / on the Tint | 10.74 / 9.51 |
| Success, warning, danger on White | 5.25 / 5.26 / 5.24 |
| Success, warning on their own tint | 4.60 / 4.63 |
| Danger on its own tint (icon and border only; the words stay Ink) | 4.48 |
| Field and checkbox border, Indigo 300, on White | 3.72 |
| Focus ring, Royal Indigo, on White | 10.74 |
| Focus ring, White, on Royal Indigo | 10.74 |

Rules that follow from the brand colours:

- **The page ground is White.** Cream is the brand light ground and is never a page, section or card ground.
- **Text on Persimmon is always Ink.** White and Cream fail on #FF6A4A, so no heading size makes them pass.
- **There is one orange, and it is a fill in fixed places only** (D-35): the logo (and its i-dot as the phone tab bar
  mark), the cart count, the Offers tag, sale badges, the slider's active dot and banner images. It is never text,
  never an icon or line on a light surface, and never reads as a warning. No darker orange steps exist. Sale prices
  and highlight words are Royal Indigo (`color.role.accent-text`); the focus ring is Royal Indigo on light surfaces
  and White on Indigo (`focus`, `focus-inverse`).
- **The interface orange is Persimmon #FF6A4A**, the same orange as the logo's i-dot and wave (owner's correction,
  18 Nov 2026, D-34). The logo is placed as supplied and never recoloured (D-34).
- **Danger is crimson (#CD2949)**, kept apart from Persimmon in hue: the error icon and field border only, with the
  words in Ink, never colour alone.

## Typography

One fluid scale on a base of 16: a minor third (1.2) at 390 and a major third (1.25) at 1440, each heading one step
lower on the phone. Every Arabic role is larger and taller than its English one, with letter spacing 0 on every
Arabic role. Prices, quantities and order numbers use Inter with tabular figures in both languages (Western digits,
DQ-01, provisional). The full rules are on the System board and in the design foundations.

Body text is Regular (400), not the brand's Light (D-34): Light reads poorly at 16px on a phone, Tajawal Light most
of all. The brand Light keeps its place in the `lead` role only. Weight 700 is used for prices only, in Inter.

## Open points

1. Whether "Good Finds, Made Easy." is the final tagline, and its Arabic version.

"""The base components of slice 1 exist in the theme, in every state, on the token layer only (spec 066, slice 1).

Ships with the theme (the slice pull request). `mizzey-theme/assets/css/components.css` holds the components
C1-Components, C1-Notices and C1-Motion-Playground draw: buttons, fields and the select, checkbox, radio and
switch, chips, badges, counts and the two card tags, notices and the two strips, the i-dot loader, skeletons and
the breathing ZZ, empty states, pagination, drawers, bottom sheets, dialogs and the popover, steps, tooltips and
tabs. This check fails when:

- a component class is missing, or a component that can be pressed lacks a hover, pressed, keyboard-focus or
  disabled form, or the button lacks its busy and done forms;
- press feedback is slower than 100 ms (it must use `--mizzey-motion-duration-1`, 80 ms);
- a transition or animation does not take its time and curve from the motion tokens, or a moving component has no
  reduced-motion form;
- the side drawers do not follow the drawer rule (square on the edge they come from, 28 on the two corners facing
  the page, written with logical corners so Arabic mirrors them);
- the behaviour script uses anything but the WordPress Interactivity API;
- a render helper prints unescaped text, or a decorative part is not hidden from assistive technology.

The literal-value and physical-side rules of `test_theme_tokens.py` already cover this stylesheet.
"""

from __future__ import annotations

import json
import re
import shutil
import subprocess
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
THEME = ROOT / "mizzey-theme"
CSS = THEME / "assets" / "css" / "components.css"
SCRIPT = THEME / "assets" / "js" / "ui.js"
HELPERS = THEME / "inc" / "components.php"

COMPONENTS = {
    "buttons": ["mizzey-button", "mizzey-button--primary", "mizzey-button--secondary", "mizzey-button--text",
                "mizzey-button--large", "mizzey-button--compact", "mizzey-button--toolbar", "mizzey-button--icon",
                "mizzey-button--block", "mizzey-on-brand", "mizzey-button__label", "mizzey-button__tick",
                "mizzey-button-pair"],
    "fields": ["mizzey-field", "mizzey-field__label", "mizzey-field__box", "mizzey-field__control",
               "mizzey-field__line", "mizzey-field__status", "mizzey-field__message", "mizzey-field__helper",
               "mizzey-select", "mizzey-select__button", "mizzey-select__chevron", "mizzey-select__list",
               "mizzey-select__option"],
    "choices": ["mizzey-check", "mizzey-check__box", "mizzey-check__tick", "mizzey-check__count", "mizzey-radio",
                "mizzey-radio__dot", "mizzey-radio__hint", "mizzey-switch", "mizzey-switch__track",
                "mizzey-switch__thumb"],
    "chips and badges": ["mizzey-chip", "mizzey-chip--chosen", "mizzey-chip--applied", "mizzey-chip--category",
                         "mizzey-chip__count", "mizzey-badge", "mizzey-badge--ink", "mizzey-badge--sale",
                         "mizzey-badge--tint", "mizzey-count", "mizzey-corner-tag", "mizzey-sale-tag",
                         "mizzey-gallery-counter"],
    "notices": ["mizzey-notice", "mizzey-notice--success", "mizzey-notice--info", "mizzey-notice--warning",
                "mizzey-notice--error", "mizzey-notice__icon", "mizzey-notice__text", "mizzey-notice__action",
                "mizzey-notice__close", "mizzey-notice__life", "mizzey-strip", "mizzey-sitewide",
                "mizzey-sitewide--warning"],
    "loading": ["mizzey-loader", "mizzey-loader__dot", "mizzey-loader--still", "mizzey-skeleton", "mizzey-well",
                "mizzey-zz"],
    "empty and paging": ["mizzey-empty", "mizzey-empty__disc", "mizzey-empty__title", "mizzey-empty__text",
                         "mizzey-pagination", "mizzey-pagination__item", "mizzey-pagination__gap"],
    "overlays": ["mizzey-overlay", "mizzey-overlay--drawer-end", "mizzey-overlay--drawer-start",
                 "mizzey-overlay--sheet", "mizzey-overlay--dialog", "mizzey-overlay__panel", "mizzey-overlay__header",
                 "mizzey-overlay__title", "mizzey-overlay__body", "mizzey-overlay__footer", "mizzey-overlay__handle",
                 "mizzey-popover"],
    "steps, tooltips, tabs": ["mizzey-steps", "mizzey-steps__step", "mizzey-steps__dot", "mizzey-steps__line",
                              "mizzey-tooltip", "mizzey-tooltip-host", "mizzey-tabs", "mizzey-tabs__list",
                              "mizzey-tabs__tab", "mizzey-tabs__indicator", "mizzey-tabs__panel"],
}
# Selectors every interactive component must carry, by state.
STATES = {
    ".mizzey-button": [":hover", ":active", ":focus-visible", ":disabled", '[aria-busy="true"]', ".is-done"],
    ".mizzey-chip": [":hover", ":active", ":focus-visible"],
    ".mizzey-field__control": [":focus", ":disabled"],
    ".mizzey-select__button": [":focus-visible", '[aria-expanded="true"]'],
    ".mizzey-select__option": [":hover", '[aria-selected="true"]'],
    ".mizzey-check__box": [":checked", ":disabled", ":focus-visible"],
    ".mizzey-radio__dot": [":checked", ":focus-visible"],
    ".mizzey-switch": ['[aria-checked="true"]', ":focus-visible", ":disabled"],
    ".mizzey-pagination__item": [":hover", ":focus-visible", '[aria-current="page"]', '[aria-disabled="true"]'],
    ".mizzey-tabs__tab": [":hover", ":focus-visible", '[aria-selected="true"]'],
    ".mizzey-notice__close": [":hover", ":focus-visible"],
}
MOVING = ["mizzey-button", "mizzey-loader__dot", "mizzey-skeleton", "mizzey-zz", "mizzey-field__line",
          "mizzey-overlay", "mizzey-tooltip", "mizzey-tabs__indicator", "mizzey-notice", "mizzey-switch__thumb",
          "mizzey-select__list"]


def css() -> str:
    text = CSS.read_text(encoding="utf-8") if CSS.exists() else ""
    return re.sub(r"/\*.*?\*/", "", text, flags=re.S)


def rules(text: str) -> list[tuple[str, str]]:
    """(selector, body) for every plain rule, at any nesting depth inside at-rules."""
    return [(m.group(1).strip(), m.group(2)) for m in re.finditer(r"([^{}@;]+)\{([^{}]*)\}", text)]


def reduced_block(text: str) -> str:
    out = []
    for m in re.finditer(r"@media\s*\(prefers-reduced-motion:\s*reduce\)\s*\{", text):
        depth, i = 1, m.end()
        while depth and i < len(text):
            depth += {"{": 1, "}": -1}.get(text[i], 0)
            i += 1
        out.append(text[m.end():i])
    return "\n".join(out)


class EveryComponentIsBuilt(unittest.TestCase):
    def test_every_component_class_exists(self) -> None:
        text = css()
        missing = [f"{group}: .{name}" for group, names in COMPONENTS.items() for name in names
                   if not re.search(r"\." + re.escape(name) + r"(?![\w-])", text)]
        self.assertEqual([], missing, "component classes C1-Components draws that components.css does not have")

    def test_every_interactive_component_has_its_states(self) -> None:
        selectors = " ".join(selector for selector, _ in rules(css()))
        missing = [f"{base}{state}" for base, states in STATES.items() for state in states
                   if not re.search(re.escape(base) + r"[\w-]*(?:\[[^\]]*\]|:[\w-]+(?:\([^)]*\))?|\.[\w-]+)*"
                                    + re.escape(state), selectors)
                   and f"{state} + {base}" not in selectors and f"{state} ~ {base}" not in selectors
                   and not re.search(re.escape(state) + r"\s*[+~>]?\s*" + re.escape(base), selectors)]
        self.assertEqual([], missing, "a state the board draws has no rule")


class Motion(unittest.TestCase):
    def test_press_feedback_is_under_100_ms(self) -> None:
        text = css()
        for selector, body in rules(text):
            if ":active" in selector and re.search(r"transform:(?!\s*none)", body):
                self.assertIn("var(--mizzey-motion-duration-1)", body, selector)
        self.assertTrue(any(":active" in s for s, _ in rules(text)), "no pressed state at all")

    def test_every_transition_and_animation_uses_motion_tokens(self) -> None:
        problems = []
        for selector, body in rules(css()):
            for prop, value in re.findall(r"(transition(?:-duration|-timing-function|-delay)?|animation(?:-duration|-timing-function|-delay)?)\s*:\s*([^;]+)", body):
                if value.strip() in ("none", "0s") or prop.endswith("-delay") and value.strip() == "0s":
                    continue
                if "var(--mizzey-motion-" not in value:
                    problems.append(f"{selector} {{ {prop}: {value.strip()} }}")
        self.assertEqual([], problems, "a transition or animation with its own time or curve")

    def test_every_moving_component_has_a_reduced_motion_form(self) -> None:
        block = reduced_block(css())
        self.assertTrue(block, "components.css has no reduced-motion block")
        missing = [name for name in MOVING if "." + name not in block]
        self.assertEqual([], missing, "moving components with no reduced-motion rule")


class Drawers(unittest.TestCase):
    def body(self, selector: str) -> str:
        found = [body for sel, body in rules(css()) if re.fullmatch(re.escape(selector), sel)]
        self.assertTrue(found, selector)
        return " ".join(found)

    def test_a_drawer_is_square_on_its_edge_and_rounded_towards_the_page(self) -> None:
        end = self.body(".mizzey-overlay--drawer-end .mizzey-overlay__panel")
        start = self.body(".mizzey-overlay--drawer-start .mizzey-overlay__panel")
        for corner in ("border-start-start-radius", "border-end-start-radius"):
            self.assertIn(f"{corner}: var(--mizzey-radius-drawer)", end)
        for corner in ("border-start-end-radius", "border-end-end-radius"):
            self.assertIn(f"{corner}: var(--mizzey-radius-drawer)", start)
        sheet = self.body(".mizzey-overlay--sheet .mizzey-overlay__panel")
        self.assertIn("border-start-start-radius: var(--mizzey-radius-sheet)", sheet)
        self.assertIn("border-start-end-radius: var(--mizzey-radius-sheet)", sheet)
        self.assertIn("border-radius: var(--mizzey-radius-tile)", self.body(".mizzey-overlay--dialog .mizzey-overlay__panel"))

    def test_the_scrim_is_the_scrim_token(self) -> None:
        self.assertIn("var(--mizzey-color-role-scrim)", self.body(".mizzey-overlay::backdrop"))


class Scope(unittest.TestCase):
    def test_the_box_sizing_rule_reaches_only_the_components(self) -> None:
        text = css()
        self.assertNotIn('[class*="mizzey-"]', text, "a rule on every class containing mizzey- reaches the sections and the page")
        self.assertRegex(text, r":where\([^)]*\.mizzey-button,[^)]*\.mizzey-overlay[^)]*\)", "box-sizing is scoped to the component roots")

    def test_a_drawer_footer_clears_the_safe_area(self) -> None:
        footer = " ".join(body for selector, body in rules(css()) if selector.strip() == ".mizzey-overlay__footer")
        self.assertIn("env(safe-area-inset-bottom)", footer, "the pinned button sits above the home indicator")

    def test_the_page_behind_an_open_modal_does_not_scroll(self) -> None:
        text = css()
        self.assertRegex(text, r":root:has\(\.mizzey-overlay\[open\]\)\s*\{[^}]*overflow:\s*hidden", "scroll lock while a modal is open")
        self.assertNotRegex(text, r"position:\s*fixed[^}]*top:\s*calc\(", "no fixed-body trick, which jumps on iOS")


class Behaviour(unittest.TestCase):
    def test_the_script_uses_only_the_interactivity_api(self) -> None:
        source = SCRIPT.read_text(encoding="utf-8")
        imports = re.findall(r"^\s*import\s.*?from\s+['\"]([^'\"]+)['\"]", source, re.M)
        self.assertEqual(["@wordpress/interactivity"], sorted(set(imports)))
        self.assertIn("store(", source)

    def test_functions_php_registers_the_module_and_the_stylesheet(self) -> None:
        source = (THEME / "functions.php").read_text(encoding="utf-8")
        self.assertIn("wp_register_script_module", source)
        self.assertIn("@wordpress/interactivity", source)
        self.assertIn("'components'", source)


RENDER = r"""
define('ABSPATH', __DIR__);
require $argv[1] . '/tests/wp-shim.php';
require $argv[1] . '/inc/icons.php';
require $argv[1] . '/inc/components.php';
use MizzeySite\Theme\Components as C;
echo json_encode([
  'button' => C\button(['label' => '<b>Add</b> & go', 'variant' => 'primary']),
  'busy' => C\button(['label' => 'Add', 'busy' => true]),
  'icon' => C\button(['icon' => 'heart', 'label' => 'Wishlist', 'variant' => 'icon']),
  'notice' => C\notice(['type' => 'error', 'text' => 'Could <not> save', 'action' => ['label' => 'Try again', 'url' => 'javascript:alert(1)']]),
  'field' => C\field(['id' => 'f1', 'label' => 'Name', 'error' => 'Enter "a" name']),
  'loader' => C\loader(),
  'empty' => C\empty_state(['icon' => 'cart', 'title' => 'Empty', 'text' => 'Nothing']),
  'drawer' => C\overlay(['id' => 'cart', 'kind' => 'drawer-end', 'title' => 'Your cart', 'body' => '<p>x</p>']),
  'controls' => $controls = C\field(['id' => 'c1', 'label' => 'Coupon', 'error' => 'Check it']) . C\check(['name' => 'brand', 'label' => 'Brand', 'checked' => true])
      . C\switch_control(['label' => 'In stock', 'checked' => true]) . C\button(['label' => 'Add', 'busy' => true]),
  'overlay_controls' => C\overlay(['id' => 'f', 'kind' => 'drawer-start', 'title' => 'Filters', 'body' => $controls, 'footer' => $controls]),
  'tabs_controls' => C\tabs('t', [['label' => 'One', 'panel' => $controls]]),
  'shim_kses' => wp_kses_post($controls),
  'counted' => C\button(['label' => 'Filters', 'variant' => 'secondary', 'count' => 3]),
  'counted_one' => C\button(['label' => 'Filters', 'variant' => 'secondary', 'count' => 1]),
  'counted_icon' => C\button(['label' => 'Cart', 'variant' => 'icon', 'icon' => 'cart', 'count' => 120]),
]);
"""


class Helpers(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        php = shutil.which("php")
        if php is None:
            raise AssertionError("the PHP command line is needed to render the helpers")
        done = subprocess.run([php, "-r", RENDER, str(THEME)], capture_output=True, text=True, encoding="utf-8")
        if done.returncode != 0:
            raise AssertionError(done.stderr or done.stdout)
        cls.out = json.loads(done.stdout)

    def test_text_is_escaped(self) -> None:
        self.assertIn("&lt;b&gt;Add&lt;/b&gt; &amp; go", self.out["button"])
        self.assertIn("Could &lt;not&gt; save", self.out["notice"])
        self.assertNotIn("javascript:", self.out["notice"])
        self.assertIn("Enter &quot;a&quot; name", self.out["field"])

    def test_states_and_names_are_announced(self) -> None:
        self.assertIn('aria-busy="true"', self.out["busy"])
        self.assertIn("disabled", self.out["busy"])
        self.assertIn('aria-label="Wishlist"', self.out["icon"])
        self.assertIn('role="alert"', self.out["notice"])
        self.assertIn('aria-invalid="true"', self.out["field"])
        self.assertIn('aria-describedby="f1-message"', self.out["field"])
        self.assertIn('role="img"', self.out["loader"])
        self.assertIn('aria-hidden="true"', self.out["empty"])

    def test_an_overlay_is_a_modal_dialog_the_interactivity_api_drives(self) -> None:
        drawer = self.out["drawer"]
        self.assertIn("<dialog", drawer)
        self.assertIn('aria-labelledby="cart-title"', drawer)
        self.assertIn('data-wp-interactive="mizzey/ui"', drawer)
        self.assertIn('data-wp-on--click="actions.backdrop"', drawer)
        self.assertIn("mizzey-overlay--drawer-end", drawer)


    def test_an_overlay_and_tabs_keep_the_controls_their_caller_passes(self) -> None:
        # The caller escapes what it passes (the helpers' contract); running it through wp_kses_post stripped the
        # very controls a drawer holds. The shim strips as WordPress does, so this fails if a helper filters again.
        for key in ("overlay_controls", "tabs_controls"):
            for needle in ("<input", "<svg", 'aria-checked="true"', 'aria-invalid="true"', 'aria-busy="true"', 'type="checkbox"'):
                self.assertGreater(self.out["controls"].count(needle), 0, needle)
                self.assertGreaterEqual(self.out[key].count(needle), self.out["controls"].count(needle), f"{key}: {needle}")

    def test_a_count_on_a_button_is_read_out(self) -> None:
        # The badge itself is decoration (aria-hidden); its number reaches assistive technology as words.
        self.assertIn('<span class="mizzey-count" aria-hidden="true">3</span>', self.out["counted"])
        self.assertIn('<span class="mizzey-visually-hidden">3 items</span>', self.out["counted"])
        self.assertIn('<span class="mizzey-visually-hidden">1 item</span>', self.out["counted_one"])
        self.assertIn('aria-label="Cart, 120 items"', self.out["counted_icon"])
        self.assertIn(">99+<", self.out["counted_icon"])

    def test_the_shim_strips_as_wordpress_does(self) -> None:
        for gone in ("<input", "<svg", "aria-checked", "aria-invalid", "aria-busy"):
            self.assertNotIn(gone, self.out["shim_kses"])
        self.assertIn('role="alert"', self.out["shim_kses"])
        self.assertIn("<label", self.out["shim_kses"])


if __name__ == "__main__":
    unittest.main()

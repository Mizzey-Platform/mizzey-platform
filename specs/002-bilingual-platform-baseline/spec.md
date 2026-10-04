# Feature Specification: Bilingual platform baseline, English default and Arabic fully delivered right to left

**Feature Branch**: `002-bilingual-platform-baseline`

**Created**: 4 October 2026

**Status**: Draft

**Work type**: requirement

**Input**: PBI #241, the first implementation feature of Option B after the product-cost pilot. It establishes the
platform and theme foundation every later bilingual storefront slice is built on, and nothing more than that.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| FIX-04 | P1 | S1 | "Multilingual storefront: English and Arabic with full LTR/RTL support" |
| FIX-04a | P1 | S1 | "English is the primary and default storefront language. Arabic is the second supported language, fully delivered at launch." |
| NFR-04 | P1 | S1 | Localisation: "English left-to-right as the default locale, Arabic right-to-left fully supported" |
| NFR-04a | P1 | S1 | Localisation: "No hard-coded text anywhere in the storefront interface" |
| NFR-14 | P1 | S1 | Browser Support: "Current Chrome, Safari, Edge, Firefox plus mobile browsers" |

Five rows, all P1, all S1. Nothing else is owed under this spec.

## Context rows (no obligation)

These rows are **not** owed here. They are named so that the boundary is explicit and so nobody builds them under
this feature. Each is a contracted row owned by another slice, except where marked.

| ID | Scope | Why it matters here |
|---|---|---|
| ADM-159 | P1, owned by E-ADM-11 | The administrative interface is English only. This feature must not add an Arabic admin interface. Its Arabic counterpart is P2 and not contracted |
| NFR-04b | P1, owned by E-NFR-1 | Currency, number and date formatting per locale is a separate contracted row. This feature establishes the locale; it does not implement per-locale formatting |
| NFR-03 | P1, owned by #242 | Search-engine optimisation, including hreflang and language-aware URLs as an SEO obligation. The URL **structure** belongs to #242 |
| PRE-03a, PRE-03b | DLV, owned by #244 | Staging and production environments. The browser acceptance of this feature cannot be completed without staging |

## Open contract items

- None. No entry in `docs/scope/open-items.json` touches FIX-04, FIX-04a, NFR-04, NFR-04a or NFR-14. CX-01 and
  PRE-09 do not reach this feature.
- One **client decision** bears on the evidence rather than the criteria: **OD-27**, hosting in writing, which
  settles the web-server rewrite configuration a clean `/ar/` URL needs in a served environment. The criteria below
  do not depend on it; two pieces of evidence do, and they are marked as staging evidence rather than weakened.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | English is the platform's default and primary storefront language. The default language is `en`, the storefront root resolves to English, and no runtime or session state makes a request default to Arabic | FIX-04, FIX-04a | final |
| AC-2 | Arabic is an **active** second storefront language, reachable under its own language prefix while English stays at the root | FIX-04, FIX-04a | final |
| AC-3 | In Arabic the platform resolves to the `ar` locale and reports right to left, and the rendered storefront document carries `lang="ar"` and `dir="rtl"` | FIX-04, NFR-04 | final |
| AC-4 | In English the platform resolves to the `en_US` locale and reports left to right, and the rendered storefront document carries `lang="en-US"` and no right-to-left direction | NFR-04 | final |
| AC-5 | An English record and its Arabic counterpart form one valid translation relationship, with the English record as the source, so later slices address the pair by translation identity and never by SKU or title. **Technical acceptance, not a restatement of the row: see the note below** | FIX-04 | final |
| AC-6 | The same templates render both languages. Reading direction and locale come from the active language, and no separate Arabic template is required at the baseline | FIX-04, NFR-04 | final |
| AC-7 | The storefront interface this engagement builds contains no hard-coded user-facing text: every such string is emitted through a translation function bound to the site text domain | NFR-04a | final |
| AC-8 | An Arabic storefront string resolves from the registered translation source without editing a template per language, so a later slice adds strings rather than language variants | NFR-04a | final |
| AC-9 | The bilingual storefront renders correctly, in both directions, on current Chrome, Safari, Edge and Firefox and on representative mobile browsers | NFR-14 | final |

**AC-9 is final as a criterion and unverified as a fact.** Its evidence is a browser matrix on staging, which does
not exist yet (#244). Nothing in this feature may report AC-9 as verified from the command-line runtime. The three
states stay separate, per constitution M-7.

**AC-5 is technical acceptance, and is labelled as such.** FIX-04 reads "Multilingual storefront: English
and Arabic with full LTR/RTL support" and **does not mention translation identity**. AC-5 is therefore not a
restatement of that row. It is **the engineering and data-integrity acceptance required to deliver the row
safely**, approved on that basis, and the need for it is measured rather than theoretical: the product-cost pilot
found that a WPML duplicate shares its original's SKU, so one SKU addresses two products, and A11 found that a
translation group can be corrupted so an Arabic record detaches from its English source. A bilingual storefront
whose later slices cannot deterministically pair a record with its counterpart cannot deliver FIX-04 safely.
**It is not presented to the client as an additional deliverable** and it adds no behaviour beyond what FIX-04
already obliges.

**AC-9 is the only criterion that needs staging.** The measured facts in the Native coverage table below show the
local Apache has `mod_rewrite` loaded and `AllowOverride All`, so AC-1 to AC-8 are verifiable here by **serving
real requests**, including a real Arabic URL. The requirement is not weakened to suit the runtime: the runtime is
configured to meet it.

## Clarifications

Five questions were open after the first draft. Four are settled here, from the register wording and the measured
configuration. The fifth is a confirmation that does not block the work.

### C-1. Does "fully delivered at launch" mean every Arabic string exists at the baseline?

**No.** FIX-04a contracts Arabic as a **supported language**, fully delivered, not as a body of translated content
delivered by this feature. This feature delivers the capability: Arabic active, right to left, from the same
templates, with the mechanism by which a string becomes translatable. The Arabic text of each later surface
arrives with that surface, and the Arabic text of the catalogue arrives with the migration. Recorded so that
"fully delivered" is not later read as "this feature owed every translation".

### C-2. Does the baseline own the Arabic counterparts of the WooCommerce system pages?

**Yes, as configuration, and only those pages.** The runtime has five pages, all English only: shop, cart,
checkout, my account and a sample page. Without an Arabic counterpart for the first four, an Arabic storefront
cannot resolve its own cart or checkout address at all, so AC-2 would be unmeetable. Creating those translated
page records and the per-language page assignment is platform configuration, which is this feature.

**The boundary**: the page *records and their assignment* are here. The page *content and interface* belong to
their own slices, which are #255 for checkout, #253 for navigation and the remaining storefront PBIs for the
rest. This feature does not design, lay out or populate any of them.

### C-3. Which mechanism makes storefront strings translatable?

Standard WordPress localisation is the primary mechanism, and WPML String Translation covers what it cannot reach.
Native first, per M-4:

| String kind | Mechanism | Why |
|---|---|---|
| A string written in this engagement's theme or site-plugin code | A translation function bound to the `mizzey-site` text domain, with a `.pot` and per-language `.mo` | The platform's own mechanism. It needs no plugin, works in tests, and is what a static check can enforce |
| A string an operator types into a setting or a page | WPML String Translation, which is already active and already holds 29 registered strings across 12 contexts | These never pass through a translation function, so the code mechanism cannot reach them |
| A string shipped by the platform or by WooCommerce | Already translated: the core `ar` pack, `woocommerce-ar.mo` and the Arabic script translations are installed | Nothing to build |

The decision is therefore **not** to pick one mechanism but to bind each kind of string to the one that can carry
it, and to enforce only the first kind in code, because it is the only kind code can enforce.

### C-4. Is the hard-coded-text check part of the obligation or evidence for it?

**Evidence.** NFR-04a obliges the absence of hard-coded text; it does not name a check. AC-7 now states the
obligation alone. The repeatable check stays as FR-007 because constitution M-7 requires a verification someone
else can repeat, and running it in CI on every pull request is S-1, a safeguard that needs approval and is not a
client deliverable.

### C-5. What counts as a "representative mobile browser" for NFR-14? Settled, approved 4 October 2026

NFR-14 reads "Current Chrome, Safari, Edge, Firefox plus mobile browsers" and does not enumerate the mobile
ones. **Mustafa approved the matrix below on 4 October 2026**, so it is the acceptance matrix for NFR-14 and no
longer a proposal. Both reading directions are tested on every row.

| Browser | Reading direction tested | Why it is in the list |
|---|---|---|
| Chrome, current | both | The desktop majority |
| Safari, current | both | The only engine on iOS, and the one that breaks right-to-left layout differently |
| Edge, current | both | Named in the row |
| Firefox, current | both | Named in the row |
| Chrome on Android, current | both | The mobile majority in the market |
| Safari on iOS, current | both | The other half of mobile, and the stricter of the two |

**Explicitly excluded by the same decision**: Internet Explorer, Opera Mini, in-app browsers and any named
device model. None is added unless separately required.

## User Scenarios and Testing *(mandatory)*

### User Story 1 - A customer reaches the storefront in English by default (Priority: High)

A customer opens the storefront without choosing a language. They get English, left to right, every time, on a
clean session and on a session that previously viewed Arabic.

**Why this priority**: FIX-04a makes English the primary language in the contract. If the default can drift to
Arabic through session or cookie state, every later storefront slice inherits an intermittent defect that is
expensive to find.

**Independent Test**: Request the storefront root with no language hint and assert the resolved language, locale
and direction. Repeat after an Arabic request in the same session.

**Acceptance Scenarios**:

1. **Given** a clean session, **When** the storefront root is requested, **Then** the active language is English,
   the locale is `en_US` and the document is left to right (AC-1, AC-4)
2. **Given** a session that has just viewed Arabic, **When** the storefront root is requested again, **Then** it
   is still English (AC-1)

---

### User Story 2 - A customer reads the whole storefront in Arabic, right to left (Priority: High)

A customer switches to Arabic. The storefront serves Arabic under its own language prefix, laid out right to left,
from the same templates the English storefront uses.

**Why this priority**: FIX-04 is a `[key]` row and Arabic is contracted as fully delivered at launch, not as a
partial translation. The platform has to carry the direction so that no later slice has to.

**Independent Test**: Resolve the Arabic language URL, request it, and assert the language, locale, direction and
document attributes. Assert that no Arabic-specific template file was needed.

**Acceptance Scenarios**:

1. **Given** Arabic is active, **When** the Arabic storefront is requested, **Then** the active language is Arabic,
   the locale is `ar`, and the document carries `lang="ar"` and `dir="rtl"` (AC-2, AC-3)
2. **Given** the same template set, **When** both languages are rendered, **Then** neither requires a
   language-specific template (AC-6)

---

### User Story 3 - A later slice adds a storefront string and it arrives in Arabic (Priority: High)

A developer building a later slice adds a user-facing string. It is translatable without any change to this
baseline, and it appears in Arabic on the Arabic storefront.

**Why this priority**: NFR-04a is the row that makes every later slice cheap or expensive. The mechanism has to
exist and be enforced before the slices that add hundreds of strings, not after.

**Independent Test**: Add a string through the baseline's mechanism, provide an Arabic translation, and assert it
renders in Arabic. Separately, add a hard-coded string and assert the repeatable check rejects it.

**Acceptance Scenarios**:

1. **Given** a string emitted through the site text domain, **When** the Arabic storefront renders it, **Then**
   the Arabic translation is shown (AC-8)
2. **Given** a hard-coded user-facing string in storefront code, **When** the check runs, **Then** it fails and
   names the file and line (AC-7)

---

### User Story 4 - A later slice addresses an English and Arabic pair safely (Priority: High)

A later slice needs the Arabic counterpart of an English record. It resolves it through the translation
relationship, and gets exactly one correct record.

**Why this priority**: the product-cost pilot measured two ways this goes wrong. A WPML duplicate shares its
original's SKU, so a SKU addresses two products; and a translation group can be corrupted so that the Arabic
record detaches from its English source. Establishing valid translation identity at the baseline is what later
slices rely on.

**Independent Test**: Create an English record, translate it, and assert one translation group, the correct
source, and that resolution by translation identity returns the counterpart.

**Acceptance Scenarios**:

1. **Given** an English record with an Arabic translation, **When** the pair is read, **Then** both share one
   translation group and the English record is the source (AC-5)

---

### Edge Cases

- **A request carries no language hint at all.** English, by AC-1. The language is resolved from the URL, never
  from a stored preference that could outlive a session.
- **A request names a language that is not active.** It must not fall through to Arabic or produce a half-rendered
  page. English is the default and the only fallback.
- **Arabic is active but a string has no Arabic translation.** The English source string is shown. That is correct
  fallback behaviour and not a hard-coded-text failure: AC-7 is about how the string is emitted, not whether a
  translation exists yet.
- **The admin is opened by an Arabic-speaking operator.** The admin stays English. Switching the storefront
  language must not change the administrative interface language.
- **A clean `/ar/` URL is requested in an environment with no rewrite configuration.** The language resolution
  still has to be correct; only the URL shape depends on the web server. See the Native coverage table.
- **The Arabic storefront resolves its cart or checkout address.** It must find an Arabic page record, not fall
  back to the English one and not fail. This is why C-2 puts the translated system-page records in this feature.

## Native coverage

Measured on the disposable runtime on 4 October 2026: WordPress 7.1.2, WooCommerce 11.1.0, WPML 4.9.7, WPML String
Translation 3.5.4, WCML 5.5.7, CoreX 0.42.0, PHP 8.3.6, theme `mizzey-theme` active.

| Capability | Evidence | Status |
|---|---|---|
| Language framework, with `en` as default and directory-based language URLs | `icl_sitepress_settings`: `default_language=en`, `language_negotiation_type=1` (directories), `urls.directory_for_default_language=0`, `setup_complete=1` | VERIFIED |
| Arabic **enabled** as a second language, with its own URL and locale | `SitePress::get_active_languages()` returns both `ar` and `en`; `wp_icl_languages` records `active=1` for both. The `active` flag inside `wpml_active_languages` marks the **current** language, not whether a language is enabled, which is why `ar` reads `active: 0` while English is the current language. Measured twice, two ways | VERIFIED |
| Arabic storefront **served over HTTP, right to left** | **Yes.** `/ar/` returns 200 with `<html dir="rtl" lang="ar">` and `/ar/shop/` the same, while `/` returns 200 with `<html lang="en-US">` and no direction attribute. Measured by served request, once the baseline carries pretty permalinks and the standard rewrite rules | VERIFIED |
| No Arabic page records exist | all five pages (shop, cart, checkout, my account, sample) have a translation group containing `en` only. **A gap this feature closes**, per clarification C-2 | VERIFIED as a gap |
| Right-to-left direction from the locale, with no custom code | switching to `ar` gives locale `ar`, `is_rtl()` true and `$wp_locale->text_direction` `rtl`; switching back to `en` gives `en_US`, false and `ltr` | VERIFIED |
| Document direction attribute emitted by the platform | the served storefront root returns `<html lang="en-US">` with no direction attribute, correct for left to right. After switching the language in process, `language_attributes()` emits `dir="rtl"` | VERIFIED |
| Document `lang` attribute **in Arabic** | **Resolved: `lang="ar"` is emitted correctly** on a served Arabic request. The earlier `dir="rtl" lang="en-US"` came from an **in-process** `wpml_switch_language`, where the direction follows immediately and the cached language tag does not. That was an artifact of switching mid-request, not a platform defect, which is why it was recorded as a measurement to repeat rather than as a finding. AC-3 is met natively | VERIFIED |
| Arabic translations of the platform's own storefront strings | core `ar` language pack installed; `woocommerce-ar.mo` and `woocommerce-multilingual-ar.mo` present, plus the Arabic JSON translations WooCommerce needs for its scripts | VERIFIED |
| Translation identity for a record pair | `wpml_element_trid` and `wpml_get_element_translations`, used throughout `specs/001-product-cost-capture` and its scenarios t12 and t19 | VERIFIED |
| String translation for strings that are not in a `.mo` file | WPML String Translation active, `wp_icl_strings` present with 29 registered strings across 12 contexts | VERIFIED |
| A text domain loaded for the theme or the site plugin | **neither exists.** No `languages/` directory in `mizzey-theme` or `mizzey-site`, no `load_theme_textdomain` or `load_plugin_textdomain` call, and no `.pot`. **Gap** | VERIFIED as a gap |
| Any translatable string in this engagement's own code | **none.** No `__()`, `_e()` or `esc_html__()` call exists in `mizzey-theme` or `mizzey-site`, so there is no hard-coded storefront text to remove either. The work is to establish the mechanism and the check before the strings arrive | VERIFIED |
| Permalink and rewrite configuration | **A deterministic environment prerequisite, not a multilingual platform defect.** WPML uses directory negotiation, so the language segment sits at the root of the path. Before configuration the runtime served PATHINFO permalinks with no `.htaccess`, so `/shop/` and `/ar/` reached Apache as missing directories and `/index.php/ar/` reached WordPress as a missing post; `?lang=ar` correctly does nothing, the negotiation type being directories and not parameters. `mod_rewrite` is loaded and the vhost sets `AllowOverride All`, so pretty permalinks plus the standard rewrite rules make the Arabic URL serve, which is how the rows above were measured. **One wrinkle the baseline script carries**: `got_mod_rewrite()` is false under this runtime's CGI PHP, so `wp rewrite flush --hard` reports success and writes no `.htaccess`, so T-01 writes the file. **Nothing here is deferred to staging** | VERIFIED as a baseline prerequisite |
| Browser rendering across Chrome, Safari, Edge, Firefox and mobile | **not available from a command-line runtime, at all.** This is the one acceptance item that genuinely needs staging and real browsers, and it is blocked on #244 | UNVERIFIED |

**Native first, per constitution M-4.** Most of the capability is already provided, including the part that is
hardest to build: direction follows the locale with no custom code at all. The gaps are narrow and three of the
four are **configuration, not code**:

| Gap | Kind | Task |
|---|---|---|
| The baseline needs pretty permalinks and the standard rewrite rules before any language-prefixed URL resolves | Test-harness configuration. Deterministic, and not a platform defect | T-01 |
| No Arabic page records exist for the WooCommerce system pages | Platform configuration (C-2) | T-02 |
| Neither the theme nor the site plugin loads a text domain, and there is no `.pot` | A small amount of code, plus a generated file | T-03, T-04, T-05 |

**Nothing else is missing.** AC-3, AC-4 and AC-6 are met natively, by measurement, and receive tests rather than
an implementation. This feature found no multilingual platform defect.

**Two first-draft readings of this section were wrong, and both are recorded rather than quietly replaced.**

1. It claimed **Arabic was inactive**, reading the `active` flag of `wpml_active_languages`, which marks the
   current language rather than whether a language is enabled. `SitePress::get_active_languages()` and the
   `wp_icl_languages` table both report Arabic enabled. The same misreading would have produced a feature that
   "activates" a language that was never off.
2. It treated **`lang="ar"` as unresolved and the Arabic URL as unreachable**, on the strength of an in-process
   language switch and an unconfigured web server. A served request settled both: `/ar/` returns
   `dir="rtl" lang="ar"`. The lesson is the one the probes kept teaching. An in-process measurement is not a
   request, and an environment limit is not a platform defect.

## Requirements

### Functional Requirements

- **FR-001**: The platform MUST resolve English as the default and primary storefront language, from the URL, with
  no dependence on stored or session state. (AC-1)
- **FR-002**: The Arabic storefront MUST be served under its own language prefix, with English at the root, which
  requires the environment's rewrite configuration and an Arabic record for each page the storefront addresses.
  (AC-2)
- **FR-003**: The platform MUST derive reading direction and locale from the active language, so that Arabic
  renders right to left and English left to right, without per-language templates. (AC-3, AC-4, AC-6)
- **FR-004**: The rendered storefront document MUST carry the correct `lang` attribute, and the direction
  attribute in Arabic. (AC-3, AC-4)
- **FR-005**: An English record and its Arabic counterpart MUST form one valid translation relationship with the
  English record as source, resolvable by translation identity. (AC-5)
- **FR-006**: This engagement's storefront code MUST emit every user-facing string through a translation function
  bound to the site text domain, with the text domain loaded for both the theme and the site plugin. (AC-7, AC-8)
- **FR-007**: A repeatable automated check MUST fail when user-facing text is hard-coded in storefront code, and
  MUST name the file and line. (AC-7)
- **FR-008**: The administrative interface MUST remain English when the storefront language changes. (AC-1, AC-2)
- **FR-009**: The acceptance strategy MUST separate what the command-line runtime can verify from what needs a
  browser on staging, and MUST NOT report the second as verified. (AC-9)

### Key Entities

- **Active language**: a storefront language the platform serves. Two at launch: `en` (default, left to right) and
  `ar` (right to left).
- **Translation relationship**: the group that ties an English record to its Arabic counterpart, with a recorded
  source. The only safe way to address a counterpart.
- **Site text domain**: `mizzey-site`, already declared by both the theme and the site plugin headers, and the
  binding every translatable string in this engagement uses.

## Optional safeguards (not owed)

| Id | Safeguard | Why | Custom code? |
|---|---|---|---|
| S-1 | A static check in CI that rejects a user-facing string in this engagement's storefront code that is not wrapped in a translation function | NFR-04a is a one-line obligation that is violated one string at a time, across every later slice. A check is the only thing that holds it. FR-007 makes it contractual, so S-1 is the part that goes beyond: running it in CI on every PR rather than on demand | Yes, a checker under `tools/`, no production code |
| S-2 | An assertion that the storefront default language cannot be changed by session state, run on every suite | Guards AC-1 against a regression introduced by a later slice that stores a language preference | No, test only |

**Both approved by Mustafa on 4 October 2026.** They remain **engineering safeguards and evidence**, not
client deliverables, and nothing in this spec, the verification record or the pull request may present them as
separate deliverables. S-1 is the continuous-integration form of the FR-007 check, so the contractual obligation
is FR-007 and the safeguard is only that it runs on every pull request rather than on demand. S-2 is a test
assertion and changes no behaviour.

## Future or Option C items (not built)

| Item | Where it sits |
|---|---|
| Arabic administrative interface | ADM-159a, P2. Not contracted |
| Currency, number and date formatting per locale | NFR-04b, P1, owned by E-NFR-1 |
| Language-aware URL structure, hreflang and the SEO obligations | NFR-03 and the IA rows, owned by #242 |
| Header language switcher as a storefront component | NAV rows, owned by #253, behind OD-01 |
| Arabic translations of the catalogue content | the MIG rows and SSC-21, owned by #247 and #249 |
| A general multilingual metadata synchronisation framework | Deliberately not built. The product-cost pilot, A11, A12, B1 and the price matrix each concluded against it |
| Any stock synchronisation between a record and its translation | Deliberately not built. B1 measured that stock already shares one balance while the translation group is intact, because WCML hooks the stock write directly and stock never depended on `save_post`. The dangerous case is translation-group corruption, which AC-5 guards and a migration invariant owns |
| Any price synchronisation between a record and its translation | Deliberately not built. The price matrix measured every contracted launch price-maintenance path as already correct, including the scheduled-sales cron, so the native-first rule applies. This feature writes no product data of any kind |

## Success Criteria

- **SC-001**: On a clean runtime, an **HTTP request** to the root serves English and an HTTP request to the Arabic
  prefix serves Arabic, each with the correct language tag and reading direction in the rendered document.
  Repeatable from a scripted baseline. This is a served-request assertion, not an in-process one, because an
  in-process language switch has already been shown to report the direction correctly while the language tag
  lagged.
- **SC-002**: A record pair created through a supported workflow forms one translation group with the English
  record as source, asserted programmatically.
- **SC-003**: The automated hard-coded-text check passes on the baseline and fails on a deliberately introduced
  hard-coded string, with the file and line named.
- **SC-004**: The same template set renders both languages, demonstrated by rendering both with no Arabic-specific
  template present.
- **SC-005**: The browser matrix for AC-9 is written down, with every row marked not yet exercised, and no row is
  reported as verified until it is run on staging.

## Delivery stage

Contractual stage: **S1**, for all five rows. Engineering timing: this is the first implementation feature after
the pilot and runs immediately, which is delivery order 1 on the board and does not change any contractual stage.

## Assumptions

- The disposable local runtime is evidence for the recorded versions only, per constitution M-8. It is not a
  production compatibility statement.
- No production URL, domain or hosting arrangement is assumed or decided here. OD-27 is open, and the clean `/ar/`
  URL shape follows it.
- The site text domain is `mizzey-site` for both the theme and the site plugin, as their headers already declare.
  This feature does not introduce a second text domain.
- Arabic content for the catalogue is not created here. This feature establishes that the platform serves Arabic;
  what Arabic text the shop contains is migration and content work owned elsewhere.
- CoreX is not modified. If a requirement here proves a framework defect, it stops and goes to Mustafa as separate
  framework work.

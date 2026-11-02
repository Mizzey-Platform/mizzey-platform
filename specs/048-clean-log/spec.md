# Feature Specification: a clean application log

**Feature Branch**: `048-clean-log`

**Created**: 2026-11-02

**Status**: Draft

**Work type**: requirement

**Input**: The owner, 2 November 2026: the log must be clean, because a warning or an error means a problem in the code
that must be fixed. The local Apache crash (0xC0000005 on the product editor) was Xdebug in the Apache PHP configuration,
switched off and confirmed on the machine; this spec covers what the PHP and WordPress logs held. Every warning,
notice, deprecation and error the integration suite wrote was traced to its source (a recording error handler run over
every scenario): the site's own code, the scenarios, or third-party code.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| NFR-10 | P1-L | S1 | Observability: Application logs, error monitoring, integration logs, admin audit logs |

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| MIG-13 | P1 | The catalogue load writes Arabic records through WooCommerce Multilingual's editor (specs/046) |

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | Saving a product, and the store's own writes of a product translation, add nothing to the PHP error log | NFR-10 | final |
| AC-2 | A full run of the integration suite adds no warning, notice or deprecation from the site's code or its scenarios to the error log | NFR-10 | final |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A log that only holds real problems (Priority: High)

1. **Given** a product saved from any path, **When** the save completes, **Then** the error log is unchanged.
2. **Given** the catalogue load writing an Arabic record, **When** it runs, **Then** the error log is unchanged.
3. **Given** the integration suite, **When** it runs, **Then** what the error log gains is only third-party code recorded
   below, never the site's code or a scenario's set-up; a scenario's deliberate fatal error goes to a throwaway log.

## What the log held, and what was done

| Source | Where | Done |
|---|---|---|
| WPML 4.9.7 `WpmlSavePostHooks::on_product_save` and WPML Media's `woocommerce_extract_media_ids_from_post_content_and_meta` | Both pass the product object to `get_post()`: two PHP 8.2 deprecations per save, then they stop at a post of ID 0 (no effect) | `I18n/WpmlProductSaveHook` removes both, for WPML 4.9.7 only |
| WooCommerce Multilingual's product editor job, called from code | Undefined keys and a null `parse_str()`: the editor's form (`$_POST['data']`) and translation job were missing | `I18n/ProductTranslationJob` supplies the form as the editor does, reusing the product's open translation job and never creating one (creating one through WPML would cancel a translator's open job and notify translators); the catalogue load and the scenarios use it |
| WooCommerce Multilingual setting `products_sync_date` | Missing from the stored settings (never saved from its screen): an undefined key on every translation save | `I18n/WcmlSettingsDefaults` supplies its installer's default as the option is read |
| Scenario fixtures setting a cost with Cost of Goods Sold off | WooCommerce's "called incorrectly" notice | `Scenario::set_cost()` sets a cost only while the feature is on |
| Scenarios deleting translated products before WooCommerce Multilingual's shutdown update | A fatal `get_id()` on false at shutdown | Fixtures are removed after that update |
| t79's deliberate fatal errors in child processes | Three fatals per run | Sent to a throwaway file; the application log under test still gets them |
| Scenarios calling WordPress or WooCommerce without fields a real request has | `change_stock`, `comment_author_url`, `show_downloads`, `HTTP_HOST`, an HTML page parsed as XML, the language left on Arabic before the English dashboard | Each scenario supplies them |
| t71, t84: a staff sign-in with two-factor sign-in enrolled | WP 2FA's challenge page ended the process | `two_factor_completed_sign_in()` runs the sign-in hooks of a completed sign-in |

## Third-party, recorded and not changed

| Source | What | Why not changed here |
|---|---|---|
| Paymob for WooCommerce | `substr()` of null in `paymob.php`, an undefined `sec_key` in `paymob-auto-generate.php` before keys are set, and an undefined `success` when its refund API answers without one | Inside the official plugin (PAY-13); reported to stay with the plugin's next version, no Mizzey code involved |

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| A product translation saved from code | WooCommerce Multilingual's editor job, which expects its screen's form | Gap: `ProductTranslationJob` supplies the form, with the open job if any |
| WPML's product-save handlers without deprecations | None in WPML 4.9.7 | Gap: `WpmlProductSaveHook`, version-pinned |

## Requirements

### Functional Requirements

- **FR-001**: A product save MUST add nothing to the PHP error log (AC-1).
- **FR-002**: The store's own product translation writes MUST supply WooCommerce Multilingual's editor its job and form (AC-1).
- **FR-003**: The integration scenarios MUST add no warning, notice, deprecation or error of their own to the error log (AC-2).

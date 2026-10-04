# Research: what the platform, the plugins and the framework actually do

Phase 0 for `002-bilingual-platform-baseline`. Everything here was **measured on the disposable runtime or read
from source on 4 October 2026**, not recalled. Where a first reading turned out to be wrong, the correction is
kept, because the wrong reading is the one a later reader is likely to repeat.

Versions: WordPress 7.1.2, WooCommerce 11.1.0, WPML 4.9.7, WPML String Translation 3.5.4, WCML 5.5.7,
CoreX 0.42.0, PHP 8.3.6, theme `mizzey-theme`, 14 active plugins.

## 1. The running multilingual configuration

| Setting | Value | Consequence |
|---|---|---|
| `default_language` | `en` | FIX-04a is already configured, not just intended |
| `language_negotiation_type` | `1`, directories | Arabic lives at a path prefix, so it needs rewrite configuration |
| `urls.directory_for_default_language` | `0` | English at the root, Arabic under `/ar/`. Exactly what FIX-04a asks for |
| `setup_complete` | `1` | WPML is configured, not mid-wizard |
| `st.strings_language` | `en` | Source strings are English |
| `custom_posts_sync_option[product]` | `1` | Products are translatable |
| `_wcml_settings[trnsl_interface]` | `1` | The WCML translation editor is on. This is the editor A11 identified as part of the batch corruption trigger |

### The reading that was wrong

`apply_filters('wpml_active_languages', ...)` returns both languages, and the entry for `ar` carries
`active => 0` while `en` carries `active => '1'`. A first pass read that as **Arabic being disabled**, and wrote
it into the spec as the central gap.

It is not. That `active` flag marks the **current** language of the request. Two independent checks:

- `SitePress::get_active_languages()` returns `['ar', 'en']`, two languages.
- `wp_icl_languages` records `active = 1` for both `ar` and `en`.

Arabic has been enabled all along. The correction matters because a feature built on the wrong reading would have
"activated" a language that was never off, and then reported success.

### Right to left, in process

Switching in process behaves correctly for direction and incorrectly for the language tag:

| After `wpml_switch_language` | `get_locale()` | `is_rtl()` | `$wp_locale->text_direction` | `language_attributes()` |
|---|---|---|---|---|
| `ar` | `ar` | `true` | `rtl` | `dir="rtl" lang="en-US"` |
| `en` | `en_US` | `false` | `ltr` | `lang="en-US"` |

The lagging `lang` attribute was **not** concluded to be a defect, because an in-process switch is not an Arabic
request. Section 3 resolves it.

## 2. CoreX v0.42.0: no i18n layer exists

Read from `C:\wamp64\www\mizzey\app\corex`, read-only. Each absence below was searched for twice, the second time
independently of the first.

| Capability | Result |
|---|---|
| i18n service, contract, driver or interface | **Does not exist.** `docs/internal/COREX-FRAMEWORK.md:648` documents an `I18nHandler` wrapping WPML and Polylang, with a `MWP_I18N_DRIVER` switch. No such class, no `src/I18n/`, no `config/i18n.php`; the env key is an orphan nothing reads |
| A language, locale, translation or direction hook | **None** among CoreX's 5 actions (`corex_booted`, `corex_admin_access_denied`, `corex_site_created`, `corex_site_deleted`, `corex_site_migrated`) and 22 `corex_*` filters |
| WPML, WCML, Polylang or sitepress integration | **None.** No `icl_*`, `pll_*` or `wcml_*` symbol in any CoreX PHP file. Every mention is prose asserting the opposite, that CoreX must run without them |
| `load_theme_textdomain` | **0 PHP files** |
| `after_setup_theme` | **0 PHP files** |
| `add_theme_support` | **0 PHP files** |
| `wp_style_add_data`, for an RTL replacement stylesheet | **0 PHP files.** `Corex\Assets\Style::enqueue()` has no RTL option. Only block styles registered through `block.json` get an RTL variant, implicitly from `wp-scripts` |
| A template hierarchy or view layer | **None.** No `template_include`, `locate_template`, `get_template_part` or `load_template`. Rendering is WordPress block templates plus server-rendered dynamic blocks |
| A front-end `body_class` or `language_attributes` filter | **None.** The only `<html>` attributes CoreX writes are in `Admin/StandalonePage.php`, for admin interstitials |
| Admin locale handling | **None.** No `locale`, `plugin_locale`, `theme_locale`, `switch_to_locale` or `get_user_locale` use anywhere. CoreX neither forces nor constrains the admin language |

### What CoreX does do with text domains

One hardcoded domain, for its own strings only:

```
plugins/corex-core/src/Foundation/CoreServiceProvider.php:127
load_plugin_textdomain('corex', false, dirname(plugin_basename(COREX_CORE_FILE)) . '/languages');
```

Every CoreX plugin, addon and block shares the literal `corex` domain, and `wp_set_script_translations($handle,
'corex')` is applied automatically to block scripts. None of it reaches a client's own strings.

**The framework's own generators have the gap this feature closes.** The CLI stubs declare a client
`Text Domain:` header and no generated code loads it, in either the plugin stub or the theme-style stub. This
site's own files are in exactly that state: both declare `Text Domain: mizzey-site`, and nothing loads it.

### One framework hazard, recorded for later slices rather than used here

`Corex\Support\Facades\Corex::onReady()` exists at `Support/Facades/Corex.php:56` and solves a real problem: CoreX
boots on `plugins_loaded` priority 10 and so does a generated client plugin, so which wins depends on the plugin
directory name, and the loser gets a fatal from `Boot::app()` on every request. A site cannot guard against it by
testing for null, because `app()` throws rather than returning null.

**This feature does not touch the CoreX container, so it is not exposed.** It is written down because the first
later slice that resolves a CoreX service must use `onReady()`, and because CoreX's own stubs do not.

## 3. Served requests: the measurement that settled the feature

The runtime was switched to pretty permalinks and given a standard WordPress `.htaccess`, then four URLs were
requested over HTTP:

| Request | Status | Document element |
|---|---|---|
| `/` | 200 | `<html lang="en-US">`, no direction attribute |
| `/shop/` | 200 | `<html lang="en-US">` |
| `/ar/` | 200 | `<html dir="rtl" lang="ar">` |
| `/ar/shop/` | 200 | `<html dir="rtl" lang="ar">` |

**Three criteria are met with no code at all.** AC-3 (Arabic resolves to `ar`, right to left, `lang="ar"` and
`dir="rtl"`), AC-4 (English resolves to `en_US`, left to right, no direction attribute) and AC-6 (the same
templates render both, there being no Arabic-specific template in the theme).

It also resolves section 1's open measurement: on a **real** Arabic request the language tag is `lang="ar"`. The
in-process lag was an artifact.

### Why it did not work before

| Request, before the change | Result | Cause |
|---|---|---|
| `/shop/` | Apache 404 | No `.htaccess`, so Apache looked for a directory |
| `/ar/` | Apache 404 | The same |
| `/index.php/ar/` | WordPress 404 | WPML directory negotiation expects the language segment at the root of the path, not after `/index.php` |
| `/?lang=ar` | 200, still English | Correct. The negotiation type is directories, so WPML does not read `?lang=` |

`mod_rewrite` is loaded in the local Apache (`httpd.conf:174`) and the vhost sets `AllowOverride All`, so the
capability was there and only the configuration was missing.

### The wrinkle the baseline script has to carry

`got_mod_rewrite()` returns **false** in this runtime: PHP runs as CGI, so `apache_get_modules()` is unavailable
and WordPress cannot confirm the module. The consequences are specific:

- `wp rewrite structure --hard` and `wp rewrite flush --hard` report success and **do not write `.htaccess`**.
- The directory is writable, so the file can simply be written.

The baseline script therefore writes `.htaccess` itself. Relying on `--hard` would produce a baseline that looks
configured and is not.

**A process note worth keeping.** The first attempt to set the permalink structure from the Bash tool ran
`wp rewrite structure '/%postname%/'`, and Git Bash rewrote the leading slash into a Windows path, storing
`/C:/Program Files/Git/%postname%/` as the permalink structure. It was spotted by reading the option back and
corrected from PowerShell. Any script setting this option should verify it by reading it back.

## 4. Arabic page records: the gap, and one false alarm

All five pages (shop, cart, checkout, my account, sample page) have a translation group containing `en` only. No
Arabic counterpart exists, so `/ar/cart/` serves and serves page 6, the English cart.

### The false alarm

`/ar/checkout/` resolves to the Cart page, which looks like a language-resolution defect. It is not:

| Request | Resolves to |
|---|---|
| `/checkout/` | page 6, title "Cart" |
| `/cart/` | page 6, title "Cart" |
| `/ar/checkout/` | page 6, title "Cart" |

English behaves identically, so this is WooCommerce redirecting an empty cart away from checkout. It is standard
behaviour, it is not multilingual, and it is recorded so it is not rediscovered as a bug by the checkout slice.

## 5. Translation readiness of this engagement's own code

| Fact | Measured |
|---|---|
| `languages/` directory in `mizzey-theme` | **None** |
| `languages/` directory in `mizzey-site` | **None** |
| `load_theme_textdomain` or `load_plugin_textdomain` in either | **None** |
| `__()`, `_e()` or `esc_html__()` anywhere in `mizzey-theme` or `mizzey-site/src` | **None** |
| `Text Domain:` header | `mizzey-site` in both the plugin and the theme |
| `mizzey-theme` shape | A block theme: `style.css`, `theme.json`, `templates/index.html`, `templates/front-page.html`, `parts/header.html`, `parts/footer.html`. No `functions.php` |

There is currently **no hard-coded storefront text, because there is no storefront text**. That is the cheapest
possible moment to put the mechanism and the check in place, and the most expensive moment to skip it: NFR-04a is
violated one string at a time across every later slice.

## 6. Arabic translations that already exist

| Source | State |
|---|---|
| WordPress core `ar` | **Installed.** `wp language core list` reports `ar` installed, and `wp-content/languages` holds the `ar` files |
| WooCommerce `ar` | **Installed.** `woocommerce-ar.mo` plus the Arabic JSON script translations WooCommerce needs |
| WCML `ar` | **Installed.** `woocommerce-multilingual-ar.mo` |
| `mizzey-site` `ar` | **Does not exist.** There is nothing to translate yet |
| WPML String Translation | Active, with 29 registered strings across 12 contexts, mostly WooCommerce endpoint slugs and admin texts |

So the strings a customer sees from the platform and from WooCommerce are already Arabic. What is missing is only
the mechanism for strings this engagement writes.

## 7. What this feature will not do, and why each was considered

| Not doing | Reason |
|---|---|
| A multilingual metadata synchronisation layer | The product-cost pilot built one narrow sync for one native field and recorded why it is not generalised. A11, A12, B1 and the price matrix each concluded against a general layer |
| A stock or price synchronisation layer | Not in these five rows, and both were measured and rejected on their own evidence |
| An i18n abstraction mirroring CoreX's unbuilt `I18nHandler` | One caller, one translation plugin, no requirement. It would be a framework with nothing to vary |
| Touching the admin language | The admin is English by a row this feature does not own. Nothing here changes admin locale behaviour in either direction |
| A language switcher in the storefront | A navigation component, behind the approved design, owned by another PBI |
| Per-locale currency, number and date formatting | A separate contracted row owned by another slice |
| Deciding a production URL, domain or host | OD-27 is open. The rewrite configuration here is the **test** environment's, and decides nothing about production |

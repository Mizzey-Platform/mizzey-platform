# CoreX and Mizzey: capability and settings ownership audit

**Date:** 21 October 2026
**Status:** Static source review. **Not** a runtime test, not an upgrade, and not contractual acceptance. Every row
marked *runtime* still needs the check it names.
**Decision record:** ADR-0003 and D-18. The upgrade itself is assessed in
`docs/2026-10-21-corex-v0.47.0-migration-readiness.md`.

| | Version | Commit |
|---|---|---|
| Current Mizzey pin (`corex.lock`) | v0.42.0 | `12dde4967449e5fcf955f33db5d6f61b3b93b575` |
| Proposed frozen legacy target | v0.47.0, published 21 October 2026 14:50 UTC, not draft, not prerelease | `3a5476593b5fdab3975ecf47d327b3cacb43b71d`, the head of CoreX `main` at publication |
| Future CoreX revamp | Not released, API not published | |

**Method.** A read-only clone of `MustafaShaaban/corex` was read at the release-branch head `fbcdd18` and compared
with `v0.42.0`. The published tag's tree (`62acfad`) is byte-identical to that commit's, so every reference below
holds for the release. Mizzey's own code, its staging plugin list and the Bosta plugin 4.5.7 in the disposable
runtime were read alongside. Source paths are in the CoreX repository at v0.47.0 unless they start with
`mizzey-site/`, `mizzey-theme/` or `bosta-woocommerce/`. Nothing was run on a WordPress install.

**What Mizzey runs.** The staging tool activates `corex-core`, `corex-config`, `corex-blocks`, `corex-forms`,
`corex-guides`, `corex-email`, `corex-media` and `corex-ui` (`mizzey-site/tests/staging/staging.py`).
`corex-kit-woo` and the other add-ons are linked by `tools/corex-sync.mjs` and are not active. Mizzey's own plugin
calls no CoreX API: `MizzeySiteServiceProvider` is empty and unregistered.

## 1. Ownership matrix

Classifications: **Reuse CoreX**, **Reuse native**, **Mizzey adapter**, **Separate setting** (intentionally separate),
**Read-only mirror** (shared value, read-only presentation), **Duplicate** (true duplicate requiring remediation),
**Revamp gap** (missing extension point for the future CoreX revamp), **Runtime** (unverified pending a runtime test).
No row has an external cost unless the Cost column says so.

### Brand and presentation

| Concept | Authority and storage | Other writers or readers | Classification | Disable behaviour | Since v0.42.0 |
|---|---|---|---|---|---|
| Storefront logo (SSC-01, #311) | WordPress Site Logo, theme mod `custom_logo` (an attachment), written through the Site Editor, `edit_theme_options` | Read by CoreX email (`addons/corex-email/src/Design/SiteEmailBrand.php:84-94`). CoreX never writes it. `mizzey-theme/parts/header.html` has a site-title block and no site-logo block yet | **Reuse native**. The one authoritative storefront mark | Not applicable | No change |
| CoreX login logo | Option `corex_brand_logo_url`, a URL, CoreX Settings, Brand tab, `manage_options` (`plugins/corex-config/src/Branding/BrandingService.php`, `Settings/SettingsStore.php:19-22`) | Read only by the login screen (`Branding/AdminBranding.php:77`). The CoreX admin shell draws a hardcoded mark (`plugins/corex-core/src/Admin/AdminPage.php:280`), so the setting's help text "Used on the CoreX admin shell" is inaccurate | **Separate setting** (CoreX product identity). **Revamp gap**: no option to follow `custom_logo`, no filter on the URL | Blank shows the CoreX lockup | No change |
| CoreX brand text (company name, tagline, phone, email, address, primary action, social links) | Options `corex_brand_*` (`Settings/SettingsRegistry.php:28-62`) | No front-end reader found in source, although the help text says "Used across the site chrome, footer" | Not a Mizzey source. Storefront contact and footer content belong to Mizzey template parts and pages (SSC-07, SSC-08). **Revamp gap** (dead or misdescribed fields) | Values are inert | No change |
| Storefront colour tokens (SSC-01) | `mizzey-theme/theme.json` (theme origin), changed by staff through Site Editor Global Styles (user origin) | CoreX merges a `brand.json` file at the theme origin when one exists (`plugins/corex-core/src/Theme/ThemeServiceProvider.php:59-84`). Mizzey has none, so it is inert; the user origin would win anyway | **Reuse native** | Not applicable | Docblock only |
| Token slugs CoreX blocks expect | corex-ui block styles consume `--wp--preset--color--primary`, `surface`, `border`, `ink-soft` and `--wp--custom--radius`, `border`, `motion` (`addons/corex-ui/assets/block-styles.css`) | Mizzey's `theme.json` defines no palette or such tokens. CoreX email reads palette slug `primary` for its accent (`MailServiceProvider.php:141-150`) | **Mizzey adapter** when the design system defines tokens (after OD-01): either supply the slugs or keep corex-ui blocks out of Mizzey templates. **Revamp gap**: the token contract is undocumented. **Runtime**: how the blocks render today | Not applicable | No change |
| Navigation and footer (SSC-07, SSC-08, #253, #311) | WordPress Navigation block and Mizzey template parts | CoreX navigation assets load only if the active theme ships them (`Theme/NavigationServiceProvider.php:71-77`, `:100-104`); its header and footer patterns live in the CoreX theme, which Mizzey does not use | **Reuse native**. No duplicate | Not applicable | No change |
| Section library (SSC-11, #311) | Mizzey's curated block patterns, built under #311 from the approved interface design | corex-ui registers ten patterns in a "Corex" category and six block-style variants whenever it is active, whatever the theme (`addons/corex-ui/src/Patterns/PatternRegistrar.php:24-36`, `Blocks/BlockStyles.php:33-56`) | **Duplicate**, pending **Runtime**: if they appear in Mizzey's inserter, staff see an uncurated second library. Resolved under #311 by unregistering that category on Mizzey or by not activating corex-ui, decided with the design system | Deactivating corex-ui removes them and its blocks | No change |
| Generator tag | `<meta name="generator" content="CoreX" />`, no version, every front-end page (`plugins/corex-core/src/Theme/GeneratorTag.php:24-46`) | WordPress prints its own generator tag | **Owner decision**. One `add_filter('corex_generator_tag', '__return_false')` in `mizzey-site/` removes it | The filter | **New in v0.47.0** |

### Email

| Concept | Authority and storage | Other writers or readers | Classification | Disable behaviour | Since v0.42.0 |
|---|---|---|---|---|---|
| Customer transactional email (NOTF rows, #285) | WooCommerce emails and settings | CoreX hooks no `woocommerce_email_*`, `pre_wp_mail` or global `wp_mail` filter. Its `phpmailer_init` listener is added and removed for each CoreX send, keyed to its own header (`addons/corex-email/src/Driver/WpMailDriver.php:51`, `:97-116`) | **Reuse native**. **Runtime**: capture a WooCommerce email with corex-email active | Not applicable | The per-send listener is new |
| Customer email logo | WooCommerce option `woocommerce_email_header_image` (a URL) | Separate from `custom_logo`; nothing keeps them in step | **Mizzey adapter** under #285: derive the header image from `custom_logo`, so staff upload once. **Runtime**: whether the installed WooCommerce already defaults to the site logo | Not applicable | Not CoreX |
| CoreX email logo | Option `corex_mail_brand_logo` (override); blank falls back to `custom_logo`, then the site icon (`SiteEmailBrand.php:84-94`, `:154-158`) | The override reaches CoreX submission replies only; templated CoreX mail ignores it (`SiteEmailBrand.php:74`). SVG is skipped | **Separate setting, kept empty**, so CoreX mail derives from the storefront logo. The Site Logo needs a raster rendition, because an SVG-only logo gives CoreX mail no logo. **Revamp gap**: the override and the template disagree | Blank derives | **New** (spec 106); v0.42.0 already fell back to `custom_logo` |
| Sender identity | WooCommerce `woocommerce_email_from_name`, `woocommerce_email_from_address` for customer mail (OD-18) | CoreX `corex_mail_from_name`, `corex_mail_from_address`, `corex_mail_reply_*` for CoreX's own mail (form replies, access requests) | **Separate setting**, operated with the same values. It becomes a **Duplicate** only if CoreX forms send customer-facing mail on Mizzey, which nothing contracted requires | Not applicable | Reply sender keys are new |
| Mail transport | The contracted transactional email delivery service (#284, INT-05) | CoreX ships no SMTP. `mail.provider` and `mail.live_delivery` gate only CoreX's sends | **Separate setting**. **Cost**: the delivery service is an existing contracted obligation, not free; no new provider is added | Not applicable | No change |

### Operations, roles and logs

| Concept | Authority and storage | Other writers or readers | Classification | Disable behaviour | Since v0.42.0 |
|---|---|---|---|---|---|
| Operational alerts for staff (ERP sync failure, unmapped carrier status, payment needing attention) | CoreX inbox: `NotificationService::publish()` (`plugins/corex-core/src/Notifications/NotificationService.php:25`), category `operations`, which users cannot mute (`NotificationPreference.php:22-26`); tables `notifications` and `notification_user_state` | Bound only by corex-config (`plugins/corex-config/src/ConfigServiceProvider.php:259`). No CoreX producer uses `operations` (`:1302-1323`). Delivery is in the admin only; the email channel is an interface with no implementation (`NotificationChannelPolicy.php:22`) | **Reuse CoreX** through a **Mizzey adapter**: guard with the container's `has()`, wrap `publish` in try/catch (it can throw, `WpNotificationRepository.php:95`), one dedup key per incident prefixed `mizzey.`, because a repeat of a key does not reset a reader's read or dismissed state | Inactive corex-config means no service; the adapter must then do nothing | Byte-identical since v0.42.0 |
| Who sees the inbox | Ability `corex_manage_notifications`, granted per role in CoreX's role matrix (table `role_ability_grants`, `plugins/corex-config/src/Access/AbilityCompatibility.php:45`) | The screen and the toolbar entry require it (`NotificationsScreen.php:44`, `NotificationToolbar.php:40`); the bell is on CoreX screens only. The same ability resolves any item by id without a visibility check (`NotificationServiceImpl.php:110`) | **Reuse CoreX**. The owner names which staff roles get it | Ungranted roles see nothing | The role matrix is unchanged; requests and expiry are new (spec 112) |
| Staff roles and capabilities (ROLE rows, Accountant pending CX-01) | Native WordPress and WooCommerce roles and capabilities | CoreX abilities are virtual capabilities added by a `user_has_cap` filter; CoreX never adds capabilities to `shop_manager` or any role. A client cannot cleanly register its own ability (`CorexAbility.php:53`, `:85`) | **Reuse native** for every Mizzey permission, refunds included; CoreX abilities only for CoreX screens | Not applicable | Two abilities added (15 now) |
| Staff activity log (#275, `ad-staff-log`) | Not yet decided | CoreX Activity, table `activity_events`: append-only, no delete route found, REST for `manage_options` only, structured filters and no text search, no sign-in or WooCommerce events, a closed area list, and a retention prune with no production caller (`plugins/corex-core/src/Activity/ActivityService.php`, `ActivityEvent.php:67`, `WpActivityRepository.php:121`) | **Owner decision** under #275, see section 2. Either a **Mizzey adapter** recording into CoreX Activity with a Mizzey search screen, or a **Separate** Mizzey log that is the only one presented to staff. **Runtime**: confirm no delete path | Not applicable | Column constants only |
| Sign-in protection and two-factor (`ad-two-factor`, 09-E) | Not in CoreX: no two-factor code in any CoreX plugin | CoreX login protection, option `corex_login_protection_settings`, off by default; it hides login URLs and records attempts only when on | Two-factor is **not available in CoreX or WordPress core**. The Technical Design names a free component (v1.3, row 20 and section 7: WP 2FA, free tier; v1.2: free options available), evaluated against ADM-131 before anything else is raised. **Cost**: a paid plugin needs the owner's written approval | Off by default | Login protection is unchanged in effect |
| Coming soon (`sy-coming-soon`) | WooCommerce Site Visibility (D-18 section 2) | WooCommerce `woocommerce_coming_soon`. CoreX operations mode, option `corex_operations_mode` = `coming-soon`, which redirects every page, serves its own page and replaces the sitemap (`plugins/corex-config/src/Operations/ComingSoonGuard.php:68`, `ComingSoonSitemap.php:24`) | **Duplicate** from the upgrade on: two coming-soon mechanisms. **Decided** by D-18 section 2: WooCommerce's is used and CoreX's stays off | Each by its own setting | **New since v0.42.0** (v0.43.0, spec 101) |
| Maintenance | CoreX operations mode `maintenance`: a 503 page guard, and since v0.47.0 a 503 for REST routes marked `corex_public_submission` (`MaintenanceSubmissionGuard.php:34-71`) | WooCommerce has no maintenance mode. The submission guard leaves WooCommerce routes untouched | **Reuse CoreX** if a maintenance window is needed. **Runtime**: what the page guard does to cart and checkout requests | Mode off | Submission guard **new in v0.47.0** |
| CoreX job runner | CoreX Jobs, operator-started, retried, through Action Scheduler or WP-Cron (`plugins/corex-core/src/Jobs/JobService.php`) | Requires `corex_manage_operations` | **Reuse CoreX** for a manual resync; **Reuse native** (WooCommerce's Action Scheduler) for a recurring ERP schedule, which CoreX Jobs does not offer | Not applicable | Permission changed |

### Commerce, search and stock

| Concept | Authority and storage | Other writers or readers | Classification | Disable behaviour | Since v0.42.0 |
|---|---|---|---|---|---|
| `corex-kit-woo` | Plugin inactive; flag option `corex_features_woocommerce_kit` absent | When active and flagged, it registers a blueprint into a registry only `corex-kit-company` binds; while active it also declares HPOS compatibility (`addons/corex-kit-woo/corex-kit-woo.php:44-48`) | **Reuse native** (WooCommerce). Keep the plugin inactive, not only the flag off | Plugin inactive: no file loaded | Version string only |
| WooCommerce, HPOS, checkout | WooCommerce | No CoreX code hooks `woocommerce_*` or touches orders, products, stock or checkout. Email Studio adds a stored "WooCommerce" layout once, attached to nothing | **Reuse native** | Not applicable | No change |
| Authoritative stock (#317, P1-E) | The ERP, through the Mizzey adapter (PRE-09). WooCommerce `_stock` is a mirror | **Bosta 4.5.7 writes stock twice**: the route `bosta/v1/webhook/stock-sync`, registered on every request and guarded by a shared secret, applies Bosta's delta with `set_stock_quantity()` (`bosta-woocommerce/includes/class-bosta-webhook-routes.php:30-34`, `class-bosta-webhook-handler.php:171-172`); and the admin "sync quantity" action overwrites stock with Bosta's figure (`class-bosta-fulfillment.php:557-558`). Its product auto-sync pushes stock to Bosta on every product save when enabled. No CoreX code writes stock | **Duplicate** (a second stock authority), remediated under #291 and #317: remove the stock route, keep product auto-sync off, and offer no manual quantity sync. **Runtime**: a test that the route is gone and that an ERP write does not loop | Not applicable | Not CoreX |
| Order status from the carrier (#291) | The explicit Mizzey mapping #291 builds | Bosta's order webhook writes meta only; its status change is commented out (`class-bosta-webhook-handler.php:260-271`), as P-012 found | **Mizzey adapter**, as already recorded in #291 | Not applicable | Not CoreX |
| Title, description, canonical, robots, sitemap (#287) | WordPress core for the title, robots and sitemap; `mizzey-site/src/Seo/MetaDescription.php` for the description; core (singular) and `ArchiveCanonical.php` (archives) for the canonical; `SitemapLanguages.php` for both languages | CoreX prints none of these at either version. CoreX coming-soon mode replaces the sitemap while on | **Reuse native** plus the existing **Mizzey adapters**: #287 stores each editable value once and feeds these writers, and adds no second printer | Not applicable | No change |
| Hreflang and structured data | WPML (hreflang), WooCommerce (product JSON-LD), both in Mizzey's evidence | None in CoreX | **Reuse native** | Not applicable | Not CoreX |
| Redirects and missing-page log (#287) | Not in CoreX, WordPress or WooCommerce. The Technical Design names Redirection, a free component (v1.3, row 16 and section 7; v1.2 classes the row as a plugin) | CoreX login protection, when on, answers hidden login and admin addresses with WordPress's 404 (`plugins/corex-config/src/Security/LoginProtection/LoginRouteGuard.php:69-103`) | **Owner decision**: the component the Technical Design names or a Mizzey build under #287, never both. Either way the log excludes those 404s. **Revamp gap** | Not applicable | Not present |
| Search synonyms (#304) | Not in CoreX | None | Built in Mizzey under #304 | Not applicable | Not present |

### Settings and flags

| Concept | Authority and storage | Notes | Classification | Since v0.42.0 |
|---|---|---|---|---|
| CoreX configuration | `.env`, then network lock, then site option `corex_<key>`, then network default, then code default (`plugins/corex-core/src/Foundation/CoreServiceProvider.php:50-58`) | No PHP constant layer. Settings saves need `manage_options` and a nonce | **Reuse CoreX** | **Changed**: an option now counts only if it is autoloaded (CoreX DECISIONS #361). A `corex_*` row stored with autoload off is ignored |
| Feature flags | `corex_features_pro`, `_mail_queue`, `_dataviews_admin`, `_woocommerce_kit`, all off by default (`plugins/corex-core/config/features.php:19-25`) | A flag that is off means the add-on's provider is not booted. The Add-ons screen reads the option directly and ignores `.env`, so the screen and the runtime can disagree | **Reuse CoreX**. Mizzey sets none | Boot read changed (see above) |

## 2. Duplicates and proposed resolutions

| Duplicate | Where it bites | Proposed resolution | Who decides | Built under |
|---|---|---|---|---|
| Bosta stock route and manual quantity sync | A second writer of authoritative stock, against the ERP invariant | Close the route, keep auto-sync off, remove the manual action from staff use; test it | Already required by the ERP invariants; mechanism at the PBI | #291 with #317 |
| Two coming-soon mechanisms after the upgrade | A visitor could meet two different holding pages, and CoreX's replaces the sitemap | WooCommerce's Site Visibility; CoreX's mode stays off | Decided, D-18 section 2 | #311 or the PBI that owns `sy-coming-soon` |
| CoreX patterns beside the curated section library | Staff could insert uncurated, unthemed sections | Unregister the "Corex" pattern category on Mizzey, or deactivate corex-ui if no CoreX block is used | Owner, with the design system | #311 |
| Two email logos and two storefront-adjacent logo uploads | Staff could change the logo in one place and not the others | `custom_logo` is the one upload; CoreX email override stays empty; WooCommerce's header image derives from it | Already directed by D-18 | #285, #311 |
| Two sender identities | Customers could receive mail from two names | WooCommerce's sender for customer mail; CoreX's set to the same values and used only for CoreX mail | Owner, at OD-18 | #285 |
| Staff activity log | Two logs presented to staff would be a duplicate | One log presented to staff; CoreX Activity either feeds it or stays CoreX's internal record | Owner, in the #275 spec | #275 |

No duplicate is resolved here. Each is resolved by its accepting PBI's spec, or by the design, when that work is
authorised.

## 3. Design consistency for batch-09 groups 09-B and 09-C

Recommendations for the design packages, to be applied only through the design workflow
(`design/wireframes/workflow.md`) and the owner's review. No brief, manifest or wireframe is changed by this audit.

- **Frozen 09-A still holds at v0.47.0.** The full-page refusal drawn in `ad-permission-denied` and `ad-staff-log`
  is CoreX's `AdminDieHandler`, byte-identical from v0.42.0 to v0.47.0; `StandalonePage` changed only additively,
  before v0.46.1. Nothing in 09-A needs redrawing for the target.
- **`ad-global-brand-controls` (09-B).** Draw the logo as WordPress's Site Logo and the colours as Global Styles,
  as its `constrained` freedom already says. Annotate that this is the single storefront mark, that email logos
  derive from it, and that CoreX Settings, Brand, is a separate product login logo that this surface does not
  edit. Do not draw a second logo upload for email.
- **`ad-section-library` (09-B).** The curated library is Mizzey's own; annotate that CoreX's patterns are not part
  of it.
- **`ad-navigation-editor` (09-B).** The native Navigation block and template parts. No CoreX navigation screen exists
  to mirror.
- **`ad-promo-banner-manager`, `ad-catalogue-terms`, `ad-manual-product-order`, `ad-search-synonyms` (09-B).** CoreX
  has nothing in these areas; no overlap to annotate.
- **`ad-seo-fields`, `ad-seo-site-settings`, `ad-missing-page-log` (09-C).** One field per value per language,
  feeding the existing writers; no CoreX SEO screen exists. Annotate on `ad-seo-site-settings` that a coming-soon
  mode, while on, overrides the sitemap.
- **`ad-stock-from-erp`, `ad-price-stock-history`, `ad-product-storefront-controls` (09-C).** Stock is read-only from
  the ERP. Do not draw Bosta's quantity sync as a staff action.
- **Operational conditions anywhere in batch-09.** Show them as items in CoreX's existing inbox, not a Mizzey bell
  or panel. Which roles see the inbox is an owner decision (section 5).

## 4. CoreX fixes and revamp backlog recommendations

Recorded for the separate CoreX revamp backlog. **No CoreX issue is created or changed by this audit**; filing them
needs the owner's authorisation, with no client material in them.

| Item | Evidence | Recommendation |
|---|---|---|
| `NotificationService::publish` can throw, against its docblock | `WpNotificationRepository.php:95` | Catch and report inside the service, or correct the contract |
| `resolveById` skips the visibility check | `NotificationServiceImpl.php:110` | Check visibility before resolving, and record the resolution |
| A repeat of a dedup key does not reset readers' state | `NotificationStatus.php` | Optional reset on reopen |
| Notification categories, ability groups and activity areas are closed lists with no client extension | `NotificationCategory.php:17-34`, `CorexAbility.php:85`, `ActivityEvent.php:67` | A documented client registration seam |
| `AREA_NOTIFICATIONS` is defined but missing from the allowed areas | `ActivityEvent.php:39`, `:67` | Add it or remove it |
| Activity and sign-in attempt retention never runs | `WpActivityRepository.php:121`, `ConfigServiceProvider.php:286-291` | Add both to the retention sweep |
| The login logo cannot follow the site's Site Logo, and has no filter | `BrandingService.php`, `AdminBranding.php:77` | An option to use `custom_logo`, and a filter |
| The admin shell help text and the brand text fields are inaccurate or unused | `SettingsRegistry.php:28-66`, `AdminPage.php:280` | Correct the text or remove the fields |
| Email logo override reaches replies but not templated mail | `SiteEmailBrand.php:74` | One logo resolution for every CoreX mail |
| The block-style token contract is implicit | `addons/corex-ui/assets/block-styles.css` | Document the slugs a client theme must define |
| The Add-ons screen and the boot disagree about `.env` flags | `AddonManager.php:106`, `Boot.php:261` | Read flags through one reader |
| No redirect manager or missing-page log | Absent | A candidate framework capability |
| corex-ui registers its patterns whatever the theme | `PatternRegistrar.php:24-36` | Let a client site opt out without unhooking |

## 5. Owner decisions still open

1. Whether the login screen shows CoreX's identity (the default) or Mizzey's (pasted by hand today; no sync).
2. Which staff roles receive `corex_manage_notifications`, knowing it also lets them resolve any inbox item.
3. Whether Mizzey's pages keep CoreX's new generator tag.
4. Which coming-soon mechanism Mizzey uses. Decided by D-18 section 2: WooCommerce Site Visibility.
5. Whether the contracted staff log is built on CoreX Activity or kept separate (#275).
6. Whether corex-ui stays active, which decides the pattern duplicate and the token adapter. D-18 section 3: it is
   not enabled because it exists, only on a demonstrated requirement.
7. How two-factor sign-in is provided. Neither CoreX nor core has it; the Technical Design names WP 2FA, free tier,
   which is evaluated first.
8. Which mechanism holds redirects and the missing-page log (MKT-13, MKT-14): Redirection, which the Technical
   Design names, or a Mizzey build. One of them, not both.

## 6. What is not established

Static reading cannot settle the rows marked *runtime*. In particular: that WooCommerce mail is untouched with
corex-email active; whether WooCommerce already uses the site logo in its email header; whether the CoreX patterns
appear in Mizzey's inserter; how corex-ui blocks render without tokens; that CoreX Activity has no delete path end
to end; and what CoreX's maintenance page guard does to cart and checkout requests. WooCommerce and WPML source was
not read for this audit; claims about them rest on Mizzey's recorded probes.

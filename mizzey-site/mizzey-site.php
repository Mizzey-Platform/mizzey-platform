<?php

/**
 * Plugin Name:       Mizzey Site
 * Description:       Application code for the Mizzey website (a Corex client site).
 * Version:           0.1.0
 * Requires at least: 7.0
 * Requires PHP:      8.3
 * Author:            Mizzey
 * License:           GPL-2.0-or-later
 * Text Domain:       mizzey-site
 *
 * @package MizzeySite
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

// Mizzey site app code lives here under the MizzeySite\ namespace — never edit the
// Corex framework. Register the service provider with Corex's container on boot.

require_once __DIR__ . '/src/I18n/Localisation.php';
require_once __DIR__ . '/src/Catalogue/Collections.php';
require_once __DIR__ . '/src/Catalogue/Brands.php';
require_once __DIR__ . '/src/Catalogue/CostTranslationSync.php';
require_once __DIR__ . '/src/Seo/ArchiveCanonical.php';
require_once __DIR__ . '/src/Seo/MetaDescription.php';
require_once __DIR__ . '/src/Seo/SitemapLanguages.php';
require_once __DIR__ . '/src/Reporting/PhysicalItems.php';
require_once __DIR__ . '/src/Reporting/StockReport.php';
require_once __DIR__ . '/src/Security/Disclosure.php';
require_once __DIR__ . '/src/Security/XmlRpc.php';
require_once __DIR__ . '/src/Security/AccountDiscovery.php';
require_once __DIR__ . '/src/Security/ApplicationPasswords.php';
require_once __DIR__ . '/src/Security/SignInMessages.php';
require_once __DIR__ . '/src/Security/RecoveryLimiter.php';
require_once __DIR__ . '/src/Security/CommentSurface.php';
require_once __DIR__ . '/src/Security/TwoFactor.php';
require_once __DIR__ . '/src/Security/TwoFactorIssuer.php';
require_once __DIR__ . '/src/Security/SignupCheck.php';
require_once __DIR__ . '/src/Security/SignupOfferRender.php';
require_once __DIR__ . '/src/Security/SignupRestCallback.php';
require_once __DIR__ . '/src/Security/SignupRoutes.php';
require_once __DIR__ . '/src/Security/BaselineReport.php';
require_once __DIR__ . '/src/Security/Cli/SecurityCommand.php';
require_once __DIR__ . '/src/Security/Security.php';
require_once __DIR__ . '/src/Catalogue/Import/CatalogueWriteRefused.php';
require_once __DIR__ . '/src/Catalogue/Import/CatalogueWriteGuard.php';
require_once __DIR__ . '/src/Catalogue/Import/ExportReader.php';
require_once __DIR__ . '/src/Catalogue/Import/LoadRules.php';
require_once __DIR__ . '/src/Catalogue/Import/Mapping.php';
require_once __DIR__ . '/src/Catalogue/Import/HeaderCheck.php';
require_once __DIR__ . '/src/Catalogue/Import/RowInterpreter.php';
require_once __DIR__ . '/src/Catalogue/Import/SkuIndex.php';
require_once __DIR__ . '/src/Catalogue/Import/ValidationPass.php';
require_once __DIR__ . '/src/Catalogue/Import/ReportWriter.php';
require_once __DIR__ . '/src/Catalogue/Import/ImportRunRecorder.php';
require_once __DIR__ . '/src/Catalogue/Import/FileImportRunRecorder.php';
require_once __DIR__ . '/src/Catalogue/Import/Cli/CatalogueCommand.php';
require_once __DIR__ . '/src/Orders/StatusModel.php';
require_once __DIR__ . '/src/Orders/StatusRegistry.php';
require_once __DIR__ . '/src/Orders/TransitionGuard.php';
require_once __DIR__ . '/src/Orders/CancellationField.php';
require_once __DIR__ . '/src/Orders/RefundIntegrity.php';
require_once __DIR__ . '/src/Promotions/PromotionSettings.php';
require_once __DIR__ . '/src/Promotions/PromotionRules.php';
require_once __DIR__ . '/src/Promotions/DiscountCap.php';
require_once __DIR__ . '/src/Promotions/AutomaticPromotions.php';
require_once __DIR__ . '/src/Promotions/Combination.php';
require_once __DIR__ . '/src/Promotions/WelcomeDiscount.php';
require_once __DIR__ . '/src/Accounts/VerificationOnRegistration.php';
require_once __DIR__ . '/src/Accounts/MarketingConsent.php';
require_once __DIR__ . '/src/Accounts/PhoneSignIn.php';
require_once __DIR__ . '/src/Accounts/SignInLink.php';
require_once __DIR__ . '/src/Catalogue/CanonicalProduct.php';
require_once __DIR__ . '/src/Stock/StockItem.php';
require_once __DIR__ . '/src/Stock/StockAnswer.php';
require_once __DIR__ . '/src/Stock/StockSourceUnavailable.php';
require_once __DIR__ . '/src/Stock/StockSourceRefused.php';
require_once __DIR__ . '/src/Stock/StockSource.php';
require_once __DIR__ . '/src/Stock/UnconfiguredStockSource.php';
require_once __DIR__ . '/src/Stock/StockIdentity.php';
require_once __DIR__ . '/src/Stock/StockGateway.php';
require_once __DIR__ . '/src/Stock/FixtureStockSource.php';
require_once __DIR__ . '/src/Stock/LocalStockWriters.php';
require_once __DIR__ . '/src/Cart/CartStock.php';
require_once __DIR__ . '/src/Cart/CartMerge.php';
require_once __DIR__ . '/src/Cart/GuestCartRetention.php';
require_once __DIR__ . '/src/Cart/FreeShippingProgress.php';
require_once __DIR__ . '/src/Cart/CartSuggestions.php';
require_once __DIR__ . '/src/Checkout/EgyptianAddress.php';
require_once __DIR__ . '/src/Checkout/OrderStock.php';
require_once __DIR__ . '/src/Wishlist/GuestCookie.php';
require_once __DIR__ . '/src/Wishlist/Wishlist.php';
require_once __DIR__ . '/src/Wishlist/RecentlyViewed.php';
require_once __DIR__ . '/src/Wishlist/WishlistRoutes.php';
require_once __DIR__ . '/src/Reviews/ReviewEligibility.php';
require_once __DIR__ . '/src/Reviews/ReviewModeration.php';
require_once __DIR__ . '/src/Reviews/StoredOrderMeta.php';
require_once __DIR__ . '/src/Reviews/ReviewInvitation.php';
require_once __DIR__ . '/src/Emails/OrderLanguage.php';
require_once __DIR__ . '/src/Emails/EmailAdditions.php';
require_once __DIR__ . '/src/Emails/Shipped.php';
require_once __DIR__ . '/src/Account/AddressBook.php';
require_once __DIR__ . '/src/Account/AccountPages.php';
require_once __DIR__ . '/src/Invoices/InvoiceSettings.php';
require_once __DIR__ . '/src/Invoices/InvoiceNumber.php';
require_once __DIR__ . '/src/Invoices/InvoiceDocument.php';
require_once __DIR__ . '/src/Invoices/InvoicePdf.php';
require_once __DIR__ . '/src/Invoices/InvoiceRoutes.php';

// The site text domain, for this plugin and for the theme. Nothing else loads it: Corex loads only its own
// `corex` domain. See specs/002-bilingual-platform-baseline (NFR-04a).
MizzeySite\I18n\Localisation::register();

// Product cost must stay equal across a product's language versions, including when the cost is changed by code
// rather than through wp-admin. See the class for the reason and specs/001-product-cost-capture for the evidence.
add_action('plugins_loaded', [MizzeySite\Catalogue\CostTranslationSync::class, 'register'], 20);

// The collection taxonomy, which gives IA-04 and IA-35 their archive route. The register settles the model:
// ADM-57 makes manually curated collections P1, ADM-58 defers rules-based membership to P2.
MizzeySite\Catalogue\Collections::register();

// IA-05: the brand archive's address word is `brand` in every language. WooCommerce's default is a translatable
// string, which gave an Arabic request links that no routing rule answered.
MizzeySite\Catalogue\Brands::register();

// NFR-03's three measured output gaps. Each emits only, through a documented filter, with no storage of its own,
// so MKT-12, MKT-15, ADM-41 and SSC-27 can override it later without this code changing.
// See specs/003-information-architecture-urls.
MizzeySite\Seo\ArchiveCanonical::register();
MizzeySite\Seo\MetaDescription::register();
MizzeySite\Seo\SitemapLanguages::register();

// RPT-10: the standard stock report lists one line per physical item, whichever language its records are in and
// whichever language the operator's session is in. Confined to that report's own query.
// See specs/004-inventory-report-one-item-once.
MizzeySite\Reporting\StockReport::register();

// The site-level security baseline: NFR-05, specs/007-security-baseline.
MizzeySite\Security\Security::register();

// The contracted order status model: the statuses, the allowed moves, the first entry into each status and the
// cancellation reason. ORD-01 to ORD-14, ADM-86, ADM-87, specs/010-order-status-model.
MizzeySite\Orders\StatusRegistry::register();
MizzeySite\Orders\TransitionGuard::register();

// The wishlist and recently viewed lists (WISH-01 to WISH-04, WISH-07; B17). See specs/015-wishlist.
MizzeySite\Stock\FixtureStockSource::register();
MizzeySite\Stock\LocalStockWriters::register();
MizzeySite\Cart\CartStock::register();
MizzeySite\Cart\CartMerge::register();
MizzeySite\Cart\GuestCartRetention::register();
MizzeySite\Cart\FreeShippingProgress::register();
MizzeySite\Cart\CartSuggestions::register();
MizzeySite\Checkout\EgyptianAddress::register();
MizzeySite\Checkout\OrderStock::register();
MizzeySite\Wishlist\Wishlist::register();
MizzeySite\Wishlist\RecentlyViewed::register();
MizzeySite\Wishlist\WishlistRoutes::register();
MizzeySite\Orders\CancellationField::register();

// The promotion engine: WooCommerce coupons plus the settings they lack (automatic, start date, maximum discount,
// minimum items, first order only). PROMO-01 to PROMO-23, specs/011-promotion-engine.
MizzeySite\Promotions\PromotionSettings::register();
MizzeySite\Promotions\PromotionRules::register();
MizzeySite\Promotions\DiscountCap::register();
MizzeySite\Promotions\AutomaticPromotions::register();
MizzeySite\Promotions\Combination::register();
MizzeySite\Promotions\WelcomeDiscount::register();
MizzeySite\Orders\RefundIntegrity::register();

// Accounts: verification sent at registration, marketing consent, phone sign-in, sign-in links (#305). See specs/017-accounts.
MizzeySite\Accounts\VerificationOnRegistration::register();
MizzeySite\Accounts\MarketingConsent::register();
MizzeySite\Accounts\PhoneSignIn::register();
MizzeySite\Accounts\SignInLink::register();

// The catalogue import specification and validation pass, Developer-operated only: MIG-01, MIG-03, MIG-04,
// MIG-06, MIG-19, specs/006-catalogue-import-spec. Nothing is hooked on a web request.
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('mizzey catalogue', MizzeySite\Catalogue\Import\Cli\CatalogueCommand::class);
    // A preview or a validation run writes nothing to the catalogue, the load of WordPress included: the guard is
    // installed here, as this plugin loads, so WooCommerce's work on `init` is covered too.
    $mizzey_cli_arguments = WP_CLI::get_runner()->arguments;
    if (array_slice($mizzey_cli_arguments, 0, 2) === ['mizzey', 'catalogue'] && in_array($mizzey_cli_arguments[2] ?? '', ['preview', 'validate'], true)) {
        MizzeySite\Catalogue\Import\CatalogueWriteGuard::installForBootstrap();
    }
    unset($mizzey_cli_arguments);
}

// Product reviews: eligibility by delivered purchase (D-17), moderation, the rating, the invitation. REV-01 to REV-05,
// specs/020-reviews.
MizzeySite\Reviews\ReviewEligibility::register();
MizzeySite\Reviews\ReviewModeration::register();
MizzeySite\Reviews\ReviewInvitation::register();

// Customer emails: the welcome discount and the payment retry link added to WooCommerce's own, and the Shipped email.
// NOTF-01 to NOTF-13, specs/021-emails.
MizzeySite\Emails\EmailAdditions::register();
MizzeySite\Emails\Shipped::register();

// The account area: the address book, order tracking and the wishlist page. ACCT-01 to ACCT-08, specs/022-my-account.
MizzeySite\Account\AddressBook::register();
MizzeySite\Account\AccountPages::register();
// The invoice (ADM-92), downloaded by staff and from the account (ACCT-12). See specs/023-invoice.
MizzeySite\Invoices\InvoiceSettings::register();
MizzeySite\Invoices\InvoiceNumber::register();
MizzeySite\Invoices\InvoiceRoutes::register();

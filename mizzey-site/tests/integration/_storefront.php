<?php
/**
 * The storefront guard: a scenario that fetches a storefront page must be reading that page.
 *
 * Why it exists. A fresh WooCommerce answers every store page (the shop, a product, the cart, the checkout, a
 * category or brand archive) with a "coming soon" page, status 200, in the right language and direction, with a
 * title and a canonical link. The development baseline left that switch on until 5 October 2026, so a scenario
 * that fetched a store page as a visitor and checked the status, the language and the head of the document
 * passed on the placeholder. Staging met the same thing in a browser on its first run
 * (docs/2026-10-05-staging-verification.md). Status 200 is not evidence that a page rendered.
 *
 * Two checks, used together:
 *   storefront_refuse_shell()  fails the scenario, by name, when the answer is a known shell that stands in
 *                              front of a page: the commerce "coming soon" page, the maintenance page, a
 *                              WordPress error page. Every helper that fetches a page calls it.
 *   storefront_shows()         says whether the body a visitor sees holds something the requested page must
 *                              hold. It is what catches a shell nobody has listed yet.
 *
 * This guards tests. It sets nothing: whether the live store opens to visitors is the launch policy, and is not
 * decided here.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

/** Raised when a fetched answer is a shell and not the page that was asked for. */
final class StorefrontShell extends \RuntimeException {}

/**
 * Name the shell an answer is, when it is one.
 *
 * The markers do not depend on the language of the page. WooCommerce marks its "coming soon" page with a meta
 * tag whatever the language, and WordPress renders the maintenance page and every other wp_die() page in one
 * template whose body carries the id `error-page`.
 *
 * @return string What the answer is, or an empty string when it is not a known shell.
 */
function storefront_shell( int $status, string $body ): string {
	if ( 1 === preg_match( '/<meta[^>]+name=["\']woo-coming-soon-page["\']/i', $body ) ) {
		return 'the commerce "coming soon" page';
	}
	if ( 503 === $status ) {
		return 'a maintenance or "service unavailable" page (status 503)';
	}
	if ( 1 === preg_match( '/<body[^>]+id=["\']error-page["\']/i', $body ) ) {
		return 'a WordPress error page';
	}
	return '';
}

/**
 * Fail the scenario when an answer is a shell standing in front of the page.
 *
 * @throws StorefrontShell Naming the address and the shell, so the failure reads as what it is.
 */
function storefront_refuse_shell( string $url, int $status, string $body ): void {
	$shell = storefront_shell( $status, $body );
	if ( '' !== $shell ) {
		throw new StorefrontShell( "Storefront guard: {$url} answered {$status} with {$shell}, not the page that was asked for." );
	}
}

/**
 * Whether the part of a document a visitor sees holds a piece of text.
 *
 * The head is left out on purpose: the "coming soon" page carries the real page's title there, which is how a
 * check of the title passes on the placeholder.
 */
function storefront_shows( string $body, string $text ): bool {
	$visible = preg_replace( '#<head\b.*?</head>|<script\b.*?</script>|<style\b.*?</style>#is', ' ', $body );
	$visible = html_entity_decode( wp_strip_all_tags( (string) $visible ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$squash  = static fn( string $s ): string => trim( (string) preg_replace( '/\s+/u', ' ', $s ) );
	return '' !== $squash( $text ) && false !== mb_stripos( $squash( $visible ), $squash( $text ) );
}

/**
 * Whether a fetched body is the given page record: it shows the record's title, or the opening of the text
 * stored in it. A page that renders a component instead of text shows neither, and its scenario names the
 * component.
 */
function storefront_shows_page( string $body, \WP_Post $page ): bool {
	$opening = mb_substr( trim( wp_strip_all_tags( strip_shortcodes( excerpt_remove_blocks( $page->post_content ) ) ) ), 0, 60 );
	return storefront_shows( $body, $page->post_title ) || storefront_shows( $body, $opening );
}

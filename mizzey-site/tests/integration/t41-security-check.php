<?php
/**
 * t41, NFR-05 (input validation) and US-27-03: the comment surface, and the check that reports the baseline.
 *
 * AC-7: comments and pings are closed on posts and pages, while a product keeps its reviews open (REV-01 to REV-05).
 * AC-8: `wp mizzey security check` lists every control of the baseline, passes on it, reports "review" for a control
 * switched off by a filter. The command exits 0 when every control of this feature passes and 1 when one does not,
 * each run in its own process.
 *
 * The command is run for real, in its own WP-CLI process, because its exit code is part of the claim. The
 * switched-off case is in process: the report is computed from the live hooks, so removing one hook is the honest way
 * to switch a control off without touching another component's setting.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Security\BaselineReport;

require_once __DIR__ . '/_bootstrap.php';

run(
	new Scenario( 't41-security-check', 'NFR-05: comments closed outside products; the security check reports the baseline' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};

		// ---- AC-7: comment surface -----------------------------------------------------------------------------
		$post_id = wp_insert_post( array( 'post_title' => 't41 post', 'post_status' => 'publish', 'comment_status' => 'open', 'ping_status' => 'open' ) );
		$s->on_finish( static fn () => wp_delete_post( $post_id, true ) );
		$page_id = wp_insert_post( array( 'post_title' => 't41 page', 'post_type' => 'page', 'post_status' => 'publish', 'comment_status' => 'open', 'ping_status' => 'open' ) );
		$s->on_finish( static fn () => wp_delete_post( $page_id, true ) );
		$product = $s->simple_product( 't41 product', '100', null );
		$product->set_reviews_allowed( true );
		$product->save();
		$check( ! comments_open( $post_id ) && ! pings_open( $post_id ), 'AC-7: a post stored as open has comments and pings closed' );
		$check( ! comments_open( $page_id ) && ! pings_open( $page_id ), 'AC-7: a page stored as open has comments and pings closed' );
		$check( comments_open( $product->get_id() ), 'AC-7: a product with reviews allowed keeps them open' );
		$closed = $s->simple_product( 't41 product, reviews off', '100', null );
		$closed->set_reviews_allowed( false );
		$closed->save();
		$check( ! comments_open( $closed->get_id() ), 'AC-7: a product with reviews off stays closed' );

		// Over HTTP, as a visitor: the comment form endpoint and the trackback endpoint.
		$comment = function ( int $post, string $text ): int {
			$r = wp_remote_post( site_url( '/wp-comments-post.php' ), array( 'timeout' => 30, 'redirection' => 0, 'body' => array( 'comment_post_ID' => $post, 'comment' => $text, 'author' => 't41 visitor', 'email' => 't41-' . wp_rand() . '@example.invalid', 'rating' => 5 ) ) );
			return is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
		};
		$on_post = $comment( $post_id, 't41 comment on a post' );
		$check( 403 === $on_post && 0 === (int) get_comments( array( 'post_id' => $post_id, 'count' => true, 'status' => 'all' ) ), "AC-7 [HTTP] a comment on a post is refused (status {$on_post})" );
		$review = $comment( $product->get_id(), 't41 review of a product' );
		$reviews = get_comments( array( 'post_id' => $product->get_id(), 'type' => 'review', 'status' => 'all' ) );
		$check( 302 === $review && 1 === count( $reviews ) && 5 === (int) get_comment_meta( $reviews[0]->comment_ID, 'rating', true ), "AC-7 [HTTP] a product review is stored with rating 5 (status {$review})" );
		$tb = wp_remote_post( add_query_arg( 'p', $post_id, site_url( '/wp-trackback.php' ) ), array( 'timeout' => 30, 'body' => array( 'url' => 'https://example.invalid/', 'title' => 't41', 'blog_name' => 't41' ) ) );
		$tb_body = is_wp_error( $tb ) ? '' : (string) wp_remote_retrieve_body( $tb );
		$check( false !== strpos( $tb_body, '<error>1</error>' ), 'AC-7 [HTTP] a trackback is refused' );

		// ---- AC-8: the report, in process ----------------------------------------------------------------------
		$rows     = BaselineReport::rows();
		$keys     = array_column( $rows, 'key' );
		$expected = array( 'generator', 'xmlrpc', 'account_discovery', 'application_passwords', 'sign_in_message', 'recovery_limit', 'comment_surface' );
		$check( array() === array_diff( $expected, $keys ), 'AC-8: the report lists every control of this feature (' . implode( ', ', $keys ) . ')' );
		$own = array_filter( $rows, fn ( $r ) => in_array( $r['key'], $expected, true ) );
		$check( array( 'pass' ) === array_values( array_unique( array_column( $own, 'status' ) ) ), 'AC-8: every control passes on the baseline' );
		$facts = array_filter( $rows, fn ( $r ) => ! in_array( $r['key'], $expected, true ) );
		$s->note( 'fact: configuration rows reported, not judged by this feature: ' . implode( ', ', array_map( fn ( $r ) => $r['key'] . '=' . $r['status'], $facts ) ) );

		remove_all_filters( 'xmlrpc_methods' );
		$after = array_column( BaselineReport::rows(), 'status', 'key' );
		$check( 'review' === ( $after['xmlrpc'] ?? '' ), 'AC-8: with the XML-RPC control switched off, the report says review' );

		// ---- AC-8: the command, for real -----------------------------------------------------------------------
		$bin  = getenv( 'MIZZEY_WP_BIN' ) ?: 'wp';
		$cmd  = escapeshellarg( $bin ) . ' --path=' . escapeshellarg( ABSPATH ) . ' mizzey security check 2>&1';
		$out  = array();
		$code = 0;
		exec( $cmd, $out, $code );
		$text = implode( "\n", $out );
		$check( 0 === $code, "AC-8: the command exits 0 on the baseline (exit {$code})" );
		foreach ( $expected as $key ) {
			$check( false !== strpos( $text, $key ), "AC-8: the command output names {$key}" );
		}

		// The failure path, in its own process: a control switched off there, then the command run in that process.
		// Single quotes inside: on Windows, escapeshellarg() turns double quotes into spaces.
		$off  = "remove_all_filters( 'xmlrpc_methods' ); WP_CLI::run_command( array( 'mizzey', 'security', 'check' ) );";
		$out2 = array();
		$code2 = 0;
		exec( escapeshellarg( $bin ) . ' --path=' . escapeshellarg( ABSPATH ) . ' eval ' . escapeshellarg( $off ) . ' 2>&1', $out2, $code2 );
		$check( 1 === $code2 && false !== strpos( implode( "
", $out2 ), 'xmlrpc' ), "AC-8: with a control switched off, the command names it and exits 1 (exit {$code2})" );

		return $ok;
	}
);

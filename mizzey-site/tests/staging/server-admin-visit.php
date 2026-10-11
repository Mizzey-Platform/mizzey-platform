<?php
/**
 * The wp-admin visit of the baseline, for the staging copy on the server.
 *
 *     wp --url=<local staging address> eval-file server-admin-visit.php open
 *     wp --url=<local staging address> eval-file server-admin-visit.php close <user id>
 *
 * Why it is not baseline/admin-visit.php. That file asks WordPress to fetch its own Plugins page. On the server
 * the site's address is behind Cloudflare Access and PHP may make no request that leaves the machine, so the
 * page is fetched by `staging.py` on the server itself, at the local staging address. This file does the two
 * halves that need WordPress: `open` makes a temporary administrator and prints the sign-in cookies for it, and
 * `close` removes that administrator and reports the cost-field settings, as admin-visit.php does.
 *
 * Both run under `--url`, so the cookie names are the ones the local address expects.
 *
 * @package MizzeySite\Tests\Staging
 */

defined( 'ABSPATH' ) || exit;
defined( 'MIZZEY_STAGING' ) && MIZZEY_STAGING && 'staging' === wp_get_environment_type() || WP_CLI::error( 'staging only' );

$mode = (string) ( $args[0] ?? '' );

if ( 'open' === $mode ) {
	$uid = wp_insert_user(
		array(
			'user_login' => 'visit_' . wp_rand(),
			'user_pass'  => wp_generate_password( 32 ),
			'role'       => 'administrator',
			'user_email' => 'visit-' . wp_rand() . '@example.invalid',
		)
	);
	if ( is_wp_error( $uid ) ) {
		WP_CLI::error( $uid->get_error_message() );
	}
	$expiry  = time() + HOUR_IN_SECONDS;
	$token   = WP_Session_Tokens::get_instance( $uid )->create( $expiry );
	$secure  = is_ssl();
	$cookies = array(
		( $secure ? SECURE_AUTH_COOKIE : AUTH_COOKIE ) => wp_generate_auth_cookie( $uid, $expiry, $secure ? 'secure_auth' : 'auth', $token ),
		LOGGED_IN_COOKIE                               => wp_generate_auth_cookie( $uid, $expiry, 'logged_in', $token ),
	);
	echo wp_json_encode(
		array(
			'user'    => (int) $uid,
			'cookies' => $cookies,
		)
	), "\n";
	return;
}

if ( 'close' === $mode ) {
	$user = get_userdata( (int) ( $args[1] ?? 0 ) );
	// Only an account this file made is ever removed.
	if ( ! $user || 0 !== strpos( $user->user_login, 'visit_' ) ) {
		WP_CLI::error( 'no temporary visit account with that id' );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $user->ID );
	wp_cache_flush();
	$settings = get_option( 'icl_sitepress_settings' );
	$tm       = $settings['translation-management'] ?? array();
	foreach ( array( '_cogs_value', '_cogs_value_is_additive', '_cogs_total_value' ) as $key ) {
		echo $key, '=', var_export( $tm['custom_fields_translation'][ $key ] ?? 'unset', true ),
			' locked=', var_export( in_array( $key, (array) ( $tm['custom_fields_readonly_config'] ?? array() ), true ), true ), "\n";
	}
	return;
}

WP_CLI::error( 'usage: wp eval-file server-admin-visit.php <open|close <user id>>' );

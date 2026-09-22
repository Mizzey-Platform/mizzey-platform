<?php
// Baseline step (disposable runtime): load the wp-admin Plugins page once over HTTP as an administrator, as an admin
// does after activating plugins. WPML parses plugin wpml-config.xml files only on that and a few other admin
// pages (WPML_Config::load_config), so without this visit no plugin's translation config is applied. Reports the
// resulting cost-field settings and removes its temporary user.
$uid     = wp_insert_user( array( 'user_login' => 'visit_' . wp_rand(), 'user_pass' => wp_generate_password( 32 ), 'role' => 'administrator', 'user_email' => 'visit-' . wp_rand() . '@example.invalid' ) );
$expiry  = time() + HOUR_IN_SECONDS;
$token   = WP_Session_Tokens::get_instance( $uid )->create( $expiry );
$cookies = array();
foreach ( array( AUTH_COOKIE => 'auth', LOGGED_IN_COOKIE => 'logged_in' ) as $name => $scheme ) {
	$cookies[] = new WP_Http_Cookie( array( 'name' => $name, 'value' => wp_generate_auth_cookie( $uid, $expiry, $scheme, $token ) ) );
}
$r = wp_remote_get( admin_url( 'plugins.php' ), array( 'cookies' => $cookies, 'timeout' => 60, 'redirection' => 0 ) );
echo 'wp-admin plugins.php: HTTP ', is_wp_error( $r ) ? $r->get_error_message() : wp_remote_retrieve_response_code( $r ), "\n";
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $uid );
wp_cache_flush();
$s  = get_option( 'icl_sitepress_settings' );
$tm = $s['translation-management'] ?? array();
foreach ( array( '_cogs_value', '_cogs_value_is_additive', '_cogs_total_value' ) as $k ) {
	echo $k, '=', var_export( $tm['custom_fields_translation'][ $k ] ?? 'unset', true ), ' locked=', var_export( in_array( $k, (array) ( $tm['custom_fields_readonly_config'] ?? array() ), true ), true ), "\n";
}

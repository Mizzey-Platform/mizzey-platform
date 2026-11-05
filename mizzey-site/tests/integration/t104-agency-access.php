<?php
/**
 * t104, the agency's restricted access (specs/064-agency-access, #298, MKT-21 to MKT-25): what an account of the
 * Marketing (agency) role holds and is refused, over HTTP to the administration and in-process through the REST API;
 * that its content, media and tracking changes are recorded in the staff log; and that the owner and admin roles keep
 * the tracking setting. Every user, post and setting it makes or changes is removed or restored when it finishes.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Marketing\TagManager;
use MizzeySite\Roles\MarketingRole;
use MizzeySite\Security\StaffActivityLog;
use MizzeySite\Security\StaffActivityScreen;

require_once __DIR__ . '/_bootstrap.php';

/** @return array{0:\WP_User,1:array<int,\WP_Http_Cookie>} a new account of the role, signed in */
function t104_account( Scenario $s, string $role ): array {
	$id = wp_insert_user( array( 'user_login' => 't104_' . $role . '_' . wp_rand(), 'user_pass' => wp_generate_password( 32 ), 'role' => $role, 'user_email' => 't104-' . wp_rand() . '@example.invalid' ) );
	$s->track_user( $id );
	$user = get_userdata( $id );
	// Two-factor sign-in is required for every staff role, the agency role included (specs/064): enrolled, as staff are.
	if ( class_exists( '\WP2FA\Methods\TOTP' ) ) {
		require_once __DIR__ . '/_two_factor.php';
		two_factor_enrol( $user );
	}
	$expiry  = time() + HOUR_IN_SECONDS;
	$token   = \WP_Session_Tokens::get_instance( $id )->create( $expiry );
	$cookies = array();
	foreach ( array( AUTH_COOKIE => 'auth', SECURE_AUTH_COOKIE => 'secure_auth', LOGGED_IN_COOKIE => 'logged_in' ) as $name => $scheme ) {
		$cookies[] = new \WP_Http_Cookie( array( 'name' => $name, 'value' => wp_generate_auth_cookie( $id, $expiry, $scheme, $token ) ) );
	}
	return array( $user, $cookies );
}

/** @return array{status:int,body:string,cookies:array} */
function t104_admin( string $path, array $cookies, ?array $post = null ): array {
	$args = array( 'cookies' => $cookies, 'timeout' => 120, 'redirection' => 0 );
	$r    = null === $post
		? wp_remote_get( admin_url( $path ), $args ) // storefront-guard: exempt, an admin screen and not a storefront page.
		: wp_remote_post( admin_url( $path ), $args + array( 'body' => $post ) ); // storefront-guard: exempt, an admin form.
	return is_wp_error( $r ) ? array( 'status' => 0, 'body' => $r->get_error_message(), 'cookies' => array() ) : array( 'status' => (int) wp_remote_retrieve_response_code( $r ), 'body' => (string) wp_remote_retrieve_body( $r ), 'cookies' => wp_remote_retrieve_cookies( $r ) );
}

/** Whether an admin answer let the account in: a 200 that is not WordPress's "not allowed" page. */
function t104_let_in( array $answer ): bool {
	return 200 === $answer['status'] && ! str_contains( $answer['body'], 'not allowed to' ) && ! str_contains( $answer['body'], 'id="error-page"' );
}

run(
	new Scenario( 't104-agency-access', 'MKT-21 to MKT-25: the agency works through a restricted role' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$container_before = get_option( TagManager::CONTAINER, null );
		$s->on_finish( static function () use ( $container_before ) {
			null === $container_before ? delete_option( TagManager::CONTAINER ) : update_option( TagManager::CONTAINER, $container_before );
			wp_set_current_user( 0 );
		} );
		// Written by the plugin itself: the role and its stamp are removed first, so this run sees them made.
		remove_role( MarketingRole::ROLE );
		get_role( 'administrator' )->remove_cap( MarketingRole::TRACKING );
		get_role( 'shop_manager' )->remove_cap( MarketingRole::TRACKING );
		delete_option( MarketingRole::STORED );
		wp_cache_delete( 'alloptions', 'options' );
		MarketingRole::install();
		$role = get_role( MarketingRole::ROLE );
		$check( $role instanceof \WP_Role, 'setup: the Marketing (agency) role exists' );
		[ $agency, $cookies ] = t104_account( $s, MarketingRole::ROLE );

		// ---- AC-1 MKT-21: no software, templates or files ------------------------------------------------------------
		$forbidden = array( 'install_plugins', 'activate_plugins', 'update_plugins', 'delete_plugins', 'edit_plugins', 'install_themes', 'switch_themes', 'edit_themes', 'update_themes', 'delete_themes', 'edit_theme_options', 'edit_files', 'update_core', 'import', 'export', 'unfiltered_html', 'unfiltered_upload', 'manage_options' );
		$held      = array_values( array_filter( $forbidden, static fn ( $cap ) => user_can( $agency, $cap ) ) );
		$check( array() === $held, 'AC-1 MKT-21 the role holds no plugin, theme, template, file, update, import or export capability, and not unfiltered_html (' . implode( ', ', $held ) . ')' );
		$screens = array();
		foreach ( array( 'plugins.php', 'plugin-install.php', 'themes.php', 'theme-editor.php', 'plugin-editor.php', 'site-editor.php', 'nav-menus.php', 'options-general.php' ) as $screen ) {
			$screens[ $screen ] = t104_let_in( t104_admin( $screen, $cookies ) );
		}
		$check( array() === array_keys( array_filter( $screens ) ), '[HTTP] AC-1 the plugin, theme, editor, site editor, menu and settings screens all refuse the account (' . wp_json_encode( array_keys( array_filter( $screens ) ) ) . ')' );

		// ---- AC-2 MKT-22: no customer data -------------------------------------------------------------------------
		$personal = array( 'list_users', 'edit_users', 'create_users', 'promote_users', 'delete_users', 'edit_shop_orders', 'read_shop_order', 'manage_woocommerce', 'view_woocommerce_reports', 'edit_products' );
		$held2    = array_values( array_filter( $personal, static fn ( $cap ) => user_can( $agency, $cap ) ) );
		$check( array() === $held2, 'AC-2 MKT-22 the role holds no user, order, customer, report or product capability (' . implode( ', ', $held2 ) . ')' );
		$data = array();
		// WooCommerce's analytics screen (wc-admin) is a shell that reads its figures from the wc-analytics routes, checked below.
		foreach ( array( 'users.php', 'admin.php?page=wc-orders', 'admin.php?page=wc-reports', 'admin.php?page=wc-settings' ) as $screen ) {
			$data[ $screen ] = t104_let_in( t104_admin( $screen, $cookies ) );
		}
		$check( array() === array_keys( array_filter( $data ) ), '[HTTP] AC-2 the users, orders, customers and reports screens all refuse the account (' . wp_json_encode( array_keys( array_filter( $data ) ) ) . ')' );
		wp_set_current_user( $agency->ID );
		$users_rest     = rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/users' ) )->get_status();
		$customers_rest = rest_do_request( new \WP_REST_Request( 'GET', '/wc/v3/customers' ) )->get_status();
		$orders_rest    = rest_do_request( new \WP_REST_Request( 'GET', '/wc/v3/orders' ) )->get_status();
		$analytics_rest = rest_do_request( new \WP_REST_Request( 'GET', '/wc-analytics/reports/customers' ) )->get_status();
		$analytics_ord  = rest_do_request( new \WP_REST_Request( 'GET', '/wc-analytics/orders' ) )->get_status();
		$edit_users     = new \WP_REST_Request( 'GET', '/wp/v2/users' );
		$edit_users->set_param( 'context', 'edit' );
		$edit_status = rest_do_request( $edit_users )->get_status();
		wp_set_current_user( 0 );
		$check( in_array( $customers_rest, array( 401, 403 ), true ) && in_array( $orders_rest, array( 401, 403 ), true ) && in_array( $edit_status, array( 401, 403 ), true ) && in_array( $analytics_rest, array( 401, 403 ), true ) && in_array( $analytics_ord, array( 401, 403 ), true ), 'AC-2 over the REST API the account reads no customers, no orders, no analytics of either, and no user record in edit context (' . "{$customers_rest}, {$orders_rest}, {$analytics_rest}, {$analytics_ord}, {$edit_status}; public user list {$users_rest}" . ')' );

		// ---- AC-3 MKT-23: Tag Manager is the route for tracking ----------------------------------------------------
		$form = t104_admin( 'options-general.php?page=' . TagManager::PAGE, $cookies );
		preg_match( '/name="_wpnonce" value="([^"]+)"/', $form['body'], $nonce );
		$saved = t104_admin( 'options.php', array_merge( $cookies, $form['cookies'] ), array( 'option_page' => TagManager::PAGE, 'action' => 'update', '_wpnonce' => $nonce[1] ?? '', '_wp_http_referer' => '/wp-admin/options-general.php?page=' . TagManager::PAGE, TagManager::CONTAINER => 'GTM-T104AB' ) );
		wp_cache_delete( TagManager::CONTAINER, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		$check( t104_let_in( $form ) && '' !== ( $nonce[1] ?? '' ) && 'GTM-T104AB' === get_option( TagManager::CONTAINER ), '[HTTP] AC-3 MKT-23 the account opens Settings, Marketing tags and names the Tag Manager container (' . $form['status'] . ', ' . $saved['status'] . ', ' . wp_json_encode( get_option( TagManager::CONTAINER ) ) . ')' );
		wp_set_current_user( $agency->ID );
		kses_init();
		$script_post = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 't104 scripted', 'post_content' => '<p>Hello</p><script>window.t104=1</script>' ) );
		$s->track_post( (int) $script_post );
		wp_set_current_user( 0 );
		kses_init();
		$check( $script_post > 0 && ! str_contains( (string) get_post_field( 'post_content', $script_post ), '<script' ), 'AC-3 a script put into a page by the account is taken out when it is saved: Tag Manager is the only route' );

		// ---- AC-4: content, media and banners ----------------------------------------------------------------------
		wp_set_current_user( $agency->ID );
		$page_request = new \WP_REST_Request( 'POST', '/wp/v2/pages' );
		$page_request->set_body_params( array( 'title' => 't104 campaign page', 'content' => '<p>Autumn offers</p>', 'status' => 'publish' ) );
		$page_answer = rest_do_request( $page_request );
		$page_id     = (int) ( $page_answer->get_data()['id'] ?? 0 );
		$s->track_post( $page_id );
		$update = new \WP_REST_Request( 'POST', '/wp/v2/pages/' . $page_id );
		$update->set_body_params( array( 'title' => 't104 campaign page, edited' ) );
		$updated = rest_do_request( $update )->get_status();
		$banner  = wp_insert_post( array( 'post_type' => \MizzeySite\Storefront\Promotions::TYPE, 'post_status' => 'draft', 'post_title' => 't104 banner' ) );
		$s->track_post( (int) $banner );
		$can_banner = current_user_can( 'edit_post', $banner ) && current_user_can( 'publish_pages' );
		$can_media  = current_user_can( 'upload_files' );
		$attachment = wp_insert_attachment( array( 'post_title' => 't104 image', 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), false );
		$s->track_post( (int) $attachment );
		wp_set_current_user( 0 );
		$check( 201 === $page_answer->get_status() && 'publish' === get_post_status( $page_id ) && 200 === $updated && $can_banner && $can_media, 'AC-4 the account publishes and edits a page, edits a promotional banner and uploads media (' . $page_answer->get_status() . ', ' . $updated . ')' );

		// ---- AC-5 MKT-25: its actions are in the staff log --------------------------------------------------------
		$log   = static fn ( string $kind ) => (array) ( StaffActivityScreen::entries( array( 'account' => $agency->user_login, 'kind' => $kind, 'outcome' => '', 'from' => '', 'to' => '', 'page' => 1 ) ) ?? array() );
		$saves = $log( StaffActivityLog::AGENCY_CONTENT );
		$media = $log( StaffActivityLog::AGENCY_MEDIA );
		$track = $log( StaffActivityLog::AGENCY_TRACKING );
		$check( count( $saves ) >= 3 && count( $media ) >= 1 && count( $track ) >= 1, 'AC-5 MKT-25 the staff log holds the account\'s page saves, its media and its tracking change, under its name (' . count( $saves ) . ', ' . count( $media ) . ', ' . count( $track ) . ')' );
		[ $manager ] = t104_account( $s, 'shop_manager' );
		wp_set_current_user( $manager->ID );
		wp_update_post( array( 'ID' => $page_id, 'post_title' => 't104 edited by the admin' ) );
		wp_set_current_user( 0 );
		$managers = (array) ( StaffActivityScreen::entries( array( 'account' => $manager->user_login, 'kind' => StaffActivityLog::AGENCY_CONTENT, 'outcome' => '', 'from' => '', 'to' => '', 'page' => 1 ) ) ?? array() );
		$check( count( $log( StaffActivityLog::AGENCY_CONTENT ) ) === count( $saves ) && array() === $managers, 'AC-5 an admin\'s edit of the same page is not recorded as the agency\'s, under either name (' . count( $managers ) . ')' );

		// ---- S-1: two-factor sign-in is required for the agency role (approved by the owner, 5 November 2026) -----
		$check( in_array( MarketingRole::ROLE, \MizzeySite\Security\BaselineReport::TWO_FACTOR_ROLES, true ), 'S-1 the security baseline requires two-factor sign-in for the agency role, as for administrators and shop managers' );

		// ---- AC-6: the owner and admin roles keep the setting ------------------------------------------------------
		$check( get_role( 'administrator' )->has_cap( MarketingRole::TRACKING ) && get_role( 'shop_manager' )->has_cap( MarketingRole::TRACKING ), 'AC-6 the owner and admin roles hold the tracking setting, as they did through manage_woocommerce' );

		return $ok;
	}
);

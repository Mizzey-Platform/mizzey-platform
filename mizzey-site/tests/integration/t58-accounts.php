<?php
/**
 * t58, accounts (specs/017-accounts, #305): registration with no more than email, password and consent, the
 * verification email sent at registration and guest orders linked only after verification, marketing consent kept
 * apart, registration and sign-in with a mobile number, sign-in and password reset, the welcome discount at
 * registration, the order-received account offer carrying the order's details, and the passwordless sign-in link.
 * In-process, and over HTTP for the account page's sign-in, the offer and the link. Every user, order and coupon it makes
 * is removed when it finishes.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Accounts\MarketingConsent;
use MizzeySite\Accounts\PhoneSignIn;
use MizzeySite\Accounts\SignInLink;
use MizzeySite\Accounts\VerificationOnRegistration;
use MizzeySite\Promotions\WelcomeDiscount;
use MizzeySite\Security\RecoveryLimiter;
use MizzeySite\Security\SignupCheck;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_sign_in.php';
require_once __DIR__ . '/_two_factor.php';

/** Create a customer the way the account page's registration does, with the posted extras. */
function t58_register( Scenario $s, string $email, string $password, array $post = array() ): int {
	$_POST = $post;
	$id    = wc_create_new_customer( $email, '', $password );
	$_POST = array();
	if ( is_wp_error( $id ) ) {
		return 0;
	}
	$s->track_user( (int) $id );
	$own = (int) get_user_meta( (int) $id, WelcomeDiscount::USER_META, true );
	if ( $own > 0 ) {
		$s->on_finish( static function () use ( $own ) { wp_delete_post( $own, true ); } );
	}
	return (int) $id;
}

run(
	new Scenario( 't58-accounts', 'AUTH-01 to AUTH-09, AUTH-11, AUTH-12, AUTH-14, AUTH-16: accounts' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$previous = get_current_user_id();
		$s->on_finish( static function () use ( $previous ) { wp_set_current_user( $previous ); } );
		wp_set_current_user( 0 );
		sign_in_options(
			$s,
			array(
				'woocommerce_enable_myaccount_registration'         => 'yes',
				'woocommerce_registration_generate_username'        => 'yes',
				'woocommerce_registration_generate_password'        => 'no',
				'woocommerce_enable_guest_checkout'                 => 'yes',
				'woocommerce_enable_delayed_account_creation'       => 'yes',
				'woocommerce_enable_signup_and_login_from_checkout' => 'yes',
			)
		);
		$tag     = 't58' . wp_rand( 1000, 9999 );
		$product = $s->simple_product( 't58 serum', '300', null );
		$sent    = array();
		$catch   = static function ( $user_id, $url ) use ( &$sent ) { $sent[ (int) $user_id ] = (string) $url; };
		add_action( 'woocommerce_customer_verify_email_notification', $catch, 1, 2 );
		$mails = array();
		$trap  = static function ( $short, $atts ) use ( &$mails ) { $mails[] = $atts; return true; };
		add_filter( 'pre_wp_mail', $trap, 10, 2 );
		$s->on_finish( static function () use ( $catch, $trap ) {
			remove_action( 'woocommerce_customer_verify_email_notification', $catch, 1 );
			remove_filter( 'pre_wp_mail', $trap, 10 );
		} );

		// AUTH-01, AUTH-14: the registration form asks for no more than email, password and consent.
		ob_start();
		wc_get_template( 'myaccount/form-login.php' );
		$form     = (string) ob_get_clean();
		$register = substr( $form, (int) strpos( $form, 'woocommerce-form-register' ) );
		preg_match_all( '/<input[^>]*\brequired\b[^>]*>|<input[^>]*name="(email|password)"[^>]*>/i', $register, $inputs );
		$named    = array();
		foreach ( $inputs[0] as $input ) {
			if ( preg_match( '/name="([^"]+)"/', $input, $m ) ) {
				$named[] = $m[1];
			}
		}
		$consent_box = preg_match( '/name="' . MarketingConsent::FIELD . '"[^>]*>/', $register, $box ) ? $box[0] : '';
		$check( array( 'email', 'password' ) === array_values( array_unique( array_intersect( $named, array( 'email', 'password', 'username', 'billing_phone' ) ) ) ) && false === strpos( $register, 'name="username"' ) && '' !== $consent_box && false === stripos( $consent_box, 'checked' ) && false === stripos( $consent_box, 'required' ), 'AUTH-01, AUTH-14 the registration form asks for an email and a password; the mobile number and marketing consent are optional, the consent unticked' );

		// AUTH-09, AUTH-05: consent kept apart; the verification email sent at registration.
		$quiet  = t58_register( $s, "{$tag}-quiet@example.invalid", 'Pw-' . wp_generate_password( 18, false ) );
		$keen   = t58_register( $s, "{$tag}-keen@example.invalid", 'Pw-' . wp_generate_password( 18, false ), array( MarketingConsent::FIELD => 'yes' ) );
		$q_meta = get_user_meta( $quiet, MarketingConsent::META, true );
		$k_meta = get_user_meta( $keen, MarketingConsent::META, true );
		$check( $quiet && $keen && ! MarketingConsent::given( $quiet ) && is_array( $q_meta ) && MarketingConsent::given( $keen ) && 'registration' === ( $k_meta['source'] ?? '' ) && abs( (int) ( $k_meta['at'] ?? 0 ) - time() ) < 60, 'AUTH-09 registering records no consent unless the box is ticked; ticked, the consent is kept with when and where it was given' );
		$check( isset( $sent[ $quiet ] ) && false !== strpos( $sent[ $quiet ], 'wc_verify_email_key' ) && ! VerificationOnRegistration::verified( $quiet ), 'AUTH-05 the verification email is sent when the account is created, and the account is not verified yet' );

		// AUTH-08, B14: a guest's order is linked only once the email is verified.
		$guest_email = "{$tag}-g@example.invalid";
		$guest_order = wc_create_order();
		$guest_order->add_product( $product, 1 );
		$guest_order->set_billing_email( $guest_email );
		$guest_order->calculate_totals();
		$guest_order->set_status( 'processing' );
		$guest_order->save();
		$s->track_order( $guest_order->get_id() );
		$joiner  = t58_register( $s, $guest_email, 'Pw-' . wp_generate_password( 18, false ) );
		$before  = (int) wc_get_order( $guest_order->get_id() )->get_customer_id();
		$url     = $sent[ $joiner ] ?? '';
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		$class   = '\\Automattic\\WooCommerce\\Internal\\CustomerEmailVerification\\VerificationController';
		wp_set_current_user( $joiner );
		$verified = wc_get_container()->get( $class )->process_verification( $joiner, (string) ( $query['wc_verify_email_key'] ?? '' ) );
		wp_set_current_user( 0 );
		wp_cache_flush();
		$after = (int) wc_get_order( $guest_order->get_id() )->get_customer_id();
		$check( 0 === $before && $verified && VerificationOnRegistration::verified( $joiner ) && $joiner === $after, "AUTH-05, AUTH-08 the guest's earlier order stays unlinked until the emailed key verifies the account, then is linked to it ({$before} then {$after})" );

		// AUTH-02: a mobile number at registration, one per account, and for signing in.
		$password = 'Pw-' . wp_generate_password( 18, false );
		$mobile   = t58_register( $s, "{$tag}-m@example.invalid", $password, array( PhoneSignIn::FIELD => '+20 101 234 5678' ) );
		$errors   = apply_filters( 'woocommerce_registration_errors', new \WP_Error(), '', "{$tag}-x@example.invalid" );
		$_POST    = array( PhoneSignIn::FIELD => '01012345678' );
		$taken    = apply_filters( 'woocommerce_registration_errors', new \WP_Error(), '', "{$tag}-y@example.invalid" );
		$_POST    = array( PhoneSignIn::FIELD => '12345' );
		$bad      = apply_filters( 'woocommerce_registration_errors', new \WP_Error(), '', "{$tag}-z@example.invalid" );
		$_POST    = array();
		$check( '01012345678' === get_user_meta( $mobile, PhoneSignIn::FIELD, true ) && ! $errors->has_errors() && ! $taken->has_errors() && in_array( 'mizzey_phone_invalid', $bad->get_error_codes(), true ), 'AUTH-02 a mobile number given at registration is kept in its local form; a number another account holds is not refused, so the form does not say whose it is; a malformed one is refused' );
		$by_phone = wp_authenticate( '0020 10 1234 5678', $password );
		$wrong    = wp_authenticate( '01012345678', 'not-the-password' );
		$nobody   = wp_authenticate( '01099999999', $password );
		$check( $by_phone instanceof \WP_User && $mobile === $by_phone->ID && is_wp_error( $wrong ) && is_wp_error( $nobody ), 'AUTH-02 the mobile number signs in with the account\'s password; a wrong password or an unknown number signs in to nothing' );
		sign_in_forget( array( '01012345678', '01099999999' ) );
		$spelled = '010-1234 5678';
		$secret  = 'x';
		do_action_ref_array( 'wp_authenticate', array( &$spelled, &$secret ) );
		$check( get_userdata( $mobile )->user_login === $spelled && get_userdata( $mobile )->user_login === PhoneSignIn::canonical( '+20 10 1234 5678' ) && '01099999999' === PhoneSignIn::canonical( '0020-109-999-9999' ), 'AUTH-02 every spelling of a held number reaches sign-in, and CoreX\'s lockout, as its account\'s username; an unheld number as its one spelling' );
		$lookalike = (int) wp_insert_user( array( 'user_login' => '01012345678', 'user_email' => "{$tag}-look@example.invalid", 'user_pass' => $password, 'role' => 'customer' ) );
		$s->track_user( $lookalike );
		$as_number = wp_authenticate( '01012345678', $password );
		$generated = (string) apply_filters( 'woocommerce_new_customer_username', '01012345678', "01012345678@example.invalid", array(), '' );
		$check( $as_number instanceof \WP_User && $mobile === $as_number->ID && '01012345678' !== $generated && null === PhoneSignIn::normalise( $generated ), 'AUTH-02 a number signs in to the account holding it, not to a username spelled like it; no generated username is spelled like a number (' . $generated . ')' );
		$second = t58_register( $s, "{$tag}-second@example.invalid", $password, array( PhoneSignIn::FIELD => '01012345678' ) );
		$check( $second > 0 && '01012345678' === get_user_meta( $second, PhoneSignIn::FIELD, true ) && $mobile === PhoneSignIn::holder( '01012345678' ) && '' === get_user_meta( $second, PhoneSignIn::HELD, true ), 'AUTH-02 an account registered with a number another account holds keeps it for delivery and does not sign in with it' );
		$wpdb_stale = $GLOBALS['wpdb'];
		$wpdb_stale->insert( $wpdb_stale->usermeta, array( 'user_id' => $second, 'meta_key' => PhoneSignIn::HELD, 'meta_value' => '01012345678' ) );
		wp_cache_flush();
		PhoneSignIn::release( $second );
		$check( $mobile === PhoneSignIn::holder( '01012345678' ), 'AUTH-02 a stale release by an account that no longer owns a number leaves the claim of the account that holds it' );
		$late = (int) wp_insert_user( array( 'user_login' => "{$tag}-late", 'user_email' => "{$tag}-late@example.invalid", 'user_pass' => $password, 'role' => 'customer' ) );
		$s->track_user( $late );
		$shared = (int) wp_insert_user( array( 'user_login' => "{$tag}-shared", 'user_email' => "{$tag}-shared@example.invalid", 'user_pass' => $password, 'role' => 'customer' ) );
		$s->track_user( $shared );
		global $wpdb;
		foreach ( array( array( $late, '01122233344' ), array( $shared, '01155566677' ), array( $second, '01155566677' ) ) as $raw ) {
			$wpdb->insert( $wpdb->usermeta, array( 'user_id' => $raw[0], 'meta_key' => PhoneSignIn::FIELD, 'meta_value' => $raw[1] ) );
		}
		wp_cache_flush();
		delete_option( PhoneSignIn::BACKFILLED );
		PhoneSignIn::backfill();
		$check( $late === PhoneSignIn::holder( '01122233344' ) && null === PhoneSignIn::holder( '01155566677' ) && $mobile === PhoneSignIn::holder( '01012345678' ), 'AUTH-02 numbers saved before claims existed are claimed once by their one holder; a number two accounts saved is claimed by neither; a claimed number is left as it is' );
		$s->on_finish( static function () { delete_option( PhoneSignIn::CLAIM . '_01122233344' ); } );
		$numbered = (int) wp_insert_user( array( 'user_login' => '01122233344', 'user_email' => "{$tag}-num@example.invalid", 'user_pass' => $password, 'role' => 'customer' ) );
		$s->track_user( $numbered );
		update_user_meta( $numbered, PhoneSignIn::FIELD, '01099988877' );
		$typed = '01099988877';
		$pass  = $password;
		do_action_ref_array( 'wp_authenticate', array( &$typed, &$pass ) );
		$once  = apply_filters( 'authenticate', null, $typed, $password );
		$check( '01122233344' === $typed && $once instanceof \WP_User && $numbered === $once->ID, 'AUTH-02 a number is resolved once: an account whose username is another holder\'s number signs in as itself, not as that holder' );
		$leaver = t58_register( $s, "{$tag}-leaver@example.invalid", $password, array( PhoneSignIn::FIELD => '01277788899' ) );
		$held_before = PhoneSignIn::holder( '01277788899' );
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $leaver );
		$after_delete = get_option( PhoneSignIn::CLAIM . '_01277788899' );
		update_option( PhoneSignIn::CLAIM . '_01277788899', '999999999', false );
		$heir = t58_register( $s, "{$tag}-heir@example.invalid", $password, array( PhoneSignIn::FIELD => '01277788899' ) );
		$check( $leaver === $held_before && false === $after_delete && $heir === PhoneSignIn::holder( '01277788899' ), 'AUTH-02 deleting an account releases its number; a claim left by an account that no longer holds it is taken over by the next account to save the number' );
		$other = t58_register( $s, "{$tag}-other@example.invalid", $password );
		update_user_meta( $other, PhoneSignIn::FIELD, '+201012345678' );
		$kept_holder = PhoneSignIn::holder( '01012345678' );
		update_user_meta( $mobile, PhoneSignIn::FIELD, '01155555555' );
		$moved = PhoneSignIn::holder( '01155555555' );
		$freed = PhoneSignIn::holder( '01012345678' );
		update_user_meta( $mobile, PhoneSignIn::FIELD, '01012345678' );
		$check( $mobile === $kept_holder && $mobile === $moved && null === $freed && $mobile === PhoneSignIn::holder( '01012345678' ), 'AUTH-02 another account saving a held number in another spelling does not take it; the holder changing number frees the old one and holds the new one' );
		delete_option( PhoneSignIn::CLAIM . '_01155555555' );

		// AUTH-03: sign-in over HTTP, and a failure that does not say whether the email exists.
		$session  = sign_in_account( 'en', "{$tag}-m@example.invalid", $password );
		$no_one   = sign_in_account( 'en', "{$tag}-nobody@example.invalid", 'Pw-whatever-1' );
		$bad_pass = sign_in_account( 'en', "{$tag}-m@example.invalid", 'Pw-whatever-1' );
		$generic  = 'Invalid username, email address or incorrect password';
		$check( sign_in_has_session( $session ) && ! sign_in_has_session( $no_one ) && ! sign_in_has_session( $bad_pass ) && false !== strpos( $no_one['body'], $generic ) && false !== strpos( $bad_pass['body'], $generic ), '[HTTP] AUTH-03 signing in on the account page starts a session; an unknown email and a wrong password both get the one generic message (as t40 checks in both languages)' );
		sign_in_forget( array( "{$tag}-m@example.invalid", "{$tag}-nobody@example.invalid" ) );

		// AUTH-04, AUTH-16: the reset link works once, and not after it expires.
		$user = get_userdata( $quiet );
		$key  = get_password_reset_key( $user );
		$good = check_password_reset_key( $key, $user->user_login );
		reset_password( $user, 'Pw-' . wp_generate_password( 18, false ) );
		$used = check_password_reset_key( $key, get_userdata( $quiet )->user_login );
		$short = static fn () => 1;
		add_filter( 'password_reset_expiration', $short );
		$late = get_password_reset_key( get_userdata( $quiet ) );
		sleep( 2 );
		$expired = check_password_reset_key( $late, get_userdata( $quiet )->user_login );
		remove_filter( 'password_reset_expiration', $short );
		$check( $good instanceof \WP_User && is_wp_error( $used ) && is_wp_error( $expired ) && 'expired_key' === $expired->get_error_code(), 'AUTH-04, AUTH-16 a reset key works, is spent by resetting the password, and is refused once it expires' );

		// AUTH-06: the welcome discount on registration, by #290's template.
		$template = new \WC_Coupon();
		$template->set_code( "{$tag}welcome" );
		$template->set_props( array( 'discount_type' => 'percent', 'amount' => '10', 'individual_use' => true ) );
		$template->update_meta_data( WelcomeDiscount::TEMPLATE, 'yes' );
		$template->update_meta_data( WelcomeDiscount::DAYS, '14' );
		$template->save();
		$s->on_finish( static function () use ( $template ) { wp_delete_post( $template->get_id(), true ); } );
		$welcomed = t58_register( $s, "{$tag}-w@example.invalid", 'Pw-' . wp_generate_password( 18, false ) );
		$check( 0 < (int) get_user_meta( $welcomed, WelcomeDiscount::USER_META, true ), 'AUTH-06 registering applies the welcome discount the template configures (#290)' );

		// AUTH-07, AUTH-11: a guest order, then the order-received offer with a password only, carrying the details.
		$offer_email = "{$tag}-o@example.invalid";
		$order       = wc_create_order();
		$order->add_product( $product, 1 );
		$order->set_address( array( 'first_name' => 'Nour', 'last_name' => 'Adel', 'phone' => '01112345678', 'address_1' => '12 Nile Street', 'city' => 'Giza', 'state' => 'EGGZ', 'country' => 'EG', 'email' => $offer_email ), 'billing' );
		$order->calculate_totals();
		$order->set_status( 'processing' );
		$order->save();
		$s->track_order( $order->get_id() );
		two_factor_http( 'POST', $order->get_checkout_order_received_url(), array( 'create-account' => '1', 'email' => $offer_email, 'password' => 'Pw-' . wp_generate_password( 18, false ), '_wpnonce' => wp_create_nonce( 'wc_create_account' ), 'mizzey_signup_token' => SignupCheck::issue( 'order-received', time() - 10 ), 'mizzey_signup_extra' => '' ) );
		wp_cache_flush();
		$made = get_user_by( 'email', $offer_email );
		if ( $made ) {
			$s->track_user( $made->ID );
		}
		$customer = $made ? new \WC_Customer( $made->ID ) : null;
		$check( 'yes' === get_option( 'woocommerce_enable_guest_checkout' ) && 0 === (int) $order->get_customer_id() && $made && $made->ID === (int) wc_get_order( $order->get_id() )->get_customer_id() && $customer && 'Nour' === $customer->get_billing_first_name() && '01112345678' === $customer->get_billing_phone() && '12 Nile Street' === $customer->get_billing_address_1(), '[HTTP] AUTH-07, AUTH-11 a guest order, then a password alone on the confirmation page: the account carries the name, phone and address and holds the order' );

		// AUTH-12: the passwordless sign-in link.
		$mails = array();
		SignInLink::request( "{$tag}-keen@example.invalid" );
		$link  = preg_match( '/https?:\/\/\S+/', (string) ( $mails[0]['message'] ?? '' ), $found ) ? $found[0] : '';
		parse_str( (string) wp_parse_url( $link, PHP_URL_QUERY ), $parts );
		$token = (string) ( $parts[ SignInLink::PARAM ] ?? '' );
		$wrong_token = SignInLink::redeem( $keen, 'not-the-token' );
		$first       = SignInLink::redeem( $keen, $token );
		$second      = SignInLink::redeem( $keen, $token );
		$check( 1 === count( $mails ) && "{$tag}-keen@example.invalid" === ( $mails[0]['to'] ?? '' ) && '' !== $token && ! $wrong_token && $first && ! $second, 'AUTH-12 a customer asking gets one link by email; a wrong token does not spend it; it works once' );
		$mails = array();
		SignInLink::request( "{$tag}-keen@example.invalid" );
		update_user_meta( $keen, SignInLink::META, strtok( (string) get_user_meta( $keen, SignInLink::META, true ), '|' ) . '|' . ( time() - 1 ) );
		$late_link = preg_match( '/mizzey_sign_in=([A-Za-z0-9]+)/', (string) ( $mails[0]['message'] ?? '' ), $lt ) ? $lt[1] : '';
		$check( '' !== $late_link && ! SignInLink::redeem( $keen, $late_link ), 'AUTH-12 an expired link signs in to nothing' );
		$mails = array();
		$staff = (int) wp_insert_user( array( 'user_login' => "{$tag}-staff", 'user_email' => "{$tag}-staff@example.invalid", 'user_pass' => wp_generate_password( 24 ), 'role' => 'shop_manager' ) );
		$s->track_user( $staff );
		SignInLink::request( "{$tag}-staff@example.invalid" );
		SignInLink::request( "{$tag}-nobody@example.invalid" );
		$req     = new \WP_REST_Request( 'POST', '/mizzey/v1/sign-in-link' );
		$req->set_param( 'email', "{$tag}-quiet@example.invalid" );
		$known   = rest_do_request( $req );
		$req2    = new \WP_REST_Request( 'POST', '/mizzey/v1/sign-in-link' );
		$req2->set_param( 'email', "{$tag}-unknown@example.invalid" );
		$unknown = rest_do_request( $req2 );
		$before  = count( $mails );
		SignInLink::flush();
		$check( 0 === $before && false !== has_action( 'shutdown', array( SignInLink::class, 'afterAnswer' ) ) && 1 === count( $mails ) && 202 === $known->get_status() && $known->get_data() === $unknown->get_data() && 202 === $unknown->get_status(), 'AUTH-12 staff are sent no link; for a known and an unknown email the answer is the same, and the link is sent only after the answer' );
		$mails     = array();
		$w_login   = get_userdata( $welcomed )->user_login;
		$recovered = array( retrieve_password( $w_login ), retrieve_password( $w_login ) );
		SignInLink::request( "{$tag}-w@example.invalid" );
		SignInLink::request( "{$tag}-w@example.invalid" );
		$limited   = retrieve_password( $w_login );
		$check( true === $recovered[0] && true === $recovered[1] && 3 === count( $mails ) && is_wp_error( $limited ) && in_array( RecoveryLimiter::ERROR, $limited->get_error_codes(), true ), 'AUTH-12 links and password resets share one limit: two resets and a link reach three; a second link and a third reset are refused (' . count( $mails ) . ' mails)' );
		RecoveryLimiter::forget( $keen, '127.0.0.1' );
		$mails = array();
		SignInLink::request( "{$tag}-keen@example.invalid" );
		$bound = preg_match( '/mizzey_sign_in=([A-Za-z0-9]+)/', (string) ( $mails[0]['message'] ?? '' ), $bt ) ? $bt[1] : '';
		wp_set_password( 'Pw-' . wp_generate_password( 18, false ), $keen );
		clean_user_cache( $keen );
		$check( '' !== $bound && ! SignInLink::redeem( $keen, $bound ), 'AUTH-12 a link sent before the password changed signs in to nothing' );
		$mails = array();
		SignInLink::request( "{$tag}-keen@example.invalid" );
		$raced = preg_match( '/mizzey_sign_in=([A-Za-z0-9]+)/', (string) ( $mails[0]['message'] ?? '' ), $rt ) ? $rt[1] : '';
		$fired = false;
		$rival = static function ( $query ) use ( $keen, &$fired ) {
			global $wpdb;
			if ( ! $fired && is_string( $query ) && str_starts_with( ltrim( $query ), 'DELETE' ) && false !== strpos( $query, SignInLink::META ) ) {
				$fired = true;
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s", $keen, SignInLink::META ) );
			}
			return $query;
		};
		add_filter( 'query', $rival );
		$lost = SignInLink::redeem( $keen, $raced );
		remove_filter( 'query', $rival );
		$check( '' !== $raced && ! $lost, 'AUTH-12 of two requests holding one live link, the one whose delete finds it already spent does not sign in' );
		RecoveryLimiter::forget( $welcomed, '127.0.0.1' );
		RecoveryLimiter::forget( $keen, '127.0.0.1' );
		RecoveryLimiter::forget( $quiet, '127.0.0.1' );
		RecoveryLimiter::forget( $staff, '127.0.0.1' );
		$mails = array();
		SignInLink::request( "{$tag}-keen@example.invalid" );
		$http_token = preg_match( '/mizzey_sign_in=([A-Za-z0-9]+)/', (string) ( $mails[0]['message'] ?? '' ), $ht ) ? $ht[1] : '';
		$opened     = two_factor_http( 'GET', add_query_arg( array( SignInLink::PARAM => $http_token, SignInLink::USER => $keen ), wc_get_page_permalink( 'myaccount' ) ) );
		$nonce      = two_factor_field( $opened['body'], '_wpnonce' );
		$fields     = array( SignInLink::PARAM => $http_token, SignInLink::USER => $keen, '_wpnonce' => $nonce );
		$foreign    = two_factor_http( 'POST', wc_get_page_permalink( 'myaccount' ), $fields );
		$followed   = two_factor_http( 'POST', wc_get_page_permalink( 'myaccount' ), $fields, $opened['cookies'] );
		$again      = two_factor_http( 'POST', wc_get_page_permalink( 'myaccount' ), $fields, $opened['cookies'] );
		$check( '' !== $http_token && ! sign_in_has_session( $opened ) && '' !== $nonce && ! sign_in_has_session( $foreign ), '[HTTP] AUTH-12 opening the link signs no one in and asks to confirm; the confirmation from another browser session is refused' );
		$check( sign_in_has_session( $followed ) && ! sign_in_has_session( $again ), '[HTTP] AUTH-12 confirming in the browser that opened the link signs the customer in; confirming again does not' );

		return $ok;
	}
);

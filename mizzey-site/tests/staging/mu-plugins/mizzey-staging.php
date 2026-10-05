<?php
/**
 * Plugin Name: Mizzey staging environment
 * Description: Staging and demonstration only. Marks every screen, captures all mail, and offers a test
 *              payment method that moves no money. Installed by
 *              mizzey-site/tests/staging/staging.py into the staging tree and nowhere else: it is not part of
 *              the site plugin and is never in a deployable build.
 *
 * @package MizzeySite\Tests\Staging
 */

defined( 'ABSPATH' ) || exit;

// Inert anywhere that is not staging, so a copy that strays does nothing.
if ( ! defined( 'MIZZEY_STAGING' ) || ! MIZZEY_STAGING || 'staging' !== wp_get_environment_type() ) {
	return;
}

const MIZZEY_STAGING_LABEL = 'STAGING: demonstration copy, invented data';

// ---- The environment marker -------------------------------------------------------------------------------

/**
 * One strip on every storefront and sign-in screen. Fixed markup, no user input.
 *
 * The admin carries the marker in its toolbar instead (below), in a colour of its own. A strip was tried there
 * first and sat on top of the commerce screens' own headers.
 *
 * The wording is English and is not translatable on purpose: it is a label for reviewers of a staging copy, not
 * part of the store, and it must read the same in both languages of the storefront.
 */
function mizzey_staging_banner(): void {
	static $printed = false;
	if ( $printed ) {
		return;
	}
	$printed = true;
	echo '<div id="mizzey-staging-banner" role="note" dir="ltr">' . esc_html( MIZZEY_STAGING_LABEL ) . '</div>';
}
add_action( 'wp_body_open', 'mizzey_staging_banner' );
add_action( 'wp_footer', 'mizzey_staging_banner' );
add_action( 'login_header', 'mizzey_staging_banner' );

/** The marker's styles, and a different admin bar colour so staging is not mistaken for anything else. */
function mizzey_staging_styles(): void {
	wp_register_style( 'mizzey-staging', false, array(), '1' );
	wp_enqueue_style( 'mizzey-staging' );
	wp_add_inline_style(
		'mizzey-staging',
		'#mizzey-staging-banner{position:sticky;top:0;z-index:100000;background:#b45309;color:#fff;'
		. 'font:600 13px/1.4 system-ui,sans-serif;text-align:center;padding:6px 12px}'
		. '#wpadminbar{background:#7c2d12}'
		. '#wp-admin-bar-mizzey-staging>.ab-item{background:#b45309;color:#fff;font-weight:700}'
	);
}
add_action( 'wp_enqueue_scripts', 'mizzey_staging_styles' );
add_action( 'admin_enqueue_scripts', 'mizzey_staging_styles' );
add_action( 'login_enqueue_scripts', 'mizzey_staging_styles' );

add_action(
	'admin_bar_menu',
	static function ( WP_Admin_Bar $bar ): void {
		$bar->add_node(
			array(
				'id'    => 'mizzey-staging',
				'title' => 'STAGING',
				'meta'  => array( 'title' => MIZZEY_STAGING_LABEL ),
			)
		);
	},
	1
);

add_filter(
	'admin_title',
	static fn ( string $title ): string => '[STAGING] ' . $title
);

// ---- Out of search engines ---------------------------------------------------------------------------------
//
// Done by the staging web server, not here: it sends "X-Robots-Tag: noindex" on every response and asks any
// request from outside to sign in. WordPress's own "discourage search engines" setting is deliberately left
// alone, because switching it on also switches off the sitemap and rewrites robots.txt, and both are things a
// staging check has to be able to look at (#242).

// ---- No mail leaves the machine ----------------------------------------------------------------------------

/**
 * Capture a message instead of sending it. Returning non-null from pre_wp_mail short-circuits wp_mail(), so the
 * mailer is never built and no transport is tried.
 */
add_filter(
	'pre_wp_mail',
	static function ( $short_circuit, array $mail ) {
		$dir = MIZZEY_STAGING_ROOT . '/mail';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$record = array(
			'captured'    => gmdate( 'c' ),
			'to'          => $mail['to'] ?? null,
			'subject'     => $mail['subject'] ?? null,
			'headers'     => $mail['headers'] ?? null,
			'message'     => $mail['message'] ?? null,
			'attachments' => array_map( 'basename', (array) ( $mail['attachments'] ?? array() ) ),
		);
		$name = gmdate( 'Ymd-His' ) . '-' . substr( md5( wp_json_encode( $record ) . wp_rand() ), 0, 8 ) . '.json';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- staging log
		file_put_contents( $dir . '/' . $name, wp_json_encode( $record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		return true;
	},
	10,
	2
);

// ---- A test payment method that moves no money -------------------------------------------------------------

add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		/**
		 * Approves every order, except one whose billing email starts with "decline", which it refuses. That gives
		 * a reviewer both outcomes without a provider account. It contacts nothing.
		 */
		final class Mizzey_Staging_Test_Gateway extends WC_Payment_Gateway {

			public function __construct() {
				$this->id                 = 'mizzey_staging_test';
				$this->method_title       = 'Staging test payment';
				$this->method_description = 'Staging only. No provider is contacted and no money moves.';
				$this->title              = 'Test payment (staging, no money moves)';
				$this->description        = 'Use an email beginning with "decline" to see a refused payment.';
				$this->has_fields         = false;
				$this->enabled            = 'yes';
				$this->supports           = array( 'products' );
			}

			public function process_payment( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 0 === stripos( (string) $order->get_billing_email(), 'decline' ) ) {
					$order->update_status( 'failed', 'Staging test payment: declined on request.' );
					wc_add_notice( 'Staging test payment: this payment was declined on request.', 'error' );
					return array( 'result' => 'failure' );
				}
				$order->payment_complete( 'STAGING-' . wp_generate_uuid4() );
				WC()->cart?->empty_cart();
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);
			}
		}

		add_filter(
			'woocommerce_payment_gateways',
			static function ( array $gateways ): array {
				$gateways[] = 'Mizzey_Staging_Test_Gateway';
				return $gateways;
			}
		);
	}
);

/** Make the test method selectable in the block checkout, which lists only methods registered with it. */
add_action(
	'woocommerce_blocks_loaded',
	static function (): void {
		if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			return;
		}

		final class Mizzey_Staging_Test_Blocks extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {

			protected $name = 'mizzey_staging_test';

			public function initialize() {}

			public function is_active() {
				return true;
			}

			public function get_payment_method_script_handles() {
				wp_register_script( 'mizzey-staging-test-payment', false, array( 'wc-blocks-registry', 'wp-element' ), '1', true );
				wp_add_inline_script(
					'mizzey-staging-test-payment',
					'(function(){var el=window.wp.element.createElement;var label="Test payment (staging, no money moves)";'
					. 'var body=function(){return el("p",null,"Use an email beginning with \"decline\" to see a refused payment.");};'
					. 'window.wc.wcBlocksRegistry.registerPaymentMethod({name:"mizzey_staging_test",label:label,ariaLabel:label,'
					. 'content:el(body),edit:el(body),canMakePayment:function(){return true;},supports:{features:["products"]}});})();'
				);
				return array( 'mizzey-staging-test-payment' );
			}

			public function get_payment_method_data() {
				return array();
			}
		}

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			static function ( $registry ): void {
				$registry->register( new Mizzey_Staging_Test_Blocks() );
			}
		);
	}
);

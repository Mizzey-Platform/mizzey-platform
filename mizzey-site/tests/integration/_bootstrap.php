<?php
/**
 * Shared helpers for the product-cost integration scenarios (specs/001-product-cost-capture).
 *
 * Each scenario runs in its own `wp eval-file` process against the disposable runtime, creates its own fixtures,
 * and ends with Scenario::finish(), which removes the fixtures, restores the Cost of Goods Sold flag to the value
 * it had before the run, and prints one JSON verdict line.
 *
 * Never run against production.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use Automattic\WooCommerce\Internal\Features\FeaturesController;

if ( ! class_exists( 'WooCommerce' ) ) {
	fwrite( STDERR, "WooCommerce is not active.\n" );
	exit( 2 );
}

final class Scenario {

	private string $test;
	private string $criterion;
	private ?bool $flag_before = null;
	/** @var int[] */
	private array $posts = array();
	/** @var int[] */
	private array $orders = array();
	/** @var int[] */
	private array $users = array();
	/** @var string[] */
	private array $observed = array();
	/** @var callable[] */
	private array $finishers = array();

	public function __construct( string $test, string $criterion ) {
		$this->test      = $test;
		$this->criterion = $criterion;
	}

	public static function features(): FeaturesController {
		return wc_get_container()->get( FeaturesController::class );
	}

	/** Enable Cost of Goods Sold for the scenario (FR-001), recording the state it was in, and fail if it will not switch on. */
	public function enable_cogs(): void {
		$this->flag_before = self::features()->feature_is_enabled( 'cost_of_goods_sold' );
		if ( ! $this->flag_before ) {
			self::features()->change_feature_enable( 'cost_of_goods_sold', true );
		}
		if ( ! self::features()->feature_is_enabled( 'cost_of_goods_sold' ) ) {
			throw new \RuntimeException( 'Cost of Goods Sold could not be enabled.' );
		}
		$this->note( 'Cost of Goods Sold before this scenario: ' . ( $this->flag_before ? 'on' : 'off' ) . '; enabled for the run and restored after.' );
	}

	/** Run $fn during finish(), before fixtures are removed (for temporary runtime files). */
	public function on_finish( callable $fn ): void {
		$this->finishers[] = $fn;
	}

	public function note( string $line ): void {
		$this->observed[] = $line;
	}

	public function simple_product( string $name, string $price, ?float $cost ): \WC_Product_Simple {
		$product = new \WC_Product_Simple();
		$product->set_name( $name );
		$product->set_regular_price( $price );
		$product->set_status( 'publish' );
		$product->set_cogs_value( $cost );
		$product->save();
		$this->track_post( $product->get_id() );
		return $product;
	}

	/** Track a fixture for deletion. Only product posts are ever tracked, so a wrong id cannot delete other content. */
	/**
	 * Track a product or variation for deletion when the scenario finishes.
	 *
	 * **Products and variations only, by design.** Anything else is ignored, silently, so a scenario that needs
	 * another post type cleaned up must register it with on_finish() instead. t21 lost two pages this way before
	 * the behaviour was written down here.
	 *
	 * @param int $id Post id.
	 */
	public function track_post( int $id ): void {
		if ( $id > 0 && in_array( get_post_type( $id ), array( 'product', 'product_variation' ), true ) ) {
			$this->posts[] = $id;
		}
	}

	public function track_order( int $id ): void {
		$this->orders[] = $id;
	}

	public function track_user( int $id ): void {
		$this->users[] = $id;
	}

	/** Place an order for $qty of $product, as WooCommerce does at checkout (totals calculated), and return it. */
	public function order_for( \WC_Product $product, int $qty ): \WC_Order {
		$order = wc_create_order();
		$order->add_product( $product, $qty );
		$order->calculate_totals();
		$order->set_status( 'processing' );
		$order->save();
		$this->track_order( $order->get_id() );
		return $order;
	}

	/** Re-read a product from storage, not from any in-memory copy. */
	public static function fresh_product( int $id ): ?\WC_Product {
		wp_cache_flush();
		$product = wc_get_product( $id );
		return $product ? $product : null;
	}

	/**
	 * Finish the scenario: clean up, restore the flag, print the verdict. $pass is true, false, or null for a
	 * fact-finding scenario that deliberately gives no verdict.
	 */
	public function finish( ?bool $pass ): void {
		foreach ( $this->finishers as $fn ) {
			$fn();
		}
		foreach ( $this->orders as $id ) {
			$order = wc_get_order( $id );
			if ( $order ) {
				$order->delete( true );
			}
		}
		foreach ( array_reverse( $this->posts ) as $id ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				$product->delete( true );
			} else {
				wp_delete_post( $id, true );
			}
		}
		if ( $this->users ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			foreach ( $this->users as $id ) {
				wp_delete_user( $id );
			}
		}
		if ( false === $this->flag_before ) {
			self::features()->change_feature_enable( 'cost_of_goods_sold', false );
		}
		echo wp_json_encode(
			array(
				'test'      => $this->test,
				'criterion' => $this->criterion,
				'pass'      => $pass,
				'observed'  => $this->observed,
				'versions'  => array(
					'wordpress'   => get_bloginfo( 'version' ),
					'woocommerce' => defined( 'WC_VERSION' ) ? WC_VERSION : null,
					'wpml'        => defined( 'ICL_SITEPRESS_VERSION' ) ? ICL_SITEPRESS_VERSION : null,
					'wcml'        => defined( 'WCML_VERSION' ) ? WCML_VERSION : null,
				),
			)
		), "\n";
	}
}

/** Fail-safe: a scenario that throws still cleans up and reports a failure. */
function run( Scenario $scenario, callable $body ): void {
	try {
		$pass = $body( $scenario );
	} catch ( \Throwable $e ) {
		$scenario->note( 'Error: ' . $e->getMessage() . ' at ' . basename( $e->getFile() ) . ':' . $e->getLine() );
		$pass = false;
	}
	$scenario->finish( $pass );
}

<?php
/**
 * P-016. Does WooCommerce provide a native product cost field, or must ADM-27 be a field we add?
 *
 * US-16-05 is classified `native` and flagged key. Technical Design section 9 calls ADM-27 a
 * "New field on product and variation". One of the two is wrong.
 *
 * Run: wp eval-file discovery/probes/P-016-product-cost-field-native.php
 * Prints one JSON object: {"observed": "...", "verdict": "confirmed|refuted|partial"}
 */

use Automattic\WooCommerce\Internal\Features\FeaturesController;

$notes    = array();
$verdict  = 'confirmed';
$restore  = null;
$product  = null;

try {
	if ( ! class_exists( 'WooCommerce' ) ) {
		throw new RuntimeException( 'WooCommerce is not active on this site.' );
	}

	$woo_version = defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown';

	// 1. Is there an API at all?
	$has_getter = method_exists( 'WC_Product', 'get_cogs_value' );
	$has_setter = method_exists( 'WC_Product', 'set_cogs_value' );
	$has_api    = $has_getter && $has_setter;

	if ( ! $has_api ) {
		$notes[] = sprintf(
			'WooCommerce %s exposes no cost API on WC_Product (get_cogs_value: %s, set_cogs_value: %s). '
				. 'ADM-27 must be a field Mizzey adds, and the `native` leverage on US-16-05 is wrong.',
			$woo_version,
			$has_getter ? 'yes' : 'no',
			$has_setter ? 'yes' : 'no'
		);
		throw new LogicException( 'no-api' ); // jumps to output with verdict confirmed
	}

	// 2. Is it on by default? That is the part that decides whether "native" is usable as shipped.
	$features   = wc_get_container()->get( FeaturesController::class );
	$default_on = $features->feature_is_enabled( 'cost_of_goods_sold' );
	$option     = get_option( 'woocommerce_feature_cost_of_goods_sold_enabled', '(unset)' );

	$notes[] = sprintf(
		'WooCommerce %s ships a Cost of Goods Sold feature. WC_Product::get_cogs_value and '
			. 'set_cogs_value both exist. Feature enabled by default: %s (option '
			. 'woocommerce_feature_cost_of_goods_sold_enabled = %s).',
		$woo_version,
		$default_on ? 'yes' : 'no',
		is_string( $option ) ? $option : var_export( $option, true )
	);

	// 3. Does a cost actually persist? Enable if needed, then put it back.
	if ( ! $default_on ) {
		$features->change_feature_enable( 'cost_of_goods_sold', true );
		$restore = false;
	}

	$product = new WC_Product_Simple();
	$product->set_name( 'P-016 probe product' );
	$product->set_regular_price( '250' );
	$product->set_cogs_value( 137.5 );
	$product->save();

	$reloaded  = wc_get_product( $product->get_id() );
	$persisted = $reloaded ? $reloaded->get_cogs_value() : null;

	if ( null !== $persisted && abs( (float) $persisted - 137.5 ) < 0.001 ) {
		$notes[]  = sprintf( 'A cost of 137.5 set on a product survived save and reload (read back %s).', var_export( $persisted, true ) );
		// The design assumption was that ADM-27 is a field we add. It is not: Woo carries it.
		$verdict  = $default_on ? 'refuted' : 'partial';
		$notes[]  = $default_on
			? 'ADM-27 needs no new product field. The Technical Design section 9 row is wrong and `native` on US-16-05 is right.'
			: 'The field is native but OFF by default, so it must be switched on deliberately as part of the build. '
				. '`native` on US-16-05 is defensible; the Technical Design "New field" row is not, but the feature flag is a real build step.';
	} else {
		$notes[] = sprintf( 'A cost set on a product did NOT survive save and reload (read back %s).', var_export( $persisted, true ) );
		$verdict = 'confirmed';
	}
} catch ( LogicException $e ) {
	$verdict = 'confirmed';
} catch ( Throwable $e ) {
	$notes[] = 'Probe error: ' . $e->getMessage();
	$verdict = 'partial';
}

// Clean up: the probe must not leave a product or a flipped feature behind.
try {
	if ( $product && $product->get_id() ) {
		wp_delete_post( $product->get_id(), true );
	}
	if ( false === $restore ) {
		wc_get_container()->get( FeaturesController::class )->change_feature_enable( 'cost_of_goods_sold', false );
		$notes[] = 'Feature flag restored to disabled after the test.';
	}
} catch ( Throwable $e ) {
	$notes[] = 'Cleanup warning: ' . $e->getMessage();
}

echo wp_json_encode(
	array(
		'observed' => implode( ' ', $notes ),
		'verdict'  => $verdict,
	)
);

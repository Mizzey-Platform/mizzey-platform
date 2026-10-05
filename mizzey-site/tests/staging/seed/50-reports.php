<?php
/**
 * Staging seed, step 5: put the invented orders into the reports.
 *
 * WooCommerce fills its reporting tables from a background job that runs some time after an order is saved. On a
 * freshly seeded staging that job has not run, so every report reads "No data", which the dry run for the store
 * operations walkthrough met on the orders report (ADM-158). This step runs the same import WooCommerce's own job
 * runs, for each seeded order and refund, so a reviewer sees reports with data in them.
 *
 * It uses the commerce platform's own importer and writes nothing itself.
 *
 * @package MizzeySite\Tests\Staging
 */

defined( 'ABSPATH' ) || exit;
defined( 'MIZZEY_STAGING' ) && MIZZEY_STAGING || WP_CLI::error( 'staging only' );

$importer = '\Automattic\WooCommerce\Internal\Admin\Schedulers\OrdersScheduler';
if ( ! class_exists( $importer ) ) {
	WP_CLI::error( 'the reports importer is not available in this version of the commerce platform' );
}

$ids = wc_get_orders(
	array(
		'type'    => array( 'shop_order', 'shop_order_refund' ),
		'limit'   => 200, // The seed makes fourteen; never an unbounded query.
		'orderby' => 'ID',
		'order'   => 'ASC',
		'return'  => 'ids',
	)
);
foreach ( $ids as $id ) {
	$importer::import( (int) $id );
}

global $wpdb;
echo wp_json_encode(
	array(
		'orders_and_refunds' => count( $ids ),
		'in_reports'         => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $wpdb->prefix . 'wc_order_stats' ) ),
	)
), "\n";

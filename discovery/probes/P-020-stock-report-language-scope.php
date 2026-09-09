<?php
/**
 * P-020. In a low stock list, does a product that exists in two languages appear once, twice, or only
 * in the language the operator happens to be viewing?
 *
 * P-019 proved the product-level split for the analytics products report and deliberately stopped
 * there, because the stock report is a different query. RPT-10 contracts low stock, out of stock, dead
 * stock and adjustments as one operations report, and US-24-04 is classified partial on the assumption
 * that the WooCommerce stock report supplies a sound base.
 *
 * Two faults are possible and they are opposites. WCML\Synchronization\Component\Stock copies _stock
 * onto every translation, so both posts hold their own number, which is the shape of a double count.
 * Reports\Stock\Controller::get_products runs a WP_Query against the product post type rather than the
 * analytics lookup tables, so WPML's language filter applies to it, which is the shape of a list scoped
 * to one language that silently omits an Arabic-only product. A low stock list that omits stock is
 * worse than one that duplicates it, so the probe has to tell them apart rather than just fail.
 *
 * Fixture. A low product that exists in English and Arabic, a low product that exists only in Arabic,
 * and a low control that exists only in English. Read the real endpoint twice, once in each language.
 *
 * Method note. The seeded row says wp-cli. The endpoint is dispatched internally with rest_do_request,
 * so the real controller, its WP_Query and every filter registered against it run as written. What an
 * internal dispatch cannot reproduce is anything keyed on the HTTP request line, which is recorded in
 * the observation rather than assumed away.
 *
 * Run: MIZZEY_WP=C:/wamp64/www/mizzey/app/wp python -m discovery.run_probes P-020
 * Prints one JSON object: {"observed": "...", "verdict": "confirmed|refuted|partial"}
 */

$notes   = array();
$verdict = 'partial';
$made    = array();

/** Create a low-stock simple product and return its id. */
$make_low = function ( $name ) {
	$p = new WC_Product_Simple();
	$p->set_name( $name );
	$p->set_regular_price( '100' );
	$p->set_status( 'publish' );
	$p->set_manage_stock( true );
	$p->set_stock_quantity( 1 );
	$p->set_low_stock_amount( 5 );
	$p->save();
	return $p->get_id();
};

/** Ask the real endpoint for the low stock list, in one language, and return the ids it names. */
$low_stock_ids = function ( $language ) use ( &$notes ) {
	global $sitepress;
	$sitepress->switch_lang( $language, true );

	$request = new WP_REST_Request( 'GET', '/wc-analytics/reports/stock' );
	$request->set_param( 'type', 'lowstock' );
	$request->set_param( 'per_page', 100 );
	$response = rest_do_request( $request );

	if ( $response->is_error() ) {
		throw new RuntimeException(
			sprintf( 'Stock report returned an error in %s: %s', $language, $response->as_error()->get_error_message() )
		);
	}
	$ids = array();
	foreach ( (array) $response->get_data() as $item ) {
		if ( isset( $item['id'] ) ) {
			$ids[] = (int) $item['id'];
		}
	}
	return $ids;
};

try {
	global $wpdb, $sitepress;

	if ( ! class_exists( 'WooCommerce' ) ) {
		throw new RuntimeException( 'WooCommerce is not active on this site.' );
	}
	if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
		throw new RuntimeException( 'WPML core is not active.' );
	}
	if ( ! class_exists( 'woocommerce_wpml' ) ) {
		throw new RuntimeException( 'WooCommerce Multilingual is not active.' );
	}
	$langs = array_keys( (array) apply_filters( 'wpml_active_languages', null, array() ) );
	if ( ! in_array( 'ar', $langs, true ) ) {
		throw new RuntimeException( 'Arabic is not an active language; configure it before running this probe.' );
	}

	// The endpoint is capability gated, and a CLI process has no user.
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	if ( ! $admins ) {
		throw new RuntimeException( 'No administrator to read the report as.' );
	}
	wp_set_current_user( (int) $admins[0] );

	$sitepress->switch_lang( 'en', true );

	$notes[] = sprintf(
		'WooCommerce %s, WPML %s, WooCommerce Multilingual %s. Active languages: %s. Site low stock threshold: %s.',
		defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
		ICL_SITEPRESS_VERSION,
		defined( 'WCML_VERSION' ) ? WCML_VERSION : 'unknown',
		implode( ', ', $langs ),
		get_option( 'woocommerce_notify_low_stock_amount' )
	);

	// One physical item that exists in both languages.
	$pair_en = $make_low( 'P-020 translated product' );
	$made[]  = $pair_en;
	$pair_ar = $sitepress->make_duplicate( $pair_en, 'ar' );
	if ( ! $pair_ar ) {
		throw new RuntimeException( 'WPML returned no Arabic product id, so the pair cannot be created. See P-006.' );
	}
	$made[] = $pair_ar;

	// One physical item that exists only in Arabic, which is the case an English-scoped list would lose.
	$only_ar = $make_low( 'P-020 Arabic only product' );
	$made[]  = $only_ar;
	do_action(
		'wpml_set_element_language_details',
		array(
			'element_id'           => $only_ar,
			'element_type'         => 'post_product',
			'trid'                 => null,
			'language_code'        => 'ar',
			'source_language_code' => null,
		)
	);

	// One single-language control, so a list that returns nothing is distinguishable from a list that works.
	$control = $make_low( 'P-020 control product' );
	$made[]  = $control;

	$only_ar_lang = apply_filters(
		'wpml_element_language_code',
		null,
		array( 'element_id' => $only_ar, 'element_type' => 'post_product' )
	);
	$notes[]      = sprintf(
		'Seeded: translated pair %d (en) and %d (ar), Arabic-only product %d (WPML reports its language as %s), '
			. 'and English-only control %d. All four carry managed stock of 1 against a low stock amount of 5, '
			. 'so every one of them is low.',
		$pair_en,
		$pair_ar,
		$only_ar,
		var_export( $only_ar_lang, true ),
		$control
	);

	// Fact one, independent of any report: does the translated pair hold one stock row or two?
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT product_id, stock_quantity FROM {$wpdb->prefix}wc_product_meta_lookup
			 WHERE product_id IN (%d, %d) ORDER BY product_id",
			$pair_en,
			$pair_ar
		),
		ARRAY_A
	);
	$row_parts = array();
	foreach ( $rows as $row ) {
		$row_parts[] = sprintf( '%d=%s', $row['product_id'], var_export( $row['stock_quantity'], true ) );
	}
	$stock_duplicated = count( $rows ) > 1;
	$notes[]          = sprintf(
		'wc_product_meta_lookup holds %d stock row(s) for the pair (%s). The same physical item %s.',
		count( $rows ),
		$row_parts ? implode( ', ', $row_parts ) : 'none',
		$stock_duplicated ? 'is stored as two stockable products' : 'is stored once'
	);

	// Fact two: the real endpoint, read in each language.
	$en_ids = $low_stock_ids( 'en' );
	$ar_ids = $low_stock_ids( 'ar' );
	$sitepress->switch_lang( 'en', true );

	$label = function ( $ids ) use ( $pair_en, $pair_ar, $only_ar, $control ) {
		$names = array(
			$pair_en => 'pair-en',
			$pair_ar => 'pair-ar',
			$only_ar => 'arabic-only',
			$control => 'control',
		);
		$out = array();
		foreach ( $ids as $id ) {
			if ( isset( $names[ $id ] ) ) {
				$out[] = $names[ $id ];
			}
		}
		return $out ? implode( ', ', $out ) : 'none of the probe products';
	};

	$notes[] = sprintf(
		'GET /wc-analytics/reports/stock?type=lowstock in English returned %d row(s) in total and named: %s. '
			. 'In Arabic it returned %d row(s) and named: %s.',
		count( $en_ids ),
		$label( $en_ids ),
		count( $ar_ids ),
		$label( $ar_ids )
	);

	// If the two language views are identical, no language scoping ran here, and that bounds the claim.
	sort( $en_ids );
	sort( $ar_ids );
	$scoping_observed = ( $en_ids !== $ar_ids );
	$notes[]          = $scoping_observed
		? 'The two language views differ, so WPML did scope this query by language.'
		: 'The two language views are identical, so no language scoping ran on this query in this dispatch '
			. 'path. That bounds what follows: the double count below is proved for the query as the controller '
			. 'builds it, and the opposite fault, a language-scoped list that omits an untranslated product, is '
			. 'not ruled out for a browser admin request. WCML removes the All languages option from the '
			. 'analytics switcher in classes/Reports/Hooks.php, which implies the admin is normally scoped, so '
			. 'the browser case is worth one manual check before US-24-04 is accepted.';

	$en_has_both   = in_array( $pair_en, $en_ids, true ) && in_array( $pair_ar, $en_ids, true );
	$en_has_one    = in_array( $pair_en, $en_ids, true ) xor in_array( $pair_ar, $en_ids, true );
	$en_has_arabic = in_array( $only_ar, $en_ids, true );
	$control_found = in_array( $control, $en_ids, true );

	if ( ! $control_found ) {
		$verdict = 'partial';
		$notes[] = 'The English-only control did not appear in the English low stock list, so the report was not '
			. 'exercised and nothing here can be concluded about language. Settle why the control is missing '
			. 'before reading anything else in this row.';
	} elseif ( $en_has_both ) {
		$verdict = 'refuted';
		$notes[] = 'One physical item is listed twice, once per language, each showing the full quantity. A stock '
			. 'report that double counts is not a basis for a purchasing decision, and the duplicate is not '
			. 'marked as one. Note that both faults share one root, proved above: WCML copies the stock onto the '
			. 'translation, so two stockable products describe one physical item. Whether the report then shows '
			. 'both of them or only the one matching the current language, it is wrong, and the difference is '
			. 'only in which way. The correction is the same shape as P-019: resolve the product to its '
			. 'translation group before the list is built, by joining icl_translations and grouping on trid, so '
			. 'one physical item is one row whatever language the operator is in.';
	} elseif ( $en_has_one && ! $en_has_arabic ) {
		$verdict = 'refuted';
		$notes[] = 'The pair is correctly listed once, but the Arabic-only product is missing from the English '
			. 'list entirely. The list is scoped to the language being viewed, so an operator working in English '
			. 'cannot see that an Arabic-only product is low, and nothing on the screen says the list is partial. '
			. 'That is a worse failure than a duplicate: a duplicate is visible and a silent omission is not. '
			. 'RPT-10 needs a list that spans both languages and names each physical item once, which means '
			. 'building the query rather than filtering WooCommerce output.';
	} elseif ( $en_has_one && $en_has_arabic ) {
		$verdict = 'confirmed';
		$notes[] = 'The English list named each physical item exactly once, including the Arabic-only product. '
			. 'The stock half of RPT-10 can be built on this base. Note the limit: this says nothing about the '
			. 'dead stock and adjustment figures, which read the product-level path P-019 refuted.';
	} else {
		$verdict = 'partial';
		$notes[] = 'Neither half of the pair appeared in the English list although the control did. Read the ids '
			. 'above before concluding; the report is filtering on something this probe did not set out to test.';
	}
} catch ( Throwable $e ) {
	$notes[] = 'Probe error: ' . $e->getMessage();
	$verdict = 'partial';
}

// Clean up: no probe products left behind.
try {
	foreach ( array_unique( $made ) as $id ) {
		wp_delete_post( $id, true );
	}
	delete_transient( 'wc_low_stock_count' );
	delete_transient( 'wc_outofstock_count' );
} catch ( Throwable $e ) {
	$notes[] = 'Cleanup warning: ' . $e->getMessage();
}

echo wp_json_encode( array( 'observed' => implode( ' ', $notes ), 'verdict' => $verdict ) );

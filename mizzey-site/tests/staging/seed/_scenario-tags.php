<?php
/**
 * The names the integration scenarios give their fixtures, so a step can find what a stopped run left behind. Every
 * name starts with the scenario's number and random digits: its run tag (`t84` and `7286` make `t847286`), or the
 * number, a word and the digits (`t40_nobody_1234567`, `t72adm4821`, `t102-shirt-5512`, `T72-PRICE-3390`,
 * `t4x_wpreg_88123`), or the search scenario's `Q` and six digits (`Q164154`). Shared by 05-scenario-leftovers.php and
 * its test (tools/tests/test_staging_seed_tidy.py). Not a seed step of its own.
 *
 * @package MizzeySite\Tests\Staging
 */

if ( ! function_exists( 'mizzey_scenario_tag' ) ) {
	/** A name that starts with a scenario's tag: a slug, a SKU, a coupon code or a login. */
	function mizzey_scenario_tag( string $name ): bool {
		return 1 === preg_match( '/^(?:t\d{1,3}x?(?:[_-]?[a-z]+)*[_-]?\d{4,}|q\d{6}(?!\d))/i', $name );
	}

	/** A SKU a scenario made: its tag, then anything (a size, a language). */
	function mizzey_scenario_sku( string $sku ): bool {
		return mizzey_scenario_tag( $sku );
	}

	/** An address a scenario made: its tag, at example.invalid, which can never be delivered. */
	function mizzey_scenario_address( string $email ): bool {
		return mizzey_scenario_tag( $email ) && 1 === preg_match( '/@example\.invalid$/i', $email );
	}
}

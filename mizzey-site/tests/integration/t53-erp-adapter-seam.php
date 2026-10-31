<?php
/**
 * t53, the ERP adapter seam (specs/012-erp-adapter-seam, #245).
 *
 * In-process, against a mock adapter that keeps the StockSource contract: identity across the two language records of
 * a product and of a variation (WPML duplicates, as the store makes them), the default adapter that refuses, bounded
 * retries, refusals not retried, one application per operation key across retries, every attempt logged, and no write
 * to WooCommerce's own stock field. No ERP is called (M-3). Every product it makes is removed when it finishes.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Stock\StockGateway;
use MizzeySite\Stock\StockIdentity;
use MizzeySite\Stock\StockItem;
use MizzeySite\Stock\StockSource;
use MizzeySite\Stock\StockSourceRefused;
use MizzeySite\Stock\StockSourceUnavailable;
use MizzeySite\Stock\UnconfiguredStockSource;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_catalogue.php';

/**
 * A stock source that keeps the contract: quantities by stock identity, a key applied once. `$failures` lists, per
 * call, what to do before answering: 'unavailable', 'refused', 'broken', or 'apply-then-unavailable' (the change is
 * made, then the answer is lost, as in a timeout).
 */
final class T53MockSource implements StockSource {
	/** @var array<int,int> */
	public array $stock = array();
	/** @var array<string,true> */
	public array $applied = array();
	/** @var list<string> */
	public array $failures = array();
	/** @var list<array{string,int,string}> */
	public array $calls = array();

	public function name(): string {
		return 'mock';
	}

	public function level( StockItem $item ): int {
		$this->calls[] = array( 'level', $item->id, '' );
		$this->fail( false, $item, 0, '' );
		if ( ! isset( $this->stock[ $item->id ] ) ) {
			throw new StockSourceRefused( 'unknown item' );
		}
		return $this->stock[ $item->id ];
	}

	public function adjust( StockItem $item, int $delta, string $operationKey ): int {
		$this->calls[] = array( 'adjust', $item->id, $operationKey );
		$this->fail( true, $item, $delta, $operationKey );
		$this->apply( $item, $delta, $operationKey );
		return $this->stock[ $item->id ];
	}

	private function apply( StockItem $item, int $delta, string $key ): void {
		if ( ! isset( $this->applied[ $key ] ) ) {
			$this->applied[ $key ]   = true;
			$this->stock[ $item->id ] = ( $this->stock[ $item->id ] ?? 0 ) + $delta;
		}
	}

	private function fail( bool $change, StockItem $item, int $delta, string $key ): void {
		$what = array_shift( $this->failures );
		if ( 'apply-then-unavailable' === $what && $change ) {
			$this->apply( $item, $delta, $key );
			throw new StockSourceUnavailable( 'timed out after applying' );
		}
		if ( 'unavailable' === $what ) {
			throw new StockSourceUnavailable( 'timed out' );
		}
		if ( 'refused' === $what ) {
			throw new StockSourceRefused( 'rejected' );
		}
		if ( 'broken' === $what ) {
			throw new \TypeError( 'adapter bug' );
		}
	}
}

/** Records what the gateway logs. */
final class T53Logger implements \WC_Logger_Interface {
	/** @var list<array{string,string,array}> */
	public array $lines = array();

	public function add( $handle, $message, $level = 'notice' ) {
		return $this->log( $level, $message, array( 'source' => $handle ) );
	}

	public function log( $level, $message, $context = array() ) {
		$this->lines[] = array( (string) $level, (string) $message, (array) $context );
		return true;
	}

	public function emergency( $message, $context = array() ) {
		$this->log( 'emergency', $message, $context );
	}

	public function alert( $message, $context = array() ) {
		$this->log( 'alert', $message, $context );
	}

	public function critical( $message, $context = array() ) {
		$this->log( 'critical', $message, $context );
	}

	public function error( $message, $context = array() ) {
		$this->log( 'error', $message, $context );
	}

	public function warning( $message, $context = array() ) {
		$this->log( 'warning', $message, $context );
	}

	public function notice( $message, $context = array() ) {
		$this->log( 'notice', $message, $context );
	}

	public function info( $message, $context = array() ) {
		$this->log( 'info', $message, $context );
	}

	public function debug( $message, $context = array() ) {
		$this->log( 'debug', $message, $context );
	}
}

run(
	new Scenario( 't53-erp-adapter-seam', 'ERP-01, ERP-02, INT-16: the ERP adapter seam' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag = 't53' . wp_rand( 1000, 9999 );

		// AC-2: one identity across the two language records of a product, and of a variation.
		$en    = catalogue_simple( $s, "{$tag}-SIMPLE", 't53 simple' );
		$ar    = catalogue_duplicate( $s, $en );
		$id_en = StockIdentity::resolve( $en );
		$id_ar = StockIdentity::resolve( $ar );
		$check( $ar > 0 && $id_en && $id_ar && $en === $id_en->id && $en === $id_ar->id && StockItem::PRODUCT === $id_ar->kind && "{$tag}-SIMPLE" === $id_ar->sku, "AC-2 a product's English ({$en}) and Arabic ({$ar}) records resolve to one stock identity, the English source (" . ( $id_ar ? $id_ar->label() : 'none' ) . ')' );
		$variable  = catalogue_variable( $s, "{$tag}-VAR", array( "{$tag}-VAR-S", "{$tag}-VAR-L" ) );
		$var_en    = (int) wc_get_product( $variable )->get_children()[0];
		$variable_ar = catalogue_duplicate( $s, $variable );
		$var_ar    = (int) apply_filters( 'wpml_object_id', $var_en, 'product_variation', false, 'ar' );
		if ( $var_ar > 0 ) {
			$s->track_post( $var_ar );
		}
		$v_en = StockIdentity::resolve( $var_en );
		$v_ar = StockIdentity::resolve( $var_ar );
		$check( $variable_ar > 0 && $var_ar > 0 && $var_ar !== $var_en && $v_en && $v_ar && $var_en === $v_ar->id && $var_en === $v_en->id && StockItem::VARIATION === $v_ar->kind && $v_ar->id !== $variable, "AC-2 a variation's English ({$var_en}) and Arabic ({$var_ar}) records resolve to one identity, the variation's, not its parent's (" . ( $v_ar ? $v_ar->label() : 'none' ) . ')' );
		$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 't53 page' ) );
		$s->track_post( $page );
		$check( null === StockIdentity::resolve( $page ) && null === StockIdentity::resolve( 999999999 ) && null === StockIdentity::resolve( 0 ), 'AC-2 a page, a missing record and no record have no stock identity' );

		// AC-1, AC-3: the store's default adapter refuses, so stock is unknown and nothing is sellable.
		$log     = new T53Logger();
		$default = new StockGateway( new UnconfiguredStockSource(), 3, $log, 0 );
		$answer  = $default->level( $ar );
		$check( ! $answer->isKnown() && ! $answer->sellable( 1 ) && 'refused' === $answer->reason && 1 === count( $log->lines ), "AC-1, AC-3 with no adapter configured the answer is unknown ({$answer->reason}), not sellable, and the refusal is not retried (" . count( $log->lines ) . ' attempt)' );
		$check( ! StockGateway::make()->sellable( $en, 1 ), 'AC-3 the gateway the store builds, with no adapter bound, sells nothing' );

		// AC-1: the adapter comes from one filter; the caller names any language record.
		$mock                = new T53MockSource();
		$mock->stock[ $en ]  = 7;
		$bind                = static fn () => $mock;
		add_filter( StockGateway::SOURCE_FILTER, $bind );
		$s->on_finish( static function () use ( $bind ) { remove_filter( StockGateway::SOURCE_FILTER, $bind ); } );
		$bound = StockGateway::make()->level( $ar );
		remove_filter( StockGateway::SOURCE_FILTER, $bind );
		$check( $bound->isKnown() && 7 === $bound->quantity && array( array( 'level', $en, '' ) ) === $mock->calls, 'AC-1 the adapter bound through mizzey_stock_source is asked about the Arabic record under its English identity, and answers 7' );
		$check( $bound->sellable( 7 ) && ! $bound->sellable( 8 ) && ! $bound->sellable( 0 ), 'AC-3 7 known units: 7 sellable, 8 not, and no quantity of 0' );

		// AC-4, AC-6: failures retried up to the bound, each attempt logged; a refusal not retried.
		$mock->calls    = array();
		$mock->failures = array( 'unavailable', 'unavailable' );
		$log            = new T53Logger();
		$answer         = ( new StockGateway( $mock, 3, $log, 0 ) )->level( $ar );
		$levels         = array_column( $log->lines, 0 );
		$sources        = array_unique( array_map( static fn ( $l ) => $l[2]['source'] ?? '', $log->lines ) );
		$check( $answer->isKnown() && 7 === $answer->quantity && 3 === count( $mock->calls ) && array( 'warning', 'warning', 'debug' ) === $levels && array( StockGateway::LOG_SOURCE ) === array_values( $sources ), 'AC-4, AC-6 two failures and an answer: three attempts, each logged under mizzey-stock (' . implode( ',', $levels ) . ')' );
		$mock->calls    = array();
		$mock->failures = array( 'unavailable', 'unavailable', 'unavailable', 'unavailable' );
		$log            = new T53Logger();
		$answer         = ( new StockGateway( $mock, 3, $log, 0 ) )->level( $en );
		$check( ! $answer->isKnown() && ! $answer->sellable( 1 ) && 3 === count( $mock->calls ) && 3 === count( $log->lines ), "AC-3, AC-4 a source that keeps failing is asked three times, then the answer is unknown ({$answer->reason})" );
		$mock->calls    = array();
		$mock->failures = array( 'refused' );
		$answer         = ( new StockGateway( $mock, 3, new T53Logger(), 0 ) )->level( $en );
		$check( ! $answer->isKnown() && 1 === count( $mock->calls ), 'AC-4 a refusal is not retried, and the answer is unknown' );
		$mock->calls    = array();
		$mock->failures = array( 'broken' );
		$log            = new T53Logger();
		$answer         = ( new StockGateway( $mock, 3, $log, 0 ) )->level( $en );
		$check( ! $answer->isKnown() && 1 === count( $mock->calls ) && 'error' === ( $log->lines[0][0] ?? '' ), 'AC-3, AC-6 an adapter that breaks its contract fails closed at once, logged as an error' );
		$mock->failures = array();
		$check( ! ( new StockGateway( $mock, 3, new T53Logger(), 0 ) )->level( $variable )->isKnown(), 'AC-3 an item the source does not know is refused, so unknown' );

		// AC-5: a change applied, then its answer lost, is retried with the same key and applied once.
		$mock->stock[ $en ] = 10;
		$mock->calls        = array();
		$mock->applied      = array();
		$mock->failures     = array( 'apply-then-unavailable' );
		$log                = new T53Logger();
		$answer             = ( new StockGateway( $mock, 3, $log, 0 ) )->adjust( $ar, -2, "{$tag}-order-1-line-1" );
		$keys               = array_unique( array_column( $mock->calls, 2 ) );
		$check( $answer->isKnown() && 8 === $answer->quantity && 8 === $mock->stock[ $en ] && 2 === count( $mock->calls ) && array( "{$tag}-order-1-line-1" ) === array_values( $keys ) && 'info' === end( $log->lines )[0], 'AC-5 2 units off 10, the first answer lost after the change: the retry carries the same key and the stock is 8, not 6 (' . $answer->quantity . ')' );
		$again = ( new StockGateway( $mock, 3, new T53Logger(), 0 ) )->adjust( $en, -2, "{$tag}-order-1-line-1" );
		$check( 8 === $again->quantity, 'AC-5 the same change asked for again, from the English record, is not applied twice' );
		$mock->calls = array();
		$zero        = ( new StockGateway( $mock, 3, new T53Logger(), 0 ) )->adjust( $en, 0, "{$tag}-zero" );
		$nokey       = ( new StockGateway( $mock, 3, new T53Logger(), 0 ) )->adjust( $en, -1, '  ' );
		$check( ! $zero->isKnown() && ! $nokey->isKnown() && array() === $mock->calls, 'FR-005 a change of zero, or without an operation key, is refused before the source is asked' );

		// FR-007: the gateway leaves WooCommerce's own stock field alone.
		$product = wc_get_product( $en );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 5 );
		$product->save();
		( new StockGateway( $mock, 3, new T53Logger(), 0 ) )->adjust( $en, -3, "{$tag}-order-2-line-1" );
		$check( 5 === (int) wc_get_product( $en )->get_stock_quantity(), 'FR-007 a change through the gateway writes nothing to WooCommerce\'s stock field (still 5)' );

		return $ok;
	}
);

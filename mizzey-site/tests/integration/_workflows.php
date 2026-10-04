<?php
/**
 * Catalogue workflows for the cost-synchronisation matrix (t11): how Arabic translations are created, and every
 * channel through which a cost can be written, each in its REAL request context. This matters: WooCommerce
 * Multilingual registers its product-translation sync only when is_admin() or WP-CLI is running, so a channel
 * tested in the wrong context gives a false result.
 *  - admin-http:  the wp-admin product form (post.php) and the variations AJAX save, over HTTP, as a logged-in admin;
 *  - import-http: the wp-admin CSV importer AJAX step (update existing, by SKU), over HTTP;
 *  - rest-http:   WooCommerce REST v3 over HTTP, authenticated with an application password;
 *  - crud-cli:    WooCommerce CRUD (set_cogs_value + save) in WP-CLI, as `wp wc` commands or CLI scripts do;
 *  - crud-web:    WooCommerce CRUD in a front-end request (not admin, not CLI), as custom code, webhooks or cron
 *                 would run it. A temporary must-use plugin in the disposable runtime provides the request.
 * Translations: WPML duplicate (make_duplicate), or WCML's product translation editor save
 * (WCML_Editor_UI_Product_Job::save_translations), which is how a translator authors the Arabic product.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

const CORE_FIELDS = array( '_regular_price', '_sale_price', '_price', '_manage_stock', '_stock', '_stock_status', '_sku' );

/** The cost fields this feature is allowed to change. */
const COST_FIELDS = array( '_cogs_total_value', '_cogs_value_is_additive' );

/** Fields WooCommerce maintains itself and writes on any CRUD save. Recorded, never treated as a failure. */
const BOOKKEEPING_FIELDS = array( '_product_version', '_wc_average_rating', '_wc_review_count', '_thumbnail_id', '_variation_description', '_edit_lock', '_edit_last', '_wp_old_slug' );

final class Workflows {

	private Scenario $s;
	private int $admin_id;
	private string $admin_login = '';
	private string $token = '';
	private string $app_password = '';
	private string $web_secret = '';
	private array $cookies = array();

	public function __construct( Scenario $s ) {
		$this->s        = $s;
		$this->admin_id = $this->make_admin();
	}

	private function make_admin(): int {
		$this->admin_login = 't11_admin_' . wp_rand();
		$id = wp_insert_user( array( 'user_login' => $this->admin_login, 'user_pass' => wp_generate_password( 32 ), 'role' => 'administrator', 'user_email' => 't11-' . wp_rand() . '@example.invalid' ) );
		$this->s->track_user( $id );
		$expiry      = time() + HOUR_IN_SECONDS;
		$token       = \WP_Session_Tokens::get_instance( $id )->create( $expiry );
		$this->token = $token;
		list( $this->app_password ) = \WP_Application_Passwords::create_new_application_password( $id, array( 'name' => 't11' ) );
		foreach ( array( AUTH_COOKIE => 'auth', LOGGED_IN_COOKIE => 'logged_in' ) as $name => $scheme ) {
			$this->cookies[] = new \WP_Http_Cookie( array( 'name' => $name, 'value' => wp_generate_auth_cookie( $id, $expiry, $scheme, $token ) ) );
		}
		return $id;
	}

	// ---- Products -------------------------------------------------------------------------------------------

	/**
	 * Register $id as an English original. WPML assigns a new post's language from the current language, and in a
	 * WP-CLI process that stays switched after a translation is made; wp-admin assigns it from the admin language.
	 */
	private function as_english( int $id, string $type ): void {
		$details = apply_filters( 'wpml_element_language_details', null, array( 'element_id' => $id, 'element_type' => $type ) );
		if ( ! $details || 'en' !== ( $details->language_code ?? null ) ) {
			do_action( 'wpml_set_element_language_details', array( 'element_id' => $id, 'element_type' => 'post_' . $type, 'trid' => false, 'language_code' => 'en' ) );
		}
	}

	public function simple( string $sku, ?float $cost ): int {
		do_action( 'wpml_switch_language', 'en' );
		$p = new \WC_Product_Simple();
		$p->set_name( "t11 $sku" );
		$p->set_sku( $sku );
		$p->set_regular_price( '300' );
		$p->set_manage_stock( true );
		$p->set_stock_quantity( 10 );
		$p->set_status( 'publish' );
		$p->set_cogs_value( $cost );
		$p->save();
		$this->as_english( $p->get_id(), 'product' );
		$this->s->track_post( $p->get_id() );
		return $p->get_id();
	}

	/** @return array{0:int,1:int[]} parent id and variation ids */
	public function variable( string $sku, array $costs ): array {
		do_action( 'wpml_switch_language', 'en' );
		$attr = new \WC_Product_Attribute();
		$attr->set_name( 'Size' );
		$attr->set_options( array_keys( $costs ) );
		$attr->set_visible( true );
		$attr->set_variation( true );
		$parent = new \WC_Product_Variable();
		$parent->set_name( "t11 $sku" );
		$parent->set_sku( $sku );
		$parent->set_status( 'publish' );
		$parent->set_attributes( array( $attr ) );
		$parent->save();
		$this->as_english( $parent->get_id(), 'product' );
		$this->s->track_post( $parent->get_id() );
		$ids = array();
		foreach ( $costs as $size => $cost ) {
			$v = new \WC_Product_Variation();
			$v->set_parent_id( $parent->get_id() );
			$v->set_attributes( array( 'size' => $size ) ); // custom attribute: the value is the option text
			$v->set_sku( "$sku-$size" );
			$v->set_regular_price( '200' );
			$v->set_manage_stock( true );
			$v->set_stock_quantity( 5 );
			$v->set_cogs_value( $cost );
			$v->save();
			$this->as_english( $v->get_id(), 'product_variation' );
			$this->s->track_post( $v->get_id() );
			$ids[] = $v->get_id();
		}
		\WC_Product_Variable::sync( $parent->get_id() );
		return array( $parent->get_id(), $ids );
	}

	// ---- Translations -----------------------------------------------------------------------------------------

	public function translate( string $method, int $en_id ): int {
		global $sitepress, $woocommerce_wpml, $wpdb;
		if ( 'duplicate' === $method ) {
			// make_duplicate's return value is not a reliable post id; resolve the translation through WPML.
			$sitepress->make_duplicate( $en_id, 'ar' );
			$ar = (int) apply_filters( 'wpml_object_id', $en_id, 'product', false, 'ar' );
		} else {
			$job = new \WCML_Editor_UI_Product_Job( array( 'job_id' => $en_id, 'target' => 'ar' ), $woocommerce_wpml, $sitepress, $wpdb );
			$job->save_translations( array( md5( 'title' ) => 'منتج ' . $en_id, md5( 'slug' ) => '', md5( 'product_content' ) => '', md5( 'product_excerpt' ) => '' ) );
			$ar = (int) apply_filters( 'wpml_object_id', $en_id, 'product', false, 'ar' );
		}
		do_action( 'wpml_switch_language', 'en' );
		// A translation only counts if it is a distinct post registered in Arabic, with the original in English.
		$lang_of = fn( int $id ) => apply_filters( 'wpml_post_language_details', null, $id )['language_code'] ?? null;
		if ( ! $ar || $ar === $en_id || 'ar' !== $lang_of( $ar ) || 'en' !== $lang_of( $en_id ) ) {
			$this->s->note( sprintf( '  %s translation of %d not created: AR id %d, EN lang %s, AR lang %s', $method, $en_id, $ar, var_export( $lang_of( $en_id ), true ), $ar ? var_export( $lang_of( $ar ), true ) : 'n/a' ) );
			return 0;
		}
		$this->track_translation( $en_id, $ar );
		return $ar;
	}

	private function track_translation( int $en_id, int $ar_id ): void {
		if ( ! $ar_id ) {
			return;
		}
		$this->s->track_post( $ar_id );
		$ar = wc_get_product( $ar_id );
		foreach ( $ar && $ar->is_type( 'variable' ) ? $ar->get_children() : array() as $child ) {
			$this->s->track_post( (int) $child );
		}
	}

	/** The Arabic counterpart of a product or variation, or 0 when there is none distinct from $id. */
	public static function ar_of( int $id ): int {
		$type = 'product_variation' === get_post_type( $id ) ? 'product_variation' : 'product';
		$ar   = (int) apply_filters( 'wpml_object_id', $id, $type, false, 'ar' );
		return $ar && $ar !== $id ? $ar : 0;
	}

	// ---- Cost channels ---------------------------------------------------------------------------------------

	public function set_cost( string $channel, int $id, ?float $cost ): string {
		$is_variation = 'product_variation' === get_post_type( $id );
		switch ( $channel ) {
			case 'crud-cli':
				$p = wc_get_product( $id );
				$p->set_cogs_value( $cost );
				$p->save();
				return 'ok';
			case 'crud-web':
				return $this->crud_web( $id, $cost );
			case 'rest-http':
				$route = $is_variation ? '/wc/v3/products/' . wp_get_post_parent_id( $id ) . "/variations/$id" : "/wc/v3/products/$id";
				$r     = wp_remote_request( add_query_arg( 'rest_route', $route, home_url( '/' ) ), array(
					'method'  => 'PUT',
					'timeout' => 60,
					'headers' => array( 'Authorization' => 'Basic ' . base64_encode( $this->admin_login . ':' . $this->app_password ), 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode( array( 'cost_of_goods_sold' => array( 'values' => null === $cost ? array() : array( array( 'defined_value' => $cost ) ) ) ) ),
				) );
				$code = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
				return 200 === $code ? 'ok' : "REST HTTP $code";
			case 'import-http':
				return $this->import_http( wc_get_product( $id )->get_sku(), $is_variation, $cost );
			case 'admin-http':
				return $is_variation ? $this->admin_variation_cost( $id, $cost ) : $this->admin_product_cost( $id, $cost );
		}
		return 'unknown channel';
	}

	/** A nonce for the test admin's HTTP session, computed exactly as wp_create_nonce() does inside that session. */
	private function session_nonce( string $action ): string {
		$i = wp_nonce_tick( $action );
		return substr( wp_hash( $i . '|' . $action . '|' . $this->admin_id . '|' . $this->token, 'nonce' ), -12, 10 );
	}

	private function import_http( string $sku, bool $is_variation, ?float $cost ): string {
		$upload = wp_upload_dir();
		$file   = trailingslashit( $upload['basedir'] ) . 't11-import-' . wp_generate_password( 8, false ) . '.csv';
		$fh     = fopen( $file, 'w' );
		fputcsv( $fh, array( 'SKU', 'Type', 'Cost of goods' ) );
		fputcsv( $fh, array( $sku, $is_variation ? 'variation' : 'simple', null === $cost ? '' : (string) $cost ) );
		fclose( $fh );
		$r = wp_remote_post( admin_url( 'admin-ajax.php' ), array( 'cookies' => $this->cookies, 'timeout' => 120, 'body' => array(
			'action'          => 'woocommerce_do_ajax_product_import',
			'security'        => $this->session_nonce( 'wc-product-import' ),
			'file'            => $file,
			'position'        => 0,
			'update_existing' => 1,
			'mapping'         => array( 'from' => array( 'SKU', 'Type', 'Cost of goods' ), 'to' => array( 'sku', 'type', 'cogs_value' ) ),
		) ) );
		if ( file_exists( $file ) ) {
			unlink( $file );
		}
		$json = is_wp_error( $r ) ? null : json_decode( wp_remote_retrieve_body( $r ), true );
		if ( empty( $json['success'] ) ) {
			return 'import HTTP ' . ( is_wp_error( $r ) ? $r->get_error_message() : wp_remote_retrieve_response_code( $r ) . ' ' . substr( wp_remote_retrieve_body( $r ), 0, 160 ) );
		}
		return ( (int) ( $json['data']['updated'] ?? 0 ) ) > 0 ? 'ok' : 'import updated 0: ' . wp_json_encode( $json['data'] ?? null );
	}

	private function crud_web( int $id, ?float $cost ): string {
		if ( ! $this->web_secret ) {
			$this->web_secret = wp_generate_password( 24, false );
			update_option( 't11_crud_web_secret', $this->web_secret, false );
			$dir = WPMU_PLUGIN_DIR;
			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
			}
			file_put_contents( $dir . '/t11-crud-web.php', file_get_contents( __DIR__ . '/fixtures/t11-crud-web.php.txt' ) );
			$this->s->on_finish( function () use ( $dir ) {
				@unlink( $dir . '/t11-crud-web.php' );
				delete_option( 't11_crud_web_secret' );
			} );
		}
		$r    = wp_remote_get( add_query_arg( array( 't11_crud' => $this->web_secret, 'id' => $id, 'cost' => null === $cost ? '' : $cost ), home_url( '/' ) ), array( 'timeout' => 60 ) );
		$body = is_wp_error( $r ) ? $r->get_error_message() : wp_remote_retrieve_body( $r );
		return 'ok:front:web' === $body ? 'ok' : 'crud-web: ' . substr( $body, 0, 120 );
	}

	// ---- wp-admin over HTTP ----------------------------------------------------------------------------------

	private function http( string $method, string $url, array $body = array() ): array {
		$args = array( 'cookies' => $this->cookies, 'timeout' => 60, 'redirection' => 0, 'method' => $method );
		if ( $body ) {
			$args['body'] = $body;
		}
		$r = wp_remote_request( $url, $args );
		return is_wp_error( $r ) ? array( 0, $r->get_error_message() ) : array( (int) wp_remote_retrieve_response_code( $r ), wp_remote_retrieve_body( $r ) );
	}

	/** Collect a form's successful controls the way a browser submits them. */
	private static function form_fields( string $html, ?string $form_id ): array {
		$doc = new \DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html );
		libxml_clear_errors();
		$xp    = new \DOMXPath( $doc );
		$scope = $form_id ? "//form[@id='$form_id']" : '';
		$out   = array();
		foreach ( $xp->query( "$scope//input[@name] | $scope//textarea[@name] | $scope//select[@name]" ) as $el ) {
			$name = $el->getAttribute( 'name' );
			if ( $el->hasAttribute( 'disabled' ) ) {
				continue;
			}
			if ( 'input' === $el->nodeName ) {
				$type = strtolower( $el->getAttribute( 'type' ) ?: 'text' );
				if ( in_array( $type, array( 'submit', 'button', 'image', 'file', 'reset' ), true ) ) {
					continue;
				}
				if ( in_array( $type, array( 'checkbox', 'radio' ), true ) && ! $el->hasAttribute( 'checked' ) ) {
					continue;
				}
				$out[] = array( $name, $el->hasAttribute( 'value' ) ? $el->getAttribute( 'value' ) : ( 'checkbox' === $type ? 'on' : '' ) );
			} elseif ( 'textarea' === $el->nodeName ) {
				$out[] = array( $name, $el->textContent );
			} else {
				$selected = $xp->query( './/option[@selected]', $el );
				$options  = $selected->length ? $selected : $xp->query( './/option[1]', $el );
				foreach ( $options as $opt ) {
					$out[] = array( $name, $opt->hasAttribute( 'value' ) ? $opt->getAttribute( 'value' ) : $opt->textContent );
					if ( ! $el->hasAttribute( 'multiple' ) ) {
						break;
					}
				}
			}
		}
		return $out;
	}

	private static function encode( array $pairs ): string {
		return implode( '&', array_map( fn( $p ) => rawurlencode( $p[0] ) . '=' . rawurlencode( (string) $p[1] ), $pairs ) );
	}

	private static function replace( array $pairs, string $name, string $value ): array {
		$found = false;
		foreach ( $pairs as $i => $p ) {
			if ( $p[0] === $name ) {
				$pairs[ $i ][1] = $value;
				$found          = true;
			}
		}
		if ( ! $found ) {
			$pairs[] = array( $name, $value );
		}
		return $pairs;
	}

	private function admin_product_cost( int $id, ?float $cost ): string {
		list( $code, $html ) = $this->http( 'GET', admin_url( "post.php?post=$id&action=edit" ) );
		if ( 200 !== $code ) {
			return "admin GET $code";
		}
		$fields = self::form_fields( $html, 'post' );
		if ( ! array_filter( $fields, fn( $p ) => '_cogs_value' === $p[0] ) ) {
			return 'admin form has no _cogs_value field';
		}
		$fields   = self::replace( $fields, '_cogs_value', null === $cost ? '' : (string) $cost );
		$fields[] = array( 'save', 'Update' );
		$r        = wp_remote_post( admin_url( 'post.php' ), array( 'cookies' => $this->cookies, 'timeout' => 60, 'redirection' => 0, 'body' => self::encode( $fields ), 'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ) ) );
		$code = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
		return 302 === $code ? 'ok' : "admin POST $code";
	}

	private function admin_variation_cost( int $variation_id, ?float $cost ): string {
		$parent = wp_get_post_parent_id( $variation_id );
		list( $code, $html ) = $this->http( 'GET', admin_url( "post.php?post=$parent&action=edit" ) );
		if ( 200 !== $code || ! preg_match( '/"load_variations_nonce":"([a-z0-9]+)"/', $html, $load ) || ! preg_match( '/"save_variations_nonce":"([a-z0-9]+)"/', $html, $save ) ) {
			return "admin variations page $code, nonces missing";
		}
		$r = wp_remote_post( admin_url( 'admin-ajax.php' ), array( 'cookies' => $this->cookies, 'timeout' => 60, 'body' => array( 'action' => 'woocommerce_load_variations', 'security' => $load[1], 'product_id' => $parent, 'attributes' => array(), 'page' => 1, 'per_page' => 50 ) ) );
		$fields = self::form_fields( '<form id="v">' . wp_remote_retrieve_body( $r ) . '</form>', 'v' );
		$loop   = null;
		foreach ( $fields as $p ) {
			if ( preg_match( '/^variable_post_id\[(\d+)\]$/', $p[0], $m ) && (int) $p[1] === $variation_id ) {
				$loop = $m[1];
			}
		}
		if ( null === $loop ) {
			return 'variation not in the loaded admin panel';
		}
		$fields = self::replace( $fields, "variable_cost_value[$loop]", null === $cost ? '' : (string) $cost );
		$fields = array_merge( array( array( 'action', 'woocommerce_save_variations' ), array( 'security', $save[1] ), array( 'product_id', $parent ), array( 'product-type', 'variable' ) ), $fields );
		$r      = wp_remote_post( admin_url( 'admin-ajax.php' ), array( 'cookies' => $this->cookies, 'timeout' => 60, 'body' => self::encode( $fields ), 'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ) ) );
		$code   = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
		return 200 === $code ? 'ok' : "admin AJAX $code";
	}

	// ---- Observation -----------------------------------------------------------------------------------------

	public static function cost( int $id ): ?float {
		wp_cache_flush();
		$p = $id ? wc_get_product( $id ) : null;
		return $p ? $p->get_cogs_value() : null;
	}

	public static function snapshot( int $id ): array {
		wp_cache_flush();
		$post = get_post( $id );
		$out  = array( 'post_title' => $post ? $post->post_title : null, 'post_status' => $post ? $post->post_status : null );
		foreach ( CORE_FIELDS as $k ) {
			$out[ $k ] = get_post_meta( $id, $k, true );
		}
		return $out;
	}

	/**
	 * The product a SKU resolves to, which is not always the one you meant: a WPML duplicate carries the SKU of
	 * its original, so a CSV import row can reach either of them.
	 */
	public static function sku_target( int $id ): int {
		wp_cache_flush();
		$product = wc_get_product( $id );
		return $product && $product->get_sku() ? (int) wc_get_product_id_by_sku( $product->get_sku() ) : 0;
	}

	/** Everything stored about a post: fields, custom fields, language, translation group, product terms. */
	public static function stored( int $id ): array {
		wp_cache_flush();
		$post = get_post( $id );
		$meta = get_post_meta( $id );
		ksort( $meta );
		$terms = array();
		foreach ( array( 'product_type', 'product_cat', 'product_tag', 'product_visibility' ) as $tax ) {
			$got           = wp_get_object_terms( $id, $tax, array( 'fields' => 'slugs' ) );
			$terms[ $tax ] = is_wp_error( $got ) ? 'error' : implode( ',', $got );
		}
		return array(
			'post'  => array(
				'title'      => $post ? $post->post_title : null,
				'name'       => $post ? $post->post_name : null,
				'status'     => $post ? $post->post_status : null,
				'parent'     => $post ? (int) $post->post_parent : 0,
				'menu_order' => $post ? (int) $post->menu_order : 0,
				'content'    => $post ? $post->post_content : null,
				'excerpt'    => $post ? $post->post_excerpt : null,
			),
			'meta'  => $meta,
			'lang'  => apply_filters( 'wpml_post_language_details', null, $id )['language_code'] ?? 'unknown',
			'trid'  => (int) apply_filters( 'wpml_element_trid', null, $id, 'post_' . get_post_type( $id ) ),
			'terms' => $terms,
		);
	}

	/**
	 * Differences between two stored snapshots, split into what must not move and what WooCommerce maintains
	 * itself on any CRUD save.
	 *
	 * @return array{authored:string[],generated:string[]}
	 */
	public static function differences( array $before, array $after, bool $is_variation ): array {
		$authored  = array();
		$generated = array();
		foreach ( array( 'post', 'terms' ) as $group ) {
			foreach ( $before[ $group ] as $key => $value ) {
				if ( $after[ $group ][ $key ] === $value ) {
					continue;
				}
				$line = sprintf( '%s %s: %s -> %s', $group, $key, var_export( $value, true ), var_export( $after[ $group ][ $key ], true ) );
				if ( $is_variation && 'title' === $key ) {
					$generated[] = $line . ' (WooCommerce regenerates a variation title on any save)';
				} else {
					$authored[] = $line;
				}
			}
		}
		foreach ( array( 'lang', 'trid' ) as $key ) {
			if ( $before[ $key ] !== $after[ $key ] ) {
				$authored[] = sprintf( '%s: %s -> %s', $key, var_export( $before[ $key ], true ), var_export( $after[ $key ], true ) );
			}
		}
		foreach ( array_unique( array_merge( array_keys( $before['meta'] ), array_keys( $after['meta'] ) ) ) as $key ) {
			if ( in_array( $key, COST_FIELDS, true ) ) {
				continue;
			}
			$was = $before['meta'][ $key ] ?? null;
			$now = $after['meta'][ $key ] ?? null;
			if ( $was === $now ) {
				continue;
			}
			$line = sprintf( '%s: %s -> %s', $key, wp_json_encode( $was ), wp_json_encode( $now ) );
			if ( in_array( $key, BOOKKEEPING_FIELDS, true ) ) {
				$generated[] = $line;
			} else {
				$authored[] = $line;
			}
		}
		return array( 'authored' => $authored, 'generated' => $generated );
	}

	/** Every line the synchronisation has logged, from whichever handler this runtime uses. */
	public static function sync_log_lines(): array {
		global $wpdb;
		$out   = array();
		$table = $wpdb->prefix . 'woocommerce_log';
		if ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT message FROM {$table} WHERE source = %s", 'mizzey-cost-sync' ) ) as $m ) {
				$out[] = 'db: ' . $m;
			}
		}
		foreach ( (array) glob( self::log_dir() . 'mizzey-cost-sync*.log' ) as $file ) {
			foreach ( (array) file( $file ) as $line ) {
				$out[] = 'file: ' . trim( $line );
			}
		}
		return $out;
	}

	/** Remove everything the synchronisation logged, so the runtime is left as it was found. */
	public static function clear_sync_log(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'woocommerce_log';
		if ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			$wpdb->delete( $table, array( 'source' => 'mizzey-cost-sync' ) );
		}
		foreach ( (array) glob( self::log_dir() . 'mizzey-cost-sync*.log' ) as $file ) {
			@unlink( $file );
		}
	}

	private static function log_dir(): string {
		return trailingslashit( defined( 'WC_LOG_DIR' ) ? WC_LOG_DIR : WP_CONTENT_DIR . '/uploads/wc-logs/' );
	}

	/** Cost recorded by an order for one unit, placed in $lang; NAN when the product cannot be loaded. */
	public function order_cost( int $product_id, string $lang ): float {
		do_action( 'wpml_switch_language', $lang );
		wp_cache_flush();
		$product = wc_get_product( $product_id );
		$cost    = NAN;
		if ( $product ) {
			$cost = $this->s->order_for( $product, 1 )->get_cogs_total_value();
		} else {
			$this->s->note( sprintf( '  product %d (%s, status %s) could not be loaded for an order', $product_id, get_post_type( $product_id ) ?: 'missing', get_post_status( $product_id ) ?: 'missing' ) );
		}
		do_action( 'wpml_switch_language', 'en' );
		return $cost;
	}
}

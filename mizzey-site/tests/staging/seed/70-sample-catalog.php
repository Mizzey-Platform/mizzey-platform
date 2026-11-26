<?php
/**
 * Staging seed: the sample catalogue the closed Stage 2 boards draw, so a reviewer browsing staging sees the boards'
 * products, categories, brands and collections on the home page and in the listings (spec 066).
 *
 * The catalogue is not in this repository. Its names, brands, prices and photos are the boards' sample content and are
 * kept only on the server, in MIZZEY_STAGING_SAMPLE_DIR (default ~/mizzey-staging/sample-catalog/): a `catalog.json`
 * and the `images/` it names, copied there by hand, read the way 60-home-page.php reads the logo. Without them this
 * step says so in one line and changes nothing.
 *
 * What it makes, each found again on a later run and updated only when the manifest changed (a hash per record):
 *
 *  - categories (with their subcategories), brands and collections, each with its Arabic term, description, image and
 *    banner; those the manifest numbers are shown on the home page in that order, and the placeholder terms step 60
 *    shows there (slugs starting `sample-` that this step did not make) are taken off it;
 *  - the menus' category order, the manifest's (the boards'), as WooCommerce's own category order in both languages;
 *    every other top category is taken out of the menus with the store's "Show in the main menu" setting (marked as
 *    set by this step), so the menus list the boards' categories only;
 *  - the store attributes the products use (a global attribute per option, as the catalogue load makes them,
 *    specs/053), each value with its Arabic term;
 *  - simple and variable products, keyed by their SKU (`SAMPLE-` only), with price, sale price, stock, badges,
 *    attributes, key features (the short description), description, category, brand, collection, photo, gallery and
 *    video (an uploaded file with its poster); each file is added to the media library once and reused;
 *  - their Arabic records, through WooCommerce Multilingual's product editor as the catalogue load writes them, in a
 *    process of their own (finding A11: never created and translated in one process): the staging tool runs this file
 *    a second time with the argument `translate` (SEED_PASSES). A run that wrote no product translates in the same
 *    process, as nothing was created in it;
 *  - the home page's product sections (from step 60) set to the manifest's hand-picked lists, in each language.
 *
 * Ratings come from real reviews, written by 75-sample-reviews.php (a review needs a customer with a delivered order,
 * specs/020, D-17). It never resets an account or a password, never deletes anything, and touches no product whose
 * SKU does not start with `SAMPLE-`. Staging only.
 *
 * @package MizzeySite\Tests\Staging
 */

// The seed stands in for the ERP's projection, so it may set stock (D-19 item 5; StockWriteGuard).
add_filter( 'mizzey_stock_write_permitted', '__return_true' );

defined( 'ABSPATH' ) || exit;
defined( 'MIZZEY_STAGING' ) && MIZZEY_STAGING || WP_CLI::error( 'staging only' );

use MizzeySite\Catalogue\ProductFields;
use MizzeySite\I18n\ProductTranslationJob;
use MizzeySite\Storefront\HeaderData;
use MizzeySite\Storefront\HomeSections;
use MizzeySite\Storefront\TermContent;

global $sitepress;
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
$sitepress->switch_lang( 'en', true );

defined( 'MIZZEY_SAMPLE_PREFIX' ) || define( 'MIZZEY_SAMPLE_PREFIX', 'SAMPLE-' );
defined( 'MIZZEY_SAMPLE_MARK' ) || define( 'MIZZEY_SAMPLE_MARK', '_mizzey_sample' );

// ---- The manifest, from the server only -----------------------------------------------------------------------------
$dir = (string) getenv( 'MIZZEY_STAGING_SAMPLE_DIR' );
if ( '' === $dir ) {
	$dir = is_dir( dirname( __DIR__, 2 ) . '/sample-catalog' ) ? dirname( __DIR__, 2 ) . '/sample-catalog' : rtrim( (string) getenv( 'HOME' ), '/' ) . '/mizzey-staging/sample-catalog';
}
$dir = rtrim( $dir, '/' );
if ( ! is_readable( $dir . '/catalog.json' ) ) {
	WP_CLI::success( "sample catalogue skipped: put catalog.json and its images/ in {$dir}" );
	return;
}
$catalog = json_decode( (string) file_get_contents( $dir . '/catalog.json' ), true );
if ( ! is_array( $catalog ) || 1 !== (int) ( $catalog['version'] ?? 0 ) || ! isset( $catalog['products'] ) || ! is_array( $catalog['products'] ) ) {
	WP_CLI::error( "{$dir}/catalog.json is not a version 1 sample catalogue" );
}
foreach ( $catalog['products'] as $entry ) {
	foreach ( array_merge( array( $entry ), (array) ( $entry['variations'] ?? array() ) ) as $record ) {
		if ( 0 !== strpos( (string) ( $record['sku'] ?? '' ), MIZZEY_SAMPLE_PREFIX ) ) {
			WP_CLI::error( 'every SKU in the sample catalogue starts with ' . MIZZEY_SAMPLE_PREFIX . ': ' . wp_json_encode( $record['sku'] ?? null ) );
		}
	}
}
$skus = array();
foreach ( $catalog['products'] as $entry ) {
	foreach ( array_merge( array( $entry ), (array) ( $entry['variations'] ?? array() ) ) as $record ) {
		$skus[] = (string) $record['sku'];
	}
}
if ( count( $skus ) !== count( array_unique( $skus ) ) ) {
	WP_CLI::error( 'the sample catalogue names one SKU twice: ' . implode( ', ', array_keys( array_filter( array_count_values( $skus ), static fn ( int $n ): bool => $n > 1 ) ) ) );
}
$phase = isset( $args[0] ) && 'translate' === $args[0] ? 'translate' : 'sources';

// ---- Helpers --------------------------------------------------------------------------------------------------------
/** The term with exactly this id: WPML would otherwise hand back its translation in the current language. */
$exact = static function ( int $id, string $taxonomy ): ?WP_Term {
	add_filter( 'wpml_disable_term_adjust_id', '__return_true' );
	$term = get_term( $id, $taxonomy );
	remove_filter( 'wpml_disable_term_adjust_id', '__return_true' );
	return $term instanceof WP_Term && $term->term_id === $id ? $term : null;
};

/** A record's fingerprint: a later run with the same manifest changes nothing. */
$hash = static fn ( $value ): string => md5( (string) wp_json_encode( $value ) );

/** A picture from the manifest's folder, added to the media library once, found again by its path. */
$media = static function ( string $relative, string $alt ) use ( $dir ): int {
	if ( '' === $relative ) {
		return 0;
	}
	$found = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => MIZZEY_SAMPLE_MARK . '_image', 'meta_value' => $relative, 'suppress_filters' => true ) );
	if ( $found ) {
		return (int) $found[0];
	}
	$file = $dir . '/' . ltrim( $relative, '/' );
	if ( false !== strpos( $relative, '..' ) || ! is_file( $file ) ) {
		WP_CLI::error( "the sample catalogue names {$relative}, which is not in {$dir}" );
	}
	$copy = wp_tempnam( basename( $file ) );
	copy( $file, $copy );
	$id = media_handle_sideload( array( 'name' => basename( $file ), 'tmp_name' => $copy ), 0, 'Sample image (staging)' );
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id->get_error_message() );
	}
	update_post_meta( (int) $id, MIZZEY_SAMPLE_MARK . '_image', $relative );
	update_post_meta( (int) $id, '_wp_attachment_image_alt', $alt );
	return (int) $id;
};

/**
 * A term this step makes, in English, found again by its slug; its Arabic term made or renamed beside it. The
 * Arabic name falls back to the English one (a brand's name is its name in both languages).
 *
 * @param array<string,mixed> $row   name, name_ar, description, description_ar.
 * @return array{0:int,1:int} The English and the Arabic term id.
 */
$term = static function ( string $taxonomy, string $slug, array $row, int $parent, int $ar_parent ) use ( $sitepress, $exact ): array {
	$type = 'tax_' . $taxonomy;
	$sitepress->switch_lang( 'en', true );
	$found = get_term_by( 'slug', $slug, $taxonomy );
	$en    = $found instanceof WP_Term ? (int) $found->term_id : 0;
	$want  = array( 'name' => (string) $row['name'], 'parent' => $parent, 'description' => (string) ( $row['description'] ?? '' ) );
	if ( ! $en ) {
		$made = wp_insert_term( $want['name'], $taxonomy, array( 'slug' => $slug, 'parent' => $parent, 'description' => $want['description'] ) );
		if ( is_wp_error( $made ) ) {
			WP_CLI::error( "{$taxonomy} {$slug}: " . $made->get_error_message() );
		}
		$en = (int) $made['term_id'];
		$sitepress->set_element_language_details( (int) $made['term_taxonomy_id'], $type, null, 'en' );
		update_term_meta( $en, MIZZEY_SAMPLE_MARK, $slug );
	} elseif ( $found->name !== $want['name'] || (int) $found->parent !== $parent || $found->description !== $want['description'] ) {
		wp_update_term( $en, $taxonomy, $want );
	}
	$source = $exact( $en, $taxonomy );
	if ( ! $source instanceof WP_Term || ! apply_filters( 'wpml_is_translated_taxonomy', false, $taxonomy ) ) {
		return array( $en, 0 );
	}
	$arabic = array(
		'name'        => '' !== (string) ( $row['name_ar'] ?? '' ) ? (string) $row['name_ar'] : $want['name'],
		'parent'      => $ar_parent,
		'description' => (string) ( $row['description_ar'] ?? '' ),
	);
	$trid  = (int) $sitepress->get_element_trid( (int) $source->term_taxonomy_id, $type );
	$group = $sitepress->get_element_translations( $trid, $type );
	// In Arabic, as WPML resolves a term's slug check in the current language.
	$sitepress->switch_lang( 'ar', true );
	if ( isset( $group['ar'] ) ) {
		$ar      = (int) $group['ar']->term_id;
		$current = $exact( $ar, $taxonomy );
		if ( $current instanceof WP_Term && ( $current->name !== $arabic['name'] || (int) $current->parent !== $ar_parent || $current->description !== $arabic['description'] ) ) {
			wp_update_term( $ar, $taxonomy, $arabic );
		}
	} else {
		$made = wp_insert_term( $arabic['name'], $taxonomy, array( 'slug' => $slug . '-ar', 'parent' => $ar_parent, 'description' => $arabic['description'] ) );
		if ( is_wp_error( $made ) ) {
			$sitepress->switch_lang( 'en', true );
			WP_CLI::error( "{$taxonomy} {$slug} in Arabic: " . $made->get_error_message() );
		}
		$ar = (int) $made['term_id'];
		$sitepress->set_element_language_details( (int) $made['term_taxonomy_id'], $type, $trid, 'ar', 'en' );
		update_term_meta( $ar, MIZZEY_SAMPLE_MARK, $slug );
	}
	$sitepress->switch_lang( 'en', true );
	return array( $en, $ar );
};

/** An English product record of the catalogue, by SKU. */
$product_id = static function ( string $sku ): int {
	$id = (int) wc_get_product_id_by_sku( $sku );
	return $id ? (int) apply_filters( 'wpml_object_id', $id, get_post_type( $id ) ?: 'product', true, 'en' ) : 0;
};

if ( 'sources' === $phase ) {
	// ---- Categories, brands, collections ----------------------------------------------------------------------------
	$terms = array( 'product_cat' => array(), 'product_brand' => array(), 'product_collection' => array() );
	$lists = array( 'product_cat' => 'categories', 'product_brand' => 'brands', 'product_collection' => 'collections' );
	$home  = array();
	foreach ( $lists as $taxonomy => $list ) {
		$rows = (array) ( $catalog[ $list ] ?? array() );
		// Parents before children, whatever the manifest's order.
		usort( $rows, static fn ( array $a, array $b ): int => (int) ( '' !== (string) ( $a['parent'] ?? '' ) ) <=> (int) ( '' !== (string) ( $b['parent'] ?? '' ) ) );
		// The menus' order (WooCommerce's own category order, which the header reads) is the manifest's: the boards'.
		$place = array_flip( array_map( static fn ( array $row ): string => sanitize_title( (string) $row['key'] ), $rows ) );
		foreach ( $rows as $row ) {
			$key    = sanitize_title( (string) $row['key'] );
			$parent = (string) ( $row['parent'] ?? '' );
			if ( '' !== $parent && ! isset( $terms[ $taxonomy ][ $parent ] ) ) {
				WP_CLI::error( "{$list}: {$key} names a parent that is not listed, {$parent}" );
			}
			$ids = $term( $taxonomy, 'sample-' . $key, $row, '' !== $parent ? $terms[ $taxonomy ][ $parent ][0] : 0, '' !== $parent ? $terms[ $taxonomy ][ $parent ][1] : 0 );

			$terms[ $taxonomy ][ $key ] = $ids;
			$image  = $media( (string) ( $row['image'] ?? '' ), (string) $row['name'] );
			$banner = $media( (string) ( $row['banner'] ?? '' ), (string) $row['name'] );
			if ( $image && (int) get_term_meta( $ids[0], TermContent::IMAGE, true ) !== $image ) {
				update_term_meta( $ids[0], TermContent::IMAGE, $image );
			}
			if ( $banner && (int) get_term_meta( $ids[0], TermContent::BANNER, true ) !== $banner ) {
				update_term_meta( $ids[0], TermContent::BANNER, $banner );
			}
			if ( (int) ( $row['home'] ?? 0 ) > 0 ) {
				$home[ $taxonomy ][ $ids[0] ] = (int) $row['home'];
			}
			if ( 'product_cat' === $taxonomy ) {
				foreach ( array_filter( $ids ) as $id ) {
					if ( (int) get_term_meta( $id, 'order', true ) !== $place[ $key ] + 1 ) {
						update_term_meta( $id, 'order', $place[ $key ] + 1 );
					}
				}
			}
		}
	}

	// In the menus: only the manifest's categories. Every other top category (step 20's placeholders, a catalogue load
	// tried on staging) is taken out of the menus by the store's own "Show in the main menu" setting (HeaderData::HIDDEN),
	// kept on the category in the main language or on an Arabic-only one, and marked so a later run, or a person, can
	// tell this step set it. Its products, pages and scenarios are untouched. A manifest category it once hid is shown.
	$hidden = array( 'hidden' => 0, 'shown' => 0 );
	if ( isset( $catalog['categories'] ) ) {
		$default = (int) get_option( 'default_product_cat', 0 );
		// Each language's own list: WPML narrows a term query to the current language.
		$tops = array();
		foreach ( array( 'en', 'ar' ) as $lang ) {
			$sitepress->switch_lang( $lang, true );
			$tops = array_merge( $tops, (array) get_terms( array( 'taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => false ) ) );
		}
		$sitepress->switch_lang( 'en', true );
		foreach ( is_array( $tops ) ? $tops : array() as $top ) {
			if ( ! $top instanceof WP_Term || $top->term_id === $default ) {
				continue;
			}
			// The setting is read from the English category; an Arabic one with none (made in Arabic only) holds its own.
			$original = (int) apply_filters( 'wpml_object_id', $top->term_id, 'product_cat', false, 'en' );
			if ( $original > 0 && $original !== $top->term_id ) {
				continue;
			}
			$ours = '' !== (string) get_term_meta( $top->term_id, MIZZEY_SAMPLE_MARK, true );
			if ( ! $ours && 'yes' !== get_term_meta( $top->term_id, HeaderData::HIDDEN, true ) ) {
				update_term_meta( $top->term_id, HeaderData::HIDDEN, 'yes' );
				update_term_meta( $top->term_id, MIZZEY_SAMPLE_MARK . '_hidden', 'yes' );
				++$hidden['hidden'];
			} elseif ( $ours && get_term_meta( $top->term_id, MIZZEY_SAMPLE_MARK . '_hidden', true ) ) {
				delete_term_meta( $top->term_id, HeaderData::HIDDEN );
				delete_term_meta( $top->term_id, MIZZEY_SAMPLE_MARK . '_hidden' );
				++$hidden['shown'];
			}
		}
	}

	// On the home page: the manifest's terms in its order, and step 60's placeholder terms taken off it.
	foreach ( HomeSections::TAXONOMIES as $taxonomy ) {
		foreach ( (array) get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'meta_key' => HomeSections::ON_HOME, 'meta_value' => 'yes', 'suppress_filters' => true ) ) as $shown ) {
			if ( $shown instanceof WP_Term && 0 === strpos( $shown->slug, 'sample-' ) && ! get_term_meta( $shown->term_id, MIZZEY_SAMPLE_MARK, true ) ) {
				delete_term_meta( $shown->term_id, HomeSections::ON_HOME );
			}
		}
		foreach ( (array) ( $home[ $taxonomy ] ?? array() ) as $id => $position ) {
			if ( 'yes' !== get_term_meta( $id, HomeSections::ON_HOME, true ) ) {
				update_term_meta( $id, HomeSections::ON_HOME, 'yes' );
			}
			if ( (int) get_term_meta( $id, HomeSections::POSITION, true ) !== $position ) {
				update_term_meta( $id, HomeSections::POSITION, $position );
			}
		}
	}

	// ---- Store attributes, one per option, each value with its Arabic term ------------------------------------------
	$attributes = array();
	foreach ( (array) ( $catalog['attributes'] ?? array() ) as $row ) {
		$key = wc_sanitize_taxonomy_name( (string) $row['key'] );
		$tax = wc_attribute_taxonomy_name( $key );
		if ( wc_attribute_taxonomy_id_by_name( $tax ) <= 0 ) {
			$made = wc_create_attribute( array( 'name' => (string) $row['name'], 'slug' => $key, 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false ) );
			if ( is_wp_error( $made ) ) {
				WP_CLI::error( "attribute {$key}: " . $made->get_error_message() );
			}
			delete_transient( 'wc_attribute_taxonomies' );
			WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' );
			// Translatable, as the catalogue load records it (StoreAttributes supplies the default to WPML).
			\MizzeySite\Catalogue\StoreAttributes::forget();
			$sync = (array) $sitepress->get_setting( 'taxonomies_sync_option', array() );
			if ( ! array_key_exists( $tax, $sync ) ) {
				$sync[ $tax ] = 1;
				$sitepress->set_setting( 'taxonomies_sync_option', $sync, false );
			}
		}
		if ( ! taxonomy_exists( $tax ) ) {
			register_taxonomy( $tax, array( 'product' ), array( 'hierarchical' => false, 'show_ui' => false, 'query_var' => true, 'rewrite' => false, 'labels' => array( 'name' => (string) $row['name'] ) ) );
		}
		$attributes[ $key ] = array( 'taxonomy' => $tax, 'name_ar' => (string) ( $row['name_ar'] ?? '' ), 'values_ar' => (array) ( $row['values_ar'] ?? array() ), 'name' => (string) $row['name'] );
	}

	/** A value of an attribute: the store's term of that name, or one made, with its Arabic term. */
	$value = static function ( string $key, string $name ) use ( &$attributes, $sitepress, $exact ): int {
		if ( ! isset( $attributes[ $key ] ) ) {
			WP_CLI::error( "the sample catalogue uses the option {$key}, which it does not list under attributes" );
		}
		$tax = $attributes[ $key ]['taxonomy'];
		if ( isset( $attributes[ $key ]['terms'][ $name ] ) ) {
			return $attributes[ $key ]['terms'][ $name ];
		}
		$sitepress->switch_lang( 'en', true );
		$found = term_exists( $name, $tax );
		$id    = is_array( $found ) ? (int) $found['term_id'] : 0;
		if ( ! $id ) {
			$made = wp_insert_term( $name, $tax );
			if ( is_wp_error( $made ) ) {
				WP_CLI::error( "{$tax} {$name}: " . $made->get_error_message() );
			}
			$id = (int) $made['term_id'];
			$sitepress->set_element_language_details( (int) $made['term_taxonomy_id'], 'tax_' . $tax, null, 'en' );
			update_term_meta( $id, MIZZEY_SAMPLE_MARK, $key );
		}
		$source = $exact( $id, $tax );
		if ( $source instanceof WP_Term && apply_filters( 'wpml_is_translated_taxonomy', false, $tax ) ) {
			$arabic = (string) ( $attributes[ $key ]['values_ar'][ $name ] ?? '' );
			$arabic = '' !== $arabic ? $arabic : $name;
			$trid   = (int) $sitepress->get_element_trid( (int) $source->term_taxonomy_id, 'tax_' . $tax );
			$group  = $sitepress->get_element_translations( $trid, 'tax_' . $tax );
			$sitepress->switch_lang( 'ar', true );
			if ( ! isset( $group['ar'] ) ) {
				$made = wp_insert_term( $arabic, $tax, array( 'slug' => wp_unique_term_slug( $source->slug . '-ar', (object) array( 'taxonomy' => $tax, 'parent' => 0 ) ) ) );
				if ( ! is_wp_error( $made ) ) {
					$sitepress->set_element_language_details( (int) $made['term_taxonomy_id'], 'tax_' . $tax, $trid, 'ar', 'en' );
					update_term_meta( (int) $made['term_id'], MIZZEY_SAMPLE_MARK, $key );
				}
			} elseif ( get_term_meta( (int) $group['ar']->term_id, MIZZEY_SAMPLE_MARK, true ) && $group['ar']->name !== $arabic ) {
				wp_update_term( (int) $group['ar']->term_id, $tax, array( 'name' => $arabic ) );
			}
			$sitepress->switch_lang( 'en', true );
		}
		$attributes[ $key ]['terms'][ $name ] = $id;
		return $id;
	};

	/** @param array<string,list<string>> $values Option key to its values, $variation the keys products vary by. */
	$product_attributes = static function ( array $values, array $variation ) use ( &$attributes, $value ): array {
		$out = array();
		foreach ( $values as $key => $names ) {
			$key = wc_sanitize_taxonomy_name( (string) $key );
			$ids = array_map( static fn ( string $name ): int => $value( $key, $name ), array_values( array_unique( array_map( 'strval', (array) $names ) ) ) );
			$a   = new WC_Product_Attribute();
			$a->set_id( wc_attribute_taxonomy_id_by_name( $attributes[ $key ]['taxonomy'] ) );
			$a->set_name( $attributes[ $key ]['taxonomy'] );
			$a->set_options( $ids );
			$a->set_visible( true );
			$a->set_variation( in_array( $key, $variation, true ) );
			$out[ $attributes[ $key ]['taxonomy'] ] = $a;
		}
		return $out;
	};

	$features = static fn ( array $items ): string => $items ? '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', array_map( 'strval', $items ) ) ) . '</li></ul>' : '';
	$in_collections = array();
	foreach ( (array) ( $catalog['collections'] ?? array() ) as $row ) {
		foreach ( (array) ( $row['products'] ?? array() ) as $sku ) {
			$in_collections[ (string) $sku ][] = $terms['product_collection'][ sanitize_title( (string) $row['key'] ) ][0];
		}
	}

	// ---- Products ---------------------------------------------------------------------------------------------------
	$made = array( 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'variations' => 0 );
	foreach ( $catalog['products'] as $entry ) {
		$sku        = (string) $entry['sku'];
		$variations = (array) ( $entry['variations'] ?? array() );
		$image      = $media( (string) ( $entry['image'] ?? '' ), (string) $entry['name'] );
		$gallery    = array_values( array_filter( array_map( static fn ( $photo ): int => $media( (string) $photo, (string) $entry['name'] ), (array) ( $entry['gallery'] ?? array() ) ) ) );
		// The product video: an uploaded file, as staff add one (specs/036), with its poster as the file's own image.
		$video = $media( (string) ( $entry['video'] ?? '' ), (string) $entry['name'] );
		if ( $video ) {
			$poster = $media( (string) ( $entry['video_poster'] ?? '' ), (string) $entry['name'] );
			if ( $poster && (int) get_post_thumbnail_id( $video ) !== $poster ) {
				set_post_thumbnail( $video, $poster );
			}
		}
		$video_url  = $video ? (string) wp_get_attachment_url( $video ) : '';
		$categories = array_map( static fn ( string $key ): int => $terms['product_cat'][ sanitize_title( $key ) ][0] ?? WP_CLI::error( "{$sku} names the category {$key}, which is not listed" ), (array) ( $entry['categories'] ?? array() ) );
		$brand      = '' !== (string) ( $entry['brand'] ?? '' ) ? ( $terms['product_brand'][ sanitize_title( (string) $entry['brand'] ) ][0] ?? WP_CLI::error( "{$sku} names the brand {$entry['brand']}, which is not listed" ) ) : 0;
		$fingerprint = $hash( array( $entry, $image, $gallery, $video_url, $categories, $brand, $in_collections[ $sku ] ?? array() ) );
		$id          = $product_id( $sku );
		if ( $id && get_post_meta( $id, MIZZEY_SAMPLE_MARK . '_hash', true ) === $fingerprint ) {
			++$made['unchanged'];
			continue;
		}
		if ( $id && get_post_meta( $id, MIZZEY_SAMPLE_MARK, true ) !== $sku ) {
			WP_CLI::error( "{$sku} is held by product {$id}, which this step did not make" );
		}
		$options = array();
		foreach ( $variations as $v ) {
			foreach ( (array) $v['attributes'] as $key => $name ) {
				$options[ $key ][] = (string) $name;
			}
		}
		$p = $id ? wc_get_product( $id ) : ( $variations ? new WC_Product_Variable() : new WC_Product_Simple() );
		if ( ! $p instanceof WC_Product || $p->is_type( 'variable' ) !== (bool) $variations ) {
			WP_CLI::error( "{$sku}: the stored product is not the manifest's type" );
		}
		$p->set_name( (string) $entry['name'] );
		$p->set_sku( $sku );
		$p->set_status( 'publish' );
		$p->set_short_description( $features( (array) ( $entry['features'] ?? array() ) ) );
		$p->set_description( wpautop( esc_html( (string) ( $entry['description'] ?? '' ) ) ) );
		$p->set_image_id( $image );
		$p->set_gallery_image_ids( $gallery );
		$p->update_meta_data( ProductFields::VIDEO, $video_url );
		$p->set_category_ids( $categories );
		$p->set_attributes( $product_attributes( array_merge( (array) ( $entry['attributes'] ?? array() ), $options ), array_keys( $options ) ) );
		$p->update_meta_data( ProductFields::BADGES, array_values( array_intersect( ProductFields::BADGE_ORDER, (array) ( $entry['badges'] ?? array() ) ) ) );
		$p->update_meta_data( MIZZEY_SAMPLE_MARK, $sku );
		if ( ! $variations ) {
			$p->set_regular_price( (string) $entry['price'] );
			$p->set_sale_price( (string) ( $entry['sale_price'] ?? '' ) );
			$p->set_manage_stock( true );
			$p->set_stock_quantity( (int) ( $entry['stock'] ?? 0 ) );
			$p->set_low_stock_amount( 3 );
			$p->set_backorders( 'no' );
		}
		$p->save();
		$new = ! $id;
		$id  = $p->get_id();
		if ( $new ) {
			$sitepress->set_element_language_details( $id, 'post_product', null, 'en' );
		}
		wp_set_object_terms( $id, $brand ? array( $brand ) : array(), 'product_brand' );
		wp_set_object_terms( $id, $in_collections[ $sku ] ?? array(), 'product_collection' );
		foreach ( $variations as $v ) {
			$vid = (int) wc_get_product_id_by_sku( (string) $v['sku'] );
			$vid = $vid ? (int) apply_filters( 'wpml_object_id', $vid, 'product_variation', true, 'en' ) : 0;
			$var = $vid ? wc_get_product( $vid ) : new WC_Product_Variation();
			if ( ! $var instanceof WC_Product_Variation || ( $vid && $var->get_parent_id() !== $id ) ) {
				WP_CLI::error( "{$v['sku']} is held by a record that is not a variation of {$sku}" );
			}
			$chosen = array();
			foreach ( (array) $v['attributes'] as $key => $name ) {
				$key = wc_sanitize_taxonomy_name( (string) $key );
				$tax = $attributes[ $key ]['taxonomy'];
				$chosen[ $tax ] = (string) get_term( $value( $key, (string) $name ), $tax )->slug;
			}
			$var->set_parent_id( $id );
			$var->set_sku( (string) $v['sku'] );
			$var->set_attributes( $chosen );
			$var->set_regular_price( (string) $v['price'] );
			$var->set_sale_price( (string) ( $v['sale_price'] ?? '' ) );
			$var->set_manage_stock( true );
			$var->set_stock_quantity( (int) ( $v['stock'] ?? 0 ) );
			$var->set_low_stock_amount( 3 );
			$var->set_backorders( 'no' );
			$var->set_status( 'publish' );
			$var->save();
			if ( ! $vid ) {
				$sitepress->set_element_language_details( $var->get_id(), 'post_product_variation', null, 'en' );
				++$made['variations'];
			}
		}
		// A variation this step made that the manifest no longer lists is removed, with its Arabic record.
		$listed = array_map( static fn ( array $v ): string => (string) $v['sku'], $variations );
		foreach ( $p->get_children() as $child ) {
			$old = wc_get_product( $child );
			if ( $old instanceof WC_Product_Variation && 0 === strpos( $old->get_sku(), MIZZEY_SAMPLE_PREFIX ) && ! in_array( $old->get_sku(), $listed, true ) ) {
				$copy = (int) apply_filters( 'wpml_object_id', $child, 'product_variation', false, 'ar' );
				if ( $copy && $copy !== $child ) {
					wp_delete_post( $copy, true );
				}
				$old->delete( true );
			}
		}
		if ( $variations ) {
			WC_Product_Variable::sync( $id );
		}
		update_post_meta( $id, MIZZEY_SAMPLE_MARK . '_hash', $fingerprint );
		++$made[ $new ? 'created' : 'updated' ];
	}

	// ---- The Arabic records, in a process of their own (A11) -------------------------------------------------------
	// When this process wrote no product, nothing was created here and the translations may follow in it; otherwise
	// they wait for the second pass, `eval-file 70-sample-catalog.php translate`, which the staging tool runs next.
	$written = $made['created'] + $made['updated'];
	$line    = sprintf( 'sample catalogue: %d products made, %d updated, %d unchanged, %d variations made; %d other categories taken out of the menus, %d shown again', $made['created'], $made['updated'], $made['unchanged'], $made['variations'], $hidden['hidden'], $hidden['shown'] );
	if ( 0 === $written ) {
		$phase = 'translate';
	} else {
		WP_CLI::success( "{$line}; the Arabic records follow in a process of their own: eval-file this step again with the argument translate" );
		return;
	}
}

// ---- Phase two: the Arabic records ----------------------------------------------------------------------------------
$sitepress->switch_lang( 'en', true );
$arabic = array( 'translated' => 0, 'unchanged' => 0 );
$ids    = array( 'en' => array(), 'ar' => array() );
$list   = static fn ( array $items ): string => $items ? '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', array_map( 'strval', $items ) ) ) . '</li></ul>' : '';
foreach ( $catalog['products'] as $entry ) {
	$sku = (string) $entry['sku'];
	$en  = $product_id( $sku );
	if ( ! $en || get_post_meta( $en, MIZZEY_SAMPLE_MARK, true ) !== $sku ) {
		WP_CLI::error( "{$sku} has no English record made by this step: run it without arguments first" );
	}
	$source = get_post( $en );
	$fields = array(
		'title'           => '' !== (string) ( $entry['name_ar'] ?? '' ) ? (string) $entry['name_ar'] : (string) $source->post_title,
		'slug'            => '',
		'product_content' => '' !== (string) ( $entry['description_ar'] ?? '' ) ? wpautop( esc_html( (string) $entry['description_ar'] ) ) : (string) $source->post_content,
		'product_excerpt' => ( $entry['features_ar'] ?? array() ) ? $list( (array) $entry['features_ar'] ) : (string) $source->post_excerpt,
	);
	$fingerprint = $hash( array( $fields, get_post_meta( $en, MIZZEY_SAMPLE_MARK . '_hash', true ) ) );
	$ar          = (int) apply_filters( 'wpml_object_id', $en, 'product', false, 'ar' );
	if ( $ar && $ar !== $en && get_post_meta( $en, MIZZEY_SAMPLE_MARK . '_hash_ar', true ) === $fingerprint ) {
		++$arabic['unchanged'];
	} else {
		if ( $ar && $ar !== $en ) {
			$fields['slug'] = (string) get_post_field( 'post_name', $ar );
		}
		$ar = ProductTranslationJob::save( $en, 'ar', $fields );
		if ( $ar <= 0 || $ar === $en ) {
			WP_CLI::error( "{$sku}: no Arabic record was made" );
		}
		update_post_meta( $en, MIZZEY_SAMPLE_MARK . '_hash_ar', $fingerprint );
		++$arabic['translated'];
	}
	$ids['en'][ $sku ] = $en;
	$ids['ar'][ $sku ] = $ar;
}

// The attributes' names in Arabic, as WooCommerce Multilingual translates them (WPML String Translation), where no
// Arabic name is held yet: a name staff have translated is kept.
global $wpdb;
if ( function_exists( 'icl_get_string_id' ) && function_exists( 'icl_add_string_translation' ) ) {
	foreach ( (array) ( $catalog['attributes'] ?? array() ) as $row ) {
		$label = wc_attribute_label( wc_attribute_taxonomy_name( wc_sanitize_taxonomy_name( (string) $row['key'] ) ) );
		if ( '' === (string) ( $row['name_ar'] ?? '' ) || '' === $label ) {
			continue;
		}
		$name = sprintf( '%s %s %s: %s', 'taxonomy', 'singular', 'name', $label );
		do_action( 'wpml_register_single_string', 'WordPress', $name, $label );
		$string = (int) icl_get_string_id( $label, 'WordPress', $name );
		$held   = $string > 0 ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}icl_string_translations WHERE string_id = %d AND language = 'ar' AND value <> ''", $string ) ) : 1; // phpcs:ignore WordPress.DB
		if ( 0 === $held ) {
			icl_add_string_translation( $string, 'ar', (string) $row['name_ar'], defined( 'ICL_TM_COMPLETE' ) ? ICL_TM_COMPLETE : 10 );
		}
	}
}

// ---- The home page's product sections, from step 60, set to the manifest's lists ------------------------------------
// The trending and new rails are found by the badge step 60 gave them and the deals panel by its block; each becomes
// a hand-picked list (specs/056), kept on its badge, so a later run finds it again.
$picks = array_map( static fn ( $skus ): array => array_values( array_map( 'strval', (array) $skus ) ), (array) ( $catalog['home'] ?? array() ) );
$pages = 0;
foreach ( array( 'en', 'ar' ) as $lang ) {
	$page = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_mizzey_seed_home', 'meta_value' => $lang, 'suppress_filters' => true ) );
	if ( ! $page ) {
		continue;
	}
	$blocks  = parse_blocks( (string) get_post_field( 'post_content', (int) $page[0] ) );
	$changed = false;
	foreach ( $blocks as &$block ) {
		$rail = null;
		if ( 'mizzey/deals' === $block['blockName'] ) {
			$rail = 'deals';
		} elseif ( 'mizzey/products' === $block['blockName'] ) {
			$rail = (string) ( $block['attrs']['badge'] ?? 'trending' );
		}
		if ( null === $rail || ! isset( $picks[ $rail ] ) ) {
			continue;
		}
		$chosen = array_values( array_filter( array_map( static fn ( string $sku ): int => (int) ( $ids[ $lang ][ $sku ] ?? 0 ), $picks[ $rail ] ) ) );
		if ( 'products' !== ( $block['attrs']['source'] ?? '' ) || array_map( 'intval', (array) ( $block['attrs']['products'] ?? array() ) ) !== $chosen ) {
			$block['attrs']['source']   = 'products';
			$block['attrs']['products'] = $chosen;
			$changed                    = true;
		}
	}
	unset( $block );
	if ( $changed ) {
		// wp_update_post() unslashes what it is given; the blocks' JSON escapes "<" as <, so it is slashed first.
		wp_update_post( array( 'ID' => (int) $page[0], 'post_content' => wp_slash( serialize_blocks( $blocks ) ) ) );
		++$pages;
	}
}

WP_CLI::success( ( isset( $line ) ? $line . '; ' : '' ) . sprintf( 'Arabic records: %d written, %d unchanged; home sections changed on %d pages', $arabic['translated'], $arabic['unchanged'], $pages ) );

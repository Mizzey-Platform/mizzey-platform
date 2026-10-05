<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue;

defined('ABSPATH') || exit;

/**
 * Registers the product collection taxonomy, which gives IA-04 and IA-35 their archive route.
 *
 * Why a taxonomy, and why this is not a decision left open. The register settles the model: ADM-57 makes
 * "manually curated collections" P1 and S1, ADM-58 defers "rules-based automatic collections" to P2, and Part One
 * states that manually curated collections are included. Around them, ADM-44 contracts collection assignment,
 * MER-03 manual product ordering within collections, SSC-06 per-collection page content and SSC-24 per-product
 * placement. A manually curated grouping of products, with its own page content and manual ordering, is a
 * taxonomy, the same shape as product_cat.
 *
 * What this class owns, and what it does not. It owns the taxonomy and therefore the archive route in both
 * languages, which is IA-04 "Collections" and IA-35 "Curated collection pages". It does not own the admin CRUD
 * (ADM-57), the assignment UI (ADM-44), the ordering (MER-03) or the per-collection content (SSC-06, SSC-24):
 * those are contracted rows belonging to other slices, and taking them here would breach M-1.
 *
 * Register ids: IA-04, IA-35. See specs/003-information-architecture-urls.
 */
final class Collections
{
    public const TAXONOMY = 'product_collection';

    /**
     * The URL base. Kept distinct from WooCommerce's own `product-category` and `product-tag` bases so a
     * collection archive can never collide with a category archive, which guardrail G-1 forbids.
     */
    public const SLUG = 'collection';

    public static function register(): void
    {
        add_action('init', [self::class, 'registerTaxonomy'], 9);
    }

    /**
     * Registered on `init` at priority 9, before WooCommerce's own taxonomies at 10, so the rewrite rules are in
     * place before anything reads them.
     */
    public static function registerTaxonomy(): void
    {
        register_taxonomy(
            self::TAXONOMY,
            ['product'],
            [
                // Translator comments are unnecessary here: these are admin-facing labels, and the admin is
                // English only by ADM-159, which this feature does not change.
                'labels'            => [
                    'name'          => __('Collections', 'mizzey-site'),
                    'singular_name' => __('Collection', 'mizzey-site'),
                    'menu_name'     => __('Collections', 'mizzey-site'),
                ],
                'public'            => true,
                'publicly_queryable' => true,
                'hierarchical'      => false,
                'show_ui'           => true,
                'show_in_menu'      => true,
                'show_in_nav_menus' => true,
                'show_admin_column' => true,
                'show_in_rest'      => true,
                'query_var'         => true,
                'rewrite'           => [
                    'slug'       => self::SLUG,
                    'with_front' => false,
                ],
            ]
        );
    }
}

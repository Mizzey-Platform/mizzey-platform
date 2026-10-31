<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Stock;

defined('ABSPATH') || exit;

/**
 * No local writer changes a product's stock (D-19 item 5: "Only the ERP is authoritative for stock. No independent
 * local stock writer may alter the effective available stock. Any permitted ERP-derived projection write must be
 * controlled through the designated adapter"), refused on the server, not hidden.
 *
 * - Every product and variation save keeps its stored stock: a change to the stock quantity, the stock status, stock
 *   management or backorders is put back (`woocommerce_before_product_object_save`, and the variation's), whoever makes
 *   it: the product and variation editors, quick and bulk edit, variation bulk actions, the CSV importer, the REST API,
 *   WP-CLI, the carrier's webhook. WooCommerce's direct stock update (`wc_update_product_stock()`) writes nothing
 *   (`woocommerce_update_product_stock_query`). The REST API refuses a stock change with a 403.
 * - The one way through is `StockWriteGuard::projection()`, for the adapter's ERP-derived projection once PRE-09 says
 *   how WooCommerce's fields carry the ERP figure. Test fixtures and staging seeds, which stand in for the ERP, hold a
 *   permit through the `mizzey_stock_write_permitted` filter; the site's own code never sets it.
 * - The product editor and the products list show the figure from the stock source (the adapter), with the fields
 *   locked; a store that has no stock source shows it as not known.
 *
 * Not covered, and recorded: a direct database write outside WooCommerce (no PHP hook can refuse it).
 *
 * Register ids: ERP-07, ADM-70, ADM-72 (the function layer; the mechanism waits for PRE-09). See specs/026-admin-stock.
 */
final class StockWriteGuard
{
    public const PERMIT = 'mizzey_stock_write_permitted';
    private const PROPS = ['stock_quantity', 'stock_status', 'manage_stock', 'backorders'];
    private const REST  = ['stock_quantity', 'stock_status', 'manage_stock', 'backorders', 'in_stock'];

    private static int $projecting = 0;

    public static function register(): void
    {
        add_action('woocommerce_before_product_object_save', [self::class, 'keep'], 1, 1);
        add_action('woocommerce_before_product_variation_object_save', [self::class, 'keep'], 1, 1);
        add_filter('woocommerce_update_product_stock_query', [self::class, 'query'], 999, 1);
        add_filter('woocommerce_rest_pre_insert_product_object', [self::class, 'rest'], 1, 2);
        add_filter('woocommerce_rest_pre_insert_product_variation_object', [self::class, 'rest'], 1, 2);
        add_filter('woocommerce_admin_stock_html', [self::class, 'listColumn'], 20, 2);
        add_action('woocommerce_product_options_stock_status', [self::class, 'editor']);
        add_action('woocommerce_variation_options_inventory', [self::class, 'variationEditor'], 10, 3);
        add_action('admin_head', [self::class, 'lockFields']);
    }

    /**
     * The adapter's way to write an ERP-derived projection into WooCommerce's stock fields.
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public static function projection(callable $work)
    {
        self::$projecting++;
        try {
            return $work();
        } finally {
            self::$projecting--;
        }
    }

    public static function permitted(): bool
    {
        return self::$projecting > 0 || apply_filters(self::PERMIT, false) === true;
    }

    /** @param mixed $product */
    public static function keep($product): void
    {
        if (! $product instanceof \WC_Product || self::permitted()) {
            return;
        }
        $changes = $product->get_changes();
        $stored  = $product->get_data();
        $back    = [];
        foreach (self::PROPS as $prop) {
            if (array_key_exists($prop, $changes)) {
                $back[$prop] = $product->get_id() > 0 ? ($stored[$prop] ?? null) : self::fresh($prop);
            }
        }
        if ($back !== []) {
            $product->set_props($back);
        }
    }

    /** A new product's stock as WooCommerce starts it, with nothing written locally. */
    private static function fresh(string $prop): mixed
    {
        return ['stock_quantity' => null, 'stock_status' => 'instock', 'manage_stock' => false, 'backorders' => 'no'][$prop];
    }

    /**
     * @param mixed $sql
     * @return mixed
     */
    public static function query($sql)
    {
        global $wpdb;

        // A query that writes nothing in place of the stock update.
        return self::permitted() ? $sql : $wpdb->prepare('SELECT %d', 1);
    }

    /**
     * @param mixed $product
     * @param mixed $request
     * @return mixed
     */
    public static function rest($product, $request)
    {
        if (! $request instanceof \WP_REST_Request || self::permitted()) {
            return $product;
        }
        foreach (self::REST as $field) {
            if ($request->get_param($field) !== null) {
                return new \WP_Error('mizzey_stock_read_only', __('Stock comes from the stock source and cannot be changed here.', 'mizzey-site'), ['status' => 403]);
            }
        }

        return $product;
    }

    /** The quantity the stock source reports, or null when it is not known. */
    public static function figure(int $productId): ?int
    {
        $answer = StockGateway::make()->level($productId);

        return $answer->isKnown() ? (int) $answer->quantity : null;
    }

    private static function label(int $productId): string
    {
        $figure = self::figure($productId);

        return $figure === null
            ? __('Stock from the stock source: not known', 'mizzey-site')
            /* translators: %d: the quantity the stock source reports. */
            : sprintf(__('Stock from the stock source: %d', 'mizzey-site'), $figure);
    }

    /**
     * @param mixed $html
     * @param mixed $product
     * @return mixed
     */
    public static function listColumn($html, $product)
    {
        if (! $product instanceof \WC_Product) {
            return $html;
        }

        return sprintf('<mark class="mizzey-stock-source">%s</mark>', esc_html(self::label((int) $product->get_id())));
    }

    public static function editor(): void
    {
        global $product_object;
        if ($product_object instanceof \WC_Product && $product_object->get_id() > 0) {
            printf('<p class="form-field mizzey-stock-source"><strong>%s</strong><br>%s</p>', esc_html(self::label((int) $product_object->get_id())), esc_html__('Stock comes from the stock source and cannot be changed here.', 'mizzey-site'));
        }
    }

    /**
     * @param mixed $loop
     * @param mixed $data
     * @param mixed $variation
     */
    public static function variationEditor($loop, $data, $variation): void
    {
        if ($variation instanceof \WP_Post) {
            printf('<p class="form-row form-row-full mizzey-stock-source"><strong>%s</strong></p>', esc_html(self::label((int) $variation->ID)));
        }
    }

    /** On the product screens, the stock fields shown locked; the server refuses a change whatever is sent. */
    public static function lockFields(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen instanceof \WP_Screen || ! in_array($screen->id, ['product', 'edit-product'], true) || self::permitted()) {
            return;
        }
        echo '<style>#_stock,#_stock_status,#_manage_stock,#_backorders,.stock_qty_field,.stock_status_field,.manage_stock_field,.backorder_field,.variable_manage_stock,.show_if_variation_manage_stock,.variable_stock_status{pointer-events:none;opacity:.55}</style>';
    }
}

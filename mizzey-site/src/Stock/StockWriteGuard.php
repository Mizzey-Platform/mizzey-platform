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
 * - At the storage boundary, for the stock keys of products and variations (`_stock`, `_stock_status`,
 *   `_manage_stock`, `_backorders`): WordPress's metadata API refuses an addition, an update or a deletion, by key or by
 *   meta id (its short-circuit filters), and `$wpdb` refuses a raw write to those keys in the post meta table (the
 *   `query` filter), which is where WooCommerce's direct stock update and WooCommerce Multilingual's stock
 *   synchronisation write. Every writer goes through one of the two: the product and variation editors, quick and bulk
 *   edit, variation bulk actions, the CSV importer, the REST API, WP-CLI, the carrier's webhook. A product being made
 *   stores WooCommerce's defaults only; a product being deleted takes its stock meta with it. After a refused direct
 *   update the product object is read again, so it does not carry the refused figure.
 * - The REST API refuses a request that sends a stock field (any version, batches and nested variations included,
 *   `inventory_delta` too) with a 403; schema defaults are not counted as sent.
 * - The one way through is `StockWriteGuard::projection()`, for the adapter's ERP-derived projection once PRE-09 says
 *   how WooCommerce's fields carry the ERP figure; it finishes WooCommerce's deferred parent synchronisation inside
 *   itself, so a variable product's status follows its variations. Test fixtures and staging seeds, which stand in
 *   for the ERP, hold a permit through the `mizzey_stock_write_permitted` filter; the site's own code never sets it.
 * - The product editor and the products list show the figure from the stock source (the adapter, read once, kept five
 *   minutes), with the stored figure hidden and the stock controls disabled.
 *
 * Not covered, and recorded: a database write outside `$wpdb` (no PHP hook can refuse it).
 *
 * Register ids: ERP-07, ADM-70, ADM-72 (the function layer; the mechanism waits for PRE-09). See specs/026-admin-stock.
 */
final class StockWriteGuard
{
    public const PERMIT    = 'mizzey_stock_write_permitted';
    public const KEYS      = ['_stock', '_stock_status', '_manage_stock', '_backorders'];
    public const FIGURE    = 'mizzey_stock_figure_';
    private const DEFAULTS = ['_stock' => '', '_stock_status' => 'instock', '_manage_stock' => 'no', '_backorders' => 'no'];
    private const REST     = ['stock_quantity', 'stock_status', 'manage_stock', 'backorders', 'in_stock', 'inventory_delta'];

    private static int $projecting = 0;

    /** @var array<int,true> Products being deleted, whose stock meta goes with them. */
    private static array $deleting = [];

    public static function register(): void
    {
        add_filter('add_post_metadata', [self::class, 'metaAdd'], 1, 5);
        add_filter('update_post_metadata', [self::class, 'metaUpdate'], 1, 5);
        add_filter('delete_post_metadata', [self::class, 'metaDelete'], 1, 5);
        add_filter('update_post_metadata_by_mid', [self::class, 'metaByMid'], 1, 2);
        add_filter('delete_post_metadata_by_mid', [self::class, 'metaByMid'], 1, 2);
        add_filter('query', [self::class, 'query'], 1);
        add_action('before_delete_post', [self::class, 'deleting'], 1, 1);
        add_action('deleted_post', [self::class, 'deleted'], 99, 1);
        add_action('woocommerce_product_set_stock', [self::class, 'reread'], 0, 1);
        add_action('woocommerce_variation_set_stock', [self::class, 'reread'], 0, 1);
        add_filter('rest_pre_dispatch', [self::class, 'rest'], 1, 3);
        add_filter('woocommerce_admin_stock_html', [self::class, 'listColumn'], 20, 2);
        add_action('woocommerce_product_options_stock_status', [self::class, 'editor']);
        add_action('woocommerce_variation_options_inventory', [self::class, 'variationEditor'], 10, 3);
        add_action('admin_enqueue_scripts', [self::class, 'lockFields']);
    }

    /**
     * The adapter's way to write an ERP-derived projection into WooCommerce's stock fields; WooCommerce's deferred
     * parent synchronisation is finished inside it.
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public static function projection(callable $work)
    {
        self::$projecting++;
        try {
            $result = $work();
            if (class_exists(\WC_Post_Data::class)) {
                \WC_Post_Data::do_deferred_product_sync();
            }

            return $result;
        } finally {
            self::$projecting--;
        }
    }

    public static function permitted(): bool
    {
        return self::$projecting > 0 || apply_filters(self::PERMIT, false) === true;
    }

    /** Whether a write of this key, to this object, is refused. */
    private static function refused(int $objectId, string $key): bool
    {
        return in_array($key, self::KEYS, true) && ! self::permitted() && ! isset(self::$deleting[$objectId])
            && in_array(get_post_type($objectId), ['product', 'product_variation'], true);
    }

    /**
     * A product being made stores WooCommerce's default stock only.
     *
     * @param mixed $check
     * @param mixed $objectId
     * @param mixed $key
     * @param mixed $value
     * @param mixed $unique
     * @return mixed
     */
    public static function metaAdd($check, $objectId, $key, $value = null, $unique = false)
    {
        if ($check !== null || ! self::refused((int) $objectId, (string) $key)) {
            return $check;
        }

        return (string) maybe_serialize($value) === self::DEFAULTS[(string) $key] ? null : false;
    }

    /**
     * @param mixed $check
     * @param mixed $objectId
     * @param mixed $key
     * @param mixed $value
     * @param mixed $previous
     * @return mixed
     */
    public static function metaUpdate($check, $objectId, $key, $value = null, $previous = '')
    {
        if ($check !== null || ! self::refused((int) $objectId, (string) $key)) {
            return $check;
        }
        // A product's first stock meta is an addition; WordPress makes it through update when none exists.
        if (! metadata_exists('post', (int) $objectId, (string) $key)) {
            return self::metaAdd(null, $objectId, $key, $value);
        }

        return false;
    }

    /**
     * @param mixed $check
     * @param mixed $objectId
     * @param mixed $key
     * @param mixed $value
     * @param mixed $all
     * @return mixed
     */
    public static function metaDelete($check, $objectId, $key, $value = '', $all = false)
    {
        return $check === null && self::refused((int) $objectId, (string) $key) ? false : $check;
    }

    /**
     * @param mixed $check
     * @param mixed $metaId
     * @return mixed
     */
    public static function metaByMid($check, $metaId)
    {
        $meta = $check === null ? get_metadata_by_mid('post', (int) $metaId) : false;

        return is_object($meta) && self::refused((int) $meta->post_id, (string) $meta->meta_key) ? false : $check;
    }

    /**
     * A raw write to a stock key in the post meta table, as WooCommerce's direct stock update and WooCommerce
     * Multilingual's synchronisation make, is replaced by a query that writes nothing.
     *
     * @param mixed $sql
     * @return mixed
     */
    public static function query($sql)
    {
        global $wpdb;
        if (! is_string($sql) || self::permitted() || preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE)\b/i', $sql) !== 1
            || stripos($sql, $wpdb->postmeta) === false || preg_match("/['\"`](_stock|_stock_status|_manage_stock|_backorders)['\"`]/", $sql) !== 1) {
            return $sql;
        }
        // A delete of a product being deleted goes through.
        foreach (array_keys(self::$deleting) as $id) {
            if (preg_match('/\bpost_id`?\s*=\s*' . $id . '\b/', $sql) === 1) {
                return $sql;
            }
        }

        return $wpdb->prepare('SELECT %d', 0);
    }

    /** @param mixed $postId */
    public static function deleting($postId): void
    {
        if (in_array(get_post_type((int) $postId), ['product', 'product_variation'], true)) {
            self::$deleting[(int) $postId] = true;
        }
    }

    /** @param mixed $postId */
    public static function deleted($postId): void
    {
        unset(self::$deleting[(int) $postId]);
    }

    /**
     * After a refused direct stock update, the product object is read again, so it does not carry the refused figure.
     *
     * @param mixed $product
     */
    public static function reread($product): void
    {
        if (! $product instanceof \WC_Product || self::permitted() || $product->get_id() <= 0) {
            return;
        }
        wp_cache_delete($product->get_id(), 'post_meta');
        $product->set_stock_quantity((string) get_post_meta($product->get_id(), '_stock', true));
        $product->set_stock_status((string) get_post_meta($product->get_id(), '_stock_status', true));
        $product->apply_changes();
    }

    /**
     * @param mixed $result
     * @param mixed $server
     * @param mixed $request
     * @return mixed
     */
    public static function rest($result, $server, $request)
    {
        if ($result !== null || ! $request instanceof \WP_REST_Request || self::permitted() || $request->get_method() === 'GET'
            || preg_match('#^/wc/v\d+/products(/|$)#', $request->get_route()) !== 1) {
            return $result;
        }
        // What the request sent, not the schema's defaults.
        $sent  = array_merge((array) $request->get_query_params(), (array) $request->get_body_params(), (array) $request->get_json_params());
        $items = [$sent];
        foreach (['create', 'update'] as $batch) {
            foreach ((array) ($sent[$batch] ?? []) as $item) {
                $items[] = (array) $item;
            }
        }
        foreach ($items as $item) {
            if (array_intersect(self::REST, array_keys($item)) !== []) {
                return new \WP_Error('mizzey_stock_read_only', __('Stock comes from the stock source and cannot be changed here.', 'mizzey-site'), ['status' => 403]);
            }
        }

        return $result;
    }

    /** The quantity the stock source reports, read once and kept five minutes, or null when it is not known. */
    public static function figure(int $productId): ?int
    {
        $kept = get_transient(self::FIGURE . $productId);
        if (is_array($kept)) {
            return $kept['q'];
        }
        $answer = StockGateway::quick()->level($productId);
        $figure = $answer->isKnown() ? (int) $answer->quantity : null;
        set_transient(self::FIGURE . $productId, ['q' => $figure], 5 * MINUTE_IN_SECONDS);

        return $figure;
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

    /**
     * On the product screens, the stored figure is hidden and the stock controls disabled (a disabled field is not
     * sent); the server refuses a change whatever is sent.
     *
     * @param mixed $hook
     */
    public static function lockFields($hook): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen instanceof \WP_Screen || ! in_array($screen->id, ['product', 'edit-product'], true) || self::permitted()) {
            return;
        }
        $file = WP_PLUGIN_DIR . '/mizzey-site/assets/admin-stock.js';
        wp_enqueue_script('mizzey-admin-stock', plugins_url('mizzey-site/assets/admin-stock.js'), ['jquery'], is_readable($file) ? (string) filemtime($file) : null, true);
    }
}

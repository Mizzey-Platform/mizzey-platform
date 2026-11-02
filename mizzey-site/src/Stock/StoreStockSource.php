<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Stock;

defined('ABSPATH') || exit;

/**
 * The store's own stock (D-28): the figure is WooCommerce's own stock quantity on the stock identity record, kept in
 * step across the languages by WooCommerce Multilingual; what WooCommerce lacks is added here. Every change is applied
 * once per operation key and recorded with its reason, its origin and its user in a ledger table, under a lock per
 * item, inside one database transaction with the figure, so a change is either applied and recorded or neither.
 *
 * The figure is written only through `StockWriteGuard::projection()`: no other local writer may change stock, on
 * either source (D-19 item 5, specs/026).
 *
 * An item whose stock WooCommerce does not count (stock management off) is unlimited while it is in stock, as in
 * WooCommerce, and changes to it are recorded without a figure.
 *
 * See specs/049-stock-source-switch, FR-002 and FR-003.
 */
final class StoreStockSource implements StockSource
{
    public const TABLE   = 'mizzey_stock_moves';
    public const VERSION = '1';
    public const VERSION_OPTION = 'mizzey_stock_moves_schema';

    /** What an item whose stock is not counted holds while in stock. */
    public const UNLIMITED = 1000000;

    /** Where a change came from. */
    public const ORDER  = 'order';
    public const STAFF  = 'staff';
    public const FEED   = 'feed';

    public const KEY_MAX    = 191;
    public const REASON_MAX = 191;

    public static function register(): void
    {
        add_action('init', [self::class, 'install'], 1);
    }

    public static function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . self::TABLE;
    }

    /** Creates or updates the ledger table when its version changes. */
    public static function install(): void
    {
        if (get_option(self::VERSION_OPTION) === self::VERSION) {
            return;
        }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table   = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  item_id bigint(20) unsigned NOT NULL,
  operation_key varchar(191) NOT NULL,
  delta int(11) NOT NULL,
  level_after int(11) NOT NULL,
  origin varchar(20) NOT NULL,
  reason varchar(191) NOT NULL DEFAULT '',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY operation_key (operation_key),
  KEY item_created (item_id,created_at)
) {$charset};");
        if (self::table() === $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)))) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            update_option(self::VERSION_OPTION, self::VERSION, true);
        }
    }

    public function name(): string
    {
        return 'store';
    }

    public function level(StockItem $item): int
    {
        return self::figureOf(self::product($item));
    }

    /** A sale or its return: the StockSource contract, recorded as an order change. */
    public function adjust(StockItem $item, int $delta, string $operationKey): int
    {
        return $this->change($item, $delta, null, $operationKey, self::ORDER, '', 0);
    }

    /**
     * Sets the figure to `$quantity` (a count, from staff or the feed), once for `$operationKey`.
     *
     * @throws StockSourceRefused|StockSourceUnavailable
     */
    public function set(StockItem $item, int $quantity, string $operationKey, string $origin, string $reason, int $userId): int
    {
        if ($quantity < 0) {
            throw new StockSourceRefused('a stock figure cannot be below zero');
        }

        return $this->change($item, null, $quantity, $operationKey, $origin, $reason, $userId);
    }

    /**
     * Changes the figure by `$delta` (from staff or the feed), once for `$operationKey`.
     *
     * @throws StockSourceRefused|StockSourceUnavailable
     */
    public function move(StockItem $item, int $delta, string $operationKey, string $origin, string $reason, int $userId): int
    {
        return $this->change($item, $delta, null, $operationKey, $origin, $reason, $userId);
    }

    /** Whether a change under this key was already applied. */
    public static function applied(string $operationKey): bool
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::table() . ' WHERE operation_key = %s', $operationKey)) > 0; // phpcs:ignore WordPress.DB
    }

    /**
     * The item's recorded changes, newest first.
     *
     * @return list<object{delta:string,level_after:string,origin:string,reason:string,user_id:string,created_at:string}>
     */
    public static function history(int $itemId, int $limit = 20): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT delta, level_after, origin, reason, user_id, created_at FROM ' . self::table() . ' WHERE item_id = %d ORDER BY id DESC LIMIT %d', $itemId, max(1, min(200, $limit)))); // phpcs:ignore WordPress.DB

        return is_array($rows) ? array_values($rows) : [];
    }

    /**
     * @throws StockSourceRefused|StockSourceUnavailable
     */
    private function change(StockItem $item, ?int $delta, ?int $target, string $operationKey, string $origin, string $reason, int $userId): int
    {
        global $wpdb;
        $operationKey = trim($operationKey);
        if ($operationKey === '' || strlen($operationKey) > self::KEY_MAX) {
            throw new StockSourceRefused('the operation key is missing or too long');
        }
        if (get_option(self::VERSION_OPTION) !== self::VERSION) {
            throw new StockSourceUnavailable('the stock ledger is not installed');
        }
        $lock = substr('mz_' . $wpdb->prefix . 'stock_' . $item->id, 0, 64);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $lock)) !== 1) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            throw new StockSourceUnavailable('stock busy');
        }
        try {
            $product = self::product($item, true);
            if (self::applied($operationKey)) {
                return self::figureOf($product);
            }
            $counted = $product->managing_stock();
            $current = self::figureOf($product);
            $change  = $target !== null ? $target - ($counted ? $current : 0) : (int) $delta;
            $after   = $counted || $target !== null ? ($target ?? $current + $change) : $current;
            if ($after < 0 && $change < 0) {
                throw new StockSourceRefused('not enough stock');
            }
            $wpdb->query('START TRANSACTION'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            try {
                $stored = $wpdb->insert(self::table(), [
                    'item_id'       => $item->id,
                    'operation_key' => $operationKey,
                    'delta'         => $change,
                    'level_after'   => min($after, self::UNLIMITED),
                    'origin'        => substr($origin, 0, 20),
                    'reason'        => mb_substr(sanitize_text_field($reason), 0, self::REASON_MAX),
                    'user_id'       => max(0, $userId),
                    'created_at'    => current_time('mysql', true),
                ], ['%d', '%s', '%d', '%d', '%s', '%s', '%d', '%s']); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                if ($stored !== 1) {
                    throw new StockSourceUnavailable('the change was not recorded');
                }
                if ($counted || $target !== null) {
                    StockWriteGuard::projection(static function () use ($product, $after): void {
                        if (! $product->managing_stock()) {
                            $product->set_manage_stock(true);
                            $product->save();
                        }
                        wc_update_product_stock($product, $after, 'set');
                    });
                }
                $wpdb->query('COMMIT'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            } catch (\Throwable $e) {
                $wpdb->query('ROLLBACK'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                throw $e instanceof StockSourceRefused || $e instanceof StockSourceUnavailable ? $e : new StockSourceUnavailable($e->getMessage());
            }
            self::forget($item->id);

            return self::figureOf(self::product($item, true));
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }
    }

    /** The figure WooCommerce holds for the item: its count, or unlimited or nothing when it is not counted. */
    private static function figureOf(\WC_Product $product): int
    {
        if ($product->managing_stock()) {
            return (int) $product->get_stock_quantity();
        }

        return $product->is_in_stock() ? self::UNLIMITED : 0;
    }

    /** @throws StockSourceRefused */
    private static function product(StockItem $item, bool $fresh = false): \WC_Product
    {
        if ($fresh) {
            wp_cache_delete($item->id, 'post_meta');
            wp_cache_delete($item->id, 'posts');
        }
        $product = wc_get_product($item->id);
        if (! $product instanceof \WC_Product) {
            throw new StockSourceRefused('unknown item');
        }

        return $product;
    }

    /** Drops the admin's kept figure for the item in every language (StockWriteGuard::figure). */
    private static function forget(int $itemId): void
    {
        $type = get_post_type($itemId) === 'product_variation' ? 'product_variation' : 'product';
        $ids  = [$itemId];
        $trid = apply_filters('wpml_element_trid', null, $itemId, 'post_' . $type);
        if ($trid) {
            foreach ((array) apply_filters('wpml_get_element_translations', [], $trid, 'post_' . $type) as $translation) {
                $ids[] = (int) ($translation->element_id ?? 0);
            }
        }
        foreach (array_unique(array_filter($ids)) as $id) {
            delete_transient(StockWriteGuard::FIGURE . $id);
        }
    }
}

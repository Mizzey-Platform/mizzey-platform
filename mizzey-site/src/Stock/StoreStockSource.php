<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Stock;

defined('ABSPATH') || exit;

/**
 * The store's own stock (D-28): the figure is WooCommerce's own stock quantity on the record that holds the item's
 * stock (the item itself, or the product when a variation's stock is kept on its product), written to each language
 * record of it. Every change is applied once per operation key and recorded with its reason, its origin and its user in
 * a ledger table, under a lock on that record, inside one database transaction with the figure, so a change is either
 * applied and recorded or neither. Inside the lock the record is read as stored, past every cache, and after the write
 * each language's stored figure is read back: a write that did not land fails the change.
 *
 * The figure is written only through `StockWriteGuard::projection()`: no other local writer may change stock, on
 * either source (D-19 item 5, specs/026).
 *
 * An item whose stock WooCommerce does not count has no figure on the store's side yet: it holds nothing and sells
 * nothing until one is given (by staff, the feed, or the switch to own stock), which also turns counting on.
 *
 * See specs/049-stock-source-switch, FR-002 and FR-003.
 */
final class StoreStockSource implements StockSource
{
    public const TABLE   = 'mizzey_stock_moves';
    public const VERSION = '1';
    public const VERSION_OPTION = 'mizzey_stock_moves_schema';

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
        return self::figureOf(StockMode::holder($item));
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
     * The recorded changes of the record holding an item's stock, newest first.
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
        $holder = StockMode::holder($item);
        $lock   = substr('mz_' . $wpdb->prefix . 'stock_' . $holder->id, 0, 64);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $lock)) !== 1) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            throw new StockSourceUnavailable('stock busy');
        }
        try {
            if (self::applied($operationKey)) {
                return self::figureOf($holder, true);
            }
            $current = self::figureOf($holder, true);
            $change  = $target !== null ? $target - $current : (int) $delta;
            $after   = $current + $change;
            if ($after < 0 && $change < 0) {
                throw new StockSourceRefused('not enough stock');
            }
            $records = self::records(self::product($holder->id));
            $ids     = array_map(static fn (\WC_Product $record): int => $record->get_id(), $records);
            if ($wpdb->query('START TRANSACTION') === false) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                throw new StockSourceUnavailable('the change could not be started');
            }
            try {
                $stored = $wpdb->insert(self::table(), [
                    'item_id'       => $holder->id,
                    'operation_key' => $operationKey,
                    'delta'         => $change,
                    'level_after'   => $after,
                    'origin'        => substr($origin, 0, 20),
                    'reason'        => mb_substr(sanitize_text_field($reason), 0, self::REASON_MAX),
                    'user_id'       => max(0, $userId),
                    'created_at'    => current_time('mysql', true),
                ], ['%d', '%s', '%d', '%d', '%s', '%s', '%d', '%s']); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                if ($stored !== 1) {
                    throw new StockSourceUnavailable('the change was not recorded');
                }
                StockWriteGuard::projection(static function () use ($records, $after): void {
                    // The figure on each language record: they share the one stock, and WooCommerce Multilingual does
                    // not follow a direct stock update.
                    foreach ($records as $record) {
                        if ($record->get_manage_stock() !== true) {
                            $record->set_manage_stock(true);
                            $record->save();
                        }
                        wc_update_product_stock($record, $after, 'set');
                    }
                });
                // WooCommerce does not report a stock update that failed: what landed is read back.
                foreach ($ids as $id) {
                    if (self::stored($id) !== $after) {
                        throw new StockSourceUnavailable('the figure was not written');
                    }
                }
                if ($wpdb->query('COMMIT') === false) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    throw new StockSourceUnavailable('the change was not committed');
                }
            } catch (\Throwable $e) {
                $wpdb->query('ROLLBACK'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                // What this request cached while writing is not what is stored.
                self::evict($ids);
                throw $e instanceof StockSourceRefused || $e instanceof StockSourceUnavailable ? $e : new StockSourceUnavailable($e->getMessage());
            }
            self::evict($ids);
            self::forget($holder->id);

            return self::figureOf($holder, true);
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }
    }

    /** The figure the store holds for the item: its count, or nothing when WooCommerce does not count its stock. */
    private static function figureOf(StockItem $holder, bool $fresh = false): int
    {
        $product = self::product($holder->id, $fresh);
        if ($product->get_manage_stock() !== true) {
            return 0;
        }

        return $fresh ? (int) self::stored($holder->id) : (int) $product->get_stock_quantity();
    }

    /** The stored figure, read from the database past every cache; null when the record has none. */
    private static function stored(int $id): ?int
    {
        global $wpdb;
        $value = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_stock' LIMIT 1", $id)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

        return $value === null || $value === '' ? null : (int) round((float) $value);
    }

    /** @throws StockSourceRefused */
    private static function product(int $id, bool $fresh = false): \WC_Product
    {
        if ($fresh) {
            self::evict([$id]);
        }
        $product = wc_get_product($id);
        if (! $product instanceof \WC_Product) {
            throw new StockSourceRefused('unknown item');
        }

        return $product;
    }

    /**
     * Drops what this request holds of the records in memory: WooCommerce's product objects, the post and its fields.
     *
     * @param list<int> $ids
     */
    private static function evict(array $ids): void
    {
        $cache = class_exists(\Automattic\WooCommerce\Internal\Caches\ProductCache::class)
            ? wc_get_container()->get(\Automattic\WooCommerce\Internal\Caches\ProductCache::class)
            : null;
        foreach ($ids as $id) {
            $cache?->remove($id);
            wp_cache_delete($id, 'post_meta');
            wp_cache_delete($id, 'posts');
            wp_cache_delete('product-' . $id, 'products');
        }
    }

    /**
     * The record and its translations, as products.
     *
     * @return list<\WC_Product>
     */
    private static function records(\WC_Product $product): array
    {
        $records = [$product];
        $type    = $product->is_type('variation') ? 'post_product_variation' : 'post_product';
        $trid    = apply_filters('wpml_element_trid', null, $product->get_id(), $type);
        if ($trid) {
            foreach ((array) apply_filters('wpml_get_element_translations', [], $trid, $type) as $translation) {
                $id = (int) ($translation->element_id ?? 0);
                if ($id > 0 && $id !== $product->get_id()) {
                    $record = self::product($id, true);
                    $records[] = $record;
                }
            }
        }

        return $records;
    }

    /** Drops the admin's kept figure for the item in every language (StockWriteGuard::figure). */
    private static function forget(int $itemId): void
    {
        $type = get_post_type($itemId) === 'product_variation' ? 'product_variation' : 'product';
        // A product's variations whose stock it keeps show its figure too.
        $ids  = array_merge([$itemId], array_map('intval', get_children(['post_parent' => $itemId, 'post_type' => 'product_variation', 'fields' => 'ids'])));
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

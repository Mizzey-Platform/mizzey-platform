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
 * a ledger table, under a lock on that record. Inside the lock the record is read as stored, past every cache. The
 * change is recorded first as pending, with the figure it leads to; the figure is written and read back; then it is
 * marked done, all in one database transaction. A change that fails is undone: the stock fields are put back from a
 * snapshot and the pending record withdrawn, which also holds on tables that ignore transactions (MyISAM). A request
 * that dies part way leaves its record pending, and the next change of the record completes it first, so a change is
 * never lost nor applied twice.
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
    public const VERSION = '3';

    /** A change's state in the ledger: begun and not yet written to the figure, or done. */
    public const PENDING = 'pending';
    public const DONE    = 'done';
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
  state varchar(10) NOT NULL DEFAULT 'done',
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY operation_key (operation_key),
  KEY item_created (item_id,created_at)
) {$charset};");
        if (self::table() !== $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)))) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            return;
        }
        // The ledger lives on the stock fields' own table engine: a transaction that held one and not the other could
        // keep a figure and lose its pending record if the request died.
        // Until they match, the version is not saved and the store's own stock takes no change (change() refuses).
        $engines = self::engines($table);
        $wanted  = $engines[$wpdb->postmeta] ?? '';
        if (in_array($wanted, ['InnoDB', 'MyISAM', 'Aria'], true) && $wanted !== ($engines[$table] ?? '')) {
            $wpdb->query("ALTER TABLE {$table} ENGINE = {$wanted}"); // phpcs:ignore WordPress.DB
            $engines = self::engines($table);
        }
        if ($wanted === '' || $wanted !== ($engines[$table] ?? '')) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->error(sprintf('stock ledger %s is on engine "%s", the stock fields on "%s": own stock takes no change until they match', $table, $engines[$table] ?? '', $wanted), ['source' => StockGateway::LOG_SOURCE]);
            }

            return;
        }
        update_option(self::VERSION_OPTION, self::VERSION, true);
    }

    /**
     * The table engine of the stock fields' table and of the ledger, by table name.
     *
     * @return array<string,string>
     */
    private static function engines(string $table): array
    {
        global $wpdb;
        $rows    = $wpdb->get_results($wpdb->prepare('SELECT TABLE_NAME AS name, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (%s, %s)', $wpdb->postmeta, $table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $engines = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $engines[(string) $row->name] = (string) $row->engine;
        }

        return $engines;
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

    /**
     * Whether a change under this key was applied and finished. A pending one is not: sent again, it goes through
     * the change, which completes it first.
     */
    public static function applied(string $operationKey): bool
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::table() . ' WHERE operation_key = %s AND state = %s', $operationKey, self::DONE)) > 0; // phpcs:ignore WordPress.DB
    }

    /**
     * Runs `$work` holding the lock every change of the item's stock takes, so a decision and the changes it makes
     * (switching an item to its own stock and setting its starting figure) cannot interleave with a sale.
     *
     * @template T
     * @param callable(): T $work
     * @return T
     * @throws StockSourceUnavailable When the lock cannot be taken.
     */
    public static function exclusive(StockItem $item, callable $work)
    {
        global $wpdb;
        $lock = self::lockName(StockMode::holder($item)->id);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $lock)) !== 1) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            throw new StockSourceUnavailable('stock busy');
        }
        try {
            return $work();
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }
    }

    private static function lockName(int $holderId): string
    {
        global $wpdb;

        return substr('mz_' . $wpdb->prefix . 'stock_' . $holderId, 0, 64);
    }

    /**
     * The recorded changes of the record holding an item's stock, newest first.
     *
     * @return list<object{delta:string,level_after:string,origin:string,reason:string,user_id:string,created_at:string}>
     */
    public static function history(int $itemId, int $limit = 20): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT delta, level_after, origin, reason, user_id, created_at FROM ' . self::table() . ' WHERE item_id = %d AND state = %s ORDER BY id DESC LIMIT %d', $itemId, self::DONE, max(1, min(200, $limit)))); // phpcs:ignore WordPress.DB

        return is_array($rows) ? array_values($rows) : [];
    }

    /**
     * @throws StockSourceRefused|StockSourceUnavailable
     */
    private function change(StockItem $item, ?int $delta, ?int $target, string $operationKey, string $origin, string $reason, int $userId): int
    {
        $operationKey = trim($operationKey);
        if ($operationKey === '' || strlen($operationKey) > self::KEY_MAX) {
            throw new StockSourceRefused('the operation key is missing or too long');
        }
        if (get_option(self::VERSION_OPTION) !== self::VERSION) {
            throw new StockSourceUnavailable('the stock ledger is not installed');
        }

        return self::exclusive($item, function () use ($item, $delta, $target, $operationKey, $origin, $reason, $userId): int {
            global $wpdb;
            $holder = StockMode::holder($item);
            // A change of this record that a request began and never finished (it died part way) is completed first.
            self::rollForward($holder);
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
            $before  = self::snapshot($ids);
            $rowId   = 0;
            if ($wpdb->query('START TRANSACTION') === false) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                throw new StockSourceUnavailable('the change could not be started');
            }
            try {
                // Recorded first, as pending, with the figure it leads to: if the request dies before it is done, the
                // next change of this record completes it (rollForward), so it is never lost nor applied twice.
                $stored = $wpdb->insert(self::table(), [
                    'item_id'       => $holder->id,
                    'operation_key' => $operationKey,
                    'delta'         => $change,
                    'level_after'   => $after,
                    'origin'        => substr($origin, 0, 20),
                    'reason'        => mb_substr(sanitize_text_field($reason), 0, self::REASON_MAX),
                    'user_id'       => max(0, $userId),
                    'state'         => self::PENDING,
                    'created_at'    => current_time('mysql', true),
                ], ['%d', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s']); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                if ($stored !== 1) {
                    throw new StockSourceUnavailable('the change was not recorded');
                }
                $rowId = (int) $wpdb->insert_id;
                self::write($records, $after);
                if ($wpdb->update(self::table(), ['state' => self::DONE], ['id' => $rowId], ['%s'], ['%d']) !== 1) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    throw new StockSourceUnavailable('the change was not recorded as done');
                }
                // A commit that reports failure may still have landed. The transaction is ended first, so what is read
                // next is what was committed, not this connection's own uncommitted writes; that decides.
                if ($wpdb->query('COMMIT') === false) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    $wpdb->query('ROLLBACK'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    if (! self::landed($rowId, $ids, $after)) {
                        throw new StockSourceUnavailable('the change was not committed');
                    }
                }
            } catch (\Throwable $e) {
                $wpdb->query('ROLLBACK'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                // A table that does not take part in transactions keeps what was written: it is put back as it was,
                // and the record of the change withdrawn, so the key stays unused.
                StockWriteGuard::projection(static fn () => self::restore($before));
                if ($rowId > 0) {
                    $wpdb->delete(self::table(), ['id' => $rowId], ['%d']); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                }
                // What this request cached while writing is not what is stored.
                self::evict($ids);
                throw $e instanceof StockSourceRefused || $e instanceof StockSourceUnavailable ? $e : new StockSourceUnavailable($e->getMessage());
            }
            self::evict($ids);
            self::forget($holder->id);

            return self::figureOf($holder, true);
        });
    }

    /**
     * Whether a change is stored whole: its record done, and its figure on every language record.
     *
     * @param list<int> $ids
     */
    private static function landed(int $rowId, array $ids, int $after): bool
    {
        global $wpdb;
        if ($wpdb->get_var($wpdb->prepare('SELECT state FROM ' . self::table() . ' WHERE id = %d', $rowId)) !== self::DONE) { // phpcs:ignore WordPress.DB
            return false;
        }
        foreach ($ids as $id) {
            if (self::stored($id) !== $after) {
                return false;
            }
        }

        return true;
    }

    /**
     * Writes the figure to each language record through the guard's projection, then reads back what landed: the
     * figure, counting turned on, the stock status WooCommerce derives from them, and WooCommerce's lookup row.
     * WooCommerce does not report a write that failed.
     *
     * @param list<\WC_Product> $records
     * @throws StockSourceUnavailable
     */
    private static function write(array $records, int $after): void
    {
        global $wpdb;
        StockWriteGuard::projection(static function () use ($records, $after): void {
            // They share the one stock, and WooCommerce Multilingual does not follow a direct stock update.
            foreach ($records as $record) {
                if ($record->get_manage_stock() !== true) {
                    $record->set_manage_stock(true);
                    $record->save();
                }
                wc_update_product_stock($record, $after, 'set');
            }
        });
        // WooCommerce's rule for a counted item: in stock above the out-of-stock threshold, else on backorder when
        // backorders are allowed, else out of stock.
        $threshold = absint(get_option('woocommerce_notify_no_stock_amount', 0));
        foreach ($records as $record) {
            $id     = $record->get_id();
            $status = $after > $threshold ? 'instock' : ($record->get_backorders() !== 'no' ? 'onbackorder' : 'outofstock');
            $manage = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_manage_stock' LIMIT 1", $id)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $stock  = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_stock_status' LIMIT 1", $id)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $lookup = $wpdb->get_row($wpdb->prepare("SELECT stock_quantity, stock_status FROM {$wpdb->prefix}wc_product_meta_lookup WHERE product_id = %d", $id)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            if (self::stored($id) !== $after || $manage !== 'yes' || $stock !== $status
                || ! is_object($lookup) || $lookup->stock_quantity === null || (int) round((float) $lookup->stock_quantity) !== $after || $lookup->stock_status !== $status) {
                throw new StockSourceUnavailable('the figure was not written');
            }
        }
    }

    /**
     * Completes a change of the record that was recorded as pending and never finished: its figure is written, and it
     * is marked done. Under the record's lock, before any new change. Fails closed: until it is complete, the record
     * takes no change.
     *
     * @throws StockSourceUnavailable
     */
    private static function rollForward(StockItem $holder): void
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT id, level_after FROM ' . self::table() . ' WHERE item_id = %d AND state = %s ORDER BY id DESC', $holder->id, self::PENDING)); // phpcs:ignore WordPress.DB
        if (! is_array($rows) || $wpdb->last_error !== '') {
            throw new StockSourceUnavailable('unfinished changes could not be read');
        }
        if ($rows === []) {
            return;
        }
        $records = self::records(self::product($holder->id, true));
        try {
            self::write($records, (int) $rows[0]->level_after);
        } finally {
            self::evict(array_map(static fn (\WC_Product $record): int => $record->get_id(), $records));
        }
        foreach ($rows as $row) {
            if ($wpdb->update(self::table(), ['state' => self::DONE], ['id' => (int) $row->id], ['%s'], ['%d']) !== 1) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                throw new StockSourceUnavailable('an unfinished change could not be marked done');
            }
        }
        self::forget($holder->id);
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->warning(sprintf('stock change of record %d completed after its request stopped part way (figure %d)', $holder->id, (int) $rows[0]->level_after), ['source' => StockGateway::LOG_SOURCE]);
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

    /** The stock fields a change writes, and the lookup row WooCommerce keeps of them. */
    private const FIELDS = ['_stock', '_stock_status', '_manage_stock'];

    /**
     * The stock fields of each record as stored, null where a field is absent.
     *
     * @param list<int> $ids
     * @return array<int,array<string,string|null>>
     */
    private static function snapshot(array $ids): array
    {
        global $wpdb;
        $snapshot = [];
        foreach ($ids as $id) {
            foreach (self::FIELDS as $field) {
                $value = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $id, $field)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $snapshot[$id][$field] = $value === null ? null : (string) $value;
            }
        }

        return $snapshot;
    }

    /**
     * Puts the stock fields, and WooCommerce's lookup row, back as the snapshot holds them. Written directly: the
     * failure being undone may come from WooCommerce's own save.
     *
     * @param array<int,array<string,string|null>> $snapshot
     */
    private static function restore(array $snapshot): void
    {
        global $wpdb;
        foreach ($snapshot as $id => $fields) {
            foreach ($fields as $field => $value) {
                $now = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $id, $field)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                if ($now === $value) {
                    continue;
                }
                if ($value === null) {
                    $wpdb->delete($wpdb->postmeta, ['post_id' => $id, 'meta_key' => $field], ['%d', '%s']); // phpcs:ignore WordPress.DB
                } elseif ($now === null) {
                    $wpdb->insert($wpdb->postmeta, ['post_id' => $id, 'meta_key' => $field, 'meta_value' => $value], ['%d', '%s', '%s']); // phpcs:ignore WordPress.DB
                } else {
                    $wpdb->update($wpdb->postmeta, ['meta_value' => $value], ['post_id' => $id, 'meta_key' => $field], ['%s'], ['%d', '%s']); // phpcs:ignore WordPress.DB
                }
            }
            $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->prefix . 'wc_product_meta_lookup',
                [
                    'stock_quantity' => $fields['_stock'] === null || $fields['_manage_stock'] !== 'yes' ? null : (float) $fields['_stock'],
                    'stock_status'   => (string) ($fields['_stock_status'] ?? 'instock'),
                ],
                ['product_id' => $id],
                ['%f', '%s'],
                ['%d']
            );
        }
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
            wp_cache_delete('lookup_table', 'object_' . $id);
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

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Stock;

defined('ABSPATH') || exit;

/**
 * The route an outside system (the ERP, or a person's tool) calls to feed the store's own stock (D-28).
 *
 * `POST /mizzey/v1/stock` takes `{"items": [{"sku": "...", "set": 12, "key": "..."}, {"sku": "...", "change": -2,
 * "key": "..."}]}`, at most 100 items, and answers each item: `applied`, `already-applied` (the key was used before;
 * nothing changed), `erp-item` (the item reads the ERP, so the store's figure is not the one in use and is not changed),
 * `unknown-sku`, `invalid` or `refused`, with the figure after it. `GET /mizzey/v1/stock?skus=a,b` reads figures and
 * sources, for reconciliation. Keys are kept apart from the store's own keys by a prefix.
 *
 * Only an account holding `mizzey_feed_stock` may call it: WordPress authenticates the call (an application password
 * over HTTPS for a system). The capability is given once to the Administrator; the owner gives it to an integration
 * account with the "Stock feed" role, which holds nothing else. It does not choose the ERP mechanism (M-3): PRE-09 may
 * use it, or not.
 *
 * See specs/049-stock-source-switch, FR-005.
 */
final class StockFeedRoute
{
    public const CAP       = 'mizzey_feed_stock';
    public const ROLE      = 'mizzey_stock_feed';
    public const GRANTED   = 'mizzey_feed_stock_granted';
    public const MAX_ITEMS = 100;
    public const KEY_PREFIX = 'feed:';
    /** The largest figure or change the route accepts. */
    public const MAX_QUANTITY = 999999;

    public static function register(): void
    {
        add_action('init', [self::class, 'grant'], 5);
        add_action('rest_api_init', [self::class, 'routes']);
    }

    public static function grant(): void
    {
        if (get_option(self::GRANTED) === '1') {
            return;
        }
        get_role('administrator')?->add_cap(self::CAP);
        if (get_role(self::ROLE) === null) {
            add_role(self::ROLE, __('Stock feed', 'mizzey-site'), ['read' => true, self::CAP => true]);
        }
        update_option(self::GRANTED, '1', false);
    }

    public static function routes(): void
    {
        register_rest_route('mizzey/v1', '/stock', [
            [
                'methods'             => 'POST',
                'callback'            => [self::class, 'feed'],
                'permission_callback' => [self::class, 'allowed'],
            ],
            [
                'methods'             => 'GET',
                'callback'            => [self::class, 'read'],
                'permission_callback' => [self::class, 'allowed'],
                'args'                => ['skus' => ['type' => 'string', 'required' => true]],
            ],
        ]);
    }

    public static function allowed(): bool|\WP_Error
    {
        if (! is_user_logged_in()) {
            return new \WP_Error('rest_not_logged_in', __('Sign in to feed stock.', 'mizzey-site'), ['status' => 401]);
        }

        return current_user_can(self::CAP) ? true : new \WP_Error('rest_forbidden', __('This account may not feed stock.', 'mizzey-site'), ['status' => 403]);
    }

    public static function feed(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $items = $request->get_json_params()['items'] ?? null;
        if (! is_array($items) || $items === [] || ! array_is_list($items)) {
            return new \WP_Error('mizzey_stock_feed_items', __('Send a list of items.', 'mizzey-site'), ['status' => 400]);
        }
        if (count($items) > self::MAX_ITEMS) {
            /* translators: %d: the largest number of items in one call. */
            return new \WP_Error('mizzey_stock_feed_items', sprintf(__('Send at most %d items in one call.', 'mizzey-site'), self::MAX_ITEMS), ['status' => 400]);
        }
        $store   = new StoreStockSource();
        $results = [];
        foreach ($items as $entry) {
            $results[] = self::one($store, $entry);
        }

        return new \WP_REST_Response(['items' => $results], 200);
    }

    /**
     * @param mixed $entry
     * @return array<string,mixed>
     */
    private static function one(StoreStockSource $store, $entry): array
    {
        $sku = is_array($entry) && is_string($entry['sku'] ?? null) ? trim($entry['sku']) : '';
        $key = is_array($entry) && is_string($entry['key'] ?? null) ? trim($entry['key']) : '';
        $set = is_array($entry) && array_key_exists('set', $entry) ? $entry['set'] : null;
        $chg = is_array($entry) && array_key_exists('change', $entry) ? $entry['change'] : null;
        $out = ['sku' => $sku, 'key' => $key];
        $valid = $sku !== '' && strlen($sku) <= 100
            && preg_match('/^[A-Za-z0-9._:-]{1,150}$/', $key) === 1
            && (($set === null) !== ($chg === null))
            && ($set === null || (is_int($set) && $set >= 0 && $set <= self::MAX_QUANTITY))
            && ($chg === null || (is_int($chg) && $chg !== 0 && abs($chg) <= self::MAX_QUANTITY));
        if (! $valid) {
            return $out + ['status' => 'invalid', 'message' => __('Give a sku, a key of letters, digits and . _ : - (at most 150), and either set (0 to 999999) or a non-zero change (at most 999999 either way), as whole numbers.', 'mizzey-site')];
        }
        $id   = (int) wc_get_product_id_by_sku($sku);
        $item = $id > 0 ? StockIdentity::resolve($id) : null;
        if ($item === null) {
            return $out + ['status' => 'unknown-sku'];
        }
        if (StockMode::sourceFor($item) !== StockMode::STORE) {
            return $out + ['status' => 'erp-item', 'source' => StockMode::ERP];
        }
        $operationKey = self::KEY_PREFIX . $key;
        if (StoreStockSource::applied($operationKey)) {
            return $out + ['status' => 'already-applied', 'level' => $store->level($item)];
        }
        try {
            $level = $set !== null
                ? $store->set($item, $set, $operationKey, StoreStockSource::FEED, 'stock feed', get_current_user_id())
                : $store->move($item, $chg, $operationKey, StoreStockSource::FEED, 'stock feed', get_current_user_id());
        } catch (StockSourceRefused | StockSourceUnavailable $e) {
            return $out + ['status' => 'refused', 'message' => $e->getMessage()];
        }

        return $out + ['status' => 'applied', 'level' => $level];
    }

    public static function read(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $skus = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $request->get_param('skus'))))));
        if ($skus === [] || count($skus) > self::MAX_ITEMS) {
            /* translators: %d: the largest number of SKUs in one call. */
            return new \WP_Error('mizzey_stock_feed_skus', sprintf(__('Ask for 1 to %d SKUs, separated by commas.', 'mizzey-site'), self::MAX_ITEMS), ['status' => 400]);
        }
        $store   = new StoreStockSource();
        $results = [];
        foreach ($skus as $sku) {
            $id   = (int) wc_get_product_id_by_sku($sku);
            $item = $id > 0 ? StockIdentity::resolve($id) : null;
            if ($item === null) {
                $results[] = ['sku' => $sku, 'status' => 'unknown-sku'];
                continue;
            }
            $source    = StockMode::sourceFor($item);
            $results[] = ['sku' => $sku, 'status' => 'known', 'source' => $source] + ($source === StockMode::STORE ? ['level' => $store->level($item)] : []);
        }

        return new \WP_REST_Response(['items' => $results], 200);
    }
}

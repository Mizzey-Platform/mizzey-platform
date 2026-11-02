<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Marketing;

defined('ABSPATH') || exit;

/**
 * The standard e-commerce events across the funnel, in both languages (EVT-01 to EVT-16, EVT-18), as the tracking
 * plan defines them (specs/042). The site pushes each event to the data layer; Tag Manager sends it on (MKT-23).
 *
 * - **Page events** (home, lists, search, product, cart, checkout, purchase) are worked out on the server when the
 *   page is built and pushed as the page loads.
 * - **Things the store does** (add to and remove from the cart, a coupon accepted, the wishlist, sign-up and sign-in)
 *   are queued in the visitor's session when they happen and delivered once: in the Store API's cart answer for the
 *   blocks that changed the cart, or on the next page. Each carries an id, so it is pushed once.
 * - **Choices in the page** (a product card clicked, a shipping or payment method chosen) are heard in the browser
 *   from WooCommerce's blocks (`assets/events.js`).
 * - **Purchase** fires once per order, when the order is confirmed, on its confirmation page.
 * - **Refund** is sent by the store to Google Analytics (Measurement Protocol) when a refund is made, because no
 *   customer's browser is open then. It needs the property's measurement id and an API secret, set in Settings,
 *   Marketing tags; without them nothing is sent.
 *
 * No personal data is in any event. An item is named by its SKU, shared by both language records.
 *
 * Register ids: EVT-01 to EVT-16, EVT-18. See specs/044-commerce-events.
 */
final class Events
{
    public const SESSION   = 'mizzey_events';
    public const TRACKED   = '_mizzey_purchase_tracked';
    public const CLIENT    = '_mizzey_ga_client_id';
    public const GA4_ID    = 'mizzey_ga4_measurement_id';
    public const GA4_KEY   = 'mizzey_ga4_api_secret';
    public const SCRIPT    = 'mizzey-events';
    public const NAMESPACE = 'mizzey-events';
    public const LIST_MAX  = 30;
    public const QUEUE_MAX = 20;

    /** True while WooCommerce adds to the cart: its quantity update of an existing line is part of the add. */
    private static bool $adding = false;

    /** @var array<string,array<string,mixed>> The listed items by product id, for a card clicked in the page. */
    private static array $listed = [];

    /** @var string|null The sign-in method of the request, when it is not a password. */
    private static ?string $method = null;

    public static function register(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue'], 20);
        add_filter('woocommerce_add_to_cart_quantity', [self::class, 'adding'], 1);
        add_action('woocommerce_add_to_cart', [self::class, 'added'], 20, 6);
        add_action('woocommerce_cart_item_removed', [self::class, 'removed'], 20, 2);
        add_action('woocommerce_after_cart_item_quantity_update', [self::class, 'quantity'], 20, 4);
        add_action('woocommerce_applied_coupon', [self::class, 'coupon'], 999, 1);
        add_action('mizzey_wishlist_added', [self::class, 'wishlist'], 20, 1);
        add_action('mizzey_signed_in_by', [self::class, 'signedInBy'], 10, 2);
        add_action('wp_login', [self::class, 'login'], 30, 2);
        add_action('user_register', [self::class, 'signUp'], 30, 1);
        add_action('woocommerce_checkout_order_created', [self::class, 'client'], 20, 1);
        add_action('woocommerce_store_api_checkout_order_processed', [self::class, 'client'], 20, 1);
        add_action('woocommerce_order_refunded', [self::class, 'refund'], 20, 2);
        add_action('woocommerce_blocks_loaded', [self::class, 'storeApi']);
        add_action('admin_init', [self::class, 'settings'], 20);
        add_action('mizzey_marketing_tags_fields', [self::class, 'fields']);
        add_filter('woocommerce_redirect_single_search_result', [self::class, 'singleResult'], 99);
    }

    /**
     * A search with one result is sent straight to that product by WooCommerce, so no results page is shown: the
     * search is queued and pushed on the product page instead.
     *
     * @param mixed $redirect
     * @return mixed
     */
    public static function singleResult($redirect)
    {
        if ($redirect && is_search()) {
            self::queue(['event' => 'search', 'params' => ['search_term' => self::term((string) get_search_query(false)), 'results_count' => 1]]);
        }

        return $redirect;
    }

    /** The refund's route to Analytics: the property's measurement id and a Measurement Protocol API secret. */
    public static function fields(): void
    {
        printf(
            '<h2>%s</h2><p>%s</p><table class="form-table"><tr><th scope="row"><label for="%s">%s</label></th><td><input type="text" id="%s" name="%s" value="%s" placeholder="G-XXXXXXX" /></td></tr><tr><th scope="row"><label for="%s">%s</label></th><td><input type="password" id="%s" name="%s" value="" placeholder="%s" autocomplete="off" /></td></tr></table>',
            esc_html__('Refunds in Google Analytics', 'mizzey-site'),
            esc_html__('A refund is made in the administration, where no customer browser is open, so the store sends it to Analytics itself. Give the property\'s measurement id and a Measurement Protocol API secret (Admin, Data streams); leave them empty to send nothing.', 'mizzey-site'),
            esc_attr(self::GA4_ID),
            esc_html__('Measurement id', 'mizzey-site'),
            esc_attr(self::GA4_ID),
            esc_attr(self::GA4_ID),
            esc_attr((string) get_option(self::GA4_ID, '')),
            esc_attr(self::GA4_KEY),
            esc_html__('API secret', 'mizzey-site'),
            esc_attr(self::GA4_KEY),
            esc_attr(self::GA4_KEY),
            get_option(self::GA4_KEY, '') !== '' ? esc_attr__('Saved; type a new one to replace it', 'mizzey-site') : ''
        );
    }

    // ---- The item --------------------------------------------------------------------------------------------

    /** @return array<string,mixed> One item, as the tracking plan names its fields. */
    public static function item(\WC_Product $product, int $quantity = 1, ?float $unit = null): array
    {
        $parent = $product->get_parent_id() > 0 ? wc_get_product($product->get_parent_id()) : null;
        $base   = $parent instanceof \WC_Product ? $parent : $product;
        $price  = $unit ?? (float) wc_get_price_to_display($product);
        $full   = (float) wc_get_price_to_display($product, ['price' => (float) $product->get_regular_price()]);
        $item   = [
            'item_id'   => ProductFeed::id($product),
            'item_name' => wp_strip_all_tags($base->get_name()),
            'price'     => round($price, 2),
            'discount'  => round(max(0.0, $full - $price), 2),
            'quantity'  => max(1, $quantity),
        ];
        $brands = get_the_terms($base->get_id(), 'product_brand');
        if (is_array($brands) && $brands !== []) {
            $item['item_brand'] = $brands[0]->name;
        }
        $cats = get_the_terms($base->get_id(), 'product_cat');
        if (is_array($cats) && $cats !== []) {
            $item['item_category'] = $cats[0]->name;
        }
        if ($product instanceof \WC_Product_Variation) {
            $item['item_variant'] = wc_get_formatted_variation($product, true, false, true);
        }

        return $item;
    }

    /** @param list<array<string,mixed>> $items */
    private static function value(array $items): float
    {
        return round(array_sum(array_map(static fn (array $i): float => (float) $i['price'] * (int) $i['quantity'], $items)), 2);
    }

    /** @param list<array<string,mixed>> $items @return array<string,mixed> */
    private static function commerce(array $items, array $extra = []): array
    {
        return array_merge(['currency' => get_woocommerce_currency(), 'value' => self::value($items), 'items' => $items], $extra);
    }

    // ---- Page events ---------------------------------------------------------------------------------------

    /** @return list<array{event:string,ecommerce?:array<string,mixed>,params?:array<string,mixed>}> */
    public static function page(): array
    {
        $events = [];
        if (is_front_page()) {
            $events[] = ['event' => 'view_home'];
        }
        $search = is_search() && (get_query_var('post_type') === 'product' || (function_exists('is_shop') && is_shop()));
        if ($search) {
            $count    = (int) ($GLOBALS['wp_query']->found_posts ?? 0);
            $term     = self::term((string) get_search_query(false));
            $events[] = ['event' => 'search', 'params' => ['search_term' => $term, 'results_count' => $count]];
            if ($count === 0) {
                $events[] = ['event' => 'zero_result_search', 'params' => ['search_term' => $term]];
            }
        }
        if (function_exists('is_shop') && (is_shop() || is_product_taxonomy() || $search)) {
            $list = self::listName($search);
            $items = [];
            foreach ((array) ($GLOBALS['wp_query']->posts ?? []) as $i => $post) {
                $product = wc_get_product($post);
                if ($product instanceof \WC_Product && count($items) < self::LIST_MAX) {
                    $item                                  = self::item($product) + ['index' => $i, 'item_list_id' => $list[0], 'item_list_name' => $list[1]];
                    $items[]                               = $item;
                    self::$listed[(string) $product->get_id()] = $item;
                }
            }
            if ($items !== []) {
                $events[] = ['event' => 'view_item_list', 'ecommerce' => ['item_list_id' => $list[0], 'item_list_name' => $list[1], 'items' => $items]];
            }
        }
        if (function_exists('is_product') && is_product()) {
            $product = wc_get_product(get_queried_object_id());
            if ($product instanceof \WC_Product) {
                $events[] = ['event' => 'view_item', 'ecommerce' => self::commerce([self::item($product)])];
            }
        }
        if (function_exists('is_cart') && is_cart() && WC()->cart) {
            $events[] = ['event' => 'view_cart', 'ecommerce' => self::commerce(self::cartItems())];
        }
        if (function_exists('is_checkout') && is_checkout() && ! is_order_received_page() && WC()->cart && ! WC()->cart->is_empty()) {
            $coupons  = implode(',', WC()->cart->get_applied_coupons());
            $events[] = ['event' => 'begin_checkout', 'ecommerce' => self::commerce(self::cartItems(), $coupons !== '' ? ['coupon' => $coupons] : [])];
        }
        if (function_exists('is_order_received_page') && is_order_received_page()) {
            $purchase = self::purchase();
            if ($purchase !== null) {
                $events[] = $purchase;
            }
        }

        return $events;
    }

    /** A search term with any email address or long number (a phone, an order) masked: no personal data. */
    public static function term(string $term): string
    {
        $term = sanitize_text_field($term);
        $term = (string) preg_replace('/\S+@\S+/', '[email]', $term);
        $term = (string) preg_replace('/\+?\d[\d\s-]{5,}\d/', '[number]', $term);

        return mb_substr($term, 0, 100);
    }

    /** @return array{0:string,1:string} The list's id and name. */
    private static function listName(bool $search): array
    {
        if ($search) {
            return ['search', 'search'];
        }
        $term = get_queried_object();
        if ($term instanceof \WP_Term) {
            return [$term->taxonomy . ':' . $term->slug, wp_strip_all_tags($term->name)];
        }

        return ['shop', 'shop'];
    }

    /** @return list<array<string,mixed>> */
    private static function cartItems(): array
    {
        $items = [];
        foreach (WC()->cart->get_cart() as $line) {
            if (($line['data'] ?? null) instanceof \WC_Product) {
                $qty     = max(1, (int) $line['quantity']);
                $items[] = self::item($line['data'], $qty, ((float) ($line['line_total'] ?? 0) + (float) ($line['line_tax'] ?? 0)) / $qty);
            }
        }

        return $items;
    }

    /** The purchase, once, when the order on its confirmation page is confirmed. */
    public static function purchase(): ?array
    {
        $id    = absint(get_query_var('order-received'));
        $order = $id > 0 ? wc_get_order($id) : null;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the order key is the confirmation page's own check.
        $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash((string) $_GET['key'])) : '';
        if (! $order instanceof \WC_Order || $key === '' || ! hash_equals($order->get_order_key(), $key)) {
            return null;
        }
        if ($order->has_status(['pending', 'on-hold', 'failed', 'cancelled', 'checkout-draft'])) {
            return null;
        }
        global $wpdb;
        $lock = substr('mz_' . $wpdb->prefix . 'purchase_' . $order->get_id(), 0, 64);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== 1) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            return null;
        }
        try {
            // Read from the database under the lock: two confirmation pages at once push one purchase.
            if (\MizzeySite\Reviews\StoredOrderMeta::get($order->get_id(), self::TRACKED) !== '') {
                return null;
            }
            $order->update_meta_data(self::TRACKED, (string) time());
            $order->save_meta_data();
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }

        return ['event' => 'purchase', 'id' => 'purchase-' . $order->get_id(), 'ecommerce' => self::orderPayload($order)];
    }

    /** @return array<string,mixed> */
    private static function orderPayload(\WC_Order $order): array
    {
        $items = [];
        foreach ($order->get_items() as $line) {
            if ($line instanceof \WC_Order_Item_Product && $line->get_product() instanceof \WC_Product) {
                $qty     = max(1, (int) $line->get_quantity());
                $items[] = self::item($line->get_product(), $qty, ((float) $line->get_total() + (float) $line->get_total_tax()) / $qty);
            }
        }
        $coupons = implode(',', $order->get_coupon_codes());

        return array_filter([
            'transaction_id' => (string) $order->get_order_number(),
            'value'          => round((float) $order->get_total(), 2),
            'tax'            => round((float) $order->get_total_tax(), 2),
            'shipping'       => round((float) $order->get_shipping_total(), 2),
            'currency'       => $order->get_currency(),
            'coupon'         => $coupons,
            'items'          => $items,
        ], static fn ($v): bool => $v !== '');
    }

    // ---- Things the store does: queued, delivered once -------------------------------------------------------

    /** @param array<string,mixed> $event */
    public static function queue(array $event): void
    {
        if (! function_exists('WC')) {
            return;
        }
        if (! WC()->session && method_exists(WC(), 'initialize_session')) {
            WC()->initialize_session(); // A REST request (a sign-in, the wishlist) has none until asked.
        }
        if (! WC()->session) {
            return;
        }
        $queue   = (array) WC()->session->get(self::SESSION, []);
        $queue[] = ['id' => wp_generate_uuid4()] + $event;
        WC()->session->set(self::SESSION, array_slice($queue, -self::QUEUE_MAX));
        if (! WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
        }
    }

    /** @return list<array<string,mixed>> The queued events, once. */
    public static function drain(): array
    {
        if (! function_exists('WC') || ! WC()->session) {
            return [];
        }
        $queue = (array) WC()->session->get(self::SESSION, []);
        if ($queue !== []) {
            WC()->session->set(self::SESSION, []);
        }

        return array_values($queue);
    }

    /**
     * @param mixed $key
     * @param mixed $productId
     * @param mixed $quantity
     * @param mixed $variationId
     */
    public static function added($key, $productId, $quantity, $variationId = 0, $variation = [], $data = []): void
    {
        self::$adding = false;
        $product      = wc_get_product((int) $variationId > 0 ? (int) $variationId : (int) $productId);
        if ($product instanceof \WC_Product && (int) $quantity > 0) {
            self::queue(['event' => 'add_to_cart', 'ecommerce' => self::commerce([self::item($product, (int) $quantity)])]);
        }
    }

    /**
     * @param mixed $quantity
     * @return mixed
     */
    public static function adding($quantity)
    {
        self::$adding = true;

        return $quantity;
    }

    /**
     * @param mixed $key
     * @param mixed $cart
     */
    public static function removed($key, $cart): void
    {
        $line    = $cart instanceof \WC_Cart ? ($cart->removed_cart_contents[(string) $key] ?? null) : null;
        $product = is_array($line) ? wc_get_product((int) ($line['variation_id'] ?: $line['product_id'])) : null;
        if ($product instanceof \WC_Product) {
            self::queue(['event' => 'remove_from_cart', 'ecommerce' => self::commerce([self::item($product, (int) $line['quantity'])])]);
        }
    }

    /**
     * @param mixed $key
     * @param mixed $quantity
     * @param mixed $old
     * @param mixed $cart
     */
    public static function quantity($key, $quantity, $old, $cart): void
    {
        if (self::$adding) {
            return; // The add itself is pushed by added().
        }
        $line    = $cart instanceof \WC_Cart ? ($cart->cart_contents[(string) $key] ?? null) : null;
        $product = is_array($line) && ($line['data'] ?? null) instanceof \WC_Product ? $line['data'] : null;
        $change  = (int) $quantity - (int) $old;
        if ($product instanceof \WC_Product && $change !== 0) {
            self::queue(['event' => $change > 0 ? 'add_to_cart' : 'remove_from_cart', 'ecommerce' => self::commerce([self::item($product, abs($change))])]);
        }
    }

    /** @param mixed $code */
    public static function coupon($code): void
    {
        $code = wc_format_coupon_code((string) $code);
        if ($code === '') {
            return;
        }
        // Heard at priority 999, after WooCommerce has recalculated the cart for the new coupon.
        $discount = WC()->cart ? (float) WC()->cart->get_coupon_discount_amount($code, false) : 0.0;
        self::queue(['event' => 'apply_coupon', 'params' => ['coupon' => $code, 'discount' => round($discount, 2), 'currency' => get_woocommerce_currency()]]);
    }

    /** @param mixed $productId */
    public static function wishlist($productId): void
    {
        $product = wc_get_product((int) $productId);
        if ($product instanceof \WC_Product) {
            self::queue(['event' => 'add_to_wishlist', 'ecommerce' => self::commerce([self::item($product)])]);
        }
    }

    /**
     * Google or a sign-in link, said by the code that signs the customer in. A sign-in link fires no `wp_login`, so
     * it names the account itself.
     *
     * @param mixed $method
     * @param mixed $userId
     */
    public static function signedInBy($method, $userId = 0): void
    {
        self::$method = in_array($method, ['google', 'link'], true) ? (string) $method : null;
        $user         = (int) $userId > 0 ? get_userdata((int) $userId) : false;
        if ($user instanceof \WP_User) {
            self::login($user->user_login, $user);
        }
    }

    private static function isCustomer(?\WP_User $user): bool
    {
        return $user instanceof \WP_User && ! user_can($user, 'edit_posts');
    }

    /**
     * @param mixed $login
     * @param mixed $user
     */
    public static function login($login, $user = null): void
    {
        if (self::isCustomer($user instanceof \WP_User ? $user : null)) {
            self::queue(['event' => 'login', 'params' => ['method' => self::$method ?? 'password']]);
        }
    }

    /** @param mixed $userId */
    public static function signUp($userId): void
    {
        if (! is_admin() && self::isCustomer(get_userdata((int) $userId) ?: null)) {
            self::queue(['event' => 'sign_up', 'params' => ['method' => self::$method ?? 'email']]);
        }
    }

    // ---- The Store API: the queue in the cart's answer -------------------------------------------------------

    public static function storeApi(): void
    {
        if (! function_exists('woocommerce_store_api_register_endpoint_data')) {
            return;
        }
        woocommerce_store_api_register_endpoint_data([
            'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
            'namespace'       => self::NAMESPACE,
            'data_callback'   => static fn (): array => ['events' => self::drain()],
            'schema_callback' => static fn (): array => ['events' => ['type' => 'array', 'context' => ['view', 'edit'], 'readonly' => true]],
            'schema_type'     => ARRAY_A,
        ]);
    }

    // ---- The browser ---------------------------------------------------------------------------------------

    public static function enqueue(): void
    {
        if (is_admin()) {
            return;
        }
        $file = WP_PLUGIN_DIR . '/mizzey-site/assets/events.js';
        $deps = wp_script_is('wp-hooks', 'registered') ? ['wp-hooks'] : [];
        wp_enqueue_script(self::SCRIPT, plugins_url('mizzey-site/assets/events.js'), $deps, is_readable($file) ? (string) filemtime($file) : null, true);
        $events = array_merge(self::page(), self::drain());
        wp_add_inline_script(self::SCRIPT, 'window.mizzeyEvents=' . wp_json_encode(['language' => TagManager::language(), 'currency' => get_woocommerce_currency(), 'events' => $events, 'listed' => self::$listed]) . ';', 'before');
    }

    // ---- Refunds, from the store ---------------------------------------------------------------------------

    /** The Analytics client id from the visitor's `_ga` cookie, kept on the order to tie a later refund to it. */
    public static function client($order): void
    {
        if (! $order instanceof \WC_Order || ! isset($_COOKIE['_ga']) || ! ConsentBanner::granted()) {
            return;
        }
        $parts = explode('.', sanitize_text_field(wp_unslash((string) $_COOKIE['_ga'])));
        if (count($parts) >= 4 && ctype_digit($parts[2]) && ctype_digit($parts[3])) {
            $order->update_meta_data(self::CLIENT, $parts[2] . '.' . $parts[3]);
            $order->save_meta_data();
        }
    }

    /**
     * @param mixed $orderId
     * @param mixed $refundId
     */
    public static function refund($orderId, $refundId): void
    {
        $id     = trim((string) get_option(self::GA4_ID, ''));
        $secret = trim((string) get_option(self::GA4_KEY, ''));
        $order  = wc_get_order((int) $orderId);
        $refund = wc_get_order((int) $refundId);
        if ($id === '' || $secret === '' || ! $order instanceof \WC_Order || ! $refund instanceof \WC_Order_Refund) {
            return;
        }
        $params = ['transaction_id' => (string) $order->get_order_number(), 'value' => round(abs((float) $refund->get_amount()), 2), 'currency' => $order->get_currency(), 'language' => (string) $order->get_meta('wpml_language') === 'ar' ? 'ar' : 'en'];
        $items  = [];
        foreach ($refund->get_items() as $line) {
            if ($line instanceof \WC_Order_Item_Product && $line->get_product() instanceof \WC_Product && abs((int) $line->get_quantity()) > 0) {
                $qty     = abs((int) $line->get_quantity());
                $items[] = self::item($line->get_product(), $qty, abs((float) $line->get_total() + (float) $line->get_total_tax()) / $qty);
            }
        }
        if ($items !== []) {
            $params['items'] = $items;
        }
        $client = (string) $order->get_meta(self::CLIENT);
        $body   = ['client_id' => $client !== '' ? $client : $order->get_id() . '.' . (int) $order->get_date_created()?->getTimestamp(), 'events' => [['name' => 'refund', 'params' => $params]]];
        $url    = add_query_arg(['measurement_id' => rawurlencode($id), 'api_secret' => rawurlencode($secret)], 'https://www.google-analytics.com/mp/collect');
        $answer = wp_remote_post($url, ['body' => wp_json_encode($body), 'headers' => ['Content-Type' => 'application/json'], 'timeout' => 5]);
        if (is_wp_error($answer) || (int) wp_remote_retrieve_response_code($answer) >= 300) {
            wc_get_logger()->warning(sprintf('refund %s not sent to Analytics: %s', $params['transaction_id'], is_wp_error($answer) ? $answer->get_error_message() : (string) wp_remote_retrieve_response_code($answer)), ['source' => 'mizzey-analytics']);
        }
    }

    public static function settings(): void
    {
        register_setting(TagManager::PAGE, self::GA4_ID, ['type' => 'string', 'default' => '', 'sanitize_callback' => static function ($v): string {
            $v = strtoupper(trim(sanitize_text_field((string) $v)));

            return preg_match('/^G-[A-Z0-9]{4,16}$/', $v) === 1 ? $v : '';
        }]);
        register_setting(TagManager::PAGE, self::GA4_KEY, ['type' => 'string', 'default' => '', 'sanitize_callback' => static function ($v): string {
            $v = mb_substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $v) ?? '', 0, 64);

            return $v !== '' ? $v : (string) get_option(self::GA4_KEY, ''); // An empty field keeps the saved secret.
        }]);
    }
}

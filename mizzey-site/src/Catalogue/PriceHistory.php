<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue;

defined('ABSPATH') || exit;

/**
 * Price change history with user and timestamp (ADM-50, the price part; the stock part is P1-E and waits for PRE-09).
 * CX-03 (D-12) keeps it narrow: the row asks for a record, so each change of a product's or a variation's regular or
 * sale price is written to that item's own history, whatever made it (the product screen, quick or bulk edit, the
 * REST API, an import). Kept on the canonical record only: WooCommerce Multilingual copies the price to the Arabic
 * record, which is the same item, not a second change. Shown on the product screen; nothing deletes it.
 *
 * Register ids: ADM-50. See specs/036-product-management.
 */
final class PriceHistory
{
    public const META = '_mizzey_price_history';

    /** @var array<int,array{regular:string,sale:string}> Prices as stored before the save that is running. */
    private static array $before = [];

    public static function register(): void
    {
        add_action('woocommerce_before_product_object_save', [self::class, 'remember'], 10, 1);
        add_action('woocommerce_after_product_object_save', [self::class, 'record'], 10, 1);
        add_action('woocommerce_before_product_variation_object_save', [self::class, 'remember'], 10, 1);
        add_action('woocommerce_after_product_variation_object_save', [self::class, 'record'], 10, 1);
        add_action('add_meta_boxes', [self::class, 'box']);
        add_filter('woocommerce_duplicate_product_exclude_meta', [self::class, 'notCloned']);
    }

    /** @param mixed $product */
    public static function remember($product): void
    {
        if (! $product instanceof \WC_Product || $product->get_id() <= 0) {
            return;
        }
        $changes = $product->get_changes();
        if (! array_key_exists('regular_price', $changes) && ! array_key_exists('sale_price', $changes)) {
            return;
        }
        $stored = wc_get_product($product->get_id());
        if ($stored instanceof \WC_Product) {
            self::$before[$product->get_id()] = ['regular' => (string) $stored->get_regular_price('edit'), 'sale' => (string) $stored->get_sale_price('edit')];
        }
    }

    /** @param mixed $product */
    public static function record($product): void
    {
        if (! $product instanceof \WC_Product || ! isset(self::$before[$product->get_id()])) {
            return;
        }
        $id     = $product->get_id();
        $before = self::$before[$id];
        unset(self::$before[$id]);
        $after = ['regular' => (string) $product->get_regular_price('edit'), 'sale' => (string) $product->get_sale_price('edit')];
        if ($after === $before) {
            return;
        }
        // One history per item: a change saved on a language record is kept on the item's canonical record, once.
        $canonical = CanonicalProduct::of($id);
        $item      = $canonical instanceof \WC_Product ? $canonical->get_id() : $id;
        $user      = wp_get_current_user();
        $history   = get_post_meta($item, self::META, true);
        $history   = is_array($history) ? $history : [];
        $last      = $history === [] ? null : $history[count($history) - 1];
        if ($item !== $id && is_array($last) && ($last['regular'][1] ?? null) === $after['regular'] && ($last['sale'][1] ?? null) === $after['sale'] && time() - (int) ($last['at'] ?? 0) < 60) {
            return; // The translation following the item's own change (WooCommerce Multilingual's copy).
        }
        $history[] = [
            'at'      => time(),
            'user'    => $user->exists() ? $user->ID : 0,
            'login'   => $user->exists() ? $user->user_login : (defined('WP_CLI') && WP_CLI ? 'wp-cli' : 'system'),
            'regular' => [$before['regular'], $after['regular']],
            'sale'    => [$before['sale'], $after['sale']],
            'record'  => $id,
        ];
        update_post_meta($item, self::META, $history);
    }

    /** @return list<array{at:int,user:int,login:string,regular:array{0:string,1:string},sale:array{0:string,1:string}}> */
    public static function of(int $id): array
    {
        $history = get_post_meta($id, self::META, true);

        return is_array($history) ? array_values($history) : [];
    }

    /** A stored price as staff read it: "0" is a price, an empty value is none. */
    private static function shown(string $price): string
    {
        return $price === '' ? '-' : $price;
    }

    /**
     * A duplicate is a new item: it starts with no history.
     *
     * @param mixed $exclude
     * @return mixed
     */
    public static function notCloned($exclude)
    {
        return is_array($exclude) ? array_merge($exclude, [self::META]) : $exclude;
    }

    public static function box(): void
    {
        add_meta_box('mizzey-price-history', __('Price history', 'mizzey-site'), [self::class, 'render'], 'product', 'side', 'low');
    }

    /** @param mixed $post */
    public static function render($post): void
    {
        if (! $post instanceof \WP_Post) {
            return;
        }
        $canonical = CanonicalProduct::of($post->ID);
        $ids       = [$canonical instanceof \WC_Product ? $canonical->get_id() : $post->ID];
        $product   = wc_get_product($ids[0]);
        if ($product instanceof \WC_Product && $product->is_type('variable')) {
            $ids = array_merge($ids, $product->get_children());
        }
        $lines = [];
        foreach ($ids as $id) {
            foreach (self::of((int) $id) as $entry) {
                $lines[] = [$entry['at'], (int) $id, $entry];
            }
        }
        if ($lines === []) {
            printf('<p>%s</p>', esc_html__('No price change recorded yet.', 'mizzey-site'));
            return;
        }
        usort($lines, static fn (array $a, array $b): int => $b[0] <=> $a[0]);
        echo '<ul>';
        foreach (array_slice($lines, 0, 30) as [$at, $id, $e]) {
            $what = [];
            if ($e['regular'][0] !== $e['regular'][1]) {
                $what[] = sprintf(__('regular %1$s to %2$s', 'mizzey-site'), self::shown($e['regular'][0]), self::shown($e['regular'][1]));
            }
            if ($e['sale'][0] !== $e['sale'][1]) {
                $what[] = sprintf(__('sale %1$s to %2$s', 'mizzey-site'), self::shown($e['sale'][0]), self::shown($e['sale'][1]));
            }
            printf(
                '<li>%s</li>',
                esc_html(sprintf(
                    /* translators: 1: date and time, 2: who, 3: which item, 4: what changed. */
                    __('%1$s, %2$s, #%3$d: %4$s', 'mizzey-site'),
                    wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $at),
                    (string) $e['login'],
                    $id,
                    implode('; ', $what)
                ))
            );
        }
        echo '</ul>';
    }
}

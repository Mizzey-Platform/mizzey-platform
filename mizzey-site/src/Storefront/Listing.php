<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Storefront;

use Automattic\WooCommerce\Internal\ProductFilters\FilterData;
use Automattic\WooCommerce\Internal\ProductFilters\FilterDataProvider;
use Automattic\WooCommerce\Internal\ProductFilters\QueryClauses;

defined('ABSPATH') || exit;

/**
 * What a category listing shows, read from WooCommerce's own product query (specs/057): the designed category page of
 * spec 066, slice 6, renders it (mizzey-theme/inc/listing.php).
 *
 * Nothing here filters, sorts or pages products. The page's main query is WooCommerce's product query, and the filters
 * are WooCommerce's own address parameters, which that query already applies: `categories` and `brands` (the child
 * categories and brands, by slug, comma separated), `filter_<attribute>` with `query_type_<attribute>`, `min_price` and
 * `max_price`, `filter_stock_status`, and `orderby`. What this class adds:
 *
 * - **The listing's data**: the groups of the filter panel with each option's count, read with WooCommerce's own
 *   filter data (the counts its filter blocks show: each group counted with every other chosen filter applied, and
 *   its own left out), the price bounds and how many products sit at each price (the bars over the price slider), the
 *   active filters each with the address that removes it, the result count and the category's total, the sorts, the
 *   paging, and the products of the page.
 * - **Twelve products a page**, as the boards draw them (three rows of four on a desktop).
 * - **The drawer's count**: the category address with `mizzey_count=1` answers `{"count": N}`, the number of products
 *   the same query finds for every chosen filter together, so the phone's filters drawer shows the server's answer
 *   before the shopper applies it (spec 066: one count, on the button, after the server answers).
 * - **The filters without JavaScript**: the filter panel is a form; sent as a form it carries a value list per group
 *   and `mizzey_filters=1`, and is answered with a redirect to the clean address WooCommerce reads (lists joined by
 *   commas, empty values dropped), so it never reaches the query in a shape WooCommerce does not take.
 *
 * WooCommerce's filter data is an internal class of WooCommerce (`Internal\ProductFilters`); it is the one its own
 * filter blocks use, and an upgrade that changes it is caught by t111 (M-8, docs/stack-and-upgrades.md).
 *
 * Register ids: PLP-06 to PLP-10, PLP-12 to PLP-22, SSC-04. See specs/066-stage-2-shop-front, slice 6, over
 * specs/057-listing-filters.
 */
final class Listing
{
    public const PER_PAGE = 12;
    public const COUNT    = 'mizzey_count';
    public const FORM     = 'mizzey_filters';
    /** The price bounds the filters form was drawn with, sent beside the slider so an untouched handle is dropped. */
    public const LOW      = 'mizzey_price_low';
    public const HIGH     = 'mizzey_price_high';
    public const STOCK    = 'filter_stock_status';
    public const SALE     = 'on_sale';
    public const SORTS    = ['menu_order', 'date', 'price', 'price-desc', 'popularity'];
    /** The price slider moves by this much (C1-Options P1: the arrow keys move a handle by EGP 50). */
    public const PRICE_STEP = 50;
    /** How many bars the price group draws. */
    public const BARS = 24;
    /** A group shows this many options before "Show more". */
    public const SHOWN = 4;

    /** @var array<string,mixed>|null */
    private static ?array $view = null;

    public static function register(): void
    {
        add_filter('loop_shop_per_page', [self::class, 'perPage'], 20);
        add_action('parse_request', [self::class, 'cleanForm'], 1);
        add_action('template_redirect', [self::class, 'count'], 1);
        add_action('woocommerce_product_query', [self::class, 'onSale']);
    }

    /**
     * "On sale" (C1-Category-Desktop and C1-Category-Phone draw it beside "In stock only"): the products WooCommerce
     * lists as on sale, through its own list of them.
     */
    public static function onSale(\WP_Query $query): void
    {
        $value = isset($_GET[self::SALE]) && ! is_array($_GET[self::SALE]) ? sanitize_key(wp_unslash((string) $_GET[self::SALE])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (! $query->is_main_query() || '1' !== $value) {
            return;
        }
        $query->set('post__in', array_merge([0], array_map('intval', wc_get_product_ids_on_sale())));
    }

    /** Whether the main query is a category listing. */
    public static function isListing(): bool
    {
        return function_exists('is_product_category') && is_product_category();
    }

    /** @param mixed $perPage */
    public static function perPage($perPage)
    {
        return self::isListing() ? self::PER_PAGE : $perPage;
    }

    /** The address parameters of the filters, as WooCommerce names them for this store. @return list<string> */
    public static function filterParams(): array
    {
        $params = ['categories', 'brands', 'min_price', 'max_price', self::STOCK, self::SALE];
        foreach (self::attributes() as $name => $taxonomy) {
            $params[] = 'filter_' . $name;
        }

        return $params;
    }

    /** @return array<string,string> Attribute name => taxonomy. */
    private static function attributes(): array
    {
        $out = [];
        foreach (wc_get_attribute_taxonomies() as $attribute) {
            $out[(string) $attribute->attribute_name] = wc_attribute_taxonomy_name((string) $attribute->attribute_name);
        }

        return $out;
    }

    /**
     * A filters form sent without JavaScript is answered with the clean address, the one the script builds: a value
     * list becomes one comma separated value, an empty value is dropped, two or more values of an attribute read as
     * either (`query_type_<attribute>=or`), a price handle left at the bound the form was drawn with is dropped, and
     * the form's own fields go.
     */
    public static function cleanForm(\WP $wp): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- a public, read-only listing address.
        if (! isset($_GET[self::FORM])) {
            return;
        }
        $clean = [];
        foreach ((array) wp_unslash($_GET) as $key => $value) {
            $key = sanitize_key((string) $key);
            if (self::FORM === $key || self::COUNT === $key) {
                continue;
            }
            $values = array_values(array_filter(array_map(static fn ($v): string => sanitize_text_field((string) $v), is_array($value) ? $value : [$value]), static fn (string $v): bool => '' !== $v));
            if ([] !== $values) {
                $clean[$key] = implode(',', $values);
            }
        }
        // phpcs:enable
        $low  = isset($clean[self::LOW]) && is_numeric($clean[self::LOW]) ? (float) $clean[self::LOW] : null;
        $high = isset($clean[self::HIGH]) && is_numeric($clean[self::HIGH]) ? (float) $clean[self::HIGH] : null;
        unset($clean[self::LOW], $clean[self::HIGH]);
        if (isset($clean['min_price']) && (! is_numeric($clean['min_price']) || (null !== $low && (float) $clean['min_price'] <= $low))) {
            unset($clean['min_price']);
        }
        if (isset($clean['max_price']) && (! is_numeric($clean['max_price']) || (null !== $high && (float) $clean['max_price'] >= $high))) {
            unset($clean['max_price']);
        }
        foreach (array_keys($clean) as $key) {
            if (str_starts_with($key, 'query_type_') && ! isset($clean['filter_' . substr($key, 11)])) {
                unset($clean[$key]);
            }
            if (str_starts_with($key, 'filter_') && self::STOCK !== $key && str_contains($clean[$key], ',')) {
                $clean['query_type_' . substr($key, 7)] = 'or';
            }
        }
        $path = (string) strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?'); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        wp_safe_redirect(home_url(self::relative($path)) . ([] === $clean ? '' : '?' . http_build_query($clean)), 303);
        exit;
    }

    /** A request path without the folder the site lives in, so home_url() can take it. */
    private static function relative(string $path): string
    {
        $root = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);

        return '/' . ltrim('' !== $root && str_starts_with($path, $root) ? substr($path, strlen($root)) : $path, '/');
    }

    /** The drawer's count: how many products the listing's query finds for every chosen filter together. */
    public static function count(): void
    {
        if (! isset($_GET[self::COUNT]) || ! self::isListing()) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }
        global $wp_query;
        $count = (int) $wp_query->found_posts;
        nocache_headers();
        wp_send_json([
            'count'    => $count,
            /* translators: %s: how many products the chosen filters find. */
            'label'    => sprintf(_n('Show %s product', 'Show %s products', $count, 'mizzey-site'), number_format_i18n($count)),
            /* translators: %s: a number of products. */
            'products' => sprintf(_n('%s product', '%s products', $count, 'mizzey-site'), number_format_i18n($count)),
        ]);
    }

    /**
     * The chosen value of each filter and the sort, from the address.
     *
     * @return array<string,list<string>|string>
     */
    public static function chosen(): array
    {
        $out = [];
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- a public, read-only listing address.
        foreach (self::filterParams() as $param) {
            if (! isset($_GET[$param]) || is_array($_GET[$param])) {
                continue;
            }
            $raw = sanitize_text_field(wp_unslash((string) $_GET[$param]));
            if (self::SALE === $param) {
                if ('1' === $raw) {
                    $out[$param] = '1';
                }
                continue;
            }
            if (in_array($param, ['min_price', 'max_price'], true)) {
                if (is_numeric($raw)) {
                    $out[$param] = (string) (0 + $raw);
                }
                continue;
            }
            $values = array_values(array_filter(array_map('sanitize_title', explode(',', $raw))));
            if ([] !== $values) {
                $out[$param] = $values;
            }
        }
        $orderby = isset($_GET['orderby']) && ! is_array($_GET['orderby']) ? sanitize_key(wp_unslash((string) $_GET['orderby'])) : '';
        // phpcs:enable
        if (in_array($orderby, self::SORTS, true) && $orderby !== self::defaultSort()) {
            $out['orderby'] = $orderby;
        }

        return $out;
    }

    public static function defaultSort(): string
    {
        $default = (string) apply_filters('woocommerce_default_catalog_orderby', get_option('woocommerce_default_catalog_orderby', 'menu_order'));

        return in_array($default, self::SORTS, true) ? $default : 'menu_order';
    }

    /**
     * An address of the listing with these filters and sort, on page 1.
     *
     * @param array<string,list<string>|string> $chosen
     */
    public static function url(string $base, array $chosen): string
    {
        $query = [];
        foreach ($chosen as $param => $value) {
            $value = is_array($value) ? implode(',', $value) : (string) $value;
            if ('' === $value) {
                continue;
            }
            $query[$param] = $value;
            if (str_starts_with($param, 'filter_') && self::STOCK !== $param && is_array($chosen[$param]) && count($chosen[$param]) > 1) {
                $query['query_type_' . substr($param, 7)] = 'or';
            }
        }

        return [] === $query ? $base : $base . (str_contains($base, '?') ? '&' : '?') . http_build_query($query);
    }

    /** The term's address in the current language. */
    private static function base(\WP_Term $term): string
    {
        $link = get_term_link($term);

        return is_wp_error($link) ? home_url('/') : (string) $link;
    }

    /**
     * The listing of the current category page, or null when the page is not one.
     *
     * @return array<string,mixed>|null
     */
    public static function view(): ?array
    {
        if (null !== self::$view) {
            return self::$view;
        }
        global $wp_query;
        $term = get_queried_object();
        if (! $term instanceof \WP_Term || 'product_cat' !== $term->taxonomy || ! $wp_query instanceof \WP_Query) {
            return null;
        }
        $chosen = self::chosen();
        $base   = self::base($term);
        $vars   = array_filter((array) $wp_query->query_vars);
        $plain  = self::without($vars, self::filterParams(), array_merge(['product_cat', 'product_brand'], array_values(self::attributes())), true);
        $data   = self::filterData();

        $found = (int) $wp_query->found_posts;
        $total = null !== $data ? array_sum(array_map('intval', (array) $data->get_stock_status_counts($plain, array_keys(wc_get_product_stock_status_options())))) : $found;
        $any   = [] !== array_diff_key($chosen, ['orderby' => true]);

        $groups = [];
        if (null !== $data) {
            $groups[] = self::termGroup($data, $vars, $plain, $chosen, 'categories', 'product_cat', __('Category', 'mizzey-site'), (int) $term->term_id);
            if (taxonomy_exists('product_brand')) {
                $groups[] = self::termGroup($data, $vars, $plain, $chosen, 'brands', 'product_brand', __('Brand', 'mizzey-site'), 0);
            }
            $groups[] = self::priceGroup($data, $vars, $chosen);
            foreach (self::attributes() as $name => $taxonomy) {
                $groups[] = self::termGroup($data, $vars, $plain, $chosen, 'filter_' . $name, $taxonomy, wc_attribute_label($taxonomy), 0);
            }
        }
        $groups = array_values(array_filter($groups));

        $page  = max(1, (int) get_query_var('paged'));
        $pages = max(1, (int) $wp_query->max_num_pages);

        self::$view = [
            'term'        => $term,
            'name'        => html_entity_decode($term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'description' => trim(wp_strip_all_tags((string) $term->description)),
            'image'       => self::image($term),
            'cats'        => null !== $data ? self::categoryChips($data, $plain, $term) : [],
            'base'        => $base,
            'chosen'      => $chosen,
            'filtered'    => $any,
            'found'       => $found,
            'total'       => max($total, $found),
            'groups'      => $groups,
            'stock'       => in_array('instock', (array) ($chosen[self::STOCK] ?? []), true),
            'sale'        => isset($chosen[self::SALE]),
            'applied'     => self::applied($groups, $chosen, $base),
            'clear'       => self::url($base, array_intersect_key($chosen, ['orderby' => true])),
            'sorts'       => self::sorts($base, $chosen),
            'sort'        => (string) ($chosen['orderby'] ?? self::defaultSort()),
            'products'    => array_map('intval', wp_list_pluck((array) $wp_query->posts, 'ID')),
            'page'        => $page,
            'pages'       => $pages,
            'from'        => $found > 0 ? ($page - 1) * self::PER_PAGE + 1 : 0,
            'to'          => min($found, $page * self::PER_PAGE),
            'suggestions' => 0 === $found && ! $any ? self::suggestions() : [],
            'shop'        => function_exists('wc_get_page_permalink') ? (string) wc_get_page_permalink('shop') : home_url('/'),
            'countUrl'    => add_query_arg(self::COUNT, '1', $base),
        ];

        return self::$view;
    }

    private static function filterData(): ?FilterData
    {
        if (! function_exists('wc_get_container') || ! class_exists(FilterDataProvider::class)) {
            return null;
        }
        $container = wc_get_container();

        return $container->get(FilterDataProvider::class)->with($container->get(QueryClauses::class));
    }

    /**
     * The query's variables without some filters: their parameters, the taxonomy conditions WooCommerce built from
     * them, and the stock condition, as WooCommerce's own filter blocks remove them before counting.
     *
     * @param array<string,mixed> $vars
     * @param list<string>        $params
     * @param list<string>        $taxonomies
     * @return array<string,mixed>
     */
    private static function without(array $vars, array $params, array $taxonomies, bool $stock): array
    {
        foreach ($params as $param) {
            unset($vars[$param], $vars['query_type_' . substr($param, 7)]);
        }
        if (! empty($vars['tax_query']) && is_array($vars['tax_query'])) {
            $vars['tax_query'] = array_values(array_filter($vars['tax_query'], static fn ($q): bool => ! is_array($q) || ! in_array($q['taxonomy'] ?? '', $taxonomies, true)));
        }
        if ($stock && ! empty($vars['meta_query']) && is_array($vars['meta_query'])) {
            $vars['meta_query'] = array_values(array_filter($vars['meta_query'], static fn ($q): bool => ! is_array($q) || '_stock_status' !== ($q['key'] ?? '')));
        }
        if (in_array(self::SALE, $params, true)) {
            unset($vars['post__in']);
        }
        unset($vars['paged']);

        return $vars;
    }

    /**
     * A group of options from a taxonomy: the child categories of the page's category, the brands, or an attribute's
     * values. Its options are those the category holds (without any filter); each count is the products with every
     * other chosen filter applied. A group with no option is left out.
     *
     * @param array<string,mixed>               $vars
     * @param array<string,mixed>               $plain
     * @param array<string,list<string>|string> $chosen
     * @return array<string,mixed>|null
     */
    private static function termGroup(FilterData $data, array $vars, array $plain, array $chosen, string $param, string $taxonomy, string $label, int $parent): ?array
    {
        if (! taxonomy_exists($taxonomy)) {
            return null;
        }
        $picked = (array) ($chosen[$param] ?? []);
        $mine   = self::without($vars, [$param], [$taxonomy], false);
        $now    = str_starts_with($taxonomy, 'pa_') ? (array) $data->get_attribute_counts($mine, $taxonomy) : (array) $data->get_taxonomy_counts($mine, $taxonomy);
        // The options are the values the category's products have, without any filter.
        $all  = str_starts_with($taxonomy, 'pa_') ? (array) $data->get_attribute_counts($plain, $taxonomy) : (array) $data->get_taxonomy_counts($plain, $taxonomy);
        $ids  = array_map('intval', array_keys(array_filter($all)));
        $args = ['taxonomy' => $taxonomy, 'hide_empty' => false, 'include' => [] === $ids ? [0] : $ids];
        if ('product_cat' === $taxonomy) {
            $args['parent'] = $parent;
        }
        $terms = get_terms($args);
        if (is_wp_error($terms)) {
            return null;
        }
        $options = [];
        foreach ($terms as $t) {
            if (! $t instanceof \WP_Term) {
                continue;
            }
            $count   = (int) ($now[$t->term_id] ?? 0);
            $checked = in_array($t->slug, $picked, true);
            $options[] = ['value' => $t->slug, 'label' => html_entity_decode($t->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'count' => $count, 'checked' => $checked];
        }
        if ([] === $options) {
            return null;
        }
        if ('product_cat' !== $taxonomy) {
            usort($options, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));
        }

        return ['kind' => 'terms', 'param' => $param, 'label' => $label, 'options' => $options, 'picked' => count(array_filter(array_column($options, 'checked'))), 'any' => 'product_cat' === $taxonomy ? 'all' : 'any'];
    }

    /**
     * The price group: the bounds of the products the other filters leave, rounded out to the slider's step, the
     * chosen range, and how many products sit at each price, in BARS bars.
     *
     * @param array<string,mixed>               $vars
     * @param array<string,list<string>|string> $chosen
     * @return array<string,mixed>|null
     */
    private static function priceGroup(FilterData $data, array $vars, array $chosen): ?array
    {
        $mine   = self::without($vars, ['min_price', 'max_price'], [], false);
        $bounds = (array) $data->get_filtered_price($mine);
        if (! isset($bounds['min_price'], $bounds['max_price']) || '' === (string) $bounds['max_price']) {
            return null;
        }
        $step = self::PRICE_STEP;
        $low  = (int) (floor((float) $bounds['min_price'] / $step) * $step);
        $high = (int) (ceil((float) $bounds['max_price'] / $step) * $step);
        if ($high <= $low) {
            $high = $low + $step;
        }
        $from = isset($chosen['min_price']) ? max($low, min($high, (int) round((float) $chosen['min_price']))) : $low;
        $to   = isset($chosen['max_price']) ? max($from, min($high, (int) round((float) $chosen['max_price']))) : $high;

        $bars  = array_fill(0, self::BARS, 0);
        $width = ($high - $low) / self::BARS;
        foreach (self::prices($mine) as $price) {
            $bar = (int) floor(($price - $low) / $width);
            ++$bars[max(0, min(self::BARS - 1, $bar))];
        }

        return [
            'kind'    => 'price',
            'param'   => 'price',
            'label'   => __('Price', 'mizzey-site'),
            'low'     => $low,
            'high'    => $high,
            'from'    => $from,
            'to'      => $to,
            'step'    => $step,
            'bars'    => $bars,
            'picked'  => isset($chosen['min_price']) || isset($chosen['max_price']) ? 1 : 0,
        ];
    }

    /**
     * The lowest price of each product the query finds, through WooCommerce's own filter clauses and price lookup.
     *
     * @param array<string,mixed> $vars
     * @return list<float>
     */
    private static function prices(array $vars): array
    {
        global $wpdb;
        $clauses = wc_get_container()->get(QueryClauses::class);
        add_filter('posts_clauses', [$clauses, 'add_query_clauses'], 10, 2);
        add_filter('posts_pre_query', '__return_empty_array');
        $query = new \WP_Query();
        $query->query(array_merge($vars, ['no_found_rows' => true, 'posts_per_page' => -1, 'fields' => 'ids', 'paged' => 1]));
        remove_filter('posts_clauses', [$clauses, 'add_query_clauses'], 10);
        remove_filter('posts_pre_query', '__return_empty_array');
        $ids = array_map('intval', array_column((array) $wpdb->get_results($query->request, ARRAY_A), 'ID')); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared by WP_Query.
        if ([] === $ids) {
            return [];
        }
        $in = implode(',', $ids);

        return array_map('floatval', (array) $wpdb->get_col("SELECT min_price FROM {$wpdb->wc_product_meta_lookup} WHERE product_id IN ({$in})")); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- integers only.
    }

    /**
     * The active filters, each with the address that removes it alone (the sort is kept).
     *
     * @param list<array<string,mixed>>         $groups
     * @param array<string,list<string>|string> $chosen
     * @return list<array{label:string,url:string,param:string}>
     */
    private static function applied(array $groups, array $chosen, string $base): array
    {
        $out = [];
        foreach ($groups as $group) {
            if ('terms' === $group['kind']) {
                foreach ($group['options'] as $option) {
                    if (! $option['checked']) {
                        continue;
                    }
                    $next                    = $chosen;
                    $next[$group['param']]   = array_values(array_diff((array) $chosen[$group['param']], [$option['value']]));
                    $out[] = ['label' => $option['label'], 'url' => self::url($base, $next), 'param' => $group['param']];
                }
            } elseif ('price' === $group['kind'] && $group['picked'] && ($group['from'] > $group['low'] || $group['to'] < $group['high'])) {
                $out[] = ['label' => self::range((int) $group['from'], (int) $group['to']), 'url' => self::url($base, array_diff_key($chosen, ['min_price' => 1, 'max_price' => 1])), 'param' => 'price'];
            }
        }
        if (in_array('instock', (array) ($chosen[self::STOCK] ?? []), true)) {
            $out[] = ['label' => __('In stock only', 'mizzey-site'), 'url' => self::url($base, array_diff_key($chosen, [self::STOCK => 1])), 'param' => self::STOCK];
        }
        if (isset($chosen[self::SALE])) {
            $out[] = ['label' => __('On sale', 'mizzey-site'), 'url' => self::url($base, array_diff_key($chosen, [self::SALE => 1])), 'param' => self::SALE];
        }

        return $out;
    }

    /**
     * A price range as the boards write it: "EGP 400 to 6,000", whole pounds, the symbol once and a space from the
     * numbers; in Arabic the numbers first, as the store's Arabic price format puts the symbol after them.
     */
    public static function range(int $from, int $to): string
    {
        /* translators: 1: the low end of a price range, 2: its high end, both numbers without the currency. */
        $numbers = sprintf(__('%1$s to %2$s', 'mizzey-site'), self::number($from), self::number($to));

        $symbol  = html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $format  = get_woocommerce_price_format();

        return (int) strpos($format, '%2$s') < (int) strpos($format, '%1$s') ? $numbers . "\u{00A0}" . $symbol : $symbol . "\u{00A0}" . $numbers;
    }

    public static function number(int $amount): string
    {
        return number_format($amount, 0, wc_get_price_decimal_separator(), wc_get_price_thousand_separator());
    }

    /**
     * The sorts, as the boards name them, each with its address (the filters kept).
     *
     * @param array<string,list<string>|string> $chosen
     * @return list<array{value:string,label:string,url:string}>
     */
    private static function sorts(string $base, array $chosen): array
    {
        $labels = [
            'menu_order' => __('Relevance', 'mizzey-site'),
            'date'       => __('Newest', 'mizzey-site'),
            'price'      => __('Price low to high', 'mizzey-site'),
            'price-desc' => __('Price high to low', 'mizzey-site'),
            'popularity' => __('Best selling', 'mizzey-site'),
        ];
        $out = [];
        foreach (self::SORTS as $value) {
            $next = $chosen;
            unset($next['orderby']);
            if ($value !== self::defaultSort()) {
                $next['orderby'] = $value;
            }
            $out[] = ['value' => $value, 'label' => $labels[$value], 'url' => self::url($base, $next)];
        }

        return $out;
    }

    /**
     * The chips row: "All" (the top category of this branch) and its child categories, each with its products,
     * counted by WooCommerce's filter data on the branch's own listing (children's products included).
     *
     * @param array<string,mixed> $plain
     * @return list<array{label:string,url:string,count:int,chosen:bool}>
     */
    private static function categoryChips(FilterData $data, array $plain, \WP_Term $term): array
    {
        $root     = $term->parent > 0 ? get_term($term->parent, 'product_cat') : $term;
        $root     = $root instanceof \WP_Term ? $root : $term;
        $children = get_terms(['taxonomy' => 'product_cat', 'parent' => $root->term_id, 'hide_empty' => false]);
        if (is_wp_error($children) || [] === $children) {
            return [];
        }
        $counts = (array) $data->get_taxonomy_counts(array_merge($plain, ['product_cat' => $root->slug, 'term' => $root->slug]), 'product_cat');
        $chips  = [['label' => __('All', 'mizzey-site'), 'url' => self::base($root), 'count' => (int) ($counts[$root->term_id] ?? 0), 'chosen' => $root->term_id === $term->term_id]];
        foreach ($children as $child) {
            if ($child instanceof \WP_Term && (int) ($counts[$child->term_id] ?? 0) > 0) {
                $chips[] = ['label' => html_entity_decode($child->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'url' => self::base($child), 'count' => (int) $counts[$child->term_id], 'chosen' => $child->term_id === $term->term_id];
            }
        }

        return count($chips) > 1 ? $chips : [];
    }

    /**
     * The banner card's image: the category's banner (specs/060), or its own image.
     *
     * @return array{url:string,alt:string,link:string}|null
     */
    private static function image(\WP_Term $term): ?array
    {
        $content = TermContent::content($term);
        if (null === $content) {
            return null;
        }
        if (is_array($content['banner'] ?? null) && '' !== (string) $content['banner']['url']) {
            return ['url' => (string) $content['banner']['url'], 'alt' => (string) $content['banner']['alt'], 'link' => (string) $content['banner']['link']];
        }

        return '' !== (string) ($content['image'] ?? '') ? ['url' => (string) $content['image'], 'alt' => '', 'link' => ''] : null;
    }

    /**
     * What an empty category suggests: the store's four best sellers in the visitor's language (C1-Category-Desktop D6).
     *
     * @return list<int>
     */
    private static function suggestions(): array
    {
        $ids = wc_get_products([
            'status'           => 'publish',
            'visibility'       => 'catalog',
            'stock_status'     => 'instock',
            'limit'            => 4,
            'return'           => 'ids',
            'meta_key'         => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery
            'orderby'          => ['meta_value_num' => 'DESC', 'date' => 'DESC'],
            'suppress_filters' => false,
        ]);

        return array_map('intval', (array) $ids);
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Search;

defined('ABSPATH') || exit;

/**
 * The store's product search: Mizzey's own (D-27, D-20 B13), with no external engine and nothing licensed.
 *
 * - **Where it applies.** Every storefront product search: the search results page (WooCommerce's product archive, with
 *   its full filter set, SRCH-04) and the Store API's `search`, which WooCommerce's blocks use. Never the
 *   administration's own searches.
 * - **What it matches** (SRCH-01, SRCH-02, SRCH-06a). The index (`SearchIndex`): name, descriptions, SKUs, brands and
 *   categories, normalised (`Normalise`), in the request's language. Every word of the query must be found; the query
 *   may also be found in any form the synonym list gives it (`Synonyms`, SRCH-06, SRCH-07). An exact SKU matches.
 * - **Its order** (relevance, PLP-15). An exact SKU first, then a name equal to the query, a name starting with it, a
 *   name containing it (or one of its synonyms), a brand or category, the rest; then the staff's order, then newest.
 * - **Suggestions as one types** (SRCH-03): `GET mizzey/v1/search/suggest?q=`, from two characters: products and
 *   categories or brands, and the address of the full results.
 * - **No result** (SRCH-05): the same route, and `emptySuggestions()` for the results page, offer the most stocked
 *   categories and the best sellers, the same for everyone (B13: nothing personal). The `zero_result_search` event is
 *   specs/044's.
 * - **The log** (SRCH-08): each search made on the results page or through the Store API (its first page) is recorded
 *   with its normalised term, language and result count, so a search with no result is told apart; no person, address
 *   or device is recorded. Kept for a year. Suggestions as one types are not recorded.
 *
 * Register ids: SRCH-01, SRCH-02, SRCH-03, SRCH-04, SRCH-05, SRCH-06, SRCH-06a, SRCH-07, SRCH-08, PLP-15.
 * See specs/058-search.
 */
final class ProductSearch
{
    public const LOG_SCHEMA = 'mizzey_search_log_schema';
    public const LOG_VERSION = '1';
    public const SUGGEST    = 'mizzey_suggest';
    private const KEEP_DAYS = 365;

    /** @var array<string,true> Terms logged in this request. */
    private static array $logged = [];

    public static function register(): void
    {
        add_action('init', [self::class, 'installLog'], 1);
        add_filter('posts_join', [self::class, 'join'], 20, 2);
        add_filter('posts_search', [self::class, 'where'], 20, 2);
        add_filter('posts_search_orderby', [self::class, 'orderby'], 20, 2);
        // `the_posts`, not `found_posts`: WordPress skips the count, and its filter, when nothing is found.
        add_filter('the_posts', [self::class, 'logPosts'], 20, 2);
        // The Store API searches with its own `search` variable and its own clause (name or SKU): ours replaces it.
        add_filter('posts_clauses', [self::class, 'storeApiClauses'], 20, 2);
        add_action('rest_api_init', [self::class, 'routes']);
        add_action('mizzey_search_reindex_all', [SearchIndex::class, 'reindexAll']);
    }

    public static function logTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'mizzey_search_log';
    }

    public static function installLog(): void
    {
        if (get_option(self::LOG_SCHEMA) === self::LOG_VERSION) {
            return;
        }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::logTable();
        dbDelta("CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  term varchar(200) NOT NULL,
  normalised varchar(200) NOT NULL,
  language varchar(7) NOT NULL DEFAULT '',
  results int(10) unsigned NOT NULL DEFAULT 0,
  searched_at int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY normalised (normalised(64)),
  KEY searched_at (searched_at)
) " . $wpdb->get_charset_collate() . ';');
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            update_option(self::LOG_SCHEMA, self::LOG_VERSION, true);
        }
    }

    /** The term of a storefront product search: WordPress's `s`, or the Store API's `search`. */
    public static function term(\WP_Query $query): string
    {
        $term = trim((string) $query->get('s'));

        return $term !== '' ? $term : trim((string) $query->get('search'));
    }

    /** Whether a query is a storefront product search this class answers. */
    public static function applies(\WP_Query $query): bool
    {
        if (self::term($query) === '' || ! SearchIndex::ready()) {
            return false;
        }
        if (is_admin() && ! wp_doing_ajax()) {
            return false;
        }
        $types = (array) $query->get('post_type');

        return in_array('product', $types, true) || $query->get('wc_query') === 'product_query';
    }

    /**
     * @param mixed $join
     * @param mixed $query
     * @return mixed
     */
    public static function join($join, $query)
    {
        if (! $query instanceof \WP_Query || ! self::applies($query) || trim((string) $query->get('s')) === '') {
            return $join;
        }
        global $wpdb;

        return $join . self::joinClause();
    }

    /** The join to the index, as `mzs`. */
    private static function joinClause(): string
    {
        global $wpdb;

        return $wpdb->prepare(' LEFT JOIN %i mzs ON mzs.product_id = %i.ID ', SearchIndex::table(), $wpdb->posts);
    }

    /** @return list<string> The query and its synonym forms, normalised; the query first. */
    public static function phrases(string $query): array
    {
        $own = Normalise::text($query);

        return array_values(array_filter(array_unique(array_merge([$own], Synonyms::alternatives($query))), 'strlen'));
    }

    /**
     * The search condition: any of the phrases, each with every word found in one of the indexed fields, or an exact
     * SKU.
     *
     * @param mixed $search
     * @param mixed $query
     * @return mixed
     */
    public static function where($search, $query)
    {
        if (! $query instanceof \WP_Query || ! self::applies($query) || trim((string) $query->get('s')) === '') {
            return $search;
        }
        return self::condition(trim((string) $query->get('s')));
    }

    /** The condition for a term: any of its phrases, each with every word found in an indexed field, or its SKU. */
    public static function condition(string $raw): string
    {
        global $wpdb;
        $any     = [];
        foreach (self::phrases($raw) as $phrase) {
            $all = [];
            foreach (explode(' ', $phrase) as $word) {
                if (mb_strlen($word) < 2 && ! ctype_digit($word)) {
                    continue; // A single letter would match every product.
                }
                $like  = '%' . $wpdb->esc_like($word) . '%';
                $all[] = $wpdb->prepare('(mzs.title LIKE %s OR mzs.terms LIKE %s OR mzs.sku LIKE %s OR mzs.body LIKE %s)', $like, $like, $like, $like);
            }
            if ($all !== []) {
                $any[] = '(' . implode(' AND ', $all) . ')';
            }
        }
        $any[] = $wpdb->prepare('mzs.sku LIKE %s', '% ' . $wpdb->esc_like(mb_strtolower($raw)) . ' %');

        return sprintf(' %s (%s) ', 'AND', implode(sprintf(' %s ', 'OR'), $any));
    }

    /**
     * @param mixed $orderby
     * @param mixed $query
     * @return mixed
     */
    public static function orderby($orderby, $query)
    {
        if (! $query instanceof \WP_Query || ! self::applies($query)) {
            return $orderby;
        }
        global $wpdb;
        $raw     = self::term($query);
        $own     = Normalise::text($raw);
        $phrases = self::phrases($raw);
        $cases   = [$wpdb->prepare('WHEN mzs.sku LIKE %s THEN 0', '% ' . $wpdb->esc_like(mb_strtolower($raw)) . ' %')];
        $cases[] = $wpdb->prepare('WHEN mzs.title = %s THEN 1', $own);
        $cases[] = $wpdb->prepare('WHEN mzs.title LIKE %s THEN 2', $wpdb->esc_like($own) . '%');
        foreach ($phrases as $phrase) {
            $cases[] = $wpdb->prepare('WHEN mzs.title LIKE %s THEN 3', '%' . $wpdb->esc_like($phrase) . '%');
        }
        foreach ($phrases as $phrase) {
            $cases[] = $wpdb->prepare('WHEN mzs.terms LIKE %s THEN 4', '%' . $wpdb->esc_like($phrase) . '%');
        }

        return sprintf('(%s %s %s 5 %s) %s, %s.%s %s, %s.%s %s', 'CASE', implode(' ', $cases), 'ELSE', 'END', 'ASC', $wpdb->posts, 'menu_order', 'ASC', $wpdb->posts, 'post_date', 'DESC');
    }

    /**
     * A Store API product search: WooCommerce's own clause (the name or the SKU containing the term) is taken out and
     * this search's condition put in, so the blocks find what the results page finds.
     *
     * @param mixed $clauses
     * @param mixed $query
     * @return mixed
     */
    public static function storeApiClauses($clauses, $query)
    {
        if (! is_array($clauses) || ! $query instanceof \WP_Query || trim((string) $query->get('search')) === '' || trim((string) $query->get('s')) !== '' || ! self::applies($query)) {
            return $clauses;
        }
        global $wpdb;
        $term   = trim((string) $query->get('search'));
        $like   = '%' . $wpdb->esc_like($term) . '%';
        $theirs = [
            $wpdb->prepare(sprintf(' %s ( %s.post_title %s %%s %s wc_product_meta_lookup.sku %s %%s ) ', 'AND', $wpdb->posts, 'LIKE', 'OR', 'LIKE'), $like, $like),
            $wpdb->prepare(sprintf(' %s %s.post_title %s %%s ', 'AND', $wpdb->posts, 'LIKE'), $like),
        ];
        $where = str_replace($theirs, '', (string) ($clauses['where'] ?? ''));
        if ($where === (string) ($clauses['where'] ?? '')) {
            return $clauses; // WooCommerce's clause was not found as written: nothing is changed.
        }
        $clauses['where'] = $where . self::condition($term);
        $clauses['join']  = (string) ($clauses['join'] ?? '') . self::joinClause();

        return $clauses;
    }

    /**
     * @param mixed $posts
     * @param mixed $query
     * @return mixed
     */
    public static function logPosts($posts, $query)
    {
        if ($query instanceof \WP_Query) {
            self::log(is_array($posts) && $posts !== [] ? max((int) $query->found_posts, count($posts)) : 0, $query);
        }

        return $posts;
    }

    /**
     * Records a search with its result count: the results page's own query, or a Store API search's first page.
     *
     * @param mixed $found
     * @param mixed $query
     * @return mixed
     */
    public static function log($found, $query)
    {
        if (! $query instanceof \WP_Query || ! self::applies($query) || $query->get(self::SUGGEST)) {
            return $found;
        }
        $rest = defined('REST_REQUEST') && REST_REQUEST;
        if (! $rest && ! $query->is_main_query()) {
            return $found;
        }
        if ($rest && max(1, (int) $query->get('paged')) > 1) {
            return $found;
        }
        $term = mb_substr(wp_strip_all_tags(self::term($query)), 0, 200);
        if ($term === '' || isset(self::$logged[$term]) || get_option(self::LOG_SCHEMA) !== self::LOG_VERSION) {
            return $found;
        }
        self::$logged[$term] = true;
        self::record($term, (int) $found);

        return $found;
    }

    public static function record(string $term, int $results): void
    {
        global $wpdb;
        $wpdb->insert(self::logTable(), [ // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            'term'        => $term,
            'normalised'  => mb_substr(Normalise::text($term), 0, 200),
            'language'    => (string) apply_filters('wpml_current_language', null),
            'results'     => max(0, $results),
            'searched_at' => time(),
        ], ['%s', '%s', '%s', '%d', '%d']);
        if (wp_rand(1, 200) === 1) {
            $wpdb->query($wpdb->prepare('DELETE FROM ' . self::logTable() . ' WHERE searched_at < %d', time() - self::KEEP_DAYS * DAY_IN_SECONDS)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
    }

    public static function routes(): void
    {
        register_rest_route('mizzey/v1', '/search/suggest', [
            'methods'             => 'GET',
            'permission_callback' => '__return_true',
            'args'                => ['q' => ['required' => true, 'type' => 'string']],
            'callback'            => static fn (\WP_REST_Request $request): \WP_REST_Response => new \WP_REST_Response(self::suggest((string) $request->get_param('q'))),
        ]);
    }

    /** @return array<string,mixed> */
    public static function suggest(string $q, int $limit = 6): array
    {
        $q   = trim(wp_strip_all_tags($q));
        $out = ['query' => $q, 'products' => [], 'terms' => [], 'url' => add_query_arg(['s' => $q, 'post_type' => 'product'], home_url('/')), 'alternatives' => null];
        if (mb_strlen(Normalise::text($q)) < 2) {
            return $out;
        }
        $found = new \WP_Query([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            's'              => $q,
            'orderby'        => 'relevance',
            'posts_per_page' => $limit,
            'no_found_rows'  => true,
            self::SUGGEST    => true,
            'tax_query'      => [['taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => ['exclude-from-search'], 'operator' => sprintf('%s %s', 'NOT', 'IN')]], // phpcs:ignore WordPress.DB.SlowDBQuery
        ]);
        foreach ($found->posts as $post) {
            $product = wc_get_product($post);
            if (! $product instanceof \WC_Product) {
                continue;
            }
            $out['products'][] = [
                'id'    => $product->get_id(),
                'name'  => html_entity_decode($product->get_name(), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'url'   => (string) get_permalink($product->get_id()),
                'price' => (string) wc_get_price_to_display($product),
                'image' => (string) wp_get_attachment_image_url((int) $product->get_image_id(), 'woocommerce_gallery_thumbnail'),
            ];
        }
        $phrases = self::phrases($q);
        foreach (['product_cat', 'product_brand'] as $taxonomy) {
            if (! taxonomy_exists($taxonomy)) {
                continue;
            }
            $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true, 'number' => 200]);
            foreach (is_array($terms) ? $terms : [] as $term) {
                $name = Normalise::text($term->name);
                foreach ($phrases as $phrase) {
                    if ($phrase !== '' && str_contains($name, $phrase)) {
                        $link           = get_term_link($term);
                        $out['terms'][] = ['taxonomy' => $taxonomy, 'name' => html_entity_decode($term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'url' => is_wp_error($link) ? '' : (string) $link];
                        break;
                    }
                }
                if (count($out['terms']) >= 4) {
                    break 2;
                }
            }
        }
        if ($out['products'] === [] && $out['terms'] === []) {
            $out['alternatives'] = self::emptySuggestions();
        }

        return $out;
    }

    /**
     * What a search with no result offers instead: the most stocked categories and the best sellers, the same for
     * everyone, in the request's language.
     *
     * @return array{categories:list<array{name:string,url:string}>,products:list<array{id:int,name:string,url:string}>}
     */
    public static function emptySuggestions(int $limit = 4): array
    {
        $categories = [];
        $default    = (int) get_option('default_product_cat', 0);
        $terms      = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => $limit + 1, 'exclude' => $default > 0 ? [$default] : []]);
        foreach (array_slice(is_array($terms) ? $terms : [], 0, $limit) as $term) {
            $link         = get_term_link($term);
            $categories[] = ['name' => html_entity_decode($term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'url' => is_wp_error($link) ? '' : (string) $link];
        }
        $products = [];
        foreach (wc_get_products(['status' => 'publish', 'visibility' => 'catalog', 'meta_key' => 'total_sales', 'orderby' => 'meta_value_num', 'order' => 'DESC', 'limit' => $limit]) as $product) { // phpcs:ignore WordPress.DB.SlowDBQuery
            $products[] = ['id' => $product->get_id(), 'name' => html_entity_decode($product->get_name(), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'url' => (string) get_permalink($product->get_id())];
        }

        return ['categories' => $categories, 'products' => $products];
    }
}

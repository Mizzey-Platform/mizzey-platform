<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue\Import;

use MizzeySite\Catalogue\ProductFields;

defined('ABSPATH') || exit;

/**
 * The initial catalogue load: what the validation pass decided, written to the store in the background.
 *
 * - **Plan first.** The file is read and validated exactly as `wp mizzey catalogue validate` does (specs/006); only rows
 *   the rules create or update are planned, simple and variable products before variations. The plan is kept outside
 *   the web root, because it holds costs.
 * - **Sources, then translations (#247).** Phase one writes the English records. Phase two adds each Arabic record
 *   through WooCommerce Multilingual's translation editor and creates no product. A11 (t19) measured that creating a
 *   product in a process that has already written such a translation corrupts the Arabic variations, so phase two
 *   starts only when every source batch is done, and a source batch that finds itself in such a process puts itself
 *   back in the queue instead of running. Phase three, the images, saves no product: it writes the image fields.
 * - **Blank means no change**, as the load rules say: on an update a blank cell leaves the field, an attribute or an
 *   Arabic text as it was. A value that is present but not usable (a sale price `199,00`) is reported, not guessed.
 * - **Arabic integrity (MIG-02, SSC-21).** Text must be valid UTF-8; a byte order mark is dropped and the text is
 *   normalised to NFC; the stored Arabic title is read back and compared.
 * - **Fields (#295):** price, sale price and cost (WooCommerce's cost of goods, MIG-13); categories as a path (`A > B`),
 *   created where missing (MIG-11); the brand (MIG-12); attributes, used for variations (MIG-10); the SEO title and
 *   description (MIG-15).
 * - **Images (#296, MIG-16, MIG-17):** only when the run says the rights are confirmed (CR-08). Each address is
 *   downloaded once (at most 20 MB), the first as the product image, the others its gallery; the alt text is the
 *   product title when the source gives none.
 * - **In the background (MIG-20):** batches of 20 through Action Scheduler. One run at a time, under a lock; every
 *   action carries its run's id. Progress is saved after each product, and a product already written under its SKU is
 *   updated, never created twice, so a batch can be run again after a crash. `wp mizzey catalogue status` shows it.
 *
 * Register ids: MIG-02, MIG-09, MIG-10, MIG-11, MIG-12, MIG-13, MIG-15, MIG-16, MIG-17, MIG-20, SSC-21.
 * See specs/046-catalogue-load.
 */
final class CatalogueLoad
{
    public const GROUP        = 'mizzey-catalogue';
    public const SOURCES      = 'mizzey_catalogue_sources';
    public const TRANSLATIONS = 'mizzey_catalogue_translations';
    public const IMAGES       = 'mizzey_catalogue_images';
    public const RUN          = 'mizzey_catalogue_run';
    public const BATCH        = 20;
    public const STALE        = 1800;
    public const IMAGE_BYTES  = 20971520;
    public const SOURCE_META  = '_mizzey_source_image';

    /** Set once this process has written a translation: no product may be created after it here (#247). */
    private static bool $translated = false;

    public static function register(): void
    {
        add_action(self::SOURCES, [self::class, 'sources'], 10, 2);
        add_action(self::TRANSLATIONS, [self::class, 'translations'], 10, 2);
        add_action(self::IMAGES, [self::class, 'images'], 10, 2);
    }

    // ---- The plan --------------------------------------------------------------------------------------------

    /**
     * Validate the file and schedule the load. Nothing is written to the catalogue here.
     *
     * @return array<string,mixed> The run's state.
     */
    public static function start(string $file, Mapping $mapping, string $directory, bool $images): array
    {
        global $wpdb;
        $lock = substr('mz_' . $wpdb->prefix . 'catalogue_load', 0, 64);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock)) !== 1) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            throw new \RuntimeException(__('Another catalogue load is starting.', 'mizzey-site'));
        }
        try {
            $current = self::state();
            if (self::active($current)) {
                throw new \RuntimeException(__('Another catalogue load is still running.', 'mizzey-site'));
            }
            $directory = self::directory($directory);
            $read      = ExportReader::read($file, $mapping->source());
            $header    = HeaderCheck::check($read['header'], $mapping);
            if ($header['failures'] !== []) {
                throw new \RuntimeException(implode(' ', array_column($header['failures'], 'detail')));
            }
            $result = ValidationPass::run($mapping, $read);
            $rows   = array_map(static fn (array $raw): array => RowInterpreter::interpret($raw, $read['header'], $mapping), $read['rows']);
            $plan   = [];
            foreach ($result['lines'] as $i => $line) {
                if (! in_array($line['outcome'], ['create', 'update'], true) || ! isset($rows[$i])) {
                    continue;
                }
                $row    = $rows[$i];
                $plan[] = [
                    'row'        => (int) $line['row'],
                    'sku'        => $row['sku'],
                    'type'       => (string) $row['type'],
                    'outcome'    => $line['outcome'],
                    'id'         => (int) $line['resolved_id'],
                    'parent_sku' => $row['parent_sku'],
                    'price'      => $row['price'],
                    'fields'     => $row['fields'],
                    'attributes' => $row['attributes'],
                ];
            }
            usort($plan, static fn (array $a, array $b): int => [(int) ($a['type'] === 'variation'), $a['row']] <=> [(int) ($b['type'] === 'variation'), $b['row']]);
            $planFile = $directory . '/plan.json';
            if (file_put_contents($planFile, (string) wp_json_encode($plan, JSON_UNESCAPED_UNICODE)) === false) {
                throw new \RuntimeException(__('The plan cannot be written.', 'mizzey-site'));
            }
            $state = [
                'id'       => wp_generate_uuid4(),
                'plan'     => $planFile,
                'total'    => count($plan),
                'counts'   => $result['counts'],
                'images'   => $images,
                'status'   => 'sources',
                'ids'      => [],
                'done'     => ['sources' => 0, 'translations' => 0, 'images' => 0],
                'problems' => [],
                'started'  => gmdate('c'),
                'updated'  => time(),
            ];
            self::save($state);
            as_enqueue_async_action(self::SOURCES, [$state['id'], 0], self::GROUP);

            return $state;
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }
    }

    /** A run counts as running while it moved in the last half hour; an older one is taken as stopped. */
    private static function active(array $state): bool
    {
        return in_array($state['status'] ?? '', ['sources', 'translations', 'images'], true) && time() - (int) ($state['updated'] ?? 0) < self::STALE;
    }

    /** The run directory, absolute, writable, and outside the web root (the plan holds costs). */
    private static function directory(string $directory): string
    {
        if ($directory === '' || ! wp_mkdir_p($directory) || ! is_writable($directory)) {
            throw new \RuntimeException(__('The run directory cannot be written.', 'mizzey-site'));
        }
        $real = wp_normalize_path((string) realpath($directory));
        // Every folder the web server can serve: WordPress's own and the uploads folder, which a server may keep
        // elsewhere (staging links it in from outside the release), each by its real path.
        $served = array_filter([
            wp_normalize_path((string) realpath(ABSPATH)),
            wp_normalize_path((string) realpath((string) (wp_upload_dir(null, false)['basedir'] ?? ''))),
        ]);
        foreach ($served as $root) {
            if ($real === '' || str_starts_with($real . '/', rtrim($root, '/') . '/')) {
                throw new \RuntimeException(__('Keep the run directory outside the website folder: the plan holds costs.', 'mizzey-site'));
            }
        }

        return rtrim($real, '/');
    }

    /** @return array<string,mixed> */
    public static function state(): array
    {
        wp_cache_delete(self::RUN, 'options');
        $state = get_option(self::RUN, []);

        return is_array($state) ? $state : [];
    }

    /** @param array<string,mixed> $state */
    private static function save(array $state): void
    {
        $state['updated'] = time();
        update_option(self::RUN, $state, false);
    }

    /** The run this action belongs to, if it is still the current one in this phase. */
    private static function current(string $runId, string $phase): ?array
    {
        $state = self::state();

        return ($state['id'] ?? '') === $runId && ($state['status'] ?? '') === $phase ? $state : null;
    }

    /** @return list<array<string,mixed>>|null The plan, or null when it cannot be read. */
    private static function plan(array $state): ?array
    {
        $file = (string) ($state['plan'] ?? '');
        $json = is_readable($file) ? file_get_contents($file) : false;
        $plan = $json === false ? null : json_decode($json, true);

        return is_array($plan) ? $plan : null;
    }

    /** A plan that cannot be read stops the run, said, never a quiet empty success. */
    private static function failed(array $state): void
    {
        $state['status']     = 'failed';
        $state['problems'][] = ['row' => 0, 'sku' => '', 'problem' => __('The plan file cannot be read.', 'mizzey-site')];
        self::save($state);
    }

    private static function problem(array &$state, array $entry, string $why): void
    {
        $state['problems'][] = ['row' => $entry['row'], 'sku' => $entry['sku'], 'problem' => $why];
    }

    // ---- Phase one: the English records ------------------------------------------------------------------

    /**
     * @param mixed $runId
     * @param mixed $offset
     */
    public static function sources($runId, $offset = 0): void
    {
        $state = self::current((string) $runId, 'sources');
        if ($state === null) {
            return;
        }
        if (self::$translated) {
            // This process has already written a translation: creating a product here is the #247 trigger.
            as_enqueue_async_action(self::SOURCES, [$state['id'], (int) $offset], self::GROUP);
            return;
        }
        $plan = self::plan($state);
        if ($plan === null) {
            self::failed($state);
            return;
        }
        $offset = max(0, (int) $offset);
        do_action('wpml_switch_language', self::sourceLanguage());
        foreach (array_slice($plan, $offset, self::BATCH) as $entry) {
            try {
                $id = self::writeSource($entry, $state);
                $state['ids'][$entry['sku']] = $id;
            } catch (\Throwable $e) {
                self::problem($state, $entry, $e->getMessage());
            }
            ++$state['done']['sources'];
            self::save($state); // Saved after each product, so a crash loses at most the product being written.
        }
        $next = $offset + self::BATCH;
        if ($next < count($plan)) {
            as_enqueue_async_action(self::SOURCES, [$state['id'], $next], self::GROUP);
            return;
        }
        $state['status'] = 'translations';
        self::save($state);
        as_enqueue_async_action(self::TRANSLATIONS, [$state['id'], 0], self::GROUP);
    }

    private static function sourceLanguage(): string
    {
        $default = (string) apply_filters('wpml_default_language', null);

        return $default !== '' ? $default : 'en';
    }

    /** The source-language record of a product id, whichever language record the id names. */
    private static function source(int $id, string $type): int
    {
        $source = (int) apply_filters('wpml_object_id', $id, $type, true, self::sourceLanguage());

        return $source > 0 ? $source : $id;
    }

    /** @param array<string,mixed> $entry @param array<string,mixed> $state */
    private static function writeSource(array $entry, array &$state): int
    {
        $f    = $entry['fields'];
        $type = $entry['type'];
        $id   = $entry['outcome'] === 'update' && $entry['id'] > 0 ? $entry['id'] : (int) wc_get_product_id_by_sku($entry['sku']);
        $new  = $id <= 0;
        if (! $new) {
            $id = self::source($id, $type === 'variation' ? 'product_variation' : 'product');
            if ($type === 'variable' && ! wc_get_product($id) instanceof \WC_Product_Variable) {
                wp_set_object_terms($id, 'variable', 'product_type'); // A simple product the file now gives variations.
                wc_delete_product_transients($id);
            }
        }
        $product = $new ? match ($type) {
            'variable'  => new \WC_Product_Variable(),
            'variation' => new \WC_Product_Variation(),
            default     => new \WC_Product_Simple(),
        } : wc_get_product($id);
        if (! $product instanceof \WC_Product) {
            throw new \RuntimeException(__('The product cannot be read.', 'mizzey-site'));
        }
        if ($new) {
            $product->set_sku($entry['sku']);
            if (! $product instanceof \WC_Product_Variation) {
                $product->set_status('publish');
            }
        }
        if ($product instanceof \WC_Product_Variation) {
            $parent = (int) ($state['ids'][$entry['parent_sku']] ?? wc_get_product_id_by_sku($entry['parent_sku']));
            $parent = $parent > 0 ? self::source($parent, 'product') : 0;
            if ($parent <= 0) {
                throw new \RuntimeException(__('The parent product was not written.', 'mizzey-site'));
            }
            $product->set_parent_id($parent);
            self::parentAttributes($parent, $entry['attributes']);
            $given = array_combine(array_map('sanitize_title', array_keys($entry['attributes'])), array_values($entry['attributes']));
            $product->set_attributes(array_merge($product->get_attributes(), $given)); // Blank cells change nothing.
        } else {
            self::set($f, 'name', static fn (string $v) => $product->set_name(self::text($v)));
            self::set($f, 'description', static fn (string $v) => $product->set_description(self::text($v)));
        }
        if ($entry['price'] !== null) {
            $product->set_regular_price($entry['price']);
        }
        self::set($f, 'sale_price', static function (string $v) use ($product, &$state, $entry): void {
            self::money($v) ? $product->set_sale_price($v) : self::problem($state, $entry, sprintf(__('The sale price "%s" is not a number; it was not written.', 'mizzey-site'), $v));
        });
        self::set($f, 'cost', static function (string $v) use ($product, &$state, $entry): void {
            if (! self::costsOn()) {
                self::problem($state, $entry, __('Cost of goods is switched off in WooCommerce, so the cost was not written.', 'mizzey-site'));
                return;
            }
            self::money($v) ? $product->set_cogs_value((float) $v) : self::problem($state, $entry, sprintf(__('The cost "%s" is not a number; it was not written.', 'mizzey-site'), $v));
        });
        self::set($f, 'source_asin', static fn (string $v) => $product->update_meta_data('_mizzey_source_asin', sanitize_text_field($v)));
        if (! $product instanceof \WC_Product_Variation) {
            self::set($f, 'seo_title', static fn (string $v) => $product->update_meta_data(ProductFields::SEO_TITLE, mb_substr(sanitize_text_field($v), 0, 120)));
            self::set($f, 'seo_description', static fn (string $v) => $product->update_meta_data(ProductFields::SEO_DESC, mb_substr(sanitize_textarea_field($v), 0, 320)));
        }
        $id = $product->save();
        if ($new) {
            // Only a new record is given its language: an existing one keeps its translation group.
            do_action('wpml_set_element_language_details', ['element_id' => $id, 'element_type' => $product instanceof \WC_Product_Variation ? 'post_product_variation' : 'post_product', 'trid' => false, 'language_code' => self::sourceLanguage()]);
        }
        if (! $product instanceof \WC_Product_Variation) {
            self::set($f, 'category', static fn (string $v) => wp_set_object_terms($id, self::categoryPath($v), 'product_cat'));
            self::set($f, 'brand', static fn (string $v) => taxonomy_exists('product_brand') ? wp_set_object_terms($id, [self::term(self::text($v), 'product_brand', 0)], 'product_brand') : null);
        } else {
            \WC_Product_Variable::sync($product->get_parent_id());
        }

        return $id;
    }

    /** WooCommerce's cost of goods (MIG-13 writes the cost there) must be switched on to hold a cost. */
    private static function costsOn(): bool
    {
        return wc_get_container()->get(\Automattic\WooCommerce\Internal\Features\FeaturesController::class)->feature_is_enabled('cost_of_goods_sold');
    }

    private static function money(string $v): bool
    {
        return preg_match('/^\d+(\.\d{1,2})?$/', $v) === 1;
    }

    /** @param array<string,string> $f */
    private static function set(array $f, string $field, callable $apply): void
    {
        if (isset($f[$field]) && trim($f[$field]) !== '') {
            $apply(trim($f[$field]));
        }
    }

    /** Text as stored: valid UTF-8, no byte order mark, NFC. Invalid text is refused, never guessed. */
    public static function text(string $value): string
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            throw new \RuntimeException(__('The text is not valid UTF-8.', 'mizzey-site'));
        }
        $value = (string) preg_replace('/^\x{FEFF}/u', '', $value);
        if (class_exists('\Normalizer')) {
            $value = (string) \Normalizer::normalize($value, \Normalizer::FORM_C);
        }

        return trim($value);
    }

    /** @return list<int> The leaf category and its parents, created where missing. */
    private static function categoryPath(string $path): array
    {
        $parent = 0;
        $leaf   = 0;
        foreach (array_filter(array_map('trim', explode('>', self::text($path)))) as $name) {
            $leaf   = self::term($name, 'product_cat', $parent);
            $parent = $leaf;
        }

        return $leaf > 0 ? [$leaf] : [];
    }

    private static function term(string $name, string $taxonomy, int $parent): int
    {
        $found = term_exists($name, $taxonomy, $parent);
        if (is_array($found)) {
            return (int) $found['term_id'];
        }
        $made = wp_insert_term($name, $taxonomy, ['parent' => $parent]);
        if (is_wp_error($made)) {
            $existing = (int) $made->get_error_data('term_exists');
            if ($existing > 0) {
                return $existing;
            }
            throw new \RuntimeException($made->get_error_message());
        }

        return (int) $made['term_id'];
    }

    /** The variable product lists each attribute a variation uses, with all its values, for variations (MIG-10). */
    private static function parentAttributes(int $parentId, array $attributes): void
    {
        $parent = wc_get_product($parentId);
        if (! $parent instanceof \WC_Product_Variable) {
            throw new \RuntimeException(__('The parent is not a variable product.', 'mizzey-site'));
        }
        $current = $parent->get_attributes();
        foreach ($attributes as $name => $value) {
            $key       = sanitize_title((string) $name);
            // A copy: WooCommerce hands back the objects it holds, and an object changed in place is not seen as a change.
            $attribute = isset($current[$key]) && $current[$key] instanceof \WC_Product_Attribute ? clone $current[$key] : null;
            if (! $attribute instanceof \WC_Product_Attribute) {
                $attribute = new \WC_Product_Attribute();
                $attribute->set_name(ucfirst((string) $name));
                $attribute->set_options([]);
                $attribute->set_visible(true);
            }
            $attribute->set_variation(true); // Also for an attribute the product showed but did not vary by.
            $options = $attribute->get_options();
            if (! in_array($value, $options, true)) {
                $options[] = $value;
                $attribute->set_options($options);
            }
            $current[$key] = $attribute;
        }
        $parent->set_attributes($current);
        $parent->save();
    }

    // ---- Phase two: the Arabic records ---------------------------------------------------------------------

    /**
     * @param mixed $runId
     * @param mixed $offset
     */
    public static function translations($runId, $offset = 0): void
    {
        $state = self::current((string) $runId, 'translations');
        if ($state === null) {
            return;
        }
        $all = self::plan($state);
        if ($all === null) {
            self::failed($state);
            return;
        }
        $plan   = array_values(array_filter($all, static fn (array $e): bool => $e['type'] !== 'variation'));
        $offset = max(0, (int) $offset);
        foreach (array_slice($plan, $offset, self::BATCH) as $entry) {
            $id = (int) ($state['ids'][$entry['sku']] ?? 0);
            $f  = $entry['fields'];
            if ($id > 0 && (trim((string) ($f['name_ar'] ?? '')) !== '' || trim((string) ($f['description_ar'] ?? '')) !== '')) {
                try {
                    self::$translated = true;
                    self::writeTranslation($id, $f);
                } catch (\Throwable $e) {
                    self::problem($state, $entry, $e->getMessage());
                }
            }
            ++$state['done']['translations'];
            self::save($state);
        }
        $next = $offset + self::BATCH;
        if ($next < count($plan)) {
            as_enqueue_async_action(self::TRANSLATIONS, [$state['id'], $next], self::GROUP);
            return;
        }
        $state['status'] = $state['images'] ? 'images' : 'done';
        self::save($state);
        if ($state['images']) {
            as_enqueue_async_action(self::IMAGES, [$state['id'], 0], self::GROUP);
        }
    }

    /** @param array<string,string> $f */
    private static function writeTranslation(int $id, array $f): void
    {
        // A blank cell keeps what the Arabic record already has (blank means no change).
        $existing = (int) apply_filters('wpml_object_id', $id, 'product', false, 'ar');
        $existing = $existing > 0 && $existing !== $id ? get_post($existing) : null;
        $title    = trim((string) ($f['name_ar'] ?? '')) !== '' ? self::text((string) $f['name_ar']) : (string) ($existing->post_title ?? '');
        $body     = trim((string) ($f['description_ar'] ?? '')) !== '' ? self::text((string) $f['description_ar']) : (string) ($existing->post_content ?? '');
        if ($title === '') {
            throw new \RuntimeException(__('The Arabic record needs a title.', 'mizzey-site'));
        }
        $ar = \MizzeySite\I18n\ProductTranslationJob::save($id, 'ar', [
            'title'           => $title,
            'slug'            => (string) ($existing->post_name ?? ''),
            'product_content' => $body,
            'product_excerpt' => (string) ($existing->post_excerpt ?? ''),
        ]);
        if ($ar <= 0 || $ar === $id) {
            throw new \RuntimeException(__('The Arabic record was not created.', 'mizzey-site'));
        }
        wp_cache_delete($ar, 'posts');
        if (get_post_field('post_title', $ar) !== $title) {
            throw new \RuntimeException(__('The Arabic title did not read back as written.', 'mizzey-site'));
        }
    }

    // ---- Phase three: images --------------------------------------------------------------------------------

    /**
     * @param mixed $runId
     * @param mixed $offset
     */
    public static function images($runId, $offset = 0): void
    {
        $state = self::current((string) $runId, 'images');
        if ($state === null) {
            return;
        }
        $all = self::plan($state);
        if ($all === null) {
            self::failed($state);
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $plan   = array_values(array_filter($all, static fn (array $e): bool => trim((string) ($e['fields']['images'] ?? '')) !== ''));
        $offset = max(0, (int) $offset);
        $limit  = static fn (array $args): array => ['limit_response_size' => self::IMAGE_BYTES] + $args;
        add_filter('http_request_args', $limit, 99);
        try {
            foreach (array_slice($plan, $offset, self::BATCH) as $entry) {
                $id = (int) ($state['ids'][$entry['sku']] ?? 0);
                try {
                    if ($id > 0) {
                        self::attach($id, $entry['fields']);
                    }
                } catch (\Throwable $e) {
                    self::problem($state, $entry, $e->getMessage());
                }
                ++$state['done']['images'];
                self::save($state);
            }
        } finally {
            remove_filter('http_request_args', $limit, 99);
        }
        $next = $offset + self::BATCH;
        if ($next < count($plan)) {
            as_enqueue_async_action(self::IMAGES, [$state['id'], $next], self::GROUP);
            return;
        }
        $state['status'] = 'done';
        self::save($state);
    }

    /** The image fields are written directly: no product is saved in this phase. @param array<string,string> $f */
    private static function attach(int $id, array $f): void
    {
        $title = wp_strip_all_tags((string) get_the_title($id));
        $urls  = array_values(array_unique(array_filter(array_map('trim', explode('|', (string) $f['images'])), static fn (string $u): bool => wp_http_validate_url($u) !== false)));
        $alt   = trim((string) ($f['image_alt'] ?? '')) !== '' ? sanitize_text_field((string) $f['image_alt']) : $title;
        $ids   = [];
        foreach (array_slice($urls, 0, 10) as $url) {
            $known = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'fields' => 'ids', 'numberposts' => 1, 'meta_key' => self::SOURCE_META, 'meta_value' => $url]); // phpcs:ignore WordPress.DB.SlowDBQuery
            $media = $known !== [] ? (int) $known[0] : media_sideload_image($url, $id, $alt, 'id');
            if (is_wp_error($media)) {
                throw new \RuntimeException($media->get_error_message());
            }
            update_post_meta((int) $media, self::SOURCE_META, $url);
            if ((string) get_post_meta((int) $media, '_wp_attachment_image_alt', true) === '') {
                update_post_meta((int) $media, '_wp_attachment_image_alt', $alt);
            }
            $ids[] = (int) $media;
        }
        if ($ids === []) {
            return;
        }
        update_post_meta($id, '_thumbnail_id', $ids[0]);
        update_post_meta($id, '_product_image_gallery', implode(',', array_slice($ids, 1)));
        wc_delete_product_transients($id);
    }
}

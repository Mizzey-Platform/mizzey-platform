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
 *   the rules create or update are planned, in the order simple and variable products, then variations.
 * - **Sources, then translations (#247).** Phase one writes the English records only. Phase two adds each Arabic record
 *   through WooCommerce Multilingual's translation editor and creates no product. A11 (t19) measured that creating a
 *   product in a process that has already written such a translation corrupts the Arabic variations; each phase runs in
 *   its own scheduled actions, and the translation phase starts only when every source batch is done.
 * - **Arabic integrity (MIG-02, SSC-21).** Arabic text must be valid UTF-8; a byte order mark is dropped and the text is
 *   normalised to NFC; after writing, the stored title is read back and compared.
 * - **Fields (#295):** price, sale price and cost (WooCommerce's cost of goods, MIG-13); categories as a path (`A > B`),
 *   created where missing (MIG-11); the brand (MIG-12); attributes, used for variations (MIG-10); the SEO title and
 *   description (MIG-15).
 * - **Images (#296, MIG-16, MIG-17):** only when the run says the rights are confirmed (CR-08). Each address is
 *   downloaded once into the media library; the first is the product image, the others its gallery; the alt text is
 *   the product title when the source gives none.
 * - **In the background (MIG-20):** batches of 20 through Action Scheduler, so the store keeps serving. The run's state
 *   is one option, readable with `wp mizzey catalogue status`.
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
    public const SOURCE_META  = '_mizzey_source_image';

    public static function register(): void
    {
        add_action(self::SOURCES, [self::class, 'sources'], 10, 1);
        add_action(self::TRANSLATIONS, [self::class, 'translations'], 10, 1);
        add_action(self::IMAGES, [self::class, 'images'], 10, 1);
    }

    // ---- The plan --------------------------------------------------------------------------------------------

    /**
     * Validate the file and schedule the load. Nothing is written to the catalogue here.
     *
     * @return array<string,mixed> The run's state.
     */
    public static function start(string $file, Mapping $mapping, string $directory, bool $images): array
    {
        $current = self::state();
        if (in_array($current['status'] ?? '', ['sources', 'translations', 'images'], true)) {
            throw new \RuntimeException(__('Another catalogue load is still running.', 'mizzey-site'));
        }
        $read   = ExportReader::read($file, $mapping->source());
        $header = HeaderCheck::check($read['header'], $mapping);
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
        if (! wp_mkdir_p($directory) || ! is_writable($directory)) {
            throw new \RuntimeException(__('The run directory cannot be written.', 'mizzey-site'));
        }
        $planFile = rtrim($directory, '/\\') . '/plan.json';
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
        ];
        update_option(self::RUN, $state, false);
        as_enqueue_async_action(self::SOURCES, [0], self::GROUP);

        return $state;
    }

    /** @return array<string,mixed> */
    public static function state(): array
    {
        $state = get_option(self::RUN, []);

        return is_array($state) ? $state : [];
    }

    /** @param array<string,mixed> $state */
    private static function save(array $state): void
    {
        update_option(self::RUN, $state, false);
    }

    /** @return list<array<string,mixed>> */
    private static function plan(array $state): array
    {
        $json = is_readable((string) ($state['plan'] ?? '')) ? file_get_contents((string) $state['plan']) : false;
        $plan = $json === false ? null : json_decode($json, true);

        return is_array($plan) ? $plan : [];
    }

    private static function problem(array &$state, array $entry, string $why): void
    {
        $state['problems'][] = ['row' => $entry['row'], 'sku' => $entry['sku'], 'problem' => $why];
    }

    // ---- Phase one: the English records ------------------------------------------------------------------

    /** @param mixed $offset */
    public static function sources($offset): void
    {
        $state = self::state();
        if (($state['status'] ?? '') !== 'sources') {
            return;
        }
        $plan   = self::plan($state);
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
        }
        $next = $offset + self::BATCH;
        if ($next < count($plan)) {
            self::save($state);
            as_enqueue_async_action(self::SOURCES, [$next], self::GROUP);
            return;
        }
        // Every source is written: the translations start in later actions, never in this process's batch.
        $state['status'] = 'translations';
        self::save($state);
        as_enqueue_async_action(self::TRANSLATIONS, [0], self::GROUP);
    }

    private static function sourceLanguage(): string
    {
        $default = (string) apply_filters('wpml_default_language', null);

        return $default !== '' ? $default : 'en';
    }

    /** @param array<string,mixed> $entry @param array<string,mixed> $state */
    private static function writeSource(array $entry, array $state): int
    {
        $f       = $entry['fields'];
        $type    = $entry['type'];
        $product = $entry['outcome'] === 'update' && $entry['id'] > 0 ? wc_get_product($entry['id']) : null;
        if (! $product instanceof \WC_Product) {
            $product = match ($type) {
                'variable'  => new \WC_Product_Variable(),
                'variation' => new \WC_Product_Variation(),
                default     => new \WC_Product_Simple(),
            };
            $product->set_sku($entry['sku']);
            if (! $product instanceof \WC_Product_Variation) {
                $product->set_status('publish');
            }
        }
        if ($product instanceof \WC_Product_Variation) {
            $parent = (int) ($state['ids'][$entry['parent_sku']] ?? wc_get_product_id_by_sku($entry['parent_sku']));
            if ($parent <= 0) {
                throw new \RuntimeException(__('The parent product was not written.', 'mizzey-site'));
            }
            $product->set_parent_id($parent);
            self::parentAttributes($parent, $entry['attributes']);
            $product->set_attributes(array_combine(array_map('sanitize_title', array_keys($entry['attributes'])), array_values($entry['attributes'])));
        } else {
            self::set($f, 'name', static fn (string $v) => $product->set_name(self::text($v)));
            self::set($f, 'description', static fn (string $v) => $product->set_description(self::text($v)));
        }
        if ($entry['price'] !== null) {
            $product->set_regular_price($entry['price']);
        }
        self::set($f, 'sale_price', static fn (string $v) => preg_match('/^\d+(\.\d{1,2})?$/', $v) === 1 ? $product->set_sale_price($v) : null);
        self::set($f, 'cost', static fn (string $v) => preg_match('/^\d+(\.\d{1,2})?$/', $v) === 1 && method_exists($product, 'set_cogs_value') ? $product->set_cogs_value((float) $v) : null);
        self::set($f, 'source_asin', static fn (string $v) => $product->update_meta_data('_mizzey_source_asin', sanitize_text_field($v)));
        if (! $product instanceof \WC_Product_Variation) {
            self::set($f, 'seo_title', static fn (string $v) => $product->update_meta_data(ProductFields::SEO_TITLE, mb_substr(sanitize_text_field($v), 0, 120)));
            self::set($f, 'seo_description', static fn (string $v) => $product->update_meta_data(ProductFields::SEO_DESC, mb_substr(sanitize_textarea_field($v), 0, 320)));
        }
        $id = $product->save();
        do_action('wpml_set_element_language_details', ['element_id' => $id, 'element_type' => $product instanceof \WC_Product_Variation ? 'post_product_variation' : 'post_product', 'trid' => false, 'language_code' => self::sourceLanguage()]);
        if (! $product instanceof \WC_Product_Variation) {
            self::set($f, 'category', static fn (string $v) => wp_set_object_terms($id, self::categoryPath($v), 'product_cat'));
            self::set($f, 'brand', static fn (string $v) => taxonomy_exists('product_brand') ? wp_set_object_terms($id, [self::term(self::text($v), 'product_brand', 0)], 'product_brand') : null);
        }
        if ($product instanceof \WC_Product_Variation) {
            \WC_Product_Variable::sync($product->get_parent_id());
        }

        return $id;
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

    /** The variable product lists each attribute a variation uses, with all its values (MIG-10). */
    private static function parentAttributes(int $parentId, array $attributes): void
    {
        $parent = wc_get_product($parentId);
        if (! $parent instanceof \WC_Product_Variable) {
            throw new \RuntimeException(__('The parent is not a variable product.', 'mizzey-site'));
        }
        $current = $parent->get_attributes();
        foreach ($attributes as $name => $value) {
            $key       = sanitize_title((string) $name);
            $attribute = $current[$key] ?? null;
            if (! $attribute instanceof \WC_Product_Attribute) {
                $attribute = new \WC_Product_Attribute();
                $attribute->set_name(ucfirst((string) $name));
                $attribute->set_options([]);
                $attribute->set_visible(true);
                $attribute->set_variation(true);
            }
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

    /** @param mixed $offset */
    public static function translations($offset): void
    {
        $state = self::state();
        if (($state['status'] ?? '') !== 'translations') {
            return;
        }
        $plan   = array_values(array_filter(self::plan($state), static fn (array $e): bool => $e['type'] !== 'variation'));
        $offset = max(0, (int) $offset);
        foreach (array_slice($plan, $offset, self::BATCH) as $entry) {
            $id = (int) ($state['ids'][$entry['sku']] ?? 0);
            $f  = $entry['fields'];
            if ($id > 0 && trim((string) ($f['name_ar'] ?? '')) !== '') {
                try {
                    self::writeTranslation($id, $f);
                } catch (\Throwable $e) {
                    self::problem($state, $entry, $e->getMessage());
                }
            }
            ++$state['done']['translations'];
        }
        $next = $offset + self::BATCH;
        if ($next < count($plan)) {
            self::save($state);
            as_enqueue_async_action(self::TRANSLATIONS, [$next], self::GROUP);
            return;
        }
        $state['status'] = $state['images'] ? 'images' : 'done';
        self::save($state);
        if ($state['images']) {
            as_enqueue_async_action(self::IMAGES, [0], self::GROUP);
        }
    }

    /** @param array<string,string> $f */
    private static function writeTranslation(int $id, array $f): void
    {
        global $sitepress, $woocommerce_wpml, $wpdb;
        if (! class_exists('\WCML_Editor_UI_Product_Job') || ! $sitepress || ! $woocommerce_wpml) {
            throw new \RuntimeException(__('WooCommerce Multilingual is not active.', 'mizzey-site'));
        }
        $title = self::text((string) $f['name_ar']);
        $body  = self::text((string) ($f['description_ar'] ?? ''));
        $job   = new \WCML_Editor_UI_Product_Job(['job_id' => $id, 'target' => 'ar'], $woocommerce_wpml, $sitepress, $wpdb);
        $job->save_translations([md5('title') => $title, md5('slug') => '', md5('product_content') => $body, md5('product_excerpt') => '']);
        $ar = (int) apply_filters('wpml_object_id', $id, 'product', false, 'ar');
        if ($ar <= 0 || $ar === $id) {
            throw new \RuntimeException(__('The Arabic record was not created.', 'mizzey-site'));
        }
        wp_cache_delete($ar, 'posts');
        if (get_post_field('post_title', $ar) !== $title) {
            throw new \RuntimeException(__('The Arabic title did not read back as written.', 'mizzey-site'));
        }
    }

    // ---- Phase three: images --------------------------------------------------------------------------------

    /** @param mixed $offset */
    public static function images($offset): void
    {
        $state = self::state();
        if (($state['status'] ?? '') !== 'images') {
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $plan   = array_values(array_filter(self::plan($state), static fn (array $e): bool => trim((string) ($e['fields']['images'] ?? '')) !== ''));
        $offset = max(0, (int) $offset);
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
        }
        $next = $offset + self::BATCH;
        if ($next < count($plan)) {
            self::save($state);
            as_enqueue_async_action(self::IMAGES, [$next], self::GROUP);
            return;
        }
        $state['status'] = 'done';
        self::save($state);
    }

    /** @param array<string,string> $f */
    private static function attach(int $id, array $f): void
    {
        $product = wc_get_product($id);
        if (! $product instanceof \WC_Product) {
            return;
        }
        $urls = array_values(array_unique(array_filter(array_map('trim', explode('|', (string) $f['images'])), static fn (string $u): bool => wp_http_validate_url($u) !== false)));
        $alt  = trim((string) ($f['image_alt'] ?? '')) !== '' ? sanitize_text_field((string) $f['image_alt']) : wp_strip_all_tags($product->get_name());
        $ids  = [];
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
        $product->set_image_id($ids[0]);
        $product->set_gallery_image_ids(array_slice($ids, 1));
        $product->save();
    }
}

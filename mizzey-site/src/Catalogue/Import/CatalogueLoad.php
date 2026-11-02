<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue\Import;

use MizzeySite\Catalogue\ProductFields;
use MizzeySite\Catalogue\TranslationSync;

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
 * - **The Arabic record follows the English one.** A CRUD save does not run WPML's copy, so the price of an update run
 *   would stay old in Arabic. TranslationSync copies the copied fields after every save of an English record, and
 *   phase two runs WooCommerce Multilingual's whole synchronisation for every product the run touched, the parent of
 *   a new variation included: the Arabic variation is created and its value added to the Arabic attribute list.
 *   A category or brand with no Arabic name is reported as a warning: the Arabic record shows it only once translated.
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
 * - **The run is recorded (ENT-19, CX-04).** The validation report and the client issue list are written into the run
 *   directory before anything is written to the store, and each run is kept as an Import Run record (ImportRunLog):
 *   file and its hash, mapping and rules version, actor, times, counts, outcome, problems and warnings.
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
    public const IMAGE_BATCH  = 5;
    public const IMAGES_MAX   = 10;
    /** Seconds after which an images action that died (a time limit) is resumed by its watchdog. */
    public const WATCHDOG     = 300;
    /** Tries per product before its images are given up, reported. */
    public const IMAGE_TRIES  = 2;
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
            // An earlier run's evidence is never overwritten: its plan, its run copy or its reports.
            foreach (['plan.json', FileImportRunRecorder::FILE, ReportWriter::REPORT, ReportWriter::ISSUES] as $earlier) {
                if (file_exists($directory . '/' . $earlier)) {
                    throw new \RuntimeException(__('Use a run directory with no earlier run in it: each run keeps its own plan and reports.', 'mizzey-site'));
                }
            }
            if ($mapping->columnFor('cost') !== null && ! self::costsOn()) {
                throw new \RuntimeException(__('Cost of goods is switched off in WooCommerce and the mapping fills the cost: switch it on before the load, or no cost is written.', 'mizzey-site'));
            }
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
            $reports  = ReportWriter::write($directory, $result['lines'], $result['client_issues']);
            $planFile = $directory . '/plan.json';
            if (file_put_contents($planFile, (string) wp_json_encode($plan, JSON_UNESCAPED_UNICODE)) === false) {
                throw new \RuntimeException(__('The plan cannot be written.', 'mizzey-site'));
            }
            if (($current['id'] ?? '') !== '' && in_array($current['status'] ?? '', ['sources', 'translations', 'images'], true)) {
                // A run that stopped moving is replaced: its record says so, with what it had written.
                ImportRunLog::update((string) $current['id'], [
                    'finished_at' => gmdate('c'),
                    'outcome'     => 'superseded',
                    'written'     => $current['done'] ?? [],
                    'problems'    => $current['problems'] ?? [],
                    'warnings'    => array_values((array) ($current['warnings'] ?? [])),
                ]);
            }
            $runId = wp_generate_uuid4();
            (new ImportRunLog())->record([
                'id'              => $runId,
                'kind'            => 'load',
                'file'            => ['name' => basename($file), 'reference' => (string) realpath($file), 'sha256' => (string) hash_file('sha256', $file)],
                'mapping_used'    => wp_normalize_path((string) realpath($mapping->path)),
                'mapping_version' => $mapping->version(),
                'rules_version'   => $mapping->rulesVersion(),
                'actor'           => self::actor(),
                'images'          => $images,
                'started_at'      => gmdate('c'),
                'finished_at'     => null,
                'counts'          => $result['counts'],
                'reports'         => $reports,
                'outcome'         => 'running',
            ]);
            $state = [
                'id'       => $runId,
                'plan'     => $planFile,
                'total'    => count($plan),
                'counts'   => $result['counts'],
                'images'   => $images,
                'status'   => 'sources',
                'ids'      => [],
                'done'     => ['sources' => 0, 'translations' => 0, 'images' => 0],
                'problems' => [],
                'warnings' => [],
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

    /**
     * The run directory, absolute, writable, and outside every folder the web server can serve (the plan holds costs).
     *
     * Refused, each by its real path, before anything is created: WordPress's own folder; the uploads folder, which a
     * server may keep elsewhere (staging links it in from outside the release); the site's root folder when WordPress
     * sits in a subfolder of it (the site address names that subfolder, as /wp does, so the folder that much above
     * WordPress is the one the domain serves); and the server's document root when the request names one. Under
     * WP-CLI the document root is WordPress's own folder, which is why the site address is read as well.
     */
    private static function directory(string $directory): string
    {
        if ($directory === '') {
            throw new \RuntimeException(__('The run directory cannot be written.', 'mizzey-site'));
        }
        // The real path of the directory as it will be: its nearest existing folder, resolved, and the rest as given.
        $existing = $directory;
        $rest     = '';
        while (! file_exists($existing) && dirname($existing) !== $existing) {
            $rest     = '/' . basename($existing) . $rest;
            $existing = dirname($existing);
        }
        $base = realpath($existing);
        $real = $base === false ? '' : rtrim(wp_normalize_path($base), '/') . $rest;
        foreach (self::served() as $root) {
            if ($real === '' || str_starts_with($real . '/', rtrim($root, '/') . '/')) {
                throw new \RuntimeException(__('Keep the run directory outside the website folder: the plan holds costs.', 'mizzey-site'));
            }
        }
        if (! wp_mkdir_p($directory) || ! is_writable($directory)) {
            throw new \RuntimeException(__('The run directory cannot be written.', 'mizzey-site'));
        }

        return rtrim(wp_normalize_path((string) realpath($directory)), '/');
    }

    /** @return list<string> The folders the web server can serve, by real path. */
    private static function served(): array
    {
        $wordpress = rtrim(wp_normalize_path((string) realpath(ABSPATH)), '/');
        $subfolder = trim((string) wp_parse_url(site_url(), PHP_URL_PATH), '/');
        $siteRoot  = $subfolder !== '' && str_ends_with(strtolower($wordpress), '/' . strtolower($subfolder))
            ? substr($wordpress, 0, -strlen('/' . $subfolder))
            : '';
        $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? sanitize_text_field(wp_unslash((string) $_SERVER['DOCUMENT_ROOT'])) : '';

        return array_values(array_filter(array_map(
            static fn (string $path): string => $path === '' ? '' : wp_normalize_path((string) realpath($path)),
            [$wordpress, $siteRoot, (string) (wp_upload_dir(null, false)['basedir'] ?? ''), $documentRoot],
        )));
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
        $state['problems'][] = ['row' => 0, 'sku' => '', 'problem' => __('The plan file cannot be read.', 'mizzey-site')];
        self::finish($state, 'failed');
    }

    /** The run ends: its state, and its Import Run record with what was written and what was not. */
    private static function finish(array $state, string $status): void
    {
        $state['status'] = $status;
        self::save($state);
        ImportRunLog::update((string) $state['id'], [
            'finished_at' => gmdate('c'),
            'outcome'     => $status === 'done' ? 'completed' : 'failed',
            'written'     => $state['done'],
            'problems'    => $state['problems'],
            'warnings'    => array_values((array) ($state['warnings'] ?? [])),
        ]);
    }

    private static function actor(): string
    {
        $user = wp_get_current_user();

        return $user->exists() ? $user->user_login : (defined('WP_CLI') && WP_CLI ? 'wp-cli' : 'system');
    }

    /** @param array<string,mixed> $state @param array<string,mixed> $entry */
    private static function warning(array &$state, string $key, array $entry, string $why): void
    {
        $state['warnings'][$key] = ['row' => $entry['row'], 'sku' => $entry['sku'], 'warning' => $why];
    }

    private static function problem(array &$state, array $entry, string $why): void
    {
        $problem = ['row' => $entry['row'], 'sku' => $entry['sku'], 'problem' => $why];
        if (in_array($problem, (array) ($state['problems'] ?? []), true)) {
            return; // A product taken up again (a resumed images action) reports the same problem once.
        }
        $state['problems'][] = $problem;
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
    public static function costsOn(): bool
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
        $plan   = self::translationEntries($all);
        $offset = max(0, (int) $offset);
        do_action('wpml_switch_language', self::sourceLanguage());
        foreach (array_slice($plan, $offset, self::BATCH) as $entry) {
            $id = (int) ($state['ids'][$entry['sku']] ?? 0);
            if ($id <= 0 && ! empty($entry['parent_only'])) {
                $id = self::source((int) wc_get_product_id_by_sku($entry['sku']), 'product');
            }
            $f = $entry['fields'];
            if ($id > 0) {
                try {
                    self::$translated = true;
                    if (trim((string) ($f['name_ar'] ?? '')) !== '' || trim((string) ($f['description_ar'] ?? '')) !== '') {
                        self::writeTranslation($id, $f);
                    }
                    self::follow($id, ! empty($entry['has_variations']), $state, $entry);
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
        if (! $state['images']) {
            self::finish($state, 'done');
            return;
        }
        $state['status'] = 'images';
        self::save($state);
        as_enqueue_async_action(self::IMAGES, [$state['id'], 0], self::GROUP);
    }

    /**
     * Phase two's list: every product row, then the parent of each variation row whose parent has no row of its own
     * (a size added to an existing product), each parent once, marked when variations of it were written.
     *
     * @param list<array<string,mixed>> $all
     * @return list<array<string,mixed>>
     */
    private static function translationEntries(array $all): array
    {
        $parents = [];
        foreach ($all as $e) {
            if ($e['type'] === 'variation' && (string) $e['parent_sku'] !== '') {
                $parents[(string) $e['parent_sku']] ??= $e['row'];
            }
        }
        $entries = [];
        foreach ($all as $e) {
            if ($e['type'] !== 'variation') {
                $entries[] = $e + ['has_variations' => isset($parents[(string) $e['sku']])];
                unset($parents[(string) $e['sku']]);
            }
        }
        foreach ($parents as $sku => $row) {
            $entries[] = ['row' => $row, 'sku' => (string) $sku, 'type' => 'variable', 'fields' => [], 'parent_only' => true, 'has_variations' => true];
        }

        return $entries;
    }

    /**
     * The Arabic record follows the English one: WooCommerce Multilingual's whole synchronisation (price, attributes,
     * terms, a new variation's Arabic variation), the new values of a variable product added to its Arabic attribute
     * list, and a warning for each category or brand that has no Arabic name.
     *
     * @param array<string,mixed> $state @param array<string,mixed> $entry
     */
    private static function follow(int $id, bool $variations, array &$state, array $entry): void
    {
        if (TranslationSync::product($id) === []) {
            return; // No Arabic record: nothing follows.
        }
        if ($variations) {
            self::extendTranslatedOptions($id);
            TranslationSync::product($id); // Each new Arabic variation's value read against the extended list.
        }
        foreach (TranslationSync::translationsOf($id) as $language) {
            foreach (['product_cat', 'product_brand'] as $taxonomy) {
                if ($language === '' || ! taxonomy_exists($taxonomy) || ! apply_filters('wpml_is_translated_taxonomy', false, $taxonomy)) {
                    continue;
                }
                $terms = wp_get_object_terms($id, $taxonomy);
                foreach (is_array($terms) ? $terms : [] as $term) {
                    $translated = (int) apply_filters('wpml_object_id', $term->term_id, $taxonomy, false, $language);
                    if ($translated <= 0 || $translated === (int) $term->term_id) {
                        self::warning($state, $taxonomy . ':' . $term->term_id . ':' . $language, $entry, sprintf(
                            /* translators: 1: a category or brand name, 2: a language code. */
                            __('"%1$s" has no %2$s name, so the %2$s record shows no such category or brand until it is translated.', 'mizzey-site'),
                            $term->name,
                            $language,
                        ));
                    }
                }
            }
        }
    }

    /**
     * A value added to an English attribute (a new size) is added to each translation's own list, at the same position,
     * in the English wording until it is translated: without it the Arabic product page cannot offer the new variation.
     */
    private static function extendTranslatedOptions(int $id): void
    {
        $source = wc_get_product($id);
        if (! $source instanceof \WC_Product_Variable) {
            return;
        }
        foreach (array_keys(TranslationSync::translationsOf($id)) as $translationId) {
            $translation = wc_get_product($translationId);
            if (! $translation instanceof \WC_Product_Variable) {
                continue;
            }
            $theirs  = $translation->get_attributes();
            $changed = false;
            foreach ($source->get_attributes() as $key => $attribute) {
                if (! $attribute instanceof \WC_Product_Attribute || $attribute->is_taxonomy()) {
                    continue;
                }
                $ours = $attribute->get_options();
                if (! isset($theirs[$key]) || ! $theirs[$key] instanceof \WC_Product_Attribute) {
                    $theirs[$key] = clone $attribute;
                    $changed      = true;
                    continue;
                }
                $have = $theirs[$key]->get_options();
                if (count($have) < count($ours)) {
                    $copy = clone $theirs[$key];
                    $copy->set_options(array_merge($have, array_slice($ours, count($have))));
                    $copy->set_variation(true);
                    $theirs[$key] = $copy;
                    $changed      = true;
                }
            }
            if ($changed) {
                $translation->set_attributes($theirs);
                $translation->save();
            }
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
        // Compared as text: a run with no user stores "&" as "&amp;" (core's title filter), which is the same title.
        if (html_entity_decode((string) get_post_field('post_title', $ar), ENT_QUOTES | ENT_HTML5, 'UTF-8') !== html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8')) {
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
        $plan = array_values(array_filter($all, static fn (array $e): bool => trim((string) ($e['fields']['images'] ?? '')) !== ''));
        // Downloads can outlast a web runner's time limit, which ends the action before it queues the next one. So a
        // watchdog is queued first, to resume from the saved progress if this action dies, and is removed when it
        // ends normally; the action hands over once half the time limit is spent, checked between downloads; a
        // product tried twice without finishing is given up and reported, so a resume never loops on it. Progress,
        // not the queued offset, says where to start. One worker per run works at a time, under a lock its database
        // connection holds, so a worker that dies releases it; an action that finds it held (the watchdog firing
        // while a slow worker is still downloading) only puts the watchdog back.
        global $wpdb;
        $watchdog = [$state['id'], 'resume'];
        $worker   = self::imageLock((string) $state['id']);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $worker)) !== 1) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            if (! self::watchdogPending((string) $state['id'])) {
                as_schedule_single_action(time() + self::WATCHDOG, self::IMAGES, $watchdog, self::GROUP);
            }
            return;
        }
        try {
            // Read again under the lock: a worker that held it may have moved the run on, or ended it, meanwhile.
            $state = self::current((string) $runId, 'images');
            if ($state === null) {
                return;
            }
            as_unschedule_all_actions(self::IMAGES, $watchdog, self::GROUP);
            as_schedule_single_action(time() + self::WATCHDOG, self::IMAGES, $watchdog, self::GROUP);
            $state = self::imageBatch($state, $plan);
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $worker)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }
        // After the lock is released: the next action, or the end; only then the watchdog goes, so at no moment is
        // the run left with neither.
        if ((int) $state['done']['images'] < count($plan)) {
            as_enqueue_async_action(self::IMAGES, [$state['id'], (int) $state['done']['images']], self::GROUP);
        } else {
            self::finish($state, 'done');
        }
        as_unschedule_all_actions(self::IMAGES, $watchdog, self::GROUP);
    }

    /** Whether a watchdog of this run waits in the queue; the one running now does not count. */
    public static function watchdogPending(string $runId): bool
    {
        return as_get_scheduled_actions(['hook' => self::IMAGES, 'args' => [$runId, 'resume'], 'group' => self::GROUP, 'status' => \ActionScheduler_Store::STATUS_PENDING, 'per_page' => 1], 'ids') !== [];
    }

    /** The lock an images worker of this run holds while it works. */
    public static function imageLock(string $runId): string
    {
        global $wpdb;

        return substr('mz_' . $wpdb->prefix . 'img_' . md5($runId), 0, 64);
    }

    /**
     * One images action's work, under the worker lock: products from the saved progress until five are done or half
     * the time limit is spent, checked between downloads too; then the next action, or the end of the run.
     *
     * @param array<string,mixed>       $state
     * @param list<array<string,mixed>> $plan
     * @return array<string,mixed> The state as this action leaves it.
     */
    private static function imageBatch(array $state, array $plan): array
    {
        $from     = max(0, (int) ($state['done']['images'] ?? 0));
        $deadline = microtime(true) + self::timeBudget();
        $limit    = static fn (array $args): array => ['limit_response_size' => self::IMAGE_BYTES] + $args;
        add_filter('http_request_args', $limit, 99);
        try {
            foreach (array_slice($plan, $from, self::IMAGE_BATCH, true) as $index => $entry) {
                $tries = (int) ($state['image_tries'][$index] ?? 0) + 1;
                $state['image_tries'][$index] = $tries;
                self::save($state); // Saved before the downloads, so a try that dies is counted.
                $id       = (int) ($state['ids'][$entry['sku']] ?? 0);
                $finished = true;
                try {
                    if ($tries > self::IMAGE_TRIES) {
                        self::problem($state, $entry, __('The images did not finish downloading in two tries (a time limit); they were not attached.', 'mizzey-site'));
                    } elseif ($id > 0) {
                        $finished = self::attach($id, $entry, $state, $deadline);
                    }
                } catch (\Throwable $e) {
                    self::problem($state, $entry, $e->getMessage());
                }
                if (! $finished) {
                    // Handed over between two downloads: not a failed try; the next action takes the product up again,
                    // reusing the images already stored.
                    $state['image_tries'][$index] = $tries - 1;
                    self::save($state);
                    break;
                }
                ++$state['done']['images'];
                self::save($state);
                if (microtime(true) > $deadline) {
                    break;
                }
            }
        } finally {
            remove_filter('http_request_args', $limit, 99);
        }

        return $state;
    }

    /**
     * Seconds an images action may spend before it hands over: half the PHP time limit, or two minutes with none.
     * A host with an unusual limit sets its own through `mizzey_catalogue_image_seconds`.
     */
    private static function timeBudget(): float
    {
        $limit = (int) ini_get('max_execution_time');

        return max(0.0, (float) apply_filters('mizzey_catalogue_image_seconds', $limit > 0 ? max(5.0, $limit / 2) : 120.0));
    }

    /**
     * The image fields are written directly: no product is saved in this phase. An address that is refused, and every
     * address after the tenth, is reported, never dropped quietly.
     *
     * @param array<string,mixed> $entry @param array<string,mixed> $state
     * @return bool False when the time was spent before the next download: nothing is set yet, the product is resumed.
     */
    private static function attach(int $id, array $entry, array &$state, float $deadline = INF): bool
    {
        $f     = $entry['fields'];
        $title = wp_strip_all_tags((string) get_the_title($id));
        $urls  = [];
        foreach (array_unique(array_filter(array_map('trim', explode('|', (string) $f['images'])), 'strlen')) as $url) {
            if (wp_http_validate_url($url) === false) {
                /* translators: %s: an image address from the file. */
                self::problem($state, $entry, sprintf(__('The image address "%s" is not a public web address; it was not downloaded.', 'mizzey-site'), $url));
                continue;
            }
            $urls[] = $url;
        }
        foreach (array_slice($urls, self::IMAGES_MAX) as $url) {
            /* translators: %s: an image address from the file. */
            self::problem($state, $entry, sprintf(__('Only ten images are kept per product; "%s" was not downloaded.', 'mizzey-site'), $url));
        }
        $alt   = trim((string) ($f['image_alt'] ?? '')) !== '' ? sanitize_text_field((string) $f['image_alt']) : $title;
        $ids   = [];
        foreach (array_slice($urls, 0, self::IMAGES_MAX) as $url) {
            $known = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'fields' => 'ids', 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'meta_key' => self::SOURCE_META, 'meta_value' => $url]); // phpcs:ignore WordPress.DB.SlowDBQuery
            if ($known === [] && microtime(true) > $deadline) {
                return false;
            }
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
            return true;
        }
        // The same images on each language record: one shared attachment, as WooCommerce Multilingual shows them.
        foreach (array_merge([$id], array_keys(TranslationSync::translationsOf($id))) as $record) {
            update_post_meta($record, '_thumbnail_id', $ids[0]);
            update_post_meta($record, '_product_image_gallery', implode(',', array_slice($ids, 1)));
            wc_delete_product_transients($record);
        }

        return true;
    }
}

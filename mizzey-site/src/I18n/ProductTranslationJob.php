<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\I18n;

defined('ABSPATH') || exit;

/**
 * Saves a product translation through WooCommerce Multilingual's own product editor, from code.
 *
 * The editor's save expects what its screen always supplies: the posted form (`$_POST['data']`, holding the job id and
 * the fields). Called without it, it still saves, but logs undefined-key warnings and a null-argument deprecation on
 * every product. This supplies the same form, with the product's existing translation job when there is one (never
 * creating one), and puts the request back as it was afterwards.
 *
 * Used by the catalogue load (specs/046) and by the scenarios that exercise the editor as an entry point.
 *
 * See specs/048-clean-log.
 */
final class ProductTranslationJob
{
    /** The posted keys the editor's save reads or writes; restored after each save. */
    private const POSTED = ['data', 'to_lang', 'icl_post_language', 'new_title', 'new_slug'];

    /**
     * @param array<string,string> $fields 'title', 'slug', 'product_content', 'product_excerpt'.
     *
     * @return int The translated product's id.
     */
    public static function save(int $originalId, string $language, array $fields): int
    {
        global $sitepress, $woocommerce_wpml, $wpdb;
        if (! class_exists('\WCML_Editor_UI_Product_Job') || ! function_exists('wpml_tm_load_job_factory') || ! $sitepress || ! $woocommerce_wpml) {
            throw new \RuntimeException(__('WooCommerce Multilingual is not active.', 'mizzey-site'));
        }
        // An existing job for this product and language is reused, and none is created: creating one through WPML
        // sends the job (cancelling one a translator has open, and notifying translators). With none, the editor's
        // save records the translation itself, as it does when no job exists.
        $trid  = (int) apply_filters('wpml_element_trid', null, $originalId, 'post_product');
        $jobId = $trid > 0 ? (int) wpml_tm_load_job_factory()->job_id_by_trid_and_lang($trid, $language) : 0;

        $translations = [];
        $form         = [];
        foreach ($fields as $key => $value) {
            $translations[md5($key)] = (string) $value;
            $form[$key]              = ['data' => (string) $value];
        }

        $saved = [];
        foreach (self::POSTED as $key) {
            $saved[$key] = array_key_exists($key, $_POST) ? $_POST[$key] : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        }
        $_POST['data'] = http_build_query(['job_id' => $jobId, 'fields' => $form]);
        try {
            $job = new \WCML_Editor_UI_Product_Job(['job_id' => $originalId, 'target' => $language], $woocommerce_wpml, $sitepress, $wpdb);
            $job->save_translations($translations);
        } finally {
            foreach ($saved as $key => $value) {
                if ($value === null) {
                    unset($_POST[$key]);
                } else {
                    $_POST[$key] = $value;
                }
            }
            self::removeSaveFilters();
        }

        $translated = (int) apply_filters('wpml_object_id', $originalId, 'product', false, $language);
        if ($translated > 0 && $translated !== $originalId) {
            self::ensureLinked($originalId, $translated, $language);
        }

        return $translated;
    }

    /**
     * The editor's save registers `WCML_Editor_Save_Filters`, which set the translation group and language of every
     * post saved afterwards, and never removes them: on the editor's screen the request ends there, but here the next
     * product made in the same request would be stored as this translation, taking over its language row (A11). They
     * are removed once the save is done.
     */
    private static function removeSaveFilters(): void
    {
        global $wp_filter;
        foreach (['wpml_tm_save_post_trid_value', 'wpml_tm_save_post_lang_value', 'wpml_save_post_trid_value', 'wpml_save_post_lang'] as $hook) {
            if (! isset($wp_filter[$hook])) {
                continue;
            }
            foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    $fn = $callback['function'];
                    if (is_array($fn) && $fn[0] instanceof \WCML_Editor_Save_Filters) {
                        remove_filter($hook, $fn, (int) $priority);
                    }
                }
            }
        }
    }

    /**
     * The translation's own language row in the database, in the original's translation group. The editor's save can
     * leave a new translation without one (seen when the product was saved from its screen earlier in the request),
     * while WPML's in-memory cache still answers the link; the row is then written, as WPML's own API writes it.
     */
    private static function ensureLinked(int $originalId, int $translatedId, string $language): void
    {
        global $wpdb;
        $table    = $wpdb->prefix . 'icl_translations';
        $original = $wpdb->get_row($wpdb->prepare("SELECT trid, language_code FROM {$table} WHERE element_id = %d AND element_type = %s", $originalId, 'post_product')); // phpcs:ignore WordPress.DB
        if (! $original || (int) $original->trid <= 0) {
            return;
        }
        $row = $wpdb->get_row($wpdb->prepare("SELECT trid, language_code FROM {$table} WHERE element_id = %d AND element_type = %s", $translatedId, 'post_product')); // phpcs:ignore WordPress.DB
        if ($row && (int) $row->trid === (int) $original->trid && (string) $row->language_code === $language) {
            return;
        }
        do_action('wpml_set_element_language_details', [
            'element_id'           => $translatedId,
            'element_type'         => 'post_product',
            'trid'                 => (int) $original->trid,
            'language_code'        => $language,
            'source_language_code' => (string) $original->language_code,
        ]);
        do_action('wpml_cache_clear');
    }
}

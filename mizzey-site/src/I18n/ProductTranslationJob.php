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
        }

        return (int) apply_filters('wpml_object_id', $originalId, 'product', false, $language);
    }
}

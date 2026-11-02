<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\I18n\Cli;

defined('ABSPATH') || exit;

use MizzeySite\I18n\LanguageSetup;
use WP_CLI;

/**
 * Makes the records and settings the Arabic storefront needs.
 *
 * Register ids: IA-01 to IA-35, FIX-04. See specs/003-information-architecture-urls, FR-016.
 */
final class LanguagesCommand
{
    /**
     * Makes, where missing, the Arabic records of the WooCommerce pages and the contracted pages, the front and terms
     * pages, the brand and collection taxonomies as translatable, and the Arabic endpoint slugs.
     *
     * Safe to run again: anything already there is kept, an endpoint wording already translated included. Run at
     * every release, after the plugin is updated (docs/release-pipeline.md). Exits 1 naming anything missing: a
     * WooCommerce page not assigned, a page that could not be made, a slug held only by an Arabic page, a taxonomy or
     * an endpoint.
     *
     * ## OPTIONS
     *
     * [--publish]
     * : Publish the pages this command makes, and its own earlier drafts (an Arabic one only when its English page is
     * published). A page staff made, or whose status staff set, is never changed. Without it a new page is a draft, so
     * no empty page goes live before its copy is written.
     *
     * [--format=<format>]
     * : json for the full report.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     * ---
     *
     * ## EXAMPLES
     *
     *     wp mizzey languages provision
     *     wp mizzey languages provision --publish
     *
     * @param list<string>          $args
     * @param array<string, string> $assoc
     */
    public function provision(array $args, array $assoc): void
    {
        if (! LanguageSetup::ready()) {
            WP_CLI::error(__('WPML is not set up with Arabic as an active language.', 'mizzey-site'));
        }
        $report = LanguageSetup::provision(isset($assoc['publish']));
        if (($assoc['format'] ?? 'table') === 'json') {
            WP_CLI::line((string) wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            /* translators: 1: English records, 2: Arabic records. */
            $counts = __('%1$d English, %2$d Arabic', 'mizzey-site');
            $rows   = [
                ['step' => __('System pages', 'mizzey-site'), 'result' => sprintf($counts, count($report['system_pages']['en']), count($report['system_pages']['ar']))],
                ['step' => __('Contracted pages', 'mizzey-site'), 'result' => sprintf($counts, count($report['pages']['en']), count($report['pages']['ar']))],
                ['step' => __('Drafts', 'mizzey-site'), 'result' => implode(', ', $report['pages']['drafts'])],
                ['step' => __('Front page', 'mizzey-site'), 'result' => $report['front_page']['show_on_front'] . ' ' . $report['front_page']['page_on_front']],
                ['step' => __('Terms page', 'mizzey-site'), 'result' => (string) $report['terms_page']['page']],
                ['step' => __('Taxonomies', 'mizzey-site'), 'result' => (string) wp_json_encode($report['taxonomies'])],
                [
                    'step'   => __('Endpoints', 'mizzey-site'),
                    'result' => sprintf(
                        /* translators: 1: slugs made, 2: slugs kept, 3: endpoints that could not be translated. */
                        __('%1$d made, %2$d kept, missing: %3$s', 'mizzey-site'),
                        count($report['endpoints']['made']),
                        count($report['endpoints']['kept']),
                        implode(', ', $report['endpoints']['missing']),
                    ),
                ],
            ];
            WP_CLI\Utils\format_items('table', $rows, ['step', 'result']);
        }
        $problems = LanguageSetup::problems($report);
        if ($problems !== []) {
            WP_CLI::error(sprintf(
                /* translators: %s: what could not be made, comma separated. */
                __('Not everything is in place: %s', 'mizzey-site'),
                implode(', ', $problems),
            ));
        }
        WP_CLI::success(__('The Arabic storefront records and settings are in place.', 'mizzey-site'));
    }
}

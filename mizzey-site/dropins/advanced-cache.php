<?php
/**
 * Mizzey: the storefront's page cache (NFR-02). The release places this file at wp-content/advanced-cache.php, and
 * WordPress loads it, before anything else, when wp-config sets `define( 'WP_CACHE', true );`.
 *
 * It answers a guest's page from Redis when a stored copy exists (MizzeySite\Performance\PageCache), and otherwise
 * lets WordPress build the page and keeps it. With no Redis host, with `MIZZEY_PAGE_CACHE=off`, or with the plugin's
 * files missing, it does nothing and WordPress answers every request as before.
 *
 * @package MizzeySite
 */

defined('ABSPATH') || exit;

(static function (): void {
    $src   = WP_CONTENT_DIR . '/plugins/mizzey-site/src/';
    $files = ['Performance/RedisFailure.php', 'Performance/RedisSettings.php', 'Performance/RedisLink.php',
        'Performance/PageCacheRequest.php', 'Performance/PageCache.php', 'I18n/LanguagePreference.php'];
    foreach ($files as $file) {
        if (! is_readable($src . $file)) {
            return;
        }
    }
    foreach ($files as $file) {
        require_once $src . $file;
    }
    MizzeySite\Performance\PageCache::start();
})();

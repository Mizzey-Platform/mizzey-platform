<?php
/**
 * Mizzey: the persistent object cache (NFR-02). The release places this file at wp-content/object-cache.php.
 *
 * It loads the Redis Object Cache drop-in kept beside it in the mizzey-site plugin (redis-cache/object-cache.php,
 * unchanged, see README.md there) after setting it up from the environment (MizzeySite\Performance\RedisSettings):
 * the host, port and password the hosting stack gives the PHP container, this site's database index and key prefix,
 * the request-only groups, and failing safe. With no Redis host, with `MIZZEY_OBJECT_CACHE=off`, without the PhpRedis
 * extension, or with the plugin's files missing, it loads nothing and WordPress keeps its own request-only cache.
 *
 * @package MizzeySite
 */

defined('ABSPATH') || exit;

if (
    is_readable(WP_CONTENT_DIR . '/plugins/mizzey-site/src/Performance/RedisSettings.php')
    && is_readable(WP_CONTENT_DIR . '/plugins/mizzey-site/dropins/redis-cache/object-cache.php')
) {
    require_once WP_CONTENT_DIR . '/plugins/mizzey-site/src/Performance/RedisSettings.php';
    if (MizzeySite\Performance\RedisSettings::prepareObjectCache()) {
        require_once WP_CONTENT_DIR . '/plugins/mizzey-site/dropins/redis-cache/object-cache.php';
    }
}

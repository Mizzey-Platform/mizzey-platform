# The cache drop-ins (NFR-02)

WordPress loads two files from `wp-content/`, before any plugin, when they exist. The release places the two files of
this folder there (`tools/release_pack.py`); they load everything else from this plugin.

| In this folder | In the release | What it is |
|---|---|---|
| `object-cache.php` | `wp-content/object-cache.php` | The persistent object cache: sets up and loads the Redis Object Cache drop-in below |
| `advanced-cache.php` | `wp-content/advanced-cache.php` | The storefront's page cache: `MizzeySite\Performance\PageCache` |
| `redis-cache/object-cache.php` | stays here | The Redis Object Cache drop-in, unchanged (see below) |

Both do nothing, and WordPress answers every request as before, when no Redis host is configured, when the cache is
switched off, or when this plugin's files are missing. Neither ever stops a request: with Redis unreachable or slow,
the request is answered without the cache, and that is logged once a minute at most, naming no address. The object
cache asks Redis for a connection first (50 ms); when none comes it does not load the drop-in at all, so WordPress
keeps its transients in the database for that request instead of the drop-in's request-only fallback. The store's
security counters (password recovery, home sign-up) are kept in the database whatever the cache does
(`src/Security/WindowCounter.php`).

## On the server

- **Redis.** From the PHP container's environment: `REDIS_HOST`, `REDIS_PASSWORD`, and optionally `REDIS_PORT` (6379)
  and `REDIS_DATABASE` (0). PHP-FPM must pass them to PHP (`clear_env = no`, or an `env[REDIS_HOST]` line per variable in
  the pool), so that `getenv('REDIS_HOST')` returns them. Constants of the same names with the `WP_REDIS_` prefix, set
  in wp-config, win over the environment.
- **One key space per site.** Every key starts with `WP_REDIS_PREFIX`, else `WP_CACHE_KEY_SALT`, else a prefix made
  from the database name and table prefix. Staging and production on one Redis also take different `REDIS_DATABASE`
  values.
- **The page cache** loads only when wp-config has `define( 'WP_CACHE', true );` above the line that loads
  `wp-settings.php`.
- **Switches.** `MIZZEY_OBJECT_CACHE=off` or `MIZZEY_PAGE_CACHE=off` (environment or constant) turns either off.
- **The object cache needs the PhpRedis extension.** Without it the drop-in is not loaded.

## The page cache in short

A guest's GET or HEAD of a storefront page, carrying only cookies that change nothing on the page (Cloudflare's,
consent, analytics, WPML's language cookie, the chosen language when it is the page's) and no query string other than
campaign parameters, is answered from Redis when a copy is stored: `X-Mizzey-Cache: hit`. Everything else is answered
by WordPress: `X-Mizzey-Cache: miss`, with `X-Mizzey-Cache-Skip` naming why the cache was not asked. A page is kept for
five minutes, only when it is a complete 200 HTML page that sets no cookie, built for a guest, and not a search, a feed,
a preview, a 404, the cart, the checkout or the account. Any change to products, prices, stock, terms, menus, pages or
settings drops every stored page in both languages. The rules are in `src/Performance/PageCacheRequest.php` and
`src/Performance/PageCache.php`; the proof is `tests/integration/t121-page-cache.php`.

## The vendored drop-in

`redis-cache/object-cache.php` is the `includes/object-cache.php` of **Redis Object Cache 3.0.1** by Till Krüss
(Rhubarb Group), from `https://downloads.wordpress.org/plugin/redis-cache.latest-stable.zip`, downloaded on
25 November 2026 (archive sha256 `8de85553394c4462dd43d38a403b805e8fb404d7dc34e02ecd313ea37002196b`; this file's
sha256 `e8a96cd076d53cb8368ef6eea6004da20058aaa0073d3135e55e2aa992c2c62c`). It is unchanged. It is free software under
the GNU General Public License version 3, whose text is `redis-cache/LICENSE.md`, copied from the same archive. Its
upstream is `https://github.com/rhubarbgroup/redis-cache`. Only the drop-in is used: the plugin itself (its settings
screen, Predis and the rest) is not installed. To upgrade, replace the file with the same file of a newer release,
read its changes, record its version and checksums here, and run t121 on staging.

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Performance;

defined('ABSPATH') || exit;

use MizzeySite\I18n\LanguagePreference;

/**
 * The storefront's full-page cache (NFR-02): a stored copy of a page, in Redis, for a visitor without an account, a
 * cart or a list, so WordPress is not run again for the same page within five minutes.
 *
 * - **Answering.** `start()`, from wp-content/advanced-cache.php (loaded by WordPress when wp-config sets `WP_CACHE`
 *   to true, before anything else), asks `PageCacheRequest` whether the request may be answered from the cache; if a
 *   stored copy of the current generation exists, it is sent with `X-Mizzey-Cache: hit` and WordPress does not run.
 *   Every other response carries `X-Mizzey-Cache: miss` (with `X-Mizzey-Cache-Skip` naming why it was not looked up).
 * - **Storing.** On a miss WordPress builds the page as always, and the page is kept only when WordPress's own checks
 *   agree (`approve()`: nobody is signed in; not a search, a feed, a preview, a 404, the cart, the checkout, the account
 *   or a password-protected post), nothing on the page depends on the visitor (`doNotStore()`, WooCommerce's
 *   `DONOTCACHEPAGE`), the answer is a complete 200 HTML page that sets no cookie and asks not to be cached nowhere,
 *   and it is in the language of its address. It lives five minutes.
 * - **What a stored page holds.** The HTML and the headers a browser needs, never a cookie. Consent and the chosen
 *   language are decided in the browser on every page (`ConsentBanner`, `LanguagePreference::cachedPageScript()`),
 *   and the cart, the wishlist and an account are never on a stored page because their visitors are never sent one.
 * - **Purging.** `PageCachePurge` replaces the generation on any change to products, prices, stock, terms, menus,
 *   settings or pages (the home sections are the home page's blocks), so every stored page of both languages is
 *   dropped at once. A page built while the change was being saved carries the old generation and is never sent.
 * - **Failing safe.** Redis unreachable, slow or refusing: the request is answered by WordPress, uncached, and nothing
 *   is stored. Never an error page.
 */
final class PageCache
{
    public const TTL         = 300;
    public const HEADER      = 'X-Mizzey-Cache';
    public const SKIP_HEADER = 'X-Mizzey-Cache-Skip';
    public const GENERATION  = 'page-generation';
    public const HIT         = 'hit';
    public const MISS        = 'miss';
    /** A page larger than this is not kept. */
    private const MAX_BYTES = 4194304;
    /** Headers a stored page never replays: the cookie, the connection's own, and the cache's. */
    private const NOT_REPLAYED = ['set-cookie', 'date', 'server', 'x-powered-by', 'content-length', 'content-encoding',
        'transfer-encoding', 'connection', 'keep-alive', 'age', 'x-mizzey-cache', 'x-mizzey-cache-skip'];

    private static ?self $current = null;

    private string $buffer = '';
    private bool $approved = false;
    private ?string $vetoed = null;

    private function __construct(
        private readonly RedisSettings $settings,
        private readonly PageCacheRequest $request,
        private readonly RedisLink $link,
        private readonly string $generation,
    ) {
    }

    /** Called by wp-content/advanced-cache.php: answers from the cache, or starts keeping the page WordPress builds. */
    public static function start(): void
    {
        $settings = RedisSettings::fromEnvironment();
        if ($settings === null || RedisSettings::switchedOff('MIZZEY_PAGE_CACHE') || PHP_SAPI === 'cli') {
            return;
        }
        $request = PageCacheRequest::from($_SERVER, $_COOKIE, LanguagePreference::LANGUAGES, function_exists('is_ssl') && is_ssl());
        header(self::HEADER . ': ' . self::MISS);
        if (! $request->lookup) {
            header(self::SKIP_HEADER . ': ' . $request->reason);

            return;
        }
        try {
            $link = RedisLink::open($settings);
            [$entry, $generation] = $link->command('MGET', $settings->prefix . $request->key(), $settings->prefix . self::GENERATION);
            if (! is_string($generation) || $generation === '') {
                $link->command('SET', $settings->prefix . self::GENERATION, self::newGeneration(), 'NX');
                $generation = (string) $link->command('GET', $settings->prefix . self::GENERATION);
            }
            $page = is_string($entry) ? self::decode($entry) : null;
            if ($page !== null && $page['generation'] === $generation) {
                $link->close();
                self::serve($page, $request->head);
            }
        } catch (RedisFailure $failure) {
            header(self::SKIP_HEADER . ': ' . 'redis');
            self::note($failure);

            return;
        }
        if (! $request->store) {
            $link->close();
            header(self::SKIP_HEADER . ': ' . ($request->head ? 'head' : 'query'));

            return;
        }
        self::$current = new self($settings, $request, $link, $generation);
        ob_start([self::$current, 'capture']);
    }

    /** Whether this request may store its page so far: a page cache is waiting for it, and nothing has refused. */
    public static function active(): bool
    {
        return self::$current !== null && self::$current->vetoed === null;
    }

    /**
     * Something on this page depends on the visitor: the page is sent to them and not kept. Code that prints a
     * visitor's own state for a guest (their recently viewed products, for instance) calls this.
     */
    public static function doNotStore(string $why): void
    {
        if (self::$current !== null && self::$current->vetoed === null) {
            self::$current->vetoed = $why;
        }
    }

    public static function register(): void
    {
        add_action('template_redirect', [self::class, 'approve'], PHP_INT_MAX);
    }

    /** WordPress's own checks, once it knows what the page is: only a storefront page a guest would be sent. */
    public static function approve(): void
    {
        if (self::$current === null) {
            return;
        }
        $shop = function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page() || is_wc_endpoint_url());
        $why  = match (true) {
            is_user_logged_in()                                     => 'signed-in',
            is_admin() || wp_doing_ajax() || wp_doing_cron()        => 'not-a-page',
            defined('REST_REQUEST') && REST_REQUEST                 => 'not-a-page',
            is_search()                                             => 'search',
            is_feed() || is_robots() || is_trackback()              => 'not-a-page',
            is_preview() || is_customize_preview()                  => 'preview',
            is_404()                                                => 'not-found',
            $shop                                                   => 'shop-step',
            is_singular() && post_password_required()               => 'password',
            default                                                 => null,
        };
        if ($why === null) {
            self::$current->approved = true;
        } else {
            self::doNotStore($why);
        }
    }

    /** The output handler: passes the page through unchanged, keeping a copy, and stores it at the end. */
    public function capture(string $chunk, int $phase): string
    {
        if (strlen($this->buffer) <= self::MAX_BYTES) {
            $this->buffer .= $chunk;
        }
        if (($phase & PHP_OUTPUT_HANDLER_FINAL) !== 0) {
            try {
                $why = $this->whyNotStore();
                if ($why === null) {
                    $this->link->command('SET', $this->settings->prefix . $this->request->key(), $this->encode(), 'EX', (string) self::TTL);
                }
            } catch (RedisFailure $failure) {
                self::note($failure);
            }
            $this->link->close();
            $this->buffer = '';
            self::$current = null;
        }

        return $chunk;
    }

    /** Why the page just built is not kept, or null when it is. */
    private function whyNotStore(): ?string
    {
        if ($this->vetoed !== null) {
            return $this->vetoed;
        }
        if (! $this->approved) {
            return 'not-approved';
        }
        if (defined('DONOTCACHEPAGE') && DONOTCACHEPAGE) {
            return 'donotcachepage';
        }
        if (function_exists('is_user_logged_in') && is_user_logged_in()) {
            return 'signed-in';
        }
        if (http_response_code() !== 200) {
            return 'status';
        }
        $type = '';
        foreach (headers_list() as $line) {
            [$name, $value] = array_map('trim', explode(':', $line, 2) + [1 => '']);
            $name = strtolower($name);
            if ($name === 'set-cookie') {
                return 'sets-cookie';
            }
            if ($name === 'cache-control' && preg_match('/\b(private|no-store|no-cache)\b/i', $value) === 1) {
                return 'no-cache';
            }
            if ($name === 'location') {
                return 'redirect';
            }
            if ($name === 'content-type') {
                $type = strtolower($value);
            }
        }
        if ($type !== '' && ! str_starts_with($type, 'text/html')) {
            return 'not-html';
        }
        if (strlen($this->buffer) > self::MAX_BYTES || stripos($this->buffer, '</html>') === false) {
            return 'incomplete';
        }
        $language = function_exists('apply_filters') ? apply_filters('wpml_current_language', null) : null;
        if (is_string($language) && $language !== '' && $language !== $this->request->language) {
            return 'language';
        }

        return null;
    }

    private function encode(): string
    {
        $headers = [];
        foreach (headers_list() as $line) {
            $name = strtolower(trim(explode(':', $line, 2)[0]));
            if (! in_array($name, self::NOT_REPLAYED, true)) {
                $headers[] = $line;
            }
        }
        $body = $this->buffer;
        $gz   = function_exists('gzdeflate');
        $meta = ['v' => 1, 'generation' => $this->generation, 'created' => time(), 'language' => $this->request->language, 'headers' => $headers, 'gz' => $gz];

        return json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n" . ($gz ? (string) gzdeflate($body, 6) : $body);
    }

    /** @return array{generation:string,created:int,headers:list<string>,body:string}|null */
    private static function decode(string $entry): ?array
    {
        $cut  = strpos($entry, "\n");
        $meta = $cut === false ? null : json_decode(substr($entry, 0, $cut), true);
        if (! is_array($meta) || ($meta['v'] ?? 0) !== 1 || ! is_string($meta['generation'] ?? null) || ! is_array($meta['headers'] ?? null)) {
            return null;
        }
        $body = substr($entry, $cut + 1);
        if (! empty($meta['gz'])) {
            $body = function_exists('gzinflate') ? @gzinflate($body) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
            if (! is_string($body)) {
                return null;
            }
        }

        return ['generation' => $meta['generation'], 'created' => (int) ($meta['created'] ?? 0), 'headers' => array_values(array_filter($meta['headers'], 'is_string')), 'body' => $body];
    }

    /** @param array{generation:string,created:int,headers:list<string>,body:string} $page */
    private static function serve(array $page, bool $head): never
    {
        $seen = [];
        foreach ($page['headers'] as $line) {
            $name = strtolower(trim(explode(':', $line, 2)[0]));
            if ($name === '' || in_array($name, self::NOT_REPLAYED, true)) {
                continue;
            }
            header($line, ! isset($seen[$name]));
            $seen[$name] = true;
        }
        header(self::HEADER . ': ' . self::HIT);
        header('Age' . ': ' . max(0, time() - $page['created']));
        if (! $head) {
            echo $page['body']; // phpcs:ignore WordPress.Security.EscapeOutput -- the page WordPress built and escaped.
        }
        exit;
    }

    /** A failure, logged: an unreachable Redis once a minute at most (RedisSettings::noteUnreachable()), anything else as it is. */
    public static function note(RedisFailure $failure): void
    {
        if ($failure->getMessage() === (new RedisFailure('unreachable'))->getMessage()) {
            RedisSettings::noteUnreachable();

            return;
        }
        error_log('Mizzey page cache: ' . $failure->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
    }

    /** A new generation: every page stored before it is dropped. */
    public static function newGeneration(): string
    {
        return bin2hex(random_bytes(8));
    }
}

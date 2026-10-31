<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Reviews;

defined('ABSPATH') || exit;

/**
 * Photos in reviews, a recorded gift (D-23, not REV-06): up to 5 images per review, no video, moderated with it.
 *
 * - The reviewer attaches them to their own review while it is held for moderation (every review is, specs/020):
 *   `POST mizzey/v1/reviews/{id}/photos`, files `photos[]` and optional `alt[]`, signed in, with WordPress's REST nonce.
 *   Uploads to one review are taken one at a time, under a lock, and only while the review is still held.
 * - Each file is checked by its content, not its name: JPEG, PNG or WebP only, at most 5 MB, 6000 pixels a side and
 *   12 million pixels, and only when the memory to decode it is there; then decoded and encoded again, at most 2000
 *   pixels a side, which drops its location, camera and colour profile data and anything that is not the picture.
 * - Stored under a random name, served only through the site and never cached. The folder is
 *   `MIZZEY_REVIEW_PHOTOS_DIR` from the configuration when set (outside the site's public folder), else
 *   `uploads/mizzey-review-photos/` with an `.htaccess` refusal, used only once a request to a file in it is seen
 *   refused (checked again daily); until then no photo is accepted.
 * - Approving a review records the photos it has at that moment; a photo is shown, and served, only while its review
 *   is approved and only if it is one of those, so a photo that arrives as a moderator approves waits for the next
 *   approval. An upload checks, after it is saved and still under the lock, that its review is still held, and takes
 *   its photos back otherwise, so an approval that lands while it runs records the review without them. Staff who
 *   moderate see every photo, in the admin's list of comments too. A review deleted takes its files with it (the
 *   deletion holds the review's lock until it is done); a stored photo no review refers to, after a deletion that could
 *   not take the lock or any failure, is swept away (`sweep()`, after such a deletion and daily).
 * - Each photo has alternative text: the reviewer's, or a description naming the product.
 *
 * The designed upload form and gallery are PRE-03b's; the approved review's photos are listed under its text.
 *
 * See specs/025-review-photos.
 */
final class ReviewPhotos
{
    public const META       = '_mizzey_review_photos';
    public const APPROVED   = '_mizzey_review_approved_photos';
    public const STORAGE    = 'mizzey_review_photos_storage';
    public const QUERY      = 'mizzey-review-photo';
    public const MAX        = 5;
    public const MAX_BYTES  = 5 * 1024 * 1024;
    public const MAX_SIDE   = 6000;
    public const MAX_PIXELS = 12000000;
    public const KEEP_SIDE  = 2000;
    private const TYPES     = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'routes']);
        add_action('template_redirect', [self::class, 'serve'], 1);
        add_action('woocommerce_review_after_comment_text', [self::class, 'show'], 20, 1);
        add_filter('comment_text', [self::class, 'adminList'], 20, 2);
        add_action('transition_comment_status', [self::class, 'approved'], 10, 3);
        add_action('delete_comment', [self::class, 'forget'], 10, 1);
        add_action('deleted_comment', [self::class, 'deleted'], 10, 1);
        add_action(self::SWEEP, [self::class, 'sweep']);
        add_action('init', [self::class, 'schedule']);
    }

    public const SWEEP = 'mizzey_review_photos_sweep';

    public static function schedule(): void
    {
        if (! wp_next_scheduled(self::SWEEP)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::SWEEP);
        }
    }

    /** Whether the review being deleted could not be locked, so its files are swept after the deletion. */
    private static bool $sweepAfter = false;

    /** The review being deleted, whose lock is held until the deletion is done. */
    private static ?string $deleting = null;

    private static function lockName(int $id): string
    {
        global $wpdb;

        return substr('mz_' . $wpdb->prefix . 'review_photos_' . $id, 0, 64);
    }

    public static function routes(): void
    {
        register_rest_route('mizzey/v1', '/reviews/(?P<id>\d+)/photos', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'upload'],
            'permission_callback' => [self::class, 'mayAttach'],
        ]);
    }

    /** @param mixed $request */
    public static function mayAttach($request): bool
    {
        $comment = $request instanceof \WP_REST_Request ? get_comment((int) $request['id']) : null;
        $user    = get_current_user_id();

        return $comment instanceof \WP_Comment && $user > 0 && (int) $comment->user_id === $user
            && get_post_type((int) $comment->comment_post_ID) === 'product' && (string) $comment->comment_approved === '0';
    }

    /** @param mixed $request */
    public static function upload($request)
    {
        global $wpdb;
        $id    = (int) $request['id'];
        $files = self::files((array) ($request->get_file_params()['photos'] ?? []));
        $alts  = array_values((array) ($request->get_param('alt') ?? []));
        if ($files === []) {
            return new \WP_Error('mizzey_photo_none', __('Please choose a photo.', 'mizzey-site'), ['status' => 400]);
        }
        if (! self::storageReady()) {
            return new \WP_Error('mizzey_photo_storage', __('Photos cannot be added right now. Please try again later.', 'mizzey-site'), ['status' => 503]);
        }
        $lock = self::lockName($id);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $lock)) !== 1) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            return new \WP_Error('mizzey_photo_busy', __('Please try again in a moment.', 'mizzey-site'), ['status' => 409]);
        }
        $made = [];
        try {
            wp_cache_delete($id, 'comment');
            clean_comment_cache($id);
            $photos = self::photos($id);
            if (count($photos) + count($files) > self::MAX) {
                /* translators: %d: the most photos a review may have. */
                return new \WP_Error('mizzey_photo_many', sprintf(__('A review can have up to %d photos.', 'mizzey-site'), self::MAX), ['status' => 400]);
            }
            $productId = (int) get_comment($id)->comment_post_ID;
            foreach ($files as $index => $file) {
                $photo = self::store($file, self::alt($alts[$index] ?? '', $productId));
                if (is_wp_error($photo)) {
                    self::remove($made);

                    return $photo;
                }
                $made[] = $photo;
            }
            $all   = array_merge($photos, $made);
            $saved = update_comment_meta($id, self::META, $all) || self::photos($id) === $all;
            // Still held, read from the database after the save: an approval or a deletion that landed while this ran
            // takes none of them (the approval waits on this lock, then records the review without them).
            $status = (string) $wpdb->get_var($wpdb->prepare("SELECT comment_approved FROM {$wpdb->comments} WHERE comment_ID = %d", $id)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            if (! $saved || $status !== '0') {
                if ($saved) {
                    update_comment_meta($id, self::META, $photos);
                }
                self::remove($made);

                return $saved
                    ? new \WP_Error('rest_forbidden', __('Photos can be added only while the review waits for moderation.', 'mizzey-site'), ['status' => 403])
                    : new \WP_Error('mizzey_photo_refused', __('This photo could not be used. Please send a JPEG, PNG or WebP image under 5 MB.', 'mizzey-site'), ['status' => 500]);
            }

            return new \WP_REST_Response(['photos' => count($all)], 201);
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }
    }

    /**
     * The photo checked, cleaned and stored, or why not.
     *
     * @param array{tmp_name:string,size:int,error:int} $file
     * @return array{file:string,alt:string,width:int,height:int}|\WP_Error
     */
    private static function store(array $file, string $alt)
    {
        $refused = new \WP_Error('mizzey_photo_refused', __('This photo could not be used. Please send a JPEG, PNG or WebP image under 5 MB.', 'mizzey-site'), ['status' => 400]);
        $path    = self::uploaded((string) $file['tmp_name']);
        if ((int) $file['error'] !== UPLOAD_ERR_OK || $path === null || filesize($path) > self::MAX_BYTES) {
            return $refused;
        }
        $bytes = (string) file_get_contents($path); // phpcs:ignore WordPress.WP.AlternativeFunctions
        $info  = $bytes !== '' ? getimagesizefromstring($bytes) : false;
        if (! is_array($info) || ! isset(self::TYPES[$info[2]]) || $info[0] < 1 || $info[1] < 1 || $info[0] > self::MAX_SIDE || $info[1] > self::MAX_SIDE
            || $info[0] * $info[1] > self::MAX_PIXELS || ! self::memoryFor($info[0], $info[1])) {
            return $refused;
        }
        $image = imagecreatefromstring($bytes);
        if ($image === false) {
            return $refused;
        }
        $scale = min(1, self::KEEP_SIDE / max($info[0], $info[1]));
        if ($scale < 1) {
            $smaller = imagescale($image, max(1, (int) round($info[0] * $scale)), max(1, (int) round($info[1] * $scale)));
            imagedestroy($image);
            if ($smaller === false) {
                return $refused;
            }
            $image = $smaller;
        }
        $ext = self::TYPES[$info[2]];
        if ($ext !== 'jpg') {
            imagesavealpha($image, true);
        }
        ob_start();
        $encoded = $ext === 'jpg' ? imagejpeg($image, null, 85) : ($ext === 'png' ? imagepng($image, null, 6) : imagewebp($image, null, 85));
        $clean   = (string) ob_get_clean();
        $width   = imagesx($image);
        $height  = imagesy($image);
        imagedestroy($image);
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        if (! $encoded || $clean === '' || file_put_contents(self::dir() . $name, $clean) === false) { // phpcs:ignore WordPress.WP.AlternativeFunctions
            return $refused;
        }

        return ['file' => $name, 'alt' => $alt, 'width' => $width, 'height' => $height];
    }

    /** Whether there is the memory to decode an image of this size, and a scaled copy of it. */
    private static function memoryFor(int $width, int $height): bool
    {
        wp_raise_memory_limit('image');
        $limit = wp_convert_hr_to_bytes((string) ini_get('memory_limit'));
        if ($limit <= 0) {
            return true;
        }
        // GD's truecolour image, the decoder's own row and palette buffers, and the scaled copy: a decoder can hold about
        // twice the image again while it works.
        $needed = (int) ($width * $height * 10 + self::KEEP_SIDE * self::KEEP_SIDE * 4 + 32 * 1024 * 1024);

        return $limit - memory_get_usage(true) > $needed;
    }

    /** @param list<array{file:string}> $photos */
    private static function remove(array $photos): void
    {
        foreach ($photos as $photo) {
            wp_delete_file(self::dir() . basename((string) $photo['file']));
        }
    }

    /** An uploaded file's path, only inside the server's temporary directory, where PHP puts uploads. */
    private static function uploaded(string $path): ?string
    {
        $real = $path !== '' ? realpath($path) : false;
        $temp = realpath((string) (ini_get('upload_tmp_dir') ?: sys_get_temp_dir()));
        $wp   = realpath(get_temp_dir());
        foreach (array_filter([$temp, $wp]) as $root) {
            if ($real !== false && is_file($real) && str_starts_with(wp_normalize_path($real), trailingslashit(wp_normalize_path($root)))) {
                return $real;
            }
        }

        return null;
    }

    /**
     * Each file of a multiple upload, in PHP's per-field shape.
     *
     * @param array<string,mixed> $field
     * @return list<array{tmp_name:string,size:int,error:int}>
     */
    private static function files(array $field): array
    {
        $names = (array) ($field['tmp_name'] ?? []);
        $out   = [];
        foreach ($names as $i => $tmp) {
            if ((string) $tmp !== '') {
                $out[] = ['tmp_name' => (string) $tmp, 'size' => (int) (((array) ($field['size'] ?? []))[$i] ?? 0), 'error' => (int) (((array) ($field['error'] ?? []))[$i] ?? UPLOAD_ERR_NO_FILE)];
            }
        }

        return $out;
    }

    private static function alt(mixed $given, int $productId): string
    {
        $text = trim(sanitize_text_field(is_scalar($given) ? (string) $given : ''));
        if ($text !== '') {
            return mb_substr($text, 0, 125);
        }
        /* translators: %s: the product's name. */
        return sprintf(__('A customer\'s photo of %s', 'mizzey-site'), get_the_title($productId));
    }

    /** @return list<array{file:string,alt:string,width:int,height:int}> */
    public static function photos(int $commentId): array
    {
        $photos = get_comment_meta($commentId, self::META, true);

        return is_array($photos) ? array_values($photos) : [];
    }

    /**
     * When a review is approved, the photos it has then are recorded, read under the review's lock: only those are shown.
     *
     * @param mixed $new
     * @param mixed $old
     * @param mixed $comment
     */
    public static function approved($new, $old, $comment): void
    {
        global $wpdb;
        if ($new !== 'approved' || ! $comment instanceof \WP_Comment || get_post_type((int) $comment->comment_post_ID) !== 'product') {
            return;
        }
        $id   = (int) $comment->comment_ID;
        $lock = self::lockName($id);
        $held = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $lock)) === 1; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        try {
            wp_cache_delete($id, 'comment_meta');
            // Without the lock, nothing is recorded: the photos stay hidden until the review is approved again.
            $files = $held ? array_map(static fn (array $photo): string => (string) $photo['file'], self::photos($id)) : [];
            update_comment_meta($id, self::APPROVED, $files);
        } finally {
            if ($held) {
                $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            }
        }
    }

    public static function url(int $commentId, int $index): string
    {
        return add_query_arg(self::QUERY, $commentId . '-' . $index, home_url('/'));
    }

    /**
     * Whether a photo may be seen by this visitor: staff who moderate see every photo; anyone else, only one its
     * review had when it was approved, while it is approved.
     *
     * @param array{file:string} $photo
     */
    public static function visible(\WP_Comment $comment, array $photo): bool
    {
        if (current_user_can('moderate_comments')) {
            return true;
        }
        $approved = get_comment_meta((int) $comment->comment_ID, self::APPROVED, true);

        return (string) $comment->comment_approved === '1' && is_array($approved) && in_array((string) $photo['file'], $approved, true);
    }

    public static function serve(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a public image, shown only for an approved review.
        if (! isset($_GET[self::QUERY])) {
            return;
        }
        $asked = sanitize_text_field(wp_unslash($_GET[self::QUERY])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        [$id, $index] = array_map('intval', explode('-', $asked . '-'));
        $comment = $id > 0 ? get_comment($id) : null;
        $photo   = $comment instanceof \WP_Comment ? (self::photos($id)[$index] ?? null) : null;
        $path    = is_array($photo) ? self::dir() . basename((string) $photo['file']) : '';
        nocache_headers();
        if (! $comment instanceof \WP_Comment || ! is_array($photo) || ! self::visible($comment, $photo) || ! is_file($path)) {
            status_header(404);
            exit;
        }
        $types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        header(sprintf('Content-Type:%s', $types[pathinfo($path, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
        header('X-Content-Type-Options:nosniff');
        header('Cache-Control:private,no-store,max-age=0');
        header(sprintf('Content-Length:%d', (int) filesize($path)));
        readfile($path); // phpcs:ignore WordPress.WP.AlternativeFunctions
        exit;
    }

    /** @param mixed $comment */
    public static function show($comment): void
    {
        if ($comment instanceof \WP_Comment) {
            echo self::markup($comment); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in markup().
        }
    }

    /**
     * The admin's list of comments: staff who moderate see a held review's photos beside it.
     *
     * @param mixed $text
     * @param mixed $comment
     * @return mixed
     */
    public static function adminList($text, $comment)
    {
        if (! is_admin() || ! is_string($text) || ! $comment instanceof \WP_Comment || ! current_user_can('moderate_comments')) {
            return $text;
        }

        return $text . self::markup($comment);
    }

    private static function markup(\WP_Comment $comment): string
    {
        $out = '';
        foreach (self::photos((int) $comment->comment_ID) as $index => $photo) {
            if (self::visible($comment, $photo)) {
                $out .= sprintf('<li><img src="%s" alt="%s" width="%d" height="%d" loading="lazy"></li>', esc_url(self::url((int) $comment->comment_ID, (int) $index)), esc_attr((string) $photo['alt']), (int) $photo['width'], (int) $photo['height']);
            }
        }

        return $out !== '' ? '<ul class="mizzey-review-photos">' . $out . '</ul>' : '';
    }

    /**
     * Before a review is deleted: its lock is taken, and held until the deletion is done (`deleted`), and its files go.
     *
     * @param mixed $commentId
     */
    public static function forget($commentId): void
    {
        global $wpdb;
        $id = (int) $commentId;
        if (self::$deleting === null && (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 30)', self::lockName($id))) === 1) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            self::$deleting = self::lockName($id);
        } else {
            // WordPress offers no way to stop the deletion: what an upload adds meanwhile is swept after it.
            self::$sweepAfter = true;
        }
        wp_cache_delete($id, 'comment_meta');
        self::remove(self::photos($id));
    }

    /** @param mixed $commentId */
    public static function deleted($commentId): void
    {
        global $wpdb;
        if (self::$deleting !== null && self::$deleting === self::lockName((int) $commentId)) {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', self::$deleting)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            self::$deleting = null;
        }
        if (self::$sweepAfter) {
            self::$sweepAfter = false;
            self::sweep();
        }
    }

    /**
     * Removes every stored photo no review refers to, once it is ten minutes old (a younger one may belong to an upload
     * still running). Answers how many went.
     */
    public static function sweep(): int
    {
        global $wpdb;
        $dir = self::dir();
        if (! is_dir($dir)) {
            return 0;
        }
        $kept = [];
        foreach ((array) $wpdb->get_col($wpdb->prepare("SELECT meta_value FROM {$wpdb->commentmeta} WHERE meta_key = %s", self::META)) as $value) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            foreach ((array) maybe_unserialize($value) as $photo) {
                if (is_array($photo) && isset($photo['file'])) {
                    $kept[(string) $photo['file']] = true;
                }
            }
        }
        if ($wpdb->last_error !== '') {
            return 0;
        }
        $gone = 0;
        foreach ((array) scandir($dir) as $name) {
            $path = $dir . $name;
            if (preg_match('/^[0-9a-f]{32}\.(jpg|png|webp)$/', (string) $name) === 1 && ! isset($kept[$name]) && is_file($path) && filemtime($path) < time() - 10 * MINUTE_IN_SECONDS) {
                wp_delete_file($path);
                $gone++;
            }
        }

        return $gone;
    }

    public static function dir(): string
    {
        if (defined('MIZZEY_REVIEW_PHOTOS_DIR') && is_string(constant('MIZZEY_REVIEW_PHOTOS_DIR')) && constant('MIZZEY_REVIEW_PHOTOS_DIR') !== '') {
            return trailingslashit((string) constant('MIZZEY_REVIEW_PHOTOS_DIR'));
        }

        return trailingslashit((string) wp_upload_dir(null, false)['basedir']) . 'mizzey-review-photos/';
    }

    /**
     * The folder exists, is writable, and cannot be read from the web: outside the site's public folder when set in
     * the configuration, or refused by the web server, as a request to a file in it shows (checked again daily).
     */
    public static function storageReady(): bool
    {
        $dir = self::dir();
        if (! wp_mkdir_p($dir) || ! is_writable($dir)) {
            return false;
        }
        if (! is_file($dir . 'index.php')) {
            file_put_contents($dir . 'index.php', "<?php\n"); // phpcs:ignore WordPress.WP.AlternativeFunctions
        }
        if (! str_starts_with(wp_normalize_path($dir), trailingslashit(wp_normalize_path(ABSPATH)))
            && ! str_starts_with(wp_normalize_path($dir), trailingslashit(wp_normalize_path(WP_CONTENT_DIR)))) {
            return true;
        }
        // The refusal as shipped, written again whenever it differs (an older one is replaced).
        $rule = dirname(__DIR__, 2) . '/assets/review-photos.htaccess';
        if ((! is_file($dir . '.htaccess') || md5_file($dir . '.htaccess') !== md5_file($rule)) && ! copy($rule, $dir . '.htaccess')) {
            return false;
        }
        $seen = get_option(self::STORAGE);
        if (is_array($seen) && ($seen['at'] ?? 0) > time() - DAY_IN_SECONDS) {
            return ! empty($seen['refused']);
        }
        // A probe file of each kind stored here; each must be refused on purpose (401, 403 or 404), never handed over,
        // redirected, or lost in a server error, for the folder to count as closed.
        $refused = true;
        foreach (['jpg', 'png', 'webp', 'txt'] as $ext) {
            $probe = 'probe-' . bin2hex(random_bytes(8)) . '.' . $ext;
            $mark  = bin2hex(random_bytes(16));
            if (file_put_contents($dir . $probe, $mark) === false) { // phpcs:ignore WordPress.WP.AlternativeFunctions
                $refused = false;
                break;
            }
            $answer = wp_remote_get(trailingslashit((string) wp_upload_dir(null, false)['baseurl']) . 'mizzey-review-photos/' . $probe, ['timeout' => 10, 'redirection' => 0, 'sslverify' => false]);
            wp_delete_file($dir . $probe);
            $code    = is_wp_error($answer) ? 0 : (int) wp_remote_retrieve_response_code($answer);
            $refused = in_array($code, [401, 403, 404], true) && ! str_contains((string) wp_remote_retrieve_body($answer), $mark);
            if (! $refused) {
                break;
            }
        }
        // A refusal is trusted for a day; anything else is asked again at the next upload.
        update_option(self::STORAGE, ['refused' => $refused, 'at' => $refused ? time() : 0], false);

        return $refused;
    }
}

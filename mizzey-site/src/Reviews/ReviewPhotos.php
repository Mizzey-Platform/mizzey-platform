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
 * - Each file is checked by its content, not its name: JPEG, PNG or WebP only, at most 5 MB and 6000 pixels a side;
 *   then decoded and encoded again, at most 2000 pixels a side, which drops its location and camera data and anything
 *   that is not the picture. Stored under a random name in `uploads/mizzey-review-photos/`, which the web server is
 *   told to refuse (`.htaccess`; on other servers, a hosting rule), and served only through the site.
 * - A photo is shown, and served, only while its review is approved; staff who moderate see it while it is held. A
 *   review deleted takes its files with it.
 * - Each photo has alternative text: the reviewer's, or a description naming the product.
 *
 * The designed upload form and gallery are PRE-03b's; the approved review's photos are listed under its text.
 *
 * See specs/025-review-photos.
 */
final class ReviewPhotos
{
    public const META      = '_mizzey_review_photos';
    public const QUERY     = 'mizzey-review-photo';
    public const MAX       = 5;
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const MAX_SIDE  = 6000;
    public const KEEP_SIDE = 2000;
    private const TYPES    = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'routes']);
        add_action('template_redirect', [self::class, 'serve'], 1);
        add_action('woocommerce_review_after_comment_text', [self::class, 'show'], 20, 1);
        add_action('delete_comment', [self::class, 'forget'], 10, 1);
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
        $comment = get_comment((int) $request['id']);
        $files   = self::files((array) ($request->get_file_params()['photos'] ?? []));
        $alts    = array_values((array) ($request->get_param('alt') ?? []));
        $photos  = self::photos((int) $comment->comment_ID);
        if ($files === []) {
            return new \WP_Error('mizzey_photo_none', __('Please choose a photo.', 'mizzey-site'), ['status' => 400]);
        }
        if (count($photos) + count($files) > self::MAX) {
            /* translators: %d: the most photos a review may have. */
            return new \WP_Error('mizzey_photo_many', sprintf(__('A review can have up to %d photos.', 'mizzey-site'), self::MAX), ['status' => 400]);
        }
        $made = [];
        foreach ($files as $index => $file) {
            $photo = self::store($file, self::alt($alts[$index] ?? '', (int) $comment->comment_post_ID));
            if (is_wp_error($photo)) {
                foreach ($made as $done) {
                    wp_delete_file(self::dir() . $done['file']);
                }

                return $photo;
            }
            $made[] = $photo;
        }
        update_comment_meta((int) $comment->comment_ID, self::META, array_merge($photos, $made));

        return new \WP_REST_Response(['photos' => count($photos) + count($made)], 201);
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
        if (! is_array($info) || ! isset(self::TYPES[$info[2]]) || $info[0] < 1 || $info[1] < 1 || $info[0] > self::MAX_SIDE || $info[1] > self::MAX_SIDE) {
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
        if (! $encoded || $clean === '' || ! self::ready()) {
            return $refused;
        }
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        if (file_put_contents(self::dir() . $name, $clean) === false) { // phpcs:ignore WordPress.WP.AlternativeFunctions
            return $refused;
        }

        return ['file' => $name, 'alt' => $alt, 'width' => $width, 'height' => $height];
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

    public static function url(int $commentId, int $index): string
    {
        return add_query_arg(self::QUERY, $commentId . '-' . $index, home_url('/'));
    }

    /** Whether the photos of the review may be seen by this visitor: approved, or the visitor moderates. */
    public static function visible(\WP_Comment $comment): bool
    {
        return (string) $comment->comment_approved === '1' || current_user_can('moderate_comments');
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
        if (! $comment instanceof \WP_Comment || ! is_array($photo) || ! self::visible($comment) || ! is_file($path)) {
            status_header(404);
            nocache_headers();
            exit;
        }
        $types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        header(sprintf('Content-Type:%s', $types[pathinfo($path, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
        header('X-Content-Type-Options:nosniff');
        header((string) $comment->comment_approved === '1' ? 'Cache-Control:public,max-age=86400' : 'Cache-Control:private,no-store');
        header(sprintf('Content-Length:%d', (int) filesize($path)));
        readfile($path); // phpcs:ignore WordPress.WP.AlternativeFunctions
        exit;
    }

    /** @param mixed $comment */
    public static function show($comment): void
    {
        if (! $comment instanceof \WP_Comment || ! self::visible($comment)) {
            return;
        }
        $photos = self::photos((int) $comment->comment_ID);
        if ($photos === []) {
            return;
        }
        echo '<ul class="mizzey-review-photos">';
        foreach ($photos as $index => $photo) {
            printf('<li><img src="%s" alt="%s" width="%d" height="%d" loading="lazy"></li>', esc_url(self::url((int) $comment->comment_ID, (int) $index)), esc_attr((string) $photo['alt']), (int) $photo['width'], (int) $photo['height']);
        }
        echo '</ul>';
    }

    /** @param mixed $commentId */
    public static function forget($commentId): void
    {
        foreach (self::photos((int) $commentId) as $photo) {
            wp_delete_file(self::dir() . basename((string) $photo['file']));
        }
    }

    public static function dir(): string
    {
        return trailingslashit((string) wp_upload_dir(null, false)['basedir']) . 'mizzey-review-photos/';
    }

    /** The folder exists, with the web server told to refuse it and no listing. */
    private static function ready(): bool
    {
        $dir = self::dir();
        if (! wp_mkdir_p($dir)) {
            return false;
        }
        if (! is_file($dir . '.htaccess')) {
            copy(dirname(__DIR__, 2) . '/assets/review-photos.htaccess', $dir . '.htaccess');
        }
        if (! is_file($dir . 'index.php')) {
            file_put_contents($dir . 'index.php', "<?php\n"); // phpcs:ignore WordPress.WP.AlternativeFunctions
        }

        return is_dir($dir) && is_writable($dir);
    }
}

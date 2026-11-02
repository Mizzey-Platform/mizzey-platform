<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue;

defined('ABSPATH') || exit;

/**
 * Images, whatever staff upload (NFR-02): heavy phone photos and large PNGs are what the catalogue will receive.
 *
 * - **Upload guard.** An image over 20 MB or 40 megapixels is refused before WordPress stores it, with a message that
 *   says why, on every route that stores a file through WordPress (the media library, a product's images, the REST
 *   API and a sideload such as the catalogue migration).
 * - **WebP output.** Every size WordPress makes of a JPEG or PNG (the scaled full size, thumbnails, WooCommerce's
 *   sizes) is written as WebP, so a shopper downloads WebP wherever WooCommerce shows the image. WordPress's own
 *   output-format filter is the one authority (ADR-0003): CoreX Media's WebP copies are served only through its own
 *   helper, which WooCommerce's images do not use, so their generation stays switched off (D-20 row A9).
 * - **Big images.** WordPress's 2,560 px threshold scales the full size down; the original is kept beside it, so a
 *   later re-crop keeps full quality (a working default, D-10: removing originals cannot be undone).
 * - **Lazy loading** is WordPress's own (`loading="lazy"` below the first image).
 *
 * Register ids: NFR-02. See specs/032-image-handling.
 */
final class ImageHandling
{
    public const MAX_BYTES  = 20 * 1024 * 1024;
    public const MAX_PIXELS = 40000000;

    /** The image types the guard measures; other uploads are left to WordPress's own type checks. */
    private const IMAGES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif', 'image/bmp', 'image/tiff', 'image/heic', 'image/heif'];

    /** Whether WordPress's image editor is saving an edit of an existing image (rotate, crop, scale). */
    private static bool $editing = false;

    public static function register(): void
    {
        add_filter('wp_handle_upload_prefilter', [self::class, 'guard']);
        add_filter('wp_handle_sideload_prefilter', [self::class, 'guard']);
        add_filter('image_editor_output_format', [self::class, 'webp'], 10, 3);
        // An edit of an existing image keeps its format: WordPress records the file name it chose before saving, and
        // a changed extension would leave the attachment pointing at a file that does not exist.
        add_action('wp_ajax_image-editor', [self::class, 'editing'], 0);
        add_filter('rest_pre_dispatch', [self::class, 'restEditing'], 0, 3);
    }

    /**
     * Refuse an image too heavy to be a product photo before WordPress keeps it.
     *
     * @param mixed $file
     * @return mixed
     */
    public static function guard($file)
    {
        if (! is_array($file) || ! empty($file['error']) || empty($file['tmp_name']) || ! is_readable((string) $file['tmp_name'])) {
            return $file;
        }
        $type = wp_check_filetype_and_ext((string) $file['tmp_name'], (string) ($file['name'] ?? ''));
        $mime = (string) ($type['type'] ?: ($file['type'] ?? ''));
        if (! in_array($mime, self::IMAGES, true)) {
            return $file;
        }
        $bytes = (int) ($file['size'] ?? 0) ?: (int) filesize((string) $file['tmp_name']);
        if ($bytes > self::MAX_BYTES) {
            $file['error'] = sprintf(
                /* translators: 1: the file's size in MB, 2: the limit in MB. */
                __('This image is %1$s MB. The largest image the store accepts is %2$s MB: export it smaller (a long side of 3,000 pixels is plenty) and upload it again.', 'mizzey-site'),
                number_format_i18n($bytes / 1048576, 1),
                number_format_i18n(self::MAX_BYTES / 1048576)
            );
            return $file;
        }
        $size = @getimagesize((string) $file['tmp_name']); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- an unreadable image is WordPress's to refuse.
        if (is_array($size) && (int) $size[0] * (int) $size[1] > self::MAX_PIXELS) {
            $file['error'] = sprintf(
                /* translators: 1: width, 2: height, 3: the limit in megapixels. */
                __('This image is %1$s by %2$s pixels. The largest image the store accepts is %3$s megapixels: export it smaller and upload it again.', 'mizzey-site'),
                number_format_i18n((int) $size[0]),
                number_format_i18n((int) $size[1]),
                number_format_i18n(self::MAX_PIXELS / 1000000)
            );
        }

        return $file;
    }

    public static function editing(): void
    {
        self::$editing = true;
    }

    /**
     * @param mixed $result
     * @param mixed $server
     * @param mixed $request
     * @return mixed
     */
    public static function restEditing($result, $server, $request)
    {
        if ($request instanceof \WP_REST_Request && preg_match('#^/wp/v2/media/\d+/edit$#', $request->get_route()) === 1) {
            self::$editing = true;
        }

        return $result;
    }

    /**
     * JPEG and PNG sizes are written as WebP, where the server's image library can write it; an edit of an existing
     * image keeps the format of the file it replaces; a palette PNG (a transparent logo, say), which GD cannot encode
     * as WebP, stays PNG.
     *
     * @param mixed $formats
     * @param mixed $filename
     * @param mixed $mime
     * @return mixed
     */
    public static function webp($formats, $filename = null, $mime = null)
    {
        if (! is_array($formats) || ! wp_image_editor_supports(['mime_type' => 'image/webp'])) {
            return $formats;
        }
        // An edit writes to the name WordPress chose from the stored file: WebP for an image uploaded since this
        // change, JPEG or PNG for one uploaded before. The saved file must keep that name's format.
        if (self::$editing && ! (is_string($filename) && str_ends_with(strtolower($filename), '.webp'))) {
            return $formats;
        }
        $formats['image/jpeg'] = 'image/webp';
        if (! (is_string($filename) && self::palettePng($filename))) {
            $formats['image/png'] = 'image/webp';
        }

        return $formats;
    }

    /** A PNG whose colour type (byte 25 of its header) is 3, indexed colour. */
    private static function palettePng(string $filename): bool
    {
        if (! is_readable($filename)) {
            return false;
        }
        $head = (string) file_get_contents($filename, false, null, 0, 26);

        return strlen($head) === 26 && str_starts_with($head, "\x89PNG") && ord($head[25]) === 3;
    }
}

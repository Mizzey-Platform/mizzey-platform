<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Security;

defined('ABSPATH') || exit;

/**
 * Comments only where the store uses them: product reviews.
 *
 * Product reviews are comments on products (REV-01 to REV-05) and stay as each product sets them. No contracted
 * surface takes comments anywhere else, and an open comment form on a post or page is an unmoderated input. Pings are
 * closed everywhere, with XML-RPC.
 *
 * Register id: NFR-05 (input validation). See specs/007-security-baseline, FR-007.
 */
final class CommentSurface
{
    public static function register(): void
    {
        add_filter('comments_open', [self::class, 'onlyProducts'], 10, 2);
        add_filter('pings_open', '__return_false');
    }

    public static function onlyProducts(bool $open, mixed $postId): bool
    {
        return $open && get_post_type($postId) === 'product';
    }
}

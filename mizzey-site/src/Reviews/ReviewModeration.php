<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Reviews;

defined('ABSPATH') || exit;

/**
 * Every product review waits for moderation before it is published (REV-03), and carries a star rating (REV-01) with
 * its text (REV-02).
 *
 * WordPress approves a comment at once when its author has an approved comment before and moderation is off
 * (`check_comment()`); this holds every new review from someone who does not moderate comments, whatever the
 * discussion settings say. WooCommerce asks for a rating when its "Star rating should be required" setting is on; it is
 * kept on here, and the text WordPress itself requires. The average WooCommerce keeps on the product (REV-04) counts
 * only approved reviews.
 *
 * Register ids: REV-01, REV-02, REV-03. See specs/020-reviews.
 */
final class ReviewModeration
{
    public static function register(): void
    {
        add_filter('pre_comment_approved', [self::class, 'hold'], 20, 2);
        add_filter('pre_option_woocommerce_review_rating_required', [self::class, 'ratingRequired']);
        add_filter('pre_option_woocommerce_enable_review_rating', [self::class, 'ratingRequired']);
    }

    /**
     * @param mixed $approved
     * @param mixed $data
     * @return mixed
     */
    public static function hold($approved, $data)
    {
        if (! is_array($data) || get_post_type((int) ($data['comment_post_ID'] ?? 0)) !== 'product' || is_wp_error($approved) || $approved === 'spam' || $approved === 'trash') {
            return $approved;
        }
        $userId = (int) ($data['user_id'] ?? 0);

        return $userId > 0 && user_can($userId, 'moderate_comments') ? $approved : 0;
    }

    public static function ratingRequired(): string
    {
        return 'yes';
    }
}

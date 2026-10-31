<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Reviews;

use MizzeySite\Orders\StatusModel;

defined('ABSPATH') || exit;

/**
 * Who may review a product, as D-17 rules (20 October 2026, an owner working decision; client acceptance pending):
 * a signed-in customer whose account holds an order for the product (any of its variations, or its record in the other
 * language) whose delivery is confirmed (StatusModel's Delivered set: Completed, and COD Collected, which records the
 * cash taken at delivery), on a line not fully refunded; one active review per customer and product, across variations
 * and both languages (a pending or approved review blocks a second; a rejected one does not, once the moderator removes
 * it). A guest order counts once WooCommerce links it to the account, which it does only after the account's email is
 * verified (specs/017).
 *
 * Enforced on the server on every route a review can be submitted: the product page's form post and XML-RPC
 * (`preprocess_comment`, which `wp_new_comment()` runs), the REST comments endpoint (`rest_pre_insert_comment`) and
 * WooCommerce's REST reviews endpoint (`woocommerce_rest_pre_insert_product_review`), where only staff who moderate may
 * set a status. Each route also needs a rating of 1 to 5. Staff who moderate comments are not checked. The product page
 * keeps its Reviews tab and approved reviews for everyone, and shows a short explanation in place of the form to a
 * customer who may not review, or who already has.
 *
 * Register ids: REV-01, REV-02. See specs/020-reviews.
 */
final class ReviewEligibility
{
    public const NOT_ELIGIBLE     = 'not_eligible';
    public const ALREADY_REVIEWED = 'already_reviewed';

    public static function register(): void
    {
        add_filter('preprocess_comment', [self::class, 'checkPost'], 5, 1);
        add_filter('rest_pre_insert_comment', [self::class, 'checkRest'], 10, 2);
        add_action('rest_insert_comment', [self::class, 'restRating'], 10, 3);
        add_filter('woocommerce_rest_pre_insert_product_review', [self::class, 'checkWooRest'], 10, 2);
        add_filter('woocommerce_product_review_comment_form_args', [self::class, 'formArgs'], 20, 1);
    }

    /** Null when the user may review the product, or the reason they may not. */
    public static function refusal(int $userId, int $productId): ?string
    {
        $group = self::group($productId);
        if ($userId <= 0 || $group === [] || ! self::delivered($userId, $group)) {
            return self::NOT_ELIGIBLE;
        }

        return self::reviewed($userId, $group) ? self::ALREADY_REVIEWED : null;
    }

    public const NO_RATING = 'no_rating';

    public static function message(string $reason): string
    {
        if ($reason === self::NO_RATING) {
            return __('Please choose a rating from 1 to 5 stars.', 'mizzey-site');
        }

        return $reason === self::ALREADY_REVIEWED
            ? __('You have already reviewed this product. Thank you.', 'mizzey-site')
            : __('Only customers whose order for this product has been delivered can review it.', 'mizzey-site');
    }

    /**
     * @param mixed $data
     * @return mixed
     */
    public static function checkPost($data)
    {
        if (! is_array($data) || ! self::isReview($data)) {
            return $data;
        }
        $userId = (int) ($data['user_id'] ?? $data['user_ID'] ?? 0);
        $reason = self::reasonFor($userId, (int) $data['comment_post_ID']);
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the comment form's own checks apply; this reads the rating.
        if ($reason === null && ! self::moderator($userId) && ! self::validRating($_POST['rating'] ?? null)) {
            $reason = self::NO_RATING;
        }
        if ($reason !== null) {
            wp_die(esc_html(self::message($reason)), esc_html__('Review not accepted', 'mizzey-site'), ['response' => 403, 'back_link' => true]);
        }

        return $data;
    }

    /**
     * @param mixed $prepared
     * @param mixed $request
     * @return mixed
     */
    public static function checkRest($prepared, $request)
    {
        if (! is_array($prepared) || ! self::isReview($prepared)) {
            return $prepared;
        }
        $userId = (int) ($prepared['user_id'] ?? 0);
        $reason = self::reasonFor($userId, (int) $prepared['comment_post_ID']);
        if ($reason === null && ! self::moderator($userId) && ! self::validRating($request instanceof \WP_REST_Request ? $request->get_param('rating') : null)) {
            $reason = self::NO_RATING;
        }

        if ($reason !== null) {
            return new \WP_Error('mizzey_review_' . $reason, self::message($reason), ['status' => $reason === self::NO_RATING ? 400 : 403]);
        }
        // A review of a product, as WooCommerce counts reviews for the average.
        $prepared['comment_type'] = 'review';

        return $prepared;
    }

    /**
     * The rating sent with a review through the REST comments endpoint, kept where WooCommerce keeps it.
     *
     * @param mixed $comment
     * @param mixed $request
     * @param mixed $creating
     */
    public static function restRating($comment, $request, $creating): void
    {
        if ($creating && $comment instanceof \WP_Comment && $request instanceof \WP_REST_Request && get_post_type((int) $comment->comment_post_ID) === 'product'
            && self::validRating($request->get_param('rating'))) {
            update_comment_meta((int) $comment->comment_ID, 'rating', (int) $request->get_param('rating'));
        }
    }

    /**
     * WooCommerce's REST reviews endpoint: the same rule for anyone who does not moderate comments, who also may not set
     * a review's status.
     *
     * @param mixed $prepared
     * @param mixed $request
     * @return mixed
     */
    public static function checkWooRest($prepared, $request)
    {
        if (! is_array($prepared) || ! $request instanceof \WP_REST_Request || self::moderator(get_current_user_id())) {
            return $prepared;
        }
        if ($request->get_param('status') !== null && $request->get_param('status') !== 'hold') {
            return new \WP_Error('mizzey_review_status', __('Only staff who moderate reviews can publish one.', 'mizzey-site'), ['status' => 403]);
        }
        $reason = self::refusal(get_current_user_id(), (int) ($prepared['comment_post_ID'] ?? 0));
        if ($reason === null && ! self::validRating($request->get_param('rating'))) {
            $reason = self::NO_RATING;
        }

        return $reason !== null ? new \WP_Error('mizzey_review_' . $reason, self::message($reason), ['status' => 403]) : $prepared;
    }

    /** @param mixed $rating */
    private static function validRating($rating): bool
    {
        $value = filter_var($rating, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);

        return $value !== false;
    }

    private static function moderator(int $userId): bool
    {
        return $userId > 0 && user_can($userId, 'moderate_comments');
    }

    /**
     * The review form on the product page: for someone who may not review, only the reason, with no fields and no
     * button, while the Reviews tab and the approved reviews stay for everyone.
     *
     * @param mixed $args
     * @return mixed
     */
    public static function formArgs($args)
    {
        $postId = (int) get_the_ID();
        if (! is_array($args) || get_post_type($postId) !== 'product' || current_user_can('moderate_comments')) {
            return $args;
        }
        $reason = self::refusal(get_current_user_id(), $postId);
        if ($reason === null) {
            return $args;
        }

        return array_merge($args, [
            'title_reply'          => '',
            'title_reply_before'   => '',
            'title_reply_after'    => '',
            'fields'               => [],
            'comment_field'        => '',
            'must_log_in'          => '',
            'logged_in_as'         => '',
            'comment_notes_before' => sprintf('<p class="woocommerce-verification-required mizzey-review-%1$s">%2$s</p>', esc_attr($reason), esc_html(self::message($reason))),
            'comment_notes_after'  => '',
            'submit_button'        => '',
            'submit_field'         => '',
        ]);
    }

    /** @param array<string,mixed> $data */
    private static function isReview(array $data): bool
    {
        $type = (string) ($data['comment_type'] ?? '');

        return get_post_type((int) ($data['comment_post_ID'] ?? 0)) === 'product' && in_array($type, ['', 'comment', 'review'], true);
    }

    private static function reasonFor(int $userId, int $productId): ?string
    {
        return self::moderator($userId) ? null : self::refusal($userId, $productId);
    }

    /**
     * The product's records in every language: the product of a variation, then its translation group.
     *
     * @return list<int>
     */
    public static function group(int $productId): array
    {
        $product = wc_get_product($productId);
        if (! $product instanceof \WC_Product) {
            return [];
        }
        $root  = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
        $trid  = apply_filters('wpml_element_trid', null, $root, 'post_product');
        $ids   = [$root];
        $group = $trid ? apply_filters('wpml_get_element_translations', null, $trid, 'post_product') : null;
        foreach ((array) $group as $translation) {
            $ids[] = (int) ($translation->element_id ?? 0);
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /** @param list<int> $group */
    private static function delivered(int $userId, array $group): bool
    {
        $orders = wc_get_orders([
            'customer_id' => $userId,
            'status'      => array_map(static fn (string $status): string => 'wc-' . $status, StatusModel::DELIVERED),
            'limit'       => -1,
        ]);
        foreach ($orders as $order) {
            if (! $order instanceof \WC_Order || ! StatusModel::isDelivered($order)) {
                continue;
            }
            foreach ($order->get_items() as $itemId => $item) {
                // A line refunded in full, by its quantity or by its amount, does not count.
                if ($item instanceof \WC_Order_Item_Product && in_array((int) $item->get_product_id(), $group, true)
                    && (int) $item->get_quantity() + (int) $order->get_qty_refunded_for_item((int) $itemId) > 0
                    && (float) $order->get_total_refunded_for_item((int) $itemId) < (float) $item->get_total()) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param list<int> $group */
    private static function reviewed(int $userId, array $group): bool
    {
        global $wpdb;
        // Read directly: WPML narrows comment queries to the current language, and a review may have arrived as a plain
        // comment through the REST comments endpoint.
        $ids = implode(',', array_map('intval', $group));

        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = %d AND comment_post_ID IN ({$ids}) AND comment_approved IN ('0', '1') AND comment_type IN ('review', 'comment', '')", $userId)) > 0; // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }
}

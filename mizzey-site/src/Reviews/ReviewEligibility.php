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
 * (`preprocess_comment`, which `wp_new_comment()` runs) and the REST comments endpoint (`rest_pre_insert_comment`).
 * Staff who moderate comments are not checked. The product page shows a short explanation instead of the form to a
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
        add_filter('comments_open', [self::class, 'formOpen'], 20, 2);
        add_action('comment_form_comments_closed', [self::class, 'explain']);
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

    public static function message(string $reason): string
    {
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
        $reason = self::reasonFor((int) ($data['user_id'] ?? $data['user_ID'] ?? 0), (int) $data['comment_post_ID']);
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
        $reason = self::reasonFor((int) ($prepared['user_id'] ?? 0), (int) $prepared['comment_post_ID']);

        return $reason !== null ? new \WP_Error('mizzey_review_' . $reason, self::message($reason), ['status' => 403]) : $prepared;
    }

    /**
     * The form shows only to someone who may review; others see why (explain()).
     *
     * @param mixed $open
     * @param mixed $postId
     */
    public static function formOpen($open, $postId): bool
    {
        if (! $open || get_post_type($postId) !== 'product' || is_admin() || current_user_can('moderate_comments')) {
            return (bool) $open;
        }

        return self::refusal(get_current_user_id(), (int) $postId) === null;
    }

    public static function explain(): void
    {
        $postId = (int) get_the_ID();
        if (get_post_type($postId) !== 'product' || ! wc_reviews_enabled()) {
            return;
        }
        $reason = self::refusal(get_current_user_id(), $postId);
        if ($reason !== null) {
            printf('<p class="woocommerce-verification-required mizzey-review-%1$s">%2$s</p>', esc_attr($reason), esc_html(self::message($reason)));
        }
    }

    /** @param array<string,mixed> $data */
    private static function isReview(array $data): bool
    {
        $type = (string) ($data['comment_type'] ?? '');

        return get_post_type((int) ($data['comment_post_ID'] ?? 0)) === 'product' && in_array($type, ['', 'comment', 'review'], true);
    }

    private static function reasonFor(int $userId, int $productId): ?string
    {
        if ($userId > 0 && user_can($userId, 'moderate_comments')) {
            return null;
        }

        return self::refusal($userId, $productId);
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
                if ($item instanceof \WC_Order_Item_Product && in_array((int) $item->get_product_id(), $group, true)
                    && (int) $item->get_quantity() + (int) $order->get_qty_refunded_for_item((int) $itemId) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param list<int> $group */
    private static function reviewed(int $userId, array $group): bool
    {
        return (int) get_comments([
            'user_id'  => $userId,
            'post__in' => $group,
            'type'     => 'review',
            'status'   => ['hold', 'approve'],
            'count'    => true,
        ]) > 0;
    }
}

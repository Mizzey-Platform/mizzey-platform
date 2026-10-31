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
        add_filter('rest_pre_dispatch', [self::class, 'wooRestStatus'], 10, 3);
        add_filter('rest_pre_dispatch', [self::class, 'commentsRest'], 10, 3);
        add_filter('map_meta_cap', [self::class, 'editReview'], 10, 4);
        add_action('wp_insert_comment', [self::class, 'unlock'], 0);
        add_action('shutdown', [self::class, 'unlock']);
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
        $reason = self::reasonFor(get_current_user_id(), (int) ($prepared['comment_post_ID'] ?? 0));
        if ($reason === null && ! self::validRating($request->get_param('rating'))) {
            $reason = self::NO_RATING;
        }
        if ($reason !== null) {
            return new \WP_Error('mizzey_review_' . $reason, self::message($reason), ['status' => 403]);
        }
        // The review is the signed-in customer's, under their own name and email, not one the request makes up.
        $user                             = wp_get_current_user();
        $prepared['user_id']              = $user->ID;
        $prepared['comment_author']       = $user->display_name;
        $prepared['comment_author_email'] = $user->user_email;

        return $prepared;
    }

    /**
     * WooCommerce's REST reviews routes, any version, update and batch included: only staff who moderate may set a
     * review's status, and only they may change a review at all. The older nested routes (`/products/{id}/reviews`, v1
     * and v2) insert a review without any of the checks above, so only staff who moderate may write through them.
     *
     * @param mixed $result
     * @param mixed $server
     * @param mixed $request
     * @return mixed
     */
    public static function wooRestStatus($result, $server, $request)
    {
        if ($result !== null || ! $request instanceof \WP_REST_Request || self::moderator(get_current_user_id())
            || preg_match('#^/wc/v\d+/products/(\d+/)?reviews#i', $request->get_route()) !== 1 || $request->get_method() === 'GET') {
            return $result;
        }
        $changes = in_array($request->get_method(), ['PUT', 'PATCH', 'DELETE'], true) || str_contains(strtolower($request->get_route()), '/batch')
            || preg_match('#/reviews/\d+#', $request->get_route()) === 1 || preg_match('#^/wc/v\d+/products/\d+/#i', $request->get_route()) === 1;
        if ($changes || $request->get_param('status') !== null && $request->get_param('status') !== 'hold') {
            return new \WP_Error('mizzey_review_status', __('Only staff who moderate reviews can publish one.', 'mizzey-site'), ['status' => 403]);
        }

        return $result;
    }

    /**
     * WordPress's REST comments endpoint, for a comment on a product or one being moved onto a product: only staff who
     * moderate may change it. WordPress lets anyone who may edit the product edit its comments, status included.
     *
     * @param mixed $result
     * @param mixed $server
     * @param mixed $request
     * @return mixed
     */
    public static function commentsRest($result, $server, $request)
    {
        if ($result !== null || ! $request instanceof \WP_REST_Request || $request->get_method() === 'GET' || self::moderator(get_current_user_id())
            || preg_match('#^/wp/v2/comments/(\d+)#i', $request->get_route(), $match) !== 1) {
            return $result;
        }
        // The comment the controller acts on: an id in the body or query wins over the one in the address.
        $comment = get_comment((int) ($request->get_param('id') ?? $match[1]));
        $onto    = $request->get_param('post');
        if (($comment instanceof \WP_Comment && get_post_type((int) $comment->comment_post_ID) === 'product')
            || ($onto !== null && get_post_type((int) $onto) === 'product')) {
            return new \WP_Error('mizzey_review_status', __('Only staff who moderate reviews can publish one.', 'mizzey-site'), ['status' => 403]);
        }

        return $result;
    }

    /**
     * Editing a review of a product, approving it included, needs the right to moderate comments: WordPress maps
     * `edit_comment` to editing the product, which every screen and endpoint that changes a comment checks (the REST
     * comments endpoint, the admin's reply with approval, approve and unapprove, the comment editor).
     *
     * @param mixed $caps
     * @param mixed $cap
     * @param mixed $userId
     * @param mixed $args
     * @return mixed
     */
    public static function editReview($caps, $cap, $userId, $args)
    {
        if ($cap !== 'edit_comment' || ! is_array($caps) || ! is_array($args) || ! isset($args[0])) {
            return $caps;
        }
        $comment = get_comment((int) $args[0]);
        if ($comment instanceof \WP_Comment && get_post_type((int) $comment->comment_post_ID) === 'product') {
            $caps[] = 'moderate_comments';
        }

        return $caps;
    }

    /** The lock held between a submission's check and its insert, released once the review is stored. */
    private static ?string $lock = null;

    /**
     * Takes the lock for the customer's review of the product, so two submissions at once cannot both pass the check
     * for one review; false when it cannot be taken.
     */
    private static function lockFor(int $userId, int $productId): bool
    {
        global $wpdb;
        $group = self::group($productId);
        // The lowest id in the translation group, the same whichever language the review arrives in.
        $name  = substr('mz_' . $wpdb->prefix . 'review_' . $userId . '_' . ($group === [] ? $productId : min($group)), 0, 64);
        if (self::$lock === $name) {
            return true;
        }
        self::unlock();
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $name)) !== 1) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            return false;
        }
        self::$lock = $name;

        return true;
    }

    public static function unlock(): void
    {
        global $wpdb;
        if (self::$lock !== null) {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', self::$lock)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            self::$lock = null;
        }
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
            // WordPress prints the notes before the fields to guests and the signed-in line to customers: both carry the
            // reason, and so does the sign-in prompt when reviews need an account.
            'must_log_in'          => self::reasonHtml($reason),
            'logged_in_as'         => self::reasonHtml($reason),
            'comment_notes_before' => self::reasonHtml($reason),
            'comment_notes_after'  => '',
            'submit_button'        => '',
            'submit_field'         => '',
        ]);
    }

    private static function reasonHtml(string $reason): string
    {
        return sprintf('<p class="woocommerce-verification-required mizzey-review-%s">%s</p>', esc_attr($reason), esc_html(self::message($reason)));
    }

    /** @param array<string,mixed> $data */
    private static function isReview(array $data): bool
    {
        $type = (string) ($data['comment_type'] ?? '');

        return get_post_type((int) ($data['comment_post_ID'] ?? 0)) === 'product' && in_array($type, ['', 'comment', 'review'], true);
    }

    private static function reasonFor(int $userId, int $productId): ?string
    {
        if (self::moderator($userId)) {
            return null;
        }
        // Checked under the customer's lock for this product, held until the review is stored.
        if ($userId > 0 && ! self::lockFor($userId, $productId)) {
            return self::ALREADY_REVIEWED;
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
                // A line refunded in full, by its quantity or by its amount, does not count.
                if ($item instanceof \WC_Order_Item_Product && in_array((int) $item->get_product_id(), $group, true)
                    && (int) $item->get_quantity() + (int) $order->get_qty_refunded_for_item((int) $itemId) > 0
                    && ! self::refundedInFull($order, (int) $itemId, $item)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** A line refunded in full by its amount: something was refunded, and at least what the line cost. */
    private static function refundedInFull(\WC_Order $order, int $itemId, \WC_Order_Item_Product $item): bool
    {
        $refunded = (float) $order->get_total_refunded_for_item($itemId);

        return $refunded > 0 && $refunded >= (float) $item->get_total();
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

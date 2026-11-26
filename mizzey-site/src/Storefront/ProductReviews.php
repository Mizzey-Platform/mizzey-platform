<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Storefront;

use MizzeySite\Reviews\ReviewEligibility;
use MizzeySite\Reviews\ReviewPhotos;

defined('ABSPATH') || exit;

/**
 * The approved reviews of a product, read for the product page (PDP-17, REV-04): the reviews themselves are WooCommerce's
 * product reviews, held for moderation and approved by staff (specs/020), with their photos (D-23, a recorded gift,
 * specs/025). This reads; it never writes a review, a rating or a photo.
 *
 * - **Only approved reviews**, of the product in either language (one product, one set of reviews, as specs/020 counts
 *   them), with a rating of 1 to 5. A held, rejected or spam review is never read here.
 * - **Only the photos ReviewPhotos shows**: those its review had when it was approved, while it is approved.
 * - **The summary**: the average to one decimal, the number of reviews, and how many gave each star, from the same
 *   reviews the list shows, so the two always agree.
 * - **Filters and order**: with photos, or one star value; most recent first (as drawn), or the highest or lowest rating.
 *
 * Register ids: PDP-17, REV-04. See specs/066-stage-2-shop-front, slice 10.
 */
final class ProductReviews
{
    public const SORTS = ['recent', 'high', 'low'];

    /** @var array<int, list<array<string,mixed>>> The reviews read once per request, by product. */
    private static array $cache = [];

    /**
     * Every approved review of the product, most recent first.
     *
     * @return list<array{id:int,rating:int,name:string,date:string,time:int,text:string,photos:list<array{url:string,alt:string,width:int,height:int}>}>
     */
    public static function all(\WC_Product $product): array
    {
        $id = $product->get_id();
        if (isset(self::$cache[$id])) {
            return self::$cache[$id];
        }
        $group = ReviewEligibility::group($id);
        // WPML keeps a comment query to the page's language unless told the query is not filtered (its own hook, which
        // WCML uses for "reviews in all languages"): a product's reviews are its reviews in either language.
        add_filter('wpml_is_comment_query_filtered', '__return_false', 99);
        $comments = $group === [] ? [] : get_comments([
            'post__in' => $group,
            'status'   => 'approve',
            'type'     => 'review',
            'parent'   => 0,
            'orderby'  => ['comment_date_gmt', 'comment_ID'],
            'order'    => 'DESC',
            'number'   => 0,
        ]);
        remove_filter('wpml_is_comment_query_filtered', '__return_false', 99);
        $reviews = [];
        foreach ((array) $comments as $comment) {
            if (! $comment instanceof \WP_Comment || (string) $comment->comment_approved !== '1') {
                continue;
            }
            $rating = (int) get_comment_meta((int) $comment->comment_ID, 'rating', true);
            if ($rating < 1 || $rating > 5) {
                continue;
            }
            $reviews[] = [
                'id'     => (int) $comment->comment_ID,
                'rating' => $rating,
                'name'   => self::name($comment),
                // As drawn, "12 Nov 2026"; the month in the page's language.
                'date'   => (string) mysql2date(__('j M Y', 'mizzey-site'), $comment->comment_date, true),
                'time'   => (int) mysql2date('U', $comment->comment_date_gmt, false),
                'text'   => trim((string) $comment->comment_content),
                'photos' => self::photos($comment),
            ];
        }

        return self::$cache[$id] = $reviews;
    }

    /** Forget what was read (a test that changes reviews in one request). */
    public static function forget(): void
    {
        self::$cache = [];
    }

    /**
     * The summary: average, count, and the count for each star from 5 to 1, and how many have photos.
     *
     * @return array{average:float,count:int,stars:array<int,int>,photos:int}
     */
    public static function summary(\WC_Product $product): array
    {
        $reviews = self::all($product);
        $stars   = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $sum     = 0;
        $photos  = 0;
        foreach ($reviews as $review) {
            ++$stars[$review['rating']];
            $sum += $review['rating'];
            $photos += $review['photos'] !== [] ? 1 : 0;
        }
        $count = count($reviews);

        return ['average' => $count > 0 ? round($sum / $count, 1) : 0.0, 'count' => $count, 'stars' => $stars, 'photos' => $photos];
    }

    /**
     * The reviews a filter keeps, in the order asked.
     *
     * @return list<array<string,mixed>>
     */
    public static function find(\WC_Product $product, string $filter = '', string $sort = 'recent'): array
    {
        $reviews = self::all($product);
        if ($filter === 'photos') {
            $reviews = array_values(array_filter($reviews, static fn (array $r): bool => $r['photos'] !== []));
        } elseif (preg_match('/^[1-5]$/', $filter) === 1) {
            $reviews = array_values(array_filter($reviews, static fn (array $r): bool => $r['rating'] === (int) $filter));
        }
        if ($sort === 'high' || $sort === 'low') {
            $sign = $sort === 'high' ? -1 : 1;
            usort($reviews, static fn (array $a, array $b): int => ($sign * ($a['rating'] <=> $b['rating'])) ?: ($b['time'] <=> $a['time']));
        }

        return $reviews;
    }

    /**
     * The product's rating, the same in every language: the average to one decimal and the number of approved reviews
     * of the product and its translations together. WooCommerce counts each language's record on its own (and WCML
     * merges only the average), so a product read 4.3 (3) in English and 4.3 (1) in Arabic; this is the one figure the
     * product page, its reviews and the product card show. A variation reads as its product.
     *
     * @param \WC_Product|int $product
     * @return array{average:float,count:int}
     */
    public static function rating($product): array
    {
        $product = $product instanceof \WC_Product ? $product : wc_get_product((int) $product);
        if (! $product instanceof \WC_Product) {
            return ['average' => 0.0, 'count' => 0];
        }
        if ($product->is_type('variation')) {
            $parent  = wc_get_product($product->get_parent_id());
            $product = $parent instanceof \WC_Product ? $parent : $product;
        }
        $summary = self::summary($product);

        return ['average' => $summary['average'], 'count' => $summary['count']];
    }

    /** The filter asked for in the address, or none. */
    public static function filterAsked(): string
    {
        $asked = isset($_GET['review_filter']) ? sanitize_key(wp_unslash((string) $_GET['review_filter'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only view.

        return $asked === 'photos' || preg_match('/^[1-5]$/', $asked) === 1 ? $asked : '';
    }

    /** The order asked for in the address, most recent when none. */
    public static function sortAsked(): string
    {
        $asked = isset($_GET['review_sort']) ? sanitize_key(wp_unslash((string) $_GET['review_sort'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only view.

        return in_array($asked, self::SORTS, true) ? $asked : 'recent';
    }

    /**
     * The name shown: the first name and the initial of the last, as drawn ("Mariam H."), from the account when the
     * reviewer has one, else from the name given with the review.
     */
    private static function name(\WP_Comment $comment): string
    {
        $user  = (int) $comment->user_id > 0 ? get_userdata((int) $comment->user_id) : false;
        $first = $user ? trim((string) $user->first_name) : '';
        $last  = $user ? trim((string) $user->last_name) : '';
        if ($first === '') {
            $words = preg_split('/\s+/u', trim((string) $comment->comment_author)) ?: [];
            $first = (string) array_shift($words);
            $last  = (string) array_pop($words);
        }
        if ($first === '') {
            return __('A customer', 'mizzey-site');
        }

        return $last !== '' ? $first . ' ' . mb_substr($last, 0, 1) . '.' : $first;
    }

    /** @return list<array{url:string,alt:string,width:int,height:int}> */
    private static function photos(\WP_Comment $comment): array
    {
        $out = [];
        foreach (ReviewPhotos::photos((int) $comment->comment_ID) as $index => $photo) {
            if (ReviewPhotos::visible($comment, $photo)) {
                $out[] = [
                    'url'    => ReviewPhotos::url((int) $comment->comment_ID, (int) $index),
                    'alt'    => (string) ($photo['alt'] ?? ''),
                    'width'  => (int) ($photo['width'] ?? 0),
                    'height' => (int) ($photo['height'] ?? 0),
                ];
            }
        }

        return $out;
    }
}

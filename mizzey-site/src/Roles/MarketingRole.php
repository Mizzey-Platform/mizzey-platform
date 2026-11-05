<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Roles;

use MizzeySite\Marketing\TagManager;
use MizzeySite\Security\StaffActivityLog;

defined('ABSPATH') || exit;

/**
 * The restricted access an agency the client engages works through (MKT-21 to MKT-25; CX-02, resolved by D-12): the
 * minimal Marketing profile the contracted Stage 1 marketing rows need, not the deferred ROLE-05.
 *
 * - **Content and tracking only.** Pages, posts, media and the promotional banners (which use the page capabilities),
 *   and the Tag Manager container, the one route for tracking scripts (MKT-23), through a capability of its own.
 * - **No software, templates or files** (MKT-21): none of WordPress's plugin, theme, template, file, import, export or
 *   update capabilities, and no `unfiltered_html`, so a script cannot be put into content past Tag Manager.
 * - **No customer data** (MKT-22): no users, orders, customers or reports.
 * - **Recorded** (MKT-25): sign-ins are already in the staff log (specs/031); each content, media and tracking change an
 *   account of this role makes is recorded there too.
 *
 * The tracking capability is also given to the owner and the admin roles, who held the setting before through
 * `manage_woocommerce`. Roles are stored by WordPress, so they are written once, and again when VERSION or the
 * capability list changes.
 *
 * Register ids: MKT-21 to MKT-25. See specs/064-agency-access.
 */
final class MarketingRole
{
    public const ROLE    = 'mizzey_marketing';
    public const TRACKING = 'mizzey_manage_tracking';
    public const VERSION = '1';
    public const STORED  = 'mizzey_marketing_role_version';

    /** The roles that hold the tracking setting besides this one: the owner and the admin. */
    public const GRANTED_TO = ['administrator', 'shop_manager'];

    /** The role's capabilities: content and media, and the tracking setting. Nothing else. */
    public const CAPABILITIES = [
        'read',
        'upload_files',
        'edit_posts', 'edit_others_posts', 'edit_published_posts', 'publish_posts',
        'delete_posts', 'delete_published_posts',
        'edit_pages', 'edit_others_pages', 'edit_published_pages', 'publish_pages',
        'delete_pages', 'delete_published_pages',
        'manage_categories',
        self::TRACKING,
    ];

    public static function register(): void
    {
        add_action('init', [self::class, 'install'], 5);
        add_action('wp_after_insert_post', [self::class, 'saved'], 20, 4);
        add_action('before_delete_post', [self::class, 'deleted'], 20, 2);
        add_action('wp_trash_post', [self::class, 'trashed'], 20, 1);
        add_action('add_attachment', [self::class, 'mediaAdded'], 20, 1);
        add_action('update_option_' . TagManager::CONTAINER, [self::class, 'trackingChanged'], 20, 2);
        add_action('add_option_' . TagManager::CONTAINER, [self::class, 'trackingAdded'], 20, 2);
    }

    /** The role, and the tracking capability for the owner and admin roles, written once per VERSION. */
    public static function install(): void
    {
        // The owner and admin roles are checked on every request: a capability taken off by hand, or by another
        // plugin, is put back. Reading a role makes no query, and add_cap writes only when the capability is missing.
        foreach (self::GRANTED_TO as $name) {
            $role = get_role($name);
            if ($role instanceof \WP_Role && ! $role->has_cap(self::TRACKING)) {
                $role->add_cap(self::TRACKING);
            }
        }
        if ((wp_load_alloptions()[self::STORED] ?? null) === self::stamp()) {
            return;
        }
        remove_role(self::ROLE);
        add_role(self::ROLE, __('Marketing (agency)', 'mizzey-site'), array_fill_keys(self::CAPABILITIES, true));
        update_option(self::STORED, self::stamp(), true);
    }

    /** The version and the capability list together: a release that changes the list writes the role again. */
    public static function stamp(): string
    {
        return self::VERSION . ':' . md5(implode(',', self::CAPABILITIES));
    }

    /** Whether the current account, or the given one, holds this role. */
    public static function holds(?\WP_User $user = null): bool
    {
        $user ??= wp_get_current_user();

        return $user instanceof \WP_User && in_array(self::ROLE, (array) $user->roles, true);
    }

    /**
     * @param mixed $postId
     * @param mixed $post
     * @param mixed $update
     * @param mixed $before
     */
    public static function saved($postId, $post, $update = false, $before = null): void
    {
        if (! $post instanceof \WP_Post || ! self::holds() || in_array($post->post_status, ['auto-draft', 'inherit'], true) || wp_is_post_revision($post) || wp_is_post_autosave($post)) {
            return;
        }
        $was = $before instanceof \WP_Post ? $before->post_status : 'new';
        self::record(StaffActivityLog::AGENCY_CONTENT, $post, ['type' => $post->post_type, 'status' => $post->post_status, 'was' => $was]);
    }

    /** @param mixed $postId */
    public static function trashed($postId): void
    {
        $post = get_post((int) $postId);
        if ($post instanceof \WP_Post && self::holds()) {
            self::record(StaffActivityLog::AGENCY_DELETED, $post, ['type' => $post->post_type, 'how' => 'trash']);
        }
    }

    /**
     * @param mixed $postId
     * @param mixed $post
     */
    public static function deleted($postId, $post = null): void
    {
        $post = $post instanceof \WP_Post ? $post : get_post((int) $postId);
        if ($post instanceof \WP_Post && self::holds() && ! wp_is_post_revision($post) && $post->post_type !== 'attachment') {
            self::record(StaffActivityLog::AGENCY_DELETED, $post, ['type' => $post->post_type, 'how' => 'delete']);
        }
    }

    /** @param mixed $attachmentId */
    public static function mediaAdded($attachmentId): void
    {
        $post = get_post((int) $attachmentId);
        if ($post instanceof \WP_Post && self::holds()) {
            self::record(StaffActivityLog::AGENCY_MEDIA, $post, ['mime' => $post->post_mime_type]);
        }
    }

    /**
     * @param mixed $old
     * @param mixed $new
     */
    public static function trackingChanged($old, $new): void
    {
        if (self::holds() && (string) $old !== (string) $new) {
            StaffActivityLog::recordAction(StaffActivityLog::AGENCY_TRACKING, 'option', TagManager::CONTAINER, __('Tag Manager container', 'mizzey-site'), ['from' => (string) $old, 'to' => (string) $new]);
        }
    }

    /**
     * @param mixed $option
     * @param mixed $value
     */
    public static function trackingAdded($option, $value): void
    {
        self::trackingChanged('', $value);
    }

    /** @param array<string,mixed> $context */
    private static function record(string $kind, \WP_Post $post, array $context): void
    {
        $title = trim(wp_strip_all_tags((string) $post->post_title));
        StaffActivityLog::recordAction($kind, 'post', (string) $post->ID, $title !== '' ? $title : '#' . $post->ID, $context);
    }
}

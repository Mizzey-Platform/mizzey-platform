<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Accounts;

use MizzeySite\Security\RecoveryLimiter;

defined('ABSPATH') || exit;

/**
 * A passwordless email sign-in link, as an alternative to remembering a password (AUTH-12, P1-L), on the owner's ruling
 * B16: configurable, single-use, time-limited links, with no same-browser restriction, shared account and address
 * protection, and responses that disclose nothing.
 *
 * A customer asks with their email (`POST mizzey/v1/sign-in-link`); the answer is the same whether or not an account
 * exists. For a customer account, a link valid for 15 minutes (`mizzey_sign_in_link_lifetime`) is emailed; asking again
 * replaces it. Requests count against the password-reset limits (`RecoveryLimiter`: per account and per address), so
 * neither route multiplies the other. Following the link signs the customer in and spends it; a spent or expired link
 * says so plainly. Staff accounts are never sent one: their sign-in needs two-factor (ADM-131).
 *
 * Proposed working defaults, recorded on #305 as B16 asks: lifetime 15 minutes, one use, the recovery limits (3 per
 * account and 10 per address in 15 minutes). Owner decisions, not client confirmations.
 *
 * Register ids: AUTH-12. See specs/017-accounts.
 */
final class SignInLink
{
    public const META  = '_mizzey_sign_in_link';
    public const PARAM = 'mizzey_sign_in';
    public const USER  = 'mizzey_sign_in_user';

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'route']);
        add_action('template_redirect', [self::class, 'follow'], 5);
    }

    public static function lifetime(): int
    {
        return max(60, (int) apply_filters('mizzey_sign_in_link_lifetime', 15 * MINUTE_IN_SECONDS));
    }

    public static function route(): void
    {
        register_rest_route('mizzey/v1', '/sign-in-link', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'requestRoute'],
            'permission_callback' => '__return_true',
            'args'                => ['email' => ['type' => 'string', 'required' => true]],
        ]);
    }

    public static function requestRoute(\WP_REST_Request $request): \WP_REST_Response
    {
        self::request((string) $request['email']);

        return new \WP_REST_Response(['message' => __('If an account exists for that email, a sign-in link is on its way.', 'mizzey-site')], 202);
    }

    /** Sends a link to a customer account for the email, if there is one and the limits allow; says nothing either way. */
    public static function request(string $email): void
    {
        $user = is_email($email) ? get_user_by('email', $email) : false;
        $errors = new \WP_Error();
        RecoveryLimiter::limit($errors, $user instanceof \WP_User && self::eligible($user) ? $user : false);
        if ($errors->has_errors() || ! $user instanceof \WP_User || ! self::eligible($user)) {
            return;
        }
        $token = wp_generate_password(40, false, false);
        update_user_meta($user->ID, self::META, ['hash' => self::hash($user->ID, $token), 'until' => time() + self::lifetime()]);
        $link = add_query_arg([self::PARAM => $token, self::USER => $user->ID], wc_get_page_permalink('myaccount'));
        wp_mail(
            $user->user_email,
            __('Your sign-in link', 'mizzey-site'),
            sprintf(
                /* translators: 1: the sign-in link, 2: minutes it stays valid. */
                __("Use this link to sign in to your account:\n\n%1\$s\n\nIt works once and expires in %2\$d minutes. If you did not ask for it, you can ignore this email.", 'mizzey-site'),
                $link,
                (int) round(self::lifetime() / MINUTE_IN_SECONDS)
            )
        );
    }

    /** A link followed: sign in and spend it, or say plainly that it is spent or expired. */
    public static function follow(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- the token is the proof.
        if (! isset($_GET[self::PARAM], $_GET[self::USER])) {
            return;
        }
        $token  = sanitize_text_field(wp_unslash((string) $_GET[self::PARAM]));
        $userId = absint($_GET[self::USER]);
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $account = wc_get_page_permalink('myaccount');
        if (self::redeem($userId, $token)) {
            wp_set_current_user($userId);
            wp_set_auth_cookie($userId, false);
            wp_safe_redirect($account);
            exit;
        }
        wc_add_notice(__('This sign-in link has expired or was already used. Ask for a new one.', 'mizzey-site'), 'error');
        wp_safe_redirect($account);
        exit;
    }

    /** Whether the token is the user's live link; a live link is spent by redeeming it. */
    public static function redeem(int $userId, string $token): bool
    {
        $user   = $userId > 0 ? get_userdata($userId) : false;
        $stored = $user ? get_user_meta($userId, self::META, true) : null;
        if (! $user instanceof \WP_User || ! is_array($stored) || ! self::eligible($user) || $token === '') {
            return false;
        }
        $live  = (int) ($stored['until'] ?? 0) >= time();
        $match = hash_equals((string) ($stored['hash'] ?? ''), self::hash($userId, $token));
        // Spent when used, cleared when expired; a wrong token leaves the customer's live link alone.
        if ($match || ! $live) {
            delete_user_meta($userId, self::META);
        }

        return $live && $match;
    }

    /** Customer accounts only: staff sign in with their password and two-factor (ADM-131). */
    public static function eligible(\WP_User $user): bool
    {
        return in_array('customer', (array) $user->roles, true) && ! user_can($user, 'edit_posts') && ! user_can($user, 'manage_woocommerce');
    }

    private static function hash(int $userId, string $token): string
    {
        return hash_hmac('sha256', $userId . '|' . $token, wp_salt('auth'));
    }
}

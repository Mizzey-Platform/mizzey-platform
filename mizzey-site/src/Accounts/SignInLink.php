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
 * exists, and the work is done after the answer is sent (`fastcgi_finish_request()`, under PHP-FPM as on the servers),
 * so the time it takes does not say whether the email has an account, or a staff one. For a customer account, a link
 * valid for 15 minutes (`mizzey_sign_in_link_lifetime`) is emailed; asking again
 * replaces it. Requests count against the password-reset limits (`RecoveryLimiter`: per account and per address), so
 * neither route multiplies the other. Staff accounts are never sent one: their sign-in needs two-factor (ADM-131).
 *
 * Opening the link signs no one in: the account page asks the visitor to confirm, by a form tied to their own browser
 * session (a WooCommerce session nonce), so a link cannot sign a visitor in to someone else's account by being opened,
 * and a mail scanner that opens it spends nothing. Confirming spends the link in one delete that only one request can
 * win. A visitor already signed in to another account is not switched. The token is bound to the account's email and
 * password, so changing either ends every link already sent.
 *
 * Proposed working defaults, recorded on #305 as B16 asks: lifetime 15 minutes, one use, the recovery limits (3 per
 * account and 10 per address in 15 minutes). Owner decisions, not client confirmations.
 *
 * Register ids: AUTH-12. See specs/017-accounts.
 */
final class SignInLink
{
    public const META   = '_mizzey_sign_in_link';
    public const PARAM  = 'mizzey_sign_in';
    public const USER   = 'mizzey_sign_in_user';
    /** Starts with "woocommerce", so WooCommerce ties the nonce to the visitor's session (`nonce_user_logged_out`). */
    public const NONCE  = 'woocommerce-mizzey-sign-in-link';

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'route']);
        add_action('template_redirect', [self::class, 'follow'], 5);
        add_action('woocommerce_before_customer_login_form', [self::class, 'confirmation'], 5);
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

    /** @var list<string> Emails asked for in this request, handled after the answer is sent. */
    private static array $queued = [];

    public static function requestRoute(\WP_REST_Request $request): \WP_REST_Response
    {
        if (self::$queued === []) {
            add_action('shutdown', [self::class, 'afterAnswer'], 0);
        }
        self::$queued[] = (string) $request['email'];

        return new \WP_REST_Response(['message' => __('If an account exists for that email, a sign-in link is on its way.', 'mizzey-site')], 202);
    }

    /** At shutdown: the answer goes out first, then the queued requests are handled. */
    public static function afterAnswer(): void
    {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        self::flush();
    }

    /** Handles the queued requests. */
    public static function flush(): void
    {
        $queued       = self::$queued;
        self::$queued = [];
        foreach ($queued as $email) {
            self::request($email);
        }
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
        update_user_meta($user->ID, self::META, self::hash($user, $token) . '|' . (time() + self::lifetime()));
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

    /**
     * A link opened: the account page asks to confirm. A confirmation posted from that page: sign in and spend the
     * link, or say plainly that it is spent or expired.
     */
    public static function follow(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification -- the confirmation is verified below; the link only shows the form.
        $posted = isset($_POST[self::PARAM], $_POST[self::USER]);
        if (! $posted && ! isset($_GET[self::PARAM], $_GET[self::USER])) {
            return;
        }
        $source = $posted ? $_POST : $_GET;
        $token  = sanitize_text_field(wp_unslash((string) $source[self::PARAM]));
        $userId = absint($source[self::USER]);
        // phpcs:enable WordPress.Security.NonceVerification
        $account = wc_get_page_permalink('myaccount');
        if (is_user_logged_in()) {
            if (get_current_user_id() !== $userId) {
                wc_add_notice(__('You are already signed in to another account. Sign out first to use this sign-in link.', 'mizzey-site'), 'error');
            }
            wp_safe_redirect($account);
            exit;
        }
        if (! $posted) {
            // The form's nonce is tied to this visitor's own session.
            if (WC()->session) {
                WC()->session->set_customer_session_cookie(true);
            }

            return;
        }
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash((string) $_POST['_wpnonce'])) : '';
        if (wp_verify_nonce($nonce, self::NONCE) && self::redeem($userId, $token)) {
            wp_set_current_user($userId);
            wp_set_auth_cookie($userId, false);
            wp_safe_redirect($account);
            exit;
        }
        wc_add_notice(__('This sign-in link has expired or was already used. Ask for a new one.', 'mizzey-site'), 'error');
        wp_safe_redirect($account);
        exit;
    }

    /** The confirmation on the account page, for a link opened by a visitor who is signed out. */
    public static function confirmation(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- showing the form changes nothing.
        if (is_user_logged_in() || ! isset($_GET[self::PARAM], $_GET[self::USER])) {
            return;
        }
        $token  = sanitize_text_field(wp_unslash((string) $_GET[self::PARAM]));
        $userId = absint($_GET[self::USER]);
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        printf(
            '<form class="mizzey-sign-in-link" method="post" action="%1$s"><p>%2$s</p><input type="hidden" name="%3$s" value="%4$s" /><input type="hidden" name="%5$s" value="%6$d" />%7$s<p><button type="submit" class="button">%8$s</button></p></form>',
            esc_url(wc_get_page_permalink('myaccount')),
            esc_html__('Sign in with the link from your email?', 'mizzey-site'),
            esc_attr(self::PARAM),
            esc_attr($token),
            esc_attr(self::USER),
            $userId,
            wp_nonce_field(self::NONCE, '_wpnonce', false, false),
            esc_html__('Sign in', 'mizzey-site')
        );
    }

    /** Whether the token is the user's live link; a live link is spent by redeeming it, by one request only. */
    public static function redeem(int $userId, string $token): bool
    {
        global $wpdb;
        $user   = $userId > 0 ? get_userdata($userId) : false;
        $stored = $user ? (string) get_user_meta($userId, self::META, true) : '';
        if (! $user instanceof \WP_User || ! self::eligible($user) || $token === '' || ! str_contains($stored, '|')) {
            return false;
        }
        [$hash, $until] = explode('|', $stored, 2);
        $live  = (int) $until >= time();
        $match = hash_equals($hash, self::hash($user, $token));
        // Spent when used, cleared when expired; a wrong token leaves the customer's live link alone. The delete names
        // the exact stored link, so of two requests holding it only one deletes a row, and only that one signs in.
        if (! $match && $live) {
            return false;
        }
        $deleted = $wpdb->delete($wpdb->usermeta, ['user_id' => $userId, 'meta_key' => self::META, 'meta_value' => $stored]); // phpcs:ignore WordPress.DB.SlowDBQuery
        wp_cache_delete($userId, 'user_meta');

        return $live && $match && $deleted === 1;
    }

    /** Customer accounts only: staff sign in with their password and two-factor (ADM-131). */
    public static function eligible(\WP_User $user): bool
    {
        return in_array('customer', (array) $user->roles, true) && ! user_can($user, 'edit_posts') && ! user_can($user, 'manage_woocommerce');
    }

    /** Bound to the account's email and password, so changing either ends the links already sent. */
    private static function hash(\WP_User $user, string $token): string
    {
        return hash_hmac('sha256', $user->ID . '|' . wc_strtolower($user->user_email) . '|' . $user->user_pass . '|' . $token, wp_salt('auth'));
    }
}

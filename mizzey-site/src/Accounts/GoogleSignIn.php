<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Accounts;

defined('ABSPATH') || exit;

/**
 * Google sign-in (AUTH-10) with one account per email across Google and manual registration (AUTH-13), as our own
 * code (D-24): OpenID Connect's authorization code flow with PKCE, a state bound to the browser by a cookie and a
 * nonce bound to the ID token, both single-use and kept ten minutes.
 *
 * - `GET mizzey/v1/google/start?redirect_to=...` sends the visitor to Google; Google returns them to
 *   `GET mizzey/v1/google/callback`, which exchanges the code with Google's token endpoint over TLS, server to server,
 *   and checks the ID token's issuer, audience, expiry and nonce (OpenID Connect Core 3.1.3.7: an ID token received
 *   straight from the token endpoint is validated by TLS in place of its signature).
 * - The account: the one already linked to that Google account; else, when Google says the email is verified, the
 *   account with that email, now linked (its email marked verified, which links its guest orders, B14; an account
 *   whose email was never verified here has its password replaced and its sessions ended first, so whoever registered
 *   it cannot keep using it); else a new customer account, with marketing consent left unset (AUTH-09).
 * - Staff accounts (any role beyond a customer) cannot sign in this way: they sign in with their password and two
 *   factors (specs/008).
 * - Off until the client ID and secret are set: the secret from `MIZZEY_GOOGLE_CLIENT_SECRET` in the configuration, or
 *   the setting.
 *
 * The designed buttons are PRE-03b's; a plain link is added to the account's sign-in and registration forms.
 *
 * Register ids: AUTH-10, AUTH-13. See specs/024-google-sign-in.
 */
final class GoogleSignIn
{
    public const CLIENT_ID     = 'mizzey_google_client_id';
    public const CLIENT_SECRET = 'mizzey_google_client_secret';
    public const SUBJECT       = '_mizzey_google_sub';
    public const COOKIE        = 'mizzey_google_state';
    public const AUTHORIZE     = 'https://accounts.google.com/o/oauth2/v2/auth';
    public const TOKEN         = 'https://oauth2.googleapis.com/token';
    private const ISSUERS      = ['https://accounts.google.com', 'accounts.google.com'];
    private const LIFETIME     = 600;

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'routes']);
        add_action('woocommerce_login_form_end', [self::class, 'button']);
        add_action('woocommerce_register_form_end', [self::class, 'button']);
        add_filter('woocommerce_general_settings', [self::class, 'settings'], 30, 1);
    }

    public static function enabled(): bool
    {
        return self::clientId() !== '' && self::secret() !== '';
    }

    public static function routes(): void
    {
        register_rest_route('mizzey/v1', '/google/start', ['methods' => 'GET', 'callback' => [self::class, 'start'], 'permission_callback' => '__return_true']);
        register_rest_route('mizzey/v1', '/google/callback', ['methods' => 'GET', 'callback' => [self::class, 'callback'], 'permission_callback' => '__return_true']);
    }

    public static function redirectUri(): string
    {
        return rest_url('mizzey/v1/google/callback');
    }

    /** @param mixed $request */
    public static function start($request): \WP_REST_Response
    {
        if (! self::enabled()) {
            return self::back(self::accountUrl(), 'unavailable');
        }
        $to       = $request instanceof \WP_REST_Request ? (string) $request->get_param('redirect_to') : '';
        $state    = self::token();
        $verifier = self::token();
        $nonce    = self::token();
        set_transient(self::key($state), ['verifier' => $verifier, 'nonce' => $nonce, 'to' => wp_validate_redirect($to, self::accountUrl())], self::LIFETIME);
        self::cookie($state, time() + self::LIFETIME);
        $url = add_query_arg(rawurlencode_deep([
            'client_id'             => self::clientId(),
            'redirect_uri'          => self::redirectUri(),
            'response_type'         => 'code',
            'scope'                 => 'openid email profile',
            'state'                 => $state,
            'nonce'                 => $nonce,
            'code_challenge'        => self::challenge($verifier),
            'code_challenge_method' => 'S256',
            'prompt'                => 'select_account',
        ]), self::AUTHORIZE);

        return self::redirect($url);
    }

    /** @param mixed $request */
    public static function callback($request): \WP_REST_Response
    {
        $state  = $request instanceof \WP_REST_Request ? (string) $request->get_param('state') : '';
        $code   = $request instanceof \WP_REST_Request ? (string) $request->get_param('code') : '';
        $cookie = isset($_COOKIE[self::COOKIE]) ? sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE])) : '';
        $saved  = $state !== '' ? get_transient(self::key($state)) : false;
        if ($state !== '') {
            delete_transient(self::key($state));
        }
        self::cookie('', time() - HOUR_IN_SECONDS);
        if (! self::enabled() || ! is_array($saved) || $cookie === '' || ! hash_equals($state, $cookie) || $code === '') {
            return self::back(self::accountUrl(), 'refused');
        }
        $claims = self::exchange($code, (string) $saved['verifier'], (string) $saved['nonce']);
        $user   = is_wp_error($claims) ? $claims : self::account($claims);
        if (is_wp_error($user)) {
            return self::back(self::accountUrl(), (string) $user->get_error_code() === 'mizzey_google_staff' ? 'staff' : 'refused');
        }
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        do_action('wp_login', $user->user_login, $user);

        return self::redirect((string) $saved['to']);
    }

    /**
     * The ID token's claims after the code is exchanged and the token checked, or why not.
     *
     * @return array<string,mixed>|\WP_Error
     */
    public static function exchange(string $code, string $verifier, string $nonce)
    {
        $answer = wp_remote_post(self::TOKEN, ['timeout' => 15, 'body' => [
            'code'          => $code,
            'client_id'     => self::clientId(),
            'client_secret' => self::secret(),
            'redirect_uri'  => self::redirectUri(),
            'grant_type'    => 'authorization_code',
            'code_verifier' => $verifier,
        ]]);
        if (is_wp_error($answer) || (int) wp_remote_retrieve_response_code($answer) !== 200) {
            return new \WP_Error('mizzey_google_token', 'token_endpoint');
        }
        $body = json_decode((string) wp_remote_retrieve_body($answer), true);

        return self::claims(is_array($body) ? (string) ($body['id_token'] ?? '') : '', $nonce);
    }

    /**
     * An ID token's claims, when its issuer, audience, expiry and nonce hold.
     *
     * @return array<string,mixed>|\WP_Error
     */
    public static function claims(string $idToken, string $nonce)
    {
        $parts  = explode('.', $idToken);
        $claims = count($parts) === 3 ? json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), true), true) : null;
        $aud    = is_array($claims) ? (array) ($claims['aud'] ?? []) : [];
        if (! is_array($claims)
            || ! in_array((string) ($claims['iss'] ?? ''), self::ISSUERS, true)
            || ! in_array(self::clientId(), $aud, true)
            || (count($aud) > 1 && (string) ($claims['azp'] ?? '') !== self::clientId())
            || (int) ($claims['exp'] ?? 0) <= time()
            || $nonce === '' || ! hash_equals($nonce, (string) ($claims['nonce'] ?? ''))
            || (string) ($claims['sub'] ?? '') === '') {
            return new \WP_Error('mizzey_google_token', 'id_token');
        }

        return $claims;
    }

    /**
     * The account the claims sign in to, found, linked or created.
     *
     * @param array<string,mixed> $claims
     * @return \WP_User|\WP_Error
     */
    public static function account(array $claims)
    {
        $sub      = (string) $claims['sub'];
        $email    = sanitize_email((string) ($claims['email'] ?? ''));
        $verified = ($claims['email_verified'] ?? false) === true || ($claims['email_verified'] ?? '') === 'true';
        $linked   = get_users(['meta_key' => self::SUBJECT, 'meta_value' => $sub, 'number' => 1]); // phpcs:ignore WordPress.DB.SlowDBQuery
        $user     = $linked[0] ?? null;
        if (! $user instanceof \WP_User) {
            if (! $verified || ! is_email($email)) {
                return new \WP_Error('mizzey_google_email', 'unverified_email');
            }
            $user = get_user_by('email', $email) ?: null;
            if ($user instanceof \WP_User && ! self::customer($user)) {
                return new \WP_Error('mizzey_google_staff', 'staff');
            }
            if ($user instanceof \WP_User) {
                if (! VerificationOnRegistration::verified($user->ID)) {
                    // Whoever registered this email here without proving it cannot keep the account.
                    wp_set_password(wp_generate_password(32, true, true), $user->ID);
                }
            } else {
                // Google has verified the email: no verification message is sent for it.
                remove_action('woocommerce_created_customer', [VerificationOnRegistration::class, 'send'], 20);
                $id = wc_create_new_customer($email, '', wp_generate_password(32, true, true), [
                    'first_name' => sanitize_text_field((string) ($claims['given_name'] ?? '')),
                    'last_name'  => sanitize_text_field((string) ($claims['family_name'] ?? '')),
                ]);
                add_action('woocommerce_created_customer', [VerificationOnRegistration::class, 'send'], 20, 1);
                if (is_wp_error($id)) {
                    return $id;
                }
                $user = get_userdata((int) $id);
                if (! $user instanceof \WP_User) {
                    return new \WP_Error('mizzey_google_account', 'account');
                }
            }
            update_user_meta($user->ID, self::SUBJECT, $sub);
            self::markVerified($user->ID);
        }
        if (! self::customer($user)) {
            return new \WP_Error('mizzey_google_staff', 'staff');
        }

        return $user;
    }

    /** A customer, and nothing more: staff sign in with their password and two factors. */
    private static function customer(\WP_User $user): bool
    {
        return array_values((array) $user->roles) === ['customer'];
    }

    private static function markVerified(int $userId): void
    {
        $class = '\\Automattic\\WooCommerce\\Internal\\CustomerEmailVerification\\EmailVerificationService';
        if (class_exists($class) && function_exists('wc_get_container')) {
            wc_get_container()->get($class)->mark_verified($userId);
        }
    }

    public static function button(): void
    {
        if (self::enabled()) {
            printf('<p class="mizzey-google-sign-in"><a class="button" href="%s">%s</a></p>', esc_url(add_query_arg('redirect_to', rawurlencode(self::accountUrl()), rest_url('mizzey/v1/google/start'))), esc_html__('Continue with Google', 'mizzey-site'));
        }
        $said = isset($_GET['mizzey-google']) ? sanitize_key(wp_unslash($_GET['mizzey-google'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a message only.
        if ($said !== '') {
            $text = $said === 'staff'
                ? __('Staff accounts sign in with their password.', 'mizzey-site')
                : __('Google sign-in did not complete. Please try again.', 'mizzey-site');
            printf('<p class="mizzey-google-sign-in-notice">%s</p>', esc_html($text));
        }
    }

    /**
     * @param mixed $settings
     * @return mixed
     */
    public static function settings($settings)
    {
        if (! is_array($settings)) {
            return $settings;
        }

        return [...$settings,
            ['title' => __('Google sign-in', 'mizzey-site'), 'type' => 'title', 'id' => 'mizzey_google_options',
                'desc' => __('The OAuth client of the store\'s Google Cloud project. Sign-in with Google is offered once both are set.', 'mizzey-site')],
            ['title' => __('Client ID', 'mizzey-site'), 'id' => self::CLIENT_ID, 'type' => 'text', 'default' => ''],
            ['title' => __('Client secret', 'mizzey-site'), 'id' => self::CLIENT_SECRET, 'type' => 'password', 'default' => '',
                'desc' => __('Left empty when it is set in the configuration file.', 'mizzey-site')],
            ['type' => 'sectionend', 'id' => 'mizzey_google_options'],
        ];
    }

    private static function clientId(): string
    {
        return trim((string) get_option(self::CLIENT_ID, ''));
    }

    private static function secret(): string
    {
        return defined('MIZZEY_GOOGLE_CLIENT_SECRET') ? (string) constant('MIZZEY_GOOGLE_CLIENT_SECRET') : trim((string) get_option(self::CLIENT_SECRET, ''));
    }

    private static function accountUrl(): string
    {
        return (string) wc_get_page_permalink('myaccount');
    }

    private static function token(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private static function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    private static function key(string $state): string
    {
        return 'mizzey_google_' . hash('sha256', $state);
    }

    private static function cookie(string $value, int $expires): void
    {
        if (! headers_sent()) {
            setcookie(self::COOKIE, $value, ['expires' => $expires, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax']);
        }
    }

    private static function back(string $url, string $why): \WP_REST_Response
    {
        return self::redirect(add_query_arg('mizzey-google', $why, $url));
    }

    private static function redirect(string $url): \WP_REST_Response
    {
        $response = new \WP_REST_Response(null, 302);
        $response->header('Location', $url);
        $response->header('Cache-Control', 'no-store');

        return $response;
    }
}

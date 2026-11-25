<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Signups;

defined('ABSPATH') || exit;

use Corex\Http\PublicSubmission;
use MizzeySite\Security\SignupCheck;

/**
 * Where a sign-up arrives and where a subscriber leaves. Each route only reads the request and answers; the work is
 * SignupService's.
 *
 * - `GET mizzey/v1/signup/token`: a fresh form token for the script, never cached, because the page may be served
 *   from a cache every visitor shares and a token there is single-use.
 * - `POST mizzey/v1/signup`: the storefront's script. It declares itself a public submission (CoreX), so whatever
 *   closes the public site's forms closes it too. Answers `{ state, email }`: 200 for a sign-up, 422 for an address
 *   that is not one, 403 for a form whose token does not check, 429 past the limit, 500 when the record could not be
 *   written.
 * - The same form posted to its own page without JavaScript: handled before the page is drawn, and the page shows the
 *   answer in the section (Banners::renderSignup() reads result()).
 * - `?mizzey_leave=<token>` on the home page, in the subscriber's language: a small page asks for a press of a button
 *   (a link that left at once would be followed by mail scanners), then confirms. A mail program's one-click request
 *   (List-Unsubscribe-Post) is a POST to the same address and leaves at once.
 *
 * Register id: HOME-09. See specs/066-stage-2-shop-front, slice 5.
 */
final class SignupRoutes
{
    public const NS    = 'mizzey/v1';
    public const LEAVE = 'mizzey_leave';

    /** @var array{state:string,email:string,optin:bool,typed:string}|null The answer to a form posted without JavaScript. */
    private static ?array $result = null;

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'routes']);
        add_action('template_redirect', [self::class, 'posted'], 5);
        add_action('template_redirect', [self::class, 'leavePage'], 4);
    }

    public static function routes(): void
    {
        register_rest_route(self::NS, '/signup/token', [
            'methods'                => 'GET',
            'callback'               => [self::class, 'token'],
            'permission_callback'    => '__return_true',
            PublicSubmission::MARKER => true,
        ]);
        register_rest_route(self::NS, '/signup', [
            'methods'                => 'POST',
            'callback'               => [self::class, 'submit'],
            'permission_callback'    => '__return_true',
            PublicSubmission::MARKER => true,
            'args'                   => [
                'email'   => ['type' => 'string', 'required' => true],
                'consent' => ['type' => 'boolean', 'default' => false],
                'lang'    => ['type' => 'string', 'default' => ''],
                'token'   => ['type' => 'string', 'default' => ''],
                'trap'    => ['type' => 'string', 'default' => ''],
            ],
        ]);
    }

    /**
     * A fresh form token, for the storefront's script. The page itself may come from a cache shared by every visitor,
     * so the script never relies on the token printed in it: it asks here, and this answer is never cached.
     */
    public static function token(): \WP_REST_Response
    {
        $response = new \WP_REST_Response(['token' => SignupCheck::issue(SignupService::ROUTE)], 200);
        $response->header('Cache-Control', implode(', ', ['no-store', 'private']));

        return $response;
    }

    public static function submit(\WP_REST_Request $request): \WP_REST_Response
    {
        $answer = SignupService::submit([
            'email'   => (string) $request['email'],
            'consent' => (bool) $request['consent'],
            'lang'    => (string) $request['lang'],
            'token'   => (string) $request['token'],
            'trap'    => (string) $request['trap'],
        ]);
        if (in_array($answer['state'], ['limited', 'failed'], true)) {
            // The form's token was spent on this attempt: the next one, a few minutes later, needs a new one.
            $answer['token'] = SignupCheck::issue(SignupService::ROUTE);
        }
        $status   = ['ok' => 200, 'invalid' => 422, 'expired' => 403, 'limited' => 429, 'failed' => 500][$answer['state']] ?? 400;
        $response = new \WP_REST_Response($answer, $status);
        $response->header('Cache-Control', 'no-store');

        return $response;
    }

    /** A sign-up form posted to its page without JavaScript. */
    public static function posted(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- SignupService verifies the form's token.
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ! isset($_POST[SignupService::FIELD_FORM])) {
            return;
        }
        $field = static fn (string $name): string => isset($_POST[$name]) && is_string($_POST[$name]) ? sanitize_text_field(wp_unslash($_POST[$name])) : '';
        $optin = $field(SignupService::FIELD_OPTIN) !== '';
        // phpcs:enable
        $answer = SignupService::submit([
            'email'   => $field(SignupService::FIELD_EMAIL),
            'consent' => $optin,
            'lang'    => (string) apply_filters('wpml_current_language', null),
            'token'   => $field(SignupService::FIELD_TOKEN),
            'trap'    => $field(SignupService::FIELD_TRAP),
        ]);
        if ($answer['state'] === 'expired') {
            // The token came from a cached or old page, or was already used: nothing is lost. The page comes back with
            // the address and the box as they were, a fresh token, and words asking for one more press.
            $answer['state'] = 'again';
        }
        self::$result = $answer + ['optin' => $optin, 'typed' => mb_substr($field(SignupService::FIELD_EMAIL), 0, 191)];
        nocache_headers();
    }

    /** @return array{state:string,email:string,optin:bool,typed:string}|null */
    public static function result(): ?array
    {
        return self::$result;
    }

    /** The small page a leave link opens, in the language of its address. */
    public static function leavePage(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- the token is the secret; nothing else is read.
        if (! isset($_GET[self::LEAVE])) {
            return;
        }
        $token = is_string($_GET[self::LEAVE]) ? sanitize_key(wp_unslash($_GET[self::LEAVE])) : '';
        // phpcs:enable
        // The token is in this page's address: nothing on it may carry that address elsewhere (LeavePage::quiet()).
        LeavePage::quiet();
        try {
            $record = SignupStore::byToken($token);
            $state  = $record === null ? 'unknown' : ($record['status'] === SignupStore::LEFT ? 'left' : 'ask');
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
                if ($record !== null) {
                    $record = SignupService::leave($token) ?? $record;
                    $state  = 'left';
                }
                // A mail program's one-click request (RFC 8058) needs no page: its answer is the status alone.
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the token is the secret.
                if (! isset($_POST['mizzey_leave_confirm'])) {
                    status_header($record === null ? 404 : 200);
                    nocache_headers();
                    exit;
                }
            }
        } catch (SignupStoreError) {
            $record = null;
            $state  = 'failed';
        }
        LeavePage::render($state, $record, $token);
        exit;
    }
}

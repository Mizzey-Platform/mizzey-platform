<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Security;

defined('ABSPATH') || exit;

use Throwable;
use WP_Error;
use WP_REST_Request;

/**
 * Where accounts are created, and the check applied at each place.
 *
 * Every WooCommerce route creates accounts through `wc_create_new_customer()`, which asks `woocommerce_registration_errors`
 * first. That filter is the guard: an anonymous insertion is allowed only with a permit from a route adapter that
 * verified the request, one permit per insertion and for its own email. The adapters identify the route from the native
 * handler that is running, never from the token or from a posted field alone:
 *
 * - the account page: `woocommerce_process_registration_errors`, reached after WooCommerce's own nonce check;
 * - the block checkout: `rest_request_before_callbacks` for the Store API checkout handler, which rejects an invalid
 *   check before the handler creates or updates any order, and only when WooCommerce would create an account;
 * - the order-received offer: inside the offer block's render callback ({@see SignupOfferRender});
 * - WordPress's registration: `registration_errors`.
 *
 * Staff keep their native authorisation (`create_customers`, `create_users`); WP-CLI is trusted. Anything else fails
 * closed, which keeps the classic checkout's still-registered handlers from creating accounts.
 *
 * Register id: AUTH-15. See specs/009-sign-in-protection, FR-003 and FR-004.
 */
final class SignupRoutes
{
    public const STORE_NAMESPACE = 'mizzey-signup';
    public const ERROR           = 'mizzey_signup_check';
    public const OFFER_BLOCK     = 'woocommerce/order-confirmation-create-account';

    private const SCRIPT = 'mizzey-signup-check';

    /** @var list<array{request:WP_REST_Request,permit:?string}> One frame per REST request being dispatched. */
    private static array $frames = [];

    /** @var list<string> Emails a non-REST adapter verified, each good for one insertion. */
    private static array $permits = [];

    private static int $offerDepth = 0;

    public static function register(): void
    {
        add_filter('woocommerce_process_registration_errors', [self::class, 'account'], 10, 4);
        add_action('wp_loaded', [self::class, 'wrapAccountHandler'], 19);
        add_filter('registration_errors', [self::class, 'wordpress'], 10, 3);
        add_filter('woocommerce_registration_errors', [self::class, 'guard'], 999, 3);
        add_filter('rest_request_before_callbacks', [self::class, 'restBefore'], 10, 3);
        add_filter('rest_request_after_callbacks', [self::class, 'restAfter'], 10, 3);
        add_filter('rest_endpoints', [self::class, 'wrapCheckoutHandler']);
        add_filter('register_block_type_args', [self::class, 'wrapOffer'], 10, 2);
        add_filter('render_block_' . self::OFFER_BLOCK, [self::class, 'offerToken']);
        add_action('woocommerce_register_form', [self::class, 'accountFields']);
        add_action('register_form', [self::class, 'wordpressFields']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
        add_action('woocommerce_blocks_loaded', [self::class, 'registerCheckoutData']);
    }

    // ---- Adapters ------------------------------------------------------------------------------------------

    public static function account(WP_Error $error, mixed $username, mixed $password, mixed $email): WP_Error
    {
        if ($error->has_errors()) {
            return $error;
        }
        if (SignupCheck::verify('account', self::posted(SignupCheck::FIELD_TOKEN), self::posted(SignupCheck::FIELD_TRAP))) {
            self::grant('account', (string) $email);
        } else {
            $error->add(self::ERROR, SignupCheck::message());
        }

        return $error;
    }

    public static function wordpress(WP_Error $errors, mixed $login, mixed $email): WP_Error
    {
        if (! SignupCheck::verify('wordpress', self::posted(SignupCheck::FIELD_TOKEN), self::posted(SignupCheck::FIELD_TRAP))) {
            $errors->add(self::ERROR, SignupCheck::message());
        }

        return $errors;
    }

    /**
     * Run WooCommerce's account-page registration with its own permits: a permit its adapter grants is gone when the
     * handler returns, even when a later validation step refused the registration.
     */
    public static function wrapAccountHandler(): void
    {
        $callback = ['WC_Form_Handler', 'process_registration'];
        $priority = has_action('wp_loaded', $callback);
        if ($priority === false) {
            return;
        }
        remove_action('wp_loaded', $callback, $priority);
        add_action('wp_loaded', [self::class, 'processAccountRegistration'], $priority);
    }

    public static function processAccountRegistration(): void
    {
        $previous      = self::$permits;
        self::$permits = [];
        try {
            \WC_Form_Handler::process_registration();
        } finally {
            self::$permits = $previous;
        }
    }

    /**
     * @param mixed $response An earlier result, kept if it is already an error.
     * @param array<string,mixed> $handler
     */
    public static function restBefore(mixed $response, array $handler, WP_REST_Request $request): mixed
    {
        $depth          = count(self::$frames);
        self::$frames[] = ['request' => $request, 'permit' => null];
        try {
            if (is_wp_error($response) || ! self::isCheckoutHandler($handler) || $request->get_method() !== 'POST') {
                return $response;
            }
            if (! self::checkoutCreatesAccount($request)) {
                return $response;
            }
            $extensions = $request['extensions'];
            $signup     = is_array($extensions) && is_array($extensions[self::STORE_NAMESPACE] ?? null) ? $extensions[self::STORE_NAMESPACE] : [];
            if (! SignupCheck::verify('checkout', $signup['token'] ?? null, $signup['trap'] ?? null)) {
                return new WP_Error(self::ERROR, SignupCheck::message(), ['status' => 400]);
            }
            $billing = $request['billing_address'];
            self::$frames[$depth]['permit'] = self::normalise(is_array($billing) ? (string) ($billing['email'] ?? '') : '');

            return $response;
        } catch (Throwable $error) {
            array_splice(self::$frames, $depth);
            throw $error;
        }
    }

    /**
     * @param array<string,mixed> $handler
     */
    public static function restAfter(mixed $response, array $handler, WP_REST_Request $request): mixed
    {
        for ($i = count(self::$frames) - 1; $i >= 0; $i--) {
            if (self::$frames[$i]['request'] === $request) {
                array_splice(self::$frames, $i);
                break;
            }
        }

        return $response;
    }

    /**
     * Wrap the Store API checkout handler ({@see SignupRestCallback}), the only handler a permit is granted for.
     *
     * @param array<string,mixed> $endpoints
     *
     * @return array<string,mixed>
     */
    public static function wrapCheckoutHandler(array $endpoints): array
    {
        foreach ($endpoints as $route => $handlers) {
            if (! is_array($handlers)) {
                continue;
            }
            foreach ($handlers as $key => $handler) {
                if (is_array($handler) && self::isCheckoutHandler($handler) && ! ($handler['callback'] instanceof SignupRestCallback)) {
                    $endpoints[$route][$key]['callback'] = new SignupRestCallback($handler['callback']);
                }
            }
        }

        return $endpoints;
    }

    /**
     * Run the checkout handler; when it ends, however it ends, its permit is spent. Frames left above it by nested
     * requests that threw are removed; if the handler itself throws, its own frame goes too, because
     * `rest_request_after_callbacks` will not run for it.
     */
    public static function callCheckout(callable $callback, WP_REST_Request $request): mixed
    {
        $frame = null;
        for ($i = count(self::$frames) - 1; $i >= 0; $i--) {
            if (self::$frames[$i]['request'] === $request) {
                $frame = $i;
                break;
            }
        }
        $completed = false;
        try {
            $result    = $callback($request);
            $completed = true;

            return $result;
        } finally {
            if ($frame !== null && isset(self::$frames[$frame]) && self::$frames[$frame]['request'] === $request) {
                array_splice(self::$frames, $frame + ($completed ? 1 : 0));
                if ($completed) {
                    self::$frames[$frame]['permit'] = null;
                }
            }
        }
    }

    /**
     * @param array<string,mixed> $args
     *
     * @return array<string,mixed>
     */
    public static function wrapOffer(array $args, string $name): array
    {
        if ($name === self::OFFER_BLOCK && isset($args['render_callback']) && is_callable($args['render_callback'])
            && ! $args['render_callback'] instanceof SignupOfferRender) {
            $args['render_callback'] = new SignupOfferRender($args['render_callback']);
        }

        return $args;
    }

    /**
     * Run the offer's render callback with the order-received context set; restored however it ends.
     */
    public static function insideOrderReceived(callable $render): mixed
    {
        self::$offerDepth++;
        try {
            return $render();
        } finally {
            self::$offerDepth--;
        }
    }

    // ---- The guard -----------------------------------------------------------------------------------------

    public static function guard(WP_Error $errors, mixed $username, mixed $email): WP_Error
    {
        if ($errors->has_errors() || self::trusted()) {
            return $errors;
        }
        $email = self::normalise((string) $email);

        if (self::$frames !== []) {
            $top = array_key_last(self::$frames);
            if ($email !== '' && self::$frames[$top]['permit'] === $email) {
                self::$frames[$top]['permit'] = null;

                return $errors;
            }
        } elseif (self::$offerDepth > 0) {
            $posted = self::normalise(sanitize_email((string) self::posted('email')));
            if ($email !== '' && $posted === $email
                && SignupCheck::verify('order-received', self::posted(SignupCheck::FIELD_TOKEN), self::posted(SignupCheck::FIELD_TRAP))) {
                return $errors;
            }
        } elseif ($email !== '' && in_array($email, self::$permits, true)) {
            self::$permits = array_values(array_diff(self::$permits, [$email]));

            return $errors;
        }

        $errors->add(self::ERROR, SignupCheck::message());

        return $errors;
    }

    /**
     * Record that an adapter verified a request creating an account for this email, good for one insertion.
     */
    public static function grant(string $route, string $email): void
    {
        $email = self::normalise($email);
        if ($email !== '' && in_array($route, SignupCheck::ROUTES, true)) {
            self::$permits[] = $email;
        }
    }

    /**
     * How many REST frames are open, and whether the top one holds a permit. For the scenarios.
     *
     * @return array{frames:int,permit:bool}
     */
    public static function openFrames(): array
    {
        $top = self::$frames === [] ? null : self::$frames[array_key_last(self::$frames)];

        return ['frames' => count(self::$frames), 'permit' => $top !== null && $top['permit'] !== null];
    }

    public static function clearPermits(): void
    {
        self::$permits = [];
    }

    /**
     * Whether the Store API checkout would create an account for this request, by WooCommerce's own rules
     * (`Checkout::should_create_customer_account()`).
     *
     * @param array<string,mixed>|\ArrayAccess<string,mixed> $request
     */
    public static function checkoutCreatesAccount(array|\ArrayAccess $request): bool
    {
        if (is_user_logged_in() || ! function_exists('WC')) {
            return false;
        }
        $checkout = WC()->checkout();
        if (filter_var($checkout->is_registration_enabled(), FILTER_VALIDATE_BOOLEAN) !== true) {
            return false;
        }

        return filter_var($checkout->is_registration_required(), FILTER_VALIDATE_BOOLEAN) === true
            || filter_var($request['create_account'] ?? false, FILTER_VALIDATE_BOOLEAN) === true;
    }

    // ---- Rendering -----------------------------------------------------------------------------------------

    public static function accountFields(): void
    {
        echo SignupCheck::fields('account'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where built.
    }

    public static function wordpressFields(): void
    {
        echo SignupCheck::fields('wordpress'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where built.
    }

    /**
     * The offer's form is drawn in the browser; its token travels beside the block for the script to add.
     */
    public static function offerToken(string $content): string
    {
        return $content . sprintf('<span hidden data-mizzey-signup-offer="%s"></span>', esc_attr(SignupCheck::issue('order-received')));
    }

    public static function enqueue(): void
    {
        $checkout = function_exists('is_checkout') && is_checkout() && ! is_wc_endpoint_url('order-received');
        $received = function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received');
        if (! $checkout && ! $received) {
            return;
        }
        // Written out, not derived from __FILE__: under the junction the site is mounted through, a derived path can
        // resolve to the real target instead of the plugin slug (see Localisation::loadPluginTextdomain()).
        $file = WP_PLUGIN_DIR . '/mizzey-site/assets/signup-check.js';
        wp_enqueue_script(self::SCRIPT, plugins_url('mizzey-site/assets/signup-check.js'), ['wp-data'], is_readable($file) ? (string) filemtime($file) : null, true);
        wp_localize_script(self::SCRIPT, 'mizzeySignup', [
            'namespace' => self::STORE_NAMESPACE,
            'token'     => $checkout ? SignupCheck::issue('checkout') : '',
            'tokenName' => SignupCheck::FIELD_TOKEN,
            'trapName'  => SignupCheck::FIELD_TRAP,
            'trapLabel' => __('Leave this field empty', 'mizzey-site'),
        ]);
    }

    public static function registerCheckoutData(): void
    {
        if (! function_exists('woocommerce_store_api_register_endpoint_data')) {
            return;
        }
        woocommerce_store_api_register_endpoint_data([
            'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema::IDENTIFIER,
            'namespace'       => self::STORE_NAMESPACE,
            'schema_callback' => static fn (): array => [
                'token' => ['type' => 'string', 'context' => ['view', 'edit'], 'readonly' => false],
                'trap'  => ['type' => 'string', 'context' => ['view', 'edit'], 'readonly' => false],
            ],
            'data_callback'   => static fn (): array => [],
            'schema_type'     => ARRAY_A,
        ]);
    }

    // ---- Reporting -----------------------------------------------------------------------------------------

    /**
     * Whether every adapter, the guard and the rendering are attached, and the offer's callback is wrapped.
     */
    public static function attached(): bool
    {
        $hooks = [
            ['woocommerce_process_registration_errors', 'account'],
            ['registration_errors', 'wordpress'],
            ['woocommerce_registration_errors', 'guard'],
            ['rest_request_before_callbacks', 'restBefore'],
            ['rest_request_after_callbacks', 'restAfter'],
            ['woocommerce_register_form', 'accountFields'],
            ['register_form', 'wordpressFields'],
            ['wp_enqueue_scripts', 'enqueue'],
            ['render_block_' . self::OFFER_BLOCK, 'offerToken'],
        ];
        foreach ($hooks as [$hook, $method]) {
            if (has_filter($hook, [self::class, $method]) === false) {
                return false;
            }
        }
        return self::offerWrapped() && has_filter('rest_endpoints', [self::class, 'wrapCheckoutHandler']) !== false
            && has_action('wp_loaded', [self::class, 'processAccountRegistration']) !== false;
    }

    private static function offerWrapped(): bool
    {
        $offer = \WP_Block_Type_Registry::get_instance()->get_registered(self::OFFER_BLOCK);

        return $offer !== null && $offer->render_callback instanceof SignupOfferRender;
    }

    /**
     * The public account writers known on this stack, and how each is protected.
     *
     * @return array<string,string> writer => checked | closed | inactive | unchecked
     */
    public static function writers(): array
    {
        $guarded = has_filter('woocommerce_registration_errors', [self::class, 'guard']) !== false;
        $stock   = get_option('woocommerce_customer_stock_notifications_allow_signups', 'no') === 'yes'
            && get_option('woocommerce_customer_stock_notifications_create_account_on_signup', 'no') === 'yes';

        return [
            'account page'           => has_filter('woocommerce_process_registration_errors', [self::class, 'account']) !== false && $guarded ? 'checked' : 'unchecked',
            'block checkout'         => has_filter('rest_request_before_callbacks', [self::class, 'restBefore']) !== false && $guarded ? 'checked' : 'unchecked',
            'order-received offer'   => self::offerWrapped() && $guarded ? 'checked' : 'unchecked',
            'wordpress registration' => ! get_option('users_can_register') ? 'inactive' : (has_filter('registration_errors', [self::class, 'wordpress']) !== false ? 'checked' : 'unchecked'),
            'classic checkout'       => $guarded ? 'closed' : 'unchecked',
            'stock notifications'    => $stock ? 'unchecked' : 'inactive',
        ];
    }

    // ---- Helpers -------------------------------------------------------------------------------------------

    private static function trusted(): bool
    {
        if (defined('WP_CLI') && WP_CLI && apply_filters('mizzey_signup_trust_cli', true)) {
            return true;
        }

        return current_user_can('create_customers') || current_user_can('create_users');
    }

    /**
     * @param array<string,mixed> $handler
     */
    private static function isCheckoutHandler(array $handler): bool
    {
        $callback = $handler['callback'] ?? null;
        if ($callback instanceof SignupRestCallback) {
            $callback = $callback->original();
        }

        return is_array($callback)
            && ($callback[0] ?? null) instanceof \Automattic\WooCommerce\StoreApi\Routes\V1\Checkout
            && ($callback[1] ?? null) === 'get_response';
    }

    private static function posted(string $name): mixed
    {
        // phpcs:ignore WordPress.Security.NonceVerification -- each route's own nonce is checked natively before this runs.
        return isset($_POST[$name]) ? wp_unslash($_POST[$name]) : null;
    }

    private static function normalise(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * For the security check: a probe that must not survive. Kept here so the claim's storage stays private.
     */
    public static function selfTest(): bool
    {
        $probe = null;
        $ok    = false;
        try {
            $fresh = SignupCheck::issue('account');
            $aged  = SignupCheck::issue('account', time() - 10);
            $probe = SignupCheck::inspect('account', $aged, '');
            $ok    = $probe !== null
                && SignupCheck::inspect('account', $fresh, '') === null
                && SignupCheck::verify('account', $aged, '')
                && ! SignupCheck::verify('account', $aged, '');
        } catch (Throwable) {
            $ok = false;
        } finally {
            if ($probe !== null) {
                try {
                    SignupCheck::forget($probe['nonce']);
                } catch (Throwable) {
                    $ok = false;
                }
            }
        }

        return $ok;
    }
}

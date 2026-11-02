<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Emails;

defined('ABSPATH') || exit;

/**
 * The transactional email service (INT-05): every email the store sends leaves through the provider's SMTP relay.
 *
 * - **Provider-neutral.** Every transactional provider offers an SMTP relay, so the provider is chosen by the client
 *   (I-10) and set by the server, not by code. The relay is named by constants in the server's `wp-config.php`:
 *   `MIZZEY_SMTP_HOST`, `MIZZEY_SMTP_PORT` (587), `MIZZEY_SMTP_SECURE` (`tls` or `ssl`), `MIZZEY_SMTP_USER`,
 *   `MIZZEY_SMTP_PASSWORD`. The password is never in the database, the repository or a screen. With no host set,
 *   WordPress sends as it always has; staging captures all mail before it reaches this point.
 * - **The sender** is WooCommerce's ("From" address and name in WooCommerce, Settings, Emails) for every email, so
 *   WordPress's own messages do not leave as `wordpress@`. The domain's SPF, DKIM and DMARC are the provider's set-up
 *   on the client's domain (OD-18).
 * - **Failures** are written to the `mizzey-email` log with addresses masked, which the monitor raises to operations
 *   (specs/039).
 * - Settings, Email delivery shows the relay in use and sends a test message to the person asking.
 *
 * Register ids: INT-05. See specs/045-email-transport.
 */
final class Transport
{
    public const SOURCE = 'mizzey-email';
    public const PAGE   = 'mizzey-email-delivery';
    public const ACTION = 'mizzey_email_test';

    public static function register(): void
    {
        add_action('phpmailer_init', [self::class, 'configure'], 20, 1);
        add_filter('wp_mail_from', [self::class, 'from'], 20);
        add_filter('wp_mail_from_name', [self::class, 'fromName'], 20);
        add_action('wp_mail_failed', [self::class, 'failed'], 10, 1);
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_post_' . self::ACTION, [self::class, 'test']);
    }

    /** @return array{host:string,port:int,secure:string,user:string,password:string} */
    public static function relay(): array
    {
        $get = static fn (string $name): string => defined($name) ? trim((string) constant($name)) : '';
        $secure = strtolower($get('MIZZEY_SMTP_SECURE'));

        return [
            'host'     => $get('MIZZEY_SMTP_HOST'),
            'port'     => (int) ($get('MIZZEY_SMTP_PORT') !== '' ? $get('MIZZEY_SMTP_PORT') : 587),
            'secure'   => in_array($secure, ['tls', 'ssl', 'none'], true) ? $secure : 'tls',
            'user'     => $get('MIZZEY_SMTP_USER'),
            'password' => $get('MIZZEY_SMTP_PASSWORD'),
        ];
    }

    public static function configured(): bool
    {
        return self::relay()['host'] !== '';
    }

    /** @param mixed $mailer PHPMailer, by reference from WordPress. */
    public static function configure($mailer): void
    {
        if (! self::configured() || ! is_object($mailer) || ! method_exists($mailer, 'isSMTP')) {
            return;
        }
        $relay = self::relay();
        $mailer->isSMTP();
        $mailer->Host        = $relay['host'];
        $mailer->Port        = $relay['port'];
        $mailer->SMTPSecure  = $relay['secure'] === 'none' ? '' : $relay['secure'];
        $mailer->SMTPAutoTLS = $relay['secure'] !== 'none';
        $mailer->SMTPAuth    = $relay['user'] !== '';
        $mailer->Username    = $relay['user'];
        $mailer->Password    = $relay['password'];
        $mailer->Timeout     = 15;
        $mailer->Sender      = self::address();
    }

    /** WooCommerce's sender address, or the site's admin address. */
    public static function address(): string
    {
        $woo = sanitize_email((string) get_option('woocommerce_email_from_address', ''));

        return is_email($woo) ? $woo : (string) get_option('admin_email');
    }

    /**
     * @param mixed $from
     * @return mixed
     */
    public static function from($from)
    {
        return is_string($from) && str_starts_with(strtolower($from), 'wordpress@') ? self::address() : $from;
    }

    /**
     * @param mixed $name
     * @return mixed
     */
    public static function fromName($name)
    {
        $woo = trim((string) get_option('woocommerce_email_from_name', ''));

        return $name === 'WordPress' && $woo !== '' ? wp_specialchars_decode($woo, ENT_QUOTES) : $name;
    }

    /** @param mixed $error */
    public static function failed($error): void
    {
        if (! $error instanceof \WP_Error || ! function_exists('wc_get_logger')) {
            return;
        }
        $why = (string) preg_replace('/[\w.+-]+@[\w-]+(?:\.[\w-]+)+/', '[email]', wp_strip_all_tags($error->get_error_message()));
        wc_get_logger()->error('mail not sent: ' . mb_substr($why, 0, 300), ['source' => self::SOURCE]);
    }

    public static function menu(): void
    {
        add_options_page(__('Email delivery', 'mizzey-site'), __('Email delivery', 'mizzey-site'), 'manage_options', self::PAGE, [self::class, 'render']);
    }

    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $relay = self::relay();
        echo '<div class="wrap"><h1>' . esc_html__('Email delivery', 'mizzey-site') . '</h1>';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the outcome of the test, shown back.
        $sent = isset($_GET['mizzey_test']) ? sanitize_key(wp_unslash((string) $_GET['mizzey_test'])) : '';
        if ($sent !== '') {
            printf('<div class="notice notice-%s"><p>%s</p></div>', $sent === 'sent' ? 'success' : 'error', esc_html($sent === 'sent' ? __('The test message was handed to the relay. Check the inbox.', 'mizzey-site') : __('The test message was not sent. The reason is in WooCommerce, Status, Logs (mizzey-email).', 'mizzey-site')));
        }
        printf(
            '<p>%s</p><table class="form-table"><tr><th scope="row">%s</th><td>%s</td></tr><tr><th scope="row">%s</th><td>%s</td></tr></table>',
            esc_html__('The relay is set in the server configuration, not here. Its password is never shown.', 'mizzey-site'),
            esc_html__('Relay', 'mizzey-site'),
            esc_html(self::configured() ? sprintf('%s:%d (%s)', $relay['host'], $relay['port'], $relay['secure']) : __('None: WordPress sends mail itself', 'mizzey-site')),
            esc_html__('Sender', 'mizzey-site'),
            esc_html(self::address())
        );
        printf('<form method="post" action="%s">', esc_url(admin_url('admin-post.php')));
        printf('<input type="hidden" name="action" value="%s" />', esc_attr(self::ACTION));
        wp_nonce_field(self::ACTION);
        submit_button(__('Send me a test message', 'mizzey-site'), 'secondary');
        echo '</form></div>';
    }

    public static function test(): void
    {
        if (! current_user_can('manage_options') || ! check_admin_referer(self::ACTION)) {
            wp_die(esc_html__('You cannot do that.', 'mizzey-site'), '', ['response' => 403]);
        }
        $user = wp_get_current_user();
        $ok   = wp_mail($user->user_email, __('Mizzey: email delivery test', 'mizzey-site'), __('This message was sent through the store\'s email relay. If you can read it, delivery works.', 'mizzey-site'));
        wp_safe_redirect(add_query_arg('mizzey_test', $ok ? 'sent' : 'failed', admin_url('options-general.php?page=' . self::PAGE)));
        exit;
    }
}

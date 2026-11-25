<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Signups;

defined('ABSPATH') || exit;

/**
 * The sign-up welcome (HOME-09): a WooCommerce email sent to the address that signed up on the home page, in the
 * language it signed up in (Welcome switches it), confirming the sign-up and giving the leave link. The link is also
 * named in the List-Unsubscribe headers, so a mail program can offer leaving in one click (SignupRoutes accepts that
 * request). Staff can change its subject and heading, or switch it off, in WooCommerce's email settings.
 *
 * Register id: HOME-09. See specs/066-stage-2-shop-front, slice 5.
 */
final class WelcomeEmail extends \WC_Email
{
    public const ID = 'mizzey_signup_welcome';

    /** @var array<string,mixed> */
    private array $record = [];

    public function __construct()
    {
        $this->id             = self::ID;
        $this->customer_email = true;
        $this->title          = __('Sign-up welcome', 'mizzey-site');
        $this->description    = __('Sent once to an address that signs up on the home page, in its language, with the link to leave the list.', 'mizzey-site');
        parent::__construct();
    }

    public function get_default_subject(): string
    {
        return __('You are on the Mizzey list', 'mizzey-site');
    }

    public function get_default_heading(): string
    {
        return __('Welcome to the list', 'mizzey-site');
    }

    /** A subject staff set goes through WooCommerce's own path; the default follows the language of the moment. */
    public function get_subject()
    {
        if (trim((string) ($this->settings['subject'] ?? '')) !== '') {
            return parent::get_subject();
        }

        return apply_filters('woocommerce_email_subject_' . $this->id, $this->format_string($this->get_default_subject()), $this->object, $this);
    }

    public function get_heading()
    {
        if (trim((string) ($this->settings['heading'] ?? '')) !== '') {
            return parent::get_heading();
        }

        return apply_filters('woocommerce_email_heading_' . $this->id, $this->format_string($this->get_default_heading()), $this->object, $this);
    }

    /** The language is the sign-up's, set by Welcome before sending; not the site's, as WC_Email would set. */
    public function setup_locale()
    {
    }

    public function restore_locale()
    {
    }

    /** @param array<string,mixed> $record */
    public function trigger(array $record): bool
    {
        $this->record    = $record;
        $this->object    = null;
        $this->recipient = (string) ($record['email'] ?? '');

        return $this->is_enabled() && $this->get_recipient() !== ''
            && (bool) $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
    }

    public function get_headers()
    {
        $headers = (string) parent::get_headers();
        $leave   = $this->leaveUrl();
        if ($leave !== '') {
            // RFC 2369 and RFC 8058: the leave link, and that one POST to it leaves.
            $headers .= sprintf('%s: <%s>', 'List-Unsubscribe', $leave) . "\r\n" . sprintf('%s: %s', 'List-Unsubscribe-Post', 'List-Unsubscribe=One-Click') . "\r\n";
        }

        return $headers;
    }

    private function leaveUrl(): string
    {
        return $this->record === [] ? '' : SignupService::leaveUrl($this->record);
    }

    /** @return list<string> The lines of the message, in the language of the moment, before the leave link. */
    private function lines(): array
    {
        $lines = [
            sprintf(
                /* translators: %s: the email address that signed up. */
                __('Thank you for signing up. This note confirms that %s is on the Mizzey list.', 'mizzey-site'),
                (string) ($this->record['email'] ?? '')
            ),
        ];
        if ((int) ($this->record['consent'] ?? 0) === 1) {
            $lines[] = __('You also asked us to email you news and offers.', 'mizzey-site');
        }
        $lines[] = __('If you did not sign up, or you want to stop, you can leave the list here:', 'mizzey-site');

        return $lines;
    }

    public function get_content_html(): string
    {
        $leave = $this->leaveUrl();
        ob_start();
        do_action('woocommerce_email_header', $this->get_heading(), $this);
        foreach ($this->lines() as $line) {
            printf('<p>%s</p>', esc_html($line));
        }
        printf('<p><a href="%1$s">%2$s</a></p>', esc_url($leave), esc_html__('Leave the list', 'mizzey-site'));
        do_action('woocommerce_email_footer', $this);

        return (string) ob_get_clean();
    }

    public function get_content_plain(): string
    {
        return implode("\n\n", [...$this->lines(), $this->leaveUrl()]);
    }
}

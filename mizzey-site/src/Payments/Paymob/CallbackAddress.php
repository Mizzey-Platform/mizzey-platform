<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Payments\Paymob;

defined('ABSPATH') || exit;

/**
 * The address Paymob calls back. The plugin registers `/?wc-api=paymob_callback`, which sits on the site's root path;
 * a host that gates the site by path (staging's access gate) cannot let that through without opening every page.
 * WooCommerce answers the same action at `/wc-api/paymob_callback/`, so each payment Paymob is asked to take names
 * that address for its notice and its return. It is built from the site's stored address, never a language one.
 *
 * Register ids: PAY-04, PAY-13. See specs/028-payments.
 */
final class CallbackAddress
{
    public static function register(): void
    {
        add_filter('paymob_intention_data', [self::class, 'intention'], 10, 1);
    }

    public static function url(): string
    {
        return untrailingslashit((string) get_option('home')) . '/wc-api/paymob_callback/';
    }

    /**
     * @param mixed $data
     * @return mixed
     */
    public static function intention($data)
    {
        if (! is_array($data)) {
            return $data;
        }
        $data['notification_url'] = self::url();
        $data['redirection_url']  = self::url();

        return $data;
    }
}

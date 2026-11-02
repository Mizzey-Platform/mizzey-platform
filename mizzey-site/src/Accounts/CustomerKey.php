<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Accounts;

defined('ABSPATH') || exit;

/**
 * One customer's contact details compared as one, however they were typed.
 *
 * - A mobile number: its Egyptian form (`01` and nine digits), from any spelling: spaces, dashes, brackets, dots, the
 *   `+20` and `0020` prefixes, Arabic-Indic and Eastern Arabic-Indic digits (an Arabic keyboard's), and the direction
 *   marks a number copied from right-to-left text carries.
 * - An email: its mailbox, lower case, with a `+tag` removed, and for Gmail the dots removed and `googlemail.com` read
 *   as `gmail.com`, so the spellings that reach one inbox are one key. A key for comparing, never an address to write to.
 *
 * Every order keeps its billing and shipping mobile number in the Egyptian form and its billing email's key
 * (`_mizzey_email_key`), whatever wrote it (the checkout, staff, the REST API), so a customer's earlier orders are
 * found under another spelling (BR-001, PROMO-09) and staff find an order by the number however it is typed (ADM-157).
 *
 * Register ids: AUTH-02, BR-001, PROMO-09, ADM-157. See specs/017-accounts, specs/016-launch-rules.
 */
final class CustomerKey
{
    public const EMAIL_META = '_mizzey_email_key';
    private const GMAIL     = ['gmail.com', 'googlemail.com'];

    public static function register(): void
    {
        add_action('woocommerce_before_order_object_save', [self::class, 'onOrderSave'], 10, 1);
        add_filter('woocommerce_order_list_table_prepare_items_query_args', [self::class, 'orderSearch'], 5, 1);
    }

    /** Arabic-Indic (U+0660 to U+0669) and Eastern Arabic-Indic (U+06F0 to U+06F9) digits as ASCII digits. */
    public static function digits(string $text): string
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            for ($i = 0; $i <= 9; $i++) {
                $map[html_entity_decode('&#' . (0x0660 + $i) . ';', ENT_QUOTES, 'UTF-8')] = (string) $i;
                $map[html_entity_decode('&#' . (0x06F0 + $i) . ';', ENT_QUOTES, 'UTF-8')] = (string) $i;
            }
        }

        return strtr($text, $map);
    }

    /** The Egyptian mobile form of a number, or null when it is not one. */
    public static function phone(string $phone): ?string
    {
        // Separators, and the direction marks and isolates (U+200E, U+200F, U+061C, U+202A to U+202E, U+2066 to U+2069).
        $digits = preg_replace('/[\s\-().\x{00A0}\x{200E}\x{200F}\x{061C}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', self::digits($phone)) ?? '';
        if (str_starts_with($digits, '+20')) {
            $digits = '0' . substr($digits, 3);
        } elseif (str_starts_with($digits, '0020')) {
            $digits = '0' . substr($digits, 4);
        }

        return preg_match('/^01[0125][0-9]{8}$/', $digits) === 1 ? $digits : null;
    }

    /** The mailbox an email reaches, as a key: lower case, no `+tag`, and Gmail's dots and domain folded. */
    public static function email(string $email): string
    {
        $email = wc_strtolower(trim($email));
        $at    = strrpos($email, '@');
        if ($at === false || $at === 0) {
            return $email;
        }
        $local  = substr($email, 0, $at);
        $domain = substr($email, $at + 1);
        $plus   = strpos($local, '+');
        if ($plus !== false && $plus > 0) {
            $local = substr($local, 0, $plus);
        }
        if (in_array($domain, self::GMAIL, true)) {
            $local  = str_replace('.', '', $local);
            $domain = 'gmail.com';
        }

        return $local . '@' . $domain;
    }

    /** Whether the email is a Gmail address, where every spelling the key folds reaches the one mailbox. */
    public static function isGmail(string $email): bool
    {
        return in_array(wc_strtolower((string) substr((string) strrchr(trim($email), '@'), 1)), self::GMAIL, true);
    }

    /**
     * Before an order is saved: its mobile numbers in the Egyptian form, and its billing email's key.
     *
     * @param mixed $order
     */
    public static function onOrderSave($order): void
    {
        if (! $order instanceof \WC_Order) {
            return;
        }
        foreach (['billing', 'shipping'] as $type) {
            $raw    = (string) $order->{"get_{$type}_phone"}('edit');
            $normal = $raw !== '' ? self::phone($raw) : null;
            if ($normal !== null && $normal !== $raw) {
                $order->{"set_{$type}_phone"}($normal);
            }
        }
        $email = (string) $order->get_billing_email('edit');
        $key   = $email !== '' ? self::email($email) : '';
        if ($key !== (string) $order->get_meta(self::EMAIL_META, true, 'edit')) {
            if ($key === '') {
                $order->delete_meta_data(self::EMAIL_META);
            } else {
                $order->update_meta_data(self::EMAIL_META, $key);
            }
        }
    }

    /**
     * The orders list's search: a mobile number in any spelling is searched in the form orders keep it in.
     *
     * @param mixed $args
     * @return mixed
     */
    public static function orderSearch($args)
    {
        if (! is_array($args) || ! isset($args['s']) || ! is_string($args['s']) || $args['s'] === '') {
            return $args;
        }
        $normal = self::phone($args['s']);
        if ($normal !== null) {
            $args['s'] = $normal;
        }

        return $args;
    }
}

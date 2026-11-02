<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Accounts;

defined('ABSPATH') || exit;

/**
 * Marketing consent is captured separately from account creation and is not implied by registering (AUTH-09).
 *
 * The registration form carries one unticked box. Ticking it records the consent on the account with when it was given
 * and where (`_mizzey_marketing_consent`); not ticking it records that consent was not given. Registering never needs
 * it (AUTH-14). Withdrawing and the consent screens are the account pages' (#308) and the presentation layer's.
 *
 * Register ids: AUTH-09, AUTH-14. See specs/017-accounts.
 */
final class MarketingConsent
{
    public const META  = '_mizzey_marketing_consent';
    public const FIELD = 'mizzey_marketing_consent';

    public static function register(): void
    {
        add_action('woocommerce_register_form', [self::class, 'field'], 20);
        add_action('woocommerce_created_customer', [self::class, 'save'], 10, 1);
    }

    public static function field(): void
    {
        printf(
            '<p class="form-row mizzey-marketing-consent"><label class="woocommerce-form__label woocommerce-form__label-for-checkbox"><input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox" name="%1$s" value="yes" /> <span>%2$s</span></label></p>',
            esc_attr(self::FIELD),
            esc_html__('Send me offers and news by email. You can stop at any time.', 'mizzey-site')
        );
    }

    /** @param mixed $customerId */
    public static function save($customerId): void
    {
        $id = (int) $customerId;
        if ($id <= 0 || get_user_meta($id, self::META, true) !== '') {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the registration form's nonce.
        $given = isset($_POST[self::FIELD]) && 'yes' === sanitize_text_field(wp_unslash((string) $_POST[self::FIELD]));
        self::record($id, $given, 'registration');
    }

    /** Records the customer's choice, with when and where it was made. */
    public static function record(int $userId, bool $given, string $source): void
    {
        update_user_meta($userId, self::META, ['given' => $given, 'at' => time(), 'source' => sanitize_key($source)]);
    }

    public static function given(int $userId): bool
    {
        $consent = get_user_meta($userId, self::META, true);

        return is_array($consent) && ! empty($consent['given']);
    }
}

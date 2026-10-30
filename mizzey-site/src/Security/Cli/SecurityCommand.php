<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Security\Cli;

defined('ABSPATH') || exit;

use MizzeySite\Security\BaselineReport;
use WP_CLI;

/**
 * Reports the security baseline.
 *
 * Register ids: NFR-05, US-27-03. See specs/007-security-baseline, FR-008.
 */
final class SecurityCommand
{
    /**
     * Lists every control of the security baseline with its state.
     *
     * Exits 1 when a control of the baseline does not pass. Configuration facts are listed and never fail the check.
     *
     * ## EXAMPLES
     *
     *     wp mizzey security check
     *
     * @param list<string>          $args
     * @param array<string, string> $assoc
     */
    public function check(array $args, array $assoc): void
    {
        $rows = BaselineReport::rows();
        WP_CLI\Utils\format_items('table', $rows, ['key', 'status', 'detail']);

        $failing = array_filter(
            $rows,
            static fn (array $row): bool => in_array($row['key'], BaselineReport::CONTROLS, true) && $row['status'] !== 'pass',
        );
        if ($failing !== []) {
            WP_CLI::error(sprintf(
                /* translators: 1: how many controls fail, 2: their keys. */
                __('%1$d control(s) need review: %2$s', 'mizzey-site'),
                count($failing),
                implode(', ', array_column($failing, 'key')),
            ));
        }
        WP_CLI::success(__('Every baseline check passes. Request behaviour is verified by the integration scenarios.', 'mizzey-site'));
    }

    /**
     * Applies the two-factor baseline (D-21) to WP 2FA's policy, once.
     *
     * Requires two-factor sign-in for administrators and shop managers, with an authenticator app and backup codes,
     * no email codes, no passkeys, no grace period and no exclusions. Only those fields change; the rest of WP 2FA's
     * policy is kept. Saved through WP 2FA's own API, so its policy hash and caches follow. Afterwards the policy is the
     * owner's to change in WP 2FA's screens; this command is never run automatically.
     *
     * ## EXAMPLES
     *
     *     wp mizzey security two-factor-baseline
     *
     * @subcommand two-factor-baseline
     *
     * @param list<string>          $args
     * @param array<string, string> $assoc
     *
     * Register id: ADM-131. See specs/008-admin-two-factor, FR-002.
     */
    public function twoFactorBaseline(array $args, array $assoc): void
    {
        if (! class_exists('\WP2FA\WP2FA') || ! class_exists('\WP2FA\Utils\Settings_Utils') || ! defined('WP_2FA_POLICY_SETTINGS_NAME')) {
            WP_CLI::error(__('WP 2FA is not active.', 'mizzey-site'));
        }
        $stored = \WP2FA\Utils\Settings_Utils::get_option(WP_2FA_POLICY_SETTINGS_NAME, null);
        if ($stored === null || $stored === false) {
            $stored = \WP2FA\WP2FA::get_default_settings();
        }
        if (! is_array($stored)) {
            WP_CLI::error(__('The stored WP 2FA policy is not readable; it was not changed.', 'mizzey-site'));
        }

        $policy = array_replace($stored, self::baselinePolicy());
        \WP2FA\WP2FA::update_plugin_settings($policy, false, WP_2FA_POLICY_SETTINGS_NAME);

        $saved = \WP2FA\Utils\Settings_Utils::get_option(WP_2FA_POLICY_SETTINGS_NAME, null);
        $held  = is_array($saved) && array_intersect_key($saved, self::baselinePolicy()) == self::baselinePolicy(); // phpcs:ignore Universal.Operators.StrictComparisons -- order-insensitive array equality.
        // The hash WP 2FA keeps must be the hash of this policy, calculated as WP 2FA calculates it.
        $expectedHash = \WP2FA\Utils\Settings_Utils::create_settings_hash(\WP2FA\WP2FA::get_policy_settings());
        if (! $held || \WP2FA\Utils\Settings_Utils::get_option(WP_2FA_PREFIX . 'settings_hash') !== $expectedHash) {
            WP_CLI::error(__('The two-factor baseline did not persist.', 'mizzey-site'));
        }
        WP_CLI::success(__('Two-factor sign-in is now required for administrators and shop managers. Staff enrol at their next sign-in.', 'mizzey-site'));
    }

    /**
     * Turns on CoreX's sign-in limit with the documented settings, once.
     *
     * Five failed sign-ins for one identity from one address within five minutes lock that pair for fifteen minutes.
     * WordPress's default sign-in address stays served, so staff and the two-factor challenge keep working. Only those
     * five settings change; the rest of CoreX's login protection is kept. Saved through CoreX's own settings store.
     * Afterwards the setting is the owner's, in CoreX's screen; this command is never run automatically.
     *
     * ## EXAMPLES
     *
     *     wp mizzey security sign-in-baseline
     *
     * @subcommand sign-in-baseline
     *
     * @param list<string>          $args
     * @param array<string, string> $assoc
     *
     * Register id: AUTH-15. See specs/009-sign-in-protection, FR-001.
     */
    public function signInBaseline(array $args, array $assoc): void
    {
        $storeClass = '\Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore';
        if (! class_exists($storeClass)) {
            WP_CLI::error(__('CoreX login protection is not available.', 'mizzey-site'));
        }
        $store  = new $storeClass();
        $before = self::signInSettings($store->current());
        $wanted = array_replace($before, self::signInPolicy());
        $store->save($wanted);

        $after = self::signInSettings($store->current());
        if ($after != $wanted) { // phpcs:ignore Universal.Operators.StrictComparisons -- order-insensitive array equality.
            WP_CLI::error(__('The sign-in baseline did not persist.', 'mizzey-site'));
        }
        WP_CLI::success(__('The sign-in limit is on: five failures in five minutes lock that sign-in for fifteen minutes.', 'mizzey-site'));
    }

    /**
     * The CoreX settings the sign-in baseline owns (specs/009-sign-in-protection, FR-001), in the store's input keys.
     *
     * @return array<string, mixed>
     */
    public static function signInPolicy(): array
    {
        return [
            'enabled'                 => true,
            'threshold'               => 5,
            'window_seconds'          => 300,
            'lockout_seconds'         => 900,
            'block_default_endpoints' => false,
        ];
    }

    /**
     * CoreX's settings object, mapped to the store's snake_case input keys.
     *
     * @return array<string, mixed>
     */
    private static function signInSettings(object $settings): array
    {
        return [
            'enabled'                  => (bool) $settings->enabled,
            'custom_slug'              => (string) $settings->customSlug,
            'block_default_endpoints'  => (bool) $settings->blockDefaultEndpoints,
            'threshold'                => (int) $settings->threshold,
            'window_seconds'           => (int) $settings->windowSeconds,
            'lockout_seconds'          => (int) $settings->lockoutSeconds,
            'trusted_proxy_mode'       => (bool) $settings->trustedProxyMode,
            'trusted_proxy_ranges'     => array_values((array) $settings->trustedProxyRanges),
            'retain_days'              => (int) $settings->retainDays,
            'successful_login_logging' => (bool) $settings->successfulLoginLogging,
        ];
    }

    /**
     * The policy fields the baseline owns (specs/008-admin-two-factor, FR-002).
     *
     * @return array<string, mixed>
     */
    public static function baselinePolicy(): array
    {
        return [
            'enforcement-policy'               => 'certain-roles-only',
            'enforced_roles'                   => BaselineReport::TWO_FACTOR_ROLES,
            'enforced_users'                   => [],
            'excluded_users'                   => [],
            'excluded_roles'                   => [],
            'superadmins-role-exclude'         => 'no',
            'enable_totp'                      => 'enable_totp',
            'enable_email'                     => '',
            'enable_passkeys'                  => '',
            'enable_passkeys_certain_roles'    => [],
            'backup_codes_enabled'             => 'yes',
            'grace-policy'                     => 'no-grace-period',
            'grace-policy-after-expire-action' => 'configure-right-away',
        ];
    }
}

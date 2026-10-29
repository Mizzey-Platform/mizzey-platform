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

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\SecondRelease;

defined('ABSPATH') || exit;

/**
 * The second release's switch. The contracted second-release rows (S2) are built now and released after go-live
 * (SOW: within 45 days of it). Until the switch is on, nothing of them is offered to customers or staff.
 *
 * Settings, Second release (`manage_options`). Off by default.
 *
 * See specs/047-second-release.
 */
final class ReleaseSwitch
{
    public const OPTION = 'mizzey_second_release';
    public const PAGE   = 'mizzey-second-release';

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_init', [self::class, 'settings']);
    }

    public static function on(): bool
    {
        return get_option(self::OPTION, 'no') === 'yes';
    }

    public static function menu(): void
    {
        add_options_page(__('Second release', 'mizzey-site'), __('Second release', 'mizzey-site'), 'manage_options', self::PAGE, [self::class, 'render']);
    }

    public static function settings(): void
    {
        register_setting(self::PAGE, self::OPTION, ['type' => 'string', 'default' => 'no', 'sanitize_callback' => static fn ($v): string => $v === 'yes' ? 'yes' : 'no']);
    }

    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__('Second release', 'mizzey-site') . '</h1>';
        echo '<p>' . esc_html__('Reorder, account deletion and data export requests, notification preferences, and the customer lifetime figures. Built, and released to customers and staff only when this is on.', 'mizzey-site') . '</p>';
        printf('<form method="post" action="%s">', esc_url(admin_url('options.php')));
        settings_fields(self::PAGE);
        printf(
            '<p><label><input type="checkbox" name="%s" value="yes"%s /> %s</label></p>',
            esc_attr(self::OPTION),
            checked(self::on(), true, false),
            esc_html__('The second release is live', 'mizzey-site')
        );
        submit_button();
        echo '</form></div>';
    }
}

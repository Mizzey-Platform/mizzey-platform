<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\SecondRelease;

defined('ABSPATH') || exit;

/**
 * Data export and account deletion requests, handled automatically (NFR-12a, ACCT-13, ADM-105).
 *
 * - **The customer asks** from My Account, Account details: a copy of their data, or the deletion of their account.
 *   WordPress's own privacy request is created and its confirmation email sent (so only the mailbox owner can confirm).
 * - **Once confirmed**, the request is handled in the background with WordPress's and WooCommerce's own exporters and
 *   erasers: the export is built and its link emailed; for a deletion, the personal data is erased as WooCommerce's
 *   privacy settings say (orders are kept for the accounts the law requires, with or without their personal data as
 *   those settings choose) and the customer account is then deleted. A staff account is never deleted this way.
 * - **Staff** prepare a customer's export from the customer's record (ADM-105): the same confirmed request, listed in
 *   Tools, Export Personal Data, behind `export_others_personal_data`.
 * - Offered only once the second release is on.
 *
 * Register ids: NFR-12a, ACCT-13, ADM-105. See specs/047-second-release.
 */
final class PrivacyRequests
{
    public const NONCE   = 'mizzey_privacy_request';
    public const HOOK    = 'mizzey_privacy_request_run';
    public const GROUP   = 'mizzey-privacy';
    public const SOURCE  = 'mizzey-privacy';

    public static function register(): void
    {
        add_action('woocommerce_edit_account_form_end', [self::class, 'buttons']);
        add_action('template_redirect', [self::class, 'ask'], 5);
        add_action('user_request_action_confirmed', [self::class, 'confirmed'], 20, 1);
        add_action(self::HOOK, [self::class, 'run'], 10, 2);
        add_action('mizzey_customer_record_panel', [self::class, 'staffExport'], 20, 1);
        add_action('admin_post_mizzey_customer_export', [self::class, 'prepareForStaff']);
    }

    public static function buttons(): void
    {
        if (! ReleaseSwitch::on() || ! is_user_logged_in()) {
            return;
        }
        printf('<fieldset class="mizzey-privacy"><legend>%s</legend><p>%s</p>', esc_html__('Your data', 'mizzey-site'), esc_html__('We email you a link to confirm. A copy of your data arrives by email; a deleted account cannot be restored.', 'mizzey-site'));
        foreach (['export_personal_data' => __('Email me a copy of my data', 'mizzey-site'), 'remove_personal_data' => __('Delete my account', 'mizzey-site')] as $action => $label) {
            printf(
                '<button type="submit" name="mizzey_privacy" value="%s" formnovalidate="formnovalidate">%s</button>',
                esc_attr($action),
                esc_html($label)
            );
        }
        wp_nonce_field(self::NONCE, self::NONCE . '_nonce');
        echo '</fieldset>';
    }

    /** The customer's request, sent with the account form. */
    public static function ask(): void
    {
        if (! isset($_POST['mizzey_privacy']) || ! ReleaseSwitch::on() || ! is_user_logged_in()) {
            return;
        }
        $nonce  = isset($_POST[self::NONCE . '_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST[self::NONCE . '_nonce'])) : '';
        $action = sanitize_key(wp_unslash((string) $_POST['mizzey_privacy']));
        if (! wp_verify_nonce($nonce, self::NONCE) || ! in_array($action, ['export_personal_data', 'remove_personal_data'], true)) {
            return;
        }
        $made = self::request(wp_get_current_user(), $action, false);
        wc_add_notice(is_wp_error($made) ? $made->get_error_message() : __('Check your email to confirm the request.', 'mizzey-site'), is_wp_error($made) ? 'error' : 'success');
        wp_safe_redirect(wc_get_account_endpoint_url('edit-account'));
        exit;
    }

    /** @return int|\WP_Error The request id. */
    public static function request(\WP_User $user, string $action, bool $confirmed)
    {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        $id = wp_create_user_request($user->user_email, $action, [], $confirmed ? 'confirmed' : 'pending');
        if (is_wp_error($id)) {
            return $id;
        }
        if ($confirmed) {
            // Prepared by staff: the copy is downloaded from Tools, Export Personal Data, not emailed.
            as_enqueue_async_action(self::HOOK, [(int) $id, false], self::GROUP);
        } else {
            wp_send_user_request((int) $id);
        }

        return (int) $id;
    }

    /** @param mixed $requestId */
    public static function confirmed($requestId): void
    {
        $request = wp_get_user_request((int) $requestId);
        if ($request && in_array($request->action_name, ['export_personal_data', 'remove_personal_data'], true) && ReleaseSwitch::on()) {
            as_enqueue_async_action(self::HOOK, [(int) $requestId, true], self::GROUP);
        }
    }

    /**
     * @param mixed $requestId
     * @param mixed $email Whether the export's link is emailed to the customer.
     */
    public static function run($requestId, $email = true): void
    {
        $request = wp_get_user_request((int) $requestId);
        if (! $request || $request->status === 'request-completed') {
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/privacy-tools.php';
        require_once ABSPATH . 'wp-admin/includes/user.php';
        // WordPress's steps report a failure by ending the request with a JSON error: here that becomes an exception.
        $ajax = static fn (): bool => true;
        $die  = static fn () => static function ($message): void {
            throw new \RuntimeException(is_string($message) ? $message : 'privacy step failed');
        };
        add_filter('wp_doing_ajax', $ajax, 99);
        add_filter('wp_die_ajax_handler', $die, 99);
        ob_start();
        try {
            $request->action_name === 'export_personal_data' ? self::export($request, (bool) $email) : self::erase($request);
        } catch (\Throwable $e) {
            wc_get_logger()->error(sprintf('privacy request %d failed: %s', (int) $requestId, $e->getMessage()), ['source' => self::SOURCE]);
        } finally {
            ob_end_clean();
            remove_filter('wp_doing_ajax', $ajax, 99);
            remove_filter('wp_die_ajax_handler', $die, 99);
        }
    }

    private static function export(\WP_User_Request $request, bool $email): void
    {
        $exporters = (array) apply_filters('wp_privacy_personal_data_exporters', []);
        $keys      = array_keys($exporters);
        foreach ($keys as $index => $key) {
            for ($page = 1; $page <= 200; $page++) {
                $response = call_user_func($exporters[$key]['callback'], $request->email, $page);
                $response = wp_privacy_process_personal_data_export_page($response, $index + 1, $request->email, $page, $request->ID, $email, $key);
                if (! empty($response['done'])) {
                    break;
                }
            }
        }
    }

    private static function erase(\WP_User_Request $request): void
    {
        $erasers = (array) apply_filters('wp_privacy_personal_data_erasers', []);
        $keys    = array_keys($erasers);
        foreach ($keys as $index => $key) {
            for ($page = 1; $page <= 200; $page++) {
                $response = call_user_func($erasers[$key]['callback'], $request->email, $page);
                $response = wp_privacy_process_personal_data_erasure_page($response, $index + 1, $request->email, $page, $request->ID);
                if (! empty($response['done'])) {
                    break;
                }
            }
        }
        $user = get_user_by('email', $request->email);
        if ($user instanceof \WP_User && ! user_can($user, 'edit_posts')) {
            wp_delete_user($user->ID);
        }
    }

    // ---- Staff (ADM-105) ---------------------------------------------------------------------------------------

    /** @param mixed $user */
    public static function staffExport($user): void
    {
        if (! ReleaseSwitch::on() || ! $user instanceof \WP_User || ! current_user_can('export_others_personal_data')) {
            return;
        }
        printf(
            '<p><a class="button" href="%s">%s</a></p>',
            esc_url(wp_nonce_url(admin_url('admin-post.php?action=mizzey_customer_export&user=' . $user->ID), 'mizzey_customer_export_' . $user->ID)),
            esc_html__('Prepare a copy of this customer\'s data', 'mizzey-site')
        );
    }

    public static function prepareForStaff(): void
    {
        $id = isset($_GET['user']) ? absint($_GET['user']) : 0;
        if (! ReleaseSwitch::on() || ! current_user_can('export_others_personal_data') || ! check_admin_referer('mizzey_customer_export_' . $id)) {
            wp_die(esc_html__('You cannot do that.', 'mizzey-site'), '', ['response' => 403]);
        }
        $user = get_userdata($id);
        if ($user instanceof \WP_User) {
            self::request($user, 'export_personal_data', true);
        }
        wp_safe_redirect(admin_url('export-personal-data.php'));
        exit;
    }
}

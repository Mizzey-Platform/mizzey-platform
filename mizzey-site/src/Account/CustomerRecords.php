<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Account;

defined('ABSPATH') || exit;

/**
 * The customer's record in the administration (ADM-100, ADM-103, ADM-104), on WordPress's own user screen, where
 * WooCommerce already shows the contacts and the billing and shipping addresses:
 *
 * - **Order history** (ADM-100): the customer's orders, newest first, each linked to its order screen.
 * - **Addresses** (ADM-100): WooCommerce's billing and shipping address, and the customer's address book (specs/022),
 *   listed read only.
 * - **Internal notes** (ADM-103, US-19-02): notes staff add to the customer, each kept with its author and time, newest
 *   first; a saved note is not edited or removed, so no note is lost to a later one. Stored in a private user field
 *   (leading underscore, not registered for the REST API), shown only on this screen, never in the customer's account.
 *   A single note saved before entries existed is kept as the first entry.
 * - **Suspension with a reason** (ADM-104): staff suspend a customer account with a reason. Its sessions end at once;
 *   it cannot sign in by any route (password, Google, a sign-in link, an existing cookie), and the sign-in page says
 *   the account is suspended. Each suspension and reinstatement is kept with who did it, when and why. Only customer
 *   accounts can be suspended here, never staff, and never one's own.
 *
 * Register ids: ADM-100, ADM-103, ADM-104. See specs/033-customer-records.
 */
final class CustomerRecords
{
    public const NOTES     = '_mizzey_customer_notes';
    public const SUSPENDED = '_mizzey_suspended';
    public const REASON    = '_mizzey_suspension_reason';
    public const HISTORY   = '_mizzey_suspension_history';
    public const NONCE     = 'mizzey_customer_record';
    public const ORDERS    = 20;
    /** The longest note kept, and the most notes kept on one customer (the oldest dropped beyond it). */
    public const NOTE_LENGTH = 2000;
    public const NOTES_KEPT  = 200;

    public static function register(): void
    {
        add_action('edit_user_profile', [self::class, 'panel'], 20);
        add_action('edit_user_profile_update', [self::class, 'save'], 20);
        add_filter('authenticate', [self::class, 'refuseSignIn'], 99, 1);
        add_filter('determine_current_user', [self::class, 'refuseSession'], 99);
        add_filter('rest_pre_dispatch', [self::class, 'refuseCartToken'], 5, 3);
        // The last door: whatever route completed a sign-in (password, Google, a sign-in link, a future one), it is
        // undone before anything else acts on it (a wishlist or cart merge, say).
        add_action('wp_login', [self::class, 'refuseCompletedSignIn'], 0, 2);
    }

    public static function isSuspended(int $userId): bool
    {
        return $userId > 0 && get_user_meta($userId, self::SUSPENDED, true) === 'yes';
    }

    /** Whether staff may manage this account's record: a customer, someone else, and staff who may edit them. */
    public static function manageable(\WP_User $user): bool
    {
        return $user->ID !== get_current_user_id()
            && ! user_can($user, 'edit_posts') && ! user_can($user, 'manage_woocommerce') && ! user_can($user, 'edit_shop_orders')
            && current_user_can('edit_user', $user->ID);
    }

    /** @param mixed $user */
    public static function panel($user): void
    {
        if (! $user instanceof \WP_User || ! self::manageable($user)) {
            return;
        }
        wp_nonce_field(self::NONCE, self::NONCE . '_nonce');
        printf('<h2>%s</h2>', esc_html__('Customer record', 'mizzey-site'));

        echo '<table class="form-table" role="presentation"><tr><th>' . esc_html__('Orders', 'mizzey-site') . '</th><td>';
        // One query for the page of orders and the customer's total.
        $page   = wc_get_orders(['customer_id' => $user->ID, 'limit' => self::ORDERS, 'orderby' => 'date', 'order' => 'DESC', 'type' => 'shop_order', 'paginate' => true]);
        $orders = is_object($page) && isset($page->orders) ? (array) $page->orders : [];
        $count  = is_object($page) && isset($page->total) ? (int) $page->total : count($orders);
        if ($orders === []) {
            esc_html_e('No orders yet.', 'mizzey-site');
        } else {
            echo '<table class="widefat striped"><thead><tr>';
            foreach ([__('Order', 'mizzey-site'), __('Date', 'mizzey-site'), __('Status', 'mizzey-site'), __('Total', 'mizzey-site')] as $h) {
                printf('<th>%s</th>', esc_html($h));
            }
            echo '</tr></thead><tbody>';
            foreach ($orders as $order) {
                if (! $order instanceof \WC_Order) {
                    continue;
                }
                $date = $order->get_date_created();
                printf(
                    '<tr><td><a href="%s">#%s</a></td><td>%s</td><td>%s</td><td>%s</td></tr>',
                    esc_url($order->get_edit_order_url()),
                    esc_html((string) $order->get_order_number()),
                    esc_html($date ? wp_date((string) get_option('date_format'), $date->getTimestamp()) : ''),
                    esc_html(wc_get_order_status_name($order->get_status())),
                    wp_kses_post($order->get_formatted_order_total())
                );
            }
            echo '</tbody></table>';
            printf(
                '<p><a href="%s">%s</a></p>',
                esc_url(add_query_arg(['page' => 'wc-orders', '_customer_user' => $user->ID], admin_url('admin.php'))),
                esc_html(sprintf(_n('All %s order of this customer', 'All %s orders of this customer', $count, 'mizzey-site'), number_format_i18n($count)))
            );
        }
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__('Address book', 'mizzey-site') . '</th><td>';
        $book = AddressBook::all($user->ID);
        if ($book === []) {
            esc_html_e('No saved addresses.', 'mizzey-site');
        } else {
            $states = (array) WC()->countries->get_states('EG');
            echo '<ul>';
            foreach ($book as $address) {
                $parts = array_filter([
                    trim((string) ($address['first_name'] ?? '') . ' ' . (string) ($address['last_name'] ?? '')),
                    (string) ($address['phone'] ?? ''),
                    (string) ($address['address_1'] ?? ''),
                    (string) ($address['district'] ?? ''),
                    (string) ($address['city'] ?? ''),
                    (string) ($states[(string) ($address['state'] ?? '')] ?? ($address['state'] ?? '')),
                    (string) ($address['landmark'] ?? ''),
                ], static fn (string $part): bool => $part !== '');
                printf('<li>%s%s</li>', esc_html(implode(', ', $parts)), ! empty($address['default']) ? ' <strong>' . esc_html__('(default)', 'mizzey-site') . '</strong>' : '');
            }
            echo '</ul>';
        }
        echo '</td></tr>';

        echo '<tr><th><label for="mizzey-customer-note">' . esc_html__('Internal notes', 'mizzey-site') . '</label></th><td>';
        $notes = self::notes($user->ID);
        if ($notes !== []) {
            echo '<ul class="mizzey-customer-notes">';
            foreach (array_reverse($notes) as $note) {
                printf(
                    '<li><strong>%s</strong><br />%s</li>',
                    esc_html(sprintf(
                        /* translators: 1: date and time, 2: staff login. */
                        __('%1$s, by %2$s', 'mizzey-site'),
                        $note['at'] > 0 ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $note['at']) : __('before notes were dated', 'mizzey-site'),
                        $note['by'] !== '' ? $note['by'] : __('unknown', 'mizzey-site')
                    )),
                    nl2br(esc_html($note['text']))
                );
            }
            echo '</ul>';
        }
        printf(
            '<textarea id="mizzey-customer-note" name="mizzey_customer_note" rows="3" cols="60" maxlength="%d"></textarea><p class="description">%s</p></td></tr>',
            self::NOTE_LENGTH,
            esc_html__('Add a note. Each note keeps who wrote it and when. Seen by staff only, never by the customer.', 'mizzey-site')
        );

        $suspended = self::isSuspended($user->ID);
        printf(
            '<tr><th>%s</th><td><label><input type="checkbox" name="mizzey_suspended" value="yes"%s /> %s</label><br /><label for="mizzey-suspension-reason">%s</label><br /><input type="text" class="regular-text" id="mizzey-suspension-reason" name="mizzey_suspension_reason" value="%s" maxlength="300" />',
            esc_html__('Suspension', 'mizzey-site'),
            checked($suspended, true, false),
            esc_html__('Suspend this account: it cannot sign in', 'mizzey-site'),
            esc_html__('Reason (required to suspend)', 'mizzey-site'),
            esc_attr((string) get_user_meta($user->ID, self::REASON, true))
        );
        $history = get_user_meta($user->ID, self::HISTORY, true);
        if (is_array($history) && $history !== []) {
            echo '<ul>';
            foreach (array_reverse($history) as $entry) {
                printf(
                    '<li>%s</li>',
                    esc_html(sprintf(
                        /* translators: 1: date, 2: suspended or reinstated, 3: staff login, 4: reason. */
                        __('%1$s: %2$s by %3$s. %4$s', 'mizzey-site'),
                        wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) ($entry['at'] ?? 0)),
                        ($entry['action'] ?? '') === 'suspended' ? __('suspended', 'mizzey-site') : __('reinstated', 'mizzey-site'),
                        (string) ($entry['by'] ?? ''),
                        (string) ($entry['reason'] ?? '')
                    ))
                );
            }
            echo '</ul>';
        }
        echo '</td></tr></table>';
        do_action('mizzey_customer_record_panel', $user);
    }

    /** @param mixed $userId */
    public static function save($userId): void
    {
        $user  = get_user_by('id', (int) $userId);
        $nonce = isset($_POST[self::NONCE . '_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST[self::NONCE . '_nonce'])) : '';
        if (! $user instanceof \WP_User || $nonce === '' || ! wp_verify_nonce($nonce, self::NONCE) || ! self::manageable($user)) {
            return;
        }
        $note = isset($_POST['mizzey_customer_note']) && is_string($_POST['mizzey_customer_note']) ? trim(sanitize_textarea_field(wp_unslash($_POST['mizzey_customer_note']))) : '';
        if ($note !== '' && ! self::addNote($user->ID, $note)) {
            add_action('user_profile_update_errors', static function ($errors): void {
                if ($errors instanceof \WP_Error) {
                    $errors->add('mizzey_customer_note_busy', __('The note was not saved, as another note on this customer was being saved. Please add it again.', 'mizzey-site'));
                }
            });
        }
        $want   = isset($_POST['mizzey_suspended']) && $_POST['mizzey_suspended'] === 'yes';
        $reason = isset($_POST['mizzey_suspension_reason']) && is_string($_POST['mizzey_suspension_reason']) ? mb_substr(sanitize_text_field(wp_unslash($_POST['mizzey_suspension_reason'])), 0, 300) : '';
        if ($want && ! self::isSuspended($user->ID)) {
            if ($reason === '') {
                add_action('user_profile_update_errors', static function ($errors): void {
                    if ($errors instanceof \WP_Error) {
                        $errors->add('mizzey_suspension_reason', __('Give a reason to suspend this account. It was not suspended.', 'mizzey-site'));
                    }
                });
                return;
            }
            self::suspend($user, $reason);
        } elseif (! $want && self::isSuspended($user->ID)) {
            self::reinstate($user, $reason);
        }
    }

    /**
     * The customer's notes, oldest first, each with its text, its author's login and its time (0 for the single note
     * saved before notes were entries).
     *
     * @return list<array{text:string,by:string,at:int}>
     */
    public static function notes(int $userId): array
    {
        $stored = get_user_meta($userId, self::NOTES, true);
        if (is_string($stored)) {
            return trim($stored) === '' ? [] : [['text' => $stored, 'by' => '', 'at' => 0]];
        }
        $notes = [];
        foreach (is_array($stored) ? $stored : [] as $note) {
            if (is_array($note) && isset($note['text']) && is_string($note['text'])) {
                $notes[] = ['text' => $note['text'], 'by' => (string) ($note['by'] ?? ''), 'at' => (int) ($note['at'] ?? 0)];
            }
        }

        return $notes;
    }

    /** The MySQL named lock that note writes on one customer take in turn. */
    public static function notesLock(int $userId): string
    {
        global $wpdb;

        return substr('mz_' . $wpdb->prefix . 'customer_notes_' . $userId, 0, 64);
    }

    /**
     * Adds a note by the signed-in staff member, now; the notes before it are kept as they are. Two staff adding a note at
     * once take turns; when the turn does not come within five seconds nothing is written (false), so no note is lost.
     */
    public static function addNote(int $userId, string $text): bool
    {
        global $wpdb;
        $lock = self::notesLock($userId);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== 1) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            return false;
        }
        try {
            wp_cache_delete($userId, 'user_meta');
            $notes   = self::notes($userId);
            $staff   = wp_get_current_user();
            $notes[] = ['text' => mb_substr($text, 0, self::NOTE_LENGTH), 'by' => $staff->exists() ? $staff->user_login : '', 'at' => time()];
            update_user_meta($userId, self::NOTES, array_slice($notes, -self::NOTES_KEPT));

            return true;
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }
    }

    public static function suspend(\WP_User $user, string $reason): void
    {
        update_user_meta($user->ID, self::SUSPENDED, 'yes');
        update_user_meta($user->ID, self::REASON, $reason);
        self::remember($user->ID, 'suspended', $reason);
        \WP_Session_Tokens::get_instance($user->ID)->destroy_all();
        // The account's store session too (its cart, reached by a Store API cart token).
        if (class_exists('\WC_Session_Handler')) {
            (new \WC_Session_Handler())->delete_session((string) $user->ID);
        }
    }

    /**
     * @param mixed $login
     * @param mixed $user
     */
    public static function refuseCompletedSignIn($login, $user = null): void
    {
        if (! $user instanceof \WP_User || ! self::isSuspended($user->ID)) {
            return;
        }
        wp_clear_auth_cookie();
        \WP_Session_Tokens::get_instance($user->ID)->destroy_all();
        wp_set_current_user(0);
        wp_die(esc_html__('This account is suspended. Please contact customer service.', 'mizzey-site'), '', ['response' => 403]);
    }

    /**
     * A Store API cart token issued to the account before it was suspended is refused.
     *
     * @param mixed $result
     * @param mixed $server
     * @param mixed $request
     * @return mixed
     */
    public static function refuseCartToken($result, $server, $request)
    {
        if ($result !== null || ! $request instanceof \WP_REST_Request || ! str_starts_with($request->get_route(), '/wc/store/')) {
            return $result;
        }
        $token = (string) $request->get_header('cart_token');
        if ($token === '' || ! class_exists('\Automattic\WooCommerce\StoreApi\Utilities\CartTokenUtils')) {
            return $result;
        }
        $payload = \Automattic\WooCommerce\StoreApi\Utilities\CartTokenUtils::get_cart_token_payload($token);
        $userId  = isset($payload['user_id']) && is_numeric($payload['user_id']) ? (int) $payload['user_id'] : 0;
        if (self::isSuspended($userId)) {
            return new \WP_Error('mizzey_suspended', __('This account is suspended. Please contact customer service.', 'mizzey-site'), ['status' => 403]);
        }

        return $result;
    }

    public static function reinstate(\WP_User $user, string $reason = ''): void
    {
        delete_user_meta($user->ID, self::SUSPENDED);
        self::remember($user->ID, 'reinstated', $reason);
    }

    private static function remember(int $userId, string $action, string $reason): void
    {
        $history   = get_user_meta($userId, self::HISTORY, true);
        $history   = is_array($history) ? $history : [];
        $staff     = wp_get_current_user();
        $history[] = ['action' => $action, 'reason' => $reason, 'by' => $staff->exists() ? $staff->user_login : '', 'at' => time()];
        update_user_meta($userId, self::HISTORY, array_slice($history, -50));
    }

    /**
     * A suspended account's password sign-in is refused with a message, on every form that authenticates.
     *
     * @param mixed $user
     * @return mixed
     */
    public static function refuseSignIn($user)
    {
        if ($user instanceof \WP_User && self::isSuspended($user->ID)) {
            return new \WP_Error('mizzey_suspended', __('This account is suspended. Please contact customer service.', 'mizzey-site'));
        }

        return $user;
    }

    /**
     * Whatever set the cookie (Google, a sign-in link, an older session), a suspended account is never the signed-in
     * user.
     *
     * @param mixed $userId
     * @return mixed
     */
    public static function refuseSession($userId)
    {
        return is_numeric($userId) && self::isSuspended((int) $userId) ? 0 : $userId;
    }
}

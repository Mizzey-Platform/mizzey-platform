<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Security;

defined('ABSPATH') || exit;

/**
 * The log, searchable in the standard administration (ADM-132): Users, Activity log. Sign-ins and security events are
 * read from CoreX Activity and filtered by account, event, outcome and dates; order notes, which WooCommerce keeps on
 * each order, are searched by their text. The screen only reads: no entry can be changed or deleted from the interface
 * (ADM-133), and CoreX's own activity routes are read-only too. Entries leave only when CoreX's retention expires them.
 *
 * Only the owner and administrators see it (`manage_options`, as CoreX's activity routes ask).
 *
 * Register ids: ADM-132, ADM-133. See specs/031-staff-activity-log.
 */
final class StaffActivityScreen
{
    public const SLUG     = 'mizzey-activity-log';
    public const CAP      = 'manage_options';
    public const PER_PAGE = 50;

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu']);
    }

    public static function menu(): void
    {
        add_users_page(__('Activity log', 'mizzey-site'), __('Activity log', 'mizzey-site'), self::CAP, self::SLUG, [self::class, 'render']);
    }

    /** @return array<string,string> */
    public static function kinds(): array
    {
        return [
            StaffActivityLog::SIGNIN          => __('Staff sign-in', 'mizzey-site'),
            StaffActivityLog::SIGNIN_FAILED   => __('Failed sign-in', 'mizzey-site'),
            StaffActivityLog::SECOND_FACTOR   => __('Failed second factor', 'mizzey-site'),
            StaffActivityLog::LOCKOUT         => __('Locked out', 'mizzey-site'),
            StaffActivityLog::SIGNOUT         => __('Staff sign-out', 'mizzey-site'),
            StaffActivityLog::RESET_REQUESTED => __('Password reset requested', 'mizzey-site'),
            StaffActivityLog::RESET_DONE      => __('Password reset', 'mizzey-site'),
        ];
    }

    /**
     * The filters in the request, checked.
     *
     * @return array{account:string,kind:string,outcome:string,from:string,to:string,page:int,notes:string}
     */
    public static function request(): array
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- a read-only screen.
        $get  = static fn (string $key): string => isset($_GET[$key]) && is_string($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
        $date = static fn (string $v): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1 && checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4)) ? $v : '';
        $kind = $get('kind');
        $out  = $get('outcome');
        $values = [
            'account' => mb_substr($get('account'), 0, 100),
            'kind'    => array_key_exists($kind, self::kinds()) ? $kind : '',
            'outcome' => in_array($out, ['success', 'failure', 'denied'], true) ? $out : '',
            'from'    => $date($get('from')),
            'to'      => $date($get('to')),
            'page'    => max(1, absint($get('paged'))),
            'notes'   => mb_substr($get('notes'), 0, 100),
        ];
        // phpcs:enable

        return $values;
    }

    /**
     * The log's entries for the filters: CoreX's query, kept to the security area and to Mizzey's events.
     *
     * @param array{account:string,kind:string,outcome:string,from:string,to:string,page:int} $f
     * @return list<\Corex\Activity\ActivityEvent>|null Null when the account searched for does not exist.
     */
    public static function entries(array $f): ?array
    {
        if (! class_exists('\Corex\Boot') || ! \Corex\Boot::booted()) {
            return [];
        }
        $filters = ['area' => \Corex\Activity\ActivityEvent::AREA_SECURITY];
        if ($f['account'] !== '') {
            $user = get_user_by('login', $f['account']) ?: get_user_by('email', $f['account']);
            if (! $user instanceof \WP_User) {
                return null;
            }
            $filters['actor_id'] = $user->ID;
        }
        if ($f['kind'] !== '') {
            $filters['kind'] = $f['kind'];
        }
        if ($f['outcome'] !== '') {
            $filters['outcome'] = $f['outcome'];
        }
        $tz = wp_timezone();
        if ($f['from'] !== '') {
            $filters['date_from'] = (new \DateTimeImmutable($f['from'] . ' 00:00:00', $tz))->setTimezone(new \DateTimeZone('UTC'));
        }
        if ($f['to'] !== '') {
            $filters['date_to'] = (new \DateTimeImmutable($f['to'] . ' 23:59:59', $tz))->setTimezone(new \DateTimeZone('UTC'));
        }
        $events = \Corex\Boot::app()->container()->make(\Corex\Activity\ActivityService::class)->query($filters, $f['page'], self::PER_PAGE);

        return array_values(array_filter($events, static fn ($e): bool => str_starts_with((string) $e->kind, StaffActivityLog::PREFIX)));
    }

    /**
     * Order notes whose text contains the words searched for, newest first.
     *
     * @return list<object{comment_ID:string,comment_post_ID:string,comment_date:string,comment_author:string,comment_content:string}>
     */
    public static function notes(string $text): array
    {
        if ($text === '') {
            return [];
        }
        global $wpdb;
        $like = '%' . $wpdb->esc_like($text) . '%';

        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT comment_ID, comment_post_ID, comment_date, comment_author, comment_content FROM {$wpdb->comments} WHERE comment_type = 'order_note' AND comment_content LIKE %s ORDER BY comment_date_gmt DESC, comment_ID DESC LIMIT 50",
            $like
        )); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }

    public static function render(): void
    {
        if (! current_user_can(self::CAP)) {
            wp_die(esc_html__('You are not allowed to see the activity log.', 'mizzey-site'), '', ['response' => 403]);
        }
        $f = self::request();
        echo '<div class="wrap mizzey-activity-log">';
        printf('<h1>%s</h1>', esc_html__('Activity log', 'mizzey-site'));
        printf('<p>%s</p>', esc_html__('Sign-ins and security events, kept for a year. Entries cannot be changed or deleted here.', 'mizzey-site'));

        echo '<form method="get">';
        printf('<input type="hidden" name="page" value="%s" />', esc_attr(self::SLUG));
        printf('<label>%s <input type="search" name="account" value="%s" placeholder="%s" /></label> ', esc_html__('Account', 'mizzey-site'), esc_attr($f['account']), esc_attr__('Username or email', 'mizzey-site'));
        printf('<label>%s <select name="kind"><option value="">%s</option>', esc_html__('Event', 'mizzey-site'), esc_html__('All events', 'mizzey-site'));
        foreach (self::kinds() as $key => $label) {
            printf('<option value="%s"%s>%s</option>', esc_attr($key), selected($f['kind'], $key, false), esc_html($label));
        }
        echo '</select></label> ';
        printf('<label>%s <select name="outcome"><option value="">%s</option>', esc_html__('Outcome', 'mizzey-site'), esc_html__('Any', 'mizzey-site'));
        foreach (['success' => __('Succeeded', 'mizzey-site'), 'failure' => __('Failed', 'mizzey-site'), 'denied' => __('Refused', 'mizzey-site')] as $key => $label) {
            printf('<option value="%s"%s>%s</option>', esc_attr($key), selected($f['outcome'], $key, false), esc_html($label));
        }
        echo '</select></label> ';
        printf('<label>%s <input type="date" name="from" value="%s" /></label> ', esc_html__('From', 'mizzey-site'), esc_attr($f['from']));
        printf('<label>%s <input type="date" name="to" value="%s" /></label> ', esc_html__('To', 'mizzey-site'), esc_attr($f['to']));
        submit_button(__('Search', 'mizzey-site'), 'secondary', '', false);
        echo '</form>';

        $entries = self::entries($f);
        if ($entries === null) {
            printf('<p>%s</p>', esc_html__('No account has that username or email.', 'mizzey-site'));
        } else {
            echo '<table class="widefat striped"><thead><tr>';
            foreach ([__('When', 'mizzey-site'), __('Event', 'mizzey-site'), __('Account', 'mizzey-site'), __('As typed', 'mizzey-site'), __('Outcome', 'mizzey-site'), __('Address', 'mizzey-site'), __('Detail', 'mizzey-site')] as $h) {
                printf('<th scope="col">%s</th>', esc_html($h));
            }
            echo '</tr></thead><tbody>';
            $kinds = self::kinds();
            foreach ($entries as $e) {
                $ctx    = (array) $e->context;
                $detail = array_filter([
                    isset($ctx['reason']) ? (string) $ctx['reason'] : '',
                    isset($ctx['locked_until']) && $ctx['locked_until'] !== '' ? sprintf(__('until %s', 'mizzey-site'), (string) $ctx['locked_until']) : '',
                ]);
                printf(
                    '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                    esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $e->occurredAt->getTimestamp())),
                    esc_html($kinds[$e->kind] ?? $e->kind),
                    esc_html($e->actorLabel),
                    esc_html($e->targetLabel),
                    esc_html($e->outcome),
                    esc_html((string) ($ctx['address'] ?? '')),
                    esc_html(implode(', ', $detail))
                );
            }
            if ($entries === []) {
                printf('<tr><td colspan="7">%s</td></tr>', esc_html__('No entries match.', 'mizzey-site'));
            }
            echo '</tbody></table>';
            $base = remove_query_arg('paged');
            if ($f['page'] > 1) {
                printf('<a class="button" href="%s">%s</a> ', esc_url(add_query_arg('paged', $f['page'] - 1, $base)), esc_html__('Newer', 'mizzey-site'));
            }
            if (count($entries) >= self::PER_PAGE) {
                printf('<a class="button" href="%s">%s</a>', esc_url(add_query_arg('paged', $f['page'] + 1, $base)), esc_html__('Older', 'mizzey-site'));
            }
        }

        printf('<h2>%s</h2>', esc_html__('Order notes', 'mizzey-site'));
        echo '<form method="get">';
        printf('<input type="hidden" name="page" value="%s" />', esc_attr(self::SLUG));
        printf('<label>%s <input type="search" name="notes" value="%s" /></label> ', esc_html__('Words in the note', 'mizzey-site'), esc_attr($f['notes']));
        submit_button(__('Search notes', 'mizzey-site'), 'secondary', '', false);
        echo '</form>';
        if ($f['notes'] !== '') {
            echo '<table class="widefat striped"><thead><tr>';
            foreach ([__('When', 'mizzey-site'), __('Order', 'mizzey-site'), __('By', 'mizzey-site'), __('Note', 'mizzey-site')] as $h) {
                printf('<th scope="col">%s</th>', esc_html($h));
            }
            echo '</tr></thead><tbody>';
            $rows = self::notes($f['notes']);
            foreach ($rows as $n) {
                $order = wc_get_order((int) $n->comment_post_ID);
                printf(
                    '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                    esc_html((string) $n->comment_date),
                    $order instanceof \WC_Order ? sprintf('<a href="%s">#%s</a>', esc_url($order->get_edit_order_url()), esc_html((string) $order->get_order_number())) : esc_html((string) $n->comment_post_ID),
                    esc_html((string) $n->comment_author),
                    esc_html(wp_strip_all_tags((string) $n->comment_content))
                );
            }
            if ($rows === []) {
                printf('<tr><td colspan="4">%s</td></tr>', esc_html__('No order notes match.', 'mizzey-site'));
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    }
}

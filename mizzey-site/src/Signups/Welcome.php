<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Signups;

defined('ABSPATH') || exit;

/**
 * The one email a sign-up gets: a short welcome that confirms it and carries the leave link, in the language the
 * visitor signed up in. It is a WooCommerce email (WelcomeEmail), listed with the store's other customer emails, so it
 * leaves through the store's sender and the transactional relay (specs/045) like every other customer email.
 *
 * It is queued with WooCommerce's Action Scheduler, so the sign-up's answer does not wait for the mail server, which
 * narrows the difference in timing between a new address and one already on the list (it does not remove every
 * difference: a new address is written, a known one only read). It is sent once per subscription. A send the mail
 * server refuses fails its queued action and stays owed; while it is owed, a repeat sign-up queues it again, and so
 * does a sign-up after leaving. When staff have switched the email off it is not sent and stays owed too. A MySQL named
 * lock per record keeps two runs from sending it twice.
 *
 * At most CEILING welcomes leave in an hour (filter `mizzey_signup_welcome_ceiling`): past it a welcome is scheduled
 * an hour later, so a flood of sign-ups cannot turn the store's sender into a source of mass mail.
 *
 * Register id: HOME-09. See specs/066-stage-2-shop-front, slice 5.
 */
final class Welcome
{
    public const HOOK  = 'mizzey_signup_welcome';
    public const GROUP = 'mizzey-signup';
    public const EMAIL = 'MizzeySite_Signup_Welcome';

    /** Welcomes sent in any hour, at most. */
    public const CEILING = 200;

    public static function register(): void
    {
        add_filter('woocommerce_email_classes', [self::class, 'email']);
        add_action(self::HOOK, [self::class, 'send'], 10, 1);
    }

    /**
     * @param mixed $emails
     * @return mixed
     */
    public static function email($emails)
    {
        if (is_array($emails) && class_exists('\WC_Email')) {
            require_once __DIR__ . '/WelcomeEmail.php';
            $emails[self::EMAIL] = new WelcomeEmail();
        }

        return $emails;
    }

    public static function queue(int $id): void
    {
        if (function_exists('as_enqueue_async_action')) {
            if (! function_exists('as_has_scheduled_action') || ! as_has_scheduled_action(self::HOOK, [$id], self::GROUP)) {
                as_enqueue_async_action(self::HOOK, [$id], self::GROUP);
            }

            return;
        }
        self::send($id);
    }

    /** The hourly ceiling, filterable; never below zero. */
    public static function ceiling(): int
    {
        return max(0, (int) apply_filters('mizzey_signup_welcome_ceiling', self::CEILING));
    }

    /**
     * @param mixed $id
     * @throws \RuntimeException When the mail server refuses it, so the queued action is marked failed.
     */
    public static function send($id): void
    {
        global $wpdb;
        $id   = (int) $id;
        $lock = substr('mz_' . $wpdb->prefix . 'signup_welcome_' . $id, 0, 64);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $lock)) !== 1) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            return;
        }
        try {
            $record = SignupStore::byId($id);
            if ($record === null || $record['status'] !== SignupStore::SUBSCRIBED || $record['welcomed_at'] !== null || ! function_exists('WC')) {
                return;
            }
            $email = WC()->mailer()->get_emails()[self::EMAIL] ?? null;
            if (! $email instanceof WelcomeEmail) {
                return;
            }
            if (! $email->is_enabled()) {
                return; // Switched off by staff: it stays owed.
            }
            if (SignupStore::welcomedSince(time() - HOUR_IN_SECONDS) >= self::ceiling()) {
                if (function_exists('as_schedule_single_action')) {
                    as_schedule_single_action(time() + HOUR_IN_SECONDS, self::HOOK, [$id], self::GROUP);
                }

                return;
            }
            if (! self::during((string) $record['language'], static fn (): bool => $email->trigger($record))) {
                throw new \RuntimeException(implode(' ', ['the mail server did not accept the welcome of sign-up', (string) $id]));
            }
            SignupStore::update($id, ['welcomed_at' => current_time('mysql', true)]);
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }
    }

    /**
     * Runs work in a language: WPML's language and the WordPress locale switched before it and restored after
     * (as Emails\OrderLanguage does for an order).
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public static function during(string $language, callable $work)
    {
        $current = (string) apply_filters('wpml_current_language', null);
        $locale  = (string) (((array) apply_filters('wpml_active_languages', null, ['skip_missing' => 0]))[$language]['default_locale'] ?? '');
        if ($language !== '') {
            do_action('wpml_switch_language', $language);
        }
        $switched = $locale !== '' && switch_to_locale($locale);
        try {
            return $work();
        } finally {
            if ($switched) {
                restore_previous_locale();
            }
            if ($language !== '') {
                do_action('wpml_switch_language', $current !== '' ? $current : null);
            }
        }
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Security;

defined('ABSPATH') || exit;

/**
 * The log of sign-ins and security events (ADM-132, ADM-134), kept in CoreX Activity behind this thin adapter (D-20
 * A14): CoreX owns the record, its table and its retention; Mizzey decides which events are written and shows them.
 *
 * Recorded, in CoreX's `security` area: a staff sign-in, a sign-out, every failed sign-in (a wrong password or a wrong
 * second factor, for any account), a lockout CoreX's sign-in protection imposes, a password reset requested and
 * completed. A customer's successful sign-in is not recorded: it is not a security event, and the log is the staff's.
 * Nothing here records refunds, prices, stock or permission changes: that audit trail is ROLE-10, deferred (CX-03).
 *
 * Register ids: ADM-132, ADM-134. See specs/031-staff-activity-log.
 */
final class StaffActivityLog
{
    /** Every kind this log writes starts with it. */
    public const PREFIX          = 'mizzey' . '.';
    public const SIGNIN          = 'mizzey.signin.succeeded';
    public const SIGNIN_FAILED   = 'mizzey.signin.failed';
    public const SECOND_FACTOR   = 'mizzey.signin.second_factor_failed';
    public const LOCKOUT         = 'mizzey.signin.locked_out';
    public const SIGNOUT         = 'mizzey.signout';
    public const RESET_REQUESTED = 'mizzey.password.reset_requested';
    public const RESET_DONE      = 'mizzey.password.reset';

    /** The label of an attempt that names no existing account (a stored value, shown in the English-only admin). */
    public const UNKNOWN = 'unknown-account';

    /** How long an entry is kept: a working default (D-10), one year. */
    public const RETENTION_DAYS = 365;

    /** @var callable|null A stand-in for CoreX's service, for the tests. */
    public static $sink = null;

    /** Whether this request failed at the password stage (before the sign-in messages make every failure alike). */
    private static bool $passwordStageFailed = false;

    /** @var array<int,bool> Accounts whose sign-in this request has recorded. */
    private static array $recorded = [];

    public static function register(): void
    {
        // After WP 2FA's own wp_login handler (priority 20), which challenges a staff member for the second factor and
        // ends the request: a sign-in is recorded only once it is complete, here or when WP 2FA finishes it.
        add_action('wp_login', [self::class, 'signedIn'], 99, 2);
        add_action('wp_2fa_user_authenticated', [self::class, 'secondFactorPassed'], 10, 1);
        add_filter('authenticate', [self::class, 'notePasswordStage'], 99, 1);
        add_action('wp_login_failed', [self::class, 'failed'], 20, 2);
        add_action('wp_logout', [self::class, 'signedOut'], 20, 1);
        add_action('retrieve_password', [self::class, 'resetRequested'], 20, 1);
        add_action('after_password_reset', [self::class, 'resetDone'], 20, 1);
        add_action('corex_booted', [self::class, 'listen']);
        if (class_exists('\Corex\Boot') && \Corex\Boot::booted()) {
            self::listen(\Corex\Boot::app());
        }
    }

    /**
     * CoreX's lockout is announced on its own event bus, not as a WordPress action.
     *
     * @param mixed $app
     */
    public static function listen($app = null): void
    {
        static $listening = false;
        if ($listening || ! is_object($app) || ! method_exists($app, 'container') || ! class_exists('\Corex\Events\ListenerProvider') || ! class_exists('\Corex\Security\LoginLockoutEvent')) {
            return;
        }
        try {
            $app->container()->make(\Corex\Events\ListenerProvider::class)->listen(\Corex\Security\LoginLockoutEvent::class, [self::class, 'lockedOut']);
            $listening = true;
        } catch (\Throwable $e) {
            wc_get_logger()->warning('staff log: cannot listen for lockouts: ' . $e->getMessage(), ['source' => 'mizzey-staff-log']);
        }
    }

    /**
     * Whether an account is staff: anyone who can reach the administration beyond their own profile.
     */
    public static function isStaff(\WP_User $user): bool
    {
        return user_can($user, 'edit_posts') || user_can($user, 'manage_woocommerce') || user_can($user, 'edit_shop_orders');
    }

    /**
     * @param mixed $login
     * @param mixed $user
     */
    public static function signedIn($login, $user = null): void
    {
        if (! $user instanceof \WP_User || ! self::isStaff($user) || isset(self::$recorded[$user->ID])) {
            return;
        }
        self::$recorded[$user->ID] = true;
        self::write($user->ID, $user->user_login, self::SIGNIN, 'success', (string) $user->ID, $user->user_login, ['roles' => array_values($user->roles)]);
    }

    /** @param mixed $user */
    public static function secondFactorPassed($user): void
    {
        if ($user instanceof \WP_User) {
            self::signedIn($user->user_login, $user);
        }
    }

    /**
     * The password check's own verdict, read before Mizzey's sign-in messages (priority 100) make every failure the
     * same generic error.
     *
     * @param mixed $user
     * @return mixed
     */
    public static function notePasswordStage($user)
    {
        if ($user instanceof \WP_Error) {
            self::$passwordStageFailed = true;
        }

        return $user;
    }

    /**
     * A failed sign-in: a wrong password or account, or a wrong second factor (WP 2FA reports it here, as
     * `authentication_failed`).
     *
     * @param mixed $login
     * @param mixed $error
     */
    public static function failed($login, $error = null): void
    {
        $typed = mb_substr(sanitize_text_field((string) $login), 0, 100);
        $user  = $typed === '' ? false : (get_user_by('login', $typed) ?: get_user_by('email', $typed));
        $code  = $error instanceof \WP_Error ? (string) $error->get_error_code() : '';
        $kind  = self::secondFactor($error) ? self::SECOND_FACTOR : self::SIGNIN_FAILED;
        $id    = $user instanceof \WP_User ? $user->ID : 0;
        self::write($id, $user instanceof \WP_User ? $user->user_login : '', $kind, 'failure', (string) $id, $typed, ['reason' => sanitize_key($code)]);
    }

    /**
     * Whether a failure is a wrong second factor. WP 2FA reports it with WordPress's generic `authentication_failed`
     * code, which Mizzey's sign-in messages also give a wrong password, so the password stage is watched: WP 2FA's
     * second step does not run the password check, so a failure with no password-stage failure before it in the
     * request is WP 2FA's, whatever fields the request carries.
     *
     * @param mixed $error
     */
    private static function secondFactor($error): bool
    {
        if (! $error instanceof \WP_Error || $error->get_error_code() !== 'authentication_failed') {
            return false;
        }

        return ! self::$passwordStageFailed;
    }

    /** @param mixed $userId */
    public static function signedOut($userId = 0): void
    {
        $user = get_user_by('id', (int) $userId);
        if (! $user instanceof \WP_User || ! self::isStaff($user)) {
            return;
        }
        self::write($user->ID, $user->user_login, self::SIGNOUT, 'success', (string) $user->ID, $user->user_login, []);
    }

    /** @param mixed $login */
    public static function resetRequested($login): void
    {
        $user = get_user_by('login', (string) $login);
        if (! $user instanceof \WP_User) {
            return;
        }
        self::write($user->ID, $user->user_login, self::RESET_REQUESTED, 'success', (string) $user->ID, $user->user_login, []);
    }

    /** @param mixed $user */
    public static function resetDone($user): void
    {
        if (! $user instanceof \WP_User) {
            return;
        }
        self::write($user->ID, $user->user_login, self::RESET_DONE, 'success', (string) $user->ID, $user->user_login, []);
    }

    /** @param object $event CoreX's LoginLockoutEvent. */
    public static function lockedOut(object $event): void
    {
        $identity = isset($event->identity) && is_string($event->identity) ? mb_substr($event->identity, 0, 100) : '';
        $user     = $identity === '' ? false : (get_user_by('login', $identity) ?: get_user_by('email', $identity));
        $until    = isset($event->lockedUntil) && $event->lockedUntil instanceof \DateTimeInterface ? $event->lockedUntil->format(DATE_ATOM) : '';
        $id       = $user instanceof \WP_User ? $user->ID : 0;
        self::write($id, $user instanceof \WP_User ? $user->user_login : '', self::LOCKOUT, 'denied', (string) $id, $identity, ['locked_until' => $until], isset($event->clientIp) && is_string($event->clientIp) ? $event->clientIp : null);
    }

    /** The visitor's address as CoreX's sign-in protection resolves it, behind the trusted proxy. */
    private static function address(): string
    {
        try {
            if (class_exists('\Corex\Boot') && \Corex\Boot::booted()) {
                return (string) \Corex\Boot::app()->container()->make(\Corex\Config\Security\LoginProtection\ClientIpResolver::class)->resolve($_SERVER);
            }
        } catch (\Throwable $e) {
            unset($e);
        }

        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash((string) $_SERVER['REMOTE_ADDR'])) : '';
    }

    /** @param array<string,mixed> $context */
    private static function write(int $actorId, string $actorLabel, string $kind, string $outcome, string $targetId, string $targetLabel, array $context, ?string $address = null): void
    {
        $context['address'] = $address ?? self::address();
        // CoreX refuses an empty label: a failed sign-in may name no account, or one that does not exist.
        $actorLabel  = $actorLabel !== '' ? $actorLabel : self::UNKNOWN;
        $targetLabel = $targetLabel !== '' ? $targetLabel : self::UNKNOWN;
        $entry = [
            'actor_id'     => $actorId,
            'actor_label'  => $actorLabel,
            'kind'         => $kind,
            'outcome'      => $outcome,
            'target_id'    => $targetId,
            'target_label' => $targetLabel,
            'context'      => $context,
        ];
        if (is_callable(self::$sink)) {
            (self::$sink)($entry);
        }
        try {
            if (! class_exists('\Corex\Boot') || ! \Corex\Boot::booted()) {
                return;
            }
            $now = new \DateTimeImmutable('now');
            \Corex\Boot::app()->container()->make(\Corex\Activity\ActivityService::class)->record(
                $actorId,
                \Corex\Activity\ActivityEvent::ACTOR_USER,
                $actorLabel,
                \Corex\Activity\ActivityEvent::AREA_SECURITY,
                $kind,
                'user',
                $targetId,
                $targetLabel,
                $outcome,
                ['key' => $kind, 'args' => []],
                $context,
                \Corex\Activity\ActivityEvent::SENSITIVITY_SECURITY,
                $now->modify('+' . self::RETENTION_DAYS . ' days'),
                $now
            );
        } catch (\Throwable $e) {
            // The sign-in itself must never fail because the log could not be written.
            wc_get_logger()->error('staff log: not recorded: ' . $kind . ': ' . $e->getMessage(), ['source' => 'mizzey-staff-log']);
        }
    }
}

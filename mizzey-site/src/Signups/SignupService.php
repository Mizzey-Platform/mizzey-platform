<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Signups;

defined('ABSPATH') || exit;

use Corex\Boot;
use Corex\Http\ClientAddress;
use MizzeySite\Security\SignupCheck;
use Throwable;

/**
 * What happens when a visitor signs up from the home page (HOME-09; DECISIONS A10 as ruled by the owner on 25 November
 * 2026: one small sign-up and consent record, no customer account, no campaign engine).
 *
 * The checks, in order: the address, which must be an email; the form's token (Security\SignupCheck: signed for this
 * form, timed, single-use); the trap field, which a person never sees and must arrive empty; the limits, per network
 * address (an IPv6 address by its /64 network) and per email, counted under one database lock. Then the record is
 * written in SignupStore and one welcome email is queued (Welcome). A write that fails is answered "failed", never as
 * a sign-up, and logged without the address.
 *
 * - **Nothing tells whether an address is already on the list.** A first sign-up, a repeat, a filled trap and an
 *   address past its own limit all get the same answer. Only a refusal the visitor can act on is different: an address
 *   that is not one, a form that expired, and too many sign-ups from one network address.
 * - **Consent only when the box is ticked.** It is recorded with the version of its words (CONSENT_VERSION) and when;
 *   a later sign-up with the box ticked adds it, one without it never takes it away. Leaving does.
 * - **No WordPress account** is made or looked up.
 *
 * Register id: HOME-09. See specs/066-stage-2-shop-front, slice 5.
 */
final class SignupService
{
    /** The form's name in Security\SignupCheck's tokens, so a token issued for another form is refused here. */
    public const ROUTE       = 'home-signup';
    public const LOG         = 'mizzey-signup';
    public const FIELD_EMAIL = 'mizzey_signup_email';
    public const FIELD_OPTIN = 'mizzey_signup_optin';
    public const FIELD_TOKEN = 'mizzey_home_signup_token';
    public const FIELD_TRAP  = 'mizzey_signup_website';
    public const FIELD_LANG  = 'mizzey_signup_lang';
    public const FIELD_FORM  = 'mizzey_signup_form';

    /**
     * The version of the box's words ("Email me news and offers. Optional, and separate from your account."), kept
     * with each consent. A change to those words in Banners::renderSignup() is a new version here.
     */
    public const CONSENT_VERSION = '2026-11-25';

    /** Seconds a request waits for the counters' lock before it is refused: a limit fails closed. */
    public const LOCK_WAIT = 3;

    /** @var array{address:int,email:int,window:int} */
    private const LIMITS = ['address' => 20, 'email' => 3, 'window' => 600];

    /**
     * The limits, filterable through `mizzey_signup_limits`. The network-address limit is generous because many
     * mobile customers share one address.
     *
     * @return array{address:int,email:int,window:int}
     */
    public static function limits(): array
    {
        $filtered = apply_filters('mizzey_signup_limits', self::LIMITS);
        $limits   = self::LIMITS;
        foreach (array_keys(self::LIMITS) as $key) {
            $value = is_array($filtered) ? filter_var($filtered[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
            if ($value !== false) {
                $limits[$key] = (int) $value;
            }
        }

        return $limits;
    }

    /** An address as it is stored and compared: trimmed and in lower case. */
    public static function normalise(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * @param array{email?:mixed,consent?:mixed,lang?:mixed,token?:mixed,trap?:mixed} $input
     * @return array{state:string,email:string} state: ok, invalid, expired, limited or failed
     */
    public static function submit(array $input, ?string $address = null): array
    {
        $email = self::normalise(is_string($input['email'] ?? null) ? (string) $input['email'] : '');
        if (strlen($email) > 191 || ! is_email($email)) {
            return ['state' => 'invalid', 'email' => ''];
        }
        // The token of Security\SignupCheck: signed for this form, at least seconds and at most two hours old, and
        // spent below, so it serves one sign-up. The trap is checked here, so a filled one gets the usual answer.
        $token = SignupCheck::inspect(self::ROUTE, $input['token'] ?? null, null);
        if ($token === null || SignupCheck::spent($token)) {
            return ['state' => 'expired', 'email' => ''];
        }
        $done = ['state' => 'ok', 'email' => $email];
        if (trim(is_string($input['trap'] ?? null) ? (string) $input['trap'] : '') !== '') {
            return $done;
        }
        // The limits come before the token is spent: spending writes a row, which a client past its limit must not get.
        $counted = self::count($email, $address ?? self::address());
        if ($counted === 'address') {
            return ['state' => 'limited', 'email' => ''];
        }
        if ($counted === 'email') {
            return $done;
        }
        if (! SignupCheck::spend($token)) {
            return ['state' => 'expired', 'email' => ''];
        }
        try {
            self::record($email, self::language($input['lang'] ?? ''), ! empty($input['consent']));
        } catch (SignupStoreError $error) {
            // Never answered as a sign-up. The log names the database's reason, without the address.
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->error('A home page sign-up could not be stored: ' . (string) preg_replace('/[^\s\'"`]+@[^\s\'"`]+/', '[address]', $error->getMessage()), ['source' => self::LOG]);
            }

            return ['state' => 'failed', 'email' => ''];
        }

        return $done;
    }

    /** The words of a refusal, in the language of the moment. */
    public static function message(string $state): string
    {
        return match ($state) {
            'invalid'     => __('Enter an email address like name@example.com.', 'mizzey-site'),
            'expired'     => __('This form could not be checked. Reload the page and try again.', 'mizzey-site'),
            'again'       => __('Your address is kept. Press Sign up again to finish.', 'mizzey-site'),
            'limited'     => __('Too many sign-ups from your connection. Please wait a few minutes and try again.', 'mizzey-site'),
            'failed'      => __('We could not sign you up just now. Please try again in a few minutes.', 'mizzey-site'),
            'offline'     => __('We could not reach Mizzey. Check your connection and try again.', 'mizzey-site'),
            'unavailable' => __('Sign-up is paused for a moment while we work on the site. Please try again shortly.', 'mizzey-site'),
            default       => '',
        };
    }

    /**
     * Marks the record of a leave token "left", once; the record is kept. Null for a token that matches none.
     *
     * @return array<string,mixed>|null
     */
    public static function leave(string $token): ?array
    {
        $record = SignupStore::byToken($token);
        if ($record === null) {
            return null;
        }
        if ($record['status'] !== SignupStore::LEFT) {
            $now = current_time('mysql', true);
            SignupStore::update((int) $record['id'], ['status' => SignupStore::LEFT, 'left_at' => $now, 'consent' => 0, 'consent_version' => '', 'consent_at' => null]);
            $record = SignupStore::byId((int) $record['id']) ?? $record;
        }

        return $record;
    }

    /** The leave link of a record, on the home page in the record's language. @param array<string,mixed> $record */
    public static function leaveUrl(array $record): string
    {
        $home = (string) apply_filters('wpml_permalink', home_url('/'), (string) ($record['language'] ?? ''));

        return add_query_arg(SignupRoutes::LEAVE, (string) ($record['token'] ?? ''), $home);
    }

    /** A language the site has, or the default one. */
    public static function language(mixed $code): string
    {
        $code      = is_string($code) ? sanitize_key($code) : '';
        $languages = (array) apply_filters('wpml_active_languages', null, ['skip_missing' => 0]);
        if ($code !== '' && isset($languages[$code])) {
            return $code;
        }
        $current = (string) apply_filters('wpml_current_language', null);
        if ($current !== '' && isset($languages[$current])) {
            return $current;
        }
        $default = (string) apply_filters('wpml_default_language', null);

        return $default !== '' ? $default : substr((string) get_locale(), 0, 2);
    }

    /** Removes the counters of a network address and, when given, of emails. The scenarios use it to clean up. */
    public static function forget(string $address, string ...$emails): void
    {
        delete_transient(self::key('address', self::network($address)));
        foreach ($emails as $email) {
            delete_transient(self::key('email', self::normalise($email)));
        }
    }

    /**
     * Writes the sign-up and queues its welcome when one is due: for a new address, an address back after leaving, and
     * an address whose welcome is still owed (it could not be sent, or the email was switched off then).
     *
     * @throws SignupStoreError When the database refuses a read or a write.
     */
    private static function record(string $email, string $language, bool $consent): void
    {
        $existing = SignupStore::find($email);
        $now      = current_time('mysql', true);
        if ($existing === null) {
            $id = SignupStore::insert(['email' => $email, 'language' => $language, 'consent' => $consent, 'consent_version' => self::CONSENT_VERSION]);
            if ($id > 0) {
                Welcome::queue($id);

                return;
            }
            // Another request stored the same address a moment ago.
            $existing = SignupStore::find($email);
            if ($existing === null) {
                throw new SignupStoreError('the address was neither inserted nor found');
            }
        }
        $id = (int) $existing['id'];
        if ($existing['status'] === SignupStore::LEFT) {
            // Back after leaving: subscribed again, in the language and with the box as answered now, and welcomed again.
            SignupStore::update($id, [
                'status'          => SignupStore::SUBSCRIBED,
                'language'        => $language,
                'left_at'         => null,
                'welcomed_at'     => null,
                'consent'         => $consent ? 1 : 0,
                'consent_version' => $consent ? self::CONSENT_VERSION : '',
                'consent_at'      => $consent ? $now : null,
            ]);
            Welcome::queue($id);

            return;
        }
        if ($consent && (int) $existing['consent'] !== 1) {
            SignupStore::update($id, ['consent' => 1, 'consent_version' => self::CONSENT_VERSION, 'consent_at' => $now]);
        }
        if ($existing['welcomed_at'] === null) {
            Welcome::queue($id);
        }
    }

    /**
     * Counts one sign-up against the network address and the email, under one lock; the counters are fixed windows
     * from their first sign-up. Returns which limit was reached (nothing is counted then), or '' when within both.
     */
    private static function count(string $email, string $address): string
    {
        $limits   = self::limits();
        $counters = ['email' => [self::key('email', $email), $limits['email']]];
        if ($address !== '') {
            $counters = ['address' => [self::key('address', self::network($address)), $limits['address']]] + $counters;
        }
        global $wpdb;
        $lock = substr('mizzey_signup_' . $wpdb->prefix, 0, 64);
        if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock, self::LOCK_WAIT)) !== '1') { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            return 'address';
        }
        try {
            $now = time();
            foreach ($counters as $name => [$key, $max]) {
                if (self::read($key, $now, $limits['window'])['count'] >= $max) {
                    return $name;
                }
            }
            foreach ($counters as [$key]) {
                $counter = self::read($key, $now, $limits['window']);
                set_transient($key, ['count' => $counter['count'] + 1, 'until' => $counter['until']], max(1, $counter['until'] - $now));
            }

            return '';
        } finally {
            $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }
    }

    /**
     * What a network address is counted by: an IPv4 address as it is, an IPv6 address by its /64 network, which one
     * customer's line usually gets whole, so changing the host part does not reset the count.
     */
    public static function network(string $address): string
    {
        if (! str_contains($address, ':')) {
            return $address;
        }
        $packed = @inet_pton($address); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- a malformed address is kept as given.
        if ($packed === false || strlen($packed) !== 16) {
            return $address;
        }
        // An IPv4-mapped address (::ffff:a.b.c.d) is an IPv4 client: counted as that address, never as the one /64
        // every mapped address shares.
        if (substr($packed, 0, 12) === str_repeat(chr(0), 10) . str_repeat(chr(255), 2)) {
            return (string) inet_ntop(substr($packed, 12));
        }

        return (string) inet_ntop(substr($packed, 0, 8) . str_repeat(chr(0), 8)) . '/64';
    }

    /** @return array{count:int,until:int} */
    private static function read(string $key, int $now, int $window): array
    {
        $stored = get_transient($key);
        if (! is_array($stored) || ! isset($stored['count'], $stored['until']) || (int) $stored['until'] <= $now) {
            return ['count' => 0, 'until' => $now + $window];
        }

        return ['count' => (int) $stored['count'], 'until' => (int) $stored['until']];
    }

    private static function key(string $kind, string $value): string
    {
        return 'mizzey_signup_' . substr(wp_hash($kind . '|' . $value), 0, 32);
    }

    /** The visitor's network address as CoreX's ClientAddress resolves it (the one its sign-in lockout uses). */
    private static function address(): string
    {
        if (class_exists(Boot::class) && Boot::booted()) {
            try {
                return (string) Boot::app()->container()->make(ClientAddress::class)->current();
            } catch (Throwable) {
                // Fall through to the connection address.
            }
        }
        $address = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP);

        return $address === false ? '' : (string) $address;
    }
}

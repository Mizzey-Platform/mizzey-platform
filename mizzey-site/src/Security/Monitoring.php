<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Security;

defined('ABSPATH') || exit;

/**
 * Observability (NFR-10, P1-L: the standard platform and hosting logs, no custom operational audit trail).
 *
 * - **Application log**: WooCommerce's logger, kept and rotated by WooCommerce (Status, Logs), with one source per
 *   area: `mizzey-errors` for the errors below, `mizzey-payments`, `mizzey-stock` and the others for integrations.
 * - **Error monitoring**: a fatal PHP error, a failed or timed-out scheduled action (WooCommerce's Action Scheduler),
 *   and any error a Mizzey integration logs (payments, stock, shipping, email) are written to the log and raised to
 *   the staff responsible for operations through CoreX's notifications (D-20 A12, D-21 monitoring), once per kind and
 *   place an hour so a repeated failure does not flood them. No secret or card data is put in a notification: only
 *   where it happened and where to look.
 * - **Integration logs**: each integration's own source (above).
 * - **Admin audit**: the staff log of sign-ins and security events (ADM-132, ADM-134) and the order notes; the
 *   sensitive-operation audit is ROLE-10, deferred.
 *
 * Register ids: NFR-10. See specs/039-observability.
 */
final class Monitoring
{
    public const SOURCE = 'mizzey-errors';
    public const QUIET  = HOUR_IN_SECONDS;

    /** Every Mizzey log source (payments, stock, shipping, email and the others) is watched, but this monitor's own. */
    public const WATCHED_PREFIX = 'mizzey-';

    /** The prefix of this monitor's notification types and keys. */
    private const TYPE = 'mizzey' . '.monitor';

    private const FATAL = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];

    /** @var callable|null A stand-in for CoreX's notifications, for the tests. */
    public static $sink = null;

    private static bool $raising = false;

    private static bool $fatalSeen = false;

    /** Memory set aside at start and released before reporting, so an out-of-memory error can still be reported. */
    private static ?string $reserve = null;

    public static function register(): void
    {
        self::$reserve = str_repeat('0', 512 * 1024);
        register_shutdown_function([self::class, 'shutdown']);
        // WordPress's own handler runs first at shutdown and ends the request with its error page, so the error is
        // taken from its message filter too; the shutdown function covers the cases where WordPress shows no page.
        add_filter('wp_php_error_message', [self::class, 'fromWordPress'], 10, 2);
        add_action('action_scheduler_failed_execution', [self::class, 'actionFailed'], 10, 2);
        add_action('action_scheduler_failed_action', [self::class, 'actionTimedOut'], 10, 1);
        add_filter('woocommerce_logger_log_message', [self::class, 'watch'], 10, 4);
    }

    /** A fatal error that ended the request. */
    public static function shutdown(): void
    {
        $error = error_get_last();
        if (! is_array($error) || ! in_array((int) $error['type'], self::FATAL, true)) {
            return;
        }
        self::fatal((string) $error['message'], (string) $error['file'], (int) $error['line']);
    }

    /**
     * The fatal error WordPress is about to show its error page for.
     *
     * @param mixed $message
     * @param mixed $error
     * @return mixed
     */
    public static function fromWordPress($message, $error = null)
    {
        if (is_array($error) && isset($error['message'], $error['file'], $error['line'])) {
            self::fatal((string) $error['message'], (string) $error['file'], (int) $error['line']);
        }

        return $message;
    }

    public static function fatal(string $message, string $file, int $line): void
    {
        if (self::$fatalSeen) {
            return; // One fatal error per request, whichever of the two routes saw it first.
        }
        self::$fatalSeen = true;
        self::$reserve   = null;

        $where = self::relative($file) . ':' . $line;
        self::error('fatal', $where, sprintf('Fatal error at %s: %s', $where, self::clean($message)), 'error');
    }

    /**
     * @param mixed $actionId
     * @param mixed $exception
     */
    public static function actionFailed($actionId, $exception = null): void
    {
        $hook = self::hookOf((int) $actionId);
        $why  = $exception instanceof \Throwable ? self::clean($exception->getMessage()) : '';
        self::error('scheduled', $hook, sprintf('Scheduled action %d (%s) failed: %s', (int) $actionId, $hook, $why), 'error');
    }

    /** @param mixed $actionId */
    public static function actionTimedOut($actionId): void
    {
        $hook = self::hookOf((int) $actionId);
        self::error('scheduled', $hook, sprintf('Scheduled action %d (%s) timed out', (int) $actionId, $hook), 'error');
    }

    /**
     * An error an integration writes to its own log is raised too.
     *
     * @param mixed $message
     * @param mixed $level
     * @param mixed $context
     * @return mixed
     */
    public static function watch($message, $level = '', $context = [], $handler = null)
    {
        $source = is_array($context) ? (string) ($context['source'] ?? '') : '';
        if (is_string($message) && str_starts_with($source, self::WATCHED_PREFIX)) {
            $message = self::maskCards($message); // A Mizzey log line never keeps a card-like number.
        }
        if (! self::$raising && str_starts_with($source, self::WATCHED_PREFIX) && $source !== self::SOURCE && in_array((string) $level, ['error', 'critical', 'alert', 'emergency'], true)) {
            self::error('integration', $source, sprintf('%s logged an error: %s', $source, self::clean((string) $message)), 'error', false);
        }

        return $message;
    }

    private static function hookOf(int $actionId): string
    {
        try {
            $action = function_exists('ActionScheduler') || class_exists('\ActionScheduler') ? \ActionScheduler::store()->fetch_action((string) $actionId) : null;

            return $action instanceof \ActionScheduler_Action ? (string) $action->get_hook() : 'unknown';
        } catch (\Throwable $e) {
            return 'unknown';
        }
    }

    /** A path inside the site, never the server's absolute layout. */
    private static function relative(string $file): string
    {
        $root = wp_normalize_path(ABSPATH);
        $file = wp_normalize_path($file);

        return str_starts_with($file, $root) ? substr($file, strlen($root)) : basename($file);
    }

    /** A run of 13 to 19 digits, spaces or hyphens allowed between them, as a card number is written. */
    private static function maskCards(string $text): string
    {
        return (string) preg_replace('/(?<!\d)(?:\d[\x20-]?){12,18}\d(?!\d)/', '[#]', $text);
    }

    /**
     * One line, no markup, a bounded length; no stack trace, server path, card-like number, email address or long
     * token.
     */
    private static function clean(string $text): string
    {
        $text = (string) preg_split('/Stack\x20trace:/', wp_strip_all_tags($text))[0];
        $root = wp_normalize_path(ABSPATH);
        $text = str_replace([$root, str_replace('/', '\\', $root)], '', $text);
        // Any other absolute path (outside the site, or another server's) keeps only its file name.
        $text = (string) preg_replace('#(?<![\w.-])(?:[A-Za-z]:)?[\\\\/](?:[\w.-]+[\\\\/])+([\w.-]+\.php)#', '$1', $text);
        $text = (string) preg_replace('/[\w.+-]+@[\w-]+(?:\.[\w-]+)+/', '[email]', $text);
        $text = (string) preg_replace('/\b[A-Za-z0-9_-]{32,}\b/', '[token]', $text);
        $text = self::maskCards($text);

        return mb_substr(trim((string) preg_replace('/\s+/', ' ', $text)), 0, 400);
    }

    /**
     * Log and notify, once per kind and place an hour.
     *
     * @param bool $log Whether to write the log line (an integration's own error is already in its log).
     */
    public static function error(string $kind, string $where, string $line, string $severity, bool $log = true): void
    {
        self::$raising = true;
        // Monitoring never stops the request it watches. The log line and the notification are tried apart, so
        // either still happens when the other fails.
        try {
            if ($log && function_exists('wc_get_logger')) {
                wc_get_logger()->log($severity === 'error' ? 'critical' : 'warning', $line, ['source' => self::SOURCE]);
            }
        } catch (\Throwable $e) {
            unset($e);
        }
        try {
            $key = 'mizzey_monitor_' . md5($kind . '|' . $where);
            if (get_transient($key) !== false) {
                return;
            }
            $entry = ['kind' => $kind, 'where' => $where, 'line' => $line];
            if (is_callable(self::$sink)) {
                (self::$sink)($entry);
            }
            if (self::notify($entry)) {
                set_transient($key, 1, self::QUIET); // The quiet hour starts once staff have been told.
            }
        } catch (\Throwable $e) {
            unset($e);
        } finally {
            self::$raising = false;
        }
    }

    /** @param array{kind:string,where:string,line:string} $entry */
    private static function notify(array $entry): bool
    {
        if (! class_exists('\Corex\Boot') || ! \Corex\Boot::booted() || ! class_exists('\Corex\Notifications\Notification')) {
            return false;
        }
        $titles = [
            'fatal'       => __('The site hit a fatal error', 'mizzey-site'),
            'scheduled'   => __('A scheduled task failed', 'mizzey-site'),
            'integration' => __('An integration reported an error', 'mizzey-site'),
        ];
        \Corex\Boot::app()->container()->make(\Corex\Notifications\NotificationService::class)->publish(\Corex\Notifications\Notification::create(
            type: self::TYPE . '.' . $entry['kind'],
            category: $entry['kind'] === 'scheduled' ? \Corex\Notifications\NotificationCategory::JOBS : \Corex\Notifications\NotificationCategory::OPERATIONS,
            severity: \Corex\Notifications\NotificationSeverity::ERROR,
            sourceModule: 'operations',
            titleKey: self::TYPE . '.' . $entry['kind'] . '.title',
            messageKey: self::TYPE . '.' . $entry['kind'] . '.body',
            rendered: [
                'title' => $titles[$entry['kind']] ?? $titles['integration'],
                'body'  => sprintf(__('%s. The details are in WooCommerce, Status, Logs.', 'mizzey-site'), $entry['where']),
            ],
            dedupKey: self::TYPE . '|' . md5($entry['kind'] . '|' . $entry['where']),
            recipient: \Corex\Notifications\NotificationRecipient::forAbility(\Corex\Access\CorexAbility::MANAGE_OPERATIONS),
            occurredAt: new \DateTimeImmutable('now'),
            sourceType: 'mizzey_monitor',
            sourceId: $entry['where'],
            action: \Corex\Notifications\NotificationAction::to(
                self::TYPE . '.logs',
                admin_url('admin.php?page=wc-status&tab=logs'),
                \Corex\Access\CorexAbility::MANAGE_OPERATIONS,
                __('Open the logs', 'mizzey-site'),
            ),
        ));

        return true;
    }
}

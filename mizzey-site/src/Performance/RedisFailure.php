<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Performance;

defined('ABSPATH') || exit;

/** Redis could not be reached or refused a command: the page cache carries on without it. Logged, never shown. */
final class RedisFailure extends \RuntimeException
{
    /** @param string $what One of unreachable, no-answer, not-sent, refused, unreadable. */
    public function __construct(string $what, string $detail = '')
    {
        parent::__construct(trim(implode(' ', ['redis', $what, $detail])));
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Stock;

defined('ABSPATH') || exit;

/** The source did not answer, and may answer if asked again. The gateway retries it (INT-16). */
final class StockSourceUnavailable extends \RuntimeException
{
}

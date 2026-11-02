<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Stock;

defined('ABSPATH') || exit;

/** The source answered no, and will answer no again: not retried. */
final class StockSourceRefused extends \RuntimeException
{
}

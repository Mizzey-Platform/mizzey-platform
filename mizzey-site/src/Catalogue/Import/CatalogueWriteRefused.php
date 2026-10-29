<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue\Import;

defined('ABSPATH') || exit;

use RuntimeException;

/**
 * Thrown by `CatalogueWriteGuard` when a run would change the catalogue. The command turns it into exit 3.
 */
final class CatalogueWriteRefused extends RuntimeException
{
}

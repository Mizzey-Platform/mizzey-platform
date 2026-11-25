<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Signups;

defined('ABSPATH') || exit;

/**
 * The sign-up store could not read or write: the table is missing, or the database refused the query. A sign-up that
 * meets it is answered "failed", never as a sign-up (SignupService).
 *
 * Register id: HOME-09. See specs/066-stage-2-shop-front, slice 5.
 */
final class SignupStoreError extends \RuntimeException
{
}

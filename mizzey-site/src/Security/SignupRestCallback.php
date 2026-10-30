<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Security;

defined('ABSPATH') || exit;

use WP_REST_Request;

/**
 * Wraps the Store API checkout handler so that no permit outlives it.
 *
 * `rest_request_after_callbacks` does not run when a callback throws, so the handler itself is the only reliable
 * boundary: when it ends, however it ends, its frame's permit is spent.
 *
 * Register id: AUTH-15. See specs/009-sign-in-protection, FR-003.
 */
final class SignupRestCallback
{
    /** @var callable */
    private $original;

    public function __construct(callable $original)
    {
        $this->original = $original;
    }

    public function original(): callable
    {
        return $this->original;
    }

    public function __invoke(WP_REST_Request $request): mixed
    {
        return SignupRoutes::callCheckout($this->original, $request);
    }
}

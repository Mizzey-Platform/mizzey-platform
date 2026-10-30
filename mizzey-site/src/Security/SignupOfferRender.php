<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Security;

defined('ABSPATH') || exit;

/**
 * Wraps the order-received account offer's render callback, the only place WooCommerce processes that form.
 *
 * WooCommerce checks the offer's nonce, the order's customer and email inside the callback and then creates the account,
 * with no hook between. Running the original callback inside this wrapper tells the insertion guard that the account
 * being created is the offer's; the context is restored however the callback ends.
 *
 * Register id: AUTH-15. See specs/009-sign-in-protection, FR-003.
 */
final class SignupOfferRender
{
    /** @var callable */
    private $original;

    public function __construct(callable $original)
    {
        $this->original = $original;
    }

    public function __invoke(mixed ...$args): mixed
    {
        return SignupRoutes::insideOrderReceived(fn () => ($this->original)(...$args));
    }
}

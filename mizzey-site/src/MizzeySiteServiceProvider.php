<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite;

defined('ABSPATH') || exit;

/**
 * The Mizzey site service provider — binds the site's services into Corex's container.
 * App code (Models/Services/Controllers/Api/Blocks/Options) lives under MizzeySite\.
 * REST namespace: mizzey/v1. Option/CPT prefix: mizzey_.
 */
final class MizzeySiteServiceProvider
{
    public function register(): void
    {
        // Bind your site's services here.
    }

    public function boot(): void
    {
        // Wire hooks / routes here.
    }
}

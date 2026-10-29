<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Security;

defined('ABSPATH') || exit;

/**
 * Wires the security baseline's site-level controls. Each control acts only through a hook and writes no setting
 * another component owns (FR-009).
 *
 * Register id: NFR-05. See specs/007-security-baseline and docs/security-baseline.md.
 */
final class Security
{
    public static function register(): void
    {
        Disclosure::register();
        XmlRpc::register();
        AccountDiscovery::register();
        ApplicationPasswords::register();
        SignInMessages::register();
        RecoveryLimiter::register();
        CommentSurface::register();

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('mizzey security', Cli\SecurityCommand::class);
        }
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Security;

defined('ABSPATH') || exit;

/**
 * XML-RPC is refused before it dispatches anything.
 *
 * Neither native switch closes it: `xmlrpc_enabled` leaves the unauthenticated methods (pingbacks among them) on, and
 * an empty `xmlrpc_methods` still leaves the XML-RPC server's own `system.listMethods`, `system.getCapabilities` and
 * `system.multicall`, while `xmlrpc.php?rsd` answers before any method runs. So a request that entered through
 * `xmlrpc.php` is answered 403 at `init`, before WordPress reaches its XML-RPC code. The two native switches stay as
 * a second layer. No contracted surface uses XML-RPC; the server also refuses the file where it can, and this holds
 * on any host.
 *
 * Register id: NFR-05 (admin protection). See specs/007-security-baseline, FR-002.
 */
final class XmlRpc
{
    public static function register(): void
    {
        add_action('init', [self::class, 'refuse'], 0);
        add_filter('xmlrpc_enabled', '__return_false');
        add_filter('xmlrpc_methods', [self::class, 'noMethods'], PHP_INT_MAX);
    }

    public static function refuse(): void
    {
        if (! self::isXmlRpcRequest()) {
            return;
        }
        status_header(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'XML-RPC is not available on this site.';
        exit;
    }

    /**
     * @return array<string, string>
     */
    public static function noMethods(): array
    {
        return [];
    }

    private static function isXmlRpcRequest(): bool
    {
        return defined('XMLRPC_REQUEST') && XMLRPC_REQUEST;
    }
}

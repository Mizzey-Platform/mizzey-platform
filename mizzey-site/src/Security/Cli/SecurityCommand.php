<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Security\Cli;

defined('ABSPATH') || exit;

use MizzeySite\Security\BaselineReport;
use WP_CLI;

/**
 * Reports the security baseline.
 *
 * Register ids: NFR-05, US-27-03. See specs/007-security-baseline, FR-008.
 */
final class SecurityCommand
{
    /**
     * Lists every control of the security baseline with its state.
     *
     * Exits 1 when a control of the baseline does not pass. Configuration facts are listed and never fail the check.
     *
     * ## EXAMPLES
     *
     *     wp mizzey security check
     *
     * @param list<string>          $args
     * @param array<string, string> $assoc
     */
    public function check(array $args, array $assoc): void
    {
        $rows = BaselineReport::rows();
        WP_CLI\Utils\format_items('table', $rows, ['key', 'status', 'detail']);

        $failing = array_filter(
            $rows,
            static fn (array $row): bool => in_array($row['key'], BaselineReport::CONTROLS, true) && $row['status'] !== 'pass',
        );
        if ($failing !== []) {
            WP_CLI::error(sprintf(
                /* translators: 1: how many controls fail, 2: their keys. */
                __('%1$d control(s) need review: %2$s', 'mizzey-site'),
                count($failing),
                implode(', ', array_column($failing, 'key')),
            ));
        }
        WP_CLI::success(__('Every baseline check passes. Request behaviour is verified by the integration scenarios.', 'mizzey-site'));
    }
}

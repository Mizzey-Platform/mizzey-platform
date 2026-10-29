<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue\Import;

defined('ABSPATH') || exit;

/**
 * Records one validation run with the payload of the CX-04 integration dependency.
 *
 * The persisted Import Run (ENT-19) is #273's. Until it exists, `FileImportRunRecorder` writes the payload beside the
 * reports, marked as a fixture-adapter record; #273 adds a recorder that stores it, and the pass does not change.
 *
 * Register ids: MIG-06 (CX-04, pending). See specs/006-catalogue-import-spec, FR-010, research R-6.
 */
interface ImportRunRecorder
{
    /**
     * @param array<string, mixed> $payload
     *
     * @return string A reference to the record.
     */
    public function record(array $payload): string;
}

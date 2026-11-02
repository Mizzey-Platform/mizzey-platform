<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue\Import;

defined('ABSPATH') || exit;

use RuntimeException;

/**
 * Writes the run payload as `run.json` beside the reports: a copy for the migration evidence. The persisted Import Run
 * (ENT-19) is ImportRunLog's, and the copy names it under `record`.
 *
 * Register ids: MIG-06 (CX-04). See specs/006-catalogue-import-spec, FR-010.
 */
final class FileImportRunRecorder implements ImportRunRecorder
{
    public const FILE = 'run.json';

    public function __construct(private readonly string $directory)
    {
    }

    public function record(array $payload): string
    {
        $path    = $this->directory . '/' . self::FILE;
        $payload = ['recorder' => 'file-copy', 'persisted' => false] + $payload;
        $json    = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $bytes   = $json === false ? false : file_put_contents($path, $json . "\n");
        if ($json === false || $bytes !== strlen($json . "\n")) {
            throw new RuntimeException(sprintf(
                /* translators: %s: a file path. */
                __('The run record cannot be written: %s', 'mizzey-site'),
                $path,
            ));
        }

        return $path;
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue\Import;

defined('ABSPATH') || exit;

use RuntimeException;

/**
 * Writes the run payload as `run.json` beside the reports. A fixture record: it proves no database persistence.
 *
 * Register ids: MIG-06 (CX-04, pending). See specs/006-catalogue-import-spec, FR-010.
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
        $payload = $payload + ['recorder' => 'fixture-adapter', 'persisted' => false];
        $json    = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents($path, $json . "\n") === false) {
            throw new RuntimeException(sprintf(
                /* translators: %s: a file path. */
                __('The run record cannot be written: %s', 'mizzey-site'),
                $path,
            ));
        }

        return $path;
    }
}

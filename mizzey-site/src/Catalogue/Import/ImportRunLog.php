<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue\Import;

defined('ABSPATH') || exit;

use RuntimeException;

/**
 * The persisted Import Run record (ENT-19, CX-04 resolved by D-12): one row per developer-operated run, validation or
 * load, kept in the database and never overwritten by a later run.
 *
 * Each run is one option of its own, not autoloaded (`mizzey_import_run_<id>`), holding the file and its reference,
 * the mapping used and its version, the rules version, the row counts, the outcome, the actor and the times; a load
 * adds what it wrote, its problems and its warnings when it ends. There is no screen: the migration report cites a run
 * by its id. Nothing deletes a record.
 *
 * Register ids: ENT-19, MIG-06, MIG-19 (CX-04). See specs/040-data-model and specs/046-catalogue-load.
 */
final class ImportRunLog implements ImportRunRecorder
{
    public const PREFIX = 'mizzey_import_run_';

    /**
     * @param array<string, mixed> $payload
     *
     * @return string The record's reference: its option name.
     */
    public function record(array $payload): string
    {
        $id     = sanitize_key((string) ($payload['id'] ?? '')) ?: wp_generate_uuid4();
        $name   = self::PREFIX . $id;
        $record = ['id' => $id, 'recorder' => 'database', 'persisted' => true, 'recorded_at' => gmdate('c')] + $payload;
        if (get_option($name, null) !== null || ! add_option($name, $record, '', false)) {
            throw new RuntimeException(sprintf(
                /* translators: %s: a record name. */
                __('The import run cannot be recorded: %s', 'mizzey-site'),
                $name,
            ));
        }

        return $name;
    }

    /** @param array<string, mixed> $changes Fields added to a run's record when it ends; earlier fields are kept. */
    public static function update(string $id, array $changes): void
    {
        $name   = self::PREFIX . sanitize_key($id);
        $record = get_option($name, null);
        if (is_array($record)) {
            update_option($name, array_replace($record, $changes), false);
        }
    }

    /** @return array<string, mixed>|null */
    public static function get(string $reference): ?array
    {
        $name   = str_starts_with($reference, self::PREFIX) ? $reference : self::PREFIX . sanitize_key($reference);
        $record = get_option($name, null);

        return is_array($record) ? $record : null;
    }
}

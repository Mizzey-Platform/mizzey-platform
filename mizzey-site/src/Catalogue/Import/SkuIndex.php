<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue\Import;

defined('ABSPATH') || exit;

use RuntimeException;

/**
 * Resolves each SKU of the file to the store record a load would act on, and its language role.
 *
 * WooCommerce's own lookup (`wc_get_product_id_by_sku()`) returns whichever record the query reaches first, and a WPML
 * duplicate carries its original's SKU (A5), so it cannot say which record a load would update. This reads every
 * matching product and variation, never one, in batched prepared queries on WooCommerce's indexed lookup table, with
 * trashed records left out.
 *
 * Matching is the database's own: the comparison key of a SKU is its collation weight under the lookup column's actual
 * collation (`WEIGHT_STRING`), so case, accents and trailing spaces count exactly as the store's SKU lookup and
 * uniqueness check count them, never as a PHP approximation. Surrounding spaces are trimmed first, as the loader trims
 * them (WooCommerce's importer cleans the SKU with `wc_clean()`). A match that is not byte for byte is kept and
 * reported (W1), not hidden and not turned into a create.
 *
 * Each candidate's language details come from WPML with its real element type. A candidate without them is an
 * identity problem, not an original. The whole translation group is then read, including members whose SKU differs,
 * so an original that is merely absent from the candidates is told apart from one that is missing.
 *
 * Read-only. Register ids: MIG-06. See specs/006-catalogue-import-spec, research R-4, FR-006.
 */
final class SkuIndex
{
    private const BATCH = 500;

    /** @var array<string, string> Trimmed SKU to its collation key. */
    private array $keys = [];

    /** @var array<string, array<int, array{id:int,stored_sku:string,kind:string}>> */
    private array $byKey = [];

    private string $collation;

    private function __construct()
    {
        $this->collation = self::columnCollation();
    }

    /**
     * @param list<string> $skus Source SKUs, as written.
     *
     * @throws RuntimeException When the catalogue cannot be read.
     */
    public static function build(array $skus): self
    {
        global $wpdb;
        $index  = new self();
        $values = array_values(array_unique(array_filter(array_map('trim', $skus), static fn (string $sku): bool => $sku !== '')));
        $index->learnKeys($values);
        $posts  = $wpdb->posts;
        $lookup = $wpdb->prefix . 'wc_product_meta_lookup';
        foreach (array_chunk($values, self::BATCH) as $batch) {
            $placeholders = implode(',', array_fill(0, count($batch), '%s'));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $found = $wpdb->get_results($wpdb->prepare("SELECT l.product_id AS id, l.sku AS sku, p.post_type AS post_type FROM {$lookup} l JOIN {$posts} p ON p.ID = l.product_id WHERE p.post_type IN ('product', 'product_variation') AND p.post_status <> 'trash' AND l.sku IN ({$placeholders})", $batch), ARRAY_A);
            if (! is_array($found) || $wpdb->last_error !== '') {
                throw new RuntimeException(__('The catalogue SKU index could not be read.', 'mizzey-site'));
            }
            $index->learnKeys(array_map(static fn (array $row): string => trim((string) $row['sku']), $found));
            foreach ($found as $row) {
                $index->byKey[$index->key((string) $row['sku'])][(int) $row['id']] = [
                    'id'         => (int) $row['id'],
                    'stored_sku' => (string) $row['sku'],
                    'kind'       => $row['post_type'] === 'product_variation' ? 'variation' : 'product',
                ];
            }
        }

        return $index;
    }

    /**
     * The comparison key the store's collation gives a SKU, surrounding spaces trimmed as the loader trims them.
     */
    public function key(string $sku): string
    {
        $trimmed = trim($sku);
        if (! isset($this->keys[$trimmed])) {
            $this->learnKeys([$trimmed]);
        }

        return $this->keys[$trimmed];
    }

    /**
     * @return array{status:string,candidates:list<array{id:int,stored_sku:string,kind:string,comparison:string,language:string,role:string}>,id?:int,kind?:string,product_type?:string,translations?:list<int>,original_matched?:bool}
     */
    public function resolve(string $sourceSku): array
    {
        $candidates = [];
        foreach ($this->byKey[$this->key($sourceSku)] ?? [] as $candidate) {
            $candidate['comparison'] = $candidate['stored_sku'] === $sourceSku ? 'exact' : 'collation';
            $candidates[]            = $candidate;
        }
        if ($candidates === []) {
            return ['status' => 'none', 'candidates' => []];
        }

        $groups   = [];
        $identity = false;
        foreach ($candidates as $index => $candidate) {
            $details = self::details($candidate['id'], $candidate['kind']);
            if ($details === null) {
                $identity                       = true;
                $candidates[$index]['language'] = '';
                $candidates[$index]['role']     = '';
                continue;
            }
            $candidates[$index]['language'] = (string) $details->language_code;
            $candidates[$index]['role']     = empty($details->source_language_code) ? 'original' : 'translation';
            $groups[(int) $details->trid]   = $candidate['kind'];
        }
        if ($identity) {
            return ['status' => 'identity', 'candidates' => $candidates];
        }
        if (count($groups) > 1) {
            return ['status' => 'ambiguous', 'candidates' => $candidates];
        }

        $trid     = (int) array_key_first($groups);
        $kind     = $groups[$trid];
        $original = null;
        $members  = [];
        foreach (self::group($trid, $kind) as $member) {
            $id     = (int) $member->element_id;
            $status = get_post_status($id);
            if ($status === 'trash' || $status === false) {
                continue;
            }
            $members[] = $id;
            if (empty($member->source_language_code)) {
                $original = $id;
            }
        }
        if ($original === null) {
            return ['status' => 'missing_original', 'candidates' => $candidates];
        }

        return [
            'status'           => 'original',
            'candidates'       => $candidates,
            'id'               => $original,
            'kind'             => $kind,
            'product_type'     => $kind === 'product' ? (string) \WC_Product_Factory::get_product_type($original) : 'variation',
            'translations'     => array_values(array_diff($members, [$original])),
            'original_matched' => in_array($original, array_column($candidates, 'id'), true),
        ];
    }

    /**
     * Asks the database for the collation weight of each value it does not know yet, in batches.
     *
     * @param list<string> $values Trimmed SKUs.
     *
     * @throws RuntimeException When the weights cannot be read.
     */
    private function learnKeys(array $values): void
    {
        global $wpdb;
        $unknown = array_values(array_unique(array_filter($values, fn (string $value): bool => ! isset($this->keys[$value]))));
        // One row, one column per value, and no table: wpdb checks non-ASCII text against the charset of the tables a
        // query names, and a derived table is not one it can describe, so it would refuse the query.
        foreach (array_chunk($unknown, self::BATCH) as $batch) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
            $weights = $wpdb->get_row($wpdb->prepare('SELECT ' . implode(', ', array_fill(0, count($batch), "HEX(WEIGHT_STRING(CONVERT(%s USING utf8mb4) COLLATE {$this->collation}))")), $batch), ARRAY_N);
            if (! is_array($weights) || count($weights) !== count($batch) || $wpdb->last_error !== '') {
                throw new RuntimeException(__('The SKU comparison keys could not be read.', 'mizzey-site'));
            }
            foreach ($batch as $position => $value) {
                $this->keys[$value] = 'sku_' . $weights[$position];
            }
        }
    }

    /**
     * The collation of WooCommerce's SKU lookup column, read from the database, never assumed.
     *
     * @throws RuntimeException When the column cannot be described.
     */
    private static function columnCollation(): string
    {
        global $wpdb;
        $lookup = $wpdb->prefix . 'wc_product_meta_lookup';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $column    = $wpdb->get_row("SHOW FULL COLUMNS FROM {$lookup} WHERE Field = 'sku'", ARRAY_A);
        $collation = is_array($column) ? (string) ($column['Collation'] ?? '') : '';
        if (preg_match('/^[a-z0-9_]+$/', $collation) !== 1) {
            throw new RuntimeException(__('The collation of the SKU lookup column could not be read.', 'mizzey-site'));
        }

        return $collation;
    }

    private static function details(int $id, string $kind): ?object
    {
        $details = apply_filters('wpml_element_language_details', null, ['element_id' => $id, 'element_type' => self::elementType($kind)]);

        return is_object($details) && ! empty($details->language_code) && ! empty($details->trid) ? $details : null;
    }

    /**
     * @return array<string, object>
     */
    private static function group(int $trid, string $kind): array
    {
        $members = apply_filters('wpml_get_element_translations', null, $trid, self::elementType($kind), false, true);

        return is_array($members) ? $members : [];
    }

    private static function elementType(string $kind): string
    {
        return $kind === 'variation' ? 'post_product_variation' : 'post_product';
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue\Import;

defined('ABSPATH') || exit;

/**
 * Resolves each SKU of the file to the store record a load would act on, and its language role.
 *
 * WooCommerce's own lookup (`wc_get_product_id_by_sku()`) returns whichever record the query reaches first, and a WPML
 * duplicate carries its original's SKU (A5), so it cannot say which record a load would update. This reads every
 * matching product and variation, never one, in batched prepared queries on WooCommerce's indexed lookup table, with
 * trashed records left out. The SKU column's collation ignores case, accents and trailing spaces, as the store's own
 * lookup does, so a match that is not byte for byte is kept and reported (W1) rather than hidden or turned into a
 * create.
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

    /**
     * @param array<string, list<array{id:int,stored_sku:string,kind:string}>> $byKey
     */
    private function __construct(private readonly array $byKey)
    {
    }

    /**
     * The comparison key the store's collation implies: case, accents and surrounding spaces do not count.
     */
    public static function key(string $sku): string
    {
        return mb_strtolower(remove_accents(trim($sku)));
    }

    /**
     * @param list<string> $skus Source SKUs, as written.
     */
    public static function build(array $skus): self
    {
        global $wpdb;
        $values = array_values(array_unique(array_filter(array_map('trim', $skus), static fn (string $sku): bool => $sku !== '')));
        $byKey  = [];
        foreach (array_chunk($values, self::BATCH) as $batch) {
            $placeholders = implode(',', array_fill(0, count($batch), '%s'));
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the placeholders are generated above.
            $sql = "SELECT l.product_id AS id, l.sku AS sku, p.post_type AS post_type FROM {$wpdb->prefix}wc_product_meta_lookup l "
                . "JOIN {$wpdb->posts} p ON p.ID = l.product_id "
                . "WHERE p.post_type IN ('product', 'product_variation') AND p.post_status <> 'trash' AND l.sku IN ({$placeholders})";
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
            foreach ((array) $wpdb->get_results($wpdb->prepare($sql, $batch), ARRAY_A) as $found) {
                $byKey[self::key((string) $found['sku'])][] = [
                    'id'         => (int) $found['id'],
                    'stored_sku' => (string) $found['sku'],
                    'kind'       => $found['post_type'] === 'product_variation' ? 'variation' : 'product',
                ];
            }
        }

        return new self($byKey);
    }

    /**
     * @return array{status:string,candidates:list<array{id:int,stored_sku:string,kind:string,comparison:string,language:string,role:string}>,id?:int,kind?:string,product_type?:string,translations?:list<int>,original_matched?:bool}
     */
    public function resolve(string $sourceSku): array
    {
        $candidates = [];
        foreach ($this->byKey[self::key($sourceSku)] ?? [] as $candidate) {
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
                $identity                          = true;
                $candidates[$index]['language']    = '';
                $candidates[$index]['role']        = '';
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
            $id = (int) $member->element_id;
            if (get_post_status($id) === 'trash' || get_post_status($id) === false) {
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

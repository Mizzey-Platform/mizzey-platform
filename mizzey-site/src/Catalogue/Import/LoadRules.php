<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue\Import;

defined('ABSPATH') || exit;

/**
 * The initial-load rules, version 1: what the live load (#247) will do with a row, and why.
 *
 * The rules are documented in the specification (`contracts/initial-load-rules.md`); this class applies them and
 * nothing else. Every rejection condition is evaluated before create or update, every applicable issue is kept, and
 * the reported rule is the first rejection in a fixed order, so the same row always gets the same answer whatever its
 * position in the file. Required fields differ between create and update: an update needs only what identifies the
 * record, and a blank cell on an update means "no change".
 *
 * Register ids: MIG-06, MIG-19. See specs/006-catalogue-import-spec, FR-005 and FR-006.
 */
final class LoadRules
{
    public const VERSION = 1;

    /** The order in which rejections are reported. */
    private const PRECEDENCE = ['R8', 'R7', 'R6', 'R2', 'R3', 'R4', 'R9', 'R1', 'R5'];

    /** Who corrects each issue. R5 can be either, so its issue carries its own owner. */
    private const OWNER = [
        'R1' => 'client', 'R2' => 'client', 'R3' => 'developer', 'R4' => 'developer', 'R5' => 'client',
        'R6' => 'client', 'R7' => 'client', 'R8' => 'client', 'R9' => 'developer',
        'W1' => 'developer', 'W3' => 'client', 'W4' => 'developer',
    ];

    /** @var array<string, list<string>> */
    private const REQUIRED_CREATE = [
        'simple'    => ['name', 'sku', 'regular_price'],
        'variable'  => ['name', 'sku'],
        'variation' => ['sku', 'parent_sku', 'regular_price', 'attributes'],
    ];

    /** @var array<string, list<string>> */
    private const REQUIRED_UPDATE = [
        'simple'    => ['sku'],
        'variable'  => ['sku'],
        'variation' => ['sku', 'parent_sku'],
    ];

    /**
     * @return array{code:string,field:string,problem:string,correction:string,owner:string}
     */
    public static function issue(string $code, string $field, string $problem, string $correction, ?string $owner = null): array
    {
        return ['code' => $code, 'field' => $field, 'problem' => $problem, 'correction' => $correction, 'owner' => $owner ?? (self::OWNER[$code] ?? 'developer')];
    }

    /**
     * @param array<string, mixed>      $row        From `RowInterpreter`, with issues added by the pass.
     * @param array<string, mixed>      $resolution From `SkuIndex::resolve()`.
     * @param array<string, mixed>|null $parent     For a variation: whether its parent is usable, and why not.
     *
     * @return array{outcome:string,rule:string,reason:string,issues:list<array<string,string>>}
     */
    public static function decide(array $row, array $resolution, ?array $parent, Mapping $mapping): array
    {
        $issues = $row['issues'];
        $type   = $row['type'];
        $update = ($resolution['status'] ?? '') === 'original';

        foreach (self::identityIssues($resolution, $type) as $issue) {
            $issues[] = $issue;
        }
        if ($type !== null) {
            foreach (self::missing($row, $update ? self::REQUIRED_UPDATE[$type] : self::REQUIRED_CREATE[$type]) as $field) {
                $issues[] = self::issue('R1', self::header($field, $mapping), sprintf(
                    /* translators: %s: a store field. */
                    __('The required value "%s" is empty.', 'mizzey-site'),
                    $field,
                ), __('Fill in the value for this row.', 'mizzey-site'));
            }
        }
        if ($type === 'variation' && $parent !== null && ! $parent['usable']) {
            $issues[] = self::issue('R5', self::header('parent_sku', $mapping), $parent['reason'], $parent['correction'], $parent['owner']);
        }
        if (! $update && $type !== null) {
            foreach ($mapping->arabicFields() as $field) {
                if (($row['fields'][$field] ?? '') === '') {
                    $issues[] = self::issue('W3', self::header($field, $mapping), __('The Arabic text is empty.', 'mizzey-site'), __('Add the Arabic text, or confirm the product is English only.', 'mizzey-site'));
                }
            }
        }

        $codes = array_column($issues, 'code');
        foreach (self::PRECEDENCE as $code) {
            if (in_array($code, $codes, true)) {
                $first = $issues[array_search($code, $codes, true)];

                return ['outcome' => 'reject', 'rule' => $code, 'reason' => $first['problem'], 'issues' => $issues];
            }
        }
        if ($update) {
            return ['outcome' => 'update', 'rule' => 'U1', 'reason' => sprintf(
                /* translators: %d: a product or variation id. */
                __('The SKU belongs to the source-language original %d.', 'mizzey-site'),
                $resolution['id'],
            ), 'issues' => $issues];
        }

        return ['outcome' => 'create', 'rule' => 'C1', 'reason' => __('No store record has this SKU.', 'mizzey-site'), 'issues' => $issues];
    }

    /**
     * @param array<string, mixed> $resolution
     *
     * @return list<array<string,string>>
     */
    private static function identityIssues(array $resolution, ?string $type): array
    {
        $issues = [];
        switch ($resolution['status'] ?? 'none') {
            case 'identity':
                $issues[] = self::issue('R3', '', __('A store record with this SKU has no language details, so its original cannot be identified.', 'mizzey-site'), __('Repair the record\'s translation details before the load.', 'mizzey-site'));
                break;
            case 'missing_original':
                $issues[] = self::issue('R3', '', __('The SKU belongs only to a translation whose source-language original is missing or in the trash.', 'mizzey-site'), __('Restore or recreate the original before the load.', 'mizzey-site'));
                break;
            case 'ambiguous':
                $issues[] = self::issue('R4', '', sprintf(
                    /* translators: %s: product ids. */
                    __('The SKU belongs to unrelated store records: %s.', 'mizzey-site'),
                    implode(', ', array_column($resolution['candidates'], 'id')),
                ), __('Give each store record its own SKU before the load.', 'mizzey-site'));
                break;
            case 'original':
                $expected = $type === 'variation' ? 'variation' : 'product';
                if ($type !== null && $resolution['kind'] !== $expected) {
                    $issues[] = self::issue('R9', '', sprintf(
                        /* translators: 1: the store record's kind, 2: the row type. */
                        __('The SKU belongs to a store %1$s, and the row is a %2$s.', 'mizzey-site'),
                        $resolution['kind'],
                        $type,
                    ), __('Correct the row type or the SKU.', 'mizzey-site'));
                }
                if (! $resolution['original_matched']) {
                    $issues[] = self::issue('W4', '', sprintf(
                        /* translators: %d: a product id. */
                        __('The SKU matched a translation; its original %d carries a different SKU.', 'mizzey-site'),
                        $resolution['id'],
                    ), __('Align the SKUs of the translation group.', 'mizzey-site'));
                }
                break;
        }
        foreach ($resolution['candidates'] ?? [] as $candidate) {
            if ($candidate['comparison'] !== 'exact') {
                $issues[] = self::issue('W1', '', sprintf(
                    /* translators: 1: a product id, 2: its stored SKU. */
                    __('Matched record %1$d stores the SKU as "%2$s"; the match ignores case, accents and surrounding spaces, as the store\'s own SKU lookup does.', 'mizzey-site'),
                    $candidate['id'],
                    $candidate['stored_sku'],
                ), __('Check the SKU is meant to be the same product.', 'mizzey-site'));
            }
        }

        return $issues;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string>         $required
     *
     * @return list<string>
     */
    private static function missing(array $row, array $required): array
    {
        $values = [
            'sku'           => $row['sku'],
            'parent_sku'    => $row['parent_sku'],
            'regular_price' => (string) ($row['price'] ?? ''),
            'attributes'    => $row['attributes'] === [] ? '' : 'present',
        ] + $row['fields'];

        return array_values(array_filter($required, static fn (string $field): bool => ($values[$field] ?? '') === ''));
    }

    private static function header(string $field, Mapping $mapping): string
    {
        return $field === 'attributes' ? implode(', ', $mapping->attributeColumns()) : (string) $mapping->columnFor($field);
    }
}
